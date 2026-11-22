<?php
/**
 * /classes/DomainMOD/SslLookup.php
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
 * Reads the certificate a site serves on port 443, for the SSL Queue and the SSL certificate edit page's Refresh. It
 * connects only to a public address (checked, then pinned), sends nothing after the TLS handshake, and never writes to
 * the database, apart from logging a local error. Every value it returns from a certificate is untrusted text, cleaned
 * of characters the database and the exports can't hold safely.
 *
 * fetch() returns array(code, details). The codes are ok, untrusted, expired, name_mismatch, not_yet_valid,
 * bad_certificate, no_dns, not_public, no_connect, no_tls, timeout, local_error, and deferred (a pacing wait that would
 * pass the deadline, so the caller tries again later without counting an attempt).
 */
class SslLookup
{
    // State shared by every SslLookup object in this PHP process (one cron run or page load)
    protected static $last_connect = array(); // when each address was last connected to

    /*
     * Test-only hooks, null outside tests:
     * - $test_resolve: callable($ascii_host), returning the host's addresses as an array of strings, used in place of DNS.
     * - $test_fetch: callable($host, $deadline), returning the array(code, details) of one hostname, used in place of
     *   fetchHost(), so fetch()'s hostname sequence still runs around it.
     * - $test_target: callable($address, $ascii_host), returning the "address:port" to connect to, used after the
     *   address checks have run.
     * - $test_connect_timeout: when true, the connect stage gets the failure a timed-out connection gives (false with the
     *   timeout error number), so the real classifier turns it into timeout.
     * - $test_clock: callable() returning the time, as microtime(true) does.
     * - $test_ca_file: a CA file used in place of the bundle HttpClient::streamSslOptions() picks.
     */
    public static $test_resolve = null;
    public static $test_fetch = null;
    public static $test_target = null;
    public static $test_connect_timeout = null;
    public static $test_clock = null;
    public static $test_ca_file = null;

    const PORT = 443;
    const DEADLINE = 15;             // seconds for one host in the SSL Queue (Refresh passes its own)
    const MIN_LOOKUP_TIME = 10;      // no host, or its www. attempt, starts with less than this many seconds left
    const MIN_RESOLVE_TIME = 5;      // PHP's resolver has no timeout of its own, so it only starts with this much left
    const MAX_CONNECT_TIME = 10;     // seconds for the connection, and again for the handshake
    const MIN_GAP_PER_ADDRESS = 1.0; // seconds between connections to the same address

    // The codes a bare domain can fail with quickly, after which the www. name is tried
    public static $fast_failures = array('no_dns', 'no_connect', 'no_tls', 'name_mismatch');

    // The codes that come with a certificate that can be saved
    public static $saved_codes = array('ok', 'untrusted', 'expired');

    // The codes a later run tries again
    public static $retry_codes = array('timeout', 'local_error');

    // Special-purpose IPv6 ranges that can lead into a local network, which Rdap::isPublicAddress() doesn't list
    public static $denied_ipv6 = array('fec0::/10', '64:ff9b:1::/48', '2001::/23', '2002::/16');

    // The earliest and latest times the database's DATE and DATETIME columns hold (1000-01-01 to 9999-12-31 23:59:59 UTC)
    const MIN_STORED_TIME = -30610224000;
    const MAX_STORED_TIME = 253402300799;

    public $log;
    public $rdap;

    public function __construct()
    {
        $this->log = new Log('class.ssllookup');
        $this->rdap = new Rdap();
    }

    /*
     * Looks up one host (in its stored form) and returns array(code, details). With $try_www, a quick failure on the
     * bare name is followed by one attempt on the www. name, within the same deadline (an absolute time, as
     * microtime(true) gives). A deferral on either name defers the whole lookup.
     */
    public function fetch($host, $deadline, $try_www = false)
    {
        $result = $this->fetchWithTime($host, $deadline);

        // a reset handshake is a timeout (so it's retried), but it comes quickly, so the www. name is still tried
        $fast = in_array($result[0], self::$fast_failures, true) || ($result[0] === 'timeout' && !empty($result[1]['reset']));
        if (!$try_www || !$fast) return $result;

        $www_host = Domain::normalize('www.' . $host);
        if ($www_host === '' || !$this->isHostname(Domain::forApi($www_host))) return $result;

        // checked once here, so a few milliseconds can't turn the bare domain's failure into a timeout
        if ($deadline - $this->now() < self::MIN_LOOKUP_TIME) return $result;

        $www_result = $this->fetchOne($www_host, $deadline);

        if ($www_result[0] === 'deferred' || in_array($www_result[0], self::$saved_codes, true)
            || in_array($www_result[0], self::$retry_codes, true)) {

            return $www_result;

        }

        return $result;
    }

    // One hostname, only when at least MIN_LOOKUP_TIME is left
    protected function fetchWithTime($host, $deadline)
    {
        if ($deadline - $this->now() < self::MIN_LOOKUP_TIME) return array('timeout', $this->emptyDetails($host));

        return $this->fetchOne($host, $deadline);
    }

    // One hostname, through the test hook when one is set
    protected function fetchOne($host, $deadline)
    {
        if (self::$test_fetch !== null) return call_user_func(self::$test_fetch, $host, $deadline);

        return $this->fetchHost($host, $deadline);
    }

    /*
     * Resolves, connects to, and reads the certificate of one hostname, returning the complete array(code, details) a
     * real lookup gives.
     */
    public function fetchHost($host, $deadline)
    {
        $details = $this->emptyDetails($host);
        $ascii_host = strtolower(Domain::forApi($host));

        if (filter_var(trim($ascii_host, '[]'), FILTER_VALIDATE_IP) !== false) return array('not_public', $details);
        if (!$this->isHostname($ascii_host)) return array('no_dns', $details);

        if ($deadline - $this->now() < self::MIN_RESOLVE_TIME) return array('timeout', $details);

        list($code, $address) = $this->resolve($ascii_host, $deadline);
        if ($code !== 'ok') return array($code, $details);

        $target = ((strpos($address, ':') !== false) ? '[' . $address . ']' : $address) . ':' . self::PORT;
        if (self::$test_target !== null) $target = (string) call_user_func(self::$test_target, $address, $ascii_host);

        if (!$this->pace($address, $deadline)) return array('deferred', $details);

        $connect_timeout = min(self::MAX_CONNECT_TIME, ($deadline - $this->now()) / 2);
        if ($connect_timeout < 1) return array('timeout', $details);

        $context = stream_context_create(array('ssl' => array(
            'peer_name' => $ascii_host,
            'SNI_enabled' => true,
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true)));

        $started = $this->now();

        if (self::$test_connect_timeout) {

            $handle = false;
            $error_number = defined('SOCKET_ETIMEDOUT') ? SOCKET_ETIMEDOUT : 110;

        } else {

            $handle = @stream_socket_client('tcp://' . $target, $error_number, $error_message, $connect_timeout,
                STREAM_CLIENT_CONNECT, $context);

        }

        if ($handle === false) {

            return array($this->connectFailure($error_number, $this->now() - $started, $connect_timeout, $deadline), $details);

        }

        // PHP times a blocking handshake with the connect timeout, so this stage gets the same time again
        $started = $this->now();
        error_clear_last();
        $crypto = @stream_socket_enable_crypto($handle, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        if ($crypto !== true) {

            // the warning PHP gave says whether the site reset the connection
            $error = error_get_last();
            $error = is_array($error) ? (string) $error['message'] : '';
            fclose($handle);
            $code = $this->handshakeFailure($this->now() - $started, $connect_timeout, $deadline, $error);
            if ($code === 'timeout' && $this->isReset($error)) $details['reset'] = true;
            return array($code, $details);

        }

        $params = stream_context_get_params($handle);
        fclose($handle);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        $chain = $params['options']['ssl']['peer_certificate_chain'] ?? array();
        if (!$cert instanceof \OpenSSLCertificate) return array('no_tls', $details);

        $result = $this->resultFromCertificate($cert, is_array($chain) ? $chain : array(), $host, (int) floor($this->now()));

        // reading and checking the certificate share the host's deadline too
        if ($this->now() > $deadline) return array('timeout', $details);

        return $result;
    }

    /*
     * The connect stage's failure: timeout when the connect timeout fired or the deadline ran out (so a host that
     * never answers gets its retries), otherwise no_connect (refused, or no route).
     */
    public function connectFailure($error_number, $elapsed, $connect_timeout, $deadline)
    {
        $timed_out_numbers = array(defined('SOCKET_ETIMEDOUT') ? SOCKET_ETIMEDOUT : 110, 110);

        if (in_array((int) $error_number, $timed_out_numbers, true) || $elapsed >= $connect_timeout - 0.05
            || $this->now() >= $deadline) {

            return 'timeout';

        }

        return 'no_connect';
    }

    /*
     * The handshake stage's failure: timeout when it took the whole connect timeout, the deadline ran out, or the site
     * reset the connection (as $error, PHP's warning, says), so a busy site that drops a handshake gets its retries.
     * Otherwise no_tls (the port answered, but not with TLS).
     */
    public function handshakeFailure($elapsed, $connect_timeout, $deadline, $error = '')
    {
        if ($elapsed >= $connect_timeout - 0.05 || $this->now() >= $deadline || $this->isReset($error)) return 'timeout';
        return 'no_tls';
    }

    /*
     * Whether PHP's warning says the site reset the connection. PHP takes the text from the system's own error message,
     * which can be in the server's language, so the system's wording is also checked when the sockets extension can
     * give it. Without that extension, a reset in a language other than English stays no_tls, as it was before.
     */
    public function isReset($error)
    {
        $error = (string) $error;
        if ($error === '') return false;

        // the English wordings on Linux and macOS, then on Windows
        if (stripos($error, 'reset by peer') !== false || stripos($error, 'forcibly closed by the remote host') !== false) {
            return true;
        }

        $system_text = $this->systemResetText();
        return $system_text !== '' && stripos($error, $system_text) !== false;
    }

    // The system's own message for a connection reset, or '' when the sockets extension isn't there to give it
    protected function systemResetText()
    {
        if (!function_exists('socket_strerror') || !defined('SOCKET_ECONNRESET')) return '';
        return (string) socket_strerror(SOCKET_ECONNRESET);
    }

    /*
     * The complete single-host result for a captured certificate and its chain: the name, storage range, and validity
     * checks, then the trust check when the certificate is within its dates, plus the fingerprint, the cleaned issuer,
     * the names, and the dates.
     */
    public function resultFromCertificate($cert, $chain, $host, $now)
    {
        $details = $this->emptyDetails($host);

        $pem = '';
        $parsed = @openssl_x509_parse($cert);
        if (!@openssl_x509_export($cert, $pem) || !is_array($parsed)) return array('bad_certificate', $details);

        $code = $this->readCertificate($pem, $parsed, $host, $now);

        // a certificate that doesn't cover the host, or can't be stored, gives nothing else
        if ($code === 'name_mismatch' || $code === 'bad_certificate') return array($code, $details);

        list(, $dns_names) = $this->dnsNames($pem);
        $details['fingerprint'] = strtolower((string) @openssl_x509_fingerprint($cert, 'sha256'));
        $details['issuer'] = $this->issuerName($parsed);
        $details['names'] = $this->cleanNames((array) $dns_names);
        $details['valid_from'] = $this->certificateTime($parsed, 'validFrom');
        $details['valid_to'] = $this->certificateTime($parsed, 'validTo');
        $details['expiry_date'] = self::utcDate('Y-m-d', $details['valid_to']);

        if ($code === 'expired') {

            $details['reason'] = 'expired';
            return array('expired', $details);

        }

        if ($code === 'not_yet_valid') return array('not_yet_valid', $details);

        list($trust_code, $reason) = $this->checkTrust($cert, $chain);
        $details['trusted'] = $trust_code === 'ok';
        $details['reason'] = $reason;

        return array($trust_code, $details);
    }

    /*
     * The name, storage-range, and validity-window checks for a certificate's PEM and the array openssl_x509_parse()
     * gave for it. The DNS names are read from the PEM's DER, so a PEM that can't be read gives name_mismatch.
     */
    public function readCertificate($pem, $parsed, $host, $now)
    {
        list($read, $dns_names) = $this->dnsNames($pem);
        return $this->classify($parsed, $read ? $dns_names : null, $host, $now);
    }

    /*
     * name_mismatch, bad_certificate (dates the database can't hold, or a start after the expiry), expired,
     * not_yet_valid, or within_dates, checked in that order. $dns_names is the
     * list dnsNames() read (empty when there's no subjectAltName), or null when they couldn't be read, which never
     * matches.
     */
    public function classify($parsed, $dns_names, $host, $now)
    {
        if (!is_array($parsed) || !$this->hostMatches(strtolower(Domain::forApi($host)), $dns_names, $this->commonNames($parsed))) {
            return 'name_mismatch';
        }

        $valid_from = $this->certificateTime($parsed, 'validFrom');
        $valid_to = $this->certificateTime($parsed, 'validTo');

        // PHP gives -1 when it can't convert a certificate's date
        if (!$this->isTime($valid_from) || !$this->isTime($valid_to) || $valid_from === -1 || $valid_to === -1
            || $valid_from < self::MIN_STORED_TIME
            || $valid_from > self::MAX_STORED_TIME || $valid_to < self::MIN_STORED_TIME || $valid_to > self::MAX_STORED_TIME) {

            return 'bad_certificate';

        }

        // a certificate whose window ends before it starts has no valid dates at all
        if ($valid_from > $valid_to) return 'bad_certificate';

        if ($now > $valid_to) return 'expired';
        if ($now < $valid_from) return 'not_yet_valid';

        return 'within_dates';
    }

    /*
     * A certificate's start or end ('validFrom' or 'validTo') as a Unix time, from the array openssl_x509_parse() gave.
     * 64-bit PHP's own time is used as it is. On 32-bit PHP (the official Docker image's armhf build) that time is -1,
     * or cut to 32 bits, for any date outside 1901 to 2038, so there every date is read from the certificate's own text
     * whenever it can be, as an int, or as a whole-number float past the int range. PHP's own value is used only when
     * the text can't be read.
     */
    public function certificateTime($parsed, $field)
    {
        $time = $parsed[$field . '_time_t'] ?? null;
        if (PHP_INT_SIZE >= 8 || !is_string($parsed[$field] ?? null)) return $time;

        $read = $this->asn1Time($parsed[$field]);
        return $read === false ? $time : $read;
    }

    /*
     * A certificate date in its ASN.1 text form, UTCTime (YYMMDDHHMMSSZ, with years 50 to 99 in the 1900s and 00 to 49
     * in the 2000s, as RFC 5280 sets out) or GeneralizedTime (YYYYMMDDHHMMSSZ), as a Unix time: an int, or a
     * whole-number float when it's past PHP's int range (on 32-bit PHP). False for anything else. DateTimeImmutable
     * works with 64-bit times on any PHP, so this never goes through a 32-bit int.
     */
    public function asn1Time($text)
    {
        if (!is_string($text) || preg_match('/^([0-9]{2}|[0-9]{4})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})Z$/D', $text, $parts) !== 1) {
            return false;
        }

        $year = (int) $parts[1];
        if (strlen($parts[1]) === 2) $year += $year < 50 ? 2000 : 1900;
        list(, , $month, $day, $hour, $minute, $second) = array_map('intval', $parts);
        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) return false;

        $date = (new \DateTimeImmutable('@0'))->setDate($year, $month, $day)->setTime($hour, $minute, $second);
        $unix = $date->format('U');
        return (string) (int) $unix === $unix ? (int) $unix : (float) $unix;
    }

    // An int time, or on 32-bit PHP a whole-number float time past the int range
    private function isTime($time)
    {
        return is_int($time) || (PHP_INT_SIZE < 8 && is_float($time) && is_finite($time) && floor($time) === $time);
    }

    // gmdate() for a time from certificateTime(), which on 32-bit PHP can be a float past the int range
    public static function utcDate($format, $time)
    {
        if (is_int($time)) return gmdate($format, $time);
        return (new \DateTimeImmutable('@' . sprintf('%.0f', $time)))->format($format);
    }

    /*
     * Whether the certificate is trusted, for one within its dates: array(code, reason), with code ok, untrusted, or
     * local_error (the intermediates couldn't be written for the check), and reason self_signed or not_trusted for an
     * untrusted one. The intermediates the site sent go to a temporary file, deleted straight away.
     */
    public function checkTrust($cert, $chain)
    {
        $leaf_fingerprint = (string) @openssl_x509_fingerprint($cert, 'sha256');
        $intermediates = '';

        foreach ($chain as $chain_cert) {

            if (!$chain_cert instanceof \OpenSSLCertificate) continue;
            if ((string) @openssl_x509_fingerprint($chain_cert, 'sha256') === $leaf_fingerprint) continue;

            $chain_pem = '';
            if (@openssl_x509_export($chain_cert, $chain_pem)) $intermediates .= $chain_pem;

        }

        $ca_info = array($this->caPath());
        $chain_file = '';

        if ($intermediates !== '') {

            $chain_file = @tempnam(sys_get_temp_dir(), 'domainmod-ssl-');
            $written = is_string($chain_file) && $chain_file !== '' ? @file_put_contents($chain_file, $intermediates) : false;

            if ($written !== strlen($intermediates)) {

                if (is_string($chain_file) && $chain_file !== '') @unlink($chain_file);

                // the log is the only database write the lookup makes, and a failed one doesn't change the result
                try {

                    $log_message = 'Unable to write a temporary file to check an SSL certificate';
                    $log_extra = array('Temporary Folder' => sys_get_temp_dir());
                    $this->log->error($log_message, $log_extra);

                } catch (\Throwable $e) {

                    // nothing more can be done

                }

                return array('local_error', '');

            }

            $check = @openssl_x509_checkpurpose($cert, X509_PURPOSE_SSL_SERVER, $ca_info, $chain_file);
            @unlink($chain_file);

        } else {

            $check = @openssl_x509_checkpurpose($cert, X509_PURPOSE_SSL_SERVER, $ca_info);

        }

        if ($check === true) return array('ok', '');

        return array('untrusted', $this->isSelfSigned($cert) ? 'self_signed' : 'not_trusted');
    }

    // Whether the certificate is signed by its own key (not only issued to the same name it's issued by)
    public function isSelfSigned($cert)
    {
        $key = @openssl_pkey_get_public($cert);
        return $key !== false && @openssl_x509_verify($cert, $key) === 1;
    }

    // The CA file or folder the trust check uses
    protected function caPath()
    {
        if (self::$test_ca_file !== null) return (string) self::$test_ca_file;

        $options = HttpClient::streamSslOptions();
        return (string) ($options['cafile'] ?? $options['capath'] ?? '');
    }

    /*
     * The certificate's DNS names, read from its DER (the base64 body of the PEM), never from the printable
     * subjectAltName string, whose entries can't be split safely. Returns array(read, names): read is false when the
     * PEM can't be decoded, its encoding is malformed, or its subjectAltName holds no DNS names, and true with the
     * names (an empty list only when there's no subjectAltName extension) otherwise. Only dNSName entries are kept, and
     * one that's empty or holds anything but printable ASCII (such as a space or a NUL) makes the whole read fail.
     */
    public function dnsNames($pem)
    {
        if (!preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', (string) $pem, $matches)) {
            return array(false, array());
        }

        $der = base64_decode(preg_replace('/\s+/', '', $matches[1]) ?? '', true);
        if (!is_string($der) || $der === '') return array(false, array());

        // Certificate ::= SEQUENCE { tbsCertificate SEQUENCE, ... }
        $certificate = $this->readTlv($der, 0, strlen($der));
        if ($certificate === null || $certificate[0] !== 0x30 || $certificate[3] !== strlen($der)) return array(false, array());

        $tbs = $this->readTlv($der, $certificate[1], $certificate[1] + $certificate[2]);
        if ($tbs === null || $tbs[0] !== 0x30) return array(false, array());

        $names = array();
        $found_san = false;
        $tbs_end = $tbs[1] + $tbs[2];
        $offset = $tbs[1];

        while ($offset < $tbs_end) {

            $field = $this->readTlv($der, $offset, $tbs_end);
            if ($field === null) return array(false, array());
            $offset = $field[3];

            // extensions: [3] EXPLICIT SEQUENCE OF Extension
            if ($field[0] !== 0xa3) continue;

            $extensions = $this->readTlv($der, $field[1], $field[1] + $field[2]);
            if ($extensions === null || $extensions[0] !== 0x30 || $extensions[3] !== $field[1] + $field[2]) return array(false, array());

            $extensions_end = $extensions[1] + $extensions[2];
            $extension_offset = $extensions[1];

            while ($extension_offset < $extensions_end) {

                // Extension ::= SEQUENCE { extnID OBJECT IDENTIFIER, critical BOOLEAN DEFAULT FALSE, extnValue OCTET STRING }
                $extension = $this->readTlv($der, $extension_offset, $extensions_end);
                if ($extension === null || $extension[0] !== 0x30) return array(false, array());
                $extension_offset = $extension[3];
                $extension_end = $extension[1] + $extension[2];

                $oid = $this->readTlv($der, $extension[1], $extension_end);
                if ($oid === null || $oid[0] !== 0x06) return array(false, array());

                $value = $this->readTlv($der, $oid[3], $extension_end);
                if ($value !== null && $value[0] === 0x01) $value = $this->readTlv($der, $value[3], $extension_end);
                if ($value === null || $value[0] !== 0x04 || $value[3] !== $extension_end) return array(false, array());

                // subjectAltName is 2.5.29.17
                if (substr($der, $oid[1], $oid[2]) !== "\x55\x1d\x11") continue;
                if ($found_san) return array(false, array());
                $found_san = true;

                // GeneralNames ::= SEQUENCE OF GeneralName, where dNSName is [2] IA5String
                $general_names = $this->readTlv($der, $value[1], $value[1] + $value[2]);
                // GeneralNames holds at least one name
                if ($general_names === null || $general_names[0] !== 0x30 || $general_names[2] === 0
                    || $general_names[3] !== $value[1] + $value[2]) {
                    return array(false, array());
                }

                $general_names_end = $general_names[1] + $general_names[2];
                $name_offset = $general_names[1];

                while ($name_offset < $general_names_end) {

                    $general_name = $this->readTlv($der, $name_offset, $general_names_end);
                    if ($general_name === null) return array(false, array());
                    $name_offset = $general_name[3];

                    if (!$this->isGeneralName($der, $general_name)) return array(false, array());
                    if ($general_name[0] !== 0x82) continue;

                    // an IA5String of printable characters with no spaces, or the names can't be relied on
                    $name = substr($der, $general_name[1], $general_name[2]);
                    if (!preg_match('/\A[\x21-\x7e]+\z/', $name)) return array(false, array());
                    $names[] = $name;

                }

                /*
                 * A subjectAltName with no DNS names (only addresses, URIs, and so on) is treated as a failed read, so
                 * the common name is only ever used when there's no subjectAltName at all, as browsers do. The other
                 * forms' contents then never decide a match.
                 */
                if (!$names) return array(false, array());

            }

        }

        return array(true, $names);
    }

    /*
     * Whether a GeneralName is one of the forms RFC 5280 allows, with a payload of the right shape: the constructed
     * [0], [3], [4], and [5] hold whole DER elements, rfc822Name [1] and URI [6] are printable ASCII, iPAddress [7] is
     * 4 or 16 bytes, and registeredID [8] isn't empty. A dNSName [2] is checked by the caller.
     */
    protected function isGeneralName($der, $general_name)
    {
        list($tag, $start, $length, $end) = $general_name;

        if (in_array($tag, array(0xa0, 0xa3, 0xa4, 0xa5), true)) {

            if ($length === 0) return false;

            for ($offset = $start; $offset < $end; $offset = $element[3]) {

                $element = $this->readTlv($der, $offset, $end);
                if ($element === null) return false;

            }

            return true;

        }

        $content = substr($der, $start, $length);

        if ($tag === 0x81 || $tag === 0x86) return (bool) preg_match('/\A[\x20-\x7e]+\z/', $content);
        if ($tag === 0x87) return $length === 4 || $length === 16;
        if ($tag === 0x88) return $length > 0;

        return $tag === 0x82;
    }

    /*
     * One DER element starting at $offset, which must end by $end: array(tag, content start, content length, next
     * offset), or null when it's malformed (a multi-byte tag, an indefinite, oversized, or longer-than-needed length, or
     * a length past $end).
     */
    protected function readTlv($der, $offset, $end)
    {
        if ($offset + 2 > $end || $end > strlen($der)) return null;

        $tag = ord($der[$offset]);
        if (($tag & 0x1f) === 0x1f) return null;

        $length = ord($der[$offset + 1]);
        $position = $offset + 2;

        if ($length & 0x80) {

            $bytes = $length & 0x7f;
            if ($bytes < 1 || $bytes > 4 || $position + $bytes > $end) return null;

            $length = 0;
            for ($i = 0; $i < $bytes; $i++) $length = ($length << 8) | ord($der[$position + $i]);
            $position += $bytes;

            // DER uses the shortest form: a long form only for 128 or more, with no leading zero byte
            if ($length < 0x80 || ord($der[$offset + 2]) === 0) return null;

        }

        if ($length > $end - $position) return null;

        return array($tag, $position, $length, $position + $length);
    }

    /*
     * Whether a certificate covers the host (both in ASCII): a DNS name equal to it, or a wildcard *.example.com when
     * the host is exactly one label under it (RFC 6125). The common names only count when the certificate has no DNS
     * names, and a certificate whose DNS names couldn't be read ($dns_names null) never matches.
     */
    public function hostMatches($ascii_host, $dns_names, $common_names)
    {
        if ($dns_names === null) return false;

        foreach ($dns_names ?: $common_names as $name) {

            if ($this->nameMatches($ascii_host, $name)) return true;

        }

        return false;
    }

    public function nameMatches($ascii_host, $name)
    {
        $host = strtolower((string) $ascii_host);
        $name = strtolower((string) $name);
        if ($host === '' || $name === '') return false;

        if (!str_starts_with($name, '*.')) return $host === $name;

        $parent = substr($name, 2);
        $first_dot = strpos($host, '.');

        return strpos($parent, '.') !== false && strpos($parent, '*') === false && $first_dot !== false && $first_dot > 0
            && substr($host, $first_dot + 1) === $parent;
    }

    /*
     * One line of a pasted list as a host in its stored form (Domain::normalize()), or '' when it isn't one: a
     * hostname (with no path), or an https:// URL (its host, with any path dropped). Wildcards, IP addresses, single-label names, other
     * schemes, user details, and any port other than one written exactly as :443 are refused. The dot and IP address
     * checks run on the normalised name, so full-width digits and a trailing dot can't get past them.
     */
    public function cleanHost($line)
    {
        // a NUL isn't trimmed, so the control character check below refuses it
        $line = trim((string) $line, " \t\n\r\x0B\f");
        if ($line === '') return '';

        $scheme_end = strpos($line, '://');

        if ($scheme_end !== false) {

            if (strtolower(substr($line, 0, $scheme_end)) !== 'https') return '';
            $line = substr($line, $scheme_end + 3);

        } elseif (strpbrk($line, '/?#') !== false) {

            // only a URL can have a path
            return '';

        }

        // the authority: everything before the first /, ?, or #
        $authority = preg_split('/[\/?#]/', $line, 2)[0];
        if ($authority === '' || strpos($authority, '@') !== false || $authority[0] === '[') return '';

        $host = $authority;
        $colon = strrpos($authority, ':');

        if ($colon !== false) {

            if (substr($authority, $colon + 1) !== '443') return '';
            $host = substr($authority, 0, $colon);

        }

        // whitespace and control characters (such as a NUL, which normalising would trim away) aren't part of a host,
        // and text that isn't valid UTF-8 makes preg_match() return false, which is refused too
        if ($host === '' || strpos($host, '*') !== false || preg_match('/[\s\p{Cc}]/u', $host) !== 0) return '';
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) return '';

        $normalized = Domain::normalize($host);
        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_IP) !== false) return '';
        if (strpos($normalized, '.') === false) return '';

        $ascii = strtolower(Domain::forApi($normalized));
        if (!$this->isHostname($ascii) || preg_match('/(^|\.)[0-9]+$/', $ascii)) return '';

        return $normalized;
    }

    public function isHostname($name)
    {
        return $this->rdap->isHostname($name);
    }

    /*
     * array(code, address): ok with the first address, no_dns when the name has none, not_public when any address
     * isn't public, or timeout when the resolver returned after the deadline (whatever it returned).
     */
    public function resolve($ascii_host, $deadline)
    {
        $addresses = array();

        if (self::$test_resolve !== null) {

            $addresses = (array) call_user_func(self::$test_resolve, $ascii_host);

        } else {

            $ipv4_addresses = @gethostbynamel($ascii_host . '.');
            if (is_array($ipv4_addresses)) $addresses = $ipv4_addresses;

            // the second query has no timeout of its own either
            if ($deadline - $this->now() < self::MIN_RESOLVE_TIME) return array('timeout', '');

            $ipv6_records = @dns_get_record($ascii_host . '.', DNS_AAAA);

            if (is_array($ipv6_records)) {

                foreach ($ipv6_records as $record) {

                    if (!empty($record['ipv6'])) $addresses[] = $record['ipv6'];

                }

            }

        }

        if ($this->now() > $deadline) return array('timeout', '');
        if (!$addresses) return array('no_dns', '');

        foreach ($addresses as $address) {

            if (!$this->isPublicAddress((string) $address)) return array('not_public', '');

        }

        return array('ok', (string) $addresses[0]);
    }

    // Rdap::isPublicAddress(), plus the IPv6 ranges it doesn't list that can lead into a local network
    public function isPublicAddress($address)
    {
        if (!$this->rdap->isPublicAddress($address)) return false;

        $packed = @inet_pton($address);
        if ($packed === false || strlen($packed) !== 16) return true;

        foreach (self::$denied_ipv6 as $range) {

            if ($this->inRange($packed, $range)) return false;

        }

        return true;
    }

    protected function inRange($packed, $range)
    {
        list($network, $bits) = explode('/', $range);
        $network_packed = inet_pton($network);
        if ($network_packed === false || strlen($network_packed) !== strlen($packed)) return false;

        $bytes = intdiv((int) $bits, 8);
        $remainder = (int) $bits % 8;
        if (substr($packed, 0, $bytes) !== substr($network_packed, 0, $bytes)) return false;
        if ($remainder === 0) return true;

        $mask = (0xff << (8 - $remainder)) & 0xff;
        return (ord($packed[$bytes]) & $mask) === (ord($network_packed[$bytes]) & $mask);
    }

    /*
     * Waits so connections to the same address are at least MIN_GAP_PER_ADDRESS apart. A wait that would pass the
     * deadline isn't started, and false is returned, so the caller defers the host.
     */
    protected function pace($address, $deadline)
    {
        $address = $this->pacingKey($address);

        if (isset(self::$last_connect[$address])) {

            $wait = self::MIN_GAP_PER_ADDRESS - ($this->now() - self::$last_connect[$address]);

            if ($wait > 0) {

                if ($this->now() + $wait > $deadline) return false;
                usleep((int) ($wait * 1000000));

            }

        }

        self::$last_connect[$address] = $this->now();
        return true;
    }

    // One form per address, so an IPv4 address and its IPv4-mapped IPv6 form (::ffff:a.b.c.d) share a timer
    public function pacingKey($address)
    {
        $packed = @inet_pton((string) $address);
        if ($packed === false) return (string) $address;

        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $packed = substr($packed, 12);
        }

        return (string) inet_ntop($packed);
    }

    // The issuer's organisation, cleaned, or its common name when the organisation cleans to '', or ''
    public function issuerName($parsed)
    {
        $organisation = $this->cleanText($this->firstString($parsed['issuer']['O'] ?? ''));
        if ($organisation !== '') return $organisation;

        return $this->cleanText($this->firstString($parsed['issuer']['CN'] ?? ''));
    }

    // Every subject common name (PHP gives an array when the certificate has more than one)
    public function commonNames($parsed)
    {
        $value = $parsed['subject']['CN'] ?? '';
        $names = array();

        foreach ((array) $value as $name) {

            if (is_string($name) && $name !== '') $names[] = $name;

        }

        return $names;
    }

    // A string as it is, the first non-empty string of an array (in the certificate's order), or '' for anything else
    public function firstString($value)
    {
        if (is_string($value)) return $value;

        if (is_array($value)) {

            foreach ($value as $item) {

                if (is_string($item) && trim($item) !== '') return $item;

            }

        }

        return '';
    }

    /*
     * Certificate text as it's kept: valid UTF-8, without characters outside the Basic Multilingual Plane (the tables use
     * 3-byte utf8), control characters, or Unicode direction controls (so no cell can hide a leading character from a
     * formula check), with whitespace collapsed and trimmed.
     */
    public function cleanText($value)
    {
        $value = mb_scrub((string) $value, 'UTF-8');
        $value = preg_replace('/[\x{10000}-\x{10FFFF}]|[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{061C}]/u', '', $value) ?? '';
        $value = preg_replace('/[\p{Cc}\s]+/u', ' ', $value) ?? '';

        return trim($value);
    }

    // The DNS names, each cleaned on its own, without empty ones or duplicates
    public function cleanNames($names)
    {
        $clean = array();

        foreach ($names as $name) {

            $name = $this->cleanText($name);
            if ($name !== '' && !in_array($name, $clean, true)) $clean[] = $name;

        }

        return $clean;
    }

    protected function emptyDetails($host)
    {
        return array('host' => (string) $host, 'fingerprint' => '', 'issuer' => '', 'names' => array(), 'valid_from' => 0,
            'valid_to' => 0, 'expiry_date' => '', 'trusted' => false, 'reason' => '');
    }

    // The time, from the test clock when one is set
    public function now()
    {
        return self::$test_clock !== null ? (float) call_user_func(self::$test_clock) : microtime(true);
    }

} //@formatter:on
