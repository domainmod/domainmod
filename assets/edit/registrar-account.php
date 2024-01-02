<?php
/**
 * /assets/edit/registrar-account.php
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
$log = new DomainMOD\Log('/assets/edit/registrar-account.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-registrar-account.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$raid = (int) ($_GET['raid'] ?? 0);
$new_owner_id = (int) ($_POST['new_owner_id'] ?? 0);
$new_registrar_id = (int) ($_POST['new_registrar_id'] ?? 0);
$new_email_address = isset($_POST['new_email_address']) ? $sanitize->text($_POST['new_email_address']) : '';
$new_username = isset($_POST['new_username']) ? $sanitize->text($_POST['new_username']) : '';
$new_password = isset($_POST['new_password']) ? $sanitize->text($_POST['new_password']) : '';
$new_account_id = isset($_POST['new_account_id']) ? $sanitize->text($_POST['new_account_id']) : '';
$new_reseller = (int) ($_POST['new_reseller'] ?? 0);
$new_reseller_id = isset($_POST['new_reseller_id']) ? $sanitize->text($_POST['new_reseller_id']) : '';
$new_api_app_name = isset($_POST['new_api_app_name']) ? $sanitize->text($_POST['new_api_app_name']) : '';
$new_api_key = isset($_POST['new_api_key']) ? $sanitize->text($_POST['new_api_key']) : '';
$new_api_secret = isset($_POST['new_api_secret']) ? $sanitize->text($_POST['new_api_secret']) : '';
$new_api_ip_id = (int) ($_POST['new_api_ip_id'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';
$new_raid = (int) ($_POST['new_raid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    if ($validate->text($new_username) && $new_owner_id !== 0 && $new_registrar_id !== 0) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE registrar_accounts
                SET owner_id = :new_owner_id,
                    registrar_id = :new_registrar_id,
                    email_address = :new_email_address,
                    username = :new_username,
                    `password` = :new_password,
                    account_id = :new_account_id,
                    reseller = :new_reseller,
                    reseller_id = :new_reseller_id,
                    api_app_name = :new_api_app_name,
                    api_key = :new_api_key,
                    api_secret = :new_api_secret,
                    api_ip_id = :new_api_ip_id,
                    notes = :new_notes,
                    update_time = :timestamp
                WHERE id = :new_raid");
            $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
            $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
            $stmt->bindValue('new_email_address', $new_email_address, PDO::PARAM_STR);
            $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
            $stmt->bindValue('new_password', $new_password, PDO::PARAM_STR);
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_STR);
            $stmt->bindValue('new_reseller', $new_reseller, PDO::PARAM_INT);
            $stmt->bindValue('new_reseller_id', $new_reseller_id, PDO::PARAM_STR);
            $stmt->bindValue('new_api_app_name', $new_api_app_name, PDO::PARAM_STR);
            $stmt->bindValue('new_api_key', $new_api_key, PDO::PARAM_STR);
            $stmt->bindValue('new_api_secret', $new_api_secret, PDO::PARAM_STR);
            $stmt->bindValue('new_api_ip_id', $new_api_ip_id, PDO::PARAM_INT);
            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
            $timestamp = $time->stamp();
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('new_raid', $new_raid, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("
                UPDATE domains
                SET owner_id = :new_owner_id,
                    registrar_id = :new_registrar_id
                WHERE account_id = :new_raid");
            $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
            $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
            $stmt->bindValue('new_raid', $new_raid, PDO::PARAM_INT);
            $stmt->execute();

            $raid = $new_raid;

            $temp_registrar = $assets->getRegistrar($new_registrar_id);
            $temp_owner = $assets->getOwner($new_owner_id);

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('Registrar Account %s (%s, %s) updated'), $new_username, $temp_registrar, $temp_owner) . '<BR>';

            header("Location: ../registrar-accounts.php");
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to update registrar account';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    } else {

        if ($new_owner_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the Owner') . '<BR>';

        }

        if ($new_registrar_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the Registrar') . '<BR>';

        }

        if (!$validate->text($new_username)) {
            $_SESSION['s_message_danger'] .= _('Enter the username') . '<BR>';
        }

    }

} else {

    $stmt = $pdo->prepare("
        SELECT owner_id, registrar_id, email_address, username, `password`, account_id, reseller, reseller_id,
               api_app_name, api_key, api_secret, api_ip_id, notes
        FROM registrar_accounts
        WHERE id = :raid");
    $stmt->bindValue('raid', $raid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_owner_id = $result->owner_id;
        $new_registrar_id = $result->registrar_id;
        $new_email_address = $result->email_address;
        $new_username = $result->username;
        $new_password = $result->password;
        $new_account_id = $result->account_id;
        $new_reseller = $result->reseller;
        $new_reseller_id = $result->reseller_id;
        $new_api_app_name = $result->api_app_name;
        $new_api_key = $result->api_key;
        $new_api_secret = $result->api_secret;
        $new_api_ip_id = $result->api_ip_id;
        $new_notes = $result->notes;

    }

}

if ($del === 1) {

    $stmt = $pdo->prepare("
        SELECT account_id
        FROM domains
        WHERE account_id = :raid
        LIMIT 1");
    $stmt->bindValue('raid', $raid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $_SESSION['s_message_danger'] .= _('This Registrar Account has domains associated with it and cannot be deleted') . '<BR>';

    } else {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT ra.username AS username, o.name AS owner_name, r.name AS registrar_name
                FROM registrar_accounts AS ra, owners AS o, registrars AS r
                WHERE ra.owner_id = o.id
                  AND ra.registrar_id = r.id
                  AND ra.id = :raid");
            $stmt->bindValue('raid', $raid, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if ($result) {

                $temp_username = $result->username;
                $temp_owner_name = $result->owner_name;
                $temp_registrar_name = $result->registrar_name;

            }

            $stmt = $pdo->prepare("
                DELETE FROM registrar_accounts
                WHERE id = :raid");
            $stmt->bindValue('raid', $raid, PDO::PARAM_INT);
            $stmt->execute();

            $system->checkExistingAssets();

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('Registrar Account %s (%s, %s) deleted'), $temp_username, $temp_registrar_name, $temp_owner_name) . '<BR>';

            header("Location: ../registrar-accounts.php");
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to delete registrar account';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    }

}

$stmt = $pdo->prepare("
    SELECT apir.name, apir.req_account_username, apir.req_account_password, apir.req_account_id, apir.req_reseller_id,
           apir.req_api_app_name, apir.req_api_key, apir.req_api_secret, apir.req_ip_address, apir.lists_domains,
           apir.ret_expiry_date, apir.ret_dns_servers, apir.ret_privacy_status, apir.ret_autorenewal_status, apir.notes
    FROM registrar_accounts AS ra, registrars AS r, api_registrars AS apir
    WHERE ra.registrar_id = r.id
      AND r.api_registrar_id = apir.id
      AND ra.id = :raid");
$stmt->bindValue('raid', $raid, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch();
$stmt->closeCursor();

if ($result) {

    $api_registrar_name = $result->name;
    $req_account_username = $result->req_account_username;
    $req_account_password = $result->req_account_password;
    $req_account_id = $result->req_account_id;
    $req_reseller_id = $result->req_reseller_id;
    $req_api_app_name = $result->req_api_app_name;
    $req_api_key = $result->req_api_key;
    $req_api_secret = $result->req_api_secret;
    $req_ip_address = $result->req_ip_address;
    $lists_domains = $result->lists_domains;
    $ret_expiry_date = $result->ret_expiry_date;
    $ret_dns_servers = $result->ret_dns_servers;
    $ret_privacy_status = $result->ret_privacy_status;
    $ret_autorenewal_status = $result->ret_autorenewal_status;
    $api_registrar_notes = $result->notes;

    $has_api_support = 1;

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

$result = $pdo->query("
    SELECT id, `name`
    FROM registrars
    ORDER BY `name` ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_registrar_id', _('Registrar'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_registrar_id);

    }

    echo $form->showDropdownBottom('');

}

$result = $pdo->query("
    SELECT id, `name`
    FROM owners
    ORDER BY `name` ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_owner_id', _('Account Owner'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_owner_id);

    }

    echo $form->showDropdownBottom('');

}

echo $form->showInputText('new_email_address', _('Email Address') . ' (100)', '', $unsanitize->text($new_email_address), '100', '', '', '', '');
echo $form->showInputText('new_username', _('Username') . ' (100)', '', $unsanitize->text($new_username), '100', '', '1', '', '');
echo $form->showInputText('new_password', _('Password') . ' (255)', '', $unsanitize->text($new_password), '255', '', '', '', '');
echo $form->showInputText('new_account_id', _('Account ID') . ' (255)', '', $unsanitize->text($new_account_id), '255', '', '', '', '');

echo $form->showSwitch(_('Reseller Account') . '?', '', 'new_reseller', $new_reseller, '', '<BR><BR>');

echo $form->showInputText('new_reseller_id', _('Reseller ID') . ' (100)', '', $unsanitize->text($new_reseller_id), '100', '', '', '', '');

if ($has_api_support >= 1) {

    echo $layout->expandableBoxTop(_('API Credentials'), '', ''); ?>

    <strong><?php echo _('API Requirements'); ?></strong><BR>
    <?php echo sprintf(_('%s requires the following credentials in order to use their API.'), $api_registrar_name); ?>

    <ul><?php

        $missing_text = ' (<span style="color: #a30000"><strong>' . strtolower(_('Missing')) . '</strong></span>)';
        $saved_text = ' (<span style="color: darkgreen"><strong>' . strtolower(_('Saved')) . '</strong></span>)';

        if ($req_account_username == '1') {
            echo '<li>' . _('Registrar Account Username');
            if ($new_username == '') {
                echo $missing_text;
            } else {
                echo $saved_text;
            }
            echo '</li>';
        }
        if ($req_account_password == '1') {
            echo '<li>' . _('Registrar Account Password');
            if ($new_password == '') {
                echo $missing_text;
            } else {
                echo $saved_text;
            }
            echo '</li>';
        }
        if ($req_account_id == '1') {
            echo '<li>' . _('Registrar Account ID');
            if ($new_account_id == '') {
                echo $missing_text;
            } else {
                echo $saved_text;
            }
            echo '</li>';
        }
        if ($req_reseller_id == '1') {
            echo '<li>' . _('Reseller ID');
            if ($new_reseller_id === 0) {
                echo $missing_text;
            } else {
                echo $saved_text;
            }
            echo '</li>';
        }
        if ($req_api_app_name == '1') {
            echo '<li>' . _('API Application Name');
            if ($new_api_app_name == '') {
                echo $missing_text;
            } else {
                echo $saved_text;
            }
            echo '</li>';
        }
        if ($req_api_key == '1') {
            echo '<li>' . _('API Key');
            if ($new_api_key == '') {
                echo $missing_text;
            } else {
                echo $saved_text;
            }
            echo '</li>';
        }
        if ($req_api_secret == '1') {
            echo '<li>' . _('API Secret');
            if ($new_api_secret == '') {
                echo $missing_text;
            } else {
                echo $saved_text;
            }
            echo '</li>';
        }
        if ($req_ip_address == '1') {
            echo '<li>' . _('Connecting IP Address');
            if ($new_api_ip_id === 0) {
                echo $missing_text;
            } else {
                echo $saved_text;
            }
            echo '</li>';
        } ?>
    </ul><?php

    if ($api_registrar_notes != '') {

        echo '<strong>' . _('Registrar Notes') . '</strong><BR>';
        echo $api_registrar_notes . "<BR><BR>";

    }

    echo $form->showInputText('new_api_app_name', _('API App Name'), '', $unsanitize->text($new_api_app_name), '255', '', '', '', '');
    echo $form->showInputText('new_api_key', _('API Key'), '', $unsanitize->text($new_api_key), '255', '', '', '', '');
    echo $form->showInputText('new_api_secret', _('API Secret'), '', $unsanitize->text($new_api_secret), '255', '', '', '', '');

    $result = $pdo->query("
        SELECT id, `name`, ip
        FROM ip_addresses
        ORDER BY `name` ASC")->fetchAll();

    if ($result) {

        echo $form->showDropdownTop('new_api_ip_id', _('API IP Address'), _('The IP Address that you whitelisted with the domain registrar for API access.') . sprintf(_('%sClick here%s to add a new IP Address'), '<a href="' . $web_root . '/assets/add/ip-address.php">', '</a>'), '', '');

        echo $form->showDropdownOption('0', 'n/a', '0');

        foreach ($result as $row) {

            echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $new_api_ip_id);

        }

        echo $form->showDropdownBottom('');

    }

    echo $layout->expandableBoxBottom();

}

echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_raid', $raid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->deleteButton(_('Registrar Account'), $new_username, 'registrar-account.php?raid=' . $raid . '&del=1');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
