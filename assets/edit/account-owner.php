<?php
/**
 * /assets/edit/account-owner.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-owner.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$oid = (int) ($_GET['oid'] ?? 0);

$new_owner = isset($_POST['new_owner']) ? $sanitize->text($_POST['new_owner']) : '';
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';
$new_oid = (int) ($_POST['new_oid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    if ($validate->text($new_owner)) {

        $stmt = $pdo->prepare("
            UPDATE owners
            SET `name` = :new_owner,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :new_oid");
        $stmt->bindValue('new_owner', $new_owner, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('new_oid', $new_oid, PDO::PARAM_INT);
        $stmt->execute();

        $oid = $new_oid;

        $_SESSION['s_message_success'] .= sprintf(_('Owner %s updated'), $new_owner) . '<BR>';

        header("Location: ../account-owners.php");
        exit;

    } else {

        $_SESSION['s_message_danger'] .= _("Enter the owner's name") . '<BR>';

    }

} else {

    $stmt = $pdo->prepare("
        SELECT `name`, notes
        FROM owners
        WHERE id = :oid");
    $stmt->bindValue('oid', $oid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    $new_owner = $result->name;
    $new_notes = $result->notes;

}

if ($del === 1) {

    $stmt = $pdo->prepare("
        SELECT owner_id
        FROM registrar_accounts
        WHERE owner_id = :oid
        LIMIT 1");
    $stmt->bindValue('oid', $oid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $existing_registrar_accounts = 1;

    }

    $stmt = $pdo->prepare("
        SELECT owner_id
        FROM ssl_accounts
        WHERE owner_id = :oid
        LIMIT 1");
    $stmt->bindValue('oid', $oid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $existing_ssl_accounts = 1;

    }

    $stmt = $pdo->prepare("
        SELECT owner_id
        FROM domains
        WHERE owner_id = :oid
        LIMIT 1");
    $stmt->bindValue('oid', $oid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $existing_domains = 1;

    }

    $stmt = $pdo->prepare("
        SELECT owner_id
        FROM ssl_certs
        WHERE owner_id = :oid
        LIMIT 1");
    $stmt->bindValue('oid', $oid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $existing_ssl_certs = 1;

    }

    if ($existing_registrar_accounts > 0 || $existing_ssl_accounts > 0 || $existing_domains > 0 ||
        $existing_ssl_certs > 0
    ) {

        if ($existing_registrar_accounts > 0) {
            $_SESSION['s_message_danger'] .= _('This Owner has registrar accounts associated with it and cannot be deleted') . '<BR>';
        }

        if ($existing_domains > 0) {
            $_SESSION['s_message_danger'] .= _('This Owner has domains associated with it and cannot be deleted') . '<BR>';
        }

        if ($existing_ssl_accounts > 0) {
            $_SESSION['s_message_danger'] .= _('This Owner has SSL accounts associated with it and cannot be deleted') . '<BR>';
        }

        if ($existing_ssl_certs > 0) {
            $_SESSION['s_message_danger'] .= _('This Owner has SSL certificates associated with it and cannot be deleted') . '<BR>';
        }

    } else {

        $stmt = $pdo->prepare("
            DELETE FROM owners
            WHERE id = :oid");
        $stmt->bindValue('oid', $oid, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_message_success'] .= sprintf(_('Owner %s deleted'), $new_owner) . '<BR>';

        header("Location: ../account-owners.php");
        exit;

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_owner', _('Owner Name') . ' (100)', '', $unsanitize->text($new_owner), '100', '', '1', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_oid', $oid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->deleteButton(_('Owner'), $new_owner, 'account-owner.php?oid=' . $oid . '&del=1');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
