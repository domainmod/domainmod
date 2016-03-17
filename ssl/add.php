<?php
/**
 * /ssl/add.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$timestamp = $time->stamp();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/ssl-add.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$new_domain_id = $_POST['new_domain_id'];
$new_name = $_POST['new_name'];
$new_type_id = $_POST['new_type_id'];
$new_ip_id = $_POST['new_ip_id'];
$new_cat_id = $_POST['new_cat_id'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_account_id = $_POST['new_account_id'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];

// Custom Fields
$query = "SELECT field_name
          FROM ssl_cert_fields
          ORDER BY `name`";
$q = $conn->stmt_init();

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
    $error->outputSqlError($conn, "ERROR");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_type_id != "" && $new_ip_id != "" &&
        $new_cat_id != "" && $new_domain_id != "" && $new_account_id != "" && $new_type_id != "0" && $new_ip_id != "0"
        && $new_cat_id != "0" && $new_domain_id != "0" && $new_account_id != "0"
    ) {

        $query = "SELECT ssl_provider_id, owner_id
                  FROM ssl_accounts
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_account_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($new_ssl_provider_id, $new_owner_id);
            $q->fetch();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "SELECT id, (renewal_fee + misc_fee) AS total_cost
                  FROM ssl_fees
                  WHERE ssl_provider_id = ?
                    AND type_id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('ii', $new_ssl_provider_id, $new_type_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($new_fee_id, $new_total_cost);
            $q->fetch();
            $q->close();

            if ($new_fee_id == "") $new_fee_id = 0;
            if ($new_total_cost == "") $new_total_cost = 0;

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "INSERT INTO ssl_certs
                  (owner_id, ssl_provider_id, account_id, domain_id, `name`, type_id, ip_id, cat_id, expiry_date,
                   fee_id, total_cost, notes, active, insert_time)
                  VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('iiiisiiisidsis', $new_owner_id, $new_ssl_provider_id, $new_account_id, $new_domain_id,
                $new_name, $new_type_id, $new_ip_id, $new_cat_id, $new_expiry_date, $new_fee_id, $new_total_cost,
                $new_notes, $new_active, $timestamp);
            $q->execute();

            $temp_ssl_id = $q->insert_id;

            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "INSERT INTO ssl_cert_field_data
                  (ssl_id, insert_time)
                  VALUES
                  (?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('is', $temp_ssl_id, $timestamp);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "SELECT field_name
                  FROM ssl_cert_fields
                  ORDER BY `name`";
        $q = $conn->stmt_init();

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

                    $full_field = "new_" . $field;

                    $query_f = "UPDATE ssl_cert_field_data
                                SET `" . $field . "` = ?
                                WHERE ssl_id = ?";
                    $q_f = $conn->stmt_init();

                    if ($q_f->prepare($query_f)) {

                        $q_f->bind_param('si', ${$full_field}, $temp_ssl_id);
                        $q_f->execute();
                        $q_f->close();

                    } else {
                        $error->outputSqlError($conn, "ERROR");
                    }

                }

            }

            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $_SESSION['s_message_success'] = "SSL Certificate $new_name Added<BR>";

        $queryB = new DomainMOD\QueryBuild();

        $sql = $queryB->missingFees('ssl_certs');
        $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($connection, $sql);

        $system->checkExistingAssets($connection);

    } else {

        if ($new_name == "") {
            $_SESSION['s_message_danger'] .= "Enter a name for the SSL certificate<BR>";
        }
        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_message_danger'] .= "The expiry date you entered is invalid<BR>";
        }

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_name', 'Host / Label (100)', '', $new_name, '100', '', '', '');
if ($new_expiry_date == '') {
    $new_expiry_date = $time->toUserTimezone($timestamp_basic_plus_one_year, 'Y-m-d');
}
echo $form->showInputText('new_expiry_date', 'Expiry Date (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '', '');





echo $form->showDropdownTop('new_domain_id', 'Domain', '', '');
$query = "SELECT id, domain
          FROM domains
          WHERE (active NOT IN ('0', '10') OR id = ?)
          ORDER BY domain";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('i', $new_domain_id);
    $q->execute();
    $q->store_result();
    $q->bind_result($id, $domain);

    while ($q->fetch()) {

        echo $form->showDropdownOption($id, $domain, '');

    }

    $q->close();

} else $error->outputSqlError($conn, "ERROR");
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_account_id;

} else {

    $to_compare = $_SESSION['s_default_ssl_provider_account'];

}
$sql_account = "SELECT sslpa.id, sslpa.username, o.name AS o_name, sslp.name AS sslp_name
                FROM ssl_accounts AS sslpa, owners AS o, ssl_providers AS sslp
                WHERE sslpa.owner_id = o.id
                  AND sslpa.ssl_provider_id = sslp.id
                ORDER BY sslp_name ASC, o_name ASC, sslpa.username ASC";
$result_account = mysqli_query($connection, $sql_account) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_account_id', 'SSL Provider Account', '', '');
while ($row_account = mysqli_fetch_object($result_account)) {

    echo $form->showDropdownOption($row_account->id, $row_account->sslp_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $to_compare);

}
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_type_id;

} else {

    $to_compare = $_SESSION['s_default_ssl_type'];

}
$sql_type = "SELECT id, type
             FROM ssl_cert_types
             ORDER BY type ASC";
$result_type = mysqli_query($connection, $sql_type) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_type_id', 'Certificate Type', '', '');
while ($row_type = mysqli_fetch_object($result_type)) {

    echo $form->showDropdownOption($row_type->id, $row_type->type, $to_compare);

}
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_ip_id;

} else {

    $to_compare = $_SESSION['s_default_ip_address_ssl'];

}
$sql_ip = "SELECT id, ip, `name`
           FROM ip_addresses
           ORDER BY `name`, ip";
$result_ip = mysqli_query($connection, $sql_ip) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_ip_id', 'IP Address', '', '');
while ($row_ip = mysqli_fetch_object($result_ip)) {

    echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ')', $to_compare);

}
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_cat_id;

} else {

    $to_compare = $_SESSION['s_system_default_category_ssl'];

}
$sql_cat = "SELECT id, `name`
            FROM categories
            ORDER BY `name`";
$result_cat = mysqli_query($connection, $sql_cat) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_cat_id', 'Category', '', '');
while ($row_cat = mysqli_fetch_object($result_cat)) {

    echo $form->showDropdownOption($row_cat->id, $row_cat->name, $to_compare);

}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_active', 'Certificate Status', '', '');
echo $form->showDropdownOption('1', 'Active', $new_active);
echo $form->showDropdownOption('5', 'Pending (Registration)', $new_active);
echo $form->showDropdownOption('3', 'Pending (Renewal)', $new_active);
echo $form->showDropdownOption('4', 'Pending (Other)', $new_active);
echo $form->showDropdownOption('0', 'Expired', $new_active);
echo $form->showDropdownBottom('');

echo $form->showInputTextarea('new_notes', 'Notes', $subtext, $new_notes, '', '');

$query = "SELECT field_name
          FROM ssl_cert_fields
          ORDER BY type_id, `name`";
$q = $conn->stmt_init();

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

            $query_cf = "SELECT sf.name, sf.field_name, sf.type_id, sf.description
                         FROM ssl_cert_fields AS sf, custom_field_types AS cft
                         WHERE sf.type_id = cft.id
                           AND sf.field_name = ?";
            $q_cf = $conn->stmt_init();

            if ($q_cf->prepare($query_cf)) {

                $q_cf->bind_param('s', $field);
                $q_cf->execute();
                $q_cf->store_result();
                $q_cf->bind_result($name, $field_name, $type_id, $description);

                while ($q_cf->fetch()) {

                    if ($type_id == "1") { // Check Box

                        echo $form->showCheckbox('new_' . $field_name, '1', $name, $description, ${'new_' . $field}, '', '');

                    } elseif ($type_id == "2") { // Text

                        echo $form->showInputText('new_' . $field_name, $name, $description, ${'new_' . $field}, '255', '', '', '');

                    } elseif ($type_id == "3") { // Text Area

                        echo $form->showInputTextarea('new_' . $field_name, $name, $description, ${'new_' . $field}, '', '');

                    }

                }

                $q_cf->close();

            } else {
                $error->outputSqlError($conn, "ERROR");
            }

        }

        echo "<BR>";

    }

    $q->close();

} else {
    $error->outputSqlError($conn, "ERROR");
}

echo $form->showSubmitButton('Add SSL Certificate', '', '');
echo $form->showFormBottom('');
?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
