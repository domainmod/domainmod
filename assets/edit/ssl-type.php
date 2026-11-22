<?php
/**
 * /assets/edit/ssl-type.php
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
$log = new DomainMOD\Log('/assets/edit/ssl-type.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-ssl-type.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$ssltid = (int) ($_GET['ssltid'] ?? 0);

$new_type = isset($_REQUEST['new_type']) ? $sanitize->text($_REQUEST['new_type']) : '';
$new_notes = isset($_REQUEST['new_notes']) ? $sanitize->text($_REQUEST['new_notes']) : '';
$new_ssltid = (int) ($_REQUEST['new_ssltid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    if ($validate->text($new_type)) {

        $stmt = $pdo->prepare("
            UPDATE ssl_cert_types
            SET type = :new_type,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :new_ssltid");
        $stmt->bindValue('new_type', $new_type, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('new_ssltid', $new_ssltid, PDO::PARAM_INT);
        $stmt->execute();

        $ssltid = $new_ssltid;

        $_SESSION['s_message_success'] .= sprintf(_('SSL Type %s updated'), $new_type) . '<BR>';

        header("Location: ../ssl-types.php");
        exit;

    } else {

        $form->addFieldError('new_type', _('Enter the Type name'));

    }

} else {

    $stmt = $pdo->prepare("
        SELECT type, notes
        FROM ssl_cert_types
        WHERE id = :ssltid");
    $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_type = $result->type;
        $new_notes = $result->notes;

    }

}

if ($del === 1) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    $stmt = $pdo->prepare("
        SELECT type_id
        FROM ssl_certs
        WHERE type_id = :ssltid
        LIMIT 1");
    $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $_SESSION['s_message_danger'] .= _('This Type has SSL Certificates associated with it and cannot be deleted.') . '<BR>';

    } else {

        try {

            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $pdo->beginTransaction();

            /*
             * The checks above run again inside the transaction, as locking reads, in the lock order every writer uses:
             * the items first, then the items still pointing at this type's fees (captured for the Change History,
             * whose costs lose their currency when those fees go), and only then the assets. Each check that finds
             * something refuses with the same message as above.
             */
            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET, $_SESSION['s_user_id'], 'ssl_type_delete');
            // One fee change at a time (the fee pages take the same lock first), so they never deadlock
            $history_op->holdNamedLock('domainmod_fee_changes_' . substr(md5(DB_NAME), 0, 12));
            $history_refused = '';
            $stmt = $pdo->prepare("
                SELECT id
                FROM ssl_certs
                WHERE type_id = :ssltid
                LIMIT 1
                FOR UPDATE");
            $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn()) $history_refused = _('This Type has SSL Certificates associated with it and cannot be deleted.');
            $stmt->closeCursor();
            $history_costs = $history_refused === '' ? $history_op->captureCosts('ssl', " AND i.fee_id IN (SELECT id FROM ssl_fees WHERE type_id = :history_asset_id)", array('history_asset_id' => $ssltid)) : null;

            if ($history_refused !== '') {

                if ($pdo->InTransaction()) $pdo->rollback();
                $_SESSION['s_message_danger'] .= $history_refused . '<BR>';
                header("Location: ssl-type.php?ssltid=" . $ssltid);
                exit;

            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM ssl_fees
                WHERE type_id = :ssltid
                FOR UPDATE");
            $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            $stmt = $pdo->prepare("
                DELETE FROM ssl_fees
                WHERE type_id = :ssltid");
            $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("
                DELETE FROM ssl_cert_types
                WHERE id = :ssltid");
            $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
            $stmt->execute();

            $history_op->compareCosts($history_costs);
            $history_op->finish();

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('SSL Type %s deleted'), $new_type) . '<BR>';

            header("Location: ../ssl-types.php");
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to delete SSL type';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            // No message for a conflict, whose own page explains it (see domainmod_restore_is_conflict())
            if (!domainmod_restore_is_conflict($e)) $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

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
echo $form->showInputText('new_type', _('Type') . ' (100)', '', $unsanitize->text($new_type), '100', '', '1', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_ssltid', $ssltid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->dangerZone(_('SSL Type'), $new_type, 'ssl-type.php?ssltid=' . $ssltid . '&del=1', _('Delete this SSL type'), sprintf(_('This removes %s and its fees. It can only be deleted when no SSL certificates use it. It can\'t be undone.'), '<strong>' . htmlentities(html_entity_decode($new_type, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>'), 2);
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
