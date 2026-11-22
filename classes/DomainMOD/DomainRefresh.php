<?php
/**
 * /classes/DomainMOD/DomainRefresh.php
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
 * Refreshes one domain's details on request (the Refresh Domain box on the domain edit page). The registrar's API is
 * tried first, then public RDAP data, and WHOIS for TLDs without an RDAP server, the same way the Domain Queue's RDAP
 * import works. Changes are applied straight away and listed for the user. The expiry date and DNS profile can come
 * from any source, while WHOIS privacy and auto renewal only come from a registrar API that reports them. The registry
 * status codes and transfer lock (DomainStatus) always come from RDAP: with the RDAP lookup itself, or, after a registrar
 * API answer, from one extra status-only RDAP lookup.
 */
class DomainRefresh
{
    public $deeb;
    public $domain;
    public $log;
    public $queue;
    public $status_available = false; // whether the lock status columns are there (DomainStatus::isAvailable())
    public $failed_started = '';      // when this refresh's lookup started, if its status codes couldn't be read
    public $time;

    const DEADLINE = 45;           // seconds for the whole lookup
    const MIN_LOOKUP_TIME = 10;    // RDAP and WHOIS are only tried with at least this many seconds left
    const SAME_DOMAIN_GAP = 30;    // seconds between refreshes of the same domain, per session
    const ANY_DOMAIN_GAP = 5;      // seconds between any two refreshes, per session
    const MAX_DNS_SERVERS = 10;    // a DNS profile holds 10 servers

    // Registrar APIs whose getFullInfo() only reads the Domain Queue's staging data, so there's no live call to make.
    public static $unsupported_apis = array('Above.com', 'DreamHost');

    /*
     * Fields a registrar API doesn't really report, because its class always returns the same value or a default when
     * the domain or field is missing (Gandi's privacy, ResellerClub's and Hostinger's auto renewal, and Namecheap's
     * auto renewal when its list doesn't include the domain). These are left as they are.
     */
    public static $unreported_fields = array(
        'Gandi' => array('privacy'),
        'Hostinger' => array('autorenew'),
        'Namecheap' => array('autorenew'),
        'ResellerClub' => array('autorenew'));

    /*
     * The registrar account details each API needs (the same ones DomainQueue::fetchRegistrarInfo() reads), named as
     * getDomainRow() returns them. GoDaddy's secret is optional, since a Personal Access Token goes in the key alone.
     */
    public static $credential_columns = array(
        'Cloudflare' => array('api_key', 'api_account_id'),
        'DNSimple' => array('api_key', 'api_account_id'),
        'Dynadot' => array('api_key'),
        'eNom' => array('username', 'password'),
        'Fabulous' => array('username', 'password'),
        'Freenom' => array('username', 'password'),
        'Gandi' => array('api_key'),
        'GoDaddy' => array('api_key'),
        'Hostinger' => array('api_key'),
        'Internet.bs' => array('api_key', 'api_secret'),
        'Name.com' => array('username', 'api_key'),
        'NameBright' => array('username', 'api_app_name', 'api_secret'),
        'Namecheap' => array('username', 'api_key', 'api_ip'),
        'NameSilo' => array('api_key'),
        'OpenSRS' => array('username', 'api_key'),
        'OVH' => array('api_key', 'api_secret', 'api_account_id'),
        'Porkbun' => array('api_key', 'api_secret'),
        'ResellerClub' => array('reseller_id', 'api_key'),
        'SiteHost' => array('api_key', 'api_account_id'));

    // The names registrar clients return when the API gave no name servers.
    public static $placeholder_dns = array('no.dns-servers.1', 'no.dns-servers.2');

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->domain = new Domain();
        $this->log = new Log('class.domainrefresh');
        $this->queue = new DomainQueue();
        $this->time = new Time();
    }

    /*
     * Refreshes a domain and returns array(success, lines): the lines make up the message for the user, already
     * translated and escaped for HTML.
     */
    public function refresh($domain_id, $user_id)
    {
        $row = $this->getDomainRow($domain_id, false);
        // Each return also gives the result in parts (a summary, reasons, changes, and notes), all translated and
        // escaped, for the edit page's compact result (System::showMessageResult())
        if (!$row) {
            $missing = _('That domain no longer exists.');
            return array(false, array($missing), array('summary' => $missing, 'reasons' => array(), 'changes' => array(), 'notes' => array()));
        }

        // Kept left to right inside the translated sentences below, for right-to-left languages
        $domain_html = (new Layout())->ltr($row->domain);

        $wait = $this->throttle((int) $row->id);
        if ($wait !== '') return array(false, array($wait), array('summary' => $wait, 'reasons' => array(), 'changes' => array(), 'notes' => array()));

        $deadline = microtime(true) + self::DEADLINE;
        $this->status_available = DomainStatus::isAvailable($this->deeb->cnxx);
        $this->failed_started = '';
        $source = '';
        $fallback_lines = array();
        $details = null;

        // 1. The registrar's API.
        $api_name = (string) $row->api_registrar_name;
        if ($api_name !== '' && $api_name !== 'RDAP') {

            if (in_array($api_name, self::$unsupported_apis, true)) {

                $fallback_lines[] = sprintf(_("Refreshing through the %s API isn't supported yet, so public RDAP and WHOIS data was used instead."), $this->html($api_name));

            } elseif (!$this->hasCredentials($row, $api_name)) {

                $fallback_lines[] = _("This registrar account's API details aren't filled in, so public RDAP and WHOIS data was used instead.");

            } else {

                $details = $this->fetchFromRegistrar($row, $api_name, $deadline);
                if ($details === null) {
                    $fallback_lines[] = _("Your registrar's API didn't return this domain's details, so public RDAP and WHOIS data was used instead.");
                } else {
                    $source = 'registrar';
                    // The API doesn't give the registry's status codes, so they come from one status-only RDAP lookup
                    if ($this->status_available) {
                        $details['status'] = $this->fetchStatusOnly($row, $deadline);
                        if ($details['status'] === null) $details['notes'][] = _("The lock status couldn't be checked this time.");
                    }
                }

            }

        }

        // 2. RDAP, then WHOIS for TLDs without an RDAP server.
        if ($details === null) {

            list($source, $details, $error, $whois_line) = $this->fetchPublicData($row, $deadline);
            if ($whois_line !== '') $fallback_lines[] = $whois_line;

            if ($details === null) {

                $this->saveFailedStatus((int) $row->id);
                $failed = sprintf(_("Domain %s couldn't be refreshed."), $domain_html);
                return array(false, array_merge(array($failed), $fallback_lines, array($error)),
                    array('summary' => $failed, 'reasons' => array($error), 'changes' => array(), 'notes' => $fallback_lines));

            }

        }

        // 3. Compare with the domain as it is now, and apply any changes.
        list($success, $lines, $parts) = $this->apply($row, $user_id, $source, $details);
        $this->saveFailedStatus((int) $row->id);

        if (!$success) {

            $failed = sprintf(_("Domain %s couldn't be refreshed."), $domain_html);
            return array(false, array_merge(array($failed), $lines),
                array('summary' => $failed, 'reasons' => $parts['reasons'], 'changes' => array(), 'notes' => $fallback_lines));

        }

        if ($source === 'registrar') {
            $first_line = sprintf(_("Domain %s refreshed using your registrar's API."), $domain_html);
        } elseif ($source === 'rdap') {
            $first_line = sprintf(_('Domain %s refreshed using RDAP.'), $domain_html);
        } else {
            $first_line = sprintf(_('Domain %s refreshed using WHOIS.'), $domain_html);
        }

        return array(true, array_merge(array($first_line), $fallback_lines, $lines),
            array('summary' => $first_line, 'reasons' => $parts['reasons'], 'changes' => $parts['changes'],
                'notes' => array_merge($fallback_lines, $parts['notes'])));
    }

    // The name of the registrar API a refresh of this domain would try first, or '' when it would only use public
    // data. It's for the edit page's help text.
    public function registrarApiName($domain_id)
    {
        $row = $this->getDomainRow($domain_id, false);
        if (!$row) return '';

        $api_name = (string) $row->api_registrar_name;
        if ($api_name === '' || $api_name === 'RDAP' || in_array($api_name, self::$unsupported_apis, true)
            || !$this->hasCredentials($row, $api_name)) return '';

        return $api_name;
    }

    /*
     * The domain with its account, registrar, API name, and the account's API columns. With $lock (inside a
     * transaction), the domain, account, and registrar rows stay locked until it ends.
     */
    protected function getDomainRow($domain_id, $lock)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT d.id, d.domain, d.tld, d.expiry_date, d.dns_id, d.privacy, d.autorenew, d.notes, d.account_id," . ($this->status_available ? "
                d.transfer_lock, d.epp_status, d.epp_status_time," : "") . "
                ra.registrar_id, r.api_registrar_id, ar.name AS api_registrar_name, ra.username, ra.`password`,
                ra.account_id AS api_account_id, ra.reseller_id, ra.api_app_name, ra.api_key, ra.api_secret,
                ra.api_ip_id, IFNULL(ip.ip, '') AS api_ip
            FROM domains AS d
            JOIN registrar_accounts AS ra ON ra.id = d.account_id
            JOIN registrars AS r ON r.id = ra.registrar_id
            LEFT JOIN api_registrars AS ar ON ar.id = r.api_registrar_id
            LEFT JOIN ip_addresses AS ip ON ip.id = ra.api_ip_id
            WHERE d.id = :domain_id" . ($lock ? "
            FOR UPDATE" : ""));
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();

        return $row;
    }

    /*
     * Per session, a refresh of the same domain waits SAME_DOMAIN_GAP seconds and any refresh waits ANY_DOMAIN_GAP
     * seconds, so repeated clicks can't hit a registrar, RDAP, or WHOIS server quickly. Returns '' when the refresh
     * can go ahead (and records it), or the message saying how long to wait.
     */
    protected function throttle($domain_id)
    {
        $now = time();
        $times = $_SESSION['s_refresh_times'] ?? array();
        if (!is_array($times)) $times = array();

        // Forget refreshes that no longer hold anything up.
        foreach ($times as $id => $time) {
            if ($now - (int) $time >= self::SAME_DOMAIN_GAP) unset($times[$id]);
        }

        if (isset($times[$domain_id])) {

            $seconds = self::SAME_DOMAIN_GAP - ($now - (int) $times[$domain_id]);
            return sprintf(ngettext('Please wait %d second before refreshing this domain again.',
                'Please wait %d seconds before refreshing this domain again.', $seconds), $seconds);

        }

        $last = (int) ($_SESSION['s_refresh_last'] ?? 0);
        if ($now - $last < self::ANY_DOMAIN_GAP) {

            $seconds = self::ANY_DOMAIN_GAP - ($now - $last);
            return sprintf(ngettext('Please wait %d second before refreshing another domain.',
                'Please wait %d seconds before refreshing another domain.', $seconds), $seconds);

        }

        $times[$domain_id] = $now;
        $_SESSION['s_refresh_times'] = $times;
        $_SESSION['s_refresh_last'] = $now;

        return '';
    }

    // Whether the account has every API detail its registrar needs (read straight from the row, not through Api).
    protected function hasCredentials($row, $api_name)
    {
        if (!isset(self::$credential_columns[$api_name])) return false;

        foreach (self::$credential_columns[$api_name] as $column) {
            if (trim((string) $row->$column) === '') return false;
        }

        return true;
    }

    // A hash of the account's API columns, so a change during the refresh can be noticed without keeping the values.
    protected function credentialHash($row)
    {
        return hash('sha256', json_encode(array((string) $row->username, (string) $row->password,
            (string) $row->api_account_id, (string) $row->reseller_id, (string) $row->api_app_name,
            (string) $row->api_key, (string) $row->api_secret, (string) $row->api_ip_id)));
    }

    /*
     * The registrar API's answer as array('expiry_date', 'name_servers', 'privacy', 'autorenew', 'notes'), or null
     * when it couldn't be used (the caller then tries RDAP). A field the API doesn't report is null. A usable answer
     * with an invalid expiry date keeps the other fields and leaves the expiry date alone.
     */
    protected function fetchFromRegistrar($row, $api_name, $deadline)
    {
        HttpClient::setDeadline($deadline);

        try {

            $info = $this->queue->fetchRegistrarInfo($api_name, (int) $row->account_id, $row->domain);

        } catch (\Throwable $e) {

            $this->log->error('Unable to refresh a domain from its registrar API',
                array('Domain' => $row->domain, 'Registrar API' => $api_name, 'Error' => get_class($e)));
            return null;

        } finally {

            HttpClient::clearDeadline();

        }

        if (!is_array($info)) return null;
        list(, $expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $info;

        // The same checks the queue makes before it imports a domain.
        if (!is_string($expiration_date) || in_array($expiration_date, array('', '0000-00-00', '1970-01-01'), true)
            || empty($dns_servers) || !is_array($dns_servers)
            || (string) $privacy_status === '' || (string) $autorenew_status === '') {

            return null;

        }

        $unreported = self::$unreported_fields[$api_name] ?? array();
        $details = array('expiry_date' => '', 'name_servers' => $dns_servers, 'privacy' => null, 'autorenew' => null,
            'notes' => array(), 'status' => null);

        if ($this->isDate($expiration_date)) {
            $details['expiry_date'] = $expiration_date;
        } else {
            $details['notes'][] = _("Your registrar's API returned an invalid expiry date, so the expiry date wasn't changed.");
        }

        if (in_array('privacy', $unreported, true)) {
            $details['notes'][] = _("Your registrar's API doesn't report WHOIS privacy, so it wasn't changed.");
        } else {
            $details['privacy'] = ((string) $privacy_status === '1') ? 1 : 0;
        }

        if (in_array('autorenew', $unreported, true)) {
            $details['notes'][] = _("Your registrar's API doesn't report the auto-renewal status, so it wasn't changed.");
        } else {
            $details['autorenew'] = ((string) $autorenew_status === '1') ? 1 : 0;
        }

        return $details;
    }

    /*
     * RDAP, then WHOIS when the TLD has no RDAP server: array(source, details, error, whois_line). details is null
     * when nothing could be fetched, and error then says why.
     */
    protected function fetchPublicData($row, $deadline)
    {
        if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) {
            return array('', null, _("RDAP and WHOIS couldn't be tried, because the lookup ran out of time. Please try again later."), '');
        }

        $started = gmdate('Y-m-d H:i:s');
        $lookup = $this->rdap()->lookup($row->domain, $deadline);
        if (!in_array($lookup['status'], array('ok', 'no_server'), true)) $this->failed_started = $started;

        if ($lookup['status'] === 'not_found') return array('', null, _("The domain wasn't found in RDAP."), '');
        if ($lookup['status'] === 'rate_limited') return array('', null, _('The RDAP server is limiting requests. Please try again later.'), '');
        if ($lookup['status'] === 'ok') return array('rdap', $this->publicDetails($lookup, 'rdap', $started), '', '');
        if ($lookup['status'] !== 'no_server') return array('', null, _('The RDAP lookup failed. Please try again later.'), '');

        // No RDAP server for the TLD, so try WHOIS.
        $whois_line = $this->tldMessage(_("RDAP isn't available for .%s domains, so WHOIS was used instead."), $row->tld);
        $whois = new Whois();
        $started = gmdate('Y-m-d H:i:s');
        list($found, $server) = $whois->serverFor(Domain::forApi($row->domain), $deadline);

        // IANA couldn't be asked which WHOIS server to use, or the TLD has neither, so the check failed
        if (!$found || $server === '') $this->failed_started = $started;
        if (!$found) return array('', null, $this->tldMessage(_("The WHOIS server for .%s domains couldn't be found. Please try again later."), $row->tld), '');
        if ($server === '') return array('', null, $this->tldMessage(_('Neither RDAP nor WHOIS is available for .%s domains.'), $row->tld), '');

        if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) {
            return array('', null, _('The WHOIS lookup failed. Please try again later.'), $whois_line);
        }

        $started = gmdate('Y-m-d H:i:s');
        $lookup = $whois->lookup($row->domain, $deadline);
        if ($lookup['status'] !== 'ok') $this->failed_started = $started;

        if ($lookup['status'] === 'ok') return array('whois', $this->publicDetails($lookup, 'whois', $started), '', $whois_line);
        if ($lookup['status'] === 'not_found') return array('', null, _("The domain wasn't found in WHOIS."), $whois_line);
        if ($lookup['status'] === 'rate_limited') return array('', null, _('The WHOIS server is limiting requests. Please try again later.'), $whois_line);
        if (!empty($lookup['connection_failed'])) return array('', null, _("WHOIS couldn't be reached (your server may block port 43)."), $whois_line);

        return array('', null, _('The WHOIS lookup failed. Please try again later.'), $whois_line);
    }

    /*
     * RDAP and WHOIS don't give WHOIS privacy or auto renewal, so those are left alone. Both give the expiry date and
     * name servers, and RDAP also gives the status codes (see statusRead()), while a WHOIS lookup records the lock status
     * as Unknown. $started is when the lookup started (UTC).
     */
    protected function publicDetails($lookup, $source, $started = '')
    {
        $details = array('expiry_date' => '', 'name_servers' => $lookup['name_servers'] ?? array(), 'privacy' => null,
            'autorenew' => null, 'notes' => array(), 'status' => null);

        if ($this->status_available) {
            $details['status'] = $source === 'whois' ? array('read' => 'whois', 'codes' => array(), 'started' => $started)
                : $this->statusRead($lookup, $started);
            if ($details['status'] === null) {
                $details['notes'][] = _("The lock status couldn't be checked this time.");
                $this->failed_started = $started;
            }
        }

        if ($this->isDate((string) ($lookup['expiry_date'] ?? ''))) {
            $details['expiry_date'] = $lookup['expiry_date'];
        } elseif ($source === 'rdap') {
            $details['notes'][] = _("RDAP has no expiry date for this domain, so the expiry date wasn't changed.");
        } else {
            $details['notes'][] = _("WHOIS has no expiry date for this domain, so the expiry date wasn't changed.");
        }

        return $details;
    }

    /*
     * A completed status read from an RDAP lookup, as array('read' => found or none, 'codes', 'started'), or null when
     * it can't be saved (a lookup that failed, or an incomplete read). $started is when the lookup started (UTC).
     */
    protected function statusRead($lookup, $started)
    {
        if (($lookup['status'] ?? '') !== 'ok' || !in_array($lookup['status_read'] ?? '', array('found', 'none'), true)) return null;

        return array('read' => $lookup['status_read'],
            'codes' => $lookup['status_read'] === 'found' ? DomainStatus::ordered($lookup['statuses'] ?? array()) : array(),
            'started' => $started);
    }

    /*
     * After a registrar API answer, one status-only RDAP lookup for the registry's status codes (its expiry date and
     * name servers are ignored, since the API's answer wins for those), when at least MIN_LOOKUP_TIME is left. A TLD
     * without an RDAP server is recorded as Unknown, as a WHOIS lookup is. Returns statusRead()'s array, or null when the
     * status couldn't be checked. Anything it throws is logged and treated as a check that couldn't be done, so the API's
     * details are still saved.
     */
    protected function fetchStatusOnly($row, $deadline)
    {
        $started = '';

        try {

            if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) return null;

            $started = gmdate('Y-m-d H:i:s');
            $lookup = $this->rdap()->lookup($row->domain, $deadline, true);

            if (($lookup['status'] ?? '') === 'no_server') return array('read' => 'whois', 'codes' => array(), 'started' => $started);

            $status = $this->statusRead($lookup, $started);
            if ($status === null) $this->failed_started = $started;
            return $status;

        } catch (\Throwable $e) {

            if ($started !== '') $this->failed_started = $started;

            try {
                $this->log->error('Unable to check a domain\'s lock status after its registrar API answered',
                    array('Domain' => $row->domain, 'Error' => get_class($e)));
            } catch (\Throwable $log_error) {
            }

            return null;

        }
    }

    /*
     * Records that this refresh's lookup couldn't read the status codes (see $failed_started), as the domain's
     * epp_status_failed_time, so the edit page and the list can say so. It's only saved when that lookup started after
     * both the last successful read and the last failure saved, in one statement, so an older failure never replaces a
     * newer result, whatever order two refreshes finish in. The saved statuses and their date are left as they are. A
     * failure to save it is logged and doesn't change the refresh's result.
     */
    protected function saveFailedStatus($domain_id)
    {
        if (!$this->status_available || $this->failed_started === '') return;

        try {

            $stmt = $this->deeb->cnxx->prepare("
                UPDATE domains
                SET epp_status_failed_time = :started
                WHERE id = :domain_id
                  AND epp_status_time < :started_after_read
                  AND epp_status_failed_time < :started_after_failure");
            $stmt->bindValue('started', $this->failed_started, \PDO::PARAM_STR);
            $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
            $stmt->bindValue('started_after_read', $this->failed_started, \PDO::PARAM_STR);
            $stmt->bindValue('started_after_failure', $this->failed_started, \PDO::PARAM_STR);
            $stmt->execute();

        } catch (\Throwable $e) {

            try {
                $this->log->error('Unable to record a failed lock status check', array('Domain ID' => $domain_id, 'Error' => get_class($e)));
            } catch (\Throwable $log_error) {
            }

        }
    }

    // The RDAP client (replaced in tests)
    protected function rdap()
    {
        return new Rdap();
    }

    /*
     * Compares the fetched details with the domain as it is now and writes any changes, all in one transaction (network
     * calls are already done). Returns array(success, lines).
     */
    protected function apply($before, $user_id, $source, $details)
    {
        $pdo = $this->deeb->cnxx;
        $dns_servers = $this->normaliseDnsServers($details['name_servers']);
        $notes_lines = $details['notes'];
        if (empty($dns_servers)) $notes_lines[] = _("No DNS servers were returned, so the DNS profile wasn't changed.");

        try {

            $pdo->beginTransaction();

            // The Change History's action starts before the domain is locked, so its wait for the custom field
            // definitions (see ChangeHistory::start()) never holds the domain's lock, as every recorded writer does
            $history_op = (new ChangeHistory($pdo))->start(ChangeHistory::SOURCE_REFRESH, $user_id, 'domain_refresh');

            $row = $this->getDomainRow((int) $before->id, true);

            if (!$row) {

                $pdo->rollback();
                $missing = _('That domain no longer exists.');
                return array(false, array($missing), array('reasons' => array($missing), 'changes' => array(), 'notes' => array()));

            }

            if ((int) $row->account_id !== (int) $before->account_id
                || (int) $row->registrar_id !== (int) $before->registrar_id
                || (int) $row->api_registrar_id !== (int) $before->api_registrar_id
                || !hash_equals($this->credentialHash($before), $this->credentialHash($row))) {

                $pdo->rollback();
                $changed = _('The domain changed while it was being refreshed. Please try again.');
                return array(false, array($changed), array('reasons' => array($changed), 'changes' => array(), 'notes' => array()));

            }

            /*
             * The refresh is recorded in the Change History (its action started above), in this transaction: the fields
             * Refresh owns, compared before and after (not the notes, since its dated note line says what the change
             * rows already say, and not the DNS profile, since its DNS servers are recorded instead, so a profile
             * created by the refresh doesn't add a second row)
             */
            $history_before = $history_op->capture('domain', array((int) $row->id),
                array('expiry_date', 'privacy', 'autorenew', 'total_cost'), false);
            $history_dns = null;

            $changes = array();      // lines for the user
            $note_changes = array(); // sentences for the domain's notes (stored in English)
            $new = array('expiry_date' => $row->expiry_date, 'dns_id' => (int) $row->dns_id,
                'privacy' => (int) $row->privacy, 'autorenew' => (int) $row->autorenew);

            if ($details['expiry_date'] !== '' && $details['expiry_date'] !== $row->expiry_date) {

                $new['expiry_date'] = $details['expiry_date'];
                $changes[] = sprintf(_('Expiry date changed from %1$s to %2$s.'), $this->html($row->expiry_date), $this->html($details['expiry_date']));
                $note_changes[] = 'Expiry date changed from ' . $row->expiry_date . ' to ' . $details['expiry_date'] . '.';

            }

            if (!empty($dns_servers)) {

                $current_profile = $this->lockDnsProfile((int) $row->dns_id);

                if (!$current_profile || array_count_values($current_profile['servers']) != array_count_values($dns_servers)) {

                    list($new_dns_id, $created) = $this->queue->findOrCreateDnsProfile($dns_servers, $user_id,
                        '[created by refresh]', 'Created by refresh.', true);
                    $new_dns_id = (int) $new_dns_id;

                    if ($new_dns_id !== (int) $row->dns_id) {

                        $new['dns_id'] = $new_dns_id;
                        $history_dns = array($current_profile ? implode("\n", $current_profile['servers']) : '',
                            implode("\n", $dns_servers));
                        $old_name = $this->html($current_profile ? $current_profile['name'] : '');
                        $new_name = $this->html($this->getDnsProfileName($new_dns_id));

                        if ($created) {
                            $changes[] = sprintf(_('DNS profile changed from %1$s to a new profile, %2$s, created for these DNS servers.'), $old_name, $new_name);
                        } else {
                            $changes[] = sprintf(_('DNS profile changed from %1$s to %2$s.'), $old_name, $new_name);
                        }
                        $note_changes[] = 'DNS profile changed.';

                    }

                }

            }

            if ($details['privacy'] !== null && $details['privacy'] !== (int) $row->privacy) {

                $new['privacy'] = $details['privacy'];
                $changes[] = $details['privacy'] === 1 ? _('WHOIS privacy turned on.') : _('WHOIS privacy turned off.');
                $note_changes[] = $details['privacy'] === 1 ? 'WHOIS privacy turned on.' : 'WHOIS privacy turned off.';

            }

            if ($details['autorenew'] !== null && $details['autorenew'] !== (int) $row->autorenew) {

                $new['autorenew'] = $details['autorenew'];
                $changes[] = $details['autorenew'] === 1 ? _('Auto-renewal turned on.') : _('Auto-renewal turned off.');
                $note_changes[] = $details['autorenew'] === 1 ? 'Auto renewal turned on.' : 'Auto renewal turned off.';

            }

            /*
             * The lock status is saved only when this lookup started after the one already saved, so whichever of two
             * overlapping refreshes started later wins, whatever order they're saved in (DomainStatus::isNewer()). Every
             * completed read is saved, even with the same codes, so the date says when they were last confirmed.
             */
            $status = $this->status_available ? ($details['status'] ?? null) : null;
            $status_write = false;
            $status_history = array(); // field => array(old, new), by DomainStatus::changes()'s rules

            if ($status !== null) {

                if (DomainStatus::isNewer($status['started'], $row->epp_status_time)) {

                    $status_write = true;
                    $status_changes = DomainStatus::changes(array('transfer_lock' => (int) $row->transfer_lock,
                        'codes' => DomainStatus::fromColumn($row->epp_status), 'time' => $row->epp_status_time), $status);
                    $changes = array_merge($changes, $status_changes['lines']);
                    $note_changes = array_merge($note_changes, $status_changes['notes']);
                    $notes_lines = array_merge($notes_lines, $status_changes['info']);
                    $status_history = $status_changes['history'];

                } else {

                    $notes_lines[] = _("The lock status wasn't updated, because a check made at the same time or later was already saved.");

                }

            }

            // Nothing changed, so nothing is written, apart from a lock status read, which is saved quietly (without
            // a note or a new update time).
            if (empty($changes)) {

                $no_changes = _('No changes were made.');

                if ($status_write) {

                    $stmt = $pdo->prepare("
                        UPDATE domains
                        SET transfer_lock = :transfer_lock,
                            epp_status = :epp_status,
                            epp_status_time = :epp_status_time
                        WHERE id = :domain_id");
                    $this->bindStatus($stmt, $status);
                    $stmt->bindValue('domain_id', (int) $row->id, \PDO::PARAM_INT);
                    $stmt->execute();
                    // A quiet read can still change the registry status codes, which the history keeps
                    $this->recordStatus($history_op, (int) $row->id, $status, $status_history);
                    $history_op->finish();
                    $pdo->commit();

                } else {

                    $pdo->rollback();

                }

                return array(true, array_merge(array($no_changes), $notes_lines),
                    array('reasons' => array($no_changes), 'changes' => array(), 'notes' => $notes_lines));

            }

            if ($source === 'registrar') {
                $note_source = "the registrar's API";
            } elseif ($source === 'rdap') {
                $note_source = 'RDAP';
            } else {
                $note_source = 'WHOIS';
            }

            // Newest note first, as the bulk updater and renewals add theirs.
            $note = $this->time->timeBasic() . ' - Refreshed using ' . $note_source . '. ' . implode(' ', $note_changes);
            $notes = ((string) $row->notes === '') ? $note : $note . "\r\n\r\n" . $row->notes;

            $sql_fees = '';
            $cost_lines = array();
            if ($new['privacy'] !== (int) $row->privacy) {

                // This domain's fee and total cost only, as the edit page works them out.
                list($fee_id, $total_cost) = $this->domain->getFeeIdAndTotalCost($new['privacy'], $row->tld, (int) $row->registrar_id);
                $sql_fees = ',
                    fee_id = :fee_id,
                    fee_fixed = :fee_fixed,
                    total_cost = :total_cost';
                // A side effect rather than a field, so the compact result lists it with the notes
                $cost_lines[] = _("The domain's total cost was recalculated for the new WHOIS privacy setting.");

            }

            $stmt = $pdo->prepare("
                UPDATE domains
                SET expiry_date = :expiry_date,
                    dns_id = :dns_id,
                    privacy = :privacy,
                    autorenew = :autorenew,
                    notes = :notes,
                    update_time = :update_time" . $sql_fees . ($status_write ? ",
                    transfer_lock = :transfer_lock,
                    epp_status = :epp_status,
                    epp_status_time = :epp_status_time" : "") . "
                WHERE id = :domain_id");
            $stmt->bindValue('expiry_date', $new['expiry_date'], \PDO::PARAM_STR);
            $stmt->bindValue('dns_id', $new['dns_id'], \PDO::PARAM_INT);
            $stmt->bindValue('privacy', $new['privacy'], \PDO::PARAM_INT);
            $stmt->bindValue('autorenew', $new['autorenew'], \PDO::PARAM_INT);
            $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
            $stmt->bindValue('update_time', $this->time->stamp(), \PDO::PARAM_STR);
            if ($sql_fees !== '') {
                $stmt->bindValue('fee_id', $fee_id, \PDO::PARAM_INT);
                $stmt->bindValue('fee_fixed', $fee_id ? 1 : 0, \PDO::PARAM_INT);
                $stmt->bindValue('total_cost', strval($total_cost), \PDO::PARAM_STR);
            }
            if ($status_write) $this->bindStatus($stmt, $status);
            $stmt->bindValue('domain_id', (int) $row->id, \PDO::PARAM_INT);
            $stmt->execute();

            if ($history_dns !== null) {
                $history_op->record('domain', (int) $row->id, 'dns_servers', $history_dns[0], $history_dns[1], $source);
            }
            $this->recordStatus($history_op, (int) $row->id, $status, $status_history);
            $history_op->compare($history_before, '', $source);
            $history_op->finish();

            $pdo->commit();

            return array(true, array_merge($changes, $cost_lines, $notes_lines),
                array('reasons' => array(), 'changes' => $changes, 'notes' => array_merge($notes_lines, $cost_lines)));

        } catch (\Throwable $e) {

            if ($pdo->inTransaction()) $pdo->rollback();

            $log_message = 'Unable to refresh domain';
            $log_extra = array('Domain' => $before->domain, 'Error' => $e);
            $this->log->critical($log_message, $log_extra);

            // Another request changed the same rows at the same moment (see domainmod_restore_is_conflict())
            $not_updated = (function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e))
                ? domainmod_restore_conflict_refresh_message() : _("The domain couldn't be updated. Please try again.");
            return array(false, array($not_updated), array('reasons' => array($not_updated), 'changes' => array(), 'notes' => array()));

        }
    }

    // Records a status read's history entries from DomainStatus::changes() (the lock, On Hold or Being Deleted, and the
    // registry status codes), marked as read from RDAP or WHOIS
    protected function recordStatus(ChangeHistoryOperation $history_op, $domain_id, $status, array $status_history)
    {
        $via = (($status['read'] ?? '') === 'whois') ? 'whois' : 'rdap';
        foreach ($status_history as $field => $values) {
            $history_op->record('domain', $domain_id, $field, (string) $values[0], (string) $values[1], $via);
        }
    }

    // Binds a status read's transfer_lock, epp_status, and epp_status_time (the time its lookup started)
    protected function bindStatus($stmt, $status)
    {
        $codes = DomainStatus::ordered($status['codes']);
        $stmt->bindValue('transfer_lock', DomainStatus::lockFor($codes), \PDO::PARAM_INT);
        $stmt->bindValue('epp_status', DomainStatus::toColumn($codes), \PDO::PARAM_STR);
        $stmt->bindValue('epp_status_time', $status['started'], \PDO::PARAM_STR);
    }

    /*
     * Lower case, without trailing dots, empty entries, duplicates, the registrar clients' placeholder names, or anything
     * that isn't a valid host name (which also keeps each one short enough for its column), and at most MAX_DNS_SERVERS
     * in the order given.
     */
    protected function normaliseDnsServers($dns_servers)
    {
        $clean = array();
        if (!is_array($dns_servers)) return $clean;

        $rdap = new Rdap();

        foreach ($dns_servers as $server) {

            if (!is_string($server)) continue;
            $server = rtrim(strtolower(trim($server)), '.');
            if (!$rdap->isHostname($server) || in_array($server, self::$placeholder_dns, true)
                || in_array($server, $clean, true)) continue;
            $clean[] = $server;

        }

        return array_slice($clean, 0, self::MAX_DNS_SERVERS);
    }

    // The domain's current DNS profile (name and lower-case servers), locked until the transaction ends, or null.
    protected function lockDnsProfile($dns_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT `name`, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10
            FROM dns
            WHERE id = :dns_id
            FOR UPDATE");
        $stmt->bindValue('dns_id', $dns_id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();
        if (!$row) return null;

        $servers = array_map('strtolower', array_filter(array($row->dns1, $row->dns2, $row->dns3, $row->dns4, $row->dns5,
            $row->dns6, $row->dns7, $row->dns8, $row->dns9, $row->dns10)));

        return array('name' => $row->name, 'servers' => array_values($servers));
    }

    protected function getDnsProfileName($dns_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT `name`
            FROM dns
            WHERE id = :dns_id");
        $stmt->bindValue('dns_id', $dns_id, \PDO::PARAM_INT);
        $stmt->execute();
        $name = $stmt->fetchColumn();
        $stmt->closeCursor();

        return (string) $name;
    }

    // A real Y-m-d date.
    protected function isDate($value)
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;

        $date = \DateTime::createFromFormat('!Y-m-d', $value);
        $errors = \DateTime::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    /*
     * A translated message that holds a TLD as ".%s", with the TLD and its dot isolated left to right, so a right-to-left
     * page doesn't show ".co.uk" as "co.uk.". The dot is part of the translated text, so it's moved inside the isolation
     * here, and a translation that puts it somewhere else still has the TLD itself isolated. The TLD is escaped.
     */
    protected function tldMessage($message, $tld)
    {
        $layout = new Layout();
        $isolated = $layout->ltr($tld);

        return str_replace('.' . $isolated, $layout->ltr('.' . $tld), sprintf($message, $isolated));
    }

    protected function html($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

} //@formatter:on
