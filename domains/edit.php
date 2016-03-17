<?php
/**
 * /domains/edit.php
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
$maint = new DomainMOD\Maintenance();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$timestamp = $time->stamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/domains-edit.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$did = $_REQUEST['did'];

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$new_domain = $_POST['new_domain'];
$new_tld = $_POST['new_tld'];
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
$sql = "SELECT field_name
        FROM domain_fields
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

    if ($date->checkDateFormat($new_expiry_date) && $new_cat_id != "" && $new_dns_id != "" && $new_ip_id != "" &&
        $new_hosting_id != "" && $new_account_id != "" && $new_cat_id != "0" && $new_dns_id != "0" &&
        $new_ip_id != "0" && $new_hosting_id != "0" && $new_account_id != "0") {

        $sql = "SELECT registrar_id, owner_id
                FROM registrar_accounts
                WHERE id = '" . $new_account_id . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {
            $new_registrar_id = $row->registrar_id;
            $new_owner_id = $row->owner_id;
        }

        $sql_fee_id = "SELECT id
                       FROM fees
                       WHERE registrar_id = '" . $new_registrar_id . "'
                         AND tld = '" . $new_tld . "'";
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

        if ($new_privacy == "1") {

            $fee_string = "renewal_fee + privacy_fee + misc_fee";

        } else {

            $fee_string = "renewal_fee + misc_fee";

        }

        $sql = "SELECT (" . $fee_string . ") AS total_cost
                FROM fees
                WHERE registrar_id = '" . $new_registrar_id . "'
                  AND tld = '" . $new_tld . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {
            $new_total_cost = $row->total_cost;
        }

        $query = "UPDATE domains
                  SET owner_id = ?,
                      registrar_id = ?,
                      account_id = ?,
                      tld = ?,
                      expiry_date = ?,
                      cat_id = ?,
                      dns_id = ?,
                      ip_id = ?,
                      hosting_id = ?,
                      fee_id = ?,
                      total_cost = ?,
                      `function` = ?,
                      notes = ?,
                      autorenew = ?,
                      privacy = ?,
                      active = ?,
                      fee_fixed = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('iiissiiiiisssiiiisi', $new_owner_id, $new_registrar_id, $new_account_id, $new_tld,
                $new_expiry_date, $new_cat_id, $new_dns_id, $new_ip_id, $new_hosting_id, $temp_fee_id, $new_total_cost,
                $new_function, $new_notes, $new_autorenew, $new_privacy, $new_active, $temp_fee_fixed, $timestamp,
                $did);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

        $sql = "SELECT field_name
                FROM domain_fields
                ORDER BY name";
        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) > 0) {

            $count = 0;

            while ($row = mysqli_fetch_object($result)) {

                $field_array[$count] = $row->field_name;
                $count++;

            }

            foreach ($field_array as $field) {

                $full_field = "new_" . $field;

                $sql = "UPDATE domain_field_data
                        SET `" . $field . "` = '" . mysqli_real_escape_string($connection, ${$full_field}) . "',
                            update_time = '" . $timestamp . "'
                        WHERE domain_id = '" . $did . "'";
                $result = mysqli_query($connection, $sql);

            }

        }

        $_SESSION['s_message_success'] = "Domain " . $new_domain . " Updated<BR>";

        $maint->updateSegments($connection);

        $queryB = new DomainMOD\QueryBuild();

        $sql = $queryB->missingFees('domains');
        $_SESSION['s_missing_domain_fees'] = $system->checkForRows($connection, $sql);

        header("Location: edit.php?did=$did");
        exit;

    } else {

        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_message_danger'] .= "The expiry date you entered is invalid<BR>";
        }

    }

} else {

    $sql = "SELECT d.domain, d.tld, d.expiry_date, d.cat_id, d.dns_id, d.ip_id, d.hosting_id, d.function, d.notes,
              d.autorenew, d.privacy, d.active, ra.id AS account_id
            FROM domains AS d, registrar_accounts AS ra
            WHERE d.account_id = ra.id
              AND d.id = '" . $did . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    while ($row = mysqli_fetch_object($result)) {

        $new_domain = $row->domain;
        $new_tld = $row->tld;
        $new_expiry_date = $row->expiry_date;
        $new_cat_id = $row->cat_id;
        $new_dns_id = $row->dns_id;
        $new_ip_id = $row->ip_id;
        $new_hosting_id = $row->hosting_id;
        $new_function = $row->function;
        $new_notes = $row->notes;
        $new_autorenew = $row->autorenew;
        $new_privacy = $row->privacy;
        $new_active = $row->active;
        $new_account_id = $row->account_id;

    }

}

if ($del == "1") {

    $sql = "SELECT domain_id
            FROM ssl_certs
            WHERE domain_id = '" . $did . "'";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {
        $existing_ssl_certs = 1;
    }

    if ($existing_ssl_certs > 0) {

        $_SESSION['s_message_danger'] = "This Domain has SSL Certificates associated with it and cannot be deleted<BR>";

    } else {

        $_SESSION['s_message_danger'] = "Are you sure you want to delete this Domain?<BR><BR><a
            href=\"edit.php?did=$did&really_del=1\">YES, REALLY DELETE THIS DOMAIN</a><BR>";

    }

}

if ($really_del == "1") {

    $sql = "DELETE FROM domains
            WHERE id = '" . $did . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "DELETE FROM domain_field_data
            WHERE domain_id = '" . $did . "'";
    $result = mysqli_query($connection, $sql);

    $_SESSION['s_message_success'] = "Domain " . $new_domain . " Deleted<BR>";

    $maint->updateSegments($connection);

    $system->checkExistingAssets($connection);

    header("Location: ../domains/index.php");
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
echo '<strong>Domain</strong><BR>';
echo $new_domain . '<BR><BR>';
echo $form->showInputText('new_function', 'Function (255)', '', $new_function, '255', '', '', '');
echo $form->showInputText('new_expiry_date', 'Expiry Date (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '', '');

$sql_account = "SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
                FROM registrar_accounts AS ra, owners AS o, registrars AS r
                WHERE ra.owner_id = o.id
                  AND ra.registrar_id = r.id
                ORDER BY r_name ASC, o_name ASC, ra.username ASC";
$result_account = mysqli_query($connection, $sql_account) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_account_id', 'Registrar Account', '', '');
while ($row_account = mysqli_fetch_object($result_account)) { //@formatter:off

    echo $form->showDropdownOption($row_account->id, $row_account->r_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $new_account_id);

}
echo $form->showDropdownBottom('');

$sql_dns = "SELECT id, `name`
            FROM dns
            ORDER BY name ASC";
$result_dns = mysqli_query($connection, $sql_dns) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_dns_id', 'DNS Profile', '', '');
while ($row_dns = mysqli_fetch_object($result_dns)) { //@formatter:off

    echo $form->showDropdownOption($row_dns->id, $row_dns->name, $new_dns_id);

}
echo $form->showDropdownBottom('');

$sql_ip = "SELECT id, `name`, ip
           FROM ip_addresses
           ORDER BY `name` ASC, ip ASC";
$result_ip = mysqli_query($connection, $sql_ip) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_ip_id', 'IP Address', '', '');
while ($row_ip = mysqli_fetch_object($result_ip)) { //@formatter:off

    echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ' )', $new_ip_id);

}
echo $form->showDropdownBottom('');

$sql_hosting = "SELECT id, `name`
                FROM hosting
                ORDER BY name ASC";
$result_hosting = mysqli_query($connection, $sql_hosting) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_hosting_id', 'Web Hosting Provider', '', '');
while ($row_hosting = mysqli_fetch_object($result_hosting)) { //@formatter:off

    echo $form->showDropdownOption($row_hosting->id, $row_hosting->name, $new_hosting_id);

}
echo $form->showDropdownBottom('');

$sql_cat = "SELECT id, `name`
            FROM categories
            ORDER BY name ASC";
$result_cat = mysqli_query($connection, $sql_cat) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_cat_id', 'Category', '', '');
while ($row_cat = mysqli_fetch_object($result_cat)) { //@formatter:off

    echo $form->showDropdownOption($row_cat->id, $row_cat->name, $new_cat_id);

}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_active', 'Domain Status', '', '');
echo $form->showDropdownOption('1', 'Active', $new_active);
echo $form->showDropdownOption('5', 'Pending (Registration)', $new_active);
echo $form->showDropdownOption('3', 'Pending (Renewal)', $new_active);
echo $form->showDropdownOption('2', 'Pending (Transfer)', $new_active);
echo $form->showDropdownOption('4', 'Pending (Other)', $new_active);
echo $form->showDropdownOption('10', 'Sold', $new_active);
echo $form->showDropdownOption('0', 'Expired', $new_active);
echo $form->showDropdownBottom('');

echo $form->showRadioTop('Auto Renewal?', '', '');
echo $form->showRadioOption('new_autorenew', '1', 'Yes', $new_autorenew, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_autorenew', '0', 'No', $new_autorenew, '', '');
echo $form->showRadioBottom('');

echo $form->showRadioTop('Privacy Enabled?', '', '');
echo $form->showRadioOption('new_privacy', '1', 'Yes', $new_privacy, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_privacy', '0', 'No', $new_privacy, '', '');
echo $form->showRadioBottom('');

if ($new_notes != '') {
    $subtext = '[<a target="_blank" href="notes.php?did=' . $did . '">view full notes</a>]';
} else {
    $subtext = '';
}
echo $form->showInputTextarea('new_notes', 'Notes', $subtext, $new_notes, '', '');

$sql = "SELECT field_name
        FROM domain_fields
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

        $sql = "SELECT df.name, df.field_name, df.type_id, df.description
                FROM domain_fields AS df, custom_field_types AS cft
                WHERE df.type_id = cft.id
                  AND df.field_name = '" . $field . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_data = "SELECT " . $row->field_name . "
                         FROM domain_field_data
                         WHERE domain_id = '" . $did . "'";
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

echo $form->showInputHidden('did', $did);
echo $form->showInputHidden('new_domain', $new_domain);
echo $form->showInputHidden('new_tld', $new_tld);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');

$sql_accounts = "SELECT id
                 FROM dw_accounts
                 WHERE domain = '" . $new_domain . "'";
$result_accounts = mysqli_query($connection, $sql_accounts);

if ($result_accounts === false || mysqli_num_rows($result_accounts) <= 0) {

    $no_results_accounts = 1;

}

$sql_dns_zones = "SELECT id
                  FROM dw_dns_zones
                  WHERE domain = '" . mysqli_real_escape_string($connection, $new_domain) . "'";
$result_dns_zones = mysqli_query($connection, $sql_dns_zones);

if ($result_dns_zones === false || mysqli_num_rows($result_dns_zones) <= 0) {

    $no_results_dns_zones = 1;

}

if ($no_results_accounts !== 1 || $no_results_dns_zones !== 1) { ?>

    <BR><BR><h3>Data Warehouse Information for <?php echo $new_domain; ?></h3><?php

}

if ($no_results_accounts !== 1) { ?>

    <h4>Accounts</h4><?php

    $sql = "SELECT s.id, s.name
            FROM dw_accounts AS a, dw_servers AS s
            WHERE a.server_id = s.id
              AND a.domain = '" . $new_domain . "'
            ORDER BY s.name ASC, a.unix_startdate DESC";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $dwdisplay = new DomainMOD\DwDisplay(); ?>

    <table id="<?php echo $slug; ?>-account" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
        </tr>
        </thead>
        <tbody><?php

            while ($row = mysqli_fetch_object($result)) { ?>

                <tr>
                    <td></td>
                    <td><?php echo $row->name; ?></td>

                    <?php echo $dwdisplay->account($connection, $row->id, $new_domain); ?>

                </tr><?php

            } ?>

        </tbody>
    </table><?php

}

if ($no_results_dns_zones !== 1) { ?>

    <h4>DNS Zones & Records</h4><?php
    $sql = "SELECT s.id AS dw_server_id, s.name
            FROM dw_dns_zones AS z, dw_servers AS s
            WHERE z.server_id = s.id
              AND z.domain = '" . $new_domain . "'
            ORDER BY s.name, z.zonefile, z.domain";
    $result = mysqli_query($connection, $sql)
    or $error->outputOldSqlError($connection);

    $dwdisplay = new DomainMOD\DwDisplay(); ?>

    <table id="<?php echo $slug; ?>-zone" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th></th>
            <th></th>
        </tr>
        </thead>
        <tbody><?php

        while ($row = mysqli_fetch_object($result)) { ?>

            <tr>
                <td></td>
                <td valign="top"><?php echo $row->name; ?></td>
                <td>
                    <?php echo $dwdisplay->zone($connection, $row->dw_server_id, $new_domain); ?>
                </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php
}
?>
<BR><BR><a href="edit.php?did=<?php echo urlencode($did); ?>&del=1">DELETE THIS DOMAIN</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
