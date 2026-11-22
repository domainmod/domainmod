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
 *
 * The Refresh Domains task (refreshDue()) runs the same lookup and save (refreshDomain()) across the portfolio a few
 * domains at a time, recording its changes in the Change History as Scheduled Refresh, without notes. Each domain's
 * schedule is kept in domain_refresh_status (see DomainRefreshSchedule).
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

    // The Refresh Domains task: the database version that adds it, its time per run (Run Now's is shorter), the most
    // domains it looks up in a run, the candidates it reads, and the time it needs left in the request when it starts
    // a run that takes turns with the other tasks (see Scheduler::turn())
    const AVAILABLE_FROM = '4.27.0';
    const REFRESH_BUDGET = 120;
    const RUN_NOW_BUDGET = 20;
    const REFRESH_BATCH = 50;
    const REFRESH_CANDIDATES = 500;
    const REFRESH_NEEDS = 20;
    // Seconds between two calls to the same registrar API in a run, and the APIs that document a stricter limit
    const REGISTRAR_GAP = 2.0;
    public static $registrar_gaps = array(
        // Namecheap allows 50 calls a minute, 700 an hour, and 8,000 a day per key, a refresh makes two (getInfo and
        // getList), and the account's other callers (clicked Refreshes and the Domain Queue) share them: one refresh
        // every 6 seconds keeps the task under 240 calls an hour (6 runs of 120 seconds) and 5,760 a day
        'Namecheap' => 6.0);

    // The task's English name and description, as the database stores them (Scheduler::taskText() translates them)
    const TASK_NAME = 'Refresh Domains';
    const TASK_DESCRIPTION = 'Refreshes the expiry date, DNS servers, and other details of each live domain, a few domains at a time.';

    // The domain_refresh_status columns this version creates (see scheduleAvailable() and the restore's clean-up)
    const STATUS_COLUMNS = array('domain_id', 'last_attempt', 'last_turn', 'last_success', 'next_due', 'hold_until',
        'failures', 'last_source', 'last_result', 'public_next', 'revision');

    // Whether the task's table is there, by connection, worked out once per request (see scheduleAvailable())
    protected static $schedule_available = array();

    // Whether a save in the task's run met another request's write (errors 1020 and 1213, see hadConflict())
    protected $had_conflict = false;

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

        $wait = $this->throttle((int) $row->id);
        if ($wait !== '') return array(false, array($wait), array('summary' => $wait, 'reasons' => array(), 'changes' => array(), 'notes' => array()));

        $outcome = $this->refreshDomain($row, $user_id, microtime(true) + self::DEADLINE, null);

        // A clicked Refresh that worked moves the domain's next scheduled check on, as a scheduled success does. One
        // that failed, or whose result was thrown away, leaves its schedule as it is.
        if ($outcome['success']) $this->saveOutcome($row, $outcome, null, false);

        return array($outcome['success'], $outcome['lines'], $outcome['parts']);
    }

    /*
     * Looks one domain up and applies the changes: the lookup and save that both the clicked Refresh and the Refresh
     * Domains task make. $deadline is the absolute time every request must finish by. $run is null for a clicked
     * Refresh, and for the task it's the run's state (see newRun()): the busy servers, the WHOIS counts, the registrar
     * accounts whose API failed, each API's last call, and whether this domain goes straight to public data.
     *
     * Returns the outcome, from which the domain's status row is worked out after it returns (see saveOutcome()):
     * success (bool), kind (a DomainRefreshSchedule kind), result (the code for last_result), source (registrar, rdap,
     * whois, or ''), started (when the lookup that gave the result started, a timestamp, or 0), changed (whether the
     * save recorded anything in the Change History), expiry_date (the domain's expiry date after the save), and lines and
     * parts (the clicked Refresh's message, translated and escaped).
     */
    protected function refreshDomain($row, $user_id, $deadline, $run)
    {
        // Kept left to right inside the translated sentences below, for right-to-left languages
        $domain_html = (new Layout())->ltr($row->domain);

        $scheduled = $run !== null;
        // Nothing a lookup remembers about one domain carries over to the next
        $this->status_available = DomainStatus::isAvailable($this->deeb->cnxx);
        $this->failed_started = '';
        Rdap::newAttempt();
        if ($scheduled) $run->server = '';

        $source = '';
        $started = 0;
        $fallback_lines = array();
        $details = null;
        $api_failed = false;
        $api_started = 0;
        // The DNS profile's servers before the lookup, so apply() can tell when it's edited meanwhile
        $before_dns = $this->dnsServers((int) $row->dns_id);
        // looked_up says whether anything was asked of a registrar or registry (for the run's cap and count)
        $outcome = array('success' => false, 'kind' => DomainRefreshSchedule::FAILURE, 'result' => '', 'source' => '',
            'started' => 0, 'changed' => false, 'expiry_date' => (string) $row->expiry_date, 'looked_up' => false,
            'public_done' => true, 'lines' => array(), 'parts' => array());

        // 1. The registrar's API (skipped by the task when an earlier slow attempt left no time for public data, or
        // when the account's API failed earlier in the run)
        $api_name = (string) $row->api_registrar_name;
        if ($api_name !== '' && $api_name !== 'RDAP') {

            if (in_array($api_name, self::$unsupported_apis, true)) {

                $fallback_lines[] = sprintf(_("Refreshing through the %s API isn't supported yet, so public RDAP and WHOIS data was used instead."), $this->html($api_name));

            } elseif (!$this->hasCredentials($row, $api_name)) {

                $fallback_lines[] = _("This registrar account's API details aren't filled in, so public RDAP and WHOIS data was used instead.");

            } elseif ($scheduled && ($run->public_next || isset($run->failed_accounts[(int) $row->account_id]))) {

                $fallback_lines[] = _("Your registrar's API didn't return this domain's details, so public RDAP and WHOIS data was used instead.");

            } else {

                if ($scheduled) $this->paceRegistrar($run, $api_name, $deadline);
                // The wait between calls can leave too little time for the call, and then nothing is asked yet
                if ($scheduled && $deadline - microtime(true) < self::MIN_LOOKUP_TIME) {
                    return array('kind' => DomainRefreshSchedule::DEFERRED, 'result' => DomainRefreshSchedule::DEFERRED) + $outcome;
                }
                $api_started = time();
                $details = $this->fetchFromRegistrar($row, $api_name, $deadline);
                // A Dynadot call its shared details budget stopped before sending asked no one
                if (!($api_name === 'Dynadot' && Dynadot::$last_call_skipped)) $outcome['looked_up'] = true;
                if ($details === null) {
                    $api_failed = true;
                    // The account's other domains go straight to public data for the rest of the run (this includes a
                    // Dynadot call its shared details budget stopped, Dynadot::$last_call_skipped)
                    if ($scheduled) $run->failed_accounts[(int) $row->account_id] = true;
                    $fallback_lines[] = _("Your registrar's API didn't return this domain's details, so public RDAP and WHOIS data was used instead.");
                } else {
                    $source = 'registrar';
                    $started = $api_started;
                    // The API doesn't give the registry's status codes, so they come from one status-only RDAP lookup
                    if ($this->status_available) {
                        $details['status'] = $this->fetchStatusOnly($row, $deadline, $run);
                        if ($details['status'] === null) $details['notes'][] = _("The lock status couldn't be checked this time.");
                    }
                }

            }

        }

        // 2. RDAP, then WHOIS for TLDs without an RDAP server.
        if ($details === null) {

            // The task checks the servers first, so a busy one, or a lookup there's no time for, is skipped without
            // asking anyone
            $public = $scheduled ? $this->preflight($row, $deadline, $run) : null;
            $error = '';

            if ($public === null) {

                list($source, $details, $error, $whois_line, $public) = $this->fetchPublicData($row, $deadline);
                if ($whois_line !== '') $fallback_lines[] = $whois_line;
                // Only a request that was sent counts as a lookup (a host name that didn't resolve asked no one)
                if (!empty($public['sent'])) $outcome['looked_up'] = true;

                if ($scheduled) {
                    if (!empty($public['busy']) && $run->server !== '') $run->busy[$run->server] = true;
                    if (($public['whois_server'] ?? '') !== '') {
                        $run->whois_counts[$public['whois_server']] = ($run->whois_counts[$public['whois_server']] ?? 0) + 1;
                    }
                }

            }

            if ($details === null) {

                $kind = $public['kind'];
                if ($kind === DomainRefreshSchedule::DEFERRED && $api_failed) $kind = DomainRefreshSchedule::DEFERRED_AFTER_API;
                // A lookup that was put off asked nothing, so it never counts as a failed status check
                if ($kind !== DomainRefreshSchedule::DEFERRED && $kind !== DomainRefreshSchedule::DEFERRED_AFTER_API
                    && $kind !== DomainRefreshSchedule::BUSY) {
                    $this->saveFailedStatus((int) $row->id, $row->domain, $scheduled);
                }

                $failed = sprintf(_("Domain %s couldn't be refreshed."), $domain_html);
                return array('kind' => $kind, 'result' => $kind === DomainRefreshSchedule::FAILURE
                        || $kind === DomainRefreshSchedule::RATE_LIMITED ? $public['result'] : $kind,
                    // When nothing public was sent, the attempt's lookup time is the registrar call's
                    'source' => $public['source'], 'public_done' => $public['public_done'],
                    'started' => empty($public['sent']) && $api_started > 0 ? $api_started : $public['started'],
                    'lines' => array_merge(array($failed), $fallback_lines, array($error)),
                    'parts' => array('summary' => $failed, 'reasons' => array($error), 'changes' => array(), 'notes' => $fallback_lines)) + $outcome;

            }

            $started = $public['started'];

        }

        // 3. Compare with the domain as it is now, and apply any changes (the task records them as Scheduled Refresh,
        // adds no note, and throws the result away if the domain was marked Expired or Sold meanwhile).
        list($success, $lines, $parts) = $this->apply($row, $user_id, $source, $details,
            $scheduled ? ChangeHistory::SOURCE_SCHEDULED_REFRESH : ChangeHistory::SOURCE_REFRESH, !$scheduled, $scheduled,
            $before_dns);
        $failure = $parts['failure'] ?? '';
        // A stale result writes nothing for the task, its failed status check included
        if (!$scheduled || $success || $failure === 'save_failed') $this->saveFailedStatus((int) $row->id, $row->domain, $scheduled);

        $outcome['source'] = $source;
        $outcome['started'] = $started;

        if (!$success) {

            $failed = sprintf(_("Domain %s couldn't be refreshed."), $domain_html);
            // Only a save that failed counts as a failure: a stale result, a deleted domain, or a write that met another
            // request's writes nothing, and the next run decides from the saved row
            if ($failure === 'conflict') $this->had_conflict = true;
            return array('kind' => $failure === 'save_failed' ? DomainRefreshSchedule::FAILURE : DomainRefreshSchedule::NONE,
                'result' => $failure === 'save_failed' ? 'save_failed' : $failure,
                'lines' => array_merge(array($failed), $lines),
                'parts' => array('summary' => $failed, 'reasons' => $parts['reasons'], 'changes' => array(), 'notes' => $fallback_lines)) + $outcome;

        }

        if ($source === 'registrar') {
            $first_line = sprintf(_("Domain %s refreshed using your registrar's API."), $domain_html);
        } elseif ($source === 'rdap') {
            $first_line = sprintf(_('Domain %s refreshed using RDAP.'), $domain_html);
        } else {
            $first_line = sprintf(_('Domain %s refreshed using WHOIS.'), $domain_html);
        }

        return array('success' => true, 'kind' => DomainRefreshSchedule::SUCCESS,
            'result' => !empty($parts['changed']) ? 'changed' : 'ok', 'changed' => !empty($parts['changed']),
            'expiry_date' => $details['expiry_date'] !== '' ? $details['expiry_date'] : (string) $row->expiry_date,
            'lines' => array_merge(array($first_line), $fallback_lines, $lines),
            'parts' => array('summary' => $first_line, 'reasons' => $parts['reasons'], 'changes' => $parts['changes'],
                'notes' => array_merge($fallback_lines, $parts['notes']))) + $outcome;
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
            SELECT d.id, d.domain, d.tld, d.expiry_date, d.dns_id, d.privacy, d.autorenew, d.notes, d.account_id, d.active," . ($this->status_available ? "
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
     * RDAP, then WHOIS when the TLD has no RDAP server: array(source, details, error, whois_line, result). details is
     * null when nothing could be fetched, and error then says why. result is how the lookup ended, for the task: kind (a
     * DomainRefreshSchedule kind), result (the code for last_result), source, started (when the lookup started, a
     * timestamp, or 0), busy (whether its server should be skipped for the rest of the run), whois_server (the WHOIS
     * server a query was made to, or ''), and public_done (whether the public attempt ended: its request for the domain
     * was sent, or the answer was definite without one). A lookup the deadline stopped before it asked anyone is put off
     * (deferred), and never counts as a failed status check.
     */
    protected function fetchPublicData($row, $deadline)
    {
        $result = array('kind' => DomainRefreshSchedule::DEFERRED, 'result' => DomainRefreshSchedule::DEFERRED,
            'source' => '', 'started' => 0, 'busy' => false, 'whois_server' => '', 'public_done' => false, 'sent' => false);

        if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) {
            return array('', null, _("RDAP and WHOIS couldn't be tried, because the lookup ran out of time. Please try again later."), '', $result);
        }

        $started = gmdate('Y-m-d H:i:s');
        $result['started'] = time();
        $rdap = $this->rdap();
        $lookup = $rdap->lookup($row->domain, $deadline);

        if ($rdap->deadline_stopped) return array('', null, _('The RDAP lookup failed. Please try again later.'), '', array('started' => 0) + $result);

        if (!in_array($lookup['status'], array('ok', 'no_server'), true)) $this->failed_started = $started;
        $result['source'] = 'rdap';
        // Only a request that reached the registry ends the public attempt (a refused connection or a failed
        // certificate check is still a failure, but asked no one)
        $result['public_done'] = $rdap->domain_request_delivered;
        $result['sent'] = $rdap->domain_request_delivered;
        $failure = array('kind' => DomainRefreshSchedule::FAILURE) + $result;

        if ($lookup['status'] === 'not_found') return array('', null, _("The domain wasn't found in RDAP."), '', array('result' => 'not_found') + $failure);
        if ($lookup['status'] === 'rate_limited') {
            return array('', null, _('The RDAP server is limiting requests. Please try again later.'), '',
                array('kind' => DomainRefreshSchedule::RATE_LIMITED, 'result' => 'rate_limited', 'busy' => true) + $result);
        }
        if ($lookup['status'] === 'ok') {
            return array('rdap', $this->publicDetails($lookup, 'rdap', $started), '', '',
                array('kind' => DomainRefreshSchedule::SUCCESS, 'result' => 'ok') + $result);
        }
        // A record for another domain fails only this domain, and its server isn't skipped
        if ($lookup['status'] === 'mismatch') return array('', null, _('The RDAP lookup failed. Please try again later.'), '', array('result' => 'mismatch') + $failure);
        if ($lookup['status'] !== 'no_server') {
            return array('', null, _('The RDAP lookup failed. Please try again later.'), '',
                array('result' => 'rdap_failed', 'busy' => true) + $failure);
        }

        // No RDAP server for the TLD, so try WHOIS.
        $whois_line = $this->tldMessage(_("RDAP isn't available for .%s domains, so WHOIS was used instead."), $row->tld);
        $whois = $this->whois();
        $started = gmdate('Y-m-d H:i:s');
        $result['started'] = time();
        $result['source'] = 'whois';
        $failure = array('kind' => DomainRefreshSchedule::FAILURE) + $result;
        list($found, $server) = $whois->serverFor(Domain::forApi($row->domain), $deadline);

        // The deadline cut off the question to IANA, so nothing failed
        if (!$found && $whois->discovery_cut_off) {
            return array('', null, $this->tldMessage(_("The WHOIS server for .%s domains couldn't be found. Please try again later."), $row->tld), '',
                array('kind' => DomainRefreshSchedule::DEFERRED, 'result' => DomainRefreshSchedule::DEFERRED, 'started' => 0) + $result);
        }

        // IANA couldn't be asked which WHOIS server to use, or the TLD has neither, so the check failed
        if (!$found || $server === '') $this->failed_started = $started;
        if (!$found) return array('', null, $this->tldMessage(_("The WHOIS server for .%s domains couldn't be found. Please try again later."), $row->tld), '', array('result' => 'whois_server_unknown') + $failure);
        // Definite only when the RDAP bootstrap is fresh: a stale copy may be missing a TLD's new server
        if ($server === '') return array('', null, $this->tldMessage(_('Neither RDAP nor WHOIS is available for .%s domains.'), $row->tld), '', array('result' => 'no_source', 'public_done' => !Rdap::$bootstrap_stale) + $failure);

        if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) {
            return array('', null, _('The WHOIS lookup failed. Please try again later.'), $whois_line,
                array('kind' => DomainRefreshSchedule::DEFERRED, 'result' => DomainRefreshSchedule::DEFERRED, 'started' => 0) + $result);
        }

        $started = gmdate('Y-m-d H:i:s');
        $result['started'] = time();
        $result['whois_server'] = $server;
        $failure = array('kind' => DomainRefreshSchedule::FAILURE) + $result;
        $lookup = $whois->lookup($row->domain, $deadline);

        // A query the deadline stopped wasn't sent, so it doesn't count toward the server's share either
        if ($whois->deadline_stopped) {
            return array('', null, _('The WHOIS lookup failed. Please try again later.'), $whois_line,
                array('kind' => DomainRefreshSchedule::DEFERRED, 'result' => DomainRefreshSchedule::DEFERRED, 'started' => 0,
                    'whois_server' => '') + $result);
        }

        if ($lookup['status'] !== 'ok') $this->failed_started = $started;
        // A connection the server refused wrote no query: still a failure, but the public attempt didn't end, and it
        // doesn't count as a lookup. A query that was written counts, whatever the server did next
        $result['public_done'] = $whois->query_written;
        $result['sent'] = $whois->query_written;
        $failure = array('kind' => DomainRefreshSchedule::FAILURE) + $result;

        if ($lookup['status'] === 'ok') {
            return array('whois', $this->publicDetails($lookup, 'whois', $started), '', $whois_line,
                array('kind' => DomainRefreshSchedule::SUCCESS, 'result' => 'ok') + $result);
        }
        if ($lookup['status'] === 'not_found') return array('', null, _("The domain wasn't found in WHOIS."), $whois_line, array('result' => 'not_found') + $failure);
        if ($lookup['status'] === 'rate_limited') {
            return array('', null, _('The WHOIS server is limiting requests. Please try again later.'), $whois_line,
                array('kind' => DomainRefreshSchedule::RATE_LIMITED, 'result' => 'rate_limited', 'busy' => true) + $result);
        }
        if (!empty($lookup['connection_failed'])) {
            return array('', null, _("WHOIS couldn't be reached (your server may block port 43)."), $whois_line,
                array('result' => 'whois_unreachable', 'busy' => true) + $failure);
        }

        return array('', null, _('The WHOIS lookup failed. Please try again later.'), $whois_line,
            array('result' => 'whois_failed', 'busy' => true) + $failure);
    }

    /*
     * The task's checks before a public lookup, as the Domain Queue's RDAP import makes them: the TLD's RDAP server (or,
     * without one, its WHOIS server) is found with the domain's own deadline, a server that was busy earlier in the run
     * (or a WHOIS server that has had its share) is skipped, and a lookup without enough time left is put off. Returns
     * null to go ahead (with $run->server set to the server's key), or the result fetchPublicData() would give.
     */
    protected function preflight($row, $deadline, $run)
    {
        $result = array('kind' => DomainRefreshSchedule::DEFERRED, 'result' => DomainRefreshSchedule::DEFERRED,
            'source' => '', 'started' => 0, 'busy' => false, 'whois_server' => '', 'public_done' => false);
        if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) return $result;

        $failure = array('kind' => DomainRefreshSchedule::FAILURE, 'started' => time()) + $result;
        $ascii_domain = Domain::forApi($row->domain);
        if ($ascii_domain === '') return array('result' => 'invalid_name', 'public_done' => true) + $failure;

        $rdap = $this->rdap();
        $server = $rdap->serverFor($ascii_domain, $deadline);

        if ($server !== '') {

            $key = 'rdap:' . strtolower((string) parse_url($server, PHP_URL_HOST));

        } else {

            // Without IANA's bootstrap (it can't be had, or the deadline cut its download off, even with an expired
            // copy that doesn't name the TLD), nothing was asked of any registry, so the domain waits for a later run
            if (!$rdap->hasBootstrap($deadline) || Rdap::downloadCutOff()) return $result;

            $whois = $this->whois();
            list($found, $whois_server) = $whois->serverFor($ascii_domain, $deadline);
            if (!$found) return $whois->discovery_cut_off ? $result : array('source' => 'whois', 'result' => 'whois_server_unknown') + $failure;
            if ($whois_server === '') return array('source' => 'whois', 'result' => 'no_source', 'public_done' => !Rdap::$bootstrap_stale) + $failure;
            if (($run->whois_counts[$whois_server] ?? 0) >= DomainQueue::WHOIS_PER_SERVER) {
                return array('kind' => DomainRefreshSchedule::BUSY, 'result' => DomainRefreshSchedule::BUSY) + $result;
            }
            $key = 'whois:' . $whois_server;

        }

        if (isset($run->busy[$key])) return array('kind' => DomainRefreshSchedule::BUSY, 'result' => DomainRefreshSchedule::BUSY) + $result;
        if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) return $result;

        $run->server = $key;
        return null;
    }

    // Waits so calls to the same registrar API in one run are at least its gap apart (REGISTRAR_GAP, or the API's own
    // stricter one), never past the domain's deadline
    protected function paceRegistrar($run, $api_name, $deadline)
    {
        $gap = self::$registrar_gaps[$api_name] ?? self::REGISTRAR_GAP;

        if (isset($run->api_last[$api_name])) {
            $wait = min($gap - (microtime(true) - $run->api_last[$api_name]), $deadline - microtime(true));
            if ($wait > 0) usleep((int) ($wait * 1000000));
        }

        $run->api_last[$api_name] = microtime(true);
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
     *
     * For the task ($run), it's skipped when the TLD's RDAP server was busy earlier in the run, and only a real rate
     * limit from the registry marks the server busy (never an error, which also means the time ran out). A lookup
     * skipped, or stopped by its deadline before it asked the registry, counts as not checked, never as a failed check.
     */
    protected function fetchStatusOnly($row, $deadline, $run = null)
    {
        $started = '';

        try {

            if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) return null;

            $rdap = $this->rdap();
            $key = '';
            if ($run !== null) {
                $ascii_domain = Domain::forApi($row->domain);
                $server = $ascii_domain !== '' ? $rdap->serverFor($ascii_domain, $deadline) : '';
                if ($server !== '') $key = 'rdap:' . strtolower((string) parse_url($server, PHP_URL_HOST));
                if ($key !== '' && isset($run->busy[$key])) return null;
                // finding the server (a bootstrap download) can use up the time a lookup needs
                if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) return null;
            }

            $started = gmdate('Y-m-d H:i:s');
            $lookup = $rdap->lookup($row->domain, $deadline, true);

            if ($rdap->deadline_stopped) return null;
            if ($run !== null && $key !== '' && ($lookup['status'] ?? '') === 'rate_limited') $run->busy[$key] = true;

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
     * failure to save it is logged and doesn't change the refresh's result. It's only saved on the domain looked up (its
     * name, $domain, unchanged), and for the task ($require_live) only while it's still live (not Expired or Sold).
     */
    protected function saveFailedStatus($domain_id, $domain = null, $require_live = false)
    {
        if (!$this->status_available || $this->failed_started === '') return;

        try {

            $stmt = $this->deeb->cnxx->prepare("
                UPDATE domains
                SET epp_status_failed_time = :started
                WHERE id = :domain_id
                  AND epp_status_time < :started_after_read
                  AND epp_status_failed_time < :started_after_failure" . ($domain !== null ? "
                  AND domain = :domain" : "") . ($require_live ? "
                  AND active NOT IN ('0', '10')" : ""));
            $stmt->bindValue('started', $this->failed_started, \PDO::PARAM_STR);
            $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
            if ($domain !== null) $stmt->bindValue('domain', (string) $domain, \PDO::PARAM_STR);
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

    // The RDAP and WHOIS clients (replaced in tests)
    protected function rdap()
    {
        return new Rdap();
    }

    protected function whois()
    {
        return new Whois();
    }

    /*
     * Compares the fetched details with the domain as it is now and writes any changes, all in one transaction (network
     * calls are already done). Returns array(success, lines, parts). The parts hold the lines in groups, and say why a
     * refresh failed (failure: stale, missing, conflict, or save_failed) or whether one that worked recorded anything in
     * the Change History (changed).
     *
     * $history_source is the Change History source (the task's is Scheduled Refresh), $add_note whether a dated line
     * goes in the domain's notes (the task adds none), and $require_live whether a domain marked Expired or Sold
     * meanwhile is thrown away (the task's rule). $before_dns is the DNS profile's servers read before the lookup (see
     * dnsServers()): the result is thrown away, as stale, when the domain's account, registrar, API details, name,
     * expiry date, DNS profile, WHOIS privacy, or auto renewal changed while it was looked up, or the profile's servers
     * were edited, so a lookup can only save over the values it started from.
     */
    protected function apply($before, $user_id, $source, $details, $history_source = ChangeHistory::SOURCE_REFRESH,
                             $add_note = true, $require_live = false, $before_dns = false)
    {
        $pdo = $this->deeb->cnxx;
        $dns_servers = $this->normaliseDnsServers($details['name_servers']);
        $notes_lines = $details['notes'];
        if (empty($dns_servers)) $notes_lines[] = _("No DNS servers were returned, so the DNS profile wasn't changed.");

        try {

            $pdo->beginTransaction();

            // The Change History's action starts before the domain is locked, so its wait for the custom field
            // definitions (see ChangeHistory::start()) never holds the domain's lock, as every recorded writer does
            $history_op = (new ChangeHistory($pdo))->start($history_source, $user_id, 'domain_refresh');

            $row = $this->getDomainRow((int) $before->id, true);

            if (!$row) {

                $pdo->rollback();
                $missing = _('That domain no longer exists.');
                return array(false, array($missing), array('reasons' => array($missing), 'changes' => array(), 'notes' => array(), 'failure' => 'missing'));

            }

            // The domain's DNS profile, locked after the domain, as every writer locks them
            $current_profile = $this->lockDnsProfile((int) $row->dns_id);

            if ((int) $row->account_id !== (int) $before->account_id
                || (int) $row->registrar_id !== (int) $before->registrar_id
                || (int) $row->api_registrar_id !== (int) $before->api_registrar_id
                || !hash_equals($this->credentialHash($before), $this->credentialHash($row))
                || (string) $row->domain !== (string) $before->domain
                || (string) $row->expiry_date !== (string) $before->expiry_date
                || (int) $row->dns_id !== (int) $before->dns_id
                || (int) $row->privacy !== (int) $before->privacy
                || (int) $row->autorenew !== (int) $before->autorenew
                || ($require_live && in_array((string) $row->active, array('0', '10'), true))
                || ($before_dns !== false && !$this->sameServers($before_dns, $current_profile ? $current_profile['servers'] : null))) {

                $pdo->rollback();
                $changed = _('The domain changed while it was being refreshed. Please try again.');
                return array(false, array($changed), array('reasons' => array($changed), 'changes' => array(), 'notes' => array(), 'failure' => 'stale'));

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

                // A quiet status write that recorded a change of the registry status codes still counts as a change
                return array(true, array_merge(array($no_changes), $notes_lines),
                    array('reasons' => array($no_changes), 'changes' => array(), 'notes' => $notes_lines,
                        'changed' => $status_write && !empty($status_history)));

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

            // The task adds no note, so its UPDATE leaves the notes alone
            $stmt = $pdo->prepare("
                UPDATE domains
                SET expiry_date = :expiry_date,
                    dns_id = :dns_id,
                    privacy = :privacy,
                    autorenew = :autorenew," . ($add_note ? "
                    notes = :notes," : "") . "
                    update_time = :update_time" . $sql_fees . ($status_write ? ",
                    transfer_lock = :transfer_lock,
                    epp_status = :epp_status,
                    epp_status_time = :epp_status_time" : "") . "
                WHERE id = :domain_id");
            $stmt->bindValue('expiry_date', $new['expiry_date'], \PDO::PARAM_STR);
            $stmt->bindValue('dns_id', $new['dns_id'], \PDO::PARAM_INT);
            $stmt->bindValue('privacy', $new['privacy'], \PDO::PARAM_INT);
            $stmt->bindValue('autorenew', $new['autorenew'], \PDO::PARAM_INT);
            if ($add_note) $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
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
                array('reasons' => array(), 'changes' => $changes, 'notes' => array_merge($notes_lines, $cost_lines),
                    'changed' => true));

        } catch (\Throwable $e) {

            if ($pdo->inTransaction()) $pdo->rollback();

            $log_message = 'Unable to refresh domain';
            $log_extra = array('Domain' => $before->domain, 'Error' => $e);
            $this->log->critical($log_message, $log_extra);

            // Another request changed the same rows at the same moment (see domainmod_restore_is_conflict())
            $conflict = function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e);
            $not_updated = $conflict ? domainmod_restore_conflict_refresh_message() : _("The domain couldn't be updated. Please try again.");
            return array(false, array($not_updated), array('reasons' => array($not_updated), 'changes' => array(), 'notes' => array(),
                'failure' => $conflict ? 'conflict' : 'save_failed'));

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

    // The DNS profile's servers (lower case, as lockDnsProfile() reads them), or null when there's no such profile
    protected function dnsServers($dns_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10
            FROM dns
            WHERE id = :dns_id");
        $stmt->bindValue('dns_id', $dns_id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_NUM);
        $stmt->closeCursor();
        if (!$row) return null;

        return array_values(array_map('strtolower', array_filter($row)));
    }

    // Whether two server lists hold the same servers, in any order (null is no profile at all)
    protected function sameServers($a, $b)
    {
        if ($a === null || $b === null) return $a === $b;
        return array_count_values($a) == array_count_values($b);
    }

    /*
     * The Refresh Domains task (cron.php, and Run Now on the Task Scheduler page with $run_now): looks up the due live
     * domains, least recently reached first, up to REFRESH_BATCH lookups within the run's time budget (RUN_NOW_BUDGET for
     * Run Now), and saves each one's status row. Nothing happens before the database update that adds the task. Returns
     * the run's counts: checked (domains looked up), changed (those whose refresh recorded a change), and the domains
     * skipped for a busy server (busy) or put off for lack of time (deferred).
     */
    public function refreshDue($run_now = false)
    {
        $summary = array('checked' => 0, 'changed' => 0, 'failed' => 0, 'busy' => 0, 'deferred' => 0);
        $pdo = $this->deeb->cnxx;
        if (!self::scheduleAvailable($pdo)) return $summary;

        $started = microtime(true);
        $deadline = $started + $this->budget($started, $run_now);
        $this->status_available = DomainStatus::isAvailable($pdo);

        return $this->refreshEach(self::candidates($pdo, self::REFRESH_CANDIDATES), $deadline, $summary);
    }

    // refreshDue()'s run over the candidates read at its start, until the batch cap or the deadline
    protected function refreshEach(array $domain_ids, $deadline, array $summary)
    {
        $run = $this->newRun();

        foreach ($domain_ids as $domain_id) {

            if ($summary['checked'] >= self::REFRESH_BATCH || $deadline - microtime(true) < self::MIN_LOOKUP_TIME) break;

            // The candidates were read at the start of the run, so the domain and its status row are read again: one a
            // person refreshed, edited, deleted, or marked Expired or Sold meanwhile isn't looked up
            $row = $this->getDomainRow($domain_id, false);
            $status = $this->readStatus($domain_id);
            if (!$row || in_array((string) $row->active, array('0', '10'), true)
                || !DomainRefreshSchedule::isDue($status, $row->expiry_date, time())) continue;

            $run->public_next = $status !== null && (int) $status['public_next'] === 1;
            $outcome = $this->refreshDomain($row, 0, min($deadline, microtime(true) + self::DEADLINE), $run);
            $this->saveOutcome($row, $outcome, $status, true);

            // Every domain a registrar or registry was asked about counts toward the cap, whatever came of it
            if (!empty($outcome['looked_up'])) $summary['checked']++;
            if ($outcome['changed']) $summary['changed']++;
            if ($outcome['kind'] === DomainRefreshSchedule::FAILURE) $summary['failed']++;
            if ($outcome['kind'] === DomainRefreshSchedule::BUSY) $summary['busy']++;
            if ($outcome['kind'] === DomainRefreshSchedule::DEFERRED
                || $outcome['kind'] === DomainRefreshSchedule::DEFERRED_AFTER_API) $summary['deferred']++;

        }

        return $summary;
    }

    // A new run's state, for refreshDomain()
    protected function newRun()
    {
        $run = new \stdClass();
        $run->busy = array();            // server keys (rdap:host or whois:server) skipped for the rest of the run
        $run->whois_counts = array();    // WHOIS server => queries made in the run
        $run->failed_accounts = array(); // registrar account id => true, once its API has failed in the run
        $run->api_last = array();        // registrar API name => when it was last called
        $run->public_next = false;       // whether the current domain goes straight to RDAP and WHOIS
        $run->server = '';               // the current domain's server key, found by preflight()
        return $run;
    }

    /*
     * The run's time for lookups: REFRESH_BUDGET (RUN_NOW_BUDGET for Run Now), cut down when max_execution_time is set
     * the way the Domain Queue's RDAP import does it (a quarter of what's left, at most a minute, is kept for the rest of
     * the run), and on another task's turn in a contested run, that task's needs too (see Scheduler::reserveFor())
     */
    public function budget($started, $run_now = false)
    {
        $budget = $run_now ? self::RUN_NOW_BUDGET : self::REFRESH_BUDGET;
        $max_execution_time = (int) ini_get('max_execution_time');

        if ($max_execution_time > 0) {

            $remaining = $max_execution_time - ($started - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? $started));
            $budget = min($budget, max(0, $remaining - min(60, $remaining / 4)));
            if (!$run_now) $budget = min($budget, max(0, $remaining - Scheduler::reserveFor('domain-refresh')));

        }

        return $budget;
    }

    /*
     * The due live domains (not Expired or Sold), as ids: no status row yet, or not held after a busy server and either
     * past next_due or near expiry (within NEAR_EXPIRY_DAYS, or past) with a last success more than a day ago and no
     * failures in a row (DomainRefreshSchedule::isDue()'s rules). Least recently reached first, never reached first.
     * Only domains whose registrar account and registrar exist, as the lookup reads them (getDomainRow()), so domains a
     * partial restore left without one can't fill every batch.
     */
    public static function candidates(\PDO $pdo, $limit)
    {
        $now = time();
        $stmt = $pdo->prepare("
            SELECT d.id
            FROM domains AS d
            JOIN registrar_accounts AS ra ON ra.id = d.account_id
            JOIN registrars AS r ON r.id = ra.registrar_id
            LEFT JOIN domain_refresh_status AS s ON s.domain_id = d.id
            WHERE d.active NOT IN ('0', '10')
              AND (s.domain_id IS NULL
                OR (s.hold_until <= :now_hold
                    AND (s.next_due <= :now_due
                      OR (d.expiry_date <= :near_expiry
                          AND s.last_success <= :day_ago
                          AND s.failures = 0))))
            ORDER BY IFNULL(s.last_turn, '1970-01-01 00:00:00'), d.id
            LIMIT " . (int) $limit);
        $stmt->bindValue('now_hold', DomainRefreshSchedule::stamp($now), \PDO::PARAM_STR);
        $stmt->bindValue('now_due', DomainRefreshSchedule::stamp($now), \PDO::PARAM_STR);
        $stmt->bindValue('near_expiry', DomainRefreshSchedule::nearExpiryDate($now), \PDO::PARAM_STR);
        $stmt->bindValue('day_ago', DomainRefreshSchedule::stamp($now - DomainRefreshSchedule::DAY), \PDO::PARAM_STR);
        $stmt->execute();
        $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        $stmt->closeCursor();

        return $ids;
    }

    // The domain's status row as column => value, or null when it has none
    public function readStatus($domain_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT " . implode(', ', self::STATUS_COLUMNS) . "
            FROM domain_refresh_status
            WHERE domain_id = :domain_id");
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $row ?: null;
    }

    /*
     * Saves the status row an outcome calls for (see DomainRefreshSchedule::row()), after the refresh and outside its
     * transaction. $status is the row read before the lookup (null when there was none, or when the caller doesn't
     * check it, as a clicked Refresh's success needn't). A failure to save it is logged and doesn't stop the run.
     */
    protected function saveOutcome($row, array $outcome, $status, $require_live)
    {
        if (!self::scheduleAvailable($this->deeb->cnxx)) return;

        $columns = DomainRefreshSchedule::row($outcome['kind'], $status, $outcome, $outcome['expiry_date'], time());
        if ($columns === null) return;

        try {
            $this->writeStatus((int) $row->id, (string) $row->domain, $columns, $outcome['kind'] === DomainRefreshSchedule::SUCCESS,
                $status === null ? -1 : (int) $status['revision'], (int) $outcome['started'], $require_live);
        } catch (\Throwable $e) {
            if (function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e)) $this->had_conflict = true;
            try {
                $this->log->warning('Unable to save a domain\'s refresh schedule', array('Domain' => $row->domain, 'Error' => get_class($e)));
            } catch (\Throwable $log_error) {
            }
        }
    }

    /*
     * Writes a domain's status row in one statement, selected from domains, so a domain deleted meanwhile never gets one
     * and one renamed meanwhile ($domain is the name that was looked up) isn't changed, and with $require_live (the task)
     * one marked Expired or Sold meanwhile isn't either. A success ($success, which needs no revision) writes unless the
     * row holds a success whose lookup started later than this one ($started), since it's the newest proof the details
     * are current. Anything else writes only while the row still has the revision read before the lookup ($revision, -1
     * when there was no row), so a failure never replaces a newer success. Every write raises the revision by one.
     */
    protected function writeStatus($domain_id, $domain, array $columns, $success, $revision, $started, $require_live)
    {
        $insert = array_merge(DomainRefreshSchedule::COLUMNS, $columns);
        /*
         * The same condition guards the update of an existing row, read from the row as it's locked, so a write that
         * waited for another refresh's never overwrites it (whatever the isolation level). Assignments run in order, and
         * the columns the condition reads are only ever set to values that keep it true (last_success to this start) or
         * set last (revision).
         */
        $guard = $success ? "last_success <= :g_started" : "revision = :g_revision";
        $updates = array();
        foreach (array_keys($columns) as $column) {
            $updates[] = $column . ' = IF(' . str_replace(':g_', ':g' . count($updates) . '_', $guard) . ', :u_' . $column . ', ' . $column . ')';
        }
        $updates[] = 'revision = IF(' . str_replace(':g_', ':g' . count($updates) . '_', $guard) . ', revision + 1, revision)';
        $guard_count = count($updates);

        $stmt = $this->deeb->cnxx->prepare("
            INSERT INTO domain_refresh_status
            (domain_id, " . implode(', ', array_keys($insert)) . ", revision)
            SELECT d.id, :i_" . implode(', :i_', array_keys($insert)) . ", 1
            FROM domains AS d
            WHERE d.id = :domain_id
              AND d.domain = :domain" . ($require_live ? "
              AND d.active NOT IN ('0', '10')" : "") . "
              AND NOT EXISTS (
                SELECT 1
                FROM domain_refresh_status AS s
                WHERE s.domain_id = d.id
                  AND " . ($success ? "s.last_success > :started" : "s.revision <> :revision") . ")
            ON DUPLICATE KEY UPDATE " . implode(', ', $updates));
        foreach ($insert as $column => $value) $stmt->bindValue('i_' . $column, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        foreach ($columns as $column => $value) $stmt->bindValue('u_' . $column, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $started_stamp = DomainRefreshSchedule::stamp($started > 0 ? $started : time());
        if ($success) {
            $stmt->bindValue('started', $started_stamp, \PDO::PARAM_STR);
        } else {
            $stmt->bindValue('revision', $revision, \PDO::PARAM_INT);
        }
        for ($i = 0; $i < $guard_count; $i++) {
            if ($success) {
                $stmt->bindValue('g' . $i . '_started', $started_stamp, \PDO::PARAM_STR);
            } else {
                $stmt->bindValue('g' . $i . '_revision', $revision, \PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /*
     * Whether a write in the task's run met another request's (errors 1020 and 1213): the domain's save was rolled back
     * and it's refreshed again on a later run, so Run Now can say so
     */
    public function hadConflict()
    {
        return $this->had_conflict;
    }

    /*
     * Whether the database has the task's table (version AVAILABLE_FROM or later, and domain_refresh_status with every
     * column this version creates), worked out once per request and connection, as DomainStatus::isAvailable() is
     */
    public static function scheduleAvailable($pdo = null)
    {
        try {
            $pdo = $pdo ?? Database::getInstance()->cnxx;
        } catch (\Throwable $e) {
            return false;
        }
        if (!is_object($pdo)) return false;

        $key = spl_object_id($pdo);
        if (isset(self::$schedule_available[$key])) return self::$schedule_available[$key];

        try {

            $version = (string) $pdo->query("
                SELECT db_version
                FROM settings")->fetchColumn();
            $available = $version !== '' && version_compare($version, self::AVAILABLE_FROM, '>=');

            if ($available) {
                $columns = (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM `INFORMATION_SCHEMA`.`COLUMNS`
                    WHERE `TABLE_SCHEMA` = DATABASE()
                      AND `TABLE_NAME` = 'domain_refresh_status'
                      AND `COLUMN_NAME` IN ('" . implode("', '", self::STATUS_COLUMNS) . "')")->fetchColumn();
                $available = $columns === count(self::STATUS_COLUMNS);
            }

        } catch (\Throwable $e) {

            $available = false;

        }

        return self::$schedule_available[$key] = $available;
    }

    // Forgets what scheduleAvailable() worked out (after the database update or a restore, and for tests)
    public static function resetScheduleAvailable()
    {
        self::$schedule_available = array();
    }

    // The task's table, as install/go.php and the database update create it (and the restore's clean-up, when missing)
    public static function tableSql()
    {
        return "
        CREATE TABLE IF NOT EXISTS `domain_refresh_status` (
            `domain_id` INT(10) UNSIGNED NOT NULL,
            `last_attempt` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `last_turn` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `last_success` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `next_due` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `hold_until` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `failures` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
            `last_source` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `last_result` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `public_next` TINYINT(1) NOT NULL DEFAULT '0',
            `revision` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            PRIMARY KEY  (`domain_id`),
            KEY `next_due` (`next_due`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
    }

    /*
     * Whether a scheduled run would have work: a due domain (the candidate query, with LIMIT 1), for the turns the tasks
     * take in a cron run with a time limit (see Scheduler::turn()). False, without a query, before the database update.
     */
    public static function reserveNeeded(\PDO $pdo)
    {
        if (!self::scheduleAvailable($pdo)) return false;
        return (bool) self::candidates($pdo, 1);
    }

    /*
     * Deletes the status rows of domains being deleted, inside the caller's transaction (built like
     * SslQueue::deleteStatus()). Before the database update there's no table, so nothing is done.
     */
    public function deleteStatus($domain_ids)
    {
        $domain_ids = array_values(array_map('intval', $domain_ids));
        if (!$domain_ids || !self::scheduleAvailable($this->deeb->cnxx)) return;

        $stmt = $this->deeb->cnxx->prepare("
            DELETE FROM domain_refresh_status
            WHERE domain_id IN (" . implode(', ', array_fill(0, count($domain_ids), '?')) . ")");
        $stmt->execute($domain_ids);
    }

    // Deletes status rows whose domain is gone (the daily cleanup), in case anything ever removes a domain without them
    public static function deleteOrphanStatus(\PDO $pdo)
    {
        if (!self::scheduleAvailable($pdo)) return;

        $pdo->exec("
            DELETE s
            FROM domain_refresh_status AS s
            LEFT JOIN domains AS d ON d.id = s.domain_id
            WHERE d.id IS NULL");
    }

    /*
     * The status line at the top of the domain edit page's Change History section: when the task last checked the
     * domain and when it checks it next, why the last check failed or was put off, or that automatic checks are off
     * (with a link to the Task Scheduler for administrators). '' before the database update, and for a domain that
     * isn't live (Expired or Sold) or doesn't exist. Every value is escaped, and the dates are in the viewer's time zone.
     */
    public static function statusLine(\PDO $pdo, $domain_id)
    {
        if (!self::scheduleAvailable($pdo)) return '';

        // A database that can't be read as expected (such as a restore that stopped before scheduler was recreated)
        // leaves the line out rather than breaking the edit page
        try {
            return self::statusLineFor($pdo, $domain_id);
        } catch (\Throwable $e) {
            return '';
        }
    }

    // statusLine()'s work, once the task's table is known to be there
    protected static function statusLineFor(\PDO $pdo, $domain_id)
    {

        $stmt = $pdo->prepare("
            SELECT d.expiry_date, d.active, s.last_success, s.next_due, s.hold_until, s.failures, s.last_source,
                s.last_result, s.domain_id AS has_status
            FROM domains AS d
            LEFT JOIN domain_refresh_status AS s ON s.domain_id = d.id
            WHERE d.id = :domain_id");
        $stmt->bindValue('domain_id', (int) $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!$row || in_array((string) $row['active'], array('0', '10'), true)) return '';

        $esc = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        };

        $active = $pdo->query("
            SELECT active
            FROM scheduler
            WHERE slug = 'domain-refresh'
            LIMIT 1")->fetchColumn();

        if ((string) $active !== '1') {
            $line = $esc(_('Automatic checks are off.'));
            if ((int) ($_SESSION['s_is_admin'] ?? 0) === 1) {
                // TRANSLATORS: %1$s and %2$s are the start and end of the link to the Task Scheduler page.
                $line .= ' ' . sprintf($esc(_('An administrator can turn them on in the %1$sTask Scheduler%2$s.')),
                    '<a href="' . $esc(WEB_ROOT . '/admin/scheduler/') . '">', '</a>');
            }
            return $line;
        }

        if ($row['has_status'] === null) return $esc(_('Not checked automatically yet.'));

        $time = new Time();
        $layout = new Layout();
        $date = function ($timestamp) use ($time, $layout) {
            return $layout->ltr($time->toUserTimezone(DomainRefreshSchedule::stamp($timestamp), 'Y-m-d'));
        };
        // A domain that's due already is checked by the next run
        $next = max(time(), (int) DomainRefreshSchedule::nextCheck($row, $row['expiry_date']));
        $result = (string) $row['last_result'];

        if ($result === 'ok' || $result === 'changed') {
            $sources = array('registrar' => _("Last checked %s using your registrar's API."),
                'rdap' => _('Last checked %s using RDAP.'), 'whois' => _('Last checked %s using WHOIS.'));
            $checked = $sources[$row['last_source']] ?? _('Last checked %s.');
            return sprintf($esc($checked), $date(DomainRefreshSchedule::time($row['last_success']))) . ' '
                . sprintf($esc(_('Next check around %s.')), $date($next));
        }

        if ($result === DomainRefreshSchedule::BUSY || $result === 'rate_limited') {
            $first = _('The last check was put off, because the RDAP or WHOIS server was busy.');
        } elseif ($result === DomainRefreshSchedule::DEFERRED || $result === DomainRefreshSchedule::DEFERRED_AFTER_API) {
            $first = _('The last check was put off, because the run ran out of time.');
        } else {
            $first = _('The last check failed.') . ' ' . self::resultText($result);
        }

        return $esc($first) . ' ' . sprintf($esc(_('Next try around %s.')), $date($next));
    }

    // A failed check's reason for the status line, from its stored last_result code (never text from a response)
    public static function resultText($result)
    {
        $texts = array(
            'not_found' => _("The domain wasn't found."),
            'mismatch' => _('The RDAP server answered for a different domain.'),
            'rdap_failed' => _('The RDAP lookup failed.'),
            'whois_failed' => _('The WHOIS lookup failed.'),
            'whois_unreachable' => _("The WHOIS server couldn't be reached."),
            'whois_server_unknown' => _("The WHOIS server for this domain's TLD couldn't be found."),
            'no_source' => _("Neither RDAP nor WHOIS is available for this domain's TLD."),
            'invalid_name' => _("This domain name can't be looked up."),
            'save_failed' => _("The domain's new details couldn't be saved."));

        return $texts[$result] ?? _('The lookup failed.');
    }

} //@formatter:on
