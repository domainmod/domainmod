<?php
/**
 * /assets/add/hosting-provider-account.php
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
$log = new DomainMOD\Log('/assets/add/hosting-provider-account.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-add-hosting-account.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

// Before the database update there are no hosting tables to add to
if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';
    header("Location: ../index.php");
    exit;

}

$new_owner_id = (int) ($_POST['new_owner_id'] ?? 0);
$new_hosting_id = (int) ($_POST['new_hosting_id'] ?? 0);
$new_email_address = isset($_POST['new_email_address']) ? $sanitize->text($_POST['new_email_address']) : '';
$new_username = isset($_POST['new_username']) ? $sanitize->text($_POST['new_username']) : '';
$new_reseller = (int) ($_POST['new_reseller'] ?? 0);
$new_reseller_id = isset($_POST['new_reseller_id']) ? $sanitize->text($_POST['new_reseller_id']): '';
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']): '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($validate->text($new_username) && $new_owner_id !== 0 && $new_hosting_id !== 0) {

        /*
         * The owner and hosting provider are checked and the account added under the hosting lock, which their
         * deletes hold too, so neither can be deleted between the check and the insert
         */
        if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

            $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';

        } else {

            $added = false;

            try {

                $stmt = $pdo->prepare("
                    SELECT (SELECT COUNT(*) FROM owners WHERE id = :new_owner_id) AS owner_found,
                           (SELECT COUNT(*) FROM hosting WHERE id = :new_hosting_id) AS provider_found");
                $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                $stmt->execute();
                $found = $stmt->fetch();
                $stmt->closeCursor();

                if ((int) $found->owner_found === 0) $form->addFieldError('new_owner_id', _('Choose the owner'));
                if ((int) $found->provider_found === 0) $form->addFieldError('new_hosting_id', _('Choose the Hosting Provider'));

                if ((int) $found->owner_found > 0 && (int) $found->provider_found > 0) {

                    $stmt = $pdo->prepare("
                        INSERT INTO hosting_provider_accounts
                        (owner_id, hosting_id, email_address, username, reseller, reseller_id, notes, created_by, insert_time)
                        VALUES
                        (:new_owner_id, :new_hosting_id, :new_email_address, :new_username, :new_reseller, :new_reseller_id,
                         :new_notes, :created_by, :timestamp)");
                    $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_email_address', $new_email_address, PDO::PARAM_STR);
                    $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
                    $stmt->bindValue('new_reseller', $new_reseller, PDO::PARAM_INT);
                    $stmt->bindValue('new_reseller_id', $new_reseller_id, PDO::PARAM_STR);
                    $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                    $stmt->bindValue('created_by', $_SESSION['s_user_id'], PDO::PARAM_INT);
                    $timestamp = $time->stamp();
                    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                    $stmt->execute();

                    $added = true;

                }

            } catch (Exception $e) {

                $log_message = 'Unable to add hosting account';
                $log_extra = array('Error' => $e);
                $log->critical($log_message, $log_extra);

                // No message for a conflict, whose own page explains it (see domainmod_restore_is_conflict())
                if (!domainmod_restore_is_conflict($e)) $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                throw $e;

            } finally {

                DomainMOD\Hosting::unlock($pdo);

            }

            if ($added) {

                $_SESSION['s_message_success'] .= sprintf(_('Hosting Account %s (%s, %s) added'),
                    $new_username, $assets->getHost($new_hosting_id), $assets->getOwner($new_owner_id)) . '<BR>';

                if (($_SESSION['s_has_hosting_provider_account'] ?? '') != '1') {

                    $system->checkExistingAssets();

                    header("Location: ../../hosting/index.php");

                } else {

                    header("Location: ../hosting-accounts.php");

                }
                exit;

            }

        }

    } else {

        if ($new_owner_id === 0) {

            $form->addFieldError('new_owner_id', _('Choose the owner'));

        }

        if ($new_hosting_id === 0) {

            $form->addFieldError('new_hosting_id', _('Choose the Hosting Provider'));

        }

        if (!$validate->text($new_username)) { $form->addFieldError('new_username', _('Enter a username')); }

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
// The fields in titled cards, the same way as the edit domain page (Layout::formSection()): the account, then the notes
ob_start();
echo $form->showDropdownTop('new_hosting_id', _('Hosting Provider'), '', '1', '');

if ($new_hosting_id === 0) {

    $to_compare = $_SESSION['s_default_host'] ?? 0;

} else {

    $to_compare = $new_hosting_id;

}
$result = $pdo->query("
    SELECT id, `name`
    FROM hosting
    ORDER BY `name` ASC")->fetchAll();
foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->name, $to_compare);

}
echo $form->showDropdownBottom('', 'new_hosting_id');

echo $form->showDropdownTop('new_owner_id', _('Account Owner'), '', '1', '');

if ($new_owner_id === 0) {

    $to_compare = $_SESSION['s_default_owner_hosting'] ?? 0;

} else {

    $to_compare = $new_owner_id;

}
$result = $pdo->query("
    SELECT id, `name`
    FROM owners
    ORDER BY `name` ASC")->fetchAll();
foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->name, $to_compare);

}
echo $form->showDropdownBottom('', 'new_owner_id');

echo $form->showInputText('new_email_address', _('Email Address') . ' (100)', '', $unsanitize->text($new_email_address), '100', '', '', '', '');
echo $form->showInputText('new_username', _('Username') . ' (100)', '', $unsanitize->text($new_username), '100', '', '1', '', '');
// The switch is one grid item, with the same spacing as the other fields
echo $form->showSwitch(_('Reseller Account') . '?', '', 'new_reseller', $new_reseller, '<div class="mb-3">', '</div>');
echo $form->showInputText('new_reseller_id', _('Reseller ID') . ' (100)', '', $unsanitize->text($new_reseller_id), '100', '', '', '', '');
echo $layout->formSection(_('Account'), 'account', ob_get_clean());

ob_start();
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '<div class="domainmod-span-all">', '</div>');
echo $layout->formSection(_('Notes'), 'notes', ob_get_clean());

echo $form->showSubmitButton(_('Add Hosting Account'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
