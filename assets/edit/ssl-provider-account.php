<?php
/**
 * /assets/edit/ssl-provider-account.php
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
$log = new DomainMOD\Log('/assets/edit/ssl-provider-account.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-ssl-account.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$sslpaid = (int) ($_GET['sslpaid'] ?? 0);
$new_owner_id = (int) ($_POST['new_owner_id'] ?? 0);
$new_ssl_provider_id = (int) ($_POST['new_ssl_provider_id'] ?? 0);
$new_email_address = isset($_POST['new_email_address']) ? $sanitize->text($_POST['new_email_address']) : '';
$new_username = isset($_POST['new_username']) ? $sanitize->text($_POST['new_username']) : '';
$new_password = isset($_POST['new_password']) ? $sanitize->text($_POST['new_password']) : '';
$new_reseller = (int) ($_POST['new_reseller'] ?? 0);
$new_reseller_id = isset($_POST['new_reseller_id']) ? $sanitize->text($_POST['new_reseller_id']) : '';
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';
$new_sslpaid = (int) ($_POST['new_sslpaid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    if ($validate->text($new_username) && $new_owner_id !== 0 && $new_ssl_provider_id !== 0) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE ssl_accounts
                SET owner_id = :new_owner_id,
                    ssl_provider_id = :new_ssl_provider_id,
                    email_address = :new_email_address,
                    username = :new_username,
                    `password` =:new_password,
                    reseller = :new_reseller,
                    reseller_id = :new_reseller_id,
                    notes = :new_notes,
                    update_time = :timestamp
                WHERE id = :new_sslpaid");
            $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_email_address', $new_email_address, PDO::PARAM_STR);
            $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
            $stmt->bindValue('new_password', $new_password, PDO::PARAM_STR);
            $stmt->bindValue('new_reseller', $new_reseller, PDO::PARAM_INT);
            $stmt->bindValue('new_reseller_id', $new_reseller_id, PDO::PARAM_STR);
            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
            $timestamp = $time->stamp();
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('new_sslpaid', $new_sslpaid, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("
                UPDATE ssl_certs
                SET owner_id = :new_owner_id,
                    ssl_provider_id = :new_ssl_provider_id
                WHERE account_id = :new_sslpaid");
            $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_sslpaid', $new_sslpaid, PDO::PARAM_INT);
            $stmt->execute();

            $sslpaid = $new_sslpaid;

            $temp_ssl_provider = $assets->getSslProvider($new_ssl_provider_id);
            $temp_owner = $assets->getOwner($new_owner_id);

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('SSL Account %s (%s, %s) updated'), $new_username, $temp_ssl_provider, $temp_owner) . '<BR>';

            header("Location: ../ssl-accounts.php");
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to update SSL provider account';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    } else {

        if ($new_owner_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the Owner') . '<BR>';

        }

        if ($new_ssl_provider_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the SSL Provider') . '<BR>';

        }

        if (!$validate->text($new_username)) {
            $_SESSION['s_message_danger'] .= _('Enter a username') . '<BR>';
        }

    }

} else {

    $stmt = $pdo->prepare("
        SELECT owner_id, ssl_provider_id, email_address, username, `password`, reseller, reseller_id, notes
        FROM ssl_accounts
        WHERE id = :sslpaid");
    $stmt->bindValue('sslpaid', $sslpaid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_owner_id = $result->owner_id;
        $new_ssl_provider_id = $result->ssl_provider_id;
        $new_email_address = $result->email_address;
        $new_username = $result->username;
        $new_password = $result->password;
        $new_reseller = $result->reseller;
        $new_reseller_id = $result->reseller_id;
        $new_notes = $result->notes;

    }

}

if ($del === 1) {

    $stmt = $pdo->prepare("
        SELECT account_id
        FROM ssl_certs
        WHERE account_id = :sslpaid
        LIMIT 1");
    $stmt->bindValue('sslpaid', $sslpaid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $existing_ssl_certs = 1;

    }

    if ($existing_ssl_certs > 0) {

        $_SESSION['s_message_danger'] .= _('This SSL Account has SSL certificates associated with it and cannot be deleted') . '<BR>';

    } else {

        $stmt = $pdo->prepare("
            SELECT a.username AS username, o.name AS owner_name, p.name AS ssl_provider_name
            FROM ssl_accounts AS a, owners AS o, ssl_providers AS p
            WHERE a.owner_id = o.id
              AND a.ssl_provider_id = p.id
              AND a.id = :sslpaid");
        $stmt->bindValue('sslpaid', $sslpaid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result) {

            $temp_username = $result->username;
            $temp_owner_name = $result->owner_name;
            $temp_ssl_provider_name = $result->ssl_provider_name;

        }

        $stmt = $pdo->prepare("
            DELETE FROM ssl_accounts
            WHERE id = :sslpaid");
        $stmt->bindValue('sslpaid', $sslpaid, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_message_success'] .= sprintf(_('SSL Account %s (%s, %s) deleted'), $temp_username, $temp_ssl_provider_name, $temp_owner_name) . '<BR>';

        $system->checkExistingAssets();

        header("Location: ../ssl-accounts.php");
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

$result = $pdo->query("
    SELECT id, `name`
    FROM ssl_providers
    ORDER BY `name` ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_ssl_provider_id', _('SSL Provider'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_ssl_provider_id);

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
echo $form->showSwitch(_('Reseller Account') . '?', '', 'new_reseller', $new_reseller, '', '<BR><BR>');
echo $form->showInputText('new_reseller_id', _('Reseller ID') . ' (100)', '', $unsanitize->text($new_reseller_id), '100', '', '', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_sslpaid', $sslpaid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->deleteButton(_('SSL Provider Account'), $new_username, 'ssl-provider-account.php?sslpaid=' . $sslpaid . '&del=1');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
