<?php
/**
 * /ssl/edit.php
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

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/ssl-edit.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslcid = $_REQUEST['sslcid'];
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
$sql = "SELECT field_name
        FROM ssl_cert_fields
        ORDER BY `name`";
$result = mysqli_query($connection, $sql);

if (mysqli_num_rows($result) > 0) {

    $count = 0;

    while ($row = mysqli_fetch_object($result)) {

        $field_array[$count] = $row->field_name;
        $count++;

    }

    foreach ($field_array as $field) {

        $full_field = "new_" . $field . "";
        ${'new_' . $field} = $_POST[$full_field];

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_domain_id != "" && $new_account_id != "" &&
        $new_type_id != "" && $new_ip_id != "" && $new_cat_id != "" && $new_domain_id != "0" && $new_account_id != "0"
        && $new_type_id != "0" && $new_ip_id != "0" && $new_cat_id != "0") {

        $sql = "SELECT ssl_provider_id, owner_id
                FROM ssl_accounts
                WHERE id = '" . $new_account_id . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {
            $new_ssl_provider_id = $row->ssl_provider_id;
            $new_owner_id = $row->owner_id;
        }

        $sql_fee_id = "SELECT id
                       FROM ssl_fees
                       WHERE ssl_provider_id = '" . $new_ssl_provider_id . "'
                         AND type_id = '" . $new_type_id . "'";
        $result_fee_id = mysqli_query($connection, $sql_fee_id);

        if (mysqli_num_rows($result_fee_id) >= 1) {

            while ($row_fee_id = mysqli_fetch_object($result_fee_id)) {
                $temp_fee_id = $row_fee_id->id;
            }
            $temp_fee_fixed = "1";

        } else {

            $temp_fee_id = "0";
            $temp_fee_fixed = "0";

        }

        $fee_string = "renewal_fee + misc_fee";

        $sql = "SELECT (" . $fee_string . ") AS total_cost
                FROM ssl_fees
                WHERE ssl_provider_id = '" . $new_ssl_provider_id . "'
                  AND type_id = '" . $new_type_id . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {
            $new_total_cost = $row->total_cost;
        }

        $sql_update = "UPDATE ssl_certs
                       SET owner_id = '" . $new_owner_id . "',
                              ssl_provider_id = '" . $new_ssl_provider_id . "',
                           account_id = '" . $new_account_id . "',
                           domain_id = '" . $new_domain_id . "',
                           `name` = '" . mysqli_real_escape_string($connection, $new_name) . "',
                           type_id = '" . $new_type_id . "',
                           ip_id = '" . $new_ip_id . "',
                           cat_id = '" . $new_cat_id . "',
                           expiry_date = '" . $new_expiry_date . "',
                           fee_id = '" . $temp_fee_id . "',
                           total_cost = '" . $new_total_cost . "',
                           notes = '" . mysqli_real_escape_string($connection, $new_notes) . "',
                           active = '" . $new_active . "',
                           fee_fixed = '" . $temp_fee_fixed . "',
                           update_time = '" . $timestamp . "'
                       WHERE id = '" . $sslcid . "'";
        $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

        $sql = "SELECT field_name
                FROM ssl_cert_fields
                ORDER BY `name`";
        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) > 0) {

            $count = 0;

            while ($row = mysqli_fetch_object($result)) {

                $field_array[$count] = $row->field_name;
                $count++;

            }

            foreach ($field_array as $field) {

                $full_field = "new_" . $field;

                $sql = "UPDATE ssl_cert_field_data
                    SET `" . $field . "` = '" . mysqli_real_escape_string($connection, ${$full_field}) . "',
                        update_time = '" . $timestamp . "'
                    WHERE ssl_id = '" . $sslcid . "'";
                $result = mysqli_query($connection, $sql);

            }

        }

        $_SESSION['s_message_success'] = "SSL Certificate " . $new_name . " Updated<BR>";

        $queryB = new DomainMOD\QueryBuild();

        $sql = $queryB->missingFees('ssl_certs');
        $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($connection, $sql);

        header("Location: edit.php?sslcid=$sslcid");
        exit;

    } else {

        if ($new_name == "") {
            $_SESSION['s_message_danger'] .= "Enter the SSL certificate name<BR>";
        }
        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_message_danger'] .= "The expiry date you entered is invalid<BR>";
        }

    }

} else {

    $sql = "SELECT sslc.domain_id, sslc.name, sslc.expiry_date, sslc.notes, sslc.active, sslpa.id AS account_id,
                sslcf.id AS type_id, ip.id AS ip_id, cat.id AS cat_id
            FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_cert_types AS sslcf, ip_addresses AS ip,
                categories AS cat
            WHERE sslc.account_id = sslpa.id
              AND sslc.type_id = sslcf.id
              AND sslc.ip_id = ip.id
              AND sslc.cat_id = cat.id
              AND sslc.id = '" . $sslcid . "'";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {

        $new_domain_id = $row->domain_id;
        $new_name = $row->name;
        $new_type_id = $row->type_id;
        $new_ip_id = $row->ip_id;
        $new_cat_id = $row->cat_id;
        $new_expiry_date = $row->expiry_date;
        $new_notes = $row->notes;
        $new_active = $row->active;
        $new_account_id = $row->account_id;

    }

}

if ($del == "1") {

    $_SESSION['s_message_danger'] .= "Are you sure you want to delete this SSL Certificate?<BR><BR>
        <a href=\"edit.php?sslcid=$sslcid&really_del=1\">YES, REALLY DELETE THIS SSL CERTIFICATE
        ACCOUNT</a><BR>";

}

if ($really_del == "1") {

    $sql = "DELETE FROM ssl_certs WHERE id = '" . $sslcid . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "DELETE FROM ssl_cert_field_data WHERE ssl_id = '" . $sslcid . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT type FROM ssl_cert_types WHERE id = '" . $new_type_id . "'";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {
        $temp_type = $row->type;
    }

    $_SESSION['s_message_success'] = "SSL Certificate $new_name ($temp_type) Deleted<BR>";

    $system->checkExistingAssets($connection);

    header("Location: index.php");
    exit;

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
echo $form->showInputText('new_expiry_date', 'Expiry Date (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '', '');

$sql_domain = "SELECT id, domain
               FROM domains
               WHERE (active NOT IN ('0', '10') OR id = '" . $new_domain_id . "')
               ORDER BY domain";
$result_domain = mysqli_query($connection, $sql_domain) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_domain_id', 'Domain', '', '');
while ($row_domain = mysqli_fetch_object($result_domain)) {

    echo $form->showDropdownOption($row_domain->id, $row_domain->domain, $new_domain_id);

}
echo $form->showDropdownBottom('');

$sql_account = "SELECT sslpa.id, sslpa.username, o.name AS o_name, sslp.name AS sslp_name
                FROM ssl_accounts AS sslpa, owners AS o, ssl_providers AS sslp
                WHERE sslpa.owner_id = o.id
                  AND sslpa.ssl_provider_id = sslp.id
                ORDER BY sslp_name ASC, o_name ASC, sslpa.username ASC";
$result_account = mysqli_query($connection, $sql_account) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_account_id', 'SSL Provider Account', '', '');
while ($row_account = mysqli_fetch_object($result_account)) {

    echo $form->showDropdownOption($row_account->id, $row_account->sslp_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $new_account_id);

}
echo $form->showDropdownBottom('');

$sql_type = "SELECT id, type
             FROM ssl_cert_types
             ORDER BY type ASC";
$result_type = mysqli_query($connection, $sql_type) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_type_id', 'Certificate Type', '', '');
while ($row_type = mysqli_fetch_object($result_type)) {

    echo $form->showDropdownOption($row_type->id, $row_type->type, $new_type_id);

}
echo $form->showDropdownBottom('');

$sql_ip = "SELECT id, ip, `name`
           FROM ip_addresses
           ORDER BY `name`, ip";
$result_ip = mysqli_query($connection, $sql_ip) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_ip_id', 'IP Address', '', '');
while ($row_ip = mysqli_fetch_object($result_ip)) {

    echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ')', $new_ip_id);

}
echo $form->showDropdownBottom('');

$sql_cat = "SELECT id, `name`
            FROM categories
            ORDER BY `name`";
$result_cat = mysqli_query($connection, $sql_cat) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_cat_id', 'Category', '', '');
while ($row_cat = mysqli_fetch_object($result_cat)) {

    echo $form->showDropdownOption($row_cat->id, $row_cat->name, $new_cat_id);

}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_active', 'Certificate Status', '', '');
echo $form->showDropdownOption('1', 'Active', $new_active);
echo $form->showDropdownOption('5', 'Pending (Registration)', $new_active);
echo $form->showDropdownOption('3', 'Pending (Renewal)', $new_active);
echo $form->showDropdownOption('4', 'Pending (Other)', $new_active);
echo $form->showDropdownOption('0', 'Expired', $new_active);
echo $form->showDropdownBottom('');

if ($new_notes != '') {
    $subtext = '[<a target="_blank" href="notes.php?sslcid=' . $sslcid . '">view full notes</a>]';
} else {
    $subtext = '';
}
echo $form->showInputTextarea('new_notes', 'Notes', $subtext, $new_notes, '', '');

$sql = "SELECT field_name
        FROM ssl_cert_fields
        ORDER BY type_id, `name`";
$result = mysqli_query($connection, $sql);

if (mysqli_num_rows($result) > 0) { ?>

    <h3>Custom Fields</h3><?php

    $count = 0;

    while ($row = mysqli_fetch_object($result)) {

        $field_array[$count] = $row->field_name;
        $count++;

    }

    foreach ($field_array as $field) {

        $sql = "SELECT sf.name, sf.field_name, sf.type_id, sf.description
                FROM ssl_cert_fields AS sf, custom_field_types AS cft
                WHERE sf.type_id = cft.id
                  AND sf.field_name = '" . $field . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_data = "SELECT " . $row->field_name . "
                     FROM ssl_cert_field_data
                     WHERE ssl_id = '" . $sslcid . "'";
            $result_data = mysqli_query($connection, $sql_data);

            while ($row_data = mysqli_fetch_object($result_data)) {

                $field_data = $row_data->{$row->field_name};

            }

            if ($row->type_id == "1") { // Check Box

                echo $form->showCheckbox('new_' . $row->field_name, '1', $row->name, $row->description, $field_data, '', '');

            } elseif ($row->type_id == "2") { // Text

                echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, $field_data, '255', '', '', '');

            } elseif ($row->type_id == "3") { // Text Area

                echo $form->showInputTextarea('new_' . $row->field_name, $row->name, $row->description, $field_data, '', '');

            }

        }

    }

}

echo $form->showInputHidden('sslcid', $sslcid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><BR><a href="edit.php?sslcid=<?php echo $system->cleanVar('i', $sslcid); ?>&del=1">DELETE THIS SSL CERTIFICATE</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
