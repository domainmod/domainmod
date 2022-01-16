<?php
/**
 * /assets/edit/ssl-type.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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
require_once DIR_INC . '/settings/assets-edit-ssl-type.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) $_GET['del'];

$ssltid = (int) $_GET['ssltid'];

$new_type = $sanitize->text($_REQUEST['new_type']);
$new_notes = $sanitize->text($_REQUEST['new_notes']);
$new_ssltid = (int) $_REQUEST['new_ssltid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

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

        $_SESSION['s_message_danger'] .= _('Enter the Type name') . '<BR>';

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

    $stmt = $pdo->prepare("
        SELECT type_id
        FROM ssl_certs
        WHERE type_id = :ssltid
        LIMIT 1");
    $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $_SESSION['s_message_danger'] .= _('This Type has SSL Certificates associated with it and cannot be deleted') . '<BR>';

    } else {

        $stmt = $pdo->prepare("
            DELETE FROM ssl_cert_types
            WHERE id = :ssltid");
        $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_message_success'] .= sprintf(_('SSL Type %s deleted'), $new_type) . '<BR>';

        header("Location: ../ssl-types.php");
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
echo $form->showInputText('new_type', _('Type') . ' (100)', '', $unsanitize->text($new_type), '100', '', '1', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_ssltid', $ssltid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->deleteButton(_('SSL Type'), $new_type, 'ssl-type.php?ssltid=' . $ssltid . '&del=1');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
