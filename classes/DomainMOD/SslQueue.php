<?php
/**
 * /classes/DomainMOD/SslQueue.php
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
 * The SSL Queue: hostnames whose certificates are read in the background (by the ssl-queue scheduled task) and added
 * as SSL certificates. Its tables come with the 4.27.0 database update, so nothing here uses them before then.
 *
 * Rows are added by addHosts() (the paste page, and the domain paste pages' tick box), released or failed while they
 * wait for their domain by releaseWaiting(), and looked up and imported by process(). Adding, releasing, claiming, and
 * saving all hold the SSL Queue's named lock, and a whole run holds its own run lock, so two runs never overlap.
 */
class SslQueue
{
    public $deeb;
    public $log;
    public $sanitize;
    public $system;
    public $time;

    // The database version that adds the SSL Queue's tables
    const AVAILABLE_FROM = '4.27.0';

    const MAX_HOSTS = 1000;        // distinct hostnames per submission
    const MAX_WAITING = 10000;     // unfinished rows the whole queue holds
    const CANDIDATES = 500;        // rows read per run
    const BATCH = 50;              // rows looked up per run
    const BUDGET = 120;            // seconds a run may spend on lookups
    const SSL_QUEUE_NEEDS = 20;    // seconds it needs left in the request on its turn (see Scheduler::turn())
    const MAX_ATTEMPTS = 3;        // retryable failures before a row fails
    const CLAIM_TIMEOUT = 900;     // seconds after which a claim is one a stopped run left behind
    const WAIT_DAYS = 7;           // days a row waits for its domain to be imported
    const MAX_NAME_LENGTH = 100;   // characters ssl_certs.name holds
    const LOCK_WAIT = 10;          // seconds to wait for the SSL Queue's named lock

    // The result codes that are retried, and the ones that fail a row straight away
    public static $retry_codes = array('timeout', 'save_failed', 'local_error');
    public static $failure_codes = array('no_dns', 'not_public', 'no_connect', 'no_tls', 'name_mismatch', 'not_yet_valid',
        'bad_certificate', 'stale_choice', 'name_too_long');

    // Whether a save in this run met another request's write (errors 1020 and 1213, see hadConflict())
    protected $had_conflict = false;

    /*
     * Test-only hooks, null outside tests:
     * - $test_trace: a callable($event) that process() calls at its start ('start'), when it returns early before the
     *   database update ('unavailable'), and when another run holds the run lock ('locked').
     * - $test_add_failure: when true, addHosts() throws inside its transaction after writing its first row, so the
     *   rollback can be tested.
     * - $test_before_save: a callable($stage, $row) called with 'claim_read' in prepareClaimed() between its first read
     *   of domains and its Domain Queue check, with 'read' in import() just after the read of the certificates with no
     *   domain (before the transaction), and with 'locked' in importLocked() once the candidates are locked, so a test
     *   can change the data between the reads and the locks, or check the locks while the transaction is open.
     */
    public static $test_trace = null;
    public static $test_add_failure = null;
    public static $test_before_save = null;

    protected $queue_locked = false;
    protected $queued_domains = null; // the Domain Queue's unfinished, valid domains, read once per add or waiting check
    protected $ascii_names = array(); // each certificate name's asciiHostname(), by the name's stored text, for the run

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.sslqueue');
        $this->sanitize = new Sanitize();
        $this->system = new System();
        $this->time = new Time();
    }

    // Whether the database has been updated to a version with the SSL Queue's tables
    public function isAvailable()
    {
        return version_compare((string) $this->system->getDbVersion(), self::AVAILABLE_FROM, '>=');
    }

    /*
     * Sets the s_ssl_in_queue session flag while the queue has any rows (it shows the Import Queue menu item to read-only
     * users), and returns '1' or '0', as DomainQueue::checkDomainQueue() does.
     */
    public function checkSslQueue()
    {
        $result = $this->isAvailable() ? $this->deeb->cnxx->query("
            SELECT id
            FROM ssl_queue
            LIMIT 1")->fetchColumn() : false;

        if (!$result) {

            unset($_SESSION['s_ssl_in_queue']);
            return '0';

        }

        $_SESSION['s_ssl_in_queue'] = '1';
        return '1';
    }

    /*
     * Whether the menu shows the Import Queue: only while either queue has rows, going by the session flags the queues
     * set (s_domains_in_list_queue, s_domains_in_queue, and s_ssl_in_queue). Users reach the add pages from the Domains
     * and SSL Certificates lists and the Dashboard.
     */
    public static function inMenu($session)
    {
        foreach (array('s_domains_in_list_queue', 's_domains_in_queue', 's_ssl_in_queue') as $flag) {
            if (($session[$flag] ?? '') == '1') return true;
        }

        return false;
    }

    /*
     * The message for a result code, translated. An unknown code gives a general failure message, never the code.
     */
    public function getMessage($code)
    {
        if ($code === '' || $code === 'ok') {
            $message = '';
        } elseif ($code === 'untrusted') {
            $message = _("Imported, but the certificate isn't trusted.");
        } elseif ($code === 'expired') {
            $message = _('Imported, but the certificate has expired.');
        } elseif ($code === 'name_mismatch') {
            $message = _("The site's certificate doesn't cover this hostname (it's often the hosting company's shared certificate).");
        } elseif ($code === 'not_yet_valid') {
            $message = _("This certificate isn't valid yet. Retry it after its start date.");
        } elseif ($code === 'no_dns') {
            $message = _("This hostname doesn't resolve.");
        } elseif ($code === 'not_public') {
            $message = _("This hostname resolves to an address that isn't public, so it can't be checked.");
        } elseif ($code === 'no_connect') {
            $message = _('Nothing answered on port 443.');
        } elseif ($code === 'no_tls') {
            $message = _("The site answered on port 443, but not with TLS.");
        } elseif ($code === 'timeout') {
            $message = _('The lookup ran out of time.');
        } elseif ($code === 'stale_choice') {
            $message = _('Something chosen when this hostname was queued has changed or been deleted. Delete this queue entry and add the hostname again.');
        } elseif ($code === 'local_error') {
            $message = _("DomainMOD couldn't write a temporary file to check this certificate. Check that PHP's temporary folder is writable.");
        } elseif ($code === 'name_too_long') {
            $message = _('This hostname is longer than the 100 characters an SSL certificate\'s Host / Label can hold.');
        } elseif ($code === 'bad_certificate') {
            $message = _("A date in this certificate can't be stored.");
        } elseif ($code === 'save_failed') {
            $message = _("The certificate couldn't be saved.");
        } else {
            $message = _('The lookup failed.');
        }

        return $message;
    }

    /*
     * The columns to write to a claimed row after a lookup result that isn't a success: a pacing deferral puts it back
     * as it was before the claim (attempts, message, and the claim's last_attempt kept), domain_changed (its domain was
     * deleted or renamed during the lookup) puts it back the same way without its domain, so its next claim finds its
     * domain again, a retryable code counts an attempt and fails the row on the third, and any other code fails it
     * straight away. Every result clears the claim.
     */
    public static function afterResult($row, $code)
    {
        if ($code === 'deferred') return array('processing' => 0);
        if ($code === 'domain_changed') return array('processing' => 0, 'domain_id' => 0, 'domain_name' => '');

        if (in_array($code, self::$retry_codes, true)) {

            $attempts = min(255, (int) $row->attempts + 1);
            if ($attempts < self::MAX_ATTEMPTS) return array('processing' => 0, 'attempts' => $attempts, 'message' => $code);

            return array('processing' => 0, 'attempts' => $attempts, 'message' => $code, 'finished' => 1, 'failed' => 1,
                'wait_for_domain' => 0);

        }

        return array('processing' => 0, 'message' => $code, 'finished' => 1, 'failed' => 1, 'wait_for_domain' => 0);
    }

    // The columns written to a row a stopped run left claimed: a timeout, so one attempt is counted and a row that stops
    // the run every time fails after MAX_ATTEMPTS
    public static function afterCrash($row)
    {
        return self::afterResult($row, 'timeout');
    }

    // The columns Retry Failed writes, so the row is Pending again
    public static function afterRetry($row)
    {
        return array('processing' => 0, 'finished' => 0, 'failed' => 0, 'attempts' => 0, 'message' => '',
            'wait_for_domain' => 0);
    }

    /*
     * A row's state from its flags: pending, waiting, processing, retrying, imported, already_in_queue, already_in_ssl,
     * or failed (see the plan's row state table).
     */
    public static function rowState($row)
    {
        if ((int) $row->finished === 1) {

            if ((int) $row->failed === 1) return 'failed';
            if ((int) $row->already_in_queue === 1) return 'already_in_queue';
            if ((int) $row->already_in_ssl === 1) return 'already_in_ssl';
            return 'imported';

        }

        if ((int) $row->processing === 1) return 'processing';
        if ((int) $row->wait_for_domain === 1) return 'waiting';
        if ((int) $row->attempts > 0) return 'retrying';

        return 'pending';
    }

    /*
     * Whether a row has already waited for its domain and been released, so it never waits again: a released row keeps
     * the wait_since its wait started with, while a row that never waited has the column's default.
     */
    public static function waitDone($row)
    {
        return (int) $row->wait_for_domain === 0 && (string) $row->wait_since !== '1970-01-01 00:00:00';
    }

    /*
     * The domain the Import Queue page and the export show for a row: the domain it's linked to, or the domain it's
     * waiting for while it waits, or '' (a row that went ahead without a domain).
     */
    public static function domainShown($row)
    {
        if ((string) $row->domain_name !== '') return (string) $row->domain_name;

        return self::rowState($row) === 'waiting' ? (string) $row->wait_domain : '';
    }

    /*
     * What to do with a row's domain, from the first read of domains ($status: 'one', 'many', or 'none'), the Domain
     * Queue's domain for it ($queued, or ''), whether it has already waited ($wait_done), and, once that's been asked
     * for, the second read's $second status. Returns 'link' (the one domain found), 'no_domain' (go ahead without one),
     * 'wait' (wait for the Domain Queue's domain), or 'recheck' (read domains once more, since the Domain Queue may have
     * imported the domain and finished its row between the reads). More than one record never waits: it goes ahead
     * without a domain straight away.
     */
    public static function claimAction($status, $queued, $wait_done, $second = null)
    {
        if ($status === 'one') return 'link';
        if ($status === 'many') return 'no_domain';
        if ($queued !== '' && !$wait_done) return 'wait';
        if ($second === null) return 'recheck';

        return ($second === 'one') ? 'link' : 'no_domain';
    }

    // What to do with a pasted host's domain (see claimAction()): a newly pasted row has never waited
    public static function pastedAction($status, $queued, $second = null)
    {
        return self::claimAction($status, $queued, false, $second);
    }

    /*
     * The columns that release a waiting row, from the read of domains ($found: array(status, id, name)), whether its
     * domain is still in the Domain Queue ($still_queued), and whether its WAIT_DAYS are up ($expired), or null when it
     * keeps waiting. It's released linked to the one domain found, and otherwise without a domain, and its wait_since is
     * kept, so it never waits again (see waitDone()).
     */
    public static function releaseValues($found, $still_queued, $expired)
    {
        list($status, $domain_id, $domain_name) = $found;

        if ($status === 'one') return array('wait_for_domain' => 0, 'domain_id' => (int) $domain_id, 'domain_name' => (string) $domain_name);
        if ($status === 'none' && $still_queued && !$expired) return null;

        return array('wait_for_domain' => 0, 'domain_id' => 0, 'domain_name' => '');
    }

    /*
     * The longest of $domain_names (stored forms) that is the host itself or a parent of it at a label boundary, or ''.
     * Names are compared in their ASCII (punycode) form, so a host stored in punycode matches a parent stored in Unicode.
     */
    public static function bestMatch($host, $domain_names)
    {
        $ascii_host = strtolower(Domain::forApi($host));
        $best = '';
        $best_length = 0;

        foreach ($domain_names as $name) {

            $ascii_name = strtolower(Domain::forApi($name));
            if ($ascii_name === '') continue;

            if (($ascii_host === $ascii_name || str_ends_with($ascii_host, '.' . $ascii_name)) && strlen($ascii_name) > $best_length) {

                $best = (string) $name;
                $best_length = strlen($ascii_name);

            }

        }

        return $best;
    }

    /*
     * The stored forms of the host and each parent of it at a label boundary (with at least two labels), as every name
     * in domains and domain_queue was stored, for the database to match with domain IN (...).
     */
    public static function storedSuffixes($host)
    {
        $labels = explode('.', strtolower(Domain::forApi($host)));
        $forms = array();

        for ($i = 0; $i < count($labels) - 1; $i++) {

            $form = Domain::normalize(implode('.', array_slice($labels, $i)));
            if ($form !== '' && !in_array($form, $forms, true)) $forms[] = $form;

        }

        return $forms;
    }

    /*
     * Adds hostnames (in their stored form) to the queue in one transaction, under the SSL Queue's named lock, and
     * returns 'added', 'full' (the backlog cap stopped the new unfinished rows), or 'failed' (an error, logged, with
     * nothing changed). It never redirects or prints anything.
     *
     * $choices holds owner_id, account_id (0 to match each certificate's issuer), type_id, cat_id, and ip_id. With a
     * chosen account, the owner and provider are the account's. $exact is true for the domain paste pages' tick box:
     * each new row then waits for its own domain to be imported.
     */
    public function addHosts($hosts, $choices, $exact, $created_by)
    {
        $pdo = $this->deeb->cnxx;
        $hosts = array_values(array_unique(array_filter(array_map('strval', $hosts), 'strlen')));
        if (!$hosts) return 'added';
        $this->queued_domains = null;

        try {

            $this->getQueueLock();
            $pdo->beginTransaction();

            $choices = $this->resolveChoices($choices);
            if ($choices === null) throw new \RuntimeException('The chosen SSL provider account no longer exists');

            $now = $this->time->stamp();
            $actions = array();
            $new_rows = 0;

            foreach ($hosts as $host) {

                $action = $exact ? $this->planExact($host) : $this->planPasted($host);
                $actions[] = array($host, $action);
                if ($action[0] === 'pending' || $action[0] === 'waiting' || $action[0] === 'new_exact') $new_rows++;

            }

            $unfinished = (int) $pdo->query("
                SELECT COUNT(*)
                FROM ssl_queue
                WHERE finished = '0'")->fetchColumn();

            $full = $new_rows > 0 && $unfinished + $new_rows > self::MAX_WAITING;

            if ($full && !$exact) {

                $pdo->rollback();
                $this->releaseQueueLock();
                return 'full';

            }

            $written = 0;

            foreach ($actions as $entry) {

                list($host, $action) = $entry;
                $kind = $action[0];

                if ($full && in_array($kind, array('pending', 'waiting', 'new_exact'), true)) continue;

                if ($kind === 'upgrade') {

                    // applied only when the row is still unclaimed and unfinished, otherwise a new exact-domain row
                    if (!$this->upgradeRow($action[1], $host, $choices, $now)) {

                        if ($full) continue;
                        $kind = 'new_exact';

                    } else {

                        $written++;
                        $this->testFailure($written);
                        continue;

                    }

                }

                $row = array('host' => $host, 'created_by' => (int) $created_by, 'insert_time' => $now) + $choices;

                if ($kind === 'already_in_queue') {
                    $row += array('finished' => 1, 'already_in_queue' => 1);
                } elseif ($kind === 'pending') {
                    $row += array('domain_id' => $action[1], 'domain_name' => $action[2]);
                } elseif ($kind === 'waiting') {
                    $row += array('wait_for_domain' => 1, 'wait_domain' => $action[1], 'wait_since' => $now);
                } else {
                    $row += array('wait_for_domain' => 1, 'wait_domain' => $host, 'wait_since' => $now, 'exact_domain' => 1);
                }

                $this->insertRow($row);
                $written++;
                $this->testFailure($written);

            }

            $pdo->commit();

        } catch (\Throwable $e) {

            $this->cleanUp();
            $this->logError('Unable to add hostnames to the SSL Queue', count($hosts) . ' hostnames', $e);

            return 'failed';

        }

        // committed, so a failure from here on doesn't make the submission fail
        $this->cleanUp();

        // the hosts are queued by now, so a problem setting the menu's flag doesn't make the submission fail
        try {

            $this->checkSslQueue();

        } catch (\Throwable $e) {

            $this->logError('Unable to check whether the SSL Queue has rows', '', $e);

        }

        return $full ? 'full' : 'added';
    }

    /*
     * The SSL paste page's list: array(hosts, invalid lines). The text is split into lines, blank and whitespace-only
     * lines are skipped (each other line keeps its own line number, from 1, for the invalid-line messages), every
     * remaining line is checked with SslLookup::cleanHost(), and only then are duplicate hosts dropped, keeping the first.
     * The hosts are in their stored form, in the order pasted. A paste with only blank lines gives two empty arrays.
     */
    public static function parseHostList($text)
    {
        $lookup = new SslLookup();
        $hosts = array();
        $invalid = array();
        $seen = array(); // the hosts so far, as keys, so a long paste isn't searched once per line

        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $index => $line) {

            // whitespace of any kind, such as a no-break space, makes a blank line too. A line that isn't valid UTF-8
            // (preg_match() gives false) is invalid, whatever part of it the host is taken from, and so is one with
            // U+FFFD, which the page shows in place of such bytes (and a browser shows in place of a NUL, which is
            // refused for the same reason), so resubmitting it unchanged is refused the same way.
            $blank = preg_match('/^[\s\p{Z}]*$/u', $line);
            if ($blank === 1) continue;

            $host = ($blank === false || strpos($line, "\u{FFFD}") !== false || strpos($line, "\0") !== false) ? '' : $lookup->cleanHost($line);

            if ($host === '') {

                $invalid[] = $index + 1;

            } elseif (!isset($seen[$host])) {

                $seen[$host] = true;
                $hosts[] = $host;

            }

        }

        return array($hosts, $invalid);
    }

    /*
     * The domain paste pages' tick box: queues an SSL certificate lookup for each pasted domain, after the page's own
     * transaction has committed, so a problem here can never undo or block the domain import. Through addHosts()'s exact
     * mode, each new row (or pasted row it upgrades) waits for its own domain to be imported, with the domain page's
     * owner, issuer matching, and the user's SSL defaults, and a host that already has an unfinished exact-domain row
     * gets a finished "Already in the queue" row instead. Returns
     * array(text to add to the success message, warning), each '' or translated HTML ending with <BR>. It never throws,
     * and a list of more than MAX_HOSTS distinct domains adds no rows.
     */
    public function queueDomainLookups($domains, $owner_id, $created_by)
    {
        $hosts = array();
        foreach ($domains as $domain) {
            $host = Domain::normalize((string) $domain);
            if ($host !== '') $hosts[$host] = true;
        }
        $hosts = array_keys($hosts);

        if (count($hosts) > self::MAX_HOSTS) {

            // TRANSLATORS: %1$s is a number, and %2$s is the name of the "Add SSL Certificates" action.
            return array(sprintf(_('SSL certificate lookups are only added for lists of up to %1$s domains. You can add them from %2$s in parts.'),
                number_format(self::MAX_HOSTS), _('Add SSL Certificates')) . '<BR>', '');

        }

        try {

            $choices = $this->defaultChoices($owner_id);
            if ($choices === null) throw new \RuntimeException('There is no SSL certificate type, category, or IP address to use');

        } catch (\Throwable $e) {

            $this->logError('Unable to read the SSL defaults for the SSL Queue', '', $e);
            $choices = null;

        }

        $outcome = ($choices === null) ? 'failed' : $this->addHosts($hosts, $choices, true, $created_by);

        if ($outcome === 'full') {

            try {

                $waiting = $this->countUnfinished();

                return array('', sprintf(ngettext('The SSL Queue already has %s hostname waiting. Please wait for some to finish before adding more.',
                    'The SSL Queue already has %s hostnames waiting. Please wait for some to finish before adding more.', $waiting),
                    number_format($waiting)) . '<BR>');

            } catch (\Throwable $e) {

                $this->logError('Unable to count the SSL Queue\'s waiting rows', '', $e);

            }

        }

        if ($outcome !== 'added') {

            // TRANSLATORS: %s is the name of the "Add SSL Certificates" action.
            return array('', sprintf(_("The domains were added to the queue, but their SSL certificate lookups couldn't be. You can add them from %s."),
                _('Add SSL Certificates')) . '<BR>');

        }

        return array('', '');
    }

    /*
     * The tick box's choices: the domain page's owner, issuer matching (account 0), and for the type, category, and IP
     * address, the user's SSL default, then the system's, then the install's first row (the lowest id), since the
     * defaults can be 0 or point to a row that's gone. Null when one of those tables has no rows at all, since a row
     * queued with none would only fail when it's saved.
     */
    protected function defaultChoices($owner_id)
    {
        $choices = array('owner_id' => (int) $owner_id, 'account_id' => 0,
            'type_id' => $this->defaultId('ssl_cert_types', array($_SESSION['s_default_ssl_type'] ?? 0, $_SESSION['s_system_default_ssl_type'] ?? 0)),
            'cat_id' => $this->defaultId('categories', array($_SESSION['s_default_category_ssl'] ?? 0, $_SESSION['s_system_default_category_ssl'] ?? 0)),
            'ip_id' => $this->defaultId('ip_addresses', array($_SESSION['s_default_ip_address_ssl'] ?? 0, $_SESSION['s_system_default_ip_address_ssl'] ?? 0)));

        return (min($choices['type_id'], $choices['cat_id'], $choices['ip_id']) === 0) ? null : $choices;
    }

    // The first of $candidates that's a row of $table (a fixed name from this class), or the table's lowest id (0 when
    // it has none)
    protected function defaultId($table, $candidates)
    {
        foreach ($candidates as $candidate) {

            if ((int) $candidate <= 0) continue;

            $stmt = $this->deeb->cnxx->prepare("
                SELECT id
                FROM `" . $table . "`
                WHERE id = :id");
            $stmt->bindValue('id', (int) $candidate, \PDO::PARAM_INT);
            $stmt->execute();
            $found = $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($found !== false) return (int) $found;

        }

        return (int) $this->deeb->cnxx->query("
            SELECT MIN(id)
            FROM `" . $table . "`")->fetchColumn();
    }

    // The number of unfinished rows, for the cap message
    public function countUnfinished()
    {
        return (int) $this->deeb->cnxx->query("
            SELECT COUNT(*)
            FROM ssl_queue
            WHERE finished = '0'")->fetchColumn();
    }

    /*
     * The counts beside the Import Queue page's two headings: array(the Domain Queue's unfinished lists and domains, the
     * SSL Queue's unfinished rows). Before the database update, the SSL Queue's count is 0.
     */
    public function waitingCounts()
    {
        $pdo = $this->deeb->cnxx;

        $domains = (int) $pdo->query("
            SELECT COUNT(*)
            FROM domain_queue_list
            WHERE finished = '0'")->fetchColumn();

        $domains += (int) $pdo->query("
            SELECT COUNT(*)
            FROM domain_queue
            WHERE finished = '0'")->fetchColumn();

        return array($domains, $this->isAvailable() ? $this->countUnfinished() : 0);
    }

    /*
     * array(completed, total) for the status page's progress line, from rows with the state flags. Finished rows count as
     * completed, failed ones included, since some rows finish without a lookup.
     */
    public static function progressCounts($rows)
    {
        $completed = 0;
        foreach ($rows as $row) {
            if ((int) $row->finished === 1) $completed++;
        }

        return array($completed, count($rows));
    }

    /*
     * The rows the Import Queue page lists and the export writes, newest first, with the imported certificate's details
     * joined only while it still exists (a deleted one leaves the row without a link).
     */
    public function statusRows()
    {
        return $this->deeb->cnxx->query("
            SELECT q.id, q.host, q.domain_name, q.wait_domain, q.expiry_date, q.issuer, q.processing, q.finished, q.failed,
                q.already_in_ssl, q.already_in_queue, q.wait_for_domain, q.message, q.attempts, q.last_attempt, q.created_by,
                q.insert_time,
                c.id AS ssl_id, c.name, c.fee_id, f.id AS fee_row_id, p.id AS provider_id, p.name AS provider, t.id AS type_id,
                t.type, u.first_name, u.last_name
            FROM ssl_queue AS q
            LEFT JOIN ssl_certs AS c ON q.new_ssl_id > 0 AND c.id = q.new_ssl_id
            LEFT JOIN ssl_providers AS p ON c.ssl_provider_id = p.id
            LEFT JOIN ssl_cert_types AS t ON c.type_id = t.id
            LEFT JOIN ssl_fees AS f ON c.fee_id = f.id
            LEFT JOIN users AS u ON q.created_by = u.id
            ORDER BY q.insert_time DESC, q.id DESC")->fetchAll();
    }

    /*
     * A row's status as plain text pieces, array(state, label, flag, detail): its state (see rowState()), its label, the
     * "Not trusted" or "Expired" flag of an imported certificate, and the detail shown under it (a reason, or what it
     * waits for). The Import Queue page's table and the export both use it.
     */
    public function statusParts($row)
    {
        $state = self::rowState($row);
        $flag = '';
        $detail = '';

        if ($state === 'pending') {

            $label = _('Pending');

        } elseif ($state === 'waiting') {

            $label = _('Waiting for the Domain');
            // TRANSLATORS: Shown under "Waiting for the Domain" in the SSL Queue. %1$s is a domain, and %2$d is a number of days.
            $detail = sprintf(ngettext('Waiting up to %2$d day for %1$s to be added. If the wait ends first, the SSL Queue checks the site anyway and links the certificate only if a matching domain has been added by then.',
                'Waiting up to %2$d days for %1$s to be added. If the wait ends first, the SSL Queue checks the site anyway and links the certificate only if a matching domain has been added by then.',
                self::WAIT_DAYS), $row->wait_domain, self::WAIT_DAYS);

        } elseif ($state === 'processing') {

            $label = _('Processing');

        } elseif ($state === 'retrying') {

            $label = _('Retrying');
            // TRANSLATORS: Shown under "Retrying" in the SSL Queue, before the reason. %1$s and %2$s are numbers, such as "Attempt 1 of 3 failed."
            $detail = sprintf(_('Attempt %1$s of %2$s failed.'), (int) $row->attempts, self::MAX_ATTEMPTS) . ' '
                . $this->getMessage((string) $row->message);

        } elseif ($state === 'imported') {

            $label = _('Imported');
            if ($row->message === 'untrusted') $flag = _('Not trusted');
            if ($row->message === 'expired') $flag = _('Expired');

        } elseif ($state === 'already_in_queue') {

            $label = _('Already in SSL Queue');

        } elseif ($state === 'already_in_ssl') {

            $label = sprintf(_('Already in %s'), SOFTWARE_TITLE);

        } else {

            $label = _('Failed');
            $detail = $this->getMessage((string) $row->message);

        }

        return array($state, $label, $flag, $detail);
    }

    // The person who queued a row (a row with first_name and last_name), or [unknown] when they're gone
    public static function addedBy($row)
    {
        if ($row->first_name === null) return '[' . Format::toLower(_('Unknown')) . ']';
        return $row->first_name . ' ' . $row->last_name;
    }

    /*
     * Whether the queue is being processed now: the ssl-queue task is running (its running flag is set and its lock is
     * held, the same test Scheduler::clearStoppedTasks() makes, so a flag a killed run left behind doesn't count), or a
     * row was claimed within CLAIM_TIMEOUT (an older claim is one a stopped run left behind).
     */
    public function isProcessing()
    {
        $pdo = $this->deeb->cnxx;

        $task_ids = $pdo->query("
            SELECT id
            FROM scheduler
            WHERE slug = 'ssl-queue'
              AND is_running = '1'")->fetchAll(\PDO::FETCH_COLUMN);

        $scheduler = new Scheduler();

        foreach ($task_ids as $task_id) {
            if ($scheduler->isTaskLocked((int) $task_id)) return true;
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM ssl_queue
            WHERE processing = '1'
              AND last_attempt >= :cutoff
            LIMIT 1");
        $stmt->bindValue('cutoff', self::claimCutoff(time()), \PDO::PARAM_STR);
        $stmt->execute();
        $claimed = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $claimed !== false;
    }

    // The oldest claim time (Y-m-d H:i:s, UTC) that still counts as a run's, at the Unix time $now
    public static function claimCutoff($now)
    {
        return gmdate('Y-m-d H:i:s', (int) $now - self::CLAIM_TIMEOUT);
    }

    // Whether a row (with processing and last_attempt) is claimed by a run now, not left behind by a stopped one
    public static function freshClaim($row, $now)
    {
        return (int) $row->processing === 1 && (string) $row->last_attempt >= self::claimCutoff($now);
    }

    /*
     * The SSL Queue's notice on the Import Queue page while the queue is processing, built like
     * DomainQueue::processingNotice(): with htmx it asks queue/ssl/processing.php every 5 seconds, and that page reloads
     * the queue page once processing has ended. It sits beside the Domain Queue's notice, so it has its own id and asks
     * by its full path.
     */
    public function processingNotice()
    {
        ob_start(); ?>
        <button type="button" id="domainmod-ssl-queue-processing" class="btn btn-secondary btn-lg" hx-get="<?php echo htmlspecialchars(WEB_ROOT . '/queue/ssl/processing.php', ENT_QUOTES, 'UTF-8'); ?>" hx-trigger="every 5s" hx-swap="outerHTML">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>&nbsp;&nbsp;&nbsp;<?php echo _('The Queue Is Currently Processing'); ?>
        </button><?php
        return ob_get_clean();
    }

    /*
     * Clear Completed: moves the finished rows (failed ones included) to ssl_queue_history, under the SSL Queue's named
     * lock and in one transaction. The rows are chosen once (locked), and exactly those are copied and deleted, in
     * batches of 500 ids, so a row a run finishes meanwhile is left for the next clear. Returns the number cleared, or
     * false on an error (logged, with nothing changed).
     */
    public function clearFinished()
    {
        $pdo = $this->deeb->cnxx;
        $columns = '`host`, `domain_id`, `domain_name`, `owner_id`, `provider_id`, `account_id`, `type_id`, `cat_id`, `ip_id`,
            `wait_for_domain`, `wait_domain`, `wait_since`, `exact_domain`, `expiry_date`, `issuer`, `fingerprint`, `finished`,
            `already_in_ssl`, `already_in_queue`, `failed`, `message`, `attempts`, `last_attempt`, `new_ssl_id`, `created_by`,
            `insert_time`';

        try {

            $this->getQueueLock();
            $pdo->beginTransaction();

            $ids = $pdo->query("
                SELECT id
                FROM ssl_queue
                WHERE finished = '1'
                ORDER BY id
                FOR UPDATE")->fetchAll(\PDO::FETCH_COLUMN);

            foreach (array_chunk(array_map('intval', $ids), 500) as $chunk) {

                $placeholders = implode(', ', array_fill(0, count($chunk), '?'));

                $stmt = $pdo->prepare("
                    INSERT INTO ssl_queue_history
                    (" . $columns . ")
                    SELECT " . $columns . "
                    FROM ssl_queue
                    WHERE id IN (" . $placeholders . ")
                    ORDER BY id");
                $stmt->execute($chunk);

                $stmt = $pdo->prepare("
                    DELETE FROM ssl_queue
                    WHERE id IN (" . $placeholders . ")");
                $stmt->execute($chunk);

            }

            $pdo->commit();

        } catch (\Throwable $e) {

            $this->cleanUp();
            $this->logError('Unable to clear completed rows from the SSL Queue', '', $e);

            return false;

        }

        $this->cleanUp();
        $this->checkSslQueueSafely();

        return count($ids);
    }

    /*
     * What Retry Failed does with a failed row, given the host's other unfinished rows (each with id, exact_domain, and
     * processing), checked in this order:
     * - array('mark'): another unfinished row is an exact-domain row, or the failed row is a pasted one, so it's marked
     *   "Already in the queue" rather than looked up twice.
     * - array('mark_upgrade', row id): the failed row is an exact-domain row and another is a pasted row not yet
     *   claimed, which gets the exact-domain upgrade, and the failed row is marked.
     * - array('busy'): the failed row is an exact-domain row and the pasted row is being processed now, so it stays
     *   failed.
     * - array('reopen'): no other unfinished row, so it's put back to Pending (if the queue has room).
     */
    public static function retryAction($failed, $others)
    {
        if (!$others) return array('reopen');

        foreach ($others as $other) {
            if ((int) $other->exact_domain === 1) return array('mark');
        }

        if ((int) $failed->exact_domain !== 1) return array('mark');

        foreach ($others as $other) {
            if ((int) $other->processing === 0) return array('mark_upgrade', (int) $other->id);
        }

        return array('busy');
    }

    /*
     * Retry Failed: puts the failed rows (not stale_choice ones, whose message asks for the hostname to be added again)
     * back to Pending, oldest first, under the SSL Queue's named lock and in one transaction, following retryAction()
     * and the MAX_WAITING cap (every reopened row takes a slot, and the marks and upgrades take none). Returns
     * array('reopened' => n, 'marked' => n, 'busy' => n, 'full' => n), or false on an error (logged, with nothing
     * changed).
     */
    public function retryFailed()
    {
        $pdo = $this->deeb->cnxx;
        $result = array('reopened' => 0, 'marked' => 0, 'busy' => 0, 'full' => 0);

        try {

            $this->getQueueLock();
            $pdo->beginTransaction();

            $slots = self::MAX_WAITING - (int) $pdo->query("
                SELECT COUNT(*)
                FROM ssl_queue
                WHERE finished = '0'")->fetchColumn();

            $failed_rows = $pdo->query("
                SELECT *
                FROM ssl_queue
                WHERE finished = '1'
                  AND failed = '1'
                  AND message <> 'stale_choice'
                ORDER BY id
                FOR UPDATE")->fetchAll();

            $now = $this->time->stamp();

            foreach ($failed_rows as $row) {

                // a row reopened earlier in this loop is one of the host's unfinished rows by now (a current read)
                $action = self::retryAction($row, $this->unfinishedRows($row->host));

                if ($action[0] === 'mark_upgrade') {

                    $choices = array('owner_id' => (int) $row->owner_id, 'account_id' => (int) $row->account_id,
                        'provider_id' => (int) $row->provider_id, 'type_id' => (int) $row->type_id, 'cat_id' => (int) $row->cat_id,
                        'ip_id' => (int) $row->ip_id);
                    $wait_domain = ((string) $row->wait_domain !== '') ? (string) $row->wait_domain : (string) $row->host;

                    $action = $this->upgradeRow($action[1], $wait_domain, $choices, $now) ? array('mark') : array('busy');

                }

                if ($action[0] === 'mark') {

                    $this->updateRow((int) $row->id, array('failed' => 0, 'already_in_queue' => 1, 'attempts' => 0, 'message' => ''));
                    $result['marked']++;

                } elseif ($action[0] === 'busy') {

                    $result['busy']++;

                } elseif ($slots > 0) {

                    $this->updateRow((int) $row->id, self::afterRetry($row));
                    $slots--;
                    $result['reopened']++;

                } else {

                    $result['full']++;

                }

            }

            $pdo->commit();

        } catch (\Throwable $e) {

            $this->cleanUp();
            $this->logError('Unable to retry the failed rows in the SSL Queue', '', $e);

            return false;

        }

        $this->cleanUp();

        return $result;
    }

    /*
     * Deletes one row, under the SSL Queue's named lock, unless it's claimed by a run (which may be in the middle of
     * changing it). Returns 'deleted', 'claimed', 'gone' (no such row), or 'failed' (an error, logged).
     */
    public function deleteRow($id)
    {
        $pdo = $this->deeb->cnxx;

        try {

            $this->getQueueLock();
            $pdo->beginTransaction();

            $row = $this->lockRow('ssl_queue', 'id', (int) $id, 'processing');
            $outcome = !$row ? 'gone' : (((int) $row->processing === 1) ? 'claimed' : 'deleted');

            if ($outcome === 'deleted') {

                $stmt = $pdo->prepare("
                    DELETE FROM ssl_queue
                    WHERE id = :id");
                $stmt->bindValue('id', (int) $id, \PDO::PARAM_INT);
                $stmt->execute();

            }

            $pdo->commit();

        } catch (\Throwable $e) {

            $this->cleanUp();
            $this->logError('Unable to delete a row from the SSL Queue', (string) $id, $e);

            return 'failed';

        }

        $this->cleanUp();
        if ($outcome === 'deleted') $this->checkSslQueueSafely();

        return $outcome;
    }

    /*
     * A text cell for the CSV export, in this order: decoded from its stored escaped form, stripped of the direction marks
     * Export::writeRow() removes, then protected from running as a spreadsheet formula, so nothing writeRow() strips
     * afterwards can uncover one.
     */
    public function exportText($value)
    {
        $value = (new Unsanitize())->text((string) $value);
        $value = str_replace(array("\u{2066}", "\u{2069}"), '', $value);

        return (new Export())->protectFormula($value);
    }

    // checkSslQueue() after a change that has already been committed, so a failure here doesn't make the change fail
    protected function checkSslQueueSafely()
    {
        try {

            $this->checkSslQueue();

        } catch (\Throwable $e) {

            $this->logError('Unable to check whether the SSL Queue has rows', '', $e);

        }
    }

    /*
     * The row's choices as stored: a chosen account is read (and locked) for its owner and provider, and the provider is
     * 0 when matching by issuer. Null when the chosen account no longer exists.
     */
    protected function resolveChoices($choices)
    {
        $resolved = array('owner_id' => (int) ($choices['owner_id'] ?? 0), 'account_id' => (int) ($choices['account_id'] ?? 0),
            'provider_id' => 0, 'type_id' => (int) ($choices['type_id'] ?? 0), 'cat_id' => (int) ($choices['cat_id'] ?? 0),
            'ip_id' => (int) ($choices['ip_id'] ?? 0));

        if ($resolved['account_id'] === 0) return $resolved;

        $stmt = $this->deeb->cnxx->prepare("
            SELECT owner_id, ssl_provider_id
            FROM ssl_accounts
            WHERE id = :account_id
            FOR UPDATE");
        $stmt->bindValue('account_id', $resolved['account_id'], \PDO::PARAM_INT);
        $stmt->execute();
        $account = $stmt->fetch();
        $stmt->closeCursor();
        if (!$account) return null;

        $resolved['owner_id'] = (int) $account->owner_id;
        $resolved['provider_id'] = (int) $account->ssl_provider_id;

        return $resolved;
    }

    /*
     * What to do with a pasted host (see pastedAction()): array('already_in_queue'), array('pending', domain id, domain
     * name), with 0 and '' when it goes ahead without a domain, or array('waiting', the Domain Queue's domain).
     */
    protected function planPasted($host)
    {
        if ($this->unfinishedRows($host)) return array('already_in_queue');

        list($status, $domain_id, $domain_name) = $this->findDomain($host, false, false);
        $queued = ($status === 'none') ? $this->findQueuedDomain($host, false) : '';
        $action = self::pastedAction($status, $queued);

        if ($action === 'wait') return array('waiting', $queued);

        if ($action === 'recheck') {

            // the Domain Queue may have imported the domain between the two reads, so domains is read once more (a
            // current read, since a plain read inside the transaction only sees the first read's snapshot)
            list($status, $domain_id, $domain_name) = $this->findDomain($host, false, true);
            $action = self::pastedAction('none', $queued, $status);

        }

        return ($action === 'link') ? array('pending', $domain_id, $domain_name) : array('pending', 0, '');
    }

    /*
     * What to do with a tick-box domain: array('already_in_queue') when it already has an unfinished exact-domain row,
     * array('upgrade', row id) for an unfinished, unclaimed pasted row, or array('new_exact').
     */
    protected function planExact($host)
    {
        $rows = $this->unfinishedRows($host);

        foreach ($rows as $row) {
            if ((int) $row->exact_domain === 1) return array('already_in_queue');
        }

        foreach ($rows as $row) {
            if ((int) $row->processing === 0) return array('upgrade', (int) $row->id);
        }

        return array('new_exact');
    }

    // The host's unfinished rows, locked
    protected function unfinishedRows($host)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT id, exact_domain, processing
            FROM ssl_queue
            WHERE host = :host
              AND finished = '0'
            ORDER BY id
            FOR UPDATE");
        $stmt->bindValue('host', $host, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /*
     * Changes a pasted row to wait for the exact domain, with the tick box's choices, starting afresh. Applied only when
     * the row is still unclaimed and unfinished, and returns whether it was.
     */
    protected function upgradeRow($id, $host, $choices, $now)
    {
        $stmt = $this->deeb->cnxx->prepare("
            UPDATE ssl_queue
            SET exact_domain = '1',
                wait_for_domain = '1',
                wait_domain = :host,
                wait_since = :now,
                domain_id = '0',
                domain_name = '',
                owner_id = :owner_id,
                account_id = :account_id,
                provider_id = :provider_id,
                type_id = :type_id,
                cat_id = :cat_id,
                ip_id = :ip_id,
                attempts = '0',
                message = ''
            WHERE id = :id
              AND processing = '0'
              AND finished = '0'");
        $stmt->bindValue('host', $host, \PDO::PARAM_STR);
        $stmt->bindValue('now', $now, \PDO::PARAM_STR);
        foreach (array('owner_id', 'account_id', 'provider_id', 'type_id', 'cat_id', 'ip_id') as $column) {
            $stmt->bindValue($column, $choices[$column], \PDO::PARAM_INT);
        }
        $stmt->bindValue('id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    protected function insertRow($row)
    {
        $columns = array_keys($row);
        $stmt = $this->deeb->cnxx->prepare("
            INSERT INTO ssl_queue
            (`" . implode('`, `', $columns) . "`)
            VALUES
            (:" . implode(', :', $columns) . ")");

        foreach ($row as $column => $value) {
            $stmt->bindValue($column, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }

        $stmt->execute();
    }

    protected function testFailure($written)
    {
        if (self::$test_add_failure && $written === 1) throw new \RuntimeException('Test failure after the first row');
    }

    /*
     * The host's domain in domains: array('one', id, name), array('many', 0, ''), or array('none', 0, ''). With $exact,
     * only a domain named exactly the host counts, otherwise the longest parent at a label boundary. With $current, it's
     * a current read (FOR UPDATE), for inside a transaction.
     */
    protected function findDomain($host, $exact, $current)
    {
        $forms = $exact ? array_filter(array(Domain::normalize($host))) : self::storedSuffixes($host);
        if (!$forms) return array('none', 0, '');

        $stmt = $this->deeb->cnxx->prepare("
            SELECT id, domain
            FROM domains
            WHERE domain IN (" . implode(', ', array_fill(0, count($forms), '?')) . ")
            ORDER BY id" . ($current ? "
            FOR UPDATE" : ""));
        $stmt->execute(array_values($forms));
        $rows = $stmt->fetchAll();

        $names = array();
        foreach ($rows as $row) $names[] = $row->domain;
        $best = $exact ? ($names ? Domain::normalize($host) : '') : self::bestMatch($host, $names);
        if ($best === '') return array('none', 0, '');

        $matches = array();
        foreach ($rows as $row) {
            if (strtolower(Domain::forApi($row->domain)) === strtolower(Domain::forApi($best))) $matches[] = $row;
        }

        if (count($matches) > 1) return array('many', 0, '');
        if (!$matches) return array('none', 0, '');

        return array('one', (int) $matches[0]->id, (string) $matches[0]->domain);
    }

    /*
     * The domain an unfinished, valid Domain Queue row has for the host (the host itself, or the longest parent at a
     * label boundary, or only the host itself with $exact), or ''. A freshly queued RDAP row counts whatever its
     * ready_to_import is, since it's 0 while the row waits for its lookup.
     */
    protected function findQueuedDomain($host, $exact)
    {
        $forms = $exact ? array_filter(array(Domain::normalize($host))) : self::storedSuffixes($host);
        if (!$forms) return '';

        $this->loadQueuedDomains();
        $names = array();
        foreach ($forms as $form) {
            if (isset($this->queued_domains[$form])) $names[] = $form;
        }

        return self::bestMatch($host, $names);
    }

    // domain_queue has no index on domain, so its unfinished, valid domains are read once (per add, waiting check, or
    // claim) rather than once per hostname
    protected function loadQueuedDomains()
    {
        if ($this->queued_domains !== null) return;

        $this->queued_domains = array_flip($this->deeb->cnxx->query("
            SELECT DISTINCT domain
            FROM domain_queue
            WHERE finished = '0'
              AND invalid_domain = '0'")->fetchAll(\PDO::FETCH_COLUMN));
    }

    /*
     * Releases the rows waiting for their domain, under the SSL Queue's named lock (see releaseValues()). A row whose
     * wait_domain is now in domains (as exactly one record) is released linked to it, keeping its attempts and message.
     * One that's still in the Domain Queue (an unfinished, valid row) keeps waiting for up to WAIT_DAYS from wait_since.
     * Otherwise it's released without a domain (when no record was found, only after domains is read once more), and
     * its claim looks for a domain again. The Domain Queue is read once, before the rows, since domain_queue has no index on domain: a domain queued
     * again during the check is only seen by the next one. $now (Y-m-d H:i:s, UTC) is for tests.
     */
    public function releaseWaiting($now = null)
    {
        $pdo = $this->deeb->cnxx;
        $now = $now ?? $this->time->stamp();
        $cutoff = gmdate('Y-m-d H:i:s', strtotime($now . ' UTC') - self::WAIT_DAYS * 86400);

        $rows = $pdo->query("
            SELECT id, wait_domain, exact_domain, wait_since
            FROM ssl_queue
            WHERE wait_for_domain = '1'
            ORDER BY id")->fetchAll();
        if (!$rows) return;

        $this->queued_domains = null;
        $this->loadQueuedDomains();

        foreach ($rows as $row) {

            try {

                $this->getQueueLock();
                $pdo->beginTransaction();

                $found = $this->findDomain($row->wait_domain, true, true);
                $expired = $row->wait_since <= $cutoff;
                $still_queued = $found[0] === 'none' && !$expired && $this->findQueuedDomain($row->wait_domain, true) !== '';
                $values = self::releaseValues($found, $still_queued, $expired);

                if ($values === null) {

                    $pdo->rollback();
                    $this->releaseQueueLock();
                    continue;

                }

                if ($found[0] === 'none') {

                    // the Domain Queue may have imported the domain and finished its row between the reads
                    $values = self::releaseValues($this->findDomain($row->wait_domain, true, true), false, true);

                }

                $this->updateRow((int) $row->id, $values, "wait_for_domain = '1' AND wait_domain = :wait_domain AND exact_domain = :exact_domain",
                    array('wait_domain' => $row->wait_domain, 'exact_domain' => (int) $row->exact_domain));

                $pdo->commit();
                $this->releaseQueueLock();

            } catch (\Throwable $e) {

                $this->cleanUp();
                $this->logError('Unable to release a waiting row in the SSL Queue', $row->wait_domain, $e);
                // The row keeps waiting, and a conflict is noted for hadConflict(), as an import's is
                if (function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e)) $this->had_conflict = true;

            }

        }
    }

    /*
     * Whether a write in this run met another request's and was rolled back: a certificate's save (it counts as
     * save_failed, tried again while it has attempts left), or a waiting row's release (it keeps waiting). The rest of
     * the run goes on, so this is reported after it rather than ending it.
     */
    public function hadConflict()
    {
        return $this->had_conflict;
    }

    // The ssl-queue task's run. Until the database update it does nothing, so cron and Run Now are safe either way.
    public function process()
    {
        $this->trace('start');

        if (!$this->isAvailable()) {

            $this->trace('unavailable');
            return;

        }

        if (!$this->getRunLock()) {

            $this->trace('locked');
            return;

        }

        try {

            $this->resetAbandonedClaims();
            $this->releaseWaiting();
            $this->processCandidates();

        } finally {

            $this->releaseRunLock();

        }
    }

    // Rows a stopped run left claimed for more than CLAIM_TIMEOUT count as a timeout (one attempt), and can be picked up
    protected function resetAbandonedClaims()
    {
        $pdo = $this->deeb->cnxx;
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::CLAIM_TIMEOUT);

        $stmt = $pdo->prepare("
            SELECT id, attempts
            FROM ssl_queue
            WHERE processing = '1'
              AND last_attempt < :cutoff");
        $stmt->bindValue('cutoff', $cutoff, \PDO::PARAM_STR);
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {

            try {

                $this->getQueueLock();
                $this->updateRow((int) $row->id, self::afterCrash($row), "processing = '1' AND last_attempt < :cutoff",
                    array('cutoff' => $cutoff));
                $this->releaseQueueLock();

            } catch (\Throwable $e) {

                $this->cleanUp();
                $this->logError('Unable to reset an abandoned row in the SSL Queue', (string) $row->id, $e);

            }

        }
    }

    protected function processCandidates()
    {
        $pdo = $this->deeb->cnxx;

        // never-tried rows first (oldest first), then retries, least recently tried first
        $candidates = $pdo->query("
            SELECT id
            FROM ssl_queue
            WHERE wait_for_domain = '0'
              AND processing = '0'
              AND finished = '0'
              AND attempts < '" . self::MAX_ATTEMPTS . "'
            ORDER BY (last_attempt > '1970-01-01 00:00:00'), last_attempt, id
            LIMIT " . self::CANDIDATES)->fetchAll(\PDO::FETCH_COLUMN);
        if (!$candidates) return;

        $started = microtime(true);
        $budget = $this->budget($started);
        $lookup = new SslLookup();
        $attempted = 0;

        foreach ($candidates as $id) {

            if ($attempted >= self::BATCH || $started + $budget - microtime(true) < SslLookup::MIN_LOOKUP_TIME) break;

            try {

                $this->getQueueLock();

                // waiting for the lock can use time, so the budget is checked again before the claim
                if ($started + $budget - microtime(true) < SslLookup::MIN_LOOKUP_TIME) {

                    $this->releaseQueueLock();
                    break;

                }

                $row = $this->claim((int) $id);

                if (!$row) {

                    $this->releaseQueueLock();
                    continue;

                }

                $attempted++;
                $this->queued_domains = null;
                $row = $this->prepareClaimed($row);
                $noted = $row ? $this->noteRevisions($row) : array();
                $this->releaseQueueLock();

                if (!$row) continue;

                // the reads since the claim took time too
                if ($started + $budget - microtime(true) < SslLookup::MIN_LOOKUP_TIME) {

                    $this->writeResult($row, 'deferred');
                    break;

                }

                $deadline = min(microtime(true) + SslLookup::DEADLINE, $started + $budget);
                list($code, $details) = $lookup->fetch($row->host, $deadline, (int) $row->exact_domain === 1);

                if (in_array($code, SslLookup::$saved_codes, true)) {
                    $this->import($row, $code, $details, $noted);
                } else {
                    $this->writeResult($row, $code);
                }

            } catch (\Throwable $e) {

                $this->cleanUp();
                $this->logError('Unable to process a row in the SSL Queue', (string) $id, $e);

            }

        }
    }

    // Whether a scheduled run would have a row to look up (for the turns the tasks take, see Scheduler::turn())
    public function hasWork()
    {
        if (!$this->isAvailable()) return false;

        return $this->deeb->cnxx->query("
            SELECT id
            FROM ssl_queue
            WHERE wait_for_domain = '0'
              AND processing = '0'
              AND finished = '0'
              AND attempts < '" . self::MAX_ATTEMPTS . "'
            LIMIT 1")->fetchColumn() !== false;
    }

    // The run's time budget: BUDGET, cut down by max_execution_time the way the Domain Queue's RDAP import does it
    protected function budget($started)
    {
        $budget = self::BUDGET;
        $max_execution_time = (int) ini_get('max_execution_time');

        if ($max_execution_time > 0) {

            // keep a quarter of what's left (at most a minute) for the rest of the run, and on a later task's turn, that
            // task's needs (see Scheduler::reserveFor())
            $remaining = $max_execution_time - ($started - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? $started));
            $budget = min($budget, max(0, $remaining - min(60, $remaining / 4)));
            $budget = min($budget, max(0, $remaining - Scheduler::reserveFor('ssl-queue')));

        }

        return $budget;
    }

    // Claims a Pending or Retrying row and returns it as it is now, or null when another run or a change got there first
    protected function claim($id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            UPDATE ssl_queue
            SET processing = '1',
                last_attempt = :now
            WHERE id = :id
              AND processing = '0'
              AND finished = '0'
              AND wait_for_domain = '0'");
        $stmt->bindValue('now', $this->time->stamp(), \PDO::PARAM_STR);
        $stmt->bindValue('id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() !== 1) return null;

        return $this->getRow($id);
    }

    public function getRow($id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT *
            FROM ssl_queue
            WHERE id = :id");
        $stmt->bindValue('id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();

        return $row ?: null;
    }

    /*
     * Finds the claimed row's domain from its current settings, and returns the row ready for its lookup, or null when
     * it waits here (its claim then cleared). A stored domain_id that still exists under the same name is kept. When
     * it's gone or renamed, or there's none, the domain is looked for again (see claimAction()): the one record found
     * is linked, a domain in the Domain Queue is waited for (unless the row has waited already), and otherwise the row
     * goes ahead without a domain. Any change to the row's domain is written to it here. An exact-domain row only ever
     * looks for its own wait_domain.
     */
    protected function prepareClaimed($row)
    {
        $exact = (int) $row->exact_domain === 1;
        $target = $exact ? (string) $row->wait_domain : (string) $row->host;

        if ((int) $row->domain_id > 0) {

            $stmt = $this->deeb->cnxx->prepare("
                SELECT domain
                FROM domains
                WHERE id = :id");
            $stmt->bindValue('id', (int) $row->domain_id, \PDO::PARAM_INT);
            $stmt->execute();
            $name = $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($name !== false && (string) $name === (string) $row->domain_name) return $row;

        }

        list($status, $domain_id, $domain_name) = $this->findDomain($target, $exact, false);
        if (self::$test_before_save) call_user_func(self::$test_before_save, 'claim_read', $row);
        $queued = ($status === 'none') ? $this->findQueuedDomain($target, $exact) : '';
        $action = self::claimAction($status, $queued, self::waitDone($row));

        if ($action === 'wait') {

            $this->updateRow((int) $row->id, array('processing' => 0, 'wait_for_domain' => 1, 'wait_domain' => $queued,
                'wait_since' => $this->time->stamp(), 'domain_id' => 0, 'domain_name' => ''));
            return null;

        }

        if ($action === 'recheck') {

            // the Domain Queue may have imported the domain and finished its row between the two reads
            list($status, $domain_id, $domain_name) = $this->findDomain($target, $exact, false);
            $action = self::claimAction('none', $queued, self::waitDone($row), $status);

        }

        if ($action !== 'link') {

            $domain_id = 0;
            $domain_name = '';

        }

        if ((int) $row->domain_id !== $domain_id || (string) $row->domain_name !== $domain_name) {

            $this->updateRow((int) $row->id, array('domain_id' => $domain_id, 'domain_name' => $domain_name));
            $row->domain_id = $domain_id;
            $row->domain_name = $domain_name;

        }

        return $row;
    }

    /*
     * array(revision, name) of every certificate a name match could pick at save time, with the revision of its status
     * row (null for none): those named for the host (or, for a tick-box row, for its www. name too) on the row's domain,
     * when it has one, and those with no domain. The name is kept as well, since a record with no status row can be
     * renamed with no revision to show it.
     */
    protected function noteRevisions($row)
    {
        $wanted = array(strtolower(Domain::forApi($row->host)));
        if ((int) $row->exact_domain === 1) $wanted[] = strtolower(Domain::forApi('www.' . $row->host));

        $noted = array();
        $certificates = ((int) $row->domain_id > 0) ? $this->certificatesNamed((int) $row->domain_id, $wanted, false) : array();

        foreach ($certificates as $certificate) {
            $noted[(int) $certificate->id] = array(($certificate->revision === null) ? null : (int) $certificate->revision,
                (string) $certificate->name);
        }

        $candidates = $this->noDomainCandidates($wanted);

        if ($candidates) {

            $stmt = $this->deeb->cnxx->prepare("
                SELECT ssl_id, revision
                FROM ssl_cert_status
                WHERE ssl_id IN (" . implode(', ', array_fill(0, count($candidates), '?')) . ")");
            $stmt->execute(array_keys($candidates));
            $revisions = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            foreach ($candidates as $id => $name) {
                $noted[$id] = array(isset($revisions[$id]) ? (int) $revisions[$id] : null, $name);
            }

        }

        return $noted;
    }

    /*
     * The certificates with no domain whose name (normalised, and a valid hostname) is one of the ASCII names in
     * $wanted, as array(id => stored name), lowest id first. It's an unlocked read on ssl_certs alone, so it locks
     * nothing and sees every certificate with no domain committed by then. Each name's ASCII form is kept for the run, so a name is only
     * converted once however many saves read it, and the rows are read one at a time rather than all at once, so a
     * large table doesn't need its every row in memory as objects. The SSL Queue only runs once the 4.27.0 update has
     * set every certificate whose domain was deleted to 0, so domain_id = 0 is exactly "no domain" wherever it runs.
     */
    protected function noDomainCandidates($wanted)
    {
        $stmt = $this->deeb->cnxx->query("
            SELECT id, `name`
            FROM ssl_certs
            WHERE domain_id = '0'
            ORDER BY id");

        $candidates = array();

        while (($certificate = $stmt->fetch(\PDO::FETCH_OBJ)) !== false) {

            $name = (string) $certificate->name;
            if (!isset($this->ascii_names[$name])) {
                $this->ascii_names[$name] = $this->asciiHostname((new Unsanitize())->text($name));
            }
            if ($this->ascii_names[$name] !== '' && in_array($this->ascii_names[$name], $wanted, true)) $candidates[(int) $certificate->id] = $name;

        }

        return $candidates;
    }

    /*
     * The domain's certificates whose name (normalised, and a valid hostname) is one of the ASCII names in $wanted,
     * lowest id first, with their status row's revision (null for none). With $lock, the certificates and status rows
     * stay locked until the transaction ends.
     */
    protected function certificatesNamed($domain_id, $wanted, $lock)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT c.id, c.`name`, s.revision, s.fingerprint
            FROM ssl_certs AS c
            LEFT JOIN ssl_cert_status AS s ON s.ssl_id = c.id
            WHERE c.domain_id = :domain_id
            ORDER BY c.id" . ($lock ? "
            FOR UPDATE" : ""));
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        $matches = array();

        foreach ($stmt->fetchAll() as $certificate) {

            $ascii = $this->asciiHostname((new Unsanitize())->text($certificate->name));
            if ($ascii !== '' && in_array($ascii, $wanted, true)) $matches[] = $certificate;

        }

        return $matches;
    }

    // A typed certificate name as an ASCII hostname (normalised, without a final dot), or '' when it isn't one
    public function asciiHostname($name)
    {
        $normalized = Domain::normalize($name);
        if ($normalized === '') return '';

        $ascii = strtolower(Domain::forApi($normalized));
        return ((new Rdap())->isHostname($ascii) && strpos($ascii, '.') !== false) ? $ascii : '';
    }

    // Whether an ASCII hostname is the domain itself or under it
    public static function isUnder($ascii_host, $domain_name)
    {
        $ascii_domain = strtolower(Domain::forApi($domain_name));
        return $ascii_domain !== '' && ($ascii_host === $ascii_domain || str_ends_with($ascii_host, '.' . $ascii_domain));
    }

    /*
     * Saves a lookup that came with a certificate, in one transaction under the SSL Queue's named lock: the queue row and
     * every id the certificate uses are locked and rechecked, then the certificate is matched to an existing record
     * (by name, then fingerprint, then a certificate with no domain named for the host) or inserted. The certificates
     * with no domain are read after the lock and before the transaction, so the read sees everything committed by then.
     * Any error rolls back and counts as save_failed (tried again next run). A save that met another request's write is
     * also noted for hadConflict(), so Run Now can say so.
     */
    protected function import($row, $code, $details, $noted)
    {
        $pdo = $this->deeb->cnxx;
        $ssl_assets = new SslAssets();
        $outcome = '';

        try {

            $this->getQueueLock();
            $candidates = $this->noDomainCandidates(array(strtolower(Domain::forApi($details['host']))));
            if (self::$test_before_save) call_user_func(self::$test_before_save, 'read', $row);
            $pdo->beginTransaction();

            $outcome = $this->importLocked($row, $code, $details, $noted, $ssl_assets, $candidates);

            if ($outcome === 'saved') {
                $pdo->commit();
            } else {
                $pdo->rollback();
            }

        } catch (\Throwable $e) {

            $this->cleanUp();
            $this->logError('Unable to save a certificate from the SSL Queue', $row->host, $e);
            $outcome = 'save_failed';
            if (function_exists('domainmod_restore_is_conflict') && domainmod_restore_is_conflict($e)) $this->had_conflict = true;

        } finally {

            // a failed release (such as a lost connection) doesn't hide the import's outcome
            try {
                $ssl_assets->release();
            } catch (\Throwable $e) {
                // the database releases the lock when the connection ends
            }

            $this->cleanUp();

        }

        if ($outcome === 'saved') {

            // the session flags for whoever ran it (Run Now), as ssl/add.php sets them after a manual add
            $_SESSION['s_missing_ssl_fees'] = $this->system->checkForRows((new QueryBuild())->missingFees('ssl_certs'));
            $this->system->checkExistingAssets();

        } elseif ($outcome !== 'gone') {

            $this->writeResult($row, $outcome);

        }
    }

    /*
     * The import's work inside its transaction. Returns 'saved', 'gone' (the row is no longer this run's claim, so
     * nothing is written), or a code to write to the row once the transaction is rolled back. $candidates are the
     * certificates with no domain named for the host (noDomainCandidates()), read before the transaction.
     */
    protected function importLocked($row, $code, $details, $noted, $ssl_assets, $candidates)
    {
        $pdo = $this->deeb->cnxx;

        // The import is recorded in the Change History, in this transaction, started before any row is locked, so it
        // takes the custom field definitions' locks first, as every writer does
        $history_op = (new ChangeHistory($pdo))->start(ChangeHistory::SOURCE_SSL_QUEUE, (int) $row->created_by);

        $current = $this->lockRow('ssl_queue', 'id', (int) $row->id, '*');
        if (!$current || (int) $current->processing !== 1 || (int) $current->finished !== 0) return 'gone';

        // every id the certificate will use, locked and rechecked, since any can be deleted or changed while the lookup
        // runs. A domain that's gone or renamed isn't looked up again here, since that could lock domains in another
        // order than a bulk delete: the row goes back to the queue without it, and its next claim finds its domain.
        $domain = null;

        if ((int) $row->domain_id > 0) {

            $domain = $this->lockRow('domains', 'id', (int) $row->domain_id, 'domain');
            if (!$domain || (string) $domain->domain !== (string) $row->domain_name) return 'domain_changed';

        }

        foreach (array(array('owners', $row->owner_id), array('ssl_cert_types', $row->type_id),
                     array('categories', $row->cat_id), array('ip_addresses', $row->ip_id)) as $check) {
            if (!$this->lockRow($check[0], 'id', (int) $check[1], 'id')) return 'stale_choice';
        }

        $account = null;

        if ((int) $row->account_id > 0) {

            $account = $this->lockRow('ssl_accounts', 'id', (int) $row->account_id, 'ssl_provider_id, owner_id');
            if (!$account || (int) $account->ssl_provider_id !== (int) $row->provider_id || (int) $account->owner_id !== (int) $row->owner_id
                || !$this->lockRow('ssl_providers', 'id', (int) $row->provider_id, 'id')) {
                return 'stale_choice';
            }

        }

        $served_ascii = strtolower(Domain::forApi($details['host']));
        $now = $this->time->stamp();

        /*
         * 1. An existing record: on the row's domain, the lowest id named for the host, then the lowest id holding the
         * fingerprint, and then (with or without a domain) the lowest id with no domain named for the host. A record
         * matched by name has its status row updated (below), and its name and revision as they are now are kept in
         * $name_match for that.
         */
        $by_name = $domain ? $this->certificatesNamed((int) $row->domain_id, array($served_ascii), true) : array();
        $match_id = $by_name ? (int) $by_name[0]->id : 0;
        $name_match = $by_name ? array(($by_name[0]->revision === null) ? null : (int) $by_name[0]->revision,
            (string) $by_name[0]->name) : null;
        $finish_domain = null;

        if ($domain && $match_id === 0 && $details['fingerprint'] !== '') {

            $stmt = $pdo->prepare("
                SELECT c.id, c.`name`
                FROM ssl_certs AS c
                JOIN ssl_cert_status AS s ON s.ssl_id = c.id
                WHERE c.domain_id = :domain_id
                  AND s.fingerprint = :fingerprint
                ORDER BY c.id
                FOR UPDATE");
            $stmt->bindValue('domain_id', (int) $row->domain_id, \PDO::PARAM_INT);
            $stmt->bindValue('fingerprint', $details['fingerprint'], \PDO::PARAM_STR);
            $stmt->execute();

            foreach ($stmt->fetchAll() as $certificate) {

                // only a record whose name still belongs to its domain
                $ascii = $this->asciiHostname((new Unsanitize())->text($certificate->name));

                if ($ascii !== '' && self::isUnder($ascii, $domain->domain)) {

                    $match_id = (int) $certificate->id;
                    break;

                }

            }

        }

        if ($match_id === 0) {

            /*
             * Each candidate is locked by its id on ssl_certs alone, then its status row, and rechecked: still named for
             * the host, and still with no domain (0 is never a domain, so no domains row is read under these locks). One
             * renamed or linked since the read only moves the match to the next candidate.
             */
            foreach ($candidates as $candidate_id => $candidate_name) {

                $certificate = $this->lockRow('ssl_certs', 'id', $candidate_id, 'id, `name`, domain_id');
                $status = $this->lockRow('ssl_cert_status', 'ssl_id', $candidate_id, 'revision');
                if (!$certificate || (int) $certificate->domain_id !== 0) continue;
                if ($this->asciiHostname((new Unsanitize())->text($certificate->name)) !== $served_ascii) continue;

                $match_id = (int) $candidate_id;
                $name_match = array($status ? (int) $status->revision : null, (string) $certificate->name);

                // the finished row shows the matched record's domain, which it leaves as it is
                if ($domain) $finish_domain = array(0, '');
                break;

            }

        }

        if (self::$test_before_save) call_user_func(self::$test_before_save, 'locked', $row);

        if ($match_id > 0) {

            if ($name_match !== null) {

                // the status row describes the latest lookup, unless the record or its status row changed while this lookup
                // ran (then the newer result is kept, and only the link is made)
                if (isset($noted[$match_id]) && $noted[$match_id] === $name_match) {
                    $this->writeStatus($match_id, $details, $code, false);
                }

            }

            $this->finishRow((int) $row->id, $code, $details, $match_id, true, $finish_domain);
            return 'saved';

        }

        // 2. A new record's name must fit, never cut short
        if (mb_strlen($details['host']) > self::MAX_NAME_LENGTH) return 'name_too_long';

        // 3. The provider and account, then the certificate
        if ($account) {

            $provider_id = (int) $account->ssl_provider_id;
            $account_id = (int) $row->account_id;
            $owner_id = (int) $account->owner_id;

        } else {

            list($provider_id, $account_id, $owner_id) = $ssl_assets->resolve($details['issuer'], (int) $row->owner_id, (int) $row->created_by);

        }

        $stmt = $pdo->prepare("
            SELECT id, (renewal_fee + misc_fee) AS total_cost
            FROM ssl_fees
            WHERE ssl_provider_id = :provider_id
              AND type_id = :type_id");
        $stmt->bindValue('provider_id', $provider_id, \PDO::PARAM_INT);
        $stmt->bindValue('type_id', (int) $row->type_id, \PDO::PARAM_INT);
        $stmt->execute();
        $fee = $stmt->fetch();
        $stmt->closeCursor();

        $stmt = $pdo->prepare("
            INSERT INTO ssl_certs
            (owner_id, ssl_provider_id, account_id, domain_id, `name`, type_id, ip_id, cat_id, expiry_date, fee_id,
             total_cost, notes, active, fee_fixed, creation_type_id, created_by, insert_time)
            VALUES
            (:owner_id, :provider_id, :account_id, :domain_id, :name, :type_id, :ip_id, :cat_id, :expiry_date, :fee_id,
             :total_cost, :notes, :active, :fee_fixed, :creation_type_id, :created_by, :insert_time)");
        $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
        $stmt->bindValue('provider_id', $provider_id, \PDO::PARAM_INT);
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->bindValue('domain_id', (int) $row->domain_id, \PDO::PARAM_INT);
        $stmt->bindValue('name', $details['host'], \PDO::PARAM_STR);
        $stmt->bindValue('type_id', (int) $row->type_id, \PDO::PARAM_INT);
        $stmt->bindValue('ip_id', (int) $row->ip_id, \PDO::PARAM_INT);
        $stmt->bindValue('cat_id', (int) $row->cat_id, \PDO::PARAM_INT);
        $stmt->bindValue('expiry_date', $details['expiry_date'], \PDO::PARAM_STR);
        $stmt->bindValue('fee_id', $fee ? (int) $fee->id : 0, \PDO::PARAM_INT);
        $stmt->bindValue('total_cost', strval($fee ? $fee->total_cost : 0), \PDO::PARAM_STR);
        $stmt->bindValue('notes', $this->importNote($details, $code), \PDO::PARAM_LOB);
        $stmt->bindValue('active', $code === 'expired' ? 0 : 1, \PDO::PARAM_INT);
        $stmt->bindValue('fee_fixed', $fee ? 1 : 0, \PDO::PARAM_INT);
        $stmt->bindValue('creation_type_id', $this->system->getCreationTypeId('Queue'), \PDO::PARAM_INT);
        $stmt->bindValue('created_by', (int) $row->created_by, \PDO::PARAM_INT);
        $stmt->bindValue('insert_time', $now, \PDO::PARAM_STR);
        $stmt->execute();
        $ssl_id = (int) $pdo->lastInsertId('id');

        $stmt = $pdo->prepare("
            INSERT INTO ssl_cert_field_data
            (ssl_id, insert_time)
            VALUES
            (:ssl_id, :insert_time)");
        $stmt->bindValue('ssl_id', $ssl_id, \PDO::PARAM_INT);
        $stmt->bindValue('insert_time', $now, \PDO::PARAM_STR);
        $stmt->execute();

        $this->writeStatus($ssl_id, $details, $code, true);
        $this->finishRow((int) $row->id, $code, $details, $ssl_id, false);

        // The import's create row, with a snapshot that includes the issuer the status row now holds
        $history_op->created('ssl', $ssl_id);
        $history_op->finish();

        return 'saved';
    }

    /*
     * One row of $table by its key, locked until the transaction ends, or null. $table, $key, and $columns are only ever
     * fixed names from this class.
     */
    protected function lockRow($table, $key, $id, $columns)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT " . $columns . "
            FROM `" . $table . "`
            WHERE `" . $key . "` = :id
            FOR UPDATE");
        $stmt->bindValue('id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();

        return $row ?: null;
    }

    // The dated line added to an imported certificate's notes (stored in English, as Refresh's note lines are)
    public function importNote($details, $code)
    {
        // an issuer that already ends with a full stop (such as "Entrust, Inc.") doesn't get a second one
        $issuer = ($details['issuer'] !== '') ? $details['issuer'] : 'unknown';
        $note = $this->time->timeBasic() . ' - Imported by the SSL Queue from ' . $details['host'] . '. Issuer: '
            . rtrim($issuer, '.') . '. Names: ' . implode(', ', $details['names']) . '.';

        if ($code === 'untrusted') {
            $note .= ' Not trusted: ' . ($details['reason'] === 'self_signed' ? 'self-signed' : 'not trusted') . '.';
        } elseif ($code === 'expired') {
            $note .= ' Not trusted: expired.';
        }

        return $this->sanitize->text($note);
    }

    /*
     * Text from a certificate as it's stored: escaped as typed text is (several pages print stored names without
     * escaping), and cut to $length characters without splitting an escape. The text is already cleaned by SslLookup.
     */
    public function storedText($text, $length)
    {
        $text = rtrim(mb_substr((string) $text, 0, $length));
        $escaped = $this->sanitize->text($text);

        while (mb_strlen($escaped) > $length && $text !== '') {

            $text = mb_substr($text, 0, mb_strlen($text) - 1);
            $escaped = $this->sanitize->text(rtrim($text));

        }

        return $escaped;
    }

    // The DNS names as stored: one per line, each escaped on its own, cut at a line to fit the TEXT column
    public function storedNames($names)
    {
        $stored = '';

        foreach ($names as $name) {

            $line = $this->storedText($name, 255);
            if (strlen($stored) + strlen($line) + 1 > 60000) break;
            $stored .= ($stored === '' ? '' : "\n") . $line;

        }

        return $stored;
    }

    /*
     * Creates or updates a certificate's status row from a lookup, adding one to its revision. $set_refreshed also sets
     * refreshed_issuer (an import or a Refresh), which the queue's name-match updates never change.
     */
    public function writeStatus($ssl_id, $details, $code, $set_refreshed)
    {
        $issuer = $this->storedText($details['issuer'], 255);
        $refreshed = $set_refreshed ? ",
                refreshed_issuer = VALUES(refreshed_issuer)" : "";

        $stmt = $this->deeb->cnxx->prepare("
            INSERT INTO ssl_cert_status
            (ssl_id, host, fingerprint, issuer, refreshed_issuer, `names`, valid_from, valid_to, trusted, last_check,
             last_result, revision)
            VALUES
            (:ssl_id, :host, :fingerprint, :issuer, :refreshed_issuer, :names, :valid_from, :valid_to, :trusted, :last_check,
             :last_result, '1')
            ON DUPLICATE KEY UPDATE
                host = VALUES(host),
                fingerprint = VALUES(fingerprint),
                issuer = VALUES(issuer)" . $refreshed . ",
                `names` = VALUES(`names`),
                valid_from = VALUES(valid_from),
                valid_to = VALUES(valid_to),
                trusted = VALUES(trusted),
                last_check = VALUES(last_check),
                last_result = VALUES(last_result),
                revision = revision + 1");
        $stmt->bindValue('ssl_id', $ssl_id, \PDO::PARAM_INT);
        $stmt->bindValue('host', $details['host'], \PDO::PARAM_STR);
        $stmt->bindValue('fingerprint', $details['fingerprint'], \PDO::PARAM_STR);
        $stmt->bindValue('issuer', $issuer, \PDO::PARAM_STR);
        $stmt->bindValue('refreshed_issuer', $set_refreshed ? $issuer : null, $set_refreshed ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue('names', $this->storedNames($details['names']), \PDO::PARAM_LOB);
        $stmt->bindValue('valid_from', SslLookup::utcDate('Y-m-d H:i:s', $details['valid_from']), \PDO::PARAM_STR);
        $stmt->bindValue('valid_to', SslLookup::utcDate('Y-m-d H:i:s', $details['valid_to']), \PDO::PARAM_STR);
        $stmt->bindValue('trusted', $details['trusted'] ? 1 : 0, \PDO::PARAM_INT);
        $stmt->bindValue('last_check', $this->time->stamp(), \PDO::PARAM_STR);
        $stmt->bindValue('last_result', $code, \PDO::PARAM_STR);
        $stmt->execute();
    }

    /*
     * Completes a row with the lookup's details, imported or matched to an existing record. $domain, array(id, name),
     * replaces the row's domain (a row with a domain matched to a certificate with no domain), so the finished row
     * shows the domain of the certificate it ended with.
     */
    protected function finishRow($id, $code, $details, $ssl_id, $already_in_ssl, $domain = null)
    {
        $values = array('processing' => 0, 'finished' => 1, 'already_in_ssl' => $already_in_ssl ? 1 : 0,
            'new_ssl_id' => $ssl_id, 'expiry_date' => $details['expiry_date'], 'issuer' => $this->storedText($details['issuer'], 255),
            'fingerprint' => $details['fingerprint'], 'message' => $code);
        if ($domain !== null) $values += array('domain_id' => (int) $domain[0], 'domain_name' => (string) $domain[1]);

        $this->updateRow($id, $values);
    }

    // Writes a result that isn't a success to a claimed row (afterResult()), clearing its claim
    protected function writeResult($row, $code)
    {
        $this->updateRow((int) $row->id, self::afterResult($row, $code), "processing = '1'");
    }

    /*
     * Updates one row. $where adds conditions (with their own values in $where_values), so a write only applies to a row
     * still in the state it was read in. Column names are only ever fixed names from this class. Returns the rows changed.
     */
    protected function updateRow($id, $values, $where = '', $where_values = array())
    {
        $sets = array();
        foreach (array_keys($values) as $column) $sets[] = "`" . $column . "` = :set_" . $column;

        $stmt = $this->deeb->cnxx->prepare("
            UPDATE ssl_queue
            SET " . implode(', ', $sets) . "
            WHERE id = :id" . ($where !== '' ? " AND " . $where : ""));

        foreach ($values as $column => $value) {
            $stmt->bindValue('set_' . $column, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        foreach ($where_values as $name => $value) {
            $stmt->bindValue($name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue('id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /*
     * Deletes the status rows of certificates being deleted, inside the caller's transaction. Before the database update
     * there's no table, so nothing is done.
     */
    public function deleteStatus($ssl_ids)
    {
        $ssl_ids = array_values(array_map('intval', $ssl_ids));
        if (!$ssl_ids || !$this->isAvailable()) return;

        $stmt = $this->deeb->cnxx->prepare("
            DELETE FROM ssl_cert_status
            WHERE ssl_id IN (" . implode(', ', array_fill(0, count($ssl_ids), '?')) . ")");
        $stmt->execute($ssl_ids);
    }

    /*
     * Clears a certificate's lookup details after its name or domain changed (inside the caller's transaction), so a
     * later import of the old host can't match it, adding one to the revision so a Refresh in progress notices.
     */
    public function resetStatus($ssl_id)
    {
        if (!$this->isAvailable()) return;

        $stmt = $this->deeb->cnxx->prepare("
            UPDATE ssl_cert_status
            SET host = '',
                fingerprint = '',
                issuer = '',
                refreshed_issuer = NULL,
                `names` = '',
                valid_from = '1970-01-01 00:00:00',
                valid_to = '1970-01-01 00:00:00',
                trusted = '0',
                last_check = '1970-01-01 00:00:00',
                last_result = '',
                revision = revision + 1
            WHERE ssl_id = :ssl_id");
        $stmt->bindValue('ssl_id', (int) $ssl_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /*
     * The SSL Queue's named lock (held by addHosts(), releaseWaiting(), the claim, the save, Retry Failed, Clear
     * Completed, and Delete), taken once and kept until releaseQueueLock().
     */
    public function getQueueLock()
    {
        if ($this->queue_locked) return;

        $stmt = $this->deeb->cnxx->prepare("SELECT GET_LOCK(:lock_name, " . self::LOCK_WAIT . ")");
        $stmt->bindValue('lock_name', $this->lockName('domainmod_ssl_queue_'), \PDO::PARAM_STR);
        $stmt->execute();
        if ((string) $stmt->fetchColumn() !== '1') throw new \RuntimeException('Unable to get the SSL Queue lock');

        $this->queue_locked = true;
    }

    public function releaseQueueLock()
    {
        if (!$this->queue_locked) return;

        try {

            $stmt = $this->deeb->cnxx->prepare("SELECT RELEASE_LOCK(:lock_name)");
            $stmt->bindValue('lock_name', $this->lockName('domainmod_ssl_queue_'), \PDO::PARAM_STR);
            $stmt->execute();

        } finally {

            $this->queue_locked = false;

        }
    }

    // The run lock, taken without waiting, so two runs never look rows up side by side
    protected function getRunLock()
    {
        $stmt = $this->deeb->cnxx->prepare("SELECT GET_LOCK(:lock_name, 0)");
        $stmt->bindValue('lock_name', $this->lockName('domainmod_ssl_queue_run_'), \PDO::PARAM_STR);
        $stmt->execute();

        return (string) $stmt->fetchColumn() === '1';
    }

    protected function releaseRunLock()
    {
        $stmt = $this->deeb->cnxx->prepare("SELECT RELEASE_LOCK(:lock_name)");
        $stmt->bindValue('lock_name', $this->lockName('domainmod_ssl_queue_run_'), \PDO::PARAM_STR);
        $stmt->execute();
    }

    // Named locks are shared by every database on the server, so the name includes a short hash of this database's name
    protected function lockName($prefix)
    {
        return $prefix . substr(md5(DB_NAME), 0, 12);
    }

    // After an error: rolls back any open transaction and releases the SSL Queue's named lock, and a failure doing either
    // (such as a lost connection) doesn't stop the caller returning its own result
    protected function cleanUp()
    {
        try {
            if ($this->deeb->cnxx->inTransaction()) $this->deeb->cnxx->rollback();
        } catch (\Throwable $e) {
            // nothing more can be done
        }

        try {
            $this->releaseQueueLock();
        } catch (\Throwable $e) {
            // nothing more can be done
        }
    }

    // Logging never stops the run
    protected function logError($log_message, $item, $error)
    {
        try {

            $log_extra = array('Item' => $item, 'Error' => $error->getMessage());
            $this->log->critical($log_message, $log_extra);

        } catch (\Throwable $e) {

            // nothing more can be done

        }
    }

    protected function trace($event)
    {
        if (self::$test_trace !== null) call_user_func(self::$test_trace, $event);
    }

} //@formatter:on
