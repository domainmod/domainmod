<?php
/**
 * /classes/DomainMOD/SslRefresh.php
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
 * Refreshes one SSL certificate on request (the Refresh button on the SSL certificate edit page). It reads the
 * certificate the site named by the record serves now (SslLookup), and updates the record's expiry date and status,
 * with a dated note, and its lookup status row (ssl_cert_status). It never changes the provider, account, type, or fee.
 * Its table comes with the 4.27.0 database update, so nothing here runs before then.
 */
class SslRefresh
{
    public $deeb;
    public $log;
    public $lookup;
    public $queue;
    public $sanitize;
    public $time;

    const DEADLINE = 45;           // seconds for the lookup, as DomainRefresh allows
    const SAME_CERT_GAP = 30;      // seconds between refreshes of the same certificate, per session
    const ANY_CERT_GAP = 5;        // seconds between any two certificate refreshes, per session

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.sslrefresh');
        $this->lookup = new SslLookup();
        $this->queue = new SslQueue();
        $this->sanitize = new Sanitize();
        $this->time = new Time();
    }

    /*
     * Refreshes a certificate and returns array(success, lines, parts), as DomainRefresh::refresh() does: the lines make
     * up the message for the user, and the parts (a summary, reasons, changes, and notes) are the edit page's compact
     * result (System::showMessageResult()), all translated and escaped for HTML. $expected_name (plain text) and
     * $expected_domain_id are the name and domain the page was drawn with, so a change made in another window since
     * then stops the refresh.
     */
    public function refresh($ssl_id, $user_id, $expected_name, $expected_domain_id)
    {
        if (!$this->queue->isAvailable()) {
            $unavailable = _('Refresh becomes available after the next database update.');
            return $this->result(false, $unavailable, array(), array(), array());
        }

        $ssl_id = (int) $ssl_id;
        $missing = _('That SSL certificate no longer exists.');

        // Only one refresh of a certificate at a time, across every session
        if (!$this->getLock($ssl_id)) {

            $named = $this->getCertificateRow($ssl_id);
            if (!$named) return $this->result(false, $missing, array(), array(), array());

            $failed = sprintf(_("SSL certificate %s couldn't be refreshed."), (new Layout())->ltr((new Unsanitize())->text((string) $named->name)));
            $busy = _('This SSL certificate is already being refreshed. Please try again in a moment.');
            return $this->result(false, $failed, array($busy), array(), array());

        }

        try {

            // read under the lock, so it includes anything a refresh that just finished saved
            $before = $this->getCertificateRow($ssl_id);
            if (!$before) return $this->result(false, $missing, array(), array(), array());

            $unsanitize = new Unsanitize();
            $plain_name = $unsanitize->text((string) $before->name);
            $plain_domain = $unsanitize->text((string) $before->domain);
            $failed = sprintf(_("SSL certificate %s couldn't be refreshed."), (new Layout())->ltr($plain_name));

            if ($plain_name !== (string) $expected_name || (int) $before->domain_id !== (int) $expected_domain_id) {
                $other_window = _('This SSL certificate was changed in another window. Please reload the page and try again.');
                return $this->result(false, $failed, array($other_window), array(), array());
            }

            // an older database can hold a certificate whose domain is gone, which the user can link to another one
            if ($before->domain === null) {
                return $this->result(false, $failed, array(_('The domain you chose no longer exists. Please choose another one.')), array(), array());
            }

            list($host_status, $host) = $this->hostCheck($plain_name, $plain_domain);
            if ($host_status !== 'ok') {
                return $this->result(false, $failed, array($this->refusalMessage($host_status, $plain_name, $plain_domain)), array(), array());
            }

            // after the lock, so a refresh that couldn't start doesn't count toward the wait
            $wait = $this->throttle($ssl_id);
            if ($wait !== '') return $this->result(false, $failed, array($wait), array(), array());

            list($code, $details) = $this->lookup->fetch($host, $this->lookup->now() + self::DEADLINE, false);

            if (!in_array($code, SslLookup::$saved_codes, true)) {
                return $this->result(false, $failed, array($this->failureMessage($code)), array(), array());
            }

            list($success, $reasons, $changes) = $this->apply($before, $code, $details);
            if (!$success) return $this->result(false, $failed, $reasons, array(), array());

            $summary = sprintf(_('SSL certificate %s refreshed.'), (new Layout())->ltr($plain_name));
            return $this->result(true, $summary, $reasons, $changes, $this->trustNotes($code, $details));

        } finally {

            $this->releaseLock($ssl_id);

        }
    }

    // array(success, lines, parts), with the lines in the order the parts are shown
    protected function result($success, $summary, $reasons, $changes, $notes)
    {
        return array($success, array_merge(array($summary), $reasons, $changes, $notes),
            array('summary' => $summary, 'reasons' => $reasons, 'changes' => $changes, 'notes' => $notes));
    }

    // The certificate with its domain's name (null when the domain is gone) and its status row's revision (null for
    // none), read without locking
    protected function getCertificateRow($ssl_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT c.id, c.`name`, c.domain_id, c.account_id, c.expiry_date, c.active, d.domain, s.revision
            FROM ssl_certs AS c
            LEFT JOIN domains AS d ON d.id = c.domain_id
            LEFT JOIN ssl_cert_status AS s ON s.ssl_id = c.id
            WHERE c.id = :ssl_id");
        $stmt->bindValue('ssl_id', (int) $ssl_id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();

        return $row ?: null;
    }

    /*
     * Which host a certificate's name (plain text) means: array('ok', host) with the host in its stored form
     * (Domain::normalize()) when the name is a hostname at or under its domain, array('label', '') when it isn't a
     * hostname, or array('outside', '') when it's a hostname outside its domain.
     */
    public function hostCheck($plain_name, $domain_name)
    {
        $ascii = $this->queue->asciiHostname($plain_name);
        if ($ascii === '') return array('label', '');
        if (!SslQueue::isUnder($ascii, $domain_name)) return array('outside', '');

        return array('ok', Domain::normalize($plain_name));
    }

    // Why a name can't be refreshed (hostCheck()'s 'label' or 'outside'), translated and escaped
    public function refusalMessage($status, $plain_name, $domain_name)
    {
        if ($status === 'label') {
            return _("Refresh needs this certificate's name to be the hostname it's installed on, such as www.example.com.");
        }

        $layout = new Layout();
        // TRANSLATORS: %1$s is the SSL certificate's name, and %2$s is the domain it's linked to.
        return sprintf(_("This certificate's name, %1\$s, isn't part of its domain, %2\$s. Link it to the right domain, or change the name, then refresh it."),
            $layout->ltr($plain_name), $layout->ltr($domain_name));
    }

    /*
     * Per session, a refresh of the same certificate waits SAME_CERT_GAP seconds and any refresh waits ANY_CERT_GAP
     * seconds, as DomainRefresh does for domains. Returns '' when the refresh can go ahead (and records it), or the
     * message saying how long to wait.
     */
    protected function throttle($ssl_id)
    {
        $now = time();
        $times = $_SESSION['s_ssl_refresh_times'] ?? array();
        if (!is_array($times)) $times = array();

        // Forget refreshes that no longer hold anything up.
        foreach ($times as $id => $time) {
            if ($now - (int) $time >= self::SAME_CERT_GAP) unset($times[$id]);
        }

        $wait = $this->waitMessage($ssl_id, $times, (int) ($_SESSION['s_ssl_refresh_last'] ?? 0), $now);
        if ($wait !== '') return $wait;

        $times[$ssl_id] = $now;
        $_SESSION['s_ssl_refresh_times'] = $times;
        $_SESSION['s_ssl_refresh_last'] = $now;

        return '';
    }

    // How long to wait, from the times this session refreshed each certificate and its last refresh, or ''
    public function waitMessage($ssl_id, $times, $last, $now)
    {
        if (isset($times[$ssl_id]) && $now - (int) $times[$ssl_id] < self::SAME_CERT_GAP) {

            $seconds = self::SAME_CERT_GAP - ($now - (int) $times[$ssl_id]);
            return sprintf(ngettext('Please wait %d second before refreshing this SSL certificate again.',
                'Please wait %d seconds before refreshing this SSL certificate again.', $seconds), $seconds);

        }

        if ($now - $last < self::ANY_CERT_GAP) {

            $seconds = self::ANY_CERT_GAP - ($now - $last);
            return sprintf(ngettext('Please wait %d second before refreshing another SSL certificate.',
                'Please wait %d seconds before refreshing another SSL certificate.', $seconds), $seconds);

        }

        return '';
    }

    // Why a lookup that came with no certificate to save failed, translated
    public function failureMessage($code)
    {
        if ($code === 'deferred') return _('The lookup had to wait for another connection to the same address. Please try again shortly.');

        return $this->queue->getMessage($code);
    }

    /*
     * Compares the lookup with the certificate as it is now and writes the changes, all in one transaction (the lookup
     * is already done). The domain, then the certificate, then its status row are locked, in the same order as the SSL
     * Queue and the bulk delete, and nothing is saved when anything the lookup depended on changed meanwhile. Returns
     * array(success, reasons, changes).
     */
    protected function apply($before, $code, $details)
    {
        $pdo = $this->deeb->cnxx;

        try {

            $pdo->beginTransaction();

            $domain = $this->lockRow("SELECT domain FROM domains WHERE id = :id FOR UPDATE", (int) $before->domain_id);
            $current = $this->lockRow("SELECT `name`, domain_id, account_id, expiry_date, active, notes FROM ssl_certs WHERE id = :id FOR UPDATE", (int) $before->id);
            $status = $this->lockRow("SELECT revision, refreshed_issuer FROM ssl_cert_status WHERE ssl_id = :id FOR UPDATE", (int) $before->id);

            if (!$current) {

                $pdo->rollback();
                return array(false, array(_('That SSL certificate no longer exists.')), array());

            }

            $revision = $status ? (int) $status->revision : null;
            $before_revision = ($before->revision === null) ? null : (int) $before->revision;

            if (!$domain || (string) $domain->domain !== (string) $before->domain
                || (int) $current->domain_id !== (int) $before->domain_id
                || (string) $current->name !== (string) $before->name
                || (int) $current->account_id !== (int) $before->account_id
                || (string) $current->expiry_date !== (string) $before->expiry_date
                || (int) $current->active !== (int) $before->active
                || $revision !== $before_revision) {

                $pdo->rollback();
                return array(false, array(_('The SSL certificate changed while it was being refreshed. Please try again.')), array());

            }

            $compared = $this->compare($current, $code, $details, $status ? $status->refreshed_issuer : null);

            // A successful lookup always updates the status row, with the issuer in the same cleaned form compared above
            $details['issuer'] = $compared['issuer'];
            $this->queue->writeStatus((int) $before->id, $details, $code, true);

            if (empty($compared['changes'])) {

                $pdo->commit();
                return array(true, array(_('No changes were made.')), array());

            }

            // Newest note first, as the domain refresh, the bulk updater, and renewals add theirs (stored in English)
            $note = $this->sanitize->text($this->time->timeBasic() . ' - Refreshed from ' . $details['host'] . '. '
                . implode(' ', $compared['note_changes']));
            $notes = ((string) $current->notes === '') ? $note : $note . "\r\n\r\n" . $current->notes;

            $stmt = $pdo->prepare("
                UPDATE ssl_certs
                SET expiry_date = :expiry_date,
                    active = :active,
                    notes = :notes,
                    update_time = :update_time
                WHERE id = :ssl_id");
            $stmt->bindValue('expiry_date', $compared['expiry_date'], \PDO::PARAM_STR);
            $stmt->bindValue('active', $compared['active'], \PDO::PARAM_INT);
            $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
            $stmt->bindValue('update_time', $this->time->stamp(), \PDO::PARAM_STR);
            $stmt->bindValue('ssl_id', (int) $before->id, \PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();

            return array(true, array(), $compared['changes']);

        } catch (\Throwable $e) {

            try {
                if ($pdo->inTransaction()) $pdo->rollback();
            } catch (\Throwable $rollback_error) {
                // the database rolls back when the connection ends
            }

            // logging never stops the result being returned, even when the database connection was lost
            try {

                $log_message = 'Unable to refresh SSL certificate';
                $log_extra = array('SSL Certificate' => (string) $before->id, 'Error' => $e->getMessage());
                $this->log->critical($log_message, $log_extra);

            } catch (\Throwable $log_error) {

                // nothing more can be done

            }

            return array(false, array(_("The SSL certificate couldn't be updated. Please try again.")), array());

        }
    }

    // One row from a fixed query of this class, locked until the transaction ends, or null
    protected function lockRow($sql, $id)
    {
        $stmt = $this->deeb->cnxx->prepare($sql);
        $stmt->bindValue('id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();

        return $row ?: null;
    }

    /*
     * What a lookup with a certificate changes, from the certificate's current expiry date and status ($current) and its
     * status row's refreshed_issuer (null when neither an import nor a Refresh has recorded one): array('expiry_date',
     * 'active', 'issuer' (the cleaned plain issuer), 'changes' (lines for the user, translated and escaped), and
     * 'note_changes' (sentences for the certificate's notes, in English)).
     */
    public function compare($current, $code, $details, $refreshed_issuer)
    {
        $changes = array();
        $note_changes = array();
        $expiry_date = (string) $current->expiry_date;
        $active = (int) $current->active;

        if ((string) $details['expiry_date'] !== '' && (string) $details['expiry_date'] !== $expiry_date) {

            $changes[] = sprintf(_('Expiry date changed from %1$s to %2$s.'), $this->html($expiry_date), $this->html($details['expiry_date']));
            $note_changes[] = 'Expiry date changed from ' . $expiry_date . ' to ' . $details['expiry_date'] . '.';
            $expiry_date = (string) $details['expiry_date'];

        }

        $new_active = self::newStatus($active, $code);

        if ($new_active !== $active) {

            // TRANSLATORS: %1$s is the SSL certificate's status before the refresh, and %2$s is its new status.
            $changes[] = sprintf(_('Status changed from %1$s to %2$s.'), $this->html($this->statusName($active)), $this->html($this->statusName($new_active)));
            $note_changes[] = 'Status changed from ' . ($active === 1 ? 'Active' : 'Expired') . ' to ' . ($new_active === 1 ? 'Active' : 'Expired') . '.';
            $active = $new_active;

        }

        // Compared in its stored form, so a name such as "Example & Sons" isn't seen as changed every time
        $issuer = $this->lookup->cleanText((string) $details['issuer']);

        if ($refreshed_issuer !== null && $this->queue->storedText($issuer, 255) !== (string) $refreshed_issuer) {

            if ($issuer === '') {

                $changes[] = _('The certificate no longer names its issuer.');
                $note_changes[] = 'The certificate no longer names its issuer.';

            } else {

                // an issuer that already ends with a full stop (such as "Entrust, Inc.") doesn't get a second one, unless
                // the full stops are all it has
                $shown = (rtrim($issuer, '.') !== '') ? rtrim($issuer, '.') : $issuer;
                // TRANSLATORS: %s is the name of the company that issued the SSL certificate, such as Let's Encrypt.
                $changes[] = sprintf(_('The certificate is now issued by %s.'), $this->html($shown));
                $note_changes[] = 'The certificate is now issued by ' . $shown . '.';

            }

        }

        return array('expiry_date' => $expiry_date, 'active' => $active, 'issuer' => $issuer, 'changes' => $changes,
            'note_changes' => $note_changes);
    }

    /*
     * The status after a lookup: a matching certificate within its dates (ok or untrusted) makes Expired (0) Active (1),
     * and an expired one makes Active (1) Expired (0). Pending statuses (3, 4, and 5) are never changed.
     */
    public static function newStatus($active, $code)
    {
        $active = (int) $active;

        if ($active === 0 && ($code === 'ok' || $code === 'untrusted')) return 1;
        if ($active === 1 && $code === 'expired') return 0;

        return $active;
    }

    // The two statuses Refresh changes between, named as on the edit page
    protected function statusName($active)
    {
        return $active === 1 ? _('Active') : _('Expired');
    }

    // The trust warning shown with the result (only there, not in the certificate's notes)
    public function trustNotes($code, $details)
    {
        if ($code === 'expired') return array(_('This certificate has expired.'));
        if ($code !== 'untrusted') return array();

        return array(($details['reason'] ?? '') === 'self_signed' ? _('This certificate is self-signed.') : _("This certificate isn't trusted."));
    }

    /*
     * The certificate's named lock, taken without waiting. Named locks are shared by every database on the server, so
     * the name includes a short hash of this database's name.
     */
    protected function getLock($ssl_id)
    {
        $stmt = $this->deeb->cnxx->prepare("SELECT GET_LOCK(:lock_name, 0)");
        $stmt->bindValue('lock_name', $this->lockName($ssl_id), \PDO::PARAM_STR);
        $stmt->execute();

        return (string) $stmt->fetchColumn() === '1';
    }

    protected function releaseLock($ssl_id)
    {
        try {

            $stmt = $this->deeb->cnxx->prepare("SELECT RELEASE_LOCK(:lock_name)");
            $stmt->bindValue('lock_name', $this->lockName($ssl_id), \PDO::PARAM_STR);
            $stmt->execute();

        } catch (\Throwable $e) {

            // the database releases the lock when the connection ends

        }
    }

    protected function lockName($ssl_id)
    {
        return 'domainmod_ssl_refresh_' . substr(md5(DB_NAME), 0, 12) . '_' . (int) $ssl_id;
    }

    protected function html($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

} //@formatter:on
