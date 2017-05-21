<?php
/**
 * /domains/add.php
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
require_once('../_includes/start-session.inc.php');
require_once('../_includes/init.inc.php');

require_once(DIR_ROOT . '/classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$maint = new DomainMOD\Maintenance();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$timestamp = $time->stamp();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/debug.inc.php');
require_once(DIR_INC . '/settings/domains-add.inc.php');
require_once(DIR_INC . '/database.inc.php');

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);

$new_domain = $_POST['new_domain'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_function = $_POST['new_function'];
$new_cat_id = $_POST['new_cat_id'];
$new_dns_id = $_POST['new_dns_id'];
$new_ip_id = $_POST['new_ip_id'];
$new_hosting_id = $_POST['new_hosting_id'];
$new_account_id = $_POST['new_account_id'];
$new_autorenew = $_POST['new_autorenew'];
$new_privacy = $_POST['new_privacy'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];

// Custom Fields
$query = "SELECT field_name
          FROM domain_fields
          ORDER BY `name`";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($field_name);

    if ($q->num_rows() > 0) {

        $count = 0;

        while ($q->fetch()) {

            $field_array[$count] = $field_name;
            $count++;

        }

        foreach ($field_array as $field) {

            $full_field = "new_" . $field . "";
            ${'new_' . $field} = $_POST[$full_field];

        }

    }

    $q->close();

} else {
    $error->outputSqlError($dbcon, '1', 'ERROR');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();
    $domain = new DomainMOD\Domain();

    if ($date->checkDateFormat($new_expiry_date) && $domain->checkFormat($new_domain) && $new_cat_id != "" &&
        $new_dns_id != "" && $new_ip_id != "" && $new_hosting_id != "" && $new_account_id != "" && $new_cat_id != "0" &&
        $new_dns_id != "0" && $new_ip_id != "0" && $new_hosting_id != "0" && $new_account_id != "0" && $new_active != '') {

        $query = "SELECT domain
                  FROM domains
                  WHERE domain = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('s', $new_domain);
            $q->execute();
            $q->store_result();

            if ($q->num_rows() === 0) {

                $tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);

                $query_ra = "SELECT registrar_id, owner_id
                             FROM registrar_accounts
                             WHERE id = ?";
                $q_ra = $dbcon->stmt_init();

                if ($q_ra->prepare($query_ra)) {

                    $q_ra->bind_param('i', $new_account_id);
                    $q_ra->execute();
                    $q_ra->store_result();
                    $q_ra->bind_result($new_registrar_id, $new_owner_id);
                    $q_ra->fetch();
                    $q_ra->close();

                } else {
                    $error->outputSqlError($dbcon, '1', 'ERROR');
                }

                if ($new_privacy == "1") {

                    $query_f = "SELECT id, (renewal_fee + privacy_fee + misc_fee) AS total_cost
                                FROM fees
                                WHERE registrar_id = ?
                                  AND tld = ?";

                } else {

                    $query_f = "SELECT id, (renewal_fee + misc_fee) AS total_cost
                                FROM fees
                                WHERE registrar_id = ?
                                  AND tld = ?";

                }

                $q_f = $dbcon->stmt_init();

                if ($q_f->prepare($query_f)) {

                    $q_f->bind_param('is', $new_registrar_id, $tld);
                    $q_f->execute();
                    $q_f->store_result();
                    $q_f->bind_result($new_fee_id, $new_total_cost);
                    $q_f->fetch();
                    $q_f->close();

                    if ($new_fee_id == "") $new_fee_id = 0;
                    if ($new_total_cost == "") $new_total_cost = 0;

                } else {
                    $error->outputSqlError($dbcon, '1', 'ERROR');
                }

                $query_d = "INSERT INTO domains
                            (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                             hosting_id, fee_id, total_cost, `function`, notes, autorenew, privacy, created_by,
                             active, insert_time)
                            VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $q_d = $dbcon->stmt_init();

                if ($q_d->prepare($query_d)) {

                    $q_d->bind_param('iiisssiiiiidssiiiis', $new_owner_id, $new_registrar_id, $new_account_id,
                        $new_domain, $tld, $new_expiry_date, $new_cat_id, $new_dns_id, $new_ip_id, $new_hosting_id,
                        $new_fee_id, $new_total_cost, $new_function, $new_notes, $new_autorenew, $new_privacy,
                        $_SESSION['s_user_id'], $new_active, $timestamp);
                    $q_d->execute();

                    $temp_domain_id = $q_d->insert_id;

                    $q_d->close();

                } else {
                    $error->outputSqlError($dbcon, '1', 'ERROR');
                }

                $query_df = "INSERT INTO domain_field_data
                             (domain_id, insert_time)
                             VALUES
                             (?, ?)";
                $q_df = $dbcon->stmt_init();

                if ($q_df->prepare($query_df)) {

                    $q_df->bind_param('is', $temp_domain_id, $timestamp);
                    $q_df->execute();
                    $q_df->close();

                } else {
                    $error->outputSqlError($dbcon, '1', 'ERROR');
                }

                $query_df = "SELECT field_name
                             FROM domain_fields
                             ORDER BY `name`";
                $q_df = $dbcon->stmt_init();

                if ($q_df->prepare($query_df)) {

                    $q_df->execute();
                    $q_df->store_result();
                    $q_df->bind_result($field_name);

                    if ($q_df->num_rows() > 0) {

                        $count = 0;

                        while ($q_df->fetch()) {

                            $field_array[$count] = $field_name;
                            $count++;

                        }

                        foreach ($field_array as $field) {

                            $full_field = "new_" . $field;

                            $query_dfd = "UPDATE domain_field_data
                                          SET `" . $field . "` = ?
                                          WHERE domain_id = ?";
                            $q_dfd = $dbcon->stmt_init();

                            if ($q_dfd->prepare($query_dfd)) {

                                $q_dfd->bind_param('si', ${$full_field}, $temp_domain_id);
                                $q_dfd->execute();
                                $q_dfd->close();

                            } else {
                                $error->outputSqlError($dbcon, '1', 'ERROR');
                            }

                        }

                    }

                    $q_df->close();

                } else {
                    $error->outputSqlError($dbcon, '1', 'ERROR');
                }

                $maint->updateDomainFee($dbcon, $temp_domain_id);

                $queryB = new DomainMOD\QueryBuild();
                $sql = $queryB->missingFees('domains');
                $_SESSION['s_missing_domain_fees'] = $system->checkForRows($dbcon, $sql);

                $maint->updateSegments($dbcon);

                $system->checkExistingAssets($dbcon);

                $_SESSION['s_message_success'] .= 'Domain ' . $new_domain . ' Added<BR>';

            } else {

                $_SESSION['s_message_danger'] .= "This domain is already in " . SOFTWARE_TITLE . "<BR>";

            }

            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

    } else {
        
        if (!$domain->checkFormat($new_domain)) {
            $_SESSION['s_message_danger'] .= "The domain format is incorrect<BR>";
        }

        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_message_danger'] .= "The expiry date you entered is invalid<BR>";
        }

        if ($new_account_id == '' || $new_account_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Registrar Account<BR>";

        }

        if ($new_dns_id == '' || $new_dns_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the DNS Profile<BR>";

        }

        if ($new_ip_id == '' || $new_ip_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the IP Address<BR>";

        }

        if ($new_hosting_id == '' || $new_hosting_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Web Host<BR>";

        }

        if ($new_cat_id == '' || $new_cat_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Category<BR>";

        }

        if ($new_active == '') {

            $_SESSION['s_message_danger'] .= "Choose the Status<BR>";

        }

    }

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
echo $form->showInputText('new_domain', 'Domain (255)', '', $new_domain, '255', '', '1', '', '');
echo $form->showInputText('new_function', 'Function (255)', '', $new_function, '255', '', '', '', '');
if ($new_expiry_date == '') {
    $new_expiry_date = $time->toUserTimezone($timestamp_basic_plus_one_year, 'Y-m-d');
}
echo $form->showInputText('new_expiry_date', 'Expiry Date (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

$sql_account = "SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
                FROM registrar_accounts AS ra, owners AS o, registrars AS r
                WHERE ra.owner_id = o.id
                  AND ra.registrar_id = r.id
                ORDER BY r_name ASC, o_name ASC, ra.username ASC";
$result_account = mysqli_query($dbcon, $sql_account) or $error->outputSqlError($dbcon, '1', 'ERROR');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_account_id;

} else {

    $to_compare = $_SESSION['s_default_registrar_account'];

}
echo $form->showDropdownTop('new_account_id', 'Registrar Account', '', '1', '');
while ($row_account = mysqli_fetch_object($result_account)) { //@formatter:off

    echo $form->showDropdownOption($row_account->id, $row_account->r_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $to_compare);

}
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_dns_id;

} else {

    $to_compare = $_SESSION['s_default_dns'];

}
$sql_dns = "SELECT id, `name`
            FROM dns
            ORDER BY `name` ASC";
$result_dns = mysqli_query($dbcon, $sql_dns) or $error->outputSqlError($dbcon, '1', 'ERROR');

echo $form->showDropdownTop('new_dns_id', 'DNS Profile', '', '1', '');
while ($row_dns = mysqli_fetch_object($result_dns)) { //@formatter:off

    echo $form->showDropdownOption($row_dns->id, $row_dns->name, $to_compare);

}
echo $form->showDropdownBottom('');


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_ip_id;

} else {

    $to_compare = $_SESSION['s_default_ip_address_domains'];

}
$sql_ip = "SELECT id, `name`, ip
           FROM ip_addresses
           ORDER BY `name` ASC, ip ASC";
$result_ip = mysqli_query($dbcon, $sql_ip) or $error->outputSqlError($dbcon, '1', 'ERROR');

echo $form->showDropdownTop('new_ip_id', 'IP Address', '', '1', '');
while ($row_ip = mysqli_fetch_object($result_ip)) { //@formatter:off

    echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ' )', $to_compare);

}
echo $form->showDropdownBottom('');


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_hosting_id;

} else {

    $to_compare = $_SESSION['s_default_host'];

}
$sql_hosting = "SELECT id, `name`
                FROM hosting
                ORDER BY name ASC";
$result_hosting = mysqli_query($dbcon, $sql_hosting) or $error->outputSqlError($dbcon, '1', 'ERROR');

echo $form->showDropdownTop('new_hosting_id', 'Web Hosting Provider', '', '1', '');
while ($row_hosting = mysqli_fetch_object($result_hosting)) { //@formatter:off

    echo $form->showDropdownOption($row_hosting->id, $row_hosting->name, $to_compare);

}
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_cat_id;

} else {

    $to_compare = $_SESSION['s_default_category_domains'];

}
$sql_cat = "SELECT id, `name`
            FROM categories
            ORDER BY name ASC";
$result_cat = mysqli_query($dbcon, $sql_cat) or $error->outputSqlError($dbcon, '1', 'ERROR');

echo $form->showDropdownTop('new_cat_id', 'Category', '', '1', '');
while ($row_cat = mysqli_fetch_object($result_cat)) { //@formatter:off

    echo $form->showDropdownOption($row_cat->id, $row_cat->name, $to_compare);

}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_active', 'Domain Status', '', '', '');
echo $form->showDropdownOption('1', 'Active', $new_active);
echo $form->showDropdownOption('5', 'Pending (Registration)', $new_active);
echo $form->showDropdownOption('3', 'Pending (Renewal)', $new_active);
echo $form->showDropdownOption('2', 'Pending (Transfer)', $new_active);
echo $form->showDropdownOption('4', 'Pending (Other)', $new_active);
echo $form->showDropdownOption('10', 'Sold', $new_active);
echo $form->showDropdownOption('0', 'Expired', $new_active);
echo $form->showDropdownBottom('');

echo $form->showRadioTop('Auto Renewal?', '', '');
if ($new_autorenew == '') $new_autorenew = '1';
echo $form->showRadioOption('new_autorenew', '1', 'Yes', $new_autorenew, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_autorenew', '0', 'No', $new_autorenew, '', '');
echo $form->showRadioBottom('');

if ($new_privacy == '') $new_privacy = '1';
echo $form->showRadioTop('Privacy Enabled?', '', '');
echo $form->showRadioOption('new_privacy', '1', 'Yes', $new_privacy, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_privacy', '0', 'No', $new_privacy, '', '');
echo $form->showRadioBottom('');

echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');

$query = "SELECT field_name
          FROM domain_fields
          ORDER BY type_id ASC, `name` ASC";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();
    $q->bind_result($field_name);

    if ($q->num_rows() > 0) { ?>

        <h3>Custom Fields</h3><?php

        $count = 0;

        while ($q->fetch()) {

            $field_array[$count] = $field_name;
            $count++;

        }

        foreach ($field_array as $field) {

            $query_df = "SELECT df.name, df.field_name, df.type_id, df.description
                         FROM domain_fields AS df, custom_field_types AS cft
                         WHERE df.type_id = cft.id
                           AND df.field_name = ?";
            $q_df = $dbcon->stmt_init();

            if ($q_df->prepare($query_df)) {

                $q_df->bind_param('s', $field);
                $q_df->execute();
                $q_df->store_result();
                $q_df->bind_result($df_name, $df_field_name, $df_type_id, $df_description);

                while ($q_df->fetch()) {

                    if ($df_type_id == "1") { // Check Box

                        echo $form->showCheckbox('new_' . $df_field_name, '1', $df_name, $df_description, ${'new_' . $field}, '', '');

                    } elseif ($df_type_id == "2") { // Text

                        echo $form->showInputText('new_' . $df_field_name, $df_name, $df_description, ${'new_' . $field}, '255', '', '', '', '');

                    } elseif ($df_type_id == "3") { // Text Area

                        echo $form->showInputTextarea('new_' . $df_field_name, $df_name, $df_description, ${'new_' . $field}, '', '', '');

                    } elseif ($df_type_id == "4") { // Date

                        echo $form->showInputText('new_' . $df_field_name, $df_name, $df_description, ${'new_' . $field}, '10', '', '', '', '');

                    } elseif ($df_type_id == "5") { // Time Stamp

                        echo $form->showInputText('new_' . $df_field_name, $df_name, $df_description, ${'new_' . $field}, '19', '', '', '', '');

                    }

                }

                $q_df->close();

            } else {
                $error->outputSqlError($dbcon, '1', 'ERROR');
            }

        }

    }

    $q->close();

} else {
    $error->outputSqlError($dbcon, '1', 'ERROR');
}

echo $form->showSubmitButton('Add Domain', '', '');
echo $form->showFormBottom('');
?>
<?php require_once(DIR_INC . '/layout/footer.inc.php'); ?>
</body>
</html>
