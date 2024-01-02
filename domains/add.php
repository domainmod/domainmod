<?php
/**
 * /domains/add.php
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/domains/add.php');
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

$timestamp = $time->stamp();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/domains-add.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

$new_domain = isset($_POST['new_domain']) ? $sanitize->text($_POST['new_domain']) : '';
$new_expiry_date = $_POST['datepick'] ?? '';
$new_function = isset($_POST['new_function']) ? $sanitize->text($_POST['new_function']) : '';
$new_cat_id = (int) ($_POST['new_cat_id'] ?? 0);
$new_dns_id = (int) ($_POST['new_dns_id'] ?? 0);
$new_ip_id = (int) ($_POST['new_ip_id'] ?? 0);
$new_hosting_id = (int) ($_POST['new_hosting_id'] ?? 0);
$new_account_id = (int) ($_POST['new_account_id'] ?? 0);
$new_autorenew = (int) ($_POST['new_autorenew'] ?? 0);
$new_privacy = (int) ($_POST['new_privacy'] ?? 0);
$new_active = (int) ($_POST['new_active'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

// Custom Fields
$result = $pdo->query("
    SELECT field_name
    FROM domain_fields
    ORDER BY `name`")->fetchAll();

if ($result) {

    $field_array = array();

    foreach ($result as $row) {

        $field_array[] = $row->field_name;

    }

    foreach ($field_array as $field) {

        $full_field = "new_" . $field . "";
        ${'new_' . $field} = $_POST[$full_field] ?? '';

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();
    $domain = new DomainMOD\Domain();

    if ($date->checkDateFormat($new_expiry_date) && $domain->checkFormat($new_domain) && $new_cat_id !== 0 &&
        $new_dns_id !== 0 && $new_ip_id !== 0 && $new_hosting_id !== 0 && $new_account_id !== 0) {

        $stmt = $pdo->prepare("
            SELECT domain
            FROM domains
            WHERE domain = :new_domain
            LIMIT 1");
        $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if ($result) {

            $_SESSION['s_message_danger'] .= sprintf(_('This domain is already in %s'), SOFTWARE_TITLE . '<BR>');

        } else {

            try {

                $pdo->beginTransaction();

                $tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);

                $stmt = $pdo->prepare("
                    SELECT registrar_id, owner_id
                    FROM registrar_accounts
                    WHERE id = :new_account_id");
                $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch();
                $stmt->closeCursor();

                if ($result) {

                    $new_registrar_id = $result->registrar_id;
                    $new_owner_id = $result->owner_id;

                }

                if ($new_privacy === 1) {

                    $fee_string = 'renewal_fee + privacy_fee + misc_fee';

                } else {

                    $fee_string = 'renewal_fee + misc_fee';

                }

                $stmt = $pdo->prepare("
                    SELECT id, (" . $fee_string . ") AS total_cost
                    FROM fees
                    WHERE registrar_id = :new_registrar_id
                      AND tld = :tld");
                $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
                $stmt->bindValue('tld', $tld, PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->fetch();
                $stmt->closeCursor();

                if ($result) {

                    $new_fee_id = $result->id;
                    $new_total_cost = $result->total_cost;

                } else {

                    $new_fee_id = 0;
                    $new_total_cost = 0;

                }

                $stmt = $pdo->prepare("
                    INSERT INTO domains
                    (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                     hosting_id, fee_id, total_cost, `function`, notes, autorenew, privacy, created_by,
                     active, insert_time)
                    VALUES
                    (:new_owner_id, :new_registrar_id, :new_account_id, :new_domain, :tld, :new_expiry_date, :new_cat_id,
                     :new_dns_id, :new_ip_id, :new_hosting_id, :new_fee_id, :new_total_cost, :new_function, :new_notes,
                     :new_autorenew, :new_privacy, :user_id, :new_active, :timestamp)");
                $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
                $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
                $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
                $stmt->bindValue('tld', $tld, PDO::PARAM_STR);
                $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
                $stmt->bindValue('new_cat_id', $new_cat_id, PDO::PARAM_INT);
                $stmt->bindValue('new_dns_id', $new_dns_id, PDO::PARAM_INT);
                $stmt->bindValue('new_ip_id', $new_ip_id, PDO::PARAM_INT);
                $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
                $stmt->bindValue('new_total_cost', strval($new_total_cost), PDO::PARAM_STR);
                $stmt->bindValue('new_function', $new_function, PDO::PARAM_STR);
                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                $stmt->bindValue('new_autorenew', $new_autorenew, PDO::PARAM_INT);
                $stmt->bindValue('new_privacy', $new_privacy, PDO::PARAM_INT);
                $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
                $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->execute();

                $temp_domain_id = $pdo->lastInsertId('id');

                $stmt = $pdo->prepare("
                    INSERT INTO domain_field_data
                    (domain_id, insert_time)
                    VALUES
                    (:temp_domain_id, :timestamp)");
                $stmt->bindValue('temp_domain_id', $temp_domain_id, PDO::PARAM_INT);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->execute();

                $result = $pdo->query("
                    SELECT field_name
                    FROM domain_fields
                    ORDER BY `name`")->fetchAll();

                if ($result) {

                    $field_array = array();

                    foreach ($result as $row) {

                        $field_array[] = $row->field_name;

                    }

                    foreach ($field_array as $field) {

                        $full_field = "new_" . $field;

                        $stmt = $pdo->prepare("
                            UPDATE domain_field_data
                            SET `" . $field . "` = :full_field
                            WHERE domain_id = :temp_domain_id");
                        $stmt->bindValue('full_field', ${$full_field}, PDO::PARAM_STR);
                        $stmt->bindValue('temp_domain_id', $temp_domain_id, PDO::PARAM_INT);
                        $stmt->execute();

                    }

                }

                $maint->updateDomainFee($temp_domain_id);

                $queryB = new DomainMOD\QueryBuild();
                $sql = $queryB->missingFees('domains');
                $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

                $maint->updateSegments();

                $system->checkExistingAssets();

                if ($pdo->InTransaction()) $pdo->commit();

                $_SESSION['s_message_success'] .= sprintf(_('Domain %s added'), $new_domain) . '<BR>';

            } catch (Exception $e) {

                if ($pdo->InTransaction()) $pdo->rollback();

                $log_message = 'Unable to add domain';
                $log_extra = array('Error' => $e);
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                throw $e;

            }

        }

    } else {

        if (!$domain->checkFormat($new_domain)) {
            $_SESSION['s_message_danger'] .= _('The domain format is incorrect') . '<BR>';
        }

        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_message_danger'] .= _('The expiry date you entered is invalid') . '<BR>';
        }

        if ($new_account_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the Registrar Account') . '<BR>';

        }

        if ($new_dns_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the DNS Profile') . '<BR>';

        }

        if ($new_ip_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the IP Address') . '<BR>';

        }

        if ($new_hosting_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the Web Host') . '<BR>';

        }

        if ($new_cat_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the Category') . '<BR>';

        }

    }

} else {

    // Casting $new_active as int sets it to 0 on first load, which sets the default status to 'Expired'. The below
    // line sets the default to 'Active' instead.
    $new_active = 1;

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-picker-head.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_domain', _('Domain') . ' (255)', '', $unsanitize->text($new_domain), '255', '', '1', '', '');
echo $form->showInputText('new_function', _('Function') . ' (255)', '', $unsanitize->text($new_function), '255', '', '', '', '');
if ($new_expiry_date == '') {
    $new_expiry_date = $time->toUserTimezone($timestamp_basic_plus_one_year, 'Y-m-d');
}
echo $form->showInputText('datepick', _('Expiry Date') . ' (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_account_id;

} else {

    $to_compare = $_SESSION['s_default_registrar_account'];

}

$result = $pdo->query("
    SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
    FROM registrar_accounts AS ra, owners AS o, registrars AS r
    WHERE ra.owner_id = o.id
      AND ra.registrar_id = r.id
    ORDER BY r_name ASC, o_name ASC, ra.username ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_account_id', _('Registrar Account'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->r_name . ', ' . $row->o_name . ' (' . $row->username . ')', $to_compare);

    }

    echo $form->showDropdownBottom('');

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_dns_id;

} else {

    $to_compare = $_SESSION['s_default_dns'];

}

$result = $pdo->query("
    SELECT id, `name`
    FROM dns
    ORDER BY `name` ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_dns_id', _('DNS Profile'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $to_compare);

    }

    echo $form->showDropdownBottom('');

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_ip_id;

} else {

    $to_compare = $_SESSION['s_default_ip_address_domains'];

}

$result = $pdo->query("
    SELECT id, `name`, ip
     FROM ip_addresses
     ORDER BY `name` ASC, ip ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ' )', $to_compare);

    }

    echo $form->showDropdownBottom('');

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_hosting_id;

} else {

    $to_compare = $_SESSION['s_default_host'];

}

$result = $pdo->query("
    SELECT id, `name`
    FROM hosting
    ORDER BY name ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_hosting_id', _('Web Hosting Provider'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $to_compare);

    }

    echo $form->showDropdownBottom('');

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_cat_id;

} else {

    $to_compare = $_SESSION['s_default_category_domains'];

}

$result = $pdo->query("
    SELECT id, `name`
    FROM categories
    ORDER BY name ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_cat_id', _('Category'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $to_compare);

    }

    echo $form->showDropdownBottom('');
}

echo $form->showDropdownTop('new_active', _('Domain Status'), '', '', '');
echo $form->showDropdownOption('1', _('Active'), $new_active);
echo $form->showDropdownOption('5', _('Pending (Registration)'), $new_active);
echo $form->showDropdownOption('3', _('Pending (Renewal)'), $new_active);
echo $form->showDropdownOption('2', _('Pending (Transfer)'), $new_active);
echo $form->showDropdownOption('4', _('Pending (Other)'), $new_active);
echo $form->showDropdownOption('10', _('Sold'), $new_active);
echo $form->showDropdownOption('0', _('Expired'), $new_active);
echo $form->showDropdownBottom('');

echo $form->showSwitch(_('Auto Renewal') . '?', '', 'new_autorenew', $new_autorenew, '', '<BR><BR>');

echo $form->showSwitch(_('Privacy Enabled') . '?', '', 'new_privacy', $new_privacy, '', '<BR><BR>');

echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');

$result = $pdo->query("
    SELECT field_name
    FROM domain_fields
    ORDER BY type_id ASC, `name` ASC")->fetchAll();

if ($result) { ?>

    <h3><?php echo _('Custom Fields'); ?></h3><?php

    $field_array = array();

    foreach ($result as $row) {

        $field_array[] = $row->field_name;

    }

    foreach ($field_array as $field) {

        $stmt = $pdo->prepare("
            SELECT df.name, df.field_name, df.type_id, df.description
            FROM domain_fields AS df, custom_field_types AS cft
            WHERE df.type_id = cft.id
              AND df.field_name = :field");
        $stmt->bindValue('field', $field, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                if ($row->type_id == "1") { // Check Box

                    echo $form->showCheckbox('new_' . $row->field_name, '1', $row->name, $row->description, ${'new_' . $field}, '', '');

                } elseif ($row->type_id == "2") { // Text

                    echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $field}, '255', '', '', '', '');

                } elseif ($row->type_id == "3") { // Text Area

                    echo $form->showInputTextarea('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $field}, '', '', '');

                } elseif ($row->type_id == "4") { // Date

                    echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $field}, '10', '', '', '', '');

                } elseif ($row->type_id == "5") { // Time Stamp

                    echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $field}, '19', '', '', '', '');

                } elseif ($row->type_id == "6") { // URL

                    echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $field}, '255', '', '', '', '');

                }

            }

        }

    }

}

echo $form->showSubmitButton(_('Add Domain'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
</body>
</html>
