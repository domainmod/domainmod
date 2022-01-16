<?php
/**
 * /ssl/add.php
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/ssl/add.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

$timestamp = $time->stamp();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/ssl-add.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);
$pdo = $deeb->cnxx;

$new_domain_id = (int) $_POST['new_domain_id'];
$new_name = $sanitize->text($_POST['new_name']);
$new_type_id = (int) $_POST['new_type_id'];
$new_ip_id = (int) $_POST['new_ip_id'];
$new_cat_id = (int) $_POST['new_cat_id'];
$new_expiry_date = $_POST['datepick'];
$new_account_id = (int) $_POST['new_account_id'];
$new_active = (int) $_POST['new_active'];
$new_notes = $sanitize->text($_POST['new_notes']);

// Custom Fields
$result = $pdo->query("
    SELECT field_name
    FROM ssl_cert_fields
    ORDER BY `name`")->fetchAll();

if ($result) {

    $field_array = array();

    foreach ($result as $row) {

        $field_array[] = $row->field_name;

    }

    foreach ($field_array as $field) {

        $full_field = "new_" . $field . "";
        ${'new_' . $field} = $_POST[$full_field];

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_type_id !== 0 && $new_ip_id !== 0 &&
        $new_cat_id !== 0 && $new_domain_id !== 0 && $new_account_id !== 0
    ) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT ssl_provider_id, owner_id
                FROM ssl_accounts
                WHERE id = :new_account_id");
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if ($result) {

                $new_ssl_provider_id = $result->ssl_provider_id;
                $new_owner_id = $result->owner_id;

            }

            $stmt = $pdo->prepare("
                SELECT id, (renewal_fee + misc_fee) AS total_cost
                FROM ssl_fees
                WHERE ssl_provider_id = :new_ssl_provider_id
                  AND type_id = :new_type_id");
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
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
                INSERT INTO ssl_certs
                (owner_id, ssl_provider_id, account_id, domain_id, `name`, type_id, ip_id, cat_id, expiry_date,
                 fee_id, total_cost, notes, active, created_by, insert_time)
                VALUES
                (:new_owner_id, :new_ssl_provider_id, :new_account_id, :new_domain_id, :new_name, :new_type_id, :new_ip_id,
                 :new_cat_id, :new_expiry_date, :new_fee_id, :new_total_cost, :new_notes, :new_active, :user_id,
                 :timestamp)");
            $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
            $stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
            $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->bindValue('new_ip_id', $new_ip_id, PDO::PARAM_INT);
            $stmt->bindValue('new_cat_id', $new_cat_id, PDO::PARAM_INT);
            $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
            $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
            $stmt->bindValue('new_total_cost', strval($new_total_cost), PDO::PARAM_STR);
            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
            $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->execute();

            $temp_ssl_id = $pdo->lastInsertId('id');

            $stmt = $pdo->prepare("
                INSERT INTO ssl_cert_field_data
                (ssl_id, insert_time)
                VALUES
                (:temp_ssl_id, :timestamp)");
            $stmt->bindValue('temp_ssl_id', $temp_ssl_id, PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->execute();

            $result = $pdo->query("
                SELECT field_name
                FROM ssl_cert_fields
                ORDER BY `name`")->fetchAll();

            if ($result) {

                $field_array = array();

                foreach ($result as $row) {

                    $field_array[] = $row->field_name;

                }

                foreach ($field_array as $field) {

                    $full_field = "new_" . $field;

                    $stmt = $pdo->prepare("
                        UPDATE ssl_cert_field_data
                        SET `" . $field . "` = :full_field
                        WHERE ssl_id = :temp_ssl_id");
                    $stmt->bindValue('full_field', ${$full_field}, PDO::PARAM_STR);
                    $stmt->bindValue('temp_ssl_id', $temp_ssl_id, PDO::PARAM_INT);
                    $stmt->execute();

                }

            }

            $queryB = new DomainMOD\QueryBuild();
            $sql = $queryB->missingFees('ssl_certs');
            $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($sql);

            $system->checkExistingAssets();

            $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('SSL Certificate %s added'), $new_name) . '<BR>';

        } catch (Exception $e) {

            $pdo->rollback();

            $log_message = 'Unable to add SSL certificate';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    } else {

        if ($new_name == "") {
            $_SESSION['s_message_danger'] .= _('Enter a name for the SSL certificate') . '<BR>';
        }
        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_message_danger'] .= _('The expiry date you entered is invalid') . '<BR>';
        }

        if ($new_domain_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the domain') . '<BR>';

        }

        if ($new_account_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the SSL Provider Account') . '<BR>';

        }

        if ($new_type_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the SSL Type') . '<BR>';

        }

        if ($new_ip_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the IP Address') . '<BR>';

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
echo $form->showInputText('new_name', _('Host') . ' / ' . _('Label') . ' (100)', '', $unsanitize->text($new_name), '100', '', '1', '', '');
if ($new_expiry_date == '') {
    $new_expiry_date = $time->toUserTimezone($timestamp_basic_plus_one_year, 'Y-m-d');
}
echo $form->showInputText('datepick', _('Expiry Date') . ' (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

$stmt = $pdo->prepare("
    SELECT id, domain
    FROM domains
    WHERE (active NOT IN ('0', '10') OR id = :new_domain_id)
    ORDER BY domain");
$stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

echo $form->showDropdownTop('new_domain_id', _('Domain'), '', '1', '');

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->domain, '');

}

echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_account_id;

} else {

    $to_compare = $_SESSION['s_default_ssl_provider_account'];

}

$result = $pdo->query("
    SELECT sslpa.id, sslpa.username, o.name AS o_name, sslp.name AS sslp_name
    FROM ssl_accounts AS sslpa, owners AS o, ssl_providers AS sslp
    WHERE sslpa.owner_id = o.id
      AND sslpa.ssl_provider_id = sslp.id
    ORDER BY sslp_name ASC, o_name ASC, sslpa.username ASC")->fetchAll();

echo $form->showDropdownTop('new_account_id', _('SSL Provider Account'), '', '1', '');

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->sslp_name . ', ' . $row->o_name . ' (' . $row->username . ')', $to_compare);

}

echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_type_id;

} else {

    $to_compare = $_SESSION['s_default_ssl_type'];

}

$result = $pdo->query("
    SELECT id, type
    FROM ssl_cert_types
    ORDER BY type ASC")->fetchAll();

echo $form->showDropdownTop('new_type_id', _('Certificate Type'), '', '1', '');

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->type, $to_compare);

}

echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_ip_id;

} else {

    $to_compare = $_SESSION['s_default_ip_address_ssl'];

}

$result = $pdo->query("
    SELECT id, ip, `name`
    FROM ip_addresses
    ORDER BY `name`, ip")->fetchAll();

echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '1', '');

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $to_compare);

}
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_cat_id;

} else {

    $to_compare = $_SESSION['s_system_default_category_ssl'];

}

$result = $pdo->query("
    SELECT id, `name`
    FROM categories
    ORDER BY `name`")->fetchAll();

echo $form->showDropdownTop('new_cat_id', _('Category'), '', '1', '');

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->name, $to_compare);

}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_active', _('Certificate Status'), '', '', '');
echo $form->showDropdownOption('1', _('Active'), $new_active);
echo $form->showDropdownOption('5', _('Pending (Registration)'), $new_active);
echo $form->showDropdownOption('3', _('Pending (Renewal)'), $new_active);
echo $form->showDropdownOption('4', _('Pending (Other)'), $new_active);
echo $form->showDropdownOption('0', _('Expired'), $new_active);
echo $form->showDropdownBottom('');

echo $form->showInputTextarea('new_notes', _('Notes'), $subtext, $unsanitize->text($new_notes), '', '', '');

$result = $pdo->query("
    SELECT field_name
    FROM ssl_cert_fields
    ORDER BY type_id ASC, `name`")->fetchAll();

if ($result) { ?>

    <h3>Custom Fields</h3><?php

    $field_array = array();

    foreach ($result as $row) {

        $field_array[] = $row->field_name;

    }

    foreach ($field_array as $field) {

        $stmt = $pdo->prepare("
            SELECT sf.name, sf.field_name, sf.type_id, sf.description
            FROM ssl_cert_fields AS sf, custom_field_types AS cft
            WHERE sf.type_id = cft.id
              AND sf.field_name = :field");
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

    echo "<BR>";

}

echo $form->showSubmitButton(_('Add SSL Certificate'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
</body>
</html>
