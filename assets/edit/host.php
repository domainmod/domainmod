<?php
/**
 * /assets/edit/host.php
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
?>
<?php
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/assets/edit/host.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-host.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$whid = (int) ($_GET['whid'] ?? 0);
$new_host = isset($_REQUEST['new_host']) ? $sanitize->text($_REQUEST['new_host']) : '';
$new_url = isset($_POST['new_url']) ? $sanitize->text($_POST['new_url']) : '';
$new_notes = isset($_REQUEST['new_notes']) ? $sanitize->text($_REQUEST['new_notes']) : '';
$new_whid = (int) ($_REQUEST['new_whid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    if ($validate->text($new_host)) {

        $stmt = $pdo->prepare("
            UPDATE hosting
            SET `name` = :new_host,
                url = :new_url,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :new_whid");
        $stmt->bindValue('new_host', $new_host, PDO::PARAM_STR);
        $stmt->bindValue('new_url', $new_url, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('new_whid', $new_whid, PDO::PARAM_INT);
        $stmt->execute();

        $whid = $new_whid;

        $_SESSION['s_message_success'] .= sprintf(_('Hosting Provider %s updated'), $new_host) . '<BR>';

        header("Location: ../hosting.php");
        exit;

    } else {

        if (!$validate->text($new_host)) $form->addFieldError('new_host', _("Enter the hosting provider's name"));

    }

} else {

    $stmt = $pdo->prepare("
        SELECT `name`, url, notes
        FROM hosting
        WHERE id = :whid");
    $stmt->bindValue('whid', $whid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_host = $result->name;
        $new_url = $result->url;
        $new_notes = $result->notes;

    }

}

if ($del === 1) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    // Before the database update, domains still name a hosting provider, and one a domain uses can't be deleted. Once
    // it's run, domains choose a hosting service instead, and only hosting accounts and services can use a provider.
    $hosting_available = DomainMOD\Hosting::isAvailable($pdo);
    $used_by_domains = false;
    if (DomainMOD\Hosting::legacyDomainColumn($pdo)) {

        $stmt = $pdo->prepare( // Legacy: domains.hosting_id before 4.27.0
            "SELECT hosting_id
            FROM domains
            WHERE hosting_id = :whid
            LIMIT 1");
        $stmt->bindValue('whid', $whid, PDO::PARAM_INT);
        $stmt->execute();
        $used_by_domains = (bool) $stmt->fetchColumn();
        $stmt->closeCursor();

    }

    if ($used_by_domains) {

        $_SESSION['s_message_danger'] .= _('This Hosting Provider has domains associated with it and cannot be deleted.') . '<BR>';

    } elseif (!$hosting_available) {

        // Before the database update there are no hosting tables, so only domains can use a hosting provider
        $stmt = $pdo->prepare("
            DELETE FROM hosting
            WHERE id = :whid");
        $stmt->bindValue('whid', $whid, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_message_success'] .= sprintf(_('Hosting Provider %s deleted'), $new_host) . '<BR>';

        header("Location: ../hosting.php");
        exit;

    } elseif (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

        // The delete holds the hosting lock, taken before its transaction, so no hosting service, hosting account, or
        // fee can be given this hosting provider between the checks and the delete
        $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';

    } else {

        $history_refused = '';
        $deleted = false;

        try {

            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $pdo->beginTransaction();

            /*
             * Refused while any hosting service or hosting account uses it (every writer of those holds the
             * hosting lock, so these reads can't be overtaken). Otherwise the services still pointing at its fees are
             * captured for the Change History (there are none, since a service on one of its fees has this provider, but
             * the capture keeps the rule every fee delete follows), then its fees go, then the hosting provider.
             */
            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET, $_SESSION['s_user_id'], 'hosting_provider_delete');

            $stmt = $pdo->prepare("
                SELECT id
                FROM hosting_services
                WHERE hosting_id = :whid
                LIMIT 1");
            $stmt->bindValue('whid', $whid, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn()) $history_refused = _('This Hosting Provider has hosting services associated with it and cannot be deleted.');
            $stmt->closeCursor();

            $stmt = $pdo->prepare("
                SELECT id
                FROM hosting_provider_accounts
                WHERE hosting_id = :whid
                LIMIT 1");
            $stmt->bindValue('whid', $whid, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn()) $history_refused = _('This Hosting Provider has hosting accounts associated with it and cannot be deleted.');
            $stmt->closeCursor();

            $history_costs = $history_refused === '' ? $history_op->captureCosts('hosting', " AND i.fee_id IN (SELECT id FROM hosting_fees WHERE hosting_id = :history_asset_id)", array('history_asset_id' => $whid)) : null;

            if ($history_refused !== '') {

                if ($pdo->InTransaction()) $pdo->rollback();

            } else {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM hosting_fees
                    WHERE hosting_id = :whid
                    FOR UPDATE");
                $stmt->bindValue('whid', $whid, PDO::PARAM_INT);
                $stmt->execute();
                $stmt->closeCursor();

                $stmt = $pdo->prepare("
                    DELETE FROM hosting_fees
                    WHERE hosting_id = :whid");
                $stmt->bindValue('whid', $whid, PDO::PARAM_INT);
                $stmt->execute();

                $stmt = $pdo->prepare("
                    DELETE FROM hosting
                    WHERE id = :whid");
                $stmt->bindValue('whid', $whid, PDO::PARAM_INT);
                $stmt->execute();

                $history_op->compareCosts($history_costs);
                $history_op->finish();

                if ($pdo->InTransaction()) $pdo->commit();

                $deleted = true;

            }

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to delete hosting provider';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            // No message for a conflict, whose own page explains it (see domainmod_restore_is_conflict())
            if (!domainmod_restore_is_conflict($e)) $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        } finally {

            // The action goes (and lets go of anything it holds) before the hosting lock does
            $history_op = null;
            DomainMOD\Hosting::unlock($pdo);

        }

        if ($history_refused !== '') {

            $_SESSION['s_message_danger'] .= $history_refused . '<BR>';

        }

        if ($deleted) {

            // Whether any hosting service is missing a fee, worked out again after any fee change
            $queryB = new DomainMOD\QueryBuild();

            $sql = $queryB->missingFees('hosting_services');
            $_SESSION['s_missing_hosting_fees'] = $system->checkForRows($sql);

            $_SESSION['s_message_success'] .= sprintf(_('Hosting Provider %s deleted'), $new_host) . '<BR>';

            header("Location: ../hosting.php");
            exit;

        }

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_host', _('Hosting Provider Name') . ' (100)', '', $unsanitize->text($new_host), '100', '', '1', '', '');
echo $form->showInputText('new_url', _("Hosting Provider's URL") . ' (100)', '', $unsanitize->text($new_url), '100', '', '', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_whid', $whid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

// Once the database has the hosting tables, hosting services and hosting accounts block the delete instead of domains
// (which no longer name a provider), and the hosting provider's fees go with it
$host_name_html = '<strong>' . htmlentities(html_entity_decode($new_host, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>';
if (DomainMOD\Hosting::isAvailable($pdo)) {
    $host_delete_text = sprintf(_('This removes %s and its fees. It can only be deleted when no hosting services or hosting accounts use it. It can\'t be undone.'), $host_name_html);
} else {
    $host_delete_text = sprintf(_('This removes %s. It can only be deleted when no domains use it. It can\'t be undone.'), $host_name_html);
}
$layout->dangerZone(_('Hosting Provider'), $new_host, 'host.php?whid=' . $whid . '&del=1', _('Delete this hosting provider'), $host_delete_text, 2);
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
