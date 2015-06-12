<?php
/**
 * /segment-results.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
include("_includes/start-session.inc.php");
include("_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
require_once(DIR_INC . "classes/Autoloader.php");

spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$time = new DomainMOD\Timestamp();

$segid = $_GET['segid'];
$export_data = $_GET['export_data'];
$type = $_GET['type'];

if ($type == "inactive") {
    $page_title = "Segments - Inactive Domains";
} elseif ($type == "filtered") {
    $page_title = "Segments - Filtered Domains";
} elseif ($type == "missing") {
    $page_title = "Segments - Missing Domains";
}

$software_section = "segments";

if ($type == "inactive") {

    $sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.privacy, d.active, d.insert_time, d.update_time, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
			FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, fees AS f, currencies AS c, currency_conversions AS cc, categories AS cat, dns, ip_addresses AS ip, hosting AS h
			WHERE d.account_id = ra.id
			  AND ra.registrar_id = r.id
			  AND ra.owner_id = o.id
			  AND d.registrar_id = f.registrar_id
			  AND d.tld = f.tld
			  AND f.currency_id = c.id
			  AND c.id = cc.currency_id
			  AND d.cat_id = cat.id
			  AND d.dns_id = dns.id
			  AND d.ip_id = ip.id
			  AND d.hosting_id = h.id
			  AND cc.user_id = '" . $_SESSION['user_id'] . "'
			  AND d.domain in (SELECT domain FROM segment_data WHERE segment_id = '$segid' AND inactive = '1' ORDER BY domain)
			ORDER BY d.domain asc";

} elseif ($type == "filtered") {

    $sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.privacy, d.active, d.insert_time, d.update_time, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
			FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, fees AS f, currencies AS c, currency_conversions AS cc, categories AS cat, dns, ip_addresses AS ip, hosting AS h
			WHERE d.account_id = ra.id
			  AND ra.registrar_id = r.id
			  AND ra.owner_id = o.id
			  AND d.registrar_id = f.registrar_id
			  AND d.tld = f.tld
			  AND f.currency_id = c.id
			  AND c.id = cc.currency_id
			  AND d.cat_id = cat.id
			  AND d.dns_id = dns.id
			  AND d.ip_id = ip.id
			  AND d.hosting_id = h.id
			  AND cc.user_id = '" . $_SESSION['user_id'] . "'
			  AND d.domain in (SELECT domain FROM segment_data WHERE segment_id = '$segid' AND filtered = '1' ORDER BY domain)
			ORDER BY d.domain asc";

} elseif ($type == "missing") {

    $sql = "SELECT domain
			FROM segment_data
			WHERE segment_id = '$segid'
			  AND missing = '1'
			ORDER BY domain";

}
$result = mysqli_query($connection, $sql);

if ($export_data == "1") {

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if ($type == "inactive") {

        $base_filename = "segment_results_inactive";

    } elseif ($type == "filtered") {

        $base_filename = "segment_results_filtered";

    } elseif ($type == "missing") {

        $base_filename = "segment_results_missing";

    }

    $export = new DomainMOD\Export();
    $export_file = $export->openFile("$base_filename", strtotime($time->time()));

    if ($type == "inactive" || $type == "filtered") {

        if ($type == "inactive") {

            $row_contents = array('INACTIVE DOMAINS');

        } elseif ($type == "filtered") {

            $row_contents = array('FILTERED DOMAINS');

        }

        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            'Domain Status',
            'Expiry Date',
            'Initial Fee',
            'Renewal Fee',
            'Domain',
            'TLD',
            'WHOIS Status',
            'Registrar',
            'Username',
            'DNS Profile',
            'IP Address Name',
            'IP Address',
            'IP Address rDNS',
            'Web Host',
            'Category',
            'Category Stakeholder',
            'Owner',
            'Function',
            'Notes',
            'Inserted',
            'Updated'
        );
        $export->writeRow($export_file, $row_contents);

    } elseif ($type == "missing") {

        $row_contents = array('MISSING DOMAINS');
        $export->writeRow($export_file, $row_contents);

    }

    if ($type == "inactive" || $type == "filtered") {

        while ($row = mysqli_fetch_object($result)) {

            $temp_initial_fee = $row->initial_fee * $row->conversion;
            $total_initial_fee_export = $total_initial_fee_export + $temp_initial_fee;

            $temp_renewal_fee = $row->renewal_fee * $row->conversion;
            $total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;

            if ($row->active == "0") { $domain_status = "EXPIRED"; }
            elseif ($row->active == "1") { $domain_status = "ACTIVE"; }
            elseif ($row->active == "2") { $domain_status = "IN TRANSFER"; }
            elseif ($row->active == "3") { $domain_status = "PENDING (RENEWAL)"; }
            elseif ($row->active == "4") { $domain_status = "PENDING (OTHER)"; }
            elseif ($row->active == "5") { $domain_status = "PENDING (REGISTRATION)"; }
            elseif ($row->active == "10") { $domain_status = "SOLD"; }
            else { $domain_status = "ERROR -- PROBLEM WITH CODE IN SEGMENT-RESULTS.PHP"; }

            if ($row->privacy == "1") {

                $privacy_status = "Private";

            } elseif ($row->privacy == "0") {

                $privacy_status = "Public";

            }

            $temp_input_amount = $temp_initial_fee;
            $temp_input_conversion = "";
            $temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
            $temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
            $temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
            include(DIR_INC . "system/convert-and-format-currency.inc.php");
            $export_initial_fee = $temp_output_amount;

            $temp_input_amount = $temp_renewal_fee;
            $temp_input_conversion = "";
            $temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
            $temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
            $temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
            include(DIR_INC . "system/convert-and-format-currency.inc.php");
            $export_renewal_fee = $temp_output_amount;

            $row_contents = array(
                $domain_status,
                $row->expiry_date,
                $export_initial_fee,
                $export_renewal_fee,
                $row->domain,
                '.' . $row->tld,
                $privacy_status,
                $row->registrar_name,
                $row->username,
                $row->dns_profile,
                $row->name,
                $row->ip,
                $row->rdns,
                $row->wh_name,
                $row->category_name,
                $row->category_stakeholder,
                $row->owner_name,
                $row->function,
                $row->notes,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

        }

    } elseif ($type == "missing") {

        while ($row = mysqli_fetch_object($result)) {

            $row_contents = array($row->domain);
            $export->writeRow($export_file, $row_contents);

        }

    }

    $export->closeFile($export_file);

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags-bare.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header-bare.inc.php"); ?>
<?php
$sql_name = "SELECT name
			 FROM segments
			 WHERE id = '$segid'";
$result_name = mysqli_query($connection, $sql_name);
while ($row_name = mysqli_fetch_object($result_name)) { $segment_name = $row_name->name; }
?>

<?php
if ($type == "inactive") {
    echo "The below domains are in the segment <strong><font class=\"highlight\">" . $segment_name . "</font></strong>, and they are stored in your  " . $software_title . " database, but they are currently marked as inactive.<BR><BR>";
} elseif ($type == "filtered") {
    echo "The below domains are in the segment <strong><font class=\"highlight\">" . $segment_name . "</font></strong>, and they are stored in your  " . $software_title . " database, but they were filtered out based on your search criteria.<BR><BR>";
} elseif ($type == "missing") {
    echo "The below domains are in the segment <strong><font class=\"highlight\">" . $segment_name . "</font></strong>, but they are not in your " . $software_title . " database.<BR><BR>";
}
?>
<?php
if ($type == "inactive") {
    echo "[<a href=\"segment-results.php?type=inactive&segid=" . $segid . "&export_data=1\">EXPORT RESULTS</a>]<BR><BR>";
} elseif ($type == "filtered") {
    echo "[<a href=\"segment-results.php?type=filtered&segid=" . $segid . "&export_data=1\">EXPORT RESULTS</a>]<BR><BR>";
} elseif ($type == "missing") {
    echo "[<a href=\"segment-results.php?type=missing&segid=" . $segid . "&export_data=1\">EXPORT RESULTS</a>]<BR><BR>";
}
?>
<?php
while ($row = mysqli_fetch_object($result)) {
    echo $row->domain . "<BR>";
}
?>
<?php include(DIR_INC . "layout/footer-bare.inc.php"); ?>
</body>
</html>
