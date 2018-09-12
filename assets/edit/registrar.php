<?php
/**
 * /assets/edit/registrar.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
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
$form = new DomainMOD\Form();
$log = new DomainMOD\Log('/assets/edit/registrar.php');
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-registrar.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$rid = $_REQUEST['rid'];
$new_registrar = $_POST['new_registrar'];
$new_url = $_POST['new_url'];
$new_api_registrar_id = $_POST['new_api_registrar_id'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    if ($new_registrar != "") {

        $stmt = $pdo->prepare("
            UPDATE registrars
            SET `name` = :new_registrar,
                url = :new_url,
                api_registrar_id = :new_api_registrar_id,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :rid");
        $stmt->bindValue('new_registrar', $new_registrar, PDO::PARAM_STR);
        $stmt->bindValue('new_url', $new_url, PDO::PARAM_STR);
        $stmt->bindValue('new_api_registrar_id', $new_api_registrar_id, PDO::PARAM_INT);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_message_success'] .= "Registrar " . $new_registrar . " Updated<BR>";

        header("Location: ../registrars.php");
        exit;

    } else {

        if ($new_registrar == "") $_SESSION['s_message_danger'] .= "Enter the registrar name<BR>";

    }

} else {

    $stmt = $pdo->prepare("
        SELECT `name`, url, api_registrar_id, notes
        FROM registrars
        WHERE id = :rid");
    $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_registrar = $result->name;
        $new_url = $result->url;
        $new_api_registrar_id = $result->api_registrar_id;
        $new_notes = $result->notes;

    }

}

if ($del == "1") {

    $stmt = $pdo->prepare("
        SELECT registrar_id
        FROM registrar_accounts
        WHERE registrar_id = :rid
        LIMIT 1");
    $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $existing_registrar_accounts = 1;

    }

    $stmt = $pdo->prepare("
        SELECT registrar_id
        FROM domains
        WHERE registrar_id = :rid
        LIMIT 1");
    $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $existing_domains = 1;

    }

    if ($existing_registrar_accounts > 0 || $existing_domains > 0) {

        if ($existing_registrar_accounts > 0) $_SESSION['s_message_danger'] .= "This Registrar has Registrar Accounts associated with it and cannot be deleted<BR>";
        if ($existing_domains > 0) $_SESSION['s_message_danger'] .= "This Registrar has domains associated with it and cannot be deleted<BR>";

    } else {

        $_SESSION['s_message_danger'] .= "Are you sure you want to delete this Registrar?<BR><BR><a
            href=\"registrar.php?rid=" . $rid . "&really_del=1\">YES, REALLY DELETE THIS REGISTRAR</a><BR>";

    }

}

if ($really_del == "1") {

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            DELETE FROM fees
            WHERE registrar_id = :rid");
        $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            DELETE FROM registrar_accounts
            WHERE registrar_id = :rid");
        $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            DELETE FROM registrars
            WHERE id = :rid");
        $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
        $stmt->execute();

        $system->checkExistingAssets();

        $pdo->commit();

        $_SESSION['s_message_success'] .= "Registrar " . $new_registrar . " Deleted<BR>";

        header("Location: ../registrars.php");
        exit;

    } catch (Exception $e) {

        $pdo->rollback();

        $log_message = 'Unable to delete registrar';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_registrar', 'Registrar Name (100)', '', $new_registrar, '100', '', '1', '', '');
echo $form->showInputText('new_url', 'Registrar\'s URL (100)', '', $new_url, '100', '', '', '', '');

$result = $pdo->query("
    SELECT id, `name`
    FROM api_registrars
    ORDER BY `name` ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_api_registrar_id', 'API Support', 'If the registrar has an API please select it from the list below.', '', '');

    echo $form->showDropdownOption('0', 'n/a', '0');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_api_registrar_id);

    }

    echo $form->showDropdownBottom('');

}

echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('rid', $rid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="registrar.php?rid=<?php echo urlencode($rid); ?>&del=1">DELETE THIS REGISTRAR</a>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
