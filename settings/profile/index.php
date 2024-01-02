<?php
/**
 * /settings/profile/index.php
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
$log = new DomainMOD\Log('/settings/profile/index.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$currency = new DomainMOD\Currency();
$conversion = new DomainMOD\Conversion();
$language = new DomainMOD\Language();

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/settings-profile.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$new_first_name = isset($_POST['new_first_name']) ? $sanitize->text($_POST['new_first_name']) : '';
$new_last_name = isset($_POST['new_last_name']) ? $sanitize->text($_POST['new_last_name']) : '';
$new_email_address = isset($_POST['new_email_address']) ? $sanitize->text($_POST['new_email_address']) : '';
$new_language = isset($_POST['new_language']) ? $sanitize->text($_POST['new_language']) : '';
$new_currency = isset($_POST['new_currency']) ? $sanitize->text($_POST['new_currency']) : '';
$new_timezone = isset($_POST['new_timezone']) ? $sanitize->text($_POST['new_timezone']) : '';
$new_expiration_emails = (int) ($_POST['new_expiration_emails'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != "" && $new_last_name != "" && $new_email_address != "") {

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = :user_id
          AND email_address = :email_address");
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->bindValue('email_address', $_SESSION['s_email_address'], PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

    if (count($result) !== 1) { // If there isn't exactly one user result

        $_SESSION['s_message_danger'] .= _('Your profile could not be updated') . '<BR>';
        $_SESSION['s_message_danger'] .= _('If the problem persists please contact your administrator') . '<BR>';

    } else {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE users
                SET first_name = :new_first_name,
                    last_name = :new_last_name,
                    email_address = :new_email_address,
                    update_time = :timestamp
                WHERE id = :user_id
                  AND email_address = :email_address");
            $stmt->bindValue('new_first_name', $new_first_name, PDO::PARAM_STR);
            $stmt->bindValue('new_last_name', $new_last_name, PDO::PARAM_STR);
            $stmt->bindValue('new_email_address', $new_email_address, PDO::PARAM_STR);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->bindValue('email_address', $_SESSION['s_email_address'], PDO::PARAM_STR);
            $stmt->execute();

            $stmt = $pdo->prepare("
                SELECT default_language, default_currency, default_timezone
                FROM user_settings
                WHERE user_id = :user_id");
            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if ($result) {

                $saved_language = $result->default_language;
                $saved_currency = $result->default_currency;
                $saved_timezone = $result->default_timezone;

            }

            if ($saved_currency != $new_currency) {

                $currency = new DomainMOD\Currency();
                $temp_new_currency_id = $currency->getCurrencyId($new_currency);

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM currency_conversions
                    WHERE user_id = :user_id
                      AND currency_id = :currency_id");
                $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
                $stmt->bindValue('currency_id', $temp_new_currency_id, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetchColumn();

                if (!$result) {

                    $stmt = $pdo->prepare("
                        INSERT INTO currency_conversions
                        (currency_id, user_id, conversion, insert_time, update_time)
                        VALUES
                        (:temp_new_currency_id, :user_id, '1', :timestamp_insert, :timestamp_update)");
                    $stmt->bindValue('temp_new_currency_id', $temp_new_currency_id, PDO::PARAM_INT);
                    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
                    $stmt->bindValue('timestamp_insert', $timestamp, PDO::PARAM_STR);
                    $stmt->bindValue('timestamp_update', $timestamp, PDO::PARAM_STR);
                    $stmt->execute();

                }

                $_SESSION['s_message_success'] .= $conversion->updateRates($new_currency, $_SESSION['s_user_id']);

            }

            $stmt = $pdo->prepare("
                UPDATE user_settings
                SET default_language = :new_language,
                    default_currency = :new_currency,
                    default_timezone = :new_timezone,
                    expiration_emails = :new_expiration_emails,
                    update_time = :timestamp
                WHERE user_id = :user_id");
            $stmt->bindValue('new_language', $new_language, PDO::PARAM_STR);
            $stmt->bindValue('new_currency', $new_currency, PDO::PARAM_STR);
            $stmt->bindValue('new_timezone', $new_timezone, PDO::PARAM_STR);
            $stmt->bindValue('new_expiration_emails', $new_expiration_emails, PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['s_first_name'] = $new_first_name;
            $_SESSION['s_last_name'] = $new_last_name;
            $_SESSION['s_email_address'] = $new_email_address;
            $_SESSION['s_default_language'] = $new_language;
            $_SESSION['s_default_language_name'] = $language->getLangName($new_language);
            $_SESSION['s_default_currency'] = $new_currency;
            $_SESSION['s_default_timezone'] = $new_timezone;
            $_SESSION['s_expiration_emails'] = $new_expiration_emails;

            list($_SESSION['s_default_currency_name'], $_SESSION['s_default_currency_symbol'],
                $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space'])
                = $currency->getCurrencyInfo($new_currency);

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= _('Your profile was updated') . '<BR>';

            header("Location: index.php");
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to update profile';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_email_address == "") $_SESSION['s_message_danger'] .= _('Your email address could not be updated') . '<BR>';
        if ($new_first_name == "") $_SESSION['s_message_danger'] .= _('Your first name could not be updated') . '<BR>';
        if ($new_last_name == "") $_SESSION['s_message_danger'] .= _('Your last name could not be updated') . '<BR>';

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

if ($new_first_name != "") {
    $temp_first_name = $new_first_name;
} else {
    $temp_first_name = $_SESSION['s_first_name'];
}
echo $form->showInputText('new_first_name', _('First Name') . ' (50)', '', $unsanitize->text($temp_first_name), '50', '', '1', '', '');

if ($new_last_name != "") {
    $temp_last_name = $new_last_name;
} else {
    $temp_last_name = $_SESSION['s_last_name'];
}
echo $form->showInputText('new_last_name', _('Last Name') . ' (50)', '', $unsanitize->text($temp_last_name), '50', '', '1', '', '');

if ($new_email_address != "") {
    $temp_email_address = $new_email_address;
} else {
    $temp_email_address = $_SESSION['s_email_address'];
}
echo $form->showInputText('new_email_address', _('Email Address') . ' (100)', '', $unsanitize->text($temp_email_address), '100', '', '1', '', '');

echo $form->showDropdownTop('new_language', _('Language'), '', '', '');

$result = $pdo->query("
    SELECT name, language
    FROM languages
    ORDER BY name")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->language, $row->name . ' [' . $row->language . ']', $_SESSION['s_default_language']);

}

echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_currency', _('Currency'), '', '', '');

$result = $pdo->query("
    SELECT currency, `name`, symbol
    FROM currencies
    ORDER BY name")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->currency, $row->name . ' (' . $row->currency . ' ' . $row->symbol . ')', $_SESSION['s_default_currency']);

}

echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_timezone', _('Time Zone'), '', '', '');

$result = $pdo->query("
    SELECT timezone
    FROM timezones
    ORDER BY timezone")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->timezone, $row->timezone, $_SESSION['s_default_timezone']);

}

echo $form->showDropdownBottom('');

if ($new_expiration_emails !== 0) {
    $temp_expiration_emails = $new_expiration_emails;
} else {
    $temp_expiration_emails = $_SESSION['s_expiration_emails'];
}

echo $form->showSwitch(_('Subscribe to Domain & SSL Certificate expiration emails?') . '?', '', 'new_expiration_emails', $temp_expiration_emails, '', '<BR><BR>');

echo $form->showSubmitButton(_('Update Profile'), '', '');
echo $form->showFormBottom('');
?>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
