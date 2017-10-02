<?php
/**
 * /settings/profile/index.php
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
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$conversion = new DomainMOD\Conversion();
$deeb = DomainMOD\Database::getInstance();
$form = new DomainMOD\Form();
$log = new DomainMOD\Log('/settings/profile/index.php');
$system = new DomainMOD\System();
$time = new DomainMOD\Time();
$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/settings-profile.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_email_address = $_POST['new_email_address'];
$new_currency = $_POST['new_currency'];
$new_timezone = $_POST['new_timezone'];
$new_expiration_email = $_POST['new_expiration_email'];

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

        $_SESSION['s_message_danger'] .= "Your profile could not be updated<BR>";
        $_SESSION['s_message_danger'] .= "If the problem persists please contact your administrator<BR>";

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
                SELECT default_currency, default_timezone
                FROM user_settings
                WHERE user_id = :user_id");
            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();

            if ($result) {

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
                SET default_currency = :new_currency,
                    default_timezone = :new_timezone,
                    expiration_emails = :new_expiration_email,
                    update_time = :timestamp
                WHERE user_id = :user_id");
            $stmt->bindValue('new_currency', $new_currency, PDO::PARAM_STR);
            $stmt->bindValue('new_timezone', $new_timezone, PDO::PARAM_STR);
            $stmt->bindValue('new_expiration_email', $new_expiration_email, PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['s_first_name'] = $new_first_name;
            $_SESSION['s_last_name'] = $new_last_name;
            $_SESSION['s_email_address'] = $new_email_address;
            $_SESSION['s_default_currency'] = $new_currency;
            $_SESSION['s_default_timezone'] = $new_timezone;
            $_SESSION['s_expiration_email'] = $new_expiration_email;

            $stmt = $pdo->prepare("
                SELECT `name`, symbol, symbol_order, symbol_space
                FROM currencies
                WHERE currency = :new_currency");
            $stmt->bindValue('new_currency', $new_currency, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch();

            if ($result) {

                $_SESSION['s_default_currency_name'] = $result->name;
                $_SESSION['s_default_currency_symbol'] = $result->symbol;
                $_SESSION['s_default_currency_symbol_order'] = $result->symbol_order;
                $_SESSION['s_default_currency_symbol_space'] = $result->symbol_space;

            }

            $pdo->commit();

            $_SESSION['s_message_success'] .= "Your profile was updated<BR>";

            header("Location: ../index.php");
            exit;

        } catch (Exception $e) {

            $pdo->rollback();

            $log_message = 'Unable to update profile';
            $log_extra = array('Error' => $e);
            $log->error($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_email_address == "") $_SESSION['s_message_danger'] .= "Your email address could not be updated<BR>";
        if ($new_first_name == "") $_SESSION['s_message_danger'] .= "Your first name could not be updated<BR>";
        if ($new_last_name == "") $_SESSION['s_message_danger'] .= "Your last name could not be updated<BR>";

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

if ($new_first_name != "") {
    $temp_first_name = $new_first_name;
} else {
    $temp_first_name = $_SESSION['s_first_name'];
}
echo $form->showInputText('new_first_name', 'First Name (50)', '', $temp_first_name, '50', '', '1', '', '');

if ($new_last_name != "") {
    $temp_last_name = $new_last_name;
} else {
    $temp_last_name = $_SESSION['s_last_name'];
}
echo $form->showInputText('new_last_name', 'Last Name (50)', '', $temp_last_name, '50', '', '1', '', '');

if ($new_email_address != "") {
    $temp_email_address = $new_email_address;
} else {
    $temp_email_address = $_SESSION['s_email_address'];
}
echo $form->showInputText('new_email_address', 'Email Address (100)', '', $temp_email_address, '100', '', '1', '', '');

echo $form->showDropdownTop('new_currency', 'Currency', '', '', '');

$result = $pdo->query("
    SELECT currency, `name`, symbol
    FROM currencies
    ORDER BY name")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->currency, $row->name . ' (' . $row->currency . ' ' . $row->symbol . ')', $_SESSION['s_default_currency']);

}

echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_timezone', 'Time Zone', '', '', '');

$result = $pdo->query("
    SELECT timezone
    FROM timezones
    ORDER BY timezone")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->timezone, $row->timezone, $_SESSION['s_default_timezone']);

}

echo $form->showDropdownBottom('');

if ($new_expiration_email != "") {
    $temp_expiration_email = $new_expiration_email;
} else {
    $temp_expiration_email = $_SESSION['s_expiration_email'];
}
echo $form->showRadioTop('Subscribe to Domain & SSL Certificate expiration emails?', '', '');
echo $form->showRadioOption('new_expiration_email', '1', 'Yes', $temp_expiration_email, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_expiration_email', '0', 'No', $temp_expiration_email, '', '');
echo $form->showRadioBottom('');

echo $form->showSubmitButton('Update Profile', '', '');
echo $form->showFormBottom('');
?>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
