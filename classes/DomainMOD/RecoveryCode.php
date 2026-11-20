<?php
/**
 * /classes/DomainMOD/RecoveryCode.php
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
 * Recovery codes for two-factor sign-in: ten single-use codes, each 16 characters from the base32 alphabet (80 random
 * bits), shown as XXXX-XXXX-XXXX-XXXX. Only their SHA-256 hashes are stored: 80 random bits make a slow hash
 * unnecessary, and a plain hash lets a code be found with one indexed query. No database access.
 */
class RecoveryCode
{
    const COUNT = 10;
    const LENGTH = 16;

    // Ten different codes, formatted for showing
    public function generate()
    {
        $totp = new Totp();
        $codes = array();
        while (count($codes) < self::COUNT) {
            $codes[$totp->base32Encode(random_bytes(10))] = true;
        }

        return array_map(array($this, 'format'), array_keys($codes));
    }

    // The code as stored before hashing: dashes, spaces, and case don't matter. Returns false for anything that can't
    // be a recovery code.
    public function normalise($input)
    {
        if (!is_string($input)) return false;
        $code = strtoupper(str_replace(array('-', ' '), '', $input));

        return preg_match('/^[A-Z2-7]{' . self::LENGTH . '}$/D', $code) === 1 ? $code : false;
    }

    public function format($code)
    {
        return implode('-', str_split($code, 4));
    }

    // The hash stored for a code, or false if it isn't one
    public function hash($input)
    {
        $code = $this->normalise($input);
        return $code === false ? false : hash('sha256', $code);
    }

} //@formatter:on
