<?php
/**
 * /ssl/index.php
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
<?php //@formatter:off
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$currency = new DomainMOD\Currency();
$customField = new DomainMOD\CustomField();
$form = new DomainMOD\Form();
$date = new DomainMOD\Date();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/ssl-main.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);

$export_data = $_GET['export_data'];
$oid = $_REQUEST['oid'];
$did = $_REQUEST['did'];
$sslpid = $_REQUEST['sslpid'];
$sslpaid = $_REQUEST['sslpaid'];
$ssltid = $_REQUEST['ssltid'];
$sslipid = $_REQUEST['sslipid'];
$sslpcid = $_REQUEST['sslpcid'];
$is_active = $_REQUEST['is_active'];
$search_for = $_REQUEST['search_for'];
$from_dropdown = $_REQUEST['from_dropdown'];
$expand = $_REQUEST['expand'];
$daterange = $_REQUEST['daterange'];

list($new_start_date, $new_end_date) = $date->splitAndCheckRange($daterange);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $from_dropdown = 0;

    $date = new DomainMOD\Date();

    if ($new_start_date > $new_end_date) {

        $_SESSION['s_message_danger'] .= 'The date range is invalid<BR>';

    }

}

if ($export_data != "1") {

    if ($from_dropdown != "1") {

        if ($search_for != "") {

            $_SESSION['s_search_for_ssl'] = $search_for;

        } else {

            $_SESSION['s_search_for_ssl'] = "";

        }

        if ($new_start_date != '' && $new_start_date != 'YYYY-MM-DD') {

            $_SESSION['s_start_date'] = $new_start_date;
            $_SESSION['s_end_date'] = $new_end_date;

        } else {

            $_SESSION['s_start_date'] = 'YYYY-MM-DD';
            $_SESSION['s_end_date'] = 'YYYY-MM-DD';

        }

    }

}

if ($_SESSION['s_start_date'] == '') $_SESSION['s_start_date'] = 'YYYY-MM-DD';
if ($_SESSION['s_end_date'] == '') $_SESSION['s_end_date'] = 'YYYY-MM-DD';

if ($is_active == "") $is_active = "LIVE";

if ($is_active == "0") { $is_active_string = " AND sslc.active = '0' ";
} elseif ($is_active == "1") { $is_active_string = " AND sslc.active = '1' ";
} elseif ($is_active == "2") { $is_active_string = " AND sslc.active = '2' ";
} elseif ($is_active == "3") { $is_active_string = " AND sslc.active = '3' ";
} elseif ($is_active == "4") { $is_active_string = " AND sslc.active = '4' ";
} elseif ($is_active == "5") { $is_active_string = " AND sslc.active = '5' ";
} elseif ($is_active == "6") { $is_active_string = " AND sslc.active = '6' ";
} elseif ($is_active == "7") { $is_active_string = " AND sslc.active = '7' ";
} elseif ($is_active == "8") { $is_active_string = " AND sslc.active = '8' ";
} elseif ($is_active == "9") { $is_active_string = " AND sslc.active = '9' ";
} elseif ($is_active == "10") { $is_active_string = " AND sslc.active = '10' ";
} elseif ($is_active == "LIVE") { $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
} elseif ($is_active == "ALL") { $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
}

if ($oid != "") {
    $oid_string = " AND o.id = '$oid' ";
} else {
    $oid_string = "";
}
if ($did != "") {
    $did_string = " AND d.id = '$did' ";
} else {
    $did_string = "";
}
if ($sslpid != "") {
    $sslpid_string = " AND sslp.id = '$sslpid' ";
} else {
    $sslpid_string = "";
}
if ($sslpaid != "") {
    $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
} else {
    $sslpaid_string = "";
}
if ($ssltid != "") {
    $ssltid_string = " AND sslc.type_id = '$ssltid' ";
} else {
    $ssltid_string = "";
}
if ($sslipid != "") {
    $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
} else {
    $sslipid_string = "";
}
if ($sslpcid != "") {
    $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
} else {
    $sslpcid_string = "";
}
if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
    $range_string = " AND (sslc.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND sslc.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
} else {
    $range_string = "";
}
if ($_SESSION['s_search_for_ssl'] != "") {
    $search_string = " AND (sslc.name LIKE '%" . $_SESSION['s_search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['s_search_for_ssl'] . "%')";
} else {
    $search_string = "";
}

$sslfd_columns = $customField->getCustomFieldsSql($connection, 'ssl_cert_fields', 'sslfd');

$sql = "SELECT sslc.id, sslc.domain_id, sslc.name, sslc.expiry_date, sslc.total_cost, sslc.notes, sslc.active, sslc.creation_type_id, sslc.created_by, sslc.insert_time, sslc.update_time, sslpa.id AS sslpa_id, sslpa.username, sslp.id AS sslp_id, sslp.name AS ssl_provider_name, o.id AS o_id, o.name AS owner_name, f.id AS f_id, f.initial_fee, f.renewal_fee, f.misc_fee, cc.conversion, d.domain, sslcf.id as type_id, sslcf.type, ip.id AS ip_id, ip.name as ip_name, ip.ip, ip.rdns, cat.id AS cat_id, cat.name AS cat_name" . $sslfd_columns . "
        FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS c, currency_conversions AS cc, domains AS d, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat, ssl_cert_field_data AS sslfd
        WHERE sslc.account_id = sslpa.id
          AND sslpa.ssl_provider_id = sslp.id
          AND sslpa.owner_id = o.id
          AND sslc.fee_id = f.id
          AND f.currency_id = c.id
          AND c.id = cc.currency_id
          AND sslc.domain_id = d.id
          AND sslc.type_id = sslcf.id
          AND sslc.ip_id = ip.id
          AND sslc.cat_id = cat.id
          AND sslc.id = sslfd.ssl_id
          AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
          $is_active_string
          $oid_string
          $did_string
          $sslpid_string
          $sslpaid_string
          $ssltid_string
          $sslipid_string
          $sslpcid_string
          $range_string
          $search_string";
$_SESSION['s_raw_list_type'] = 'ssl-certs';
$_SESSION['s_raw_list_query'] = $sql;

$sql_grand_total = "SELECT SUM(sslc.total_cost * cc.conversion) AS grand_total
                    FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS c, currency_conversions AS cc, domains AS d, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat
                    WHERE sslc.account_id = sslpa.id
                      AND sslpa.ssl_provider_id = sslp.id
                      AND sslpa.owner_id = o.id
                      AND sslc.fee_id = f.id
                      AND f.currency_id = c.id
                      AND c.id = cc.currency_id
                      AND sslc.domain_id = d.id
                      AND sslc.type_id = sslcf.id
                      AND sslc.ip_id = ip.id
                      AND sslc.cat_id = cat.id
                      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                      $is_active_string
                      $oid_string
                      $did_string
                      $sslpid_string
                      $sslpaid_string
                      $ssltid_string
                      $sslipid_string
                      $sslpcid_string
                      $range_string
                      $search_string";

$result_grand_total = mysqli_query($connection, $sql_grand_total) or $error->outputOldSqlError($connection);
while ($row_grand_total = mysqli_fetch_object($result_grand_total)) {
    $grand_total = $row_grand_total->grand_total;
}

$grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

if ($export_data == "1") {

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    $total_rows = number_format(mysqli_num_rows($result));

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('ssl_results', strtotime($time->stamp()));

    $row_contents = array('SSL Certificate Search Results Export');
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Total Cost:',
        $grand_total,
        $_SESSION['s_default_currency']
    );
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(
        'Number of SSL Certs:',
        $total_rows
    );
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        '[Search Filters]'
    );
    $export->writeRow($export_file, $row_contents);

    if ($_SESSION['s_search_for_ssl'] != "") {

        $row_contents = array(
            'Keyword Search:',
            $_SESSION['s_search_for_ssl']
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($did > 0) {

        $sql_filter = "SELECT domain
                       FROM domains
                       WHERE id = '" . $did . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'Associated Domain:',
                $row_filter->domain
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($sslpid > 0) {

        $sql_filter = "SELECT `name`
                       FROM ssl_providers
                       WHERE id = '" . $sslpid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'SSL Provider:',
                $row_filter->name
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($sslpaid > 0) {

        $sql_filter = "SELECT sslp.name AS ssl_provider_name, o.name AS owner_name, sslpa.username
                       FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o
                       WHERE sslpa.ssl_provider_id = sslp.id
                         AND sslpa.owner_id = o.id
                         AND sslpa.id = '" . $sslpaid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'SSL Provider Account:',
                $row_filter->ssl_provider_name . " - " . $row_filter->owner_name . " - " . $row_filter->username
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($ssltid > 0) {

        $sql_filter = "SELECT type
                       FROM ssl_cert_types
                       WHERE id = '" . $ssltid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'SSL Type:',
                $row_filter->type
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($sslipid > 0) {

        $sql_filter = "SELECT `name`, ip
                       FROM ip_addresses
                       WHERE id = '" . $sslipid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'SSL IP Address:',
                $row_filter->name . ' (' . $row_filter->ip . ')'
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($sslpcid > 0) {

        $sql_filter = "SELECT `name`
                       FROM categories
                       WHERE id = '" . $sslpcid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'SSL Category:',
                $row_filter->name
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($oid > 0) {

        $sql_filter = "SELECT `name`
                       FROM owners
                       WHERE id = '" . $oid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'Owner:',
                $row_filter->name
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {

        $row_contents = array(
            'Expiry Date Range:',
            $_SESSION['s_start_date']  . " to " . $_SESSION['s_end_date']
        );
        $export->writeRow($export_file, $row_contents);

    }

    unset($row_contents);
    $count = 0;

    $row_contents[$count++] = "SSL Status:";

    if ($is_active == "ALL") {

        $row_contents[$count++] = 'ALL';

    } elseif ($is_active == "LIVE" || $is_active == "") {

        $row_contents[$count++] = 'LIVE (Active / Pending)';

    } elseif ($is_active == "0") {

        $row_contents[$count++] = 'Expired';

    } elseif ($is_active == "1") {

        $row_contents[$count++] = 'Active';

    } elseif ($is_active == "3") {

        $row_contents[$count++] = 'Pending (Renewal)';

    } elseif ($is_active == "4") {

        $row_contents[$count++] = 'Pending (Other)';

    } elseif ($is_active == "5") {

        $row_contents[$count++] = 'Pending (Registration)';

    }
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    unset($row_contents);
    $count = 0;

    $row_contents[$count++] = 'SSL Cert Status';
    $row_contents[$count++] = 'Expiry Date';
    $row_contents[$count++] = 'Initial Fee';
    $row_contents[$count++] = 'Renewal Fee';
    $row_contents[$count++] = 'Misc Fee';
    $row_contents[$count++] = 'Total Yearly Cost';
    $row_contents[$count++] = 'Host / Label';
    $row_contents[$count++] = 'Domain';
    $row_contents[$count++] = 'SSL Provider';
    $row_contents[$count++] = 'SSL Provider Account';
    $row_contents[$count++] = 'Username';
    $row_contents[$count++] = 'SSL Type';
    $row_contents[$count++] = 'IP Address Name';
    $row_contents[$count++] = 'IP Address';
    $row_contents[$count++] = 'IP Address rDNS';
    $row_contents[$count++] = 'Category';
    $row_contents[$count++] = 'Owner';
    $row_contents[$count++] = 'Notes';
    $row_contents[$count++] = 'Creation Type';
    $row_contents[$count++] = 'Created By';
    $row_contents[$count++] = "Inserted";
    $row_contents[$count++] = "Updated";
    $row_contents[$count++] = "CUSTOM FIELDS";

    $sql_field = "SELECT `name`
                  FROM ssl_cert_fields
                  ORDER BY `name` ASC";
    $result_field = mysqli_query($connection, $sql_field);

    if (mysqli_num_rows($result_field) > 0) {

        while ($row_field = mysqli_fetch_object($result_field)) {

            $row_contents[$count++] = $row_field->name;

        }

    }

    $export->writeRow($export_file, $row_contents);

    while ($row = mysqli_fetch_object($result)) {

        $temp_initial_fee = $row->initial_fee * $row->conversion;
        $temp_renewal_fee = $row->renewal_fee * $row->conversion;
        $temp_misc_fee = $row->misc_fee * $row->conversion;
        $temp_total_cost = $row->total_cost * $row->conversion;

        if ($row->active == "0") {
            $ssl_status = "EXPIRED";
        } elseif ($row->active == "1") {
            $ssl_status = "ACTIVE";
        } elseif ($row->active == "3") {
            $ssl_status = "PENDING (RENEWAL)";
        } elseif ($row->active == "4") {
            $ssl_status = "PENDING (OTHER)";
        } elseif ($row->active == "5") {
            $ssl_status = "PENDING (REGISTRATION)";
        } else {
            $ssl_status = "ERROR -- PROBLEM WITH CODE IN SSL/INDEX.PHP";
        }

        $export_initial_fee = $currency->format($temp_initial_fee,
            $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
            $_SESSION['s_default_currency_symbol_space']);

        $export_renewal_fee = $currency->format($temp_renewal_fee, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        $export_misc_fee = $currency->format($temp_misc_fee,
            $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
            $_SESSION['s_default_currency_symbol_space']);

        $export_total_cost = $currency->format($temp_total_cost, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        unset($row_contents);
        $count = 0;

        $creation_type = $system->getCreationType($connection, $row->creation_type_id);

        if ($row->created_by == '0') {
            $created_by = 'Unknown';
        } else {
            $user = new DomainMOD\User();
            $created_by = $user->getFullName($connection, $row->created_by);
        }

        $row_contents[$count++] = $ssl_status;
        $row_contents[$count++] = $row->expiry_date;
        $row_contents[$count++] = $export_initial_fee;
        $row_contents[$count++] = $export_renewal_fee;
        $row_contents[$count++] = $export_misc_fee;
        $row_contents[$count++] = $export_total_cost;
        $row_contents[$count++] = $row->name;
        $row_contents[$count++] = $row->domain;
        $row_contents[$count++] = $row->ssl_provider_name;
        $row_contents[$count++] = $row->ssl_provider_name . ', ' . $row->owner_name . ' (' . $row->username . ')';
        $row_contents[$count++] = $row->username;
        $row_contents[$count++] = $row->type;
        $row_contents[$count++] = $row->ip_name;
        $row_contents[$count++] = $row->ip;
        $row_contents[$count++] = $row->rdns;
        $row_contents[$count++] = $row->cat_name;
        $row_contents[$count++] = $row->owner_name;
        $row_contents[$count++] = $row->notes;
        $row_contents[$count++] = $creation_type;
        $row_contents[$count++] = $created_by;
        $row_contents[$count++] = $time->toUserTimezone($row->insert_time);
        $row_contents[$count++] = $time->toUserTimezone($row->update_time);
        $row_contents[$count++] = '';

        $sslfd_columns_array = $customField->getCustomFields($connection, 'ssl_cert_fields');

        if ($sslfd_columns_array != "") {

            foreach ($sslfd_columns_array as $column) {

                $row_contents[$count++] = $row->{$column};

            }

        }

        $export->writeRow($export_file, $row_contents);

    }

    $export->closeFile($export_file);

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
    <?php include(DIR_INC . "layout/date-range-picker-head.inc.php"); ?>
    <?php echo $layout->jumpMenu(); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php
// Double check to make sure there are still no SSL certs in the system
if ($_SESSION['s_has_ssl_cert'] == '0') {

    $queryB = new DomainMOD\QueryBuild();
    $sql_asset_check = $queryB->singleAsset('ssl_certs');
    $_SESSION['s_has_ssl_cert'] = $system->checkForRows($connection, $sql_asset_check);

}

$subtext1 = 'Before you can start adding SSL certificates to DomainMOD you need to add at least one SSL provider, an SSL provider account, and a domain.<BR><BR>';

if ($_SESSION['s_has_ssl_provider'] != '1') {
    echo "<BR><strong>0</strong> SSL Providers found. <a href=\"../assets/add/ssl-provider.php\">Click here to add one</a>.<BR><BR>";
    echo $subtext1;
}

if ($_SESSION['s_has_ssl_account'] != '1' && $_SESSION['s_has_ssl_provider'] == '1') {
    echo "<BR><strong>0</strong> SSL Provider Accounts found. <a href=\"../assets/add/ssl-provider-account.php\">Click here to add one</a>.<BR><BR>";
    echo $subtext1;
}

if ($_SESSION['s_has_domain'] != '1' && $_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1') {
    echo "<BR><strong>0</strong> domains found.  <a href=\"../domains/\">Click here to add one</a>.<BR><BR>";
    echo $subtext1;
}

if ($_SESSION['s_has_ssl_cert'] != '1' && $_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_domain'] == '1') {
    echo "<BR><strong>0</strong> SSL Certificates. <a href=\"add.php\">Click here to add one</a>.<BR><BR>";
}

$result = mysqli_query($connection, $sql);
$total_rows = number_format(mysqli_num_rows($result));

if ($_SERVER['REQUEST_METHOD'] == 'POST' || $expand == '1') {
    $box_type = 'expanded';
    $box_icon = 'minus';
} else {
    $box_type = 'collapsed';
    $box_icon = 'plus';
}

if ($_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_ssl_cert'] == '1' && $_SESSION['s_has_domain'] == '1') { ?>

    <div class="box box-default <?php echo $box_type; ?>-box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">Advanced Filtering [<a href="<?php echo $web_root; ?>/ssl/">reset filters</a>]</h3>
            <div class="box-tools">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i
                        class="fa fa-<?php echo $box_icon; ?>"></i>
                </button>
            </div>
        </div>
        <div class="box-body">
            <?php
            echo $form->showFormTop('');

            // DOMAIN
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == "LIVE") {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == "ALL") {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid != "") {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($sslpid != "") {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid != "") {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid != "") {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid != "") {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid != "") {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND sslc.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE '%" . $_SESSION['s_search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['s_search_for_ssl'] . "%')";
            } else {
                $search_string = "";
            }

            $sql_domain = "SELECT d.id, d.domain
                           FROM domains AS d, ssl_certs AS sslc
                           WHERE d.id = sslc.domain_id
                             AND d.active not in ('0', '10')
                             $is_active_string
                             $oid_string
                             $sslpid_string
                             $sslpaid_string
                             $ssltid_string
                             $sslipid_string
                             $sslpcid_string
                             $range_string
                             $search_string
                           GROUP BY d.domain
                           ORDER BY d.domain asc";
            $result_domain = mysqli_query($connection, $sql_domain);

            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1', '', 'Domain - ALL', 'null');
            while ($row_domain = mysqli_fetch_object($result_domain)) {

                echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $row_domain->id . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1&null=', $row_domain->id, $row_domain->domain, $did);

            }
            echo $form->showDropdownBottom('');


            // SSL PROVIDER
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == "LIVE") {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == "ALL") {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid != "") {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did != "") {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpaid != "") {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid != "") {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid != "") {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid != "") {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND sslc.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE '%" . $_SESSION['s_search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['s_search_for_ssl'] . "%')";
            } else {
                $search_string = "";
            }

            $sql_ssl_provider = "SELECT sslp.id, sslp.name
                                 FROM ssl_providers AS sslp, ssl_certs AS sslc, domains AS d
                                 WHERE sslp.id = sslc.ssl_provider_id
                                   AND sslc.domain_id = d.id
                                   $is_active_string
                                   $oid_string
                                   $did_string
                                   $sslpaid_string
                                   $ssltid_string
                                   $sslipid_string
                                   $sslpcid_string
                                   $range_string
                                   $search_string
                                 GROUP BY sslp.name
                                 ORDER BY sslp.name asc";
            $result_ssl_provider = mysqli_query($connection, $sql_ssl_provider);

            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1', '', 'SSL Provider - ALL', 'null');
            while ($row_ssl_provider = mysqli_fetch_object($result_ssl_provider)) {

                echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $row_ssl_provider->id . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1&null=', $row_ssl_provider->id, $row_ssl_provider->name, $sslpid);

            }
            echo $form->showDropdownBottom('');


            // SSL PROVIDER ACCOUNT
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == "LIVE") {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == "ALL") {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid != "") {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did != "") {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid != "") {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($ssltid != "") {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid != "") {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid != "") {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND sslc.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE '%" . $_SESSION['s_search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['s_search_for_ssl'] . "%')";
            } else {
                $search_string = "";
            }

            $sql_account = "SELECT sslpa.id AS sslpa_id, sslpa.username, sslp.name AS sslp_name, o.name AS owner_name
                            FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_certs AS sslc, domains AS d
                            WHERE sslpa.ssl_provider_id = sslp.id
                              AND sslpa.owner_id = o.id
                              AND sslpa.id = sslc.account_id
                              AND sslc.domain_id = d.id
                              $is_active_string
                              $oid_string
                              $did_string
                              $sslpid_string
                              $ssltid_string
                              $sslipid_string
                              $sslpcid_string
                              $range_string
                              $search_string
                            GROUP BY sslp.name, o.name, sslpa.username
                            ORDER BY sslp.name asc, o.name asc, sslpa.username asc";
            $result_account = mysqli_query($connection, $sql_account);

            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1', '', 'SSL Provider Account - ALL', 'null');
            while ($row_account = mysqli_fetch_object($result_account)) {

                echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $row_account->sslpa_id . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1&null=', $row_account->sslpa_id, $row_account->sslp_name . ', ' . $row_account->owner_name . ' (' . $row_account->username . ')', $sslpaid);

            }
            echo $form->showDropdownBottom('');


            // TYPE
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == "LIVE") {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == "ALL") {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid != "") {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did != "") {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid != "") {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid != "") {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($sslipid != "") {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid != "") {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND sslc.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE '%" . $_SESSION['s_search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['s_search_for_ssl'] . "%')";
            } else {
                $search_string = "";
            }

            $sql_type = "SELECT sslc.type_id, sslcf.type
                         FROM ssl_certs AS sslc, domains AS d, ssl_cert_types AS sslcf
                         WHERE sslc.domain_id = d.id
                           AND sslc.type_id = sslcf.id
                           $is_active_string
                           $oid_string
                           $did_string
                           $sslpid_string
                           $sslpaid_string
                           $sslipid_string
                           $sslpcid_string
                           $range_string
                           $search_string
                         GROUP BY sslcf.type
                         ORDER BY sslcf.type asc";
            $result_type = mysqli_query($connection, $sql_type);

            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1', '', 'SSL Type - ALL', 'null');
            while ($row_type = mysqli_fetch_object($result_type)) {

                echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $row_type->type_id . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1&null=', $row_type->type_id, $row_type->type, $ssltid);

            }
            echo $form->showDropdownBottom('');


            // IP ADDRESS
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == "LIVE") {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == "ALL") {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid != "") {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did != "") {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid != "") {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid != "") {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid != "") {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslpcid != "") {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND sslc.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE '%" . $_SESSION['s_search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['s_search_for_ssl'] . "%')";
            } else {
                $search_string = "";
            }

            $sql_ip = "SELECT ip.id AS ip_id, ip.name AS ip_name, ip.ip
                       FROM ssl_certs AS sslc, domains AS d, ip_addresses AS ip
                       WHERE sslc.domain_id = d.id
                         AND sslc.ip_id = ip.id
                         $is_active_string
                         $oid_string
                         $did_string
                         $sslpid_string
                         $sslpaid_string
                         $ssltid_string
                         $sslpcid_string
                         $range_string
                         $search_string
                       GROUP BY ip.name, ip.ip
                       ORDER BY ip.name, ip.ip";
            $result_ip = mysqli_query($connection, $sql_ip);

            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1', '', 'IP Address - ALL', 'null');
            while ($row_ip = mysqli_fetch_object($result_ip)) {

                echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $row_ip->ip_id . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1&null=', $row_ip->ip_id, $row_ip->ip_name . ' (' . $row_ip->ip . ')', $sslipid);

            }
            echo $form->showDropdownBottom('');


            // CATEGORY
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == "LIVE") {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == "ALL") {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid != "") {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did != "") {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid != "") {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid != "") {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid != "") {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid != "") {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND sslc.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE '%" . $_SESSION['s_search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['s_search_for_ssl'] . "%')";
            } else {
                $search_string = "";
            }

            $sql_cat = "SELECT c.id AS cat_id, c.name AS cat_name
                        FROM ssl_certs AS sslc, domains AS d, categories AS c
                        WHERE sslc.domain_id = d.id
                          AND sslc.cat_id = c.id
                          $is_active_string
                          $oid_string
                          $did_string
                          $sslpid_string
                          $sslpaid_string
                          $ssltid_string
                          $sslipid_string
                          $range_string
                          $search_string
                       GROUP BY c.name
                       ORDER BY c.name";
            $result_cat = mysqli_query($connection, $sql_cat);

            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1', '', 'Category - ALL', 'null');
            while ($row_cat = mysqli_fetch_object($result_cat)) {

                echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $row_cat->cat_id . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1&null=', $row_cat->cat_id, $row_cat->cat_name, $sslpcid);

            }
            echo $form->showDropdownBottom('');


            // OWNER
            if ($is_active == "0") {
                $is_active_string = " AND sslc.active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND sslc.active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND sslc.active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND sslc.active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND sslc.active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND sslc.active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND sslc.active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND sslc.active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND sslc.active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND sslc.active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND sslc.active = '10' ";
            } elseif ($is_active == "LIVE") {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == "ALL") {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($did != "") {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid != "") {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid != "") {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid != "") {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid != "") {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid != "") {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND sslc.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE '%" . $_SESSION['s_search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['s_search_for_ssl'] . "%')";
            } else {
                $search_string = "";
            }

            $sql_owner = "SELECT o.id, o.name
                          FROM owners AS o, ssl_certs AS sslc, domains AS d
                          WHERE o.id = sslc.owner_id
                            AND o.id = d.owner_id
                            $is_active_string
                            $did_string
                            $sslpid_string
                            $sslpaid_string
                            $ssltid_string
                            $sslipid_string
                            $sslpcid_string
                            $range_string
                            $search_string
                          GROUP BY o.name
                          ORDER BY o.name asc";
            $result_owner = mysqli_query($connection, $sql_owner);

            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?oid=&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1', '', 'Owner - ALL', 'null');
            while ($row_owner = mysqli_fetch_object($result_owner)) {

                echo $form->showDropdownOptionJump('index.php?oid=' . $row_owner->id . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $is_active . '&from_dropdown=1&expand=1&null=', $row_owner->id, $row_owner->name, $oid);

            }
            echo $form->showDropdownBottom('');


            // STATUS
            if ($is_active == "0") {
                $is_active_string = " AND active = '0' ";
            } elseif ($is_active == "1") {
                $is_active_string = " AND active = '1' ";
            } elseif ($is_active == "2") {
                $is_active_string = " AND active = '2' ";
            } elseif ($is_active == "3") {
                $is_active_string = " AND active = '3' ";
            } elseif ($is_active == "4") {
                $is_active_string = " AND active = '4' ";
            } elseif ($is_active == "5") {
                $is_active_string = " AND active = '5' ";
            } elseif ($is_active == "6") {
                $is_active_string = " AND active = '6' ";
            } elseif ($is_active == "7") {
                $is_active_string = " AND active = '7' ";
            } elseif ($is_active == "8") {
                $is_active_string = " AND active = '8' ";
            } elseif ($is_active == "9") {
                $is_active_string = " AND active = '9' ";
            } elseif ($is_active == "10") {
                $is_active_string = " AND active = '10' ";
            } elseif ($is_active == "LIVE") {
                $is_active_string = " AND active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == "ALL") {
                $is_active_string = " AND active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid != "") {
                $oid_string = " AND owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($did != "") {
                $did_string = " AND domain_id = '$did' ";
            } else {
                $did_string = "";
            }
            if ($sslpid != "") {
                $sslp_string = " AND ssl_provider_id = '$sslpid' ";
            } else {
                $sslp_string = "";
            }
            if ($sslpaid != "") {
                $sslpaid_string = " AND account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid != "") {
                $ssltid_string = " AND type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid != "") {
                $sslipid_string = " AND ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid != "") {
                $sslpcid_string = " AND cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (expiry_date >= '" . $_SESSION['s_start_date'] . "' AND expiry_date <= '" . $_SESSION['s_end_date'] . "')";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE '%" . $_SESSION['s_search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['s_search_for_ssl'] . "%')";
            } else {
                $search_string = "";
            }

            $sql_active = "SELECT active, count(*) AS total_count
                           FROM ssl_certs
                           WHERE id != '0'
                             $oid_string
                             $did_string
                             $sslpid_string
                             $sslpaid_string
                             $ssltid_string
                             $sslipid_string
                             $sslpcid_string
                             $range_string
                             $search_string
                           GROUP BY active
                           ORDER BY active asc";
            $result_active = mysqli_query($connection, $sql_active);

            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=LIVE&from_dropdown=1&expand=1&null=', $is_active, '"Live" SSL Certificates (Active / Pending)', 'LIVE');
            while ($row_active = mysqli_fetch_object($result_active)) {

                if ($row_active->active == "0") {
                    $display_text = "Expired";
                } elseif ($row_active->active == "1") {
                    $display_text = "Active";
                } elseif ($row_active->active == "3") {
                    $display_text = "Pending (Renewal)";
                } elseif ($row_active->active == "4") {
                    $display_text = "Pending (Other)";
                } elseif ($row_active->active == "5") {
                    $display_text = "Pending (Registration)";
                }

                echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=' . $row_active->active . '&from_dropdown=1&expand=1&null=', $is_active, $display_text, $row_active->active);

            }
            echo $form->showDropdownOptionJump('index.php?oid=' . $oid . '&did=' . $did . '&sslpid=' . $sslpid . '&sslpaid=' . $sslpaid . '&ssltid=' . $ssltid . '&sslipid=' . $sslipid . '&sslpcid=' . $sslpcid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&is_active=ALL&from_dropdown=1&expand=1&null=', $is_active, 'ALL', 'ALL');
            echo $form->showDropdownBottom('');
            ?>

            <?php echo $form->showInputText('search_for', 'SSL Keyword Search', '', $_SESSION['s_search_for_ssl'], '100', '', '', '', ''); ?>

            <?php
            if ($new_start_date == "") {
                $new_start_date = $time->toUserTimezone($time->timeBasic(), 'Y-m-d');
            }
            if ($new_end_date == "") {
                $new_end_date = '3000-12-31';
            }
            echo $form->showInputText('daterange', 'Expiring Between', '', $new_start_date . ' - ' . $new_end_date, '23', '', '', '', '');

            echo $form->showInputHidden('oid', $oid);
            echo $form->showInputHidden('did', $did);
            echo $form->showInputHidden('sslpid', $sslpid);
            echo $form->showInputHidden('sslpaid', $sslpaid);
            echo $form->showInputHidden('ssltid', $ssltid);
            echo $form->showInputHidden('sslipid', $sslipid);
            echo $form->showInputHidden('sslpcid', $sslpcid);
            echo $form->showInputHidden('is_active', $is_active);
            echo $form->showSubmitButton('Apply Filters', '', '');
            ?>
            &nbsp;&nbsp;&nbsp;<a
                href="<?php echo $web_root; ?>/ssl/"><?php echo $layout->showButton('button', 'Reset Filters'); ?></a><?php

            echo $form->showFormBottom(''); ?>

        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->
    <BR><?php

}

if (mysqli_num_rows($result) > 0) { ?>

    <a href="add.php"><?php echo $layout->showButton('button', 'Add SSL Cert'); ?></a>&nbsp;&nbsp;&nbsp;
    <a target="_blank" href="<?php echo $web_root; ?>/raw.php"><?php echo $layout->showButton('button', 'Raw List'); ?></a>&nbsp;&nbsp;&nbsp;
    <a href="index.php?<?php echo urlencode($_SERVER['QUERY_STRING']); ?>&export_data=1"><?php echo $layout->showButton('button', 'Export'); ?></a>

    <BR><BR><strong>Total Cost:</strong> <?php echo htmlentities($grand_total, ENT_QUOTES); ?> <?php echo htmlentities($_SESSION['s_default_currency'], ENT_QUOTES); ?><BR>
    <strong>Number of SSL Certs:</strong> <?php echo number_format(mysqli_num_rows($result)); ?><BR><BR><?php

    if ($totalrows != '0') { ?>

        <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th width="20px"></th>
                <th class="all">
                    Name
                </th>
                <?php if ($_SESSION['s_display_ssl_expiry_date'] == "1") { ?>
                <th>
                    Expiry
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_fee'] == "1") { ?>
                <th>
                    Fee
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_domain'] == "1") { ?>
                <th>
                    Domain
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_provider'] == "1") { ?>
                <th>
                    Provider
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_account'] == "1") { ?>
                <th>
                    Account
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_type'] == "1") { ?>
                <th>
                    Type
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_ip'] == "1") { ?>
                <th>
                    IP Address
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_category'] == "1") { ?>
                <th>
                    Category
                </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_owner'] == "1") { ?>
                <th>
                    Owner
                </th>
                <?php } ?>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_object($result)) { ?>
            <tr>
                <td></td>
                <td>
                    <?php if ($row->active == "0") {
                        echo "<a title=\"Expired\"><strong>x</strong></a>&nbsp;";
                    } elseif ($row->active == "3") {
                        echo "<a title=\"Pending (Renewal)\"><strong>PRn</strong></a>&nbsp;";
                    } elseif ($row->active == "4") {
                        echo "<a title=\"Pending (Other)\"><strong>PO</strong></a>&nbsp;";
                    } elseif ($row->active == "5") {
                        echo "<a title=\"Pending (Registration)\"><strong>PRg</strong></a>&nbsp;";
                    }
                    ?><a href="edit.php?sslcid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a>
                </td>
                <?php if ($_SESSION['s_display_ssl_expiry_date'] == "1") { ?>
                <td>
                    <a href="edit.php?sslcid=<?php echo $row->id; ?>"><?php echo $row->expiry_date; ?></a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_fee'] == "1") { ?>
                <td>
                    <a href="../assets/edit/ssl-provider-fee.php?sslpid=<?php echo $row->sslp_id; ?>&fee_id=<?php echo $row->f_id; ?>">
                            <?php
                            $converted_total_cost = $row->total_cost * $row->conversion;
                            $temp_output_amount = $currency->format($converted_total_cost,
                                $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
                                $_SESSION['s_default_currency_symbol_space']);
                            echo htmlentities($temp_output_amount, ENT_QUOTES);
                            ?>
                        </a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_domain'] == "1") { ?>
                <td>
                    <a href="../domains/edit.php?did=<?php echo $row->domain_id; ?>"><?php echo $row->domain; ?></a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_provider'] == "1") { ?>
                <td>
                    <a href="../assets/edit/ssl-provider.php?sslpid=<?php echo $row->sslp_id; ?>"><?php echo $row->ssl_provider_name; ?></a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_account'] == "1") { ?>
                <td>
                    <a href="../assets/edit/ssl-provider.php?sslpid=<?php echo $row->sslp_id; ?>"><?php echo $row->ssl_provider_name; ?></a>, <a href="../assets/edit/account-owner.php?oid=<?php echo $row->o_id; ?>"><?php echo $row->owner_name; ?></a> (<a href="../assets/edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpa_id; ?>"><?php echo substr($row->username, 0, 15); ?><?php if (strlen($row->username) >= 16) echo "..."; ?></a>)
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_type'] == "1") { ?>
                <td>
                    <a href="../assets/edit/ssl-type.php?ssltid=<?php echo $row->type_id; ?>"><?php echo $row->type; ?></a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_ip'] == "1") { ?>
                <td>
                    <a href="../assets/edit/ip-address.php?ipid=<?php echo $row->ip_id; ?>"><?php
                        echo $row->ip_name; ?> (<?php echo $row->ip; ?>)</a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_category'] == "1") { ?>
                <td>
                    <a href="../assets/edit/category.php?pcid=<?php echo $row->cat_id; ?>"><?php echo $row->cat_name; ?></a>
                </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_ssl_owner'] == "1") { ?>
                <td>
                    <a href="../assets/edit/account-owner.php?oid=<?php echo $row->o_id; ?>"><?php echo $row->owner_name; ?></a>
                </td>
                <?php } ?>

            </tr>
            <?php } ?>
            </tbody>
        </table><BR><?php

    }

} else {

    if ($_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_ssl_cert'] == '1' && $_SESSION['s_has_domain'] == '1') { ?>

        Your search returned zero results.<?php

    }

} ?>
<?php include(DIR_INC . "layout/footer.inc.php"); //@formatter:on ?>
<?php include(DIR_INC . "layout/date-range-picker-footer.inc.php"); ?>
</body>
</html>
