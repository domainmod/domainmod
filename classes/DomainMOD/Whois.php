<?php
/**
 * /classes/DomainMOD/Whois.php
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
 * Looks domains up over WHOIS (port 43), as the fallback for TLDs that have no RDAP server. Each TLD's WHOIS server
 * comes from IANA's root zone database over HTTPS. The WHOIS answer itself can't be authenticated (the protocol has
 * no encryption), so only an expiry date, validated name servers, and a registrar name are taken from it. Results use
 * the same shape as Rdap::lookup(), plus connection_failed, which is true only when the connection to the WHOIS server
 * couldn't be made or broke before any reply, and replied, which is true when the server sent anything back.
 */
class Whois
{
    public $log;
    public $rdap;

    // State shared by every Whois object in this PHP process (one cron run or page load)
    protected static $servers = null;      // TLD => WHOIS server ('' for none) and when it was looked up
    protected static $last_request = array();

    // Test-only hook: when set, it returns the address and port to connect to for a WHOIS server, such as
    // '172.22.0.4:4304' (see the test harness)
    public static $test_resolve = null;

    const PORT = 43;
    const SERVERS_MAX_AGE = 2592000;   // 30 days
    const SERVERS_RETRY_AFTER = 3600;  // after a failed IANA lookup, wait an hour before trying again
    const IANA_MAX_BYTES = 524288;
    const RESPONSE_MAX_BYTES = 65536;
    const MIN_GAP_PER_SERVER = 2.0;    // seconds between queries to the same WHOIS server

    // Servers that need more than the bare domain to answer usefully (English output, domain records only)
    public static $query_formats = array(
        'whois.denic.de' => '-T dn %s',
        'whois.jprs.jp' => '%s/e');

    public function __construct()
    {
        $this->log = new Log('class.whois');
        $this->rdap = new Rdap();
    }

    public function lookup($domain, $deadline = null)
    {
        $deadline = $deadline ?? (microtime(true) + 30);
        $result = array('status' => 'error', 'expiry_date' => '', 'name_servers' => array(), 'registrar_name' => '',
            'registrar_iana_id' => '', 'registrar_url' => '', 'message' => '', 'connection_failed' => false,
            'replied' => false);

        $ascii_domain = Domain::forApi($domain);

        if ($ascii_domain === '' || !$this->rdap->isHostname($ascii_domain)) {

            $result['message'] = 'Not a valid domain name';
            return $result;

        }

        list($found, $server) = $this->serverFor($ascii_domain, $deadline);

        if (!$found) {

            $result['message'] = 'The WHOIS server for this TLD could not be found';
            return $result;

        }

        if ($server === '') {

            $result['status'] = 'no_server';
            $result['message'] = 'No WHOIS server for this TLD';
            return $result;

        }

        list($status, $response, $connection_failed) = $this->query($server, $ascii_domain, $deadline);
        $result['replied'] = $response !== '';

        if ($status !== 'ok') {

            $result['connection_failed'] = $connection_failed;
            $result['message'] = 'The WHOIS lookup failed';
            return $result;

        }

        return $this->parse($response, $ascii_domain) + $result;
    }

    /*
     * array(found, server): found is false when IANA couldn't be asked or its answer couldn't be read (a temporary
     * error). Otherwise, server is the TLD's WHOIS server, or '' when IANA lists none.
     */
    public function serverFor($ascii_domain, $deadline = null)
    {
        $deadline = $deadline ?? (microtime(true) + 30);
        $tld = strtolower(substr(strrchr('.' . $ascii_domain, '.'), 1));
        if ($tld === '' || !$this->rdap->isHostname($tld)) return array(true, '');

        $cache_file = DIR_TEMP . '/whois-servers.json';
        $failed_file = DIR_TEMP . '/whois-servers.failed';

        if (self::$servers === null) {

            $cached = is_readable($cache_file) ? json_decode((string) @file_get_contents($cache_file), true, 4) : null;
            self::$servers = is_array($cached) ? $cached : array();

        }

        $entry = self::$servers[$tld] ?? null;

        if (is_array($entry) && isset($entry['server'], $entry['time']) && time() - (int) $entry['time'] < self::SERVERS_MAX_AGE) {

            return array(true, (string) $entry['server']);

        }

        if (is_readable($failed_file) && time() - (int) @filemtime($failed_file) < self::SERVERS_RETRY_AFTER) {

            return array(false, '');

        }

        list($status, $body) = $this->rdap->fetch('https://www.iana.org/domains/root/db/' . rawurlencode($tld) . '.html',
            $deadline, self::IANA_MAX_BYTES, false);

        if ($status !== 'ok') {

            @touch($failed_file);

            $log_message = 'Unable to look up the WHOIS server for a TLD at IANA';
            $log_extra = array('TLD' => $tld);
            $this->log->warning($log_message, $log_extra);

            return array(false, '');

        }

        // IANA shows "-" when a TLD has no WHOIS server, so anything else that isn't a host name means the page changed
        $value = preg_match('#WHOIS Server\s*</div>\s*<div[^>]*>([^<]*)</div>#i', $body, $matches) ? strtolower(rtrim(trim($matches[1]), '.')) : '';

        if ($value === '-') {

            $server = '';

        } elseif ($this->rdap->isHostname($value) && filter_var($value, FILTER_VALIDATE_IP) === false) {

            $server = $value;

        } else {

            @touch($failed_file);

            $log_message = 'Unable to read the WHOIS server for a TLD from IANA';
            $log_extra = array('TLD' => $tld);
            $this->log->warning($log_message, $log_extra);

            return array(false, '');

        }

        self::$servers[$tld] = array('server' => $server, 'time' => time());

        // write a temporary file and rename it over the cache, so readers never see a partial file
        $temp_file = $cache_file . '.' . getmypid() . '.tmp';

        if (@file_put_contents($temp_file, json_encode(self::$servers)) !== false && @rename($temp_file, $cache_file)) {
            @unlink($failed_file);
        } else {
            @unlink($temp_file);
        }

        return array(true, $server);
    }

    /*
     * One WHOIS query: array(status, response, connection_failed). On an error, response holds whatever arrived
     * before it (only to show the server replied, never parsed). The whole transfer is bounded by the deadline (the
     * time left is applied before every write and read), and a timeout, a read error, or a response over the size cap
     * is an error, so a partial response is never parsed.
     */
    protected function query($server, $ascii_domain, $deadline)
    {
        if (self::$test_resolve !== null) {

            $target = (string) call_user_func(self::$test_resolve, $server);

        } else {

            // PHP's resolver has no per-call timeout, so only start it with at least 5 seconds left
            if ($deadline - microtime(true) < 5) return array('error', '', false);
            $address = $this->rdap->publicAddress($server);
            $target = ($address === '') ? '' : ((strpos($address, ':') !== false) ? '[' . $address . ']' : $address) . ':' . self::PORT;

        }

        if ($target === '') return array('error', '', true);

        if (!$this->pace($server, $deadline)) return array('error', '', false);

        $remaining = $deadline - microtime(true);
        if ($remaining < 1) return array('error', '', false);

        $handle = @stream_socket_client('tcp://' . $target, $error_number, $error_message, min(10, $remaining));

        if ($handle === false) {

            $log_message = 'Unable to connect to a WHOIS server';
            $log_extra = array('Server' => $server, 'Error' => $error_number . ': ' . $error_message);
            $this->log->notice($log_message, $log_extra);

            return array('error', '', true);

        }

        $query = sprintf(self::$query_formats[$server] ?? '%s', $ascii_domain) . "\r\n";

        if (!$this->setTimeout($handle, $deadline) || @fwrite($handle, $query) !== strlen($query)) {

            fclose($handle);
            return array('error', '', true);

        }

        $response = '';

        while (!feof($handle)) {

            if (!$this->setTimeout($handle, $deadline)) {

                fclose($handle);
                return array('error', $response, false);

            }

            $chunk = @fread($handle, 8192);
            $meta = stream_get_meta_data($handle);

            if ($chunk === false || !empty($meta['timed_out'])) {

                fclose($handle);
                return array('error', $response, $response === '');

            }

            $response .= $chunk;

            if (strlen($response) > self::RESPONSE_MAX_BYTES) {

                fclose($handle);
                return array('error', $response, false);

            }

        }

        fclose($handle);

        if ($response === '') return array('error', '', true);

        // some servers answer in ISO-8859-1
        if (!mb_check_encoding($response, 'UTF-8')) $response = mb_convert_encoding($response, 'UTF-8', 'ISO-8859-1');

        return array('ok', $response, false);
    }

    // Applies the time left before the deadline to the next read or write (false once it has passed)
    protected function setTimeout($handle, $deadline)
    {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) return false;

        $seconds = (int) floor($remaining);
        return stream_set_timeout($handle, $seconds, (int) (($remaining - $seconds) * 1000000));
    }

    // Waits so queries to the same server are at least MIN_GAP_PER_SERVER apart (false, without waiting, when the wait
    // would leave less than a second before the deadline)
    protected function pace($server, $deadline)
    {
        if (isset(self::$last_request[$server])) {

            $wait = self::MIN_GAP_PER_SERVER - (microtime(true) - self::$last_request[$server]);
            if ($wait > 0 && microtime(true) + $wait > $deadline - 1) return false;
            if ($wait > 0) usleep((int) ($wait * 1000000));

        }

        self::$last_request[$server] = microtime(true);
        return true;
    }

    /*
     * The details in a WHOIS response: status (ok, not_found, or rate_limited), expiry_date, name_servers,
     * registrar_name, and registrar_iana_id. An expiry date always wins, so a full record whose notices mention "no
     * match" or rate limits is never treated as a failure.
     */
    public function parse($response, $ascii_domain)
    {
        $lines = preg_split('/\r\n|\r|\n/', $response);

        $expiry_date = '';
        $name_servers = array();
        $registrar_name = '';
        $registrar_iana_id = '';
        $in_name_server_block = false;
        $in_registrar_block = false;

        foreach ($lines as $line) {

            $trimmed = trim($line);

            // an indented block under a heading of its own (".it" lists name servers and the registrar this way)
            if ($in_name_server_block || $in_registrar_block) {

                if ($trimmed === '' || !preg_match('/^\s/', $line)) {

                    $in_name_server_block = false;
                    $in_registrar_block = false;

                } elseif ($in_name_server_block) {

                    $this->addNameServer($name_servers, $trimmed);
                    continue;

                } else {

                    if ($registrar_name === '' && preg_match('/^(Name|Organization)\s*:\s*(.+)$/i', $trimmed, $matches)) {
                        $registrar_name = trim($matches[2]);
                    }
                    continue;

                }

            }

            if (preg_match('/^Nameservers?$/i', $trimmed)) {

                $in_name_server_block = true;
                continue;

            }

            if (preg_match('/^Registrar$/i', $trimmed)) {

                $in_registrar_block = true;
                continue;

            }

            if ($expiry_date === '' && preg_match('/^(Registry Expiry Date|Registrar Registration Expiration Date|Expiration Date|Expiry Date|Expire Date|Expires On|Expiration Time|paid-till|\[Expires on\])\s*:?\s*(.+)$/i', $trimmed, $matches)) {

                $expiry_date = $this->dateFrom(trim($matches[2]));

            } elseif (preg_match('/^(Name Server|nserver|\[Name Server\])\s*:?\s*(.+)$/i', $trimmed, $matches)) {

                $this->addNameServer($name_servers, trim($matches[2]));

            } elseif ($registrar_name === '' && preg_match('/^(Registrar|Sponsoring Registrar|Registrar Name)\s*:\s*(.+)$/i', $trimmed, $matches)) {

                $registrar_name = trim($matches[2]);

            } elseif ($registrar_iana_id === '' && preg_match('/^Registrar IANA ID\s*:\s*(\d+)$/i', $trimmed, $matches)) {

                if ((int) $matches[1] > 0) $registrar_iana_id = (string) (int) $matches[1];

            }

        }

        $details = array('status' => 'ok', 'expiry_date' => $expiry_date, 'name_servers' => $name_servers,
            'registrar_name' => $registrar_name, 'registrar_iana_id' => $registrar_iana_id);

        if ($expiry_date !== '') return $details;

        if ($this->isNotFound($lines, $ascii_domain)) {

            $details['status'] = 'not_found';

        } elseif (strlen($response) < 1000 && preg_match('/limit exceeded|query rate limit exceeded|too many queries/i', $response)) {

            $details['status'] = 'rate_limited';

        }

        return $details;
    }

    // A whole line (comments ignored) that says the domain isn't registered
    protected function isNotFound($lines, $ascii_domain)
    {
        $domain = preg_quote(strtolower($ascii_domain), '/');

        foreach ($lines as $line) {

            $trimmed = strtolower(trim($line));
            if ($trimmed === '' || $trimmed[0] === '%' || $trimmed[0] === '#') continue;

            if (preg_match('/^(no match for "?' . $domain . '"?\.?|not found\.?|domain not found\.|no data found\.?|status:\s*(available|free)|no entries found for the selected source\(s\)\.)$/', $trimmed)) {

                return true;

            }

        }

        return false;
    }

    // A WHOIS name server value (sometimes followed by its IP addresses), kept if it's a valid host name, at most 10
    protected function addNameServer(&$name_servers, $value)
    {
        $parts = preg_split('/\s+/', $value);
        $name = strtolower(rtrim((string) ($parts[0] ?? ''), '.'));
        if (count($name_servers) < 10 && $this->rdap->isHostname($name) && !in_array($name, $name_servers, true)) $name_servers[] = $name;
    }

    /*
     * A WHOIS date as Y-m-d, or '' unless it's in a form that can't be misread: RFC 3339 or ISO 8601 (converted to
     * UTC when it has a time zone), YYYY-MM-DD, YYYY/MM/DD, YYYY.MM.DD, or DD-Mon-YYYY with an English month name,
     * optionally followed by UTC, GMT, or a note in brackets. Anything else after the date, such as an invalid time, is
     * rejected.
     * Something like 01/02/2027, which could be either order, is rejected.
     */
    protected function dateFrom($value)
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})([T ]([01]\d|2[0-3]):[0-5]\d(:[0-5]\d(\.\d+)?)?(Z|[+-]((0\d|1[0-3]):[0-5]\d|14:00))?)?(\s+(UTC|GMT|\([^()]*\)))?$/i', $value, $matches)) {

            if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) return '';

            if (!empty($matches[8])) {

                try {

                    $expiry_time = new \DateTimeImmutable(str_replace(' ', 'T', substr($value, 0, strlen($matches[0]) - strlen($matches[11] ?? ''))), new \DateTimeZone('UTC'));

                } catch (\Exception $e) {

                    return '';

                }

                return $expiry_time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');

            }

            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];

        }

        if (preg_match('/^(\d{4})[\/.](\d{2})[\/.](\d{2})(\s+(UTC|GMT|\([^()]*\)))?$/i', $value, $matches)) {

            return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) ? $matches[1] . '-' . $matches[2] . '-' . $matches[3] : '';

        }

        if (preg_match('/^(\d{1,2})-(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)-(\d{4})(\s+(UTC|GMT|\([^()]*\)))?$/i', $value, $matches)) {

            $month = array_search(strtolower($matches[2]), array('jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec')) + 1;
            return checkdate($month, (int) $matches[1], (int) $matches[3]) ? sprintf('%04d-%02d-%02d', (int) $matches[3], $month, (int) $matches[1]) : '';

        }

        return '';
    }
} //@formatter:on
