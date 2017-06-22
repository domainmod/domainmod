<?php
/**
 * /assets/edit/ssl-type.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once('../../_includes/start-session.inc.php');
require_once('../../_includes/init.inc.php');

require_once(DIR_ROOT . '/classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/debug.inc.php');
require_once(DIR_INC . '/settings/assets-edit-ssl-type.inc.php');
require_once(DIR_INC . '/database.inc.php');

$pdo = $system->db();
$system->authCheck();

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$ssltid = $_GET['ssltid'];

$new_type = $_REQUEST['new_type'];
$new_notes = $_REQUEST['new_notes'];
$new_ssltid = $_REQUEST['new_ssltid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    if ($new_type != "") {

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

        $_SESSION['s_message_success'] .= "SSL Type " . $new_type . " Updated<BR>";

        header("Location: ../ssl-types.php");
        exit;

    } else {

        $_SESSION['s_message_danger'] .= "Enter the Type name<BR>";

    }

} else {

    $stmt = $pdo->prepare("
        SELECT type, notes
        FROM ssl_cert_types
        WHERE id = :ssltid");
    $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result) {

        $new_type = $result->type;
        $new_notes = $result->notes;

    }

}

if ($del == "1") {

    $stmt = $pdo->prepare("
        SELECT type_id
        FROM ssl_certs
        WHERE type_id = :ssltid
        LIMIT 1");
    $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $_SESSION['s_message_danger'] .= "This Type has SSL certificates associated with it and cannot be deleted<BR>";

    } else {

        $_SESSION['s_message_danger'] .= "Are you sure you want to delete this SSL Type?<BR><BR><a
            href=\"ssl-type.php?ssltid=" . $ssltid . "&really_del=1\">YES, REALLY DELETE THIS SSL TYPE</a><BR>";

    }

}

if ($really_del == "1") {

    $stmt = $pdo->prepare("
        DELETE FROM ssl_cert_types
        WHERE id = :ssltid");
    $stmt->bindValue('ssltid', $ssltid, PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['s_message_success'] .= "SSL Type " . $new_type . " Deleted<BR>";

    header("Location: ../ssl-types.php");
    exit;

}
?>
<?php require_once(DIR_INC . '/doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once(DIR_INC . '/layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . '/layout/header.inc.php'); ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_type', 'Type (100)', '', $new_type, '100', '', '1', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('new_ssltid', $ssltid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="ssl-type.php?ssltid=<?php echo urlencode($ssltid); ?>&del=1">DELETE THIS SSL TYPE</a>
<?php require_once(DIR_INC . '/layout/footer.inc.php'); ?>
</body>
</html>
