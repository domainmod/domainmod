<?php
/**
 * /classes/DomainMOD/Totp.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
//@formatter:off
namespace DomainMOD;

/*
 * Time-based one-time codes for two-factor sign-in: HOTP (RFC 4226) with HMAC-SHA1, used as TOTP (RFC 6238) with
 * 30-second steps and six digits, the settings every authenticator app supports. Secrets are raw bytes here, and
 * base32 (RFC 4648) only when shown to the user or put in the QR code's address. No database access.
 */
class Totp
{
    const PERIOD = 30;
    const DIGITS = 6;
    // One step either side of the current one (90 seconds in all) covers normal clock drift
    const WINDOW = 1;
    const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // 20 random bytes (160 bits), as RFC 4226 recommends
    public function newSecret()
    {
        return random_bytes(20);
    }

    public function base32Encode($bytes)
    {
        $bits = '';
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if ($chunk === '') continue;
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    // Accepts lower case, spaces, and '=' padding, as people copy secrets in different ways. Returns false for any
    // other character.
    public function base32Decode($text)
    {
        if (!is_string($text)) return false;
        $text = strtoupper(str_replace(array(' ', '='), '', $text));

        $bits = '';
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $value = strpos(self::BASE32_ALPHABET, $text[$i]);
            if ($value === false) return false;
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            // The leftover bits at the end are padding, not a byte
            if (strlen($chunk) === 8) $bytes .= chr(bindec($chunk));
        }

        return $bytes;
    }

    // HOTP: the step is the counter, as a 64-bit big-endian number
    public function code($secret, $step, $digits = self::DIGITS)
    {
        $hash = hash_hmac('sha1', pack('J', $step), $secret, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public function step($unix_time)
    {
        return intdiv((int) $unix_time, self::PERIOD);
    }

    // Spaces removed, and only six digits count
    public function normalise($code)
    {
        if (!is_string($code)) return false;
        $code = str_replace(' ', '', $code);
        return preg_match('/^[0-9]{6}$/D', $code) === 1 ? $code : false;
    }

    /*
     * Returns the matching step, or false. Every step in the window is checked without stopping early, so the time
     * taken doesn't say which step matched, and only a step after $last_step counts, so a code can't be used twice. If
     * two steps happen to give the same code, the later one in the window is returned, and a code that's the same as
     * the one for $last_step is refused while that step is recent, so a used code can't come back when the window
     * moves on to a later step that shares it.
     */
    public function verify($secret, $code, $unix_time, $last_step)
    {
        $code = $this->normalise($code);
        if ($code === false || !is_string($secret) || $secret === '') return false;

        $current = $this->step($unix_time);
        $last_step = (int) $last_step;
        $matched = false;
        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            $step = $current + $offset;
            if (hash_equals($this->code($secret, $step), $code) && $step > $last_step) $matched = $step;
        }

        $last_is_recent = $last_step > 0 && $last_step >= $current - 2 * self::WINDOW;
        if ($last_is_recent && hash_equals($this->code($secret, $last_step), $code)) return false;

        return $matched;
    }

    /*
     * For setup only: when a code is wrong, whether it matches a step within ten minutes either side, as the number of
     * minutes the phone is ahead of the server (negative when it's behind), or false. It never accepts a code.
     */
    public function clockOffsetMinutes($secret, $code, $unix_time)
    {
        $code = $this->normalise($code);
        if ($code === false || !is_string($secret) || $secret === '') return false;

        $current = $this->step($unix_time);
        $steps = intdiv(600, self::PERIOD);
        $found = false;
        for ($offset = -$steps; $offset <= $steps; $offset++) {
            if (abs($offset) <= self::WINDOW) continue;
            if (hash_equals($this->code($secret, $current + $offset), $code) && $found === false) $found = $offset;
        }

        return $found === false ? false : (int) round($found * self::PERIOD / 60);
    }

    /*
     * The address an authenticator app reads from the QR code. The account is <username>@<host>, so people with several
     * installs can tell them apart. A colon separates the issuer from the account in the label, even encoded, so any
     * other colon (such as a host's port) becomes a hyphen there.
     */
    public function otpauthUri($secret, $account, $issuer)
    {
        $label = rawurlencode(str_replace(':', '-', $issuer)) . ':' . rawurlencode(str_replace(':', '-', $account));
        $query = 'secret=' . $this->base32Encode($secret) . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;

        return 'otpauth://totp/' . $label . '?' . $query;
    }

} //@formatter:on
