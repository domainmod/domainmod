<?php
/**
 * /assets/edit/hosting-type.php
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
$log = new DomainMOD\Log('/assets/edit/hosting-type.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-hosting-type.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Before the database update there are no hosting tables to edit
if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';
    header("Location: ../index.php");
    exit;

}

$del = (int) ($_GET['del'] ?? 0);

$htid = (int) ($_GET['htid'] ?? 0);

$new_type = isset($_REQUEST['new_type']) ? $sanitize->text($_REQUEST['new_type']) : '';
$new_notes = isset($_REQUEST['new_notes']) ? $sanitize->text($_REQUEST['new_notes']) : '';
$new_htid = (int) ($_REQUEST['new_htid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    if ($validate->text($new_type)) {

        $stmt = $pdo->prepare("
            UPDATE hosting_types
            SET type = :new_type,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :new_htid");
        $stmt->bindValue('new_type', $new_type, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('new_htid', $new_htid, PDO::PARAM_INT);
        $stmt->execute();

        $htid = $new_htid;

        $_SESSION['s_message_success'] .= sprintf(_('Service Type %s updated'), $new_type) . '<BR>';

        header("Location: ../hosting-types.php");
        exit;

    } else {

        $form->addFieldError('new_type', _('Enter the Service Type name'));

    }

} else {

    $stmt = $pdo->prepare("
        SELECT type, notes
        FROM hosting_types
        WHERE id = :htid");
    $stmt->bindValue('htid', $htid, PDO::PARAM_INT);
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

    /*
     * The delete holds the hosting lock (see DomainMOD\Hosting::lock()), taken before its transaction, so no hosting
     * service can be given this service type, and no fee added for it, between the check and the delete
     */
    if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

        $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';
        header("Location: hosting-type.php?htid=" . $htid);
        exit;

    }

    $history_refused = '';
    $deleted = false;

    try {

        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $pdo->beginTransaction();

        /*
         * Refused while any hosting service uses the type. Every writer of hosting services holds the hosting lock, so
         * this plain read can't be overtaken. Otherwise the services still pointing at this type's fees are captured for
         * the Change History (there are none, since a service on one of its fees has this type, but the capture keeps
         * the rule every fee delete follows), then the fees go, then the type.
         */
        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET, $_SESSION['s_user_id'], 'hosting_type_delete');
        $stmt = $pdo->prepare("
            SELECT id
            FROM hosting_services
            WHERE type_id = :htid
            LIMIT 1");
        $stmt->bindValue('htid', $htid, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn()) $history_refused = _('This Service Type has hosting services associated with it and cannot be deleted.');
        $stmt->closeCursor();
        $history_costs = $history_refused === '' ? $history_op->captureCosts('hosting', " AND i.fee_id IN (SELECT id FROM hosting_fees WHERE type_id = :history_asset_id)", array('history_asset_id' => $htid)) : null;

        if ($history_refused !== '') {

            if ($pdo->InTransaction()) $pdo->rollback();

        } else {

            $stmt = $pdo->prepare("
                SELECT id
                FROM hosting_fees
                WHERE type_id = :htid
                FOR UPDATE");
            $stmt->bindValue('htid', $htid, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            $stmt = $pdo->prepare("
                DELETE FROM hosting_fees
                WHERE type_id = :htid");
            $stmt->bindValue('htid', $htid, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("
                DELETE FROM hosting_types
                WHERE id = :htid");
            $stmt->bindValue('htid', $htid, PDO::PARAM_INT);
            $stmt->execute();

            $history_op->compareCosts($history_costs);
            $history_op->finish();

            if ($pdo->InTransaction()) $pdo->commit();

            $deleted = true;

        }

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();

        $log_message = 'Unable to delete service type';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

    } finally {

        // The action goes (and lets go of anything it holds) before the hosting lock does
        $history_op = null;
        DomainMOD\Hosting::unlock($pdo);

    }

    if ($history_refused !== '') {

        $_SESSION['s_message_danger'] .= $history_refused . '<BR>';
        header("Location: hosting-type.php?htid=" . $htid);
        exit;

    }

    if ($deleted) {

        // Whether any hosting service is missing a fee, worked out again after any fee change
        $queryB = new DomainMOD\QueryBuild();

        $sql = $queryB->missingFees('hosting_services');
        $_SESSION['s_missing_hosting_fees'] = $system->checkForRows($sql);

        $_SESSION['s_message_success'] .= sprintf(_('Service Type %s deleted'), $new_type) . '<BR>';

        header("Location: ../hosting-types.php");
        exit;

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
echo $form->showInputText('new_type', _('Service Type') . ' (100)', '', $unsanitize->text($new_type), '100', '', '1', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_htid', $htid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->dangerZone(_('Service Type'), $new_type, 'hosting-type.php?htid=' . $htid . '&del=1', _('Delete this service type'), sprintf(_('This removes %s and its fees. It can only be deleted when no hosting services use it. It can\'t be undone.'), '<strong>' . htmlentities(html_entity_decode($new_type, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>'), 2);
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
