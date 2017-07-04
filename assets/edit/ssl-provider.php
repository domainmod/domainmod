<?php
/**
 * /assets/edit/ssl-provider.php
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';

require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$log = new DomainMOD\Log('/assets/edit/ssl-provider.php');
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-ssl-provider.inc.php';

$pdo = $system->db();
$system->authCheck();

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslpid = $_GET['sslpid'];
$new_ssl_provider = $_POST['new_ssl_provider'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];
$new_sslpid = $_POST['new_sslpid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    if ($new_ssl_provider != "") {

        $stmt = $pdo->prepare("
            UPDATE ssl_providers
            SET `name` = :new_ssl_provider,
                url = :new_url,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :new_sslpid");
        $stmt->bindValue('new_ssl_provider', $new_ssl_provider, PDO::PARAM_STR);
        $stmt->bindValue('new_url', $new_url, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('new_sslpid', $new_sslpid, PDO::PARAM_INT);
        $stmt->execute();

        $sslpid = $new_sslpid;

        $_SESSION['s_message_success'] .= "SSL Provider " . $new_ssl_provider . " Updated<BR>";

        header("Location: ../ssl-providers.php");
        exit;

    } else {

        if ($new_ssl_provider == "") $_SESSION['s_message_danger'] .= "Enter the SSL provider's name<BR>";

    }

} else {

    $stmt = $pdo->prepare("
        SELECT `name`, url, notes
        FROM ssl_providers
        WHERE id = :sslpid");
    $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result) {

        $new_ssl_provider = $result->name;
        $new_url = $result->url;
        $new_notes = $result->notes;

    }

}

if ($del == "1") {

    $stmt = $pdo->prepare("
        SELECT ssl_provider_id
        FROM ssl_accounts
        WHERE ssl_provider_id = :sslpid
        LIMIT 1");
    $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $existing_ssl_provider_accounts = 1;

    }

    $stmt = $pdo->prepare("
        SELECT ssl_provider_id
        FROM ssl_certs
        WHERE ssl_provider_id = :sslpid
        LIMIT 1");
    $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $existing_ssl_certs = 1;

    }

    if ($existing_ssl_provider_accounts > 0 || $existing_ssl_certs > 0) {

        if ($existing_ssl_provider_accounts > 0) $_SESSION['s_message_danger'] .= "This SSL Provider has Accounts
            associated with it and cannot be deleted<BR>";
        if ($existing_ssl_certs > 0) $_SESSION['s_message_danger'] .= "This SSL Provider has SSL Certificates associated
            with it and cannot be deleted<BR>";

    } else {

        $_SESSION['s_message_danger'] .= "Are you sure you want to delete this SSL Provider?<BR><BR><a
            href=\"ssl-provider.php?sslpid=" . $sslpid . "&really_del=1\">YES, REALLY DELETE THIS SSL PROVIDER</a><BR>";

    }

}

if ($really_del == "1") {

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            DELETE FROM ssl_fees
            WHERE ssl_provider_id = :sslpid");
        $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            DELETE FROM ssl_accounts
            WHERE ssl_provider_id = :sslpid");
        $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            DELETE FROM ssl_providers
            WHERE id = :sslpid");
        $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
        $stmt->execute();

        $system->checkExistingAssets();

        $pdo->commit();

        $_SESSION['s_message_success'] .= "SSL Provider " . $new_ssl_provider . " Deleted<BR>";

        header("Location: ../ssl-providers.php");
        exit;

    } catch (Exception $e) {

        $pdo->rollback();

        $log_message = 'Unable to delete SSL provider';
        $log_extra = array('Error' => $e);
        $log->error($log_message, $log_extra);

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
echo $form->showInputText('new_ssl_provider', 'SSL Provider Name (100)', '', $new_ssl_provider, '100', '', '1', '', '');
echo $form->showInputText('new_url', 'SSL Provider\'s URL', '', $new_url, '100', '', '', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('new_sslpid', $sslpid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="ssl-provider.php?sslpid=<?php echo urlencode($sslpid); ?>&del=1">DELETE THIS SSL PROVIDER</a>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
