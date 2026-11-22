<?php
/**
 * /classes/DomainMOD/DomainQueue.php
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

class DomainQueue
{
    public $api;
    public $deeb;
    public $domain;
    public $log;
    public $maint;
    public $queryB;
    public $schedule;
    public $system;
    public $time;

    // Domains added with RDAP: rows attempted per run, rows read per run (so rows skipped for a busy server don't use
    // up the batch), and the seconds a run may spend on them
    const RDAP_BATCH = 100;
    const RDAP_CANDIDATES = 1000;
    const RDAP_BUDGET = 240;

    // Domains whose TLD has no RDAP server are looked up with WHOIS instead: queries to one WHOIS server per run (so a
    // large paste can't get the server's IP blocked), and the consecutive connection failures before a row fails
    const WHOIS_PER_SERVER = 20;
    const WHOIS_MAX_FAILURES = 5;

    // Seconds of list paging that Dynadot lists leave free at the end of PHP's time limit while Dynadot domains are
    // waiting for their details, so long lists can't use up the time those details calls need
    const DYNADOT_LIST_RESERVE = 10;

    // Whether domain_queue_list has its last_attempt column (added in 4.27.0), worked out once per request
    protected static $list_last_attempt = null;
    // Whether an import in this run met another request's write (see hadConflict())
    protected static $had_conflict = false;
    // Whether domains still has its pre-4.27.0 hosting_id column (Hosting::legacyDomainColumn()), asked when the queue
    // is made
    protected $legacy_domain_column = false;

    public function __construct()
    {
        $this->api = new Api();
        $this->deeb = Database::getInstance();
        $this->domain = new Domain();
        $this->log = new Log('class.domainqueue');
        $this->maint = new Maintenance();
        $this->queryB = new QueryBuild();
        $this->schedule = new Scheduler();
        $this->system = new System();
        $this->time = new Time();
        // Asked once, here, before any transaction, so its plain read never opens a transaction's snapshot early
        $this->legacy_domain_column = Hosting::legacyDomainColumn($this->deeb->cnxx);
    }

    /*
     * Whether an import in this run met another request's write and was rolled back (the row stays in the queue, as for
     * any failed attempt). The rest of the run goes on, so this is reported after it rather than ending it.
     */
    public function hadConflict()
    {
        return self::$had_conflict;
    }

    public function processQueueList()
    {
        // lists still flagged by a run that was killed are waiting again (see releaseListProcessing())
        $this->releaseListProcessing();

        // process domain lists in the queue
        $result = $this->getQueueList();

        $this->markProcessingList();

        if (!$result) {

            $log_message = 'No Domain Queue Lists to process';
            $this->log->info($log_message);

        }  else {

            $log_message = '[START] Processing Domain Queue Lists';
            $this->log->notice($log_message);

            $dynadot_details_waiting = null;

            foreach ($result as $row) {

                if ($row->api_registrar_name == 'Dynadot') {

                    // counted again until some are waiting, since an earlier Dynadot list may have just queued some
                    if (!$dynadot_details_waiting) $dynadot_details_waiting = $this->hasDynadotDetailsWaiting();
                    Dynadot::$list_reserve = $dynadot_details_waiting ? self::DYNADOT_LIST_RESERVE : 0;

                }

                $this->processQueueListRow($row);

            }

            Dynadot::$list_reserve = 0;

            $log_message = '[END] Processing Domain Queue Lists';
            $this->log->notice($log_message);

        }

        $this->copyToHistoryList();

        return _('Domain List Queue Processed') . '<BR>';
    }

    /*
     * Gets one Domain Queue list's domains and queues them, in one transaction, so a failure part-way leaves nothing
     * behind: a failed list call (the registrar returns '' for the count) or an error puts the list back to be retried
     * on the next run, and the other lists are still processed
     */
    private function processQueueListRow($row)
    {
        $pdo = $this->deeb->cnxx;

        // Dynadot reports whether this list's first request was sent, and whether it fell back to the legacy API
        Dynadot::$last_list_sent = false;
        Dynadot::$last_list_fell_back = null;

        try {

            $pdo->beginTransaction();

            $result = $this->getRegistrarDomainList($row);

            if ($result === null) {

                $pdo->commit();
                $log_message = 'Invalid Domain Registrar for a Domain Queue list';
                $log_extra = array('List ID' => $row->id, 'Registrar' => $row->api_registrar_name);
                $this->log->error($log_message, $log_extra);
                $this->markNotProcessingList($row->id);
                return;

            }

            list($domain_count, $domain_list) = $result;

            // make sure the domain list was successfully retrieved ('' means the call failed; 0 is an empty account)
            if ($domain_count !== '' && is_array($domain_list)) {

                // update the domain count
                $this->updateDomainCount($row->id, $domain_count);

                foreach ($domain_list as $domain) {

                    $this->importToDomainQueue($row->api_registrar_id, $domain, $row->owner_id, $row->registrar_id, $row->account_id, $row->created_by);

                }

                $this->markFinishedList($row->id);
                $pdo->commit();

            } else {

                $pdo->commit();
                $this->markNotProcessingList($row->id);

            }

        } catch (\Throwable $e) {

            // A write that met another request's (errors 1020 and 1213) is noted for hadConflict(), so Run Now can say so
            if (function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e)) self::$had_conflict = true;

            // Recovery must never stop the other lists: roll back only if the transaction is still open (the server
            // may have rolled it back already, e.g. after a deadlock), log after the rollback so the entry is kept,
            // and put the list back to be retried
            try {
                if ($pdo->inTransaction()) $pdo->rollBack();
            } catch (\Throwable $rollback_error) {
            }

            try {
                $log_message = 'Unable to process a Domain Queue list';
                $log_extra = array('List ID' => $row->id, 'Registrar' => $row->api_registrar_name, 'Error' => $e->getMessage());
                $this->log->error($log_message, $log_extra);
            } catch (\Throwable $log_error) {
            }

            try {
                $this->markNotProcessingList($row->id);
            } catch (\Throwable $reset_error) {
            }

        } finally {

            $this->finishListAttempt($row);

        }
    }

    /*
     * After a list's transaction has ended (committed or rolled back), so neither entry can be lost with a rollback:
     * logs a Dynadot list that went through the legacy API because Dynadot refused the API Secret, and stamps the list's
     * last attempt, so a list that keeps failing waits behind lists not tried yet. A Dynadot list is only stamped when
     * its first request was sent. Neither step can stop the other lists.
     */
    protected function finishListAttempt($row)
    {
        $is_dynadot = $row->api_registrar_name == 'Dynadot';

        if ($is_dynadot && Dynadot::$last_list_fell_back !== null && Dynadot::$last_list_sent) {

            try {
                $log_extra = array('List ID' => $row->id, 'HTTP Status' => Dynadot::$last_list_fell_back);
                $this->log->addEntry('warning', Dynadot::LIST_FALLBACK_MESSAGE, $log_extra);
            } catch (\Throwable $log_error) {
            }

        }

        if ($is_dynadot && !Dynadot::$last_list_sent) return;

        try {

            if (!$this->hasListLastAttempt()) return;

            $stmt = $this->deeb->cnxx->prepare("
                UPDATE domain_queue_list
                SET last_attempt = :last_attempt
                WHERE id = :id");
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('last_attempt', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->bindValue('id', $row->id, \PDO::PARAM_INT);
            $stmt->execute();

        } catch (\Throwable $e) {

            try {
                $log_message = "Unable to save a Domain Queue list's last attempt";
                $log_extra = array('List ID' => $row->id, 'Error' => $e->getMessage());
                $this->log->error($log_message, $log_extra);
            } catch (\Throwable $log_error) {
            }

        }
    }

    // Whether Dynadot domains are already waiting in the Domain Queue for their details
    protected function hasDynadotDetailsWaiting()
    {
        try {

            return (int) $this->deeb->cnxx->query("
                SELECT COUNT(*)
                FROM domain_queue AS dq, api_registrars AS ar
                WHERE dq.api_registrar_id = ar.id
                  AND ar.name = 'Dynadot'
                  AND dq.ready_to_import = '0'
                  AND dq.finished = '0'
                  AND dq.copied_to_history = '0'
                  AND dq.already_in_domains = '0'
                  AND dq.already_in_queue = '0'")->fetchColumn() > 0;

        } catch (\Throwable $e) {

            return false;

        }
    }

    // Whether domain_queue_list has its last_attempt column, which a database not yet upgraded to 4.27.0 lacks
    protected function hasListLastAttempt()
    {
        if (self::$list_last_attempt !== null) return self::$list_last_attempt;

        try {

            $columns = (int) $this->deeb->cnxx->query("
                SELECT COUNT(*)
                FROM `INFORMATION_SCHEMA`.`COLUMNS`
                WHERE `TABLE_SCHEMA` = DATABASE()
                  AND `TABLE_NAME` = 'domain_queue_list'
                  AND `COLUMN_NAME` = 'last_attempt'")->fetchColumn();
            return self::$list_last_attempt = $columns === 1;

        } catch (\Throwable $e) {

            return false;

        }
    }

    // A list's domains from its registrar: array(count, domains), array('', '') if the call failed, or null for an
    // unknown registrar
    protected function getRegistrarDomainList($row)
    {
        if ($row->api_registrar_name == 'Above.com') {

            $registrar = new AboveCom();
            $api_key = $this->api->getKey($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $row->account_id);

        } elseif ($row->api_registrar_name == 'Cloudflare') {

            $registrar = new Cloudflare();
            $api_key = $this->api->getKey($row->account_id);
            $account_id = $this->api->getAccountId($row->account_id);
            list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_id, $api_key);

        } elseif ($row->api_registrar_name == 'DNSimple') {

            $registrar = new DnSimple();
            $api_key = $this->api->getKey($row->account_id);
            $account_id = $this->api->getAccountId($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $account_id);

        } elseif ($row->api_registrar_name == 'DreamHost') {

            $registrar = new DreamHost();
            $api_key = $this->api->getKey($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $row->account_id);

        } elseif ($row->api_registrar_name == 'Dynadot') {

            $registrar = new Dynadot();
            list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $api_secret);

        } elseif ($row->api_registrar_name == 'eNom') {

            $registrar = new Enom();
            list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_password);

        } elseif ($row->api_registrar_name == 'Fabulous') {

            $registrar = new Fabulous();
            list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_password);

        } elseif ($row->api_registrar_name == 'Freenom') {

            $registrar = new Freenom();
            list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_password);

        } elseif ($row->api_registrar_name == 'Gandi') {

            $registrar = new Gandi();
            $api_key = $this->api->getKey($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key);

        } elseif ($row->api_registrar_name == 'GoDaddy') {

            $registrar = new GoDaddy();
            list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $api_secret);

        } elseif ($row->api_registrar_name == 'Internet.bs') {

            $registrar = new InternetBs();
            list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $api_secret);

        } elseif ($row->api_registrar_name == 'Name.com') {

            $registrar = new NameCom();
            list($account_username, $api_key) = $this->api->getUserKey($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_key);

        } elseif ($row->api_registrar_name == 'NameBright') {

            $registrar = new NameBright();
            list($account_username, $api_app_name, $api_secret) = $this->api->getUserAppSecret($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_app_name, $api_secret);

        } elseif ($row->api_registrar_name == 'Namecheap') {

            $registrar = new Namecheap();
            list($account_username, $api_key, $api_ip_address) = $this->api->getUserKeyIp($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_key, $api_ip_address);

        } elseif ($row->api_registrar_name == 'NameSilo') {

            $registrar = new NameSilo();
            $api_key = $this->api->getKey($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key);

        } elseif ($row->api_registrar_name == 'OpenSRS') {

            $registrar = new OpenSrs();
            list($account_username, $api_key) = $this->api->getUserKey($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_key);

        } elseif ($row->api_registrar_name == 'OVH') {

            $registrar = new Ovh();
            list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
            $account_id = $this->api->getAccountId($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $api_secret, $account_id);

        } elseif ($row->api_registrar_name == 'Porkbun') {

            $registrar = new Porkbun();
            list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $api_secret);

        } elseif ($row->api_registrar_name == 'SiteHost') {

            $registrar = new SiteHost();
            $api_key = $this->api->getKey($row->account_id);
            $account_id = $this->api->getAccountId($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $account_id);

        } elseif ($row->api_registrar_name == 'Hostinger') {

            $registrar = new Hostinger();
            $api_key = $this->api->getKey($row->account_id);
            list($domain_count, $domain_list) = $registrar->getDomainList($api_key);

        } else {

            return null;

        }

        return array($domain_count, $domain_list);
    }

    public function processQueueDomain()
    {
        // domains still flagged by a run that was killed are waiting again (see releaseDomainProcessing())
        $this->releaseDomainProcessing();

        // process domains in the queue
        $result = $this->getQueueDomain();

        $this->markProcessingDomain();

        if (!$result) {

            $log_message = 'No domains in the Domain Queue to process';
            $this->log->info($log_message);

        } else {

            $log_message = '[START] Processing domains in the Domain Queue';
            $this->log->notice($log_message);

            foreach ($result as $row) {

                $this->processQueueDomainRow($row);

            }

            $log_message = '[END] Processing domains in the Domain Queue';
            $this->log->notice($log_message);

        }

        // domains added with RDAP go after the API registrars' domains, so API imports never wait behind them
        $this->processRdapQueue();

        $this->copyToHistoryDomain();

        return _('Domain Queue Processed') . '<BR>';
    }

    /*
     * Gets one queued domain's details from its registrar and imports it. Marking it ready and importing it happen in
     * one transaction, as for RDAP domains, so a failure (or a run that dies) part-way leaves the domain waiting to be
     * looked up again, never ready but not imported, or imported without its custom field row. A domain whose registrar
     * has no API class here, or one that fails, is put back to be retried on the next run, and the other domains are
     * still processed.
     */
    private function processQueueDomainRow($row)
    {
        $pdo = $this->deeb->cnxx;

        try {

            // The row's last attempt is stamped even if the lookup throws, unless the registrar has no API class here
            // or a Dynadot call's time budget stopped it before anything was sent (it then keeps its place)
            $registrar_info = null;
            Dynadot::$last_call_skipped = false;

            try {

                $registrar_info = $this->fetchRegistrarInfo($row->api_registrar_name, $row->account_id, $row->domain);

            } finally {

                if ($registrar_info !== false && !($row->api_registrar_name == 'Dynadot' && Dynadot::$last_call_skipped)) {

                    $this->stampDomainAttempt($row->id);

                }

            }

            if ($registrar_info === false) {

                $log_message = 'Invalid Domain Registrar for a domain in the Domain Queue';
                $log_extra = array('Queue Domain ID' => $row->id, 'Registrar' => $row->api_registrar_name);
                $this->log->error($log_message, $log_extra);
                $this->markNotProcessingDomain($row->id);
                return;

            }

            list($domain_status, $expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar_info;

            // make sure the domain details were successfully retrieved
            if ($expiration_date != '' && $expiration_date != '0000-00-00' && $expiration_date != '1970-01-01'
                && $dns_servers != '' && $privacy_status != '' && $autorenew_status != '') {

                // REPEATABLE READ, so the import's locking reads' next-key locks hold even on a server whose default is READ
                // COMMITTED
                $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                $pdo->beginTransaction();

                // The fee-change lock, held until this method returns (after the commit or rollback below), since
                // importToMainDb()'s action ends before this transaction commits
                $queue_fee_lock = (new ChangeHistory($pdo))->start(ChangeHistory::SOURCE_QUEUE, (int) $row->created_by);
                $queue_fee_lock->holdNamedLock('domainmod_fee_changes_' . substr(md5(DB_NAME), 0, 12));

                $created_by = $row->created_by;
                list($ready_to_import, $expiration_date, $dns_id, $ip_id, $cat_id, $privacy_status, $autorenew_status)
                    = $this->updateDomain($row->id, $row->domain, $expiration_date, $dns_servers, $privacy_status, $autorenew_status, $created_by);

                // only process the domain if $ready_to_import = 1 (ie. all the information is valid)
                if ($ready_to_import == '1') {

                    // the domain may have reached the main domain table since it was queued (added by hand, or
                    // a queued punycode name that's the same domain as one already there), so check again
                    $existing_domain_id = $this->getDomainIdByName($row->domain);

                    if ($existing_domain_id) {

                        $this->markAlreadyInDomains($row->id, $existing_domain_id);

                    } else {

                        $new_domain_id = $this->importToMainDb($row->id);

                        // importToMainDb() returns a message instead of an id when it can't import the row
                        if (!ctype_digit((string) $new_domain_id)) {

                            throw new \RuntimeException('The domain could not be imported');

                        }

                        // updates queue with the new domain id
                        $this->updateNewDomainId($row->id, $new_domain_id);

                        // markFinishedDomain confirms that the domain was added to the main domain database before marking it as finished
                        $this->markFinishedDomain($row->id, $row->domain, $expiration_date, $dns_id, $ip_id, $cat_id, $privacy_status, $autorenew_status);

                    }

                    $pdo->commit();

                } else {

                    // the details found so far are kept, and the domain is looked up again on the next run
                    $pdo->commit();
                    $this->markNotProcessingDomain($row->id);

                }

            } else {

                if ($domain_status == Format::toLower(_('Invalid'))) {

                    $this->markInvalidDomain($row->id);

                }

                $this->markNotProcessingDomain($row->id);

            }

        } catch (\Throwable $e) {

            // A write that met another request's (errors 1020 and 1213) is noted for hadConflict(), so Run Now can say so
            if (function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e)) self::$had_conflict = true;

            // as for a list (see processQueueListRow()), recovery must never stop the other domains
            try {
                if ($pdo->inTransaction()) $pdo->rollBack();
            } catch (\Throwable $rollback_error) {
            }

            try {
                $log_message = 'Unable to process a domain in the Domain Queue';
                $log_extra = array('Queue Domain ID' => $row->id, 'Domain' => $row->domain, 'Error' => $e->getMessage());
                $this->log->error($log_message, $log_extra);
            } catch (\Throwable $log_error) {
            }

            try {
                $this->markNotProcessingDomain($row->id);
            } catch (\Throwable $reset_error) {
            }

        }
    }

    /*
     * Fetches one domain's details from its registrar's API: array($domain_status, $expiration_date, $dns_servers,
     * $privacy_status, $autorenew_status), where $domain_status is '' for registrars that don't report one. Returns
     * false for a registrar name with no API class here. Values are returned as the registrar class gives them, so
     * callers make their own checks.
     */
    public function fetchRegistrarInfo($api_registrar_name, $registrar_account_id, $domain)
    {
        // Only some registrars report a domain status.
        $domain_status = '';

        if ($api_registrar_name == 'Above.com') {

            $registrar = new AboveCom();
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($registrar_account_id, $domain);

        } elseif ($api_registrar_name == 'Cloudflare') {

            $registrar = new Cloudflare();
            $api_key = $this->api->getKey($registrar_account_id);
            $account_id = $this->api->getAccountId($registrar_account_id);
            list($account_username, $account_password) = $this->api->getUserPass($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_id, $api_key, $domain);

        } elseif ($api_registrar_name == 'DNSimple') {

            $registrar = new DnSimple();
            $api_key = $this->api->getKey($registrar_account_id);
            $account_id = $this->api->getAccountId($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $account_id, $domain);

        } elseif ($api_registrar_name == 'DreamHost') {

            $registrar = new DreamHost();
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($registrar_account_id, $domain);

        } elseif ($api_registrar_name == 'Dynadot') {

            $registrar = new Dynadot();
            list($api_key, $api_secret) = $this->api->getKeySecret($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $domain);

        } elseif ($api_registrar_name == 'eNom') {

            $registrar = new Enom();
            list($account_username, $account_password) = $this->api->getUserPass($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_password, $domain);

        } elseif ($api_registrar_name == 'Fabulous') {

            $registrar = new Fabulous();
            list($account_username, $account_password) = $this->api->getUserPass($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_password, $domain);

        } elseif ($api_registrar_name == 'Freenom') {

            $registrar = new Freenom();
            list($account_username, $account_password) = $this->api->getUserPass($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_password, $domain);

        } elseif ($api_registrar_name == 'Gandi') {

            $registrar = new Gandi();
            $api_key = $this->api->getKey($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $domain);

        } elseif ($api_registrar_name == 'GoDaddy') {

            $registrar = new GoDaddy();
            list($api_key, $api_secret) = $this->api->getKeySecret($registrar_account_id);
            list($domain_status, $expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $domain);

        } elseif ($api_registrar_name == 'Internet.bs') {

            $registrar = new InternetBs();
            list($api_key, $api_secret) = $this->api->getKeySecret($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $domain);

        } elseif ($api_registrar_name == 'Name.com') {

            $registrar = new NameCom();
            list($account_username, $api_key) = $this->api->getUserKey($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_key, $domain);

        } elseif ($api_registrar_name == 'NameBright') {

            $registrar = new NameBright();
            list($account_username, $api_app_name, $api_secret) = $this->api->getUserAppSecret($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_app_name, $api_secret, $domain);

        } elseif ($api_registrar_name == 'Namecheap') {

            $registrar = new Namecheap();
            list($account_username, $api_key, $api_ip_address) = $this->api->getUserKeyIp($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_key, $api_ip_address, $domain);

        } elseif ($api_registrar_name == 'NameSilo') {

            $registrar = new NameSilo();
            $api_key = $this->api->getKey($registrar_account_id);
            list($domain_status, $expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $domain);

        } elseif ($api_registrar_name == 'OpenSRS') {

            $registrar = new OpenSrs();
            list($account_username, $api_key) = $this->api->getUserKey($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_key, $domain);

        } elseif ($api_registrar_name == 'OVH') {

            $registrar = new Ovh();
            list($api_key, $api_secret) = $this->api->getKeySecret($registrar_account_id);
            $account_id = $this->api->getAccountId($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $account_id, $domain);

        } elseif ($api_registrar_name == 'Porkbun') {

            $registrar = new Porkbun();
            list($api_key, $api_secret) = $this->api->getKeySecret($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $domain);

        } elseif ($api_registrar_name == 'ResellerClub') {

            $registrar = new ResellerClub();
            list($reseller_id, $api_key) = $this->api->getResellerIdKey($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($reseller_id, $api_key, $domain);

        } elseif ($api_registrar_name == 'SiteHost') {

            $registrar = new SiteHost();
            $api_key = $this->api->getKey($registrar_account_id);
            $account_id = $this->api->getAccountId($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $account_id, $domain);

        } elseif ($api_registrar_name == 'Hostinger') {

            $registrar = new Hostinger();
            $api_key = $this->api->getKey($registrar_account_id);
            list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $domain);

        } else {

            return false;

        }

        return array($domain_status, $expiration_date, $dns_servers, $privacy_status, $autorenew_status);
    }

    /*
     * Domains added with RDAP (queue/add/rdap.php) have no registrar or account yet: RDAP fills in the expiry date and
     * name servers and names the registrar, then RdapAssets finds or creates the registrar and account. Each run takes
     * at most RDAP_BATCH rows within a time budget; rows are claimed one at a time, so overlapping runs can't both
     * process a row; and a server that rate-limits or fails is skipped for the rest of the run.
     */

    public function processRdapQueue()
    {
        $pdo = $this->deeb->cnxx;

        $rdap_api_registrar_id = $this->getRdapApiRegistrarId();
        if (!$rdap_api_registrar_id) return;

        // never-tried rows first (oldest first), then retries, least recently tried first
        $stmt = $pdo->prepare("
            SELECT id, domain, tld, owner_id, created_by
            FROM domain_queue
            WHERE api_registrar_id = :api_registrar_id
              AND account_id = '0'
              AND processing = '0'
              AND ready_to_import = '0'
              AND finished = '0'
              AND copied_to_history = '0'
              AND already_in_domains = '0'
              AND already_in_queue = '0'
            ORDER BY (last_attempt > '1970-01-01 00:00:00'), last_attempt, id
            LIMIT " . self::RDAP_CANDIDATES);
        $stmt->bindValue('api_registrar_id', $rdap_api_registrar_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (!$result) return;

        $started = microtime(true);
        $budget = self::RDAP_BUDGET;
        $max_execution_time = (int) ini_get('max_execution_time');

        if ($max_execution_time > 0) {

            // keep a quarter of what's left (at most a minute) for the rest of the run, and on a later task's turn, that
            // task's needs (see Scheduler::reserveFor())
            $remaining = $max_execution_time - ($started - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? $started));
            $budget = min($budget, max(0, $remaining - min(60, $remaining / 4)));
            $budget = min($budget, max(0, $remaining - Scheduler::reserveFor('domain-queue')));

        }

        $log_message = '[START] Processing RDAP domains in the Domain Queue';
        $this->log->notice($log_message);

        $rdap = new Rdap();
        $rdap_assets = new RdapAssets();
        $whois = new Whois();
        $skipped_servers = array();
        $whois_queries = array();
        $attempted = 0;

        foreach ($result as $row) {

            if ($attempted >= self::RDAP_BATCH || microtime(true) - $started >= $budget) break;

            // each row is its own attempt, so a bootstrap download an earlier row's deadline cut off is tried again
            Rdap::newAttempt();

            // the bootstrap download (the first time serverFor() needs it) also counts against the budget
            $ascii_domain = Domain::forApi($row->domain);
            $server = ($ascii_domain !== '') ? $rdap->serverFor($ascii_domain, $started + $budget) : '';
            if ($server !== '' && isset($skipped_servers[$server])) continue;

            // with no RDAP server, the WHOIS server takes its place for the busy-server skip and the per-server cap
            $whois_found = false;
            $whois_server = '';

            if ($server === '' && $ascii_domain !== '' && $rdap->hasBootstrap($started + $budget)) {

                list($whois_found, $whois_server) = $whois->serverFor($ascii_domain, $started + $budget);

                if ($whois_server !== '' && (isset($skipped_servers['whois:' . $whois_server])
                        || ($whois_queries[$whois_server] ?? 0) >= self::WHOIS_PER_SERVER)) continue;

            }

            if (microtime(true) - $started >= $budget) break;

            try {

                $claimed = $this->claimRdapRow($row->id);

            } catch (\Throwable $e) {

                $this->logRdapError('Unable to claim an RDAP domain in the Domain Queue, will retry', $row->domain, $e);
                if (function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e)) self::$had_conflict = true;
                $claimed = false;

            }

            if (!$claimed) continue;

            $attempted++;

            /*
             * Every database write for the row is inside a per-row handler, so one failure is logged and the row is
             * left for the next run, instead of stopping the whole run with the row still claimed. The lookup never runs
             * past the end of the budget.
             */
            try {

                // the lock status is saved with the time its lookup started (see DomainStatus::isNewer())
                $status_started = gmdate('Y-m-d H:i:s');
                $status_source = 'rdap';
                $lookup = $rdap->lookup($row->domain, min(microtime(true) + 30, $started + $budget));

                if ($rdap->deadline_stopped) {

                    // the budget ran out before the registry was asked, so the server isn't skipped, and a WHOIS count
                    // the row already has is kept
                    $failures = $this->getWhoisFailures($row->id);
                    $this->releaseRdapRow($row->id, $failures > 0 ? 'retry_whois_error|' . $failures : 'retry_error');
                    continue;

                } elseif ($lookup['status'] === 'rate_limited' || $lookup['status'] === 'error') {

                    $this->releaseRdapRow($row->id, ($lookup['status'] === 'rate_limited') ? 'retry_rate_limited' : 'retry_error');
                    if ($server !== '') $skipped_servers[$server] = true;
                    continue;

                } elseif ($lookup['status'] === 'mismatch') {

                    // the server answered for another domain: retried like an error, but the server isn't skipped, so
                    // other domains on it are still looked up
                    $this->releaseRdapRow($row->id, 'retry_error');
                    continue;

                } elseif ($lookup['status'] === 'no_server') {

                    // no RDAP server for the TLD, so try WHOIS
                    $failures = $this->getWhoisFailures($row->id);

                    if (!$whois_found) {

                        // IANA couldn't be asked for the WHOIS server, so port 43 was never tried: keep the count
                        $this->releaseRdapRow($row->id, 'retry_whois_error|' . $failures);
                        continue;

                    }

                    if ($whois_server === '') {

                        $this->markInvalidDomain($row->id, 'no_lookup|' . mb_substr((string) $row->tld, 0, 60));
                        continue;

                    }

                    $whois_queries[$whois_server] = ($whois_queries[$whois_server] ?? 0) + 1;
                    $status_started = gmdate('Y-m-d H:i:s');
                    $status_source = 'whois';
                    $whois_deadline = min(microtime(true) + 30, $started + $budget);
                    $lookup = $whois->lookup($row->domain, $whois_deadline);

                    if ($whois->deadline_stopped) {

                        // the budget ran out before the query was sent: the row waits for the next run with its count
                        // as it was, and the server isn't skipped or charged for it
                        $whois_queries[$whois_server]--;
                        $this->releaseRdapRow($row->id, 'retry_whois_error|' . $failures);
                        continue;

                    } elseif ($lookup['status'] === 'rate_limited' || $lookup['status'] === 'error') {

                        $skipped_servers['whois:' . $whois_server] = true;

                        // only a connection that couldn't be made counts (some hosts block port 43), any reply resets the
                        // count, and a lookup that never tried to connect (too little time left) leaves it as it was, as
                        // does a query that was written but whose reply the deadline cut off (a server that closes the
                        // connection without a reply while time is left still counts)
                        $reply_cut_off = $whois->query_written && $whois_deadline - microtime(true) < 0.5;
                        if ($lookup['connection_failed'] && !$reply_cut_off) {
                            $failures++;
                        } elseif ($lookup['replied']) {
                            $failures = 0;
                        }

                        if ($failures >= self::WHOIS_MAX_FAILURES) {
                            $this->markInvalidDomain($row->id, 'whois_unreachable');
                        } elseif ($lookup['status'] === 'rate_limited') {
                            $this->releaseRdapRow($row->id, 'retry_whois_rate_limited');
                        } else {
                            $this->releaseRdapRow($row->id, 'retry_whois_error|' . $failures);
                        }
                        continue;

                    } elseif ($lookup['status'] === 'not_found') {

                        $this->markInvalidDomain($row->id, 'whois_not_found');
                        continue;

                    } elseif ($lookup['expiry_date'] === '') {

                        $this->markInvalidDomain($row->id, 'whois_no_expiry');
                        continue;

                    }

                } elseif ($lookup['status'] === 'not_found') {

                    $this->markInvalidDomain($row->id, 'not_found');
                    continue;

                } elseif ($lookup['expiry_date'] === '') {

                    $this->markInvalidDomain($row->id, 'no_expiry');
                    continue;

                }

                // network work (the IP lookup) happens before the lock, which then only covers database work
                $ip_id = $this->updateIp($row->id, $row->domain, $row->created_by);

            } catch (\Throwable $e) {

                $this->logRdapError('Unable to process an RDAP domain in the Domain Queue, will retry', $row->domain, $e);
                // A write that met another request's isn't a failed lookup, so the row says DomainMOD was busy
                $conflict = function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e);
                if ($conflict) self::$had_conflict = true;
                $this->releaseRdapRow($row->id, $conflict ? 'retry_busy' : 'retry_error');
                continue;

            }

            if (!$this->getRdapLock()) {

                $this->releaseRdapRow($row->id, 'retry_lock');
                continue;

            }

            $imported = false;
            $rdap_fee_lock = null;
            $rdap_busy = false;

            try {

                // REPEATABLE READ, so the import's locking reads' next-key locks hold even on a server whose default is READ
                // COMMITTED
                $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                $pdo->beginTransaction();

                // The fee-change lock first, before the registrar and its account are found or created, as the CSV
                // import takes it before creating assets, so a registrar delete never waits on this import's new account
                // while this import waits on its lock (importToMainDb() takes it again, and it's let go below)
                $rdap_fee_lock = (new ChangeHistory($pdo))->start(ChangeHistory::SOURCE_QUEUE, (int) $row->created_by);
                $rdap_fee_lock->holdNamedLock('domainmod_fee_changes_' . substr(md5(DB_NAME), 0, 12));

                list($registrar_id, $account_id) = $rdap_assets->resolve($lookup['registrar_name'],
                    $lookup['registrar_iana_id'], $lookup['registrar_url'], $row->owner_id, $row->created_by);

                $stmt = $pdo->prepare("
                    UPDATE domain_queue
                    SET registrar_id = :registrar_id,
                        account_id = :account_id
                    WHERE id = :queue_domain_id");
                $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
                $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
                $stmt->bindValue('queue_domain_id', $row->id, \PDO::PARAM_INT);
                $stmt->execute();

                // a domain with no name servers still imports, with the usual placeholders
                $dns_servers = $lookup['name_servers'] ?: array('no.dns-servers.1', 'no.dns-servers.2');

                $this->updateExpirationDate($row->id, $lookup['expiry_date']);
                $dns_id = $this->updateDnsServers($row->id, $dns_servers, $row->created_by);
                $cat_id = $this->updateCategory($row->id, $row->created_by);

                // RDAP doesn't give WHOIS privacy or auto renewal, so both are No (see the RDAP page's warning)
                $this->updatePrivacy($row->id, '0');
                $this->updateRenewStatus($row->id, '0');

                if ($registrar_id && $account_id && $dns_id && $ip_id && $cat_id) {

                    $stmt = $pdo->prepare("
                        UPDATE domain_queue
                        SET ready_to_import = '1',
                            message = ''
                        WHERE id = :queue_domain_id");
                    $stmt->bindValue('queue_domain_id', $row->id, \PDO::PARAM_INT);
                    $stmt->execute();

                    /*
                     * The import happens in the same transaction, so a failure (or a run that dies) leaves the row as it
                     * was, still waiting for RDAP, instead of prepared but never imported. The domain may have reached
                     * the main domain table since it was queued, so check again.
                     */
                    $existing_domain_id = $this->getDomainIdByName($row->domain);

                    if ($existing_domain_id) {

                        $this->markAlreadyInDomains($row->id, $existing_domain_id);

                    } else {

                        $new_domain_id = $this->importToMainDb($row->id);

                        // importToMainDb() returns a message instead of an id when it can't import the row
                        if (!ctype_digit((string) $new_domain_id)) {

                            throw new \RuntimeException('The domain could not be imported');

                        }

                        $this->updateNewDomainId($row->id, $new_domain_id);
                        $this->saveImportedStatus((int) $new_domain_id, $status_source, $lookup, $status_started);
                        $this->markFinishedDomain($row->id, $row->domain, $lookup['expiry_date'], $dns_id, $ip_id,
                            $cat_id, '0', '0');

                    }

                    $imported = true;

                }

                if ($imported) {

                    $pdo->commit();
                    $rdap_assets->remember();

                } else {

                    // nothing usable to import yet: undo the registrar and account changes and retry later
                    $pdo->rollBack();
                    $rdap_assets->forget();

                }

            } catch (\Throwable $e) {

                try {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                } catch (\Throwable $rollback_error) {
                }

                $rdap_assets->forget();
                $this->logRdapError('Unable to save an RDAP domain in the Domain Queue, will retry', $row->domain, $e);
                $imported = false;
                // A busy fee-change lock isn't a failed lookup, so the row says DomainMOD was busy, as it does for a
                // save that met another request's write (also noted for hadConflict())
                if ($e instanceof RestoreLockException) $rdap_busy = true;
                if (function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e)) {
                    $rdap_busy = true;
                    self::$had_conflict = true;
                }

            }

            // Lets go of the fee-change lock, after the commit or rollback
            $rdap_fee_lock = null;
            $this->releaseRdapLock();

            if (!$imported) $this->releaseRdapRow($row->id, $rdap_busy ? 'retry_busy' : 'retry_error');

        }

        $log_message = '[END] Processing RDAP domains in the Domain Queue';
        $this->log->notice($log_message);
    }

    // The id of the RDAP source in api_registrars, or 0 before the database update that adds it
    // Whether the RDAP import has a row waiting (for the turns the tasks take, see Scheduler::turn())
    public function hasRdapWork()
    {
        $rdap_api_registrar_id = $this->getRdapApiRegistrarId();
        if (!$rdap_api_registrar_id) return false;

        $stmt = $this->deeb->cnxx->prepare("
            SELECT id
            FROM domain_queue
            WHERE api_registrar_id = :api_registrar_id
              AND account_id = '0'
              AND processing = '0'
              AND ready_to_import = '0'
              AND finished = '0'
              AND copied_to_history = '0'
              AND already_in_domains = '0'
              AND already_in_queue = '0'
            LIMIT 1");
        $stmt->bindValue('api_registrar_id', $rdap_api_registrar_id, \PDO::PARAM_INT);
        $stmt->execute();
        $found = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $found !== false;
    }

    public function getRdapApiRegistrarId()
    {
        return (int) $this->deeb->cnxx->query("
            SELECT id
            FROM api_registrars
            WHERE `name` = 'RDAP'
            LIMIT 1")->fetchColumn();
    }

    // Claims one row for this run (true) unless another run already has it or it's no longer waiting (false)
    public function claimRdapRow($queue_domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET processing = '1',
                last_attempt = :last_attempt
            WHERE id = :queue_domain_id
              AND processing = '0'
              AND ready_to_import = '0'
              AND finished = '0'
              AND copied_to_history = '0'
              AND already_in_domains = '0'
              AND already_in_queue = '0'");
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('last_attempt', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    // Consecutive failed connections to the row's WHOIS server so far (kept in its message, see processRdapQueue())
    public function getWhoisFailures($queue_domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT message
            FROM domain_queue
            WHERE id = :queue_domain_id");
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $message = (string) $stmt->fetchColumn();

        $code_parts = explode('|', $message, 2);
        return ($code_parts[0] === 'retry_whois_error') ? (int) ($code_parts[1] ?? 0) : 0;
    }

    // Leaves a row for a later run, with the reason it's waiting (never throws, so a failure can't stop the run)
    public function releaseRdapRow($queue_domain_id, $message)
    {
        $pdo = $this->deeb->cnxx;

        try {

            $stmt = $pdo->prepare("
                UPDATE domain_queue
                SET processing = '0',
                    message = :message
                WHERE id = :queue_domain_id");
            $stmt->bindValue('message', $message, \PDO::PARAM_STR);
            $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
            $stmt->execute();

        } catch (\Throwable $e) {

            $this->logRdapError('Unable to release an RDAP domain in the Domain Queue', '', $e);

        }
    }

    // Serialises finding or creating registrars and accounts across overlapping queue runs (true when held)
    public function getRdapLock()
    {
        try {

            $stmt = $this->deeb->cnxx->prepare("SELECT GET_LOCK(:lock_name, 10)");
            $stmt->bindValue('lock_name', $this->getRdapLockName(), \PDO::PARAM_STR);
            $stmt->execute();

            return (string) $stmt->fetchColumn() === '1';

        } catch (\Throwable $e) {

            $this->logRdapError('Unable to get the RDAP lock for the Domain Queue', '', $e);
            return false;

        }
    }

    public function releaseRdapLock()
    {
        try {

            $stmt = $this->deeb->cnxx->prepare("SELECT RELEASE_LOCK(:lock_name)");
            $stmt->bindValue('lock_name', $this->getRdapLockName(), \PDO::PARAM_STR);
            $stmt->execute();

        } catch (\Throwable $e) {

            $this->logRdapError('Unable to release the RDAP lock for the Domain Queue', '', $e);

        }
    }

    /*
     * Named locks are shared by every database on the server, so the name includes a short hash of this database's name,
     * and two installations on one server don't block each other (as the Task Scheduler's locks do).
     */
    protected function getRdapLockName()
    {
        return 'domainmod_rdap_assets_' . substr(md5(DB_NAME), 0, 12);
    }

    // The id of the 'Queue (Edited Manually)' creation type, or 0 before the database update that adds it
    public function getEditedCreationTypeId()
    {
        return (int) $this->deeb->cnxx->query("
            SELECT id
            FROM creation_types
            WHERE `name` = 'Queue (Edited Manually)'
            LIMIT 1")->fetchColumn();
    }

    // A queue domain that failed and hasn't been fixed and imported since (queue/edit/), or false
    public function getFixableQueueDomain($queue_domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id, domain, account_id, expiry_date, cat_id, dns_id, ip_id, autorenew, privacy, message
            FROM domain_queue
            WHERE id = :queue_domain_id
              AND finished = '1'
              AND invalid_domain = '1'
              AND already_in_domains = '0'
              AND already_in_queue = '0'
              AND message != 'edited'");
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        return $result;
    }

    // The text for a stored RDAP message code, translated for the viewing user (escape it when printing)
    public function getRdapMessage($code)
    {
        $code_parts = explode('|', (string) $code, 2);

        if ($code_parts[0] === '' || $code_parts[0] === 'edited') {
            $message = '';
        } elseif ($code_parts[0] === 'no_server') {
            $message = sprintf(_('No RDAP server for .%s domains'), $code_parts[1] ?? '');
        } elseif ($code_parts[0] === 'no_expiry') {
            $message = _('RDAP has no expiry date for this domain');
        } elseif ($code_parts[0] === 'not_found') {
            $message = _('Domain not found in RDAP');
        } elseif ($code_parts[0] === 'retry_rate_limited') {
            $message = _('Will retry: the RDAP server is limiting requests');
        } elseif ($code_parts[0] === 'retry_error') {
            $message = _('Will retry: the RDAP lookup failed');
        } elseif ($code_parts[0] === 'retry_lock') {
            $message = _('Will retry: another Domain Queue run was busy');
        } elseif ($code_parts[0] === 'retry_busy') {
            $message = _('Will retry: DomainMOD was busy');
        } elseif ($code_parts[0] === 'no_lookup') {
            $message = sprintf(_('Neither RDAP nor WHOIS is available for .%s domains'), $code_parts[1] ?? '');
        } elseif ($code_parts[0] === 'whois_no_expiry') {
            $message = _('WHOIS has no expiry date for this domain');
        } elseif ($code_parts[0] === 'whois_not_found') {
            $message = _('Domain not found in WHOIS');
        } elseif ($code_parts[0] === 'whois_unreachable') {
            $message = _("WHOIS couldn't be reached (your server may block port 43)");
        } elseif ($code_parts[0] === 'retry_whois_rate_limited') {
            $message = _('Will retry: the WHOIS server is limiting requests');
        } elseif ($code_parts[0] === 'retry_whois_error') {
            $message = _('Will retry: the WHOIS lookup failed');
        } else {
            $message = _('RDAP lookup failed');
        }

        return $message;
    }

    /*
     * The same message as HTML, escaped, with the TLD in the "no server" messages kept left to right (in Arabic a TLD
     * such as .co.uk otherwise reads as "co.uk."), as DomainRefresh::tldMessage() does for the Refresh messages.
     * getRdapMessage() stays plain text for the export.
     */
    public function getRdapMessageHtml($code)
    {
        $html = htmlspecialchars($this->getRdapMessage($code), ENT_QUOTES, 'UTF-8');
        $code_parts = explode('|', (string) $code, 2);
        $tld = $code_parts[1] ?? '';
        if ($tld === '' || !in_array($code_parts[0], array('no_server', 'no_lookup'), true)) return $html;

        $layout = new Layout();
        return str_replace(htmlspecialchars('.' . $tld, ENT_QUOTES, 'UTF-8'), $layout->ltr('.' . $tld), $html);
    }

    /*
     * Sets a domain imported through RDAP's lock status from the lookup that imported it, as Refresh does (see
     * DomainStatus): a completed RDAP read (found or none) is saved, a WHOIS import is Unknown, and an incomplete read
     * only saves when the lookup ran, so the domain shows Couldn't Check. $started is when the lookup started (UTC). It runs inside the
     * import's transaction, and only once the 4.27.0 database update has added the columns.
     */
    protected function saveImportedStatus($domain_id, $source, $lookup, $started)
    {
        if (!DomainStatus::isAvailable($this->deeb->cnxx)) return;

        if ($source === 'whois') {
            $codes = array();
        } elseif (in_array($lookup['status_read'] ?? '', array('found', 'none'), true)) {
            $codes = $lookup['status_read'] === 'found' ? DomainStatus::ordered($lookup['statuses'] ?? array()) : array();
        } else {

            // the codes couldn't be read, so the new domain shows Couldn't Check, with when the lookup ran
            $stmt = $this->deeb->cnxx->prepare("
                UPDATE domains
                SET epp_status_failed_time = :epp_status_failed_time
                WHERE id = :domain_id");
            $stmt->bindValue('epp_status_failed_time', $started, \PDO::PARAM_STR);
            $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
            $stmt->execute();
            return;

        }

        $stmt = $this->deeb->cnxx->prepare("
            UPDATE domains
            SET transfer_lock = :transfer_lock,
                epp_status = :epp_status,
                epp_status_time = :epp_status_time
            WHERE id = :domain_id");
        $stmt->bindValue('transfer_lock', DomainStatus::lockFor($codes), \PDO::PARAM_INT);
        $stmt->bindValue('epp_status', DomainStatus::toColumn($codes), \PDO::PARAM_STR);
        $stmt->bindValue('epp_status_time', $started, \PDO::PARAM_STR);
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    // Logging never stops the run (see processQueueListRow())
    protected function logRdapError($log_message, $domain, $error)
    {
        try {
            $log_extra = array('Domain' => $domain, 'Error' => $error->getMessage());
            $this->log->error($log_message, $log_extra);
        } catch (\Throwable $log_error) {
        }
    }

    public function getQueueList()
    {
        return $this->deeb->cnxx->query("
            SELECT dql.id, dql.api_registrar_id, dql.owner_id, dql.registrar_id, dql.account_id, dql.created_by,
                ar.name AS api_registrar_name
            FROM domain_queue_list AS dql, api_registrars AS ar
            WHERE dql.api_registrar_id = ar.id
              AND dql.processing = '0'
              AND dql.ready_to_import = '0'
              AND dql.finished = '0'
              AND dql.copied_to_history = '0'
            ORDER BY " . ($this->hasListLastAttempt()
                ? "(dql.last_attempt > '1970-01-01 00:00:00'), dql.last_attempt, dql.insert_time DESC"
                : "dql.insert_time DESC"))->fetchAll();
    }

    public function getQueueDomain()
    {
        return $this->deeb->cnxx->query("
            SELECT dq.id, dq.api_registrar_id, dq.domain, dq.owner_id, dq.registrar_id, dq.account_id, dq.created_by,
                ar.name AS api_registrar_name
            FROM domain_queue AS dq, api_registrars AS ar
            WHERE dq.api_registrar_id = ar.id
              AND ar.name != 'RDAP'
              AND dq.processing = '0'
              AND dq.ready_to_import = '0'
              AND dq.finished = '0'
              AND dq.copied_to_history = '0'
              AND dq.already_in_domains = '0'
              AND dq.already_in_queue = '0'
            ORDER BY (dq.last_attempt > '1970-01-01 00:00:00'), dq.last_attempt, dq.id")->fetchAll();
    }

    // Records when a queued domain's registrar was last asked for its details, so domains not tried yet go first and
    // the rest take turns. A failure here is logged and never stops the run.
    public function stampDomainAttempt($queue_domain_id)
    {
        try {

            $stmt = $this->deeb->cnxx->prepare("
                UPDATE domain_queue
                SET last_attempt = :last_attempt
                WHERE id = :queue_domain_id");
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('last_attempt', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
            $stmt->execute();

        } catch (\Throwable $e) {

            try {
                $log_message = "Unable to save a queued domain's last attempt";
                $log_extra = array('Queue Domain ID' => $queue_domain_id, 'Error' => $e->getMessage());
                $this->log->error($log_message, $log_extra);
            } catch (\Throwable $log_error) {
            }

        }
    }

    public function markProcessingList()
    {
        $this->deeb->cnxx->query("
            UPDATE domain_queue_list
            SET processing = '1'
            WHERE processing = '0'
              AND ready_to_import = '0'
              AND finished = '0'
              AND copied_to_history = '0'");
    }

    public function updateDomainCount($list_id, $domain_count)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue_list
            SET domain_count = :domain_count
            WHERE id = :id");
        $stmt->bindValue('domain_count', $domain_count, \PDO::PARAM_INT);
        $stmt->bindValue('id', $list_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markProcessingDomain()
    {
        $this->deeb->cnxx->query("
            UPDATE domain_queue
            SET processing = '1'
            WHERE processing = '0'
              AND ready_to_import = '0'
              AND finished = '0'
              AND copied_to_history = '0'
              AND already_in_domains = '0'
              AND already_in_queue = '0'
              AND api_registrar_id NOT IN (SELECT id FROM api_registrars WHERE `name` = 'RDAP')");
    }

    public function updateDomain($queue_domain_id, $domain, $expiration_date, $dns_servers, $privacy_status, $autorenew_status, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        // the IP lookup goes first, so its network work happens before the caller's transaction locks anything
        $ip_id = $this->updateIp($queue_domain_id, $domain, $created_by);
        $this->updateExpirationDate($queue_domain_id, $expiration_date);
        $dns_id = $this->updateDnsServers($queue_domain_id, $dns_servers, $created_by);
        $cat_id = $this->updateCategory($queue_domain_id, $created_by);
        $this->updatePrivacy($queue_domain_id, $privacy_status);
        $this->updateRenewStatus($queue_domain_id, $autorenew_status);

        // don't mark the domain as ready to import if the information isn't valid
        if ($expiration_date != '0000-00-00' && $dns_id != '0' && $dns_id != '' && $ip_id != '0' && $ip_id != ''
            && $cat_id != '0' && $cat_id != '' && $privacy_status != '' && $autorenew_status != '') {

            $stmt = $pdo->prepare("
                UPDATE domain_queue
                SET ready_to_import = '1'
                WHERE id = :queue_domain_id");
            $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
            $stmt->execute();

            $ready_to_import = '1';

        } else {

            $ready_to_import = '0';

        }

        return array($ready_to_import, $expiration_date, $dns_id, $ip_id, $cat_id, $privacy_status, $autorenew_status);
    }

    public function updateExpirationDate($queue_domain_id, $expiration_date)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET expiry_date = :expiration_date
            WHERE id = :queue_domain_id");
        $stmt->bindValue('expiration_date', $expiration_date, \PDO::PARAM_STR);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /*
     * The existing DNS profile holding exactly these name servers (order ignored, compared the way Live DNS compares a
     * profile with live servers, see LiveDns::sameServers()), as array('id' => ..., 'name' => ...), or null. Read-only:
     * it never creates a profile or locks a row, so the edit page's Live DNS section can suggest one. Profile 1 ("[no
     * dns]") is never suggested, and with more than 10 servers no profile can hold them. Unlike findOrCreateDnsProfile(),
     * the profile's servers are normalised first (trailing dots, case, and Unicode names) and duplicates are ignored.
     * When several match, the one read last in findOrCreateDnsProfile()'s order (the least recently updated) is used.
     */
    public function findDnsProfile(array $dns_servers)
    {
        if (!$dns_servers || count($dns_servers) > 10) return null;

        $result = $this->deeb->cnxx->query("
            SELECT id, `name`, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10
            FROM dns
            WHERE id <> '1'
            ORDER BY update_time DESC, insert_time DESC")->fetchAll();

        $match = null;
        foreach ($result as $row) {
            $servers = array($row->dns1, $row->dns2, $row->dns3, $row->dns4, $row->dns5, $row->dns6, $row->dns7, $row->dns8,
                $row->dns9, $row->dns10);
            // The last match wins, as in findOrCreateDnsProfile()
            if (LiveDns::sameServers($dns_servers, $servers)) $match = array('id' => (int) $row->id, 'name' => (string) $row->name);
        }

        return $match;
    }

    /*
     * The DNS profile for a list of DNS servers: array(id, created). An existing profile with the same servers (in any
     * order and case) is used, otherwise a new one is created with $name and $notes. Refreshing a domain passes $lock,
     * inside its transaction, so a matched profile is locked and checked again before it's used.
     */
    public function findOrCreateDnsProfile($dns_servers, $created_by, $name = '[created by queue]', $notes = 'Created by queue.', $lock = false)
    {
        $pdo = $this->deeb->cnxx;

        $matches = array();

        // lower case the DNS servers for accurate matching
        $lower_value = array();
        foreach ($dns_servers as $value) {
            $lower_value[] = strtolower($value);
        }
        $dns_servers = $lower_value;

        $result = $pdo->query("
            SELECT id, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10
            FROM dns
            ORDER BY update_time DESC, insert_time DESC")->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                $existing_dns = array($row->dns1, $row->dns2, $row->dns3, $row->dns4, $row->dns5, $row->dns6, $row->dns7,
                    $row->dns8, $row->dns9, $row->dns10);
                $filtered_dns = array_filter($existing_dns);

                // lower case the DNS servers for accurate matching
                $lower_value = array();
                foreach ($filtered_dns as $value) {
                    $lower_value[] = strtolower($value);
                }
                $filtered_dns = $lower_value;

                // If there's a match use it (the last match wins)
                if (array_count_values($filtered_dns) == array_count_values($dns_servers)) {

                    $matches[] = $row->id;

                }

            }

        }

        // With $lock, each match is locked and checked again, newest choice first, since a profile can change between
        // the read above and the caller's update.
        $new_dns_id = $lock ? $this->lockMatchingDnsProfile(array_reverse($matches), $dns_servers) : end($matches);
        $created = false;

        // If the DNS Profile doesn't exist create a new one
        if (!$new_dns_id) {

            $new_servers = array();
            $count = 0;

            // Make sure DNS servers were returned
            foreach ($dns_servers as $server) {

                $new_servers[$count++] = strtolower($server);

            }

            $clean_servers = array_filter($new_servers);
            $number_of_servers = count($clean_servers);

            $creation_type_id = $this->system->getCreationTypeId('Queue');

            $stmt = $pdo->prepare("
                INSERT INTO dns
                (`name`, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, notes, number_of_servers,
                 creation_type_id, created_by, insert_time)
                VALUES
                (:name, :new_servers0, :new_servers1, :new_servers2, :new_servers3, :new_servers4,
                 :new_servers5, :new_servers6, :new_servers7, :new_servers8, :new_servers9, :notes, :number_of_servers,
                 :creation_type_id, :created_by, :insert_time)");

            if (!isset($new_servers[0])) $new_servers[0] = '';
            if (!isset($new_servers[1])) $new_servers[1] = '';
            if (!isset($new_servers[2])) $new_servers[2] = '';
            if (!isset($new_servers[3])) $new_servers[3] = '';
            if (!isset($new_servers[4])) $new_servers[4] = '';
            if (!isset($new_servers[5])) $new_servers[5] = '';
            if (!isset($new_servers[6])) $new_servers[6] = '';
            if (!isset($new_servers[7])) $new_servers[7] = '';
            if (!isset($new_servers[8])) $new_servers[8] = '';
            if (!isset($new_servers[9])) $new_servers[9] = '';

            $temp_notes = $this->time->timeBasic() . ' - ' . $notes;

            $stmt->bindValue('name', $name, \PDO::PARAM_STR);
            $stmt->bindValue('new_servers0', $new_servers[0], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers1', $new_servers[1], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers2', $new_servers[2], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers3', $new_servers[3], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers4', $new_servers[4], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers5', $new_servers[5], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers6', $new_servers[6], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers7', $new_servers[7], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers8', $new_servers[8], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers9', $new_servers[9], \PDO::PARAM_STR);
            $stmt->bindValue('notes', $temp_notes, \PDO::PARAM_LOB);
            $stmt->bindValue('number_of_servers', $number_of_servers, \PDO::PARAM_INT);
            $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
            $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->execute();

            $new_dns_id = $pdo->lastInsertId('id');
            $created = true;

        }

        return array($new_dns_id, $created);
    }

    // The first of $dns_ids whose profile still has exactly $dns_servers (already lower case), locked for the rest of the
    // transaction, or 0 when none does.
    protected function lockMatchingDnsProfile($dns_ids, $dns_servers)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10
            FROM dns
            WHERE id = :dns_id
            FOR UPDATE");

        foreach ($dns_ids as $dns_id) {

            $stmt->bindValue('dns_id', $dns_id, \PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();
            $stmt->closeCursor();
            if (!$row) continue;

            $existing_dns = array_map('strtolower', array_filter(array($row->dns1, $row->dns2, $row->dns3, $row->dns4,
                $row->dns5, $row->dns6, $row->dns7, $row->dns8, $row->dns9, $row->dns10)));

            if (array_count_values($existing_dns) == array_count_values($dns_servers)) return $dns_id;

        }

        return 0;
    }

    public function updateDnsServers($queue_domain_id, $dns_servers, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        list($new_dns_id) = $this->findOrCreateDnsProfile($dns_servers, $created_by);

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET dns_id = :new_dns_id
            WHERE id = :queue_domain_id");
        $stmt->bindValue('new_dns_id', $new_dns_id, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $new_dns_id;
    }

    public function updateIp($queue_domain_id, $domain, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $has_match = '';

        // get the rDNS for the IP
        // look up the punycode form, which is what DNS uses
        $ascii_domain = Domain::toAscii($domain);
        $live_ip = $ascii_domain !== '' ? gethostbyname($ascii_domain . '.') : '';

        // If the domain doesn't resolve assign an IP and rDNS of 0.0.0.0 (gethostbyname() returns the name it was
        // given when it can't resolve it)
        if (filter_var($live_ip, FILTER_VALIDATE_IP) === false) {

            $live_ip = '0.0.0.0';
            $rdns = '0.0.0.0';

        } else {

            $rdns = gethostbyaddr($live_ip);

        }

        // Check to see if the IP already exists
        $result = $pdo->query("
            SELECT id, ip
            FROM ip_addresses
            ORDER BY update_time DESC, insert_time DESC")->fetchAll();

        if ($result) {

            // Cycle through the existing IPs to see if there's a match
            foreach ($result as $row) {

                // If there's a match use it
                if ($live_ip == $row->ip) {

                    $new_ip_id = $row->id;
                    $has_match = '1';

                }

            }

        }

        // If the IP doesn't exist create a new one
        if ($has_match != '1') {
            
            $creation_type_id = $this->system->getCreationTypeId('Queue');

            $stmt = $pdo->prepare("
                INSERT INTO ip_addresses
                (`name`, ip, rdns, notes, creation_type_id, created_by, insert_time)
                VALUES
                ('[created by queue]', :live_ip, :rdns, :notes, :creation_type_id, :created_by, :insert_time)");

            $temp_notes = $this->time->timeBasic() . ' - Created by queue.';

            $stmt->bindValue('live_ip', $live_ip, \PDO::PARAM_STR);
            $stmt->bindValue('rdns', $rdns, \PDO::PARAM_STR);
            $stmt->bindValue('notes', $temp_notes, \PDO::PARAM_LOB);
            $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
            $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->execute();

            $new_ip_id = $pdo->lastInsertId('id');

        }

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET ip_id = :new_ip_id
            WHERE id = :queue_domain_id");
        $stmt->bindValue('new_ip_id', $new_ip_id, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $new_ip_id;
    }

    public function updateCategory($queue_domain_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT id
            FROM categories
            WHERE `name` = '" . _('[created by queue]') . "'
            ORDER BY update_time DESC, insert_time DESC
            LIMIT 1")->fetchColumn();

        if (!$result) { // If there isn't an existing '[created by queue]' category create one

            $creation_type_id = $this->system->getCreationTypeId('Queue');

            $stmt = $pdo->prepare("
                INSERT INTO categories
                (`name`, stakeholder, creation_type_id, created_by, insert_time)
                VALUES
                ('" . _('[created by queue]') . "', '" . _('[created by queue]') . "', :creation_type_id, :created_by, :insert_time)");
            $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
            $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->execute();

            $category_id = $pdo->lastInsertId('id');

        } else { // If there's an existing '[created by queue]' category use it

            $category_id = $result;

        }

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET cat_id = :category_id
            WHERE id = :queue_domain_id");
        $stmt->bindValue('category_id', $category_id, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $category_id;
    }

    public function updatePrivacy($queue_domain_id, $privacy_status)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET privacy = :privacy_status
            WHERE id = :queue_domain_id");
        $stmt->bindValue('privacy_status', $privacy_status, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function updateRenewStatus($queue_domain_id, $autorenew_status)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET autorenew = :autorenew_status
            WHERE id = :queue_domain_id");
        $stmt->bindValue('autorenew_status', $autorenew_status, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function importToDomainQueue($api_registrar_id, $domain, $owner_id, $registrar_id, $account_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        // registrars often return punycode (xn--mnchen-3ya.de); store the normalised form (münchen.de), and skip a
        // name that isn't a valid domain rather than failing the whole list
        $raw_domain = $domain;
        $domain = Domain::normalize($domain);
        if ($domain === '') {

            // logged whether or not debugging is on, since the domain is left out of the queue
            $log_message = 'Skipped an invalid domain name in a Domain Queue list';
            $log_extra = array('Domain' => $raw_domain, 'API Registrar ID' => $api_registrar_id);
            $this->log->addEntry('warning', $log_message, $log_extra);
            return;

        }

        $tld = $this->domain->getTld($domain);

        // check to make sure that the domain isn't already in the main domain table
        $stmt = $pdo->prepare("
            SELECT id
            FROM domains
            WHERE domain = :domain");
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchColumn();

        if ($result) { // already in the main domain table

            $stmt = $pdo->prepare("
                INSERT INTO domain_queue
                (api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, processing,
                 ready_to_import, finished, already_in_domains, created_by, insert_time)
                VALUES
                (:api_registrar_id, :domain_id, :owner_id, :registrar_id, :account_id, :domain, :tld, '0', '1', '1',
                 '1', :created_by, :insert_time)");
            $stmt->bindValue('api_registrar_id', $api_registrar_id, \PDO::PARAM_INT);
            $stmt->bindValue('domain_id', $result, \PDO::PARAM_INT);
            $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
            $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
            $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
            $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->execute();

        } else { // not already in the main domain table

            // check to make sure that the domain isn't already in the domain queue
            $stmt = $pdo->prepare("
                SELECT id
                FROM domain_queue
                WHERE domain = :domain");
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetchColumn();

            if ($result) { // already in the domain queue

                $stmt = $pdo->prepare("
                    INSERT INTO domain_queue
                    (api_registrar_id, owner_id, registrar_id, account_id, domain, tld, processing, ready_to_import, finished, already_in_queue, created_by, insert_time)
                    VALUES
                    (:api_registrar_id, :owner_id, :registrar_id, :account_id, :domain, :tld, '0', '1', '1', '1', :created_by, :insert_time)");
                $stmt->bindValue('api_registrar_id', $api_registrar_id, \PDO::PARAM_INT);
                $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
                $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
                $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
                $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
                $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
                $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
                $bind_timestamp = $this->time->stamp();
                $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
                $stmt->execute();

            } else { // if it's not in the main domain table or the domain queue

                $stmt = $pdo->prepare("
                    INSERT INTO domain_queue
                    (api_registrar_id, owner_id, registrar_id, account_id, domain, tld, created_by, insert_time)
                    VALUES
                    (:api_registrar_id, :owner_id, :registrar_id, :account_id, :domain, :tld, :created_by, :insert_time)");
                $stmt->bindValue('api_registrar_id', $api_registrar_id, \PDO::PARAM_INT);
                $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
                $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
                $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
                $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
                $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
                $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
                $bind_timestamp = $this->time->stamp();
                $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
                $stmt->execute();

            }

        }

        $_SESSION['s_domains_in_queue'] = '1';
    }

    public function importToMainDb($queue_domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id, autorenew, privacy, created_by, insert_time
            FROM domain_queue
            WHERE id = :queue_domain_id
              AND ready_to_import = '1'
              AND already_in_domains != '1'
              AND already_in_queue != '1'
            ORDER BY insert_time ASC");
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if (!$result) {

            $log_message = 'Unable to retrieve domains from queue';
            $log_extra = array('Queue Domain ID' => $queue_domain_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            // A domain the queue imports has no hosting service (service_id keeps its default of 0). Before the update,
            // the old hosting_id column is still there, and is written as 0 so it doesn't take its old default of 1 (a
            // real provider on some installs). A stopped upgrade that has dropped it writes the domain as the update does
            // (see Hosting::legacyDomainColumn()).
            if (!$this->legacy_domain_column) {
                $stmt = $pdo->prepare("
                    INSERT INTO domains
                    (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                     notes, autorenew, privacy, creation_type_id, created_by, insert_time)
                    VALUES
                    (:owner_id, :registrar_id, :account_id, :domain, :tld, :expiry_date, :cat_id, :dns_id, :ip_id,
                     :notes, :autorenew, :privacy, :creation_type_id, :created_by, :insert_time)");
            } else {
                // Legacy: domains.hosting_id before 4.27.0
                $stmt = $pdo->prepare("
                    INSERT INTO domains
                    (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id, hosting_id,
                     notes, autorenew, privacy, creation_type_id, created_by, insert_time)
                    VALUES
                    (:owner_id, :registrar_id, :account_id, :domain, :tld, :expiry_date, :cat_id, :dns_id, :ip_id,
                     0, :notes, :autorenew, :privacy, :creation_type_id, :created_by, :insert_time)");
            }
            $stmt->bindParam('owner_id', $bind_owner_id, \PDO::PARAM_INT);
            $stmt->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('account_id', $bind_account_id, \PDO::PARAM_INT);
            $stmt->bindParam('domain', $bind_domain, \PDO::PARAM_STR);
            $stmt->bindParam('tld', $bind_tld, \PDO::PARAM_STR);
            $stmt->bindParam('expiry_date', $bind_expiry_date, \PDO::PARAM_STR);
            $stmt->bindParam('cat_id', $bind_cat_id, \PDO::PARAM_INT);
            $stmt->bindParam('dns_id', $bind_dns_id, \PDO::PARAM_INT);
            $stmt->bindParam('ip_id', $bind_ip_id, \PDO::PARAM_INT);
            $bind_temp_notes = $this->time->timeBasic() . " - Inserted by Queue.";
            $stmt->bindValue('notes', $bind_temp_notes, \PDO::PARAM_LOB);
            $stmt->bindParam('autorenew', $bind_autorenew, \PDO::PARAM_INT);
            $stmt->bindParam('privacy', $bind_privacy, \PDO::PARAM_INT);
            $bind_creation_type_id = $this->system->getCreationTypeId('Queue');
            $stmt->bindValue('creation_type_id', $bind_creation_type_id, \PDO::PARAM_INT);
            $stmt->bindParam('created_by', $bind_created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);

            $stmt2 = $pdo->prepare("
                INSERT INTO domain_field_data
                (domain_id, insert_time)
                VALUES
                (:domain_id, :insert_time)");
            $stmt2->bindParam('domain_id', $new_domain_id, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt2->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);

            foreach ($result as $row) {

                /*
                 * The import is recorded in the Change History, in the caller's transaction (one per domain): a create
                 * row for the new domain, and a cost row for each domain with the same registrar and TLD whose cost
                 * updateDomainFee() recalculates, captured and locked before the insert
                 */
                $history_op = (new ChangeHistory($pdo))->start(ChangeHistory::SOURCE_QUEUE, (int) $row->created_by);
                $fee_peers = $this->maint->captureFeePeers($history_op, (int) $row->registrar_id, (string) $row->tld);

                $bind_owner_id = $row->owner_id;
                $bind_registrar_id = $row->registrar_id;
                $bind_account_id = $row->account_id;
                $bind_domain = $row->domain;
                $bind_tld = $row->tld;
                $bind_expiry_date = $row->expiry_date;
                $bind_cat_id = $row->cat_id;
                $bind_dns_id = $row->dns_id;
                $bind_ip_id = $row->ip_id;
                $bind_autorenew = $row->autorenew;
                $bind_privacy = $row->privacy;
                $bind_created_by = $row->created_by;
                $stmt->execute();

                $new_domain_id = $pdo->lastInsertId('id');
                $history_op->created('domain', $new_domain_id);

                $stmt2->execute();

                // update domain's fees
                $this->maint->updateDomainFee($new_domain_id, $fee_peers);
                $history_op->finish();

                $sql = $this->queryB->missingFees('domains');
                $_SESSION['s_missing_domain_fees'] = $this->system->checkForRows($sql);

            }

            return $new_domain_id;
        }
    }

    // The ID of a domain in the main domain table, or false if it isn't there
    public function getDomainIdByName($domain)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT id
            FROM domains
            WHERE domain = :domain
            LIMIT 1");
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $result ? (int) $result : false;
    }

    // Finishes a queued domain that's already in the main domain table, the same way as one found when it was queued
    public function markAlreadyInDomains($queue_domain_id, $domain_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            UPDATE domain_queue
            SET domain_id = :domain_id,
                processing = '0',
                ready_to_import = '1',
                finished = '1',
                already_in_domains = '1'
            WHERE id = :queue_domain_id");
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updateNewDomainId($queue_domain_id, $new_domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET domain_id = :new_domain_id
            WHERE id = :queue_domain_id");
        $stmt->bindValue('new_domain_id', $new_domain_id, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markFinishedList($list_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue_list
            SET processing = '0',
                ready_to_import = '2',
                finished = '1'
            WHERE id = :list_id");
        $stmt->bindValue('list_id', $list_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markFinishedDomain($queue_domain_id, $domain, $expiration_date, $dns_id, $ip_id, $cat_id, $privacy_status, $autorenew_status)
    {
        $pdo = $this->deeb->cnxx;

        $creation_type_id = $this->system->getCreationTypeId('Queue');

        // confirm that the domain was successfully imported into the main database before marking it as finished
        $stmt = $pdo->prepare("
            SELECT id
            FROM domains
            WHERE domain = :domain
              AND expiry_date = :expiration_date
              AND dns_id = :dns_id
              AND ip_id = :ip_id
              AND cat_id = :cat_id
              AND privacy = :privacy_status
              AND autorenew = :autorenew_status
              AND creation_type_id = :creation_type_id
              AND active = '1'");
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->bindValue('expiration_date', $expiration_date, \PDO::PARAM_STR);
        $stmt->bindValue('dns_id', $dns_id, \PDO::PARAM_INT);
        $stmt->bindValue('ip_id', $ip_id, \PDO::PARAM_INT);
        $stmt->bindValue('cat_id', $cat_id, \PDO::PARAM_INT);
        $stmt->bindValue('privacy_status', $privacy_status, \PDO::PARAM_INT);
        $stmt->bindValue('autorenew_status', $autorenew_status, \PDO::PARAM_INT);
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to mark queue domain as finished';
            $log_extra = array('Queue Domain ID' => $queue_domain_id, 'Domain' => $domain, 'Expiry Date' =>
                $expiration_date, 'DNS ID' => $dns_id, 'IP ID' => $ip_id, 'Category ID' => $cat_id, 'Privacy Status' =>
                $privacy_status, 'Autorenew Status' => $autorenew_status,
                'Creation Type ID' => $creation_type_id);
            $this->log->critical($log_message, $log_extra);

        } else {

            $stmt = $pdo->prepare("
                UPDATE domain_queue
                SET processing = '0',
                    ready_to_import = '2',
                    finished = '1'
                WHERE id = :queue_domain_id");
            $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
            $stmt->execute();

        }
    }

    public function markNotProcessingList($list_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue_list
            SET processing = '0'
            WHERE id = :list_id");
        $stmt->bindValue('list_id', $list_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function markNotProcessingDomain($queue_domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET processing = '0'
            WHERE id = :queue_domain_id");
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function markInvalidDomain($queue_domain_id, $message = '')
    {
        $pdo = $this->deeb->cnxx;

        // RDAP rows also record why they failed (see getRdapMessage())
        if ($message !== '') {

            $stmt = $pdo->prepare("
                UPDATE domain_queue
                SET finished = '1',
                    invalid_domain = '1',
                    processing = '0',
                    message = :message
                WHERE id = :queue_domain_id");
            $stmt->bindValue('message', $message, \PDO::PARAM_STR);
            $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
            $stmt->execute();
            return;

        }

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET finished = '1',
                invalid_domain = '1'
            WHERE id = :queue_domain_id");
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function copyToHistoryList()
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT api_registrar_id, domain_count, owner_id, registrar_id, account_id, created_by, insert_time
            FROM domain_queue_list
            WHERE finished = '1'
              AND copied_to_history = '0'
            ORDER BY insert_time ASC")->fetchAll();

        if (!$result) {

            $log_message = 'No Domain Queue List results to copy to history table';
            $this->log->info($log_message);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO domain_queue_list_history
                (api_registrar_id, domain_count, owner_id, registrar_id, account_id, created_by, insert_time)
                VALUES
                (:api_registrar_id, :domain_count, :owner_id, :registrar_id, :account_id, :created_by,
                 :insert_time)");
            $stmt->bindParam('api_registrar_id', $bind_api_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('domain_count', $bind_domain_count, \PDO::PARAM_INT);
            $stmt->bindParam('owner_id', $bind_owner_id, \PDO::PARAM_INT);
            $stmt->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('account_id', $bind_account_id, \PDO::PARAM_INT);
            $stmt->bindParam('created_by', $bind_created_by, \PDO::PARAM_INT);
            $stmt->bindParam('insert_time', $bind_insert_time, \PDO::PARAM_STR);

            foreach ($result as $row) {

                $bind_api_registrar_id = $row->api_registrar_id;
                $bind_domain_count = $row->domain_count;
                $bind_owner_id = $row->owner_id;
                $bind_registrar_id = $row->registrar_id;
                $bind_account_id = $row->account_id;
                $bind_created_by = $row->created_by;
                $bind_insert_time = $row->insert_time;
                $stmt->execute();

            }

            $this->deeb->cnxx->query("
                UPDATE domain_queue_list
                SET copied_to_history = '1'
                WHERE finished = '1'
                  AND copied_to_history = '0'");

        }
    }

    public function copyToHistoryDomain()
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT id, api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id,
                dns_id, ip_id, autorenew, privacy, already_in_domains, already_in_queue, invalid_domain,
                message, created_by, insert_time
            FROM domain_queue
            WHERE finished = '1'
              AND copied_to_history = '0'
            ORDER BY insert_time ASC")->fetchAll();

        if (!$result) {

            $log_message = 'No Domain Queue results to copy to history table';
            $this->log->info($log_message);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO domain_queue_history
                (api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id,
                 dns_id, ip_id, autorenew, privacy, already_in_domains, already_in_queue, invalid_domain,
                 message, created_by, insert_time)
                VALUES
                (:api_registrar_id, :domain_id, :owner_id, :registrar_id, :account_id, :domain, :tld, :expiry_date,
                 :cat_id, :dns_id, :ip_id, :autorenew, :privacy, :already_in_domains,
                 :already_in_queue, :invalid_domain, :message, :created_by, :insert_time)");
            $stmt->bindParam('api_registrar_id', $bind_api_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('domain_id', $bind_domain_id, \PDO::PARAM_INT);
            $stmt->bindParam('owner_id', $bind_owner_id, \PDO::PARAM_INT);
            $stmt->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('account_id', $bind_account_id, \PDO::PARAM_INT);
            $stmt->bindParam('domain', $bind_domain, \PDO::PARAM_STR);
            $stmt->bindParam('tld', $bind_tld, \PDO::PARAM_STR);
            $stmt->bindParam('expiry_date', $bind_expiry_date, \PDO::PARAM_STR);
            $stmt->bindParam('cat_id', $bind_cat_id, \PDO::PARAM_INT);
            $stmt->bindParam('dns_id', $bind_dns_id, \PDO::PARAM_INT);
            $stmt->bindParam('ip_id', $bind_ip_id, \PDO::PARAM_INT);
            $stmt->bindParam('autorenew', $bind_autorenew, \PDO::PARAM_INT);
            $stmt->bindParam('privacy', $bind_privacy, \PDO::PARAM_INT);
            $stmt->bindParam('already_in_domains', $bind_already_in_domains, \PDO::PARAM_INT);
            $stmt->bindParam('already_in_queue', $bind_already_in_queue, \PDO::PARAM_INT);
            $stmt->bindParam('invalid_domain', $bind_invalid_domain, \PDO::PARAM_INT);
            $stmt->bindParam('message', $bind_message, \PDO::PARAM_STR);
            $stmt->bindParam('created_by', $bind_created_by, \PDO::PARAM_INT);
            $stmt->bindParam('insert_time', $bind_insert_time, \PDO::PARAM_STR);

            // each row is marked copied only when this run copies it (and in the same transaction), so overlapping
            // queue runs can neither skip a row that finished after the SELECT nor copy a row twice
            $stmt2 = $pdo->prepare("
                UPDATE domain_queue
                SET copied_to_history = '1'
                WHERE id = :queue_domain_id
                  AND copied_to_history = '0'");

            foreach ($result as $row) {

                try {

                    $pdo->beginTransaction();
                    $stmt2->bindValue('queue_domain_id', $row->id, \PDO::PARAM_INT);
                    $stmt2->execute();

                    if ($stmt2->rowCount() !== 1) {

                        $pdo->rollBack();
                        continue;

                    }

                    /*
                     * Read the row again now that it's claimed (and locked), so a fix by hand (queue/edit/) that
                     * committed after the list above was read is copied as it is now, not as it was
                     */
                    $stmt3 = $pdo->prepare("
                        SELECT api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, expiry_date,
                            cat_id, dns_id, ip_id, autorenew, privacy, already_in_domains, already_in_queue,
                            invalid_domain, message, created_by, insert_time
                        FROM domain_queue
                        WHERE id = :queue_domain_id
                        FOR UPDATE");
                    $stmt3->bindValue('queue_domain_id', $row->id, \PDO::PARAM_INT);
                    $stmt3->execute();
                    $current_row = $stmt3->fetch();
                    $stmt3->closeCursor();

                    if ($current_row) $row = (object) array_merge((array) $row, (array) $current_row);

                    $bind_api_registrar_id = $row->api_registrar_id;
                    $bind_domain_id = $row->domain_id;
                    $bind_owner_id = $row->owner_id;
                    $bind_registrar_id = $row->registrar_id;
                    $bind_account_id = $row->account_id;
                    $bind_domain = $row->domain;
                    $bind_tld = $row->tld;
                    $bind_expiry_date = $row->expiry_date;
                    $bind_cat_id = $row->cat_id;
                    $bind_dns_id = $row->dns_id;
                    $bind_ip_id = $row->ip_id;
                    $bind_autorenew = $row->autorenew;
                    $bind_privacy = $row->privacy;
                    $bind_already_in_domains = $row->already_in_domains;
                    $bind_already_in_queue = $row->already_in_queue;
                    $bind_invalid_domain = $row->invalid_domain;
                    $bind_message = $row->message;
                    $bind_created_by = $row->created_by;
                    $bind_insert_time = $row->insert_time;
                    $stmt->execute();
                    $pdo->commit();

                } catch (\Throwable $e) {

                    try {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                    } catch (\Throwable $rollback_error) {
                    }

                    if (function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e)) self::$had_conflict = true;
                    $log_message = 'Unable to copy a Domain Queue result to the history table';
                    $log_extra = array('Domain' => $row->domain, 'Error' => $e->getMessage());
                    $this->log->error($log_message, $log_extra);

                }

            }

        }
    }

    public function clearFinished()
    {
        $pdo = $this->deeb->cnxx;

        // finished domains are only removed once they're in the history, so copy any still waiting first (a domain fixed
        // and imported by hand in queue/edit/ waits for its copy until the queue next runs)
        $this->copyToHistoryDomain();

        $pdo->query("
            DELETE FROM domain_queue_list
            WHERE finished = '1'
              AND copied_to_history = '1'");

        $this->checkListQueue();

        $pdo->query("
            DELETE FROM domain_queue
            WHERE finished = '1'
              AND copied_to_history = '1'");

        $this->checkDomainQueue();
    }

    /*
     * Maintenance's Clear Queue Processing. It takes the Domain Queue Processing task's lock first, as Run Now does, so it
     * can't clear the flags of a run that's still going, and returns false without clearing anything while a run holds
     * the lock.
     */
    public function clearProcessing()
    {
        $task_id = $this->getTaskId();

        if ($task_id && !$this->schedule->getTaskLock($task_id)) return false;

        try {

            $this->releaseListProcessing();
            $this->releaseDomainProcessing();

        } finally {

            if ($task_id) $this->schedule->releaseTaskLock($task_id);

        }

        return _('Queue Processing Cleared') . '<BR>';
    }

    // The ID of the Domain Queue Processing task, or 0 if there isn't one (then nothing runs the queue)
    public function getTaskId()
    {
        return (int) $this->deeb->cnxx->query("
            SELECT id
            FROM scheduler
            WHERE slug = 'domain-queue'
            LIMIT 1")->fetchColumn();
    }

    /*
     * Clears every list's processing flag. Only a queue run flags lists, and it only runs while it holds the Domain Queue
     * Processing task's lock (cron.php and the Task Scheduler's Run Now, and Clear Queue Processing takes it too), so
     * when a run starts, any list still flagged was left by a run that was killed part-way (a time limit or a crash),
     * and it would otherwise never be processed again.
     */
    public function releaseListProcessing()
    {
        $this->deeb->cnxx->query("
            UPDATE domain_queue_list
            SET processing = '0'
            WHERE processing = '1'");
    }

    /*
     * Clears every domain's processing flag, for the same reason (see releaseListProcessing()). An unfinished domain whose
     * processing flag is cleared also loses its ready_to_import mark. A run marks a domain ready only in the transaction
     * that imports it, so an unfinished domain marked ready was left by an older version's run that was killed between
     * the two, or its import couldn't be confirmed. It's looked up again, and a domain that did reach the main domain
     * table is then marked as already there.
     */
    public function releaseDomainProcessing()
    {
        $this->deeb->cnxx->query("
            UPDATE domain_queue
            SET ready_to_import = IF(finished = '0', '0', ready_to_import),
                processing = '0'
            WHERE processing = '1'");
    }

    public function clearQueues()
    {
        $pdo = $this->deeb->cnxx;

        $pdo->query("DELETE FROM domain_queue_list");

        $pdo->query("DELETE FROM domain_queue");

        return _('Queues Cleared') . '<BR>';
    }
    
    public function checkListQueue()
    {
        $result =  $this->deeb->cnxx->query("
            SELECT id
            FROM domain_queue_list
            LIMIT 1")->fetchColumn();

        if (!$result) {

            unset($_SESSION['s_domains_in_list_queue']);
            return '0';

        } else {

            $_SESSION['s_domains_in_list_queue'] = '1';
            return '1';

        }
    }

    public function checkDomainQueue()
    {
        $result = $this->deeb->cnxx->query("
            SELECT id
            FROM domain_queue
            LIMIT 1")->fetchColumn();

        if (!$result) {

            unset($_SESSION['s_domains_in_queue']);
            return '0';

        } else {

            $_SESSION['s_domains_in_queue'] = '1';
            return '1';

        }
    }

    /*
     * The Domain Queue page's notice while the queue is processing. With htmx it asks queue/processing.php every 5
     * seconds whether the queue is still processing, and that page reloads the queue page once it has finished. Its
     * fixed id lets htmx keep the keyboard focus on it when a check replaces it.
     */
    public function processingNotice()
    {
        ob_start(); ?>
        <button type="button" id="domainmod-queue-processing" class="btn btn-secondary btn-lg" hx-get="processing.php" hx-trigger="every 5s" hx-swap="outerHTML">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>&nbsp;&nbsp;&nbsp;<?php echo _('The Queue Is Currently Processing'); ?>
        </button><?php
        return ob_get_clean();
    }

    public function checkProcessingLists()
    {
        $result = $this->deeb->cnxx->query("
            SELECT id
            FROM domain_queue_list
            WHERE processing = '1'
            LIMIT 1")->fetchColumn();

        if (!$result) {

            unset($_SESSION['s_list_queue_processing']);
            return '0';

        } else {

            $_SESSION['s_list_queue_processing'] = '1';
            return '1';

        }
    }

    public function checkProcessingDomains()
    {
        $result = $this->deeb->cnxx->query("
            SELECT id
            FROM domain_queue
            WHERE processing = '1'
            LIMIT 1")->fetchColumn();

        if (!$result) {

            unset($_SESSION['s_domain_queue_processing']);
            return '0';

        } else {

            $_SESSION['s_domain_queue_processing'] = '1';
            return '1';

        }
    }

} //@formatter:on
