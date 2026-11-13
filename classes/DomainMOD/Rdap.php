<?php
/**
 * /classes/DomainMOD/Rdap.php
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
 * Looks domains up in RDAP (the JSON replacement for WHOIS), using IANA's bootstrap file to find each TLD's server. It
 * has no ties to the Domain Queue, so other features can use it. Every outbound request is HTTPS to a public address
 * (checked, then pinned), size-limited, and bounded by the caller's deadline.
 */
class Rdap
{
    public $log;

    // State shared by every Rdap object in this PHP process (one cron run or page load)
    protected static $bootstrap = null;
    protected static $last_request = array();          // when each RDAP server was last asked
    protected static $failed_registrar_hosts = array(); // registrar servers that failed or limited requests this run

    const BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';
    const BOOTSTRAP_MAX_AGE = 604800;   // 7 days
    const BOOTSTRAP_RETRY_AFTER = 3600; // after a failed refresh, wait an hour before trying again
    const BOOTSTRAP_MAX_BYTES = 2097152;
    const LOOKUP_MAX_BYTES = 1048576;
    const MAX_REDIRECTS = 3;
    const MIN_GAP_PER_SERVER = 1.0;     // seconds between requests to the same RDAP server

    public function __construct()
    {
        $this->log = new Log('class.rdap');
    }

    /*
     * Looks up one domain. $deadline is an absolute time (microtime(true)) by which every request must finish; no new
     * request starts after it. Returns status (ok, not_found, no_server, rate_limited, or error), expiry_date (Y-m-d or
     * ''), name_servers, registrar_name, registrar_iana_id, registrar_url, and message (plain text, never taken from
     * the response).
     */
    public function lookup($domain, $deadline = null)
    {
        $deadline = $deadline ?? (microtime(true) + 30);
        $result = array('status' => 'error', 'expiry_date' => '', 'name_servers' => array(), 'registrar_name' => '',
            'registrar_iana_id' => '', 'registrar_url' => '', 'message' => '');

        // registries take the punycode form of the domain (xn--mnchen-3ya.de for münchen.de)
        $ascii_domain = Domain::forApi($domain);

        if ($ascii_domain === '' || !$this->isHostname($ascii_domain)) {

            $result['message'] = 'Not a valid domain name';
            return $result;

        }

        // without IANA's bootstrap file (no cached copy and the download failed) nothing can be looked up yet; that's
        // a temporary error, not a TLD without RDAP
        if (!$this->bootstrap($deadline)) {

            $result['message'] = 'The RDAP bootstrap file is unavailable';
            return $result;

        }

        $server = $this->serverFor($ascii_domain, $deadline);

        if ($server === '') {

            $result['status'] = 'no_server';
            $result['message'] = 'No RDAP server for this TLD';
            return $result;

        }

        list($status, $data) = $this->fetchJson($server . 'domain/' . rawurlencode($ascii_domain), $deadline);

        if ($status !== 'ok') {

            $result['status'] = $status;
            $result['message'] = $this->statusMessage($status);
            return $result;

        }

        $result['status'] = 'ok';
        $result['expiry_date'] = $this->expiryFrom($data);
        $result['name_servers'] = $this->nameServersFrom($data);
        list($result['registrar_name'], $result['registrar_iana_id'], $result['registrar_url']) = $this->registrarFrom($data);

        /*
         * A registry can push its expiry date a year ahead during the auto-renew grace period, so the registrar's own
         * RDAP record (linked as "related") is preferred when it answers, with at least 5 seconds left. A registrar
         * server that failed or limited requests isn't asked again in this process; the registry's answer is kept
         * either way.
         */
        $related = $this->relatedLink($data);
        $related_host = strtolower((string) parse_url($related, PHP_URL_HOST));

        if ($related !== '' && !isset(self::$failed_registrar_hosts[$related_host]) && $deadline - microtime(true) >= 5) {

            list($registrar_status, $registrar_data) = $this->fetchJson($related, $deadline);

            if ($registrar_status === 'ok') {

                $registrar_expiry = $this->expiryFrom($registrar_data);
                if ($registrar_expiry !== '') $result['expiry_date'] = $registrar_expiry;

            } elseif ($registrar_status !== 'not_found') {

                self::$failed_registrar_hosts[$related_host] = true;

            }

        }

        return $result;
    }

    // The RDAP base URL (ending in "/") for the domain's TLD, or '' if the bootstrap has none
    public function serverFor($ascii_domain, $deadline = null)
    {
        $services = $this->bootstrap($deadline ?? (microtime(true) + 30));
        if (!$services) return '';

        // the longest matching suffix wins (entries are TLDs today, but RFC 9224 allows longer ones)
        $labels = explode('.', strtolower($ascii_domain));

        for ($i = 1; $i < count($labels); $i++) {

            $suffix = implode('.', array_slice($labels, $i));
            if (isset($services[$suffix])) return $services[$suffix];

        }

        return '';
    }

    // Whether the IANA bootstrap is available, so an empty serverFor() really means the TLD has no RDAP server
    public function hasBootstrap($deadline = null)
    {
        return (bool) $this->bootstrap($deadline ?? (microtime(true) + 30));
    }

    public function statusMessage($status)
    {
        if ($status === 'not_found') {
            $message = 'Domain not found in RDAP';
        } elseif ($status === 'rate_limited') {
            $message = 'The RDAP server is limiting requests';
        } else {
            $message = 'The RDAP lookup failed';
        }
        return $message;
    }

    // Returns the TLD => server map, from memory, the cache file, or a fresh download
    protected function bootstrap($deadline)
    {
        if (self::$bootstrap !== null) return self::$bootstrap;

        $cache_file = DIR_TEMP . '/rdap-dns.json';
        $failed_file = DIR_TEMP . '/rdap-dns.failed';

        $cached = is_readable($cache_file) ? $this->parseBootstrap((string) @file_get_contents($cache_file)) : null;
        $fresh = $cached && (time() - (int) @filemtime($cache_file)) < self::BOOTSTRAP_MAX_AGE;
        $recently_failed = is_readable($failed_file) && (time() - (int) @filemtime($failed_file)) < self::BOOTSTRAP_RETRY_AFTER;

        if (!$fresh && !$recently_failed) {

            list($status, $body) = $this->fetch(self::BOOTSTRAP_URL, $deadline, self::BOOTSTRAP_MAX_BYTES, false);
            $downloaded = ($status === 'ok') ? $this->parseBootstrap($body) : null;

            if ($downloaded) {

                // write a temporary file and rename it over the cache, so readers never see a partial file
                $temp_file = $cache_file . '.' . getmypid() . '.tmp';

                if (@file_put_contents($temp_file, $body) !== false && @rename($temp_file, $cache_file)) {
                    @unlink($failed_file);
                } else {
                    @unlink($temp_file);
                }

                $cached = $downloaded;

            } else {

                @touch($failed_file);

                $log_message = 'Unable to refresh the RDAP bootstrap file';
                $log_extra = array('Using Cached Copy' => $cached ? 'Yes' : 'No');
                $this->log->warning($log_message, $log_extra);

            }

        }

        self::$bootstrap = $cached ?: array();
        return self::$bootstrap;
    }

    // A valid bootstrap file as a TLD => server map, or null
    protected function parseBootstrap($body)
    {
        $data = json_decode($body, true, 16);
        if (!is_array($data) || !isset($data['services']) || !is_array($data['services'])) return null;

        $map = array();

        foreach ($data['services'] as $service) {

            if (!is_array($service) || count($service) < 2 || !is_array($service[0]) || !is_array($service[1])) continue;

            $server = '';

            foreach ($service[1] as $url) {

                if (is_string($url) && stripos($url, 'https://') === 0) {

                    $server = rtrim($url, '/') . '/';
                    break;

                }

            }

            if ($server === '') continue;

            foreach ($service[0] as $tld) {

                if (is_string($tld) && $tld !== '') $map[strtolower($tld)] = $server;

            }

        }

        return $map ?: null;
    }

    // Fetches and decodes an RDAP response: array(status, data)
    protected function fetchJson($url, $deadline)
    {
        list($status, $body) = $this->fetch($url, $deadline, self::LOOKUP_MAX_BYTES, true);
        if ($status !== 'ok') return array($status, null);

        $data = json_decode($body, true, 32);
        return is_array($data) ? array('ok', $data) : array('error', null);
    }

    /*
     * One HTTPS GET with the destination checks: https on port 443, a hostname (not an IP literal) that resolves only
     * to public addresses, with the checked address pinned so DNS can't swap it; redirects followed by hand (at most
     * three, each checked the same way); the body capped while it streams. Returns array(status, body). Also used by
     * Whois for its HTTPS requests to IANA.
     */
    public function fetch($url, $deadline, $max_bytes, $paced)
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {

            $parts = parse_url($url);

            if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
                || (isset($parts['port']) && (int) $parts['port'] !== 443) || isset($parts['user']) || isset($parts['pass'])) {

                return array('error', '');

            }

            $host = strtolower($parts['host']);
            if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false || !$this->isHostname($host)) return array('error', '');

            // PHP's resolver has no per-call timeout, so only start it with at least 5 seconds left
            if ($deadline - microtime(true) < 5) return array('error', '');

            $address = $this->publicAddress($host);
            if ($address === '') return array('error', '');

            if ($paced) $this->pace($host);

            $remaining = $deadline - microtime(true);
            if ($remaining < 1) return array('error', '');

            $body = '';
            $too_big = false;
            $location = '';
            $pinned_address = (strpos($address, ':') !== false) ? '[' . $address . ']' : $address;

            $handle = HttpClient::init($url, (int) max(1, min(10, $remaining)), (int) max(1, min(20, $remaining)));
            curl_setopt($handle, CURLOPT_RESOLVE, array($host . ':443:' . $pinned_address));
            curl_setopt($handle, CURLOPT_HTTPHEADER, array('Accept: application/rdap+json, application/json'));
            curl_setopt($handle, CURLOPT_USERAGENT, 'DomainMOD/' . (defined('SOFTWARE_VERSION') ? SOFTWARE_VERSION : ''));
            curl_setopt($handle, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) use (&$body, &$too_big, $max_bytes) {

                if (strlen($body) + strlen($chunk) > $max_bytes) {

                    $too_big = true;
                    return 0;

                }

                $body .= $chunk;
                return strlen($chunk);

            });
            curl_setopt($handle, CURLOPT_HEADERFUNCTION, function ($curl, $line) use (&$location) {

                if (stripos($line, 'Location:') === 0) $location = trim(substr($line, 9));
                return strlen($line);

            });

            $curl_result = curl_exec($handle);
            $http_code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            // No curl_close(): it has done nothing since PHP 8.0 and is deprecated in 8.5

            if ($too_big) return array('error', '');

            // Some servers (Verisign among them) close the connection without a TLS close_notify after a 404, so cURL
            // reports an error even though the status line arrived; a 404 or 429 still counts, nothing else does
            if ($curl_result === false) {

                if ($http_code === 404) return array('not_found', '');
                if ($http_code === 429) return array('rate_limited', '');
                return array('error', '');

            }

            if (in_array($http_code, array(301, 302, 303, 307, 308), true) && $location !== '') {

                $url = $this->absoluteUrl($location, $parts);
                continue;

            }

            if ($http_code === 200) return array('ok', $body);
            if ($http_code === 404) return array('not_found', '');
            if ($http_code === 429) return array('rate_limited', '');
            return array('error', '');

        }

        return array('error', '');
    }

    // Waits so requests to the same server are at least MIN_GAP_PER_SERVER apart
    protected function pace($host)
    {
        if (isset(self::$last_request[$host])) {

            $wait = self::MIN_GAP_PER_SERVER - (microtime(true) - self::$last_request[$host]);
            if ($wait > 0) usleep((int) ($wait * 1000000));

        }

        self::$last_request[$host] = microtime(true);
    }

    // Resolves a redirect's Location against the URL that returned it (RFC 3986 section 5.2); the result is checked
    // again by fetch() before it's requested
    protected function absoluteUrl($location, $base_parts)
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) return $location;
        if (strpos($location, '//') === 0) return 'https:' . $location;

        $query = '';

        $query_start = strpos($location, '?');
        if ($query_start !== false) {

            $query = substr($location, $query_start);
            $location = substr($location, 0, $query_start);

        }

        $fragment_start = strpos($location, '#');
        if ($fragment_start !== false) $location = substr($location, 0, $fragment_start);

        if ($location === '') {

            $path = $base_parts['path'] ?? '/';
            if ($query === '' && isset($base_parts['query'])) $query = '?' . $base_parts['query'];

        } elseif ($location[0] === '/') {

            $path = $location;

        } else {

            $base_path = $base_parts['path'] ?? '/';
            $path = substr($base_path, 0, (int) strrpos($base_path, '/') + 1) . $location;

        }

        // remove "." and ".." segments
        $output = array();

        foreach (explode('/', $path) as $segment) {

            if ($segment === '..') {

                if (count($output) > 1) array_pop($output);

            } elseif ($segment !== '.') {

                $output[] = $segment;

            }

        }

        $ends_in_dot_segment = substr($path, -3) === '/..' || substr($path, -2) === '/.' || $path === '..' || $path === '.';
        $path = implode('/', $output) . ($ends_in_dot_segment ? '/' : '');
        if ($path === '' || $path[0] !== '/') $path = '/' . $path;

        return 'https://' . $base_parts['host'] . $path . $query;
    }

    // One public address for the host, or '' if it doesn't resolve or any of its addresses isn't public
    public function publicAddress($host)
    {
        $addresses = array();

        $ipv4_addresses = @gethostbynamel($host . '.');
        if (is_array($ipv4_addresses)) $addresses = $ipv4_addresses;

        $ipv6_records = @dns_get_record($host . '.', DNS_AAAA);

        if (is_array($ipv6_records)) {

            foreach ($ipv6_records as $record) {

                if (!empty($record['ipv6'])) $addresses[] = $record['ipv6'];

            }

        }

        if (!$addresses) return '';

        foreach ($addresses as $address) {

            if (!$this->isPublicAddress($address)) return '';

        }

        return $addresses[0];
    }

    // False for every IANA special-use range listed in the plan, and anything that isn't a valid address
    public function isPublicAddress($address)
    {
        $packed = @inet_pton($address);
        if ($packed === false) return false;

        if (strlen($packed) === 16) {

            // IPv4-mapped (::ffff:0:0/96) is checked as the IPv4 address it carries
            if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {

                return $this->isPublicAddress(inet_ntop(substr($packed, 12)));

            }

            $denied = array('::/128', '::1/128', '64:ff9b::/96', '100::/64', '2001:db8::/32', 'fc00::/7', 'fe80::/10',
                'ff00::/8');

        } else {

            $denied = array('0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
                '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24',
                '224.0.0.0/3');

        }

        foreach ($denied as $range) {

            list($network, $bits) = explode('/', $range);
            $network_packed = inet_pton($network);
            if (strlen($network_packed) !== strlen($packed)) continue;

            $bytes = intdiv((int) $bits, 8);
            $remainder = (int) $bits % 8;
            if (substr($packed, 0, $bytes) !== substr($network_packed, 0, $bytes)) continue;
            if ($remainder === 0) return false;

            $mask = (0xff << (8 - $remainder)) & 0xff;
            if ((ord($packed[$bytes]) & $mask) === (ord($network_packed[$bytes]) & $mask)) return false;

        }

        return true;
    }

    // An ASCII hostname: LDH labels of 1-63 characters, at most 253 in total, no trailing dot
    public function isHostname($name)
    {
        if (!is_string($name) || $name === '' || strlen($name) > 253) return false;

        foreach (explode('.', $name) as $label) {

            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $label)) return false;

        }

        return true;
    }

    // The expiration event as a UTC Y-m-d date, or '' if there's none or it can't be read
    protected function expiryFrom($data)
    {
        foreach ((array) ($data['events'] ?? array()) as $event) {

            if (!is_array($event) || ($event['eventAction'] ?? '') !== 'expiration') continue;

            $date = $event['eventDate'] ?? '';

            // RFC 3339: a time of 00:00:00 to 23:59:59 (optional fraction) and an offset of at most 14:00, so nothing is
            // quietly rolled into another day
            if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})(T([01]\d|2[0-3]):[0-5]\d(:[0-5]\d(\.\d+)?)?(Z|[+-]((0\d|1[0-3]):[0-5]\d|14:00))?)?$/i', $date, $date_parts)) return '';

            // DateTimeImmutable turns 2027-02-30 into 2027-03-02, so check the calendar date first
            if (!checkdate((int) $date_parts[2], (int) $date_parts[3], (int) $date_parts[1])) return '';

            try {

                $expiry_time = new \DateTimeImmutable($date, new \DateTimeZone('UTC'));

            } catch (\Exception $e) {

                return '';

            }

            return $expiry_time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');

        }

        return '';
    }

    // Valid hostnames from nameservers[].ldhName: lowercase, no duplicates, order kept, at most 10
    protected function nameServersFrom($data)
    {
        $name_servers = array();

        foreach ((array) ($data['nameservers'] ?? array()) as $name_server) {

            $name = is_array($name_server) ? strtolower(rtrim((string) ($name_server['ldhName'] ?? ''), '.')) : '';
            if ($this->isHostname($name) && !in_array($name, $name_servers, true)) $name_servers[] = $name;
            if (count($name_servers) === 10) break;

        }

        return $name_servers;
    }

    // array(name, IANA Registrar ID, https URL) of the entity with the registrar role, each '' if missing
    protected function registrarFrom($data)
    {
        foreach ((array) ($data['entities'] ?? array()) as $entity) {

            if (!is_array($entity) || !in_array('registrar', (array) ($entity['roles'] ?? array()), true)) continue;

            $name = '';
            $url = '';

            foreach ((array) ($entity['vcardArray'][1] ?? array()) as $property) {

                if (!is_array($property) || count($property) < 4) continue;
                if ($property[0] === 'fn' && is_string($property[3])) $name = trim($property[3]);
                if ($property[0] === 'url' && is_string($property[3])) $url = trim($property[3]);

            }

            $iana_id = '';

            foreach ((array) ($entity['publicIds'] ?? array()) as $public_id) {

                if (is_array($public_id) && strcasecmp((string) ($public_id['type'] ?? ''), 'IANA Registrar ID') === 0
                    && ctype_digit((string) ($public_id['identifier'] ?? '')) && (int) $public_id['identifier'] > 0) {

                    $iana_id = (string) $public_id['identifier'];

                }

            }

            // only the vCard's url is the registrar's website (an entity's links usually point back at RDAP)
            if (stripos($url, 'https://') !== 0) $url = '';

            return array($name, $iana_id, $url);

        }

        return array('', '', '');
    }

    // The registrar's RDAP URL from a "related" link, or ''
    protected function relatedLink($data)
    {
        foreach ((array) ($data['links'] ?? array()) as $link) {

            if (!is_array($link) || ($link['rel'] ?? '') !== 'related') continue;

            $href = $link['href'] ?? '';
            $type = strtolower((string) ($link['type'] ?? ''));
            $is_rdap_type = ($type === '' || strpos($type, 'rdap') !== false || strpos($type, 'json') !== false);

            if (is_string($href) && stripos($href, 'https://') === 0 && $is_rdap_type && stripos($href, '/domain/') !== false) {

                return $href;

            }

        }

        return '';
    }
} //@formatter:on
