<?php
/**
 * /classes/DomainMOD/Dynadot.php
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
 * Dynadot has two APIs. Its RESTful API (v2) signs each request with the account's API Secret, and its legacy api3,
 * which Dynadot plans to retire, takes the API Key alone. A registrar account with an API Secret uses the RESTful API,
 * and one without uses api3 exactly as before. While api3 still works, a RESTful request that Dynadot refuses for its
 * credentials (an old or mistyped API Secret, say) is tried once through api3 with the same key, and that account stays
 * on api3 for the rest of the run.
 *
 * RESTful requests are paced to one a second (the limit for Dynadot's Regular accounts) and kept within PHP's time
 * limit: details calls share a time budget for the run, and a list import stops paging before the end of the time
 * limit, so a long Domain Queue run fails the calls it can't fit instead of being killed part-way.
 */
class Dynadot
{
    public $format;
    public $log;
    public $rest_base_url = 'https://api.dynadot.com';
    public $api3_base_url = 'https://api.dynadot.com/api3.xml';

    const PAGE_SIZE = 100;
    const MAX_PAGES = 1000;
    const DETAILS_BUDGET = 120; // seconds of details calls per run, cut to what PHP's time limit leaves
    const PACE = 1.0; // seconds between RESTful requests
    const HINT_SECONDS = 30; // a failed list with less time than this left also logs the time-limit hint
    const FALLBACK_MESSAGE = 'Dynadot refused the REST request. Trying the legacy API.';
    const LIST_FALLBACK_MESSAGE = 'The Dynadot domain list used the legacy API, since Dynadot refused the REST request.';

    // Set by every getFullInfo() call: true when the time budget or a deadline stopped the call before its first
    // request was sent, so the Domain Queue leaves the row's last attempt as it was
    public static $last_call_skipped = false;

    // Set to false by the Domain Queue before each list, and to true once the list's first request is sent
    public static $last_list_sent = false;

    // The HTTP status of the refusal that sent the last list to api3, or null when it didn't fall back. The Domain
    // Queue logs it once the list's database transaction has ended, so the entry isn't lost if the import rolls back.
    public static $last_list_fell_back = null;

    // Seconds the Domain Queue asks list paging to leave free at the end of the time limit, for Dynadot details calls
    // that are already waiting
    public static $list_reserve = 0;

    // The run's state: accounts that fell back to api3 (by a hash of the credentials, never the values), with the
    // HTTP status of the refusal, accounts whose fallback has been logged, the time of the last RESTful request, the
    // end of the details budget, and where list paging stops once a reserve is asked for
    protected static $legacy_accounts = array();
    protected static $warned_accounts = array();
    protected static $last_rest_call = null;
    protected static $details_budget_end = null;
    protected static $list_paging_end = null;

    protected $listing = false; // whether the request being sent belongs to a list
    protected $stopped = false; // whether a deadline stopped a request during the current call
    protected $pending_fallback = null; // a list refusal to report once its api3 request leaves

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.dynadot');
    }

    public function getApiUrl($api_key, $command, $domain)
    {
        // registrar APIs take the punycode form of the domain (xn--mnchen-3ya.de for münchen.de)
        $domain = Domain::forApi($domain);
        $base_url = $this->api3_base_url . '?key=' . $api_key . '&';
        if ($command == 'domainlist') {
            return $base_url . "command=list_domain";
        } elseif ($command == 'info') {
            return $base_url . "command=domain_info&domain=" . $domain;
        } else {
            return _('Unable to build API URL');
        }
    }

    public function apiCall($full_url)
    {
        $handle = HttpClient::init($full_url);
        $result = HttpClient::exec($handle, 'Dynadot API');
        return $result;
    }

    public function getDomainList($api_key, $api_secret = '')
    {
        self::$last_list_fell_back = null;

        if (trim((string) $api_secret) === '') return $this->getLegacyDomainList($api_key);

        $this->listing = true;
        $this->stopped = false;

        try {

            $deadline = $this->listDeadline();
            $account = $this->accountHash($api_key, $api_secret);

            if (isset(self::$legacy_accounts[$account])) {

                $result = $this->getFallbackDomainList($api_key, self::$legacy_accounts[$account], $deadline);

            } else {

                $result = $this->getRestDomainList($api_key, $api_secret, $deadline);

                if (is_int($result)) {

                    // Dynadot refused the credentials on the first page, so the list goes through api3 instead
                    self::$legacy_accounts[$account] = $result;
                    $result = $this->getFallbackDomainList($api_key, $result, $deadline);

                }

            }

            if ($result[0] === '' && $deadline !== null && ($this->stopped || $deadline - $this->now() < self::HINT_SECONDS)) {

                $this->addLogEntry('warning', "The Dynadot domain list didn't finish, and PHP's time limit was close. If this keeps happening, see the Registrar Notes.");

            }

            return $result;

        } finally {

            $this->listing = false;

        }
    }

    public function getFullInfo($api_key, $api_secret, $domain)
    {
        self::$last_call_skipped = false;

        if (trim((string) $api_secret) === '') return $this->getLegacyFullInfo($api_key, $domain);

        $this->stopped = false;
        $deadline = $this->detailsDeadline();

        if ($deadline - $this->now() < 1) {

            self::$last_call_skipped = true;
            return $this->detailsFailed('Not enough time left for Dynadot details this run', $api_key, $domain);

        }

        $account = $this->accountHash($api_key, $api_secret);

        if (isset(self::$legacy_accounts[$account])) {

            $response = $this->request($this->getApiUrl($api_key, 'info', $domain), array(), $deadline, false);
            if ($response === null) self::$last_call_skipped = true;
            return $this->legacyDetails($response, $api_key, $domain);

        }

        $response = $this->restCall($api_key, $api_secret, '/restful/v2/domains/' . rawurlencode(Domain::forApi($domain)), $deadline);

        if ($response === null) {

            self::$last_call_skipped = true;
            return $this->detailsFailed('Not enough time left for Dynadot details this run', $api_key, $domain);

        }

        list($http_code, $body) = $response;

        if ($this->isRefusal($http_code, $body)) {

            self::$legacy_accounts[$account] = $http_code;

            if (!isset(self::$warned_accounts[$account])) {

                self::$warned_accounts[$account] = true;
                $this->addLogEntry('warning', self::FALLBACK_MESSAGE, array('HTTP Status' => $http_code, 'API Key' => $this->format->obfusc($api_key)));

            }

            return $this->legacyDetails($this->request($this->getApiUrl($api_key, 'info', $domain), array(), $deadline, false), $api_key, $domain);

        }

        $full_info = ($http_code === 200) ? $this->parseRestFullInfo($body, $domain) : null;

        if ($full_info === null) {

            return $this->detailsFailed('Unable to get domain details', $api_key, $domain, $http_code, $body);

        }

        return $full_info;
    }

    // The domain list through api3 for an account whose credentials Dynadot refused ($refusal, the HTTP status), within
    // the list's deadline. The refusal is reported for the Domain Queue's log only once the api3 request has been sent.
    protected function getFallbackDomainList($api_key, $refusal, $deadline)
    {
        $this->pending_fallback = $refusal;

        try {
            $response = $this->request($this->getApiUrl($api_key, 'domainlist', ''), array(), $deadline, false);
        } finally {
            $this->pending_fallback = null;
        }

        return $this->parseLegacyDomainList($response, $api_key);
    }

    // Domain details through api3 for an account with no API Secret, exactly as before the RESTful API was added
    protected function getLegacyFullInfo($api_key, $domain)
    {
        $api_url = $this->getApiUrl($api_key, 'info', $domain);
        $api_results = $this->apiCall($api_url);
        $full_info = $this->parseFullInfo($api_results);

        // confirm that the api call was successful
        if ($full_info === null) {

            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain, 'API Key' => $this->format->obfusc($api_key));
            $this->log->error($log_message, $log_extra);

            return array('', array(), '', '');

        }

        return $full_info;
    }

    // Domain details from an api3 response sent within the run's budget (a fallback, or an account already on api3),
    // or the usual failure values when the request wasn't sent or failed
    protected function legacyDetails($response, $api_key, $domain)
    {
        $full_info = ($response === null) ? null : $this->parseFullInfo($response[1]);

        if ($full_info === null) {

            return $this->detailsFailed('Unable to get domain details', $api_key, $domain);

        }

        return $full_info;
    }

    // Logs a failed details call (with the HTTP status and Dynadot's numeric code when there was a response, never
    // Dynadot's own message) and returns the usual failure values
    protected function detailsFailed($log_message, $api_key, $domain, $http_code = null, $body = null)
    {
        $log_extra = array('Domain' => $domain, 'API Key' => $this->format->obfusc($api_key));
        if ($http_code !== null) $log_extra = array_merge($log_extra, $this->responseCodes($http_code, $body));
        $this->log->error($log_message, $log_extra);

        return array('', array(), '', '');
    }

    // The domain list through api3 for an account with no API Secret, exactly as before the RESTful API was added
    protected function getLegacyDomainList($api_key)
    {
        $api_url = $this->getApiUrl($api_key, 'domainlist', '');
        self::$last_list_sent = true;
        $api_results = $this->apiCall($api_url);
        return $this->parseLegacyDomainList(array(0, $api_results), $api_key);
    }

    // The domain list in an api3 list_domain response, as array($count, $list), or array('', '') if the call failed
    // or wasn't sent (a null response)
    protected function parseLegacyDomainList($response, $api_key)
    {
        $array_results = ($response === null) ? null : RegistrarResponse::parseXml($response[1]);

        // confirm that the api call was successful
        if (!(RegistrarResponse::get($array_results, 0, 'ListDomainInfoHeader', 'Status') === 'success'
              && RegistrarResponse::isXmlContainer($array_results, 0, 'ListDomainInfoContent', 'DomainInfoList'))) {

            $log_message = 'Unable to get domain list';
            $log_extra = array('API Key' => $this->format->obfusc($api_key));
            $this->log->error($log_message, $log_extra);

            // a failed call isn't an account with no domains, so the Domain Queue retries the list
            return array('', '');

        }

        $domain_list = array();
        $domain_count = 0;

        // DomainInfo may hold every Domain, or there may be one DomainInfo per Domain, so read both
        foreach (RegistrarResponse::toXmlList(RegistrarResponse::get($array_results, 0, 'ListDomainInfoContent', 'DomainInfoList', 'DomainInfo')) as $info) {

            // a DomainInfo without a Domain is an unreadable record (the check below fails the list)
            $domains = RegistrarResponse::get($info, 'Domain');
            foreach ($domains === null ? array(array()) : RegistrarResponse::toXmlList($domains) as $item) {

                $domain = RegistrarResponse::text(RegistrarResponse::get($item, 'Name'));
                if ($domain === '') {

                    $this->log->error('Unable to read a domain in the domain list');

                    // one unreadable record fails the whole list (it's retried), rather than finishing with domains missing
                    return array('', '');

                }
                $domain_list[] = $domain;
                $domain_count++;

            }

        }

        return array($domain_count, $domain_list);
    }

    /*
     * The domain list through the RESTful API, a page at a time in name order: array($count, $list), array('', '') if
     * any page failed (a partial list never comes back, so the Domain Queue retries the whole list), or the HTTP status
     * of a credential refusal on the first page, so the caller can try api3. Dynadot's default status filter is used,
     * which listed the same domains as api3 in Dynadot's sandbox (expired domains couldn't be checked there). Every page gives the total, the page it is, and whether another follows,
     * and the list ends on the page that says none does.
     */
    protected function getRestDomainList($api_key, $api_secret, $deadline)
    {
        $names = array(); // normalized name => the name as Dynadot gave it
        $total = null;
        $previous_page = null;
        $page_one = null;
        $pages_with_domains = 0;

        for ($page = 1; ; $page++) {

            if ($page > static::MAX_PAGES) return $this->listFailed('The Dynadot domain list has too many pages', $api_key);

            $result = $this->getRestListPage($api_key, $api_secret, $page, $deadline, $page === 1);
            if (is_int($result)) return $result;
            if ($result === null) return array('', '');
            list($page_names, $page_total, $has_next_page) = $result;

            if ($total !== null && $page_total !== $total) return $this->listFailed('The Dynadot domain list changed while it was read', $api_key);
            $total = $page_total;

            if ($page_names && $page_names === $previous_page) return $this->listFailed('The Dynadot domain list repeated a page', $api_key);
            if (!$page_names && $has_next_page) return $this->listFailed('Unable to get domain list', $api_key);
            $previous_page = $page_names;

            if ($page === 1) $page_one = $page_names;
            if ($page_names) $pages_with_domains++;

            foreach ($page_names as $normalized => $name) {

                if (!isset($names[$normalized])) $names[$normalized] = $name;

            }

            if (!$has_next_page) break;

        }

        if (count($names) !== $total) return $this->listFailed('The Dynadot domain list changed while it was read', $api_key);

        // A domain that leaves the account while the pages are read can shift a later page past one of them, so page 1
        // is read again: if it changed, the list is retried
        if ($pages_with_domains > 1) {

            $result = $this->getRestListPage($api_key, $api_secret, 1, $deadline, false);
            if ($result === null) return array('', '');
            if ($result[0] !== $page_one || $result[1] !== $total) return $this->listFailed('The Dynadot domain list changed while it was read', $api_key);

        }

        return array(count($names), array_values($names));
    }

    // One page of the RESTful list: array($names (normalized => as given, in order), $total, $has_next_page), null if
    // it failed (already logged), or, for the list's first request ($first), the HTTP status of a credential refusal.
    // A refusal after a page has worked fails the list instead, since the credentials already worked.
    protected function getRestListPage($api_key, $api_secret, $page, $deadline, $first)
    {
        $path = '/restful/v2/domains?page=' . $page . '&page_size=' . static::PAGE_SIZE . '&sort=name_asc';
        $response = $this->restCall($api_key, $api_secret, $path, $deadline);

        if ($response === null) {

            $this->listFailed('Not enough time left for the Dynadot domain list', $api_key);
            return null;

        }

        list($http_code, $body) = $response;

        if ($this->isRefusal($http_code, $body)) {

            if ($first) return $http_code;

            $this->listFailed('Dynadot refused the request', $api_key, $http_code, $body);
            return null;

        }

        $data = RegistrarResponse::get(RegistrarResponse::parseJson($body), 'data');
        $pagination = RegistrarResponse::get($data, 'pagination_result');
        $total = RegistrarResponse::get($pagination, 'total');
        $has_next_page = RegistrarResponse::get($pagination, 'has_next_page');

        if (!($http_code === 200 && $this->isSuccess($body)
              && RegistrarResponse::isJsonListAt($body, 'data', 'domain_info_list')
              && RegistrarResponse::get($pagination, 'page') === $page
              && is_int($total) && $total >= 0
              && ($has_next_page === 'Yes' || $has_next_page === 'No'))) {

            $this->listFailed('Unable to get domain list', $api_key, $http_code, $body);
            return null;

        }

        $names = array();

        foreach (RegistrarResponse::get($data, 'domain_info_list') as $row) {

            $name = RegistrarResponse::get($row, 'domain_name');
            $normalized = is_string($name) ? Domain::normalize($name) : '';

            if ($normalized === '') {

                // one unreadable record fails the whole list (it's retried), rather than finishing with domains missing
                $this->log->error('Unable to read a domain in the domain list');
                return null;

            }

            if (!isset($names[$normalized])) $names[$normalized] = $name;

        }

        return array($names, $total, $has_next_page === 'Yes');
    }

    protected function listFailed($log_message, $api_key, $http_code = null, $body = null)
    {
        $log_extra = array('API Key' => $this->format->obfusc($api_key));
        if ($http_code !== null) $log_extra = array_merge($log_extra, $this->responseCodes($http_code, $body));
        $this->log->error($log_message, $log_extra);

        return array('', '');
    }

    /*
     * One signed RESTful GET: array($http_code, $body), as transfer() returns it, or null when there wasn't time to
     * send it. $path_and_query is signed exactly as it's sent.
     */
    public function restCall($api_key, $api_secret, $path_and_query, $deadline)
    {
        $request_id = $this->requestId();
        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer ' . trim((string) $api_key),
            'X-Request-ID: ' . $request_id,
            'X-Signature: ' . $this->signature(trim((string) $api_key), trim((string) $api_secret), $path_and_query, $request_id));

        return $this->request($this->rest_base_url . $path_and_query, $headers, $deadline, true);
    }

    // The X-Signature header's value: Base64 of an HMAC-SHA256, keyed with the API Secret, over the API Key, the path
    // and query, the request ID, and the body, one per line
    public function signature($api_key, $api_secret, $path_and_query, $request_id, $body = '')
    {
        return base64_encode(hash_hmac('sha256', $api_key . "\n" . $path_and_query . "\n" . $request_id . "\n" . $body, $api_secret, true));
    }

    /*
     * Sends one request within the earlier of $deadline and any deadline the caller has set (Refresh sets one), as the
     * HttpClient deadline for the request, and puts the caller's deadline back afterwards. A RESTful request ($paced)
     * first waits until a second has passed since the last one. Returns null, with nothing sent, when the wait and at
     * least a second for the request wouldn't fit.
     */
    protected function request($url, $headers, $deadline, $paced)
    {
        $caller_deadline = HttpClient::getDeadline();
        $effective = $deadline;
        if ($caller_deadline !== null && ($effective === null || $caller_deadline < $effective)) $effective = $caller_deadline;

        if ($paced && self::$last_rest_call !== null) {

            $wait = self::$last_rest_call + self::PACE - $this->now();

            if ($wait > 0) {

                if ($effective !== null && $this->now() + $wait + 1 > $effective) {

                    $this->stopped = true;
                    return null;

                }

                $this->sleepFor($wait);

            }

        }

        $handle = HttpClient::init($url);
        if ($headers) curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);

        try {

            if ($effective !== null) HttpClient::setDeadline($effective);
            return $this->send($handle, $effective, $paced);

        } finally {

            if ($caller_deadline === null) {
                HttpClient::clearDeadline();
            } else {
                HttpClient::setDeadline($caller_deadline);
            }

        }
    }

    // The last check before a request leaves: with less than a second to go, nothing is sent (null)
    protected function send($handle, $deadline, $paced)
    {
        if ($deadline !== null && $deadline - $this->now() < 1) {

            $this->stopped = true;
            return null;

        }

        if ($this->listing) self::$last_list_sent = true;
        if ($this->pending_fallback !== null) self::$last_list_fell_back = $this->pending_fallback;
        if ($paced) self::$last_rest_call = $this->now();

        return $this->transfer($handle);
    }

    // The only place a budgeted request leaves: array($http_code, $body), with $body false when the transfer failed
    protected function transfer($handle)
    {
        $body = HttpClient::exec($handle, 'Dynadot API');
        return array((int) curl_getinfo($handle, CURLINFO_HTTP_CODE), $body);
    }

    // Whether a RESTful response is Dynadot refusing the credentials: a wrong API Key (401), a refused connection such
    // as an IP address restriction (403), or a signature that doesn't match, which means a wrong API Secret (400). The
    // response must be a completed transfer with Dynadot's own JSON, whose code matches the HTTP status. Dynadot's
    // description is matched for the signature case, never logged.
    public function isRefusal($http_code, $body)
    {
        $data = RegistrarResponse::parseJson($body);
        $code = RegistrarResponse::get($data, 'code');
        if (!is_int($code) && !(is_string($code) && ctype_digit($code))) return false;
        $code = (int) $code;

        if (($http_code === 401 && $code === 401) || ($http_code === 403 && $code === 403)) return true;

        $description = RegistrarResponse::get($data, 'error', 'description');
        return $http_code === 400 && $code === 400 && is_string($description)
            && stripos($description, 'X-Signature provided is not valid') !== false;
    }

    // Whether a RESTful response's own code is 200 (a number or a string)
    protected function isSuccess($body)
    {
        $code = RegistrarResponse::get(RegistrarResponse::parseJson($body), 'code');
        return $code === 200 || $code === '200';
    }

    // The HTTP status and Dynadot's numeric code, for a log entry
    protected function responseCodes($http_code, $body)
    {
        $code = RegistrarResponse::get(RegistrarResponse::parseJson($body), 'code');
        return array('HTTP Status' => $http_code, 'Dynadot Code' => (is_int($code) || (is_string($code) && ctype_digit($code))) ? (string) $code : '');
    }

    /*
     * The details in a RESTful domain response, as array($expiration_date, $dns_servers, $privacy_status,
     * $autorenewal_status) like parseFullInfo(), or null if it isn't a successful response for $domain. Name servers
     * follow api3: custom name servers are listed, and parking, forwarding, and Dynadot's own DNS give the placeholders
     * (api3 leaves name servers out for them). Custom name servers with no usable name, or no DNS type at all, fail,
     * so a damaged response can't replace real name servers with the placeholders.
     */
    public function parseRestFullInfo($body, $domain)
    {
        $data = RegistrarResponse::parseJson($body);
        if (!$this->isSuccess($body)) return null;

        $domain_info = RegistrarResponse::get($data, 'data', 'domain_info');
        $name = RegistrarResponse::get($domain_info, 'domain_name');
        if (!is_array($domain_info) || !is_string($name) || !Domain::sameDomain($name, Domain::normalize($domain))) return null;

        $glue_type = RegistrarResponse::get($domain_info, 'glue_info', 'glue_type');
        if (!is_string($glue_type) || $glue_type === '') return null;

        if ($glue_type === 'NAME_SERVERS') {

            $servers = array();

            foreach (RegistrarResponse::toList(RegistrarResponse::get($domain_info, 'glue_info', 'nameserver_list')) as $server) {

                $server_name = RegistrarResponse::get($server, 'server_name');
                if (is_string($server_name) && trim($server_name) !== '') $servers[] = trim($server_name);

            }

            $dns_servers = $servers;
            if (empty($dns_servers)) return null;

        } else {

            $dns_servers = array('no.dns-servers.1', 'no.dns-servers.2');

        }

        $privacy = strtolower(RegistrarResponse::text(RegistrarResponse::get($domain_info, 'privacy')));
        $privacy_status = in_array($privacy, array('full privacy', 'partial privacy', 'full', 'partial'), true) ? '1' : '0';

        $renew_option = strtolower(RegistrarResponse::text(RegistrarResponse::get($domain_info, 'renew_option')));
        $autorenewal_status = in_array($renew_option, array('auto-renew', 'auto'), true) ? '1' : '0';

        return array($this->processRestExpiry(RegistrarResponse::get($domain_info, 'expiration_date')), $dns_servers,
                     $privacy_status, $autorenewal_status);
    }

    // A RESTful expiry (milliseconds since the epoch, a number or a string of digits) as a date, or '' for anything
    // else, including a time after 9999-12-31. On 32-bit PHP (the official Docker image's armhf build), json_decode()
    // gives a number of milliseconds as a float, which is exact for every date that counts here.
    public function processRestExpiry($expiry_result)
    {
        if (PHP_INT_SIZE < 8 && is_float($expiry_result) && is_finite($expiry_result) && $expiry_result >= 0
            && floor($expiry_result) === $expiry_result) {
            $expiry_result = sprintf('%.0f', $expiry_result);
        }
        $digits = RegistrarResponse::text($expiry_result);
        if (!ctype_digit($digits)) return '';
        $digits = ltrim($digits, '0');
        if (strlen($digits) < 4) return '';
        if (strlen($digits) > 15 || (strlen($digits) === 15 && strcmp($digits, '253402300799999') > 0)) return '';
        return $this->processExpiry($digits);
    }

    // The end of the run's details budget: DETAILS_BUDGET seconds from the first details call, cut to what PHP's time
    // limit leaves less a margin (as the Domain Queue's RDAP budget is). request() also applies any earlier deadline
    // the caller has set.
    protected function detailsDeadline()
    {
        if (self::$details_budget_end === null) {

            self::$details_budget_end = $this->now() + min(self::DETAILS_BUDGET, $this->timeLeft(self::DETAILS_BUDGET));

        }

        return self::$details_budget_end;
    }

    /*
     * The deadline for list paging: what PHP's time limit leaves less the margin, or null when PHP has no time limit.
     * Once the Domain Queue asks for a reserve, paging also stops at a fixed time for the rest of the run: the reserve
     * before the end, but never more than half of what was left then, so under a short time limit details that keep
     * failing can't stop every list, and later lists can't use the time earlier ones left free.
     */
    protected function listDeadline()
    {
        $left = $this->timeLeft(null);
        if ($left === null) return null;

        $deadline = $this->now() + $left;
        if (self::$list_reserve <= 0) return $deadline;

        if (self::$list_paging_end === null) {

            self::$list_paging_end = $this->now() + max($left / 2, $left - self::$list_reserve);

        }

        return min($deadline, self::$list_paging_end);
    }

    // The seconds PHP's time limit leaves less a margin (a quarter of what's left, at most a minute), or $no_limit when
    // there's no time limit
    protected function timeLeft($no_limit)
    {
        $max_execution_time = $this->maxExecutionTime();
        if ($max_execution_time <= 0) return $no_limit;

        $remaining = $max_execution_time - ($this->now() - $this->requestStarted());
        return max(0, $remaining - min(60, $remaining / 4));
    }

    // A hash naming the account for the run's cached choices, never holding the key or secret
    protected function accountHash($api_key, $api_secret)
    {
        return hash('sha256', trim((string) $api_key) . "\n" . trim((string) $api_secret));
    }

    // A log entry that's always written (not only with debugging on), and never stops the caller if it fails
    protected function addLogEntry($level, $message, $extra = array())
    {
        try {
            $this->log->addEntry($level, $message, $extra);
        } catch (\Throwable $e) {
        }
    }

    // A random version 4 UUID, for the X-Request-ID header
    protected function requestId()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    protected function maxExecutionTime()
    {
        return (int) ini_get('max_execution_time');
    }

    // When PHP started this request, which its time limit counts from
    protected function requestStarted()
    {
        return (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? $this->now());
    }

    protected function now()
    {
        return microtime(true);
    }

    protected function sleepFor($seconds)
    {
        usleep((int) ceil($seconds * 1000000));
    }

    // The details in a domain_info response, as array($expiration_date, $dns_servers, $privacy_status,
    // $autorenewal_status), or null if the call failed. A field that's missing or isn't in the expected form counts as
    // having no value: Dynadot leaves out NameServers when the domain uses forwarding, parking, or Dynadot's own DNS
    public function parseFullInfo($api_results)
    {
        $array_results = RegistrarResponse::parseXml($api_results);

        if (RegistrarResponse::get($array_results, 0, 'DomainInfoHeader', 'Status') !== 'success') return null;

        $domain_info = RegistrarResponse::get($array_results, 0, 'DomainInfoContent', 'Domain');

        // get expiration date
        $expiration_date = $this->processExpiry(RegistrarResponse::get($domain_info, 'Expiration'));

        // get dns servers
        $dns_result = RegistrarResponse::get($domain_info, 'NameServerSettings', 'NameServers', 'NameServer');
        $dns_servers = $this->processDns($dns_result);

        // get privacy status
        $privacy_result = RegistrarResponse::text(RegistrarResponse::get($domain_info, 'Privacy'));
        $privacy_status = $this->processPrivacy($privacy_result);

        // get auto renewal status
        $autorenewal_result = RegistrarResponse::text(RegistrarResponse::get($domain_info, 'RenewOption'));
        $autorenewal_status = $this->processAutorenew($autorenewal_result);

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    // Dynadot gives the expiry date in milliseconds since the epoch, and anything else is no date ('')
    public function processExpiry($expiry_result)
    {
        $expiry_result = RegistrarResponse::text($expiry_result);
        if (!ctype_digit($expiry_result) || strlen($expiry_result) < 4) return '';
        $expiry_formatted = substr($expiry_result, 0, -3);
        if (PHP_INT_SIZE >= 8 || (string) (int) $expiry_formatted === $expiry_formatted) {
            return gmdate("Y-m-d", (int) $expiry_formatted);
        }

        // On 32-bit PHP, (int) stops at 2038, and DateTimeImmutable works with 64-bit times
        $expiry_formatted = ltrim($expiry_formatted, '0');
        if ($expiry_formatted === '') $expiry_formatted = '0';
        try {
            return (new \DateTimeImmutable('@' . $expiry_formatted))->format('Y-m-d');
        } catch (\Exception $e) {
            return '';
        }
    }

    public function processDns($dns_result)
    {
        $servers = array();

        // one NameServer converts to a single record instead of a list of one, and none (null) to no records
        foreach (RegistrarResponse::toXmlList($dns_result) as $server) {

            $servers[] = RegistrarResponse::text(RegistrarResponse::get($server, 'ServerName'));

        }

        $dns_servers = array_filter($servers);

        if (empty($dns_servers)) {
            $dns_servers[0] = 'no.dns-servers.1';
            $dns_servers[1] = 'no.dns-servers.2';
        }
        return $dns_servers;
    }

    public function processPrivacy($privacy_result)
    {
        if ($privacy_result == 'full' || $privacy_result == 'partial') {
            $privacy_status = '1';
        } else {
            $privacy_status = '0';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == 'auto-renew') {
            $autorenewal_status = '1';
        } else { // result messages - 'do not renew', 'no renew option'
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

} //@formatter:on
