<?php
/**
 * /domains.php
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
<?php //@formatter:off
include("_includes/start-session.inc.php");
include("_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$currency = new DomainMOD\Currency();
$customField = new DomainMOD\CustomField();
$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "Domains";
$software_section = "domains";

// Form Variables
$export_data = $_GET['export_data'];
$pcid = $_REQUEST['pcid'];
$oid = $_REQUEST['oid'];
$dnsid = $_REQUEST['dnsid'];
$ipid = $_REQUEST['ipid'];
$whid = $_REQUEST['whid'];
$rid = $_REQUEST['rid'];
$raid = $_REQUEST['raid'];
$start_date = $_REQUEST['start_date'];
$end_date = $_REQUEST['end_date'];
$tld = $_REQUEST['tld'];
$segid = $_REQUEST['segid'];
$is_active = $_REQUEST['is_active'];
$result_limit = $_REQUEST['result_limit'];
$sort_by = $_REQUEST['sort_by'];
$search_for = $_REQUEST['search_for'];
$quick_search = $_REQUEST['quick_search'];
$from_dropdown = $_REQUEST['from_dropdown'];

// Search Navigation Variables
$numBegin = $_REQUEST['numBegin'];
$begin = $_REQUEST['begin'];
$num = $_REQUEST['num'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') $from_dropdown = 0;

if ($export_data != "1") {

    if ($from_dropdown != "1") {

        if ($search_for != "") {

            $_SESSION['s_search_for'] = $search_for;

        } elseif ($numBegin != "") {

            // $_SESSION['s_search_for'] = $_SESSION['s_search_for'];

        } else {

            $_SESSION['s_search_for'] = "";

        }

        if ($quick_search != "") {

            $_SESSION['s_quick_search'] = $quick_search;

        } elseif ($numBegin != "") {

            // $_SESSION['s_quick_search'] = $_SESSION['s_quick_search'];

        } else {

            $_SESSION['s_quick_search'] = "";

        }

        if ($start_date != '' && $start_date != 'YYYY-MM-DD') {

            $_SESSION['s_start_date'] = $start_date;
            $_SESSION['s_end_date'] = $end_date;

        } elseif ($numBegin != "") {

            // $_SESSION['s_start_date'] = $_SESSION['s_start_date'];
            // $_SESSION['s_end_date'] = $_SESSION['s_end_date'];

        } else {

            $_SESSION['s_start_date'] = 'YYYY-MM-DD';
            $_SESSION['s_end_date'] = 'YYYY-MM-DD';

        }

    }

}
if ($_SESSION['s_search_for'] == "Search Term") $_SESSION['s_search_for'] = "";
if ($_SESSION['s_start_date'] == '') $_SESSION['s_start_date'] = 'YYYY-MM-DD';
if ($_SESSION['s_end_date'] == '') $_SESSION['s_end_date'] = 'YYYY-MM-DD';
if ($result_limit == "") $result_limit = $_SESSION['s_number_of_domains'];

if ($is_active == "") $is_active = "LIVE";

if ($tld == "0") $tld = "";

if ($is_active == "0") {
    $is_active_string = " AND d.active = '0' ";
} elseif ($is_active == "1") {
    $is_active_string = " AND d.active = '1' ";
} elseif ($is_active == "2") {
    $is_active_string = " AND d.active = '2' ";
} elseif ($is_active == "3") {
    $is_active_string = " AND d.active = '3' ";
} elseif ($is_active == "4") {
    $is_active_string = " AND d.active = '4' ";
} elseif ($is_active == "5") {
    $is_active_string = " AND d.active = '5' ";
} elseif ($is_active == "6") {
    $is_active_string = " AND d.active = '6' ";
} elseif ($is_active == "7") {
    $is_active_string = " AND d.active = '7' ";
} elseif ($is_active == "8") {
    $is_active_string = " AND d.active = '8' ";
} elseif ($is_active == "9") {
    $is_active_string = " AND d.active = '9' ";
} elseif ($is_active == "10") {
    $is_active_string = " AND d.active = '10' ";
} elseif ($is_active == "LIVE") {
    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
} elseif ($is_active == "ALL") {
    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
} else {
    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
}

if ($segid != "") {

    $seg_sql = "SELECT segment
                FROM segments
                WHERE id = '$segid'";
    $seg_result = mysqli_query($connection, $seg_sql);
    while ($seg_row = mysqli_fetch_object($seg_result)) {
        $temp_segment = $seg_row->segment;
    }
    $segid_string = " AND d.domain IN ($temp_segment)";

} else {

    $segid_string = "";
}

if ($_SESSION['s_quick_search'] != "") {

    $format = new DomainMOD\Format();
    $_SESSION['s_quick_search'] = $format->stripSpacing($_SESSION['s_quick_search']);

    $lines = explode("\r\n", $_SESSION['s_quick_search']);

    $domain = new DomainMOD\Domain();

    list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) = $domain->findInvalidDomains($lines);

    if ($invalid_domains == 1) {

        if ($invalid_count == 1) {

            $_SESSION['s_result_message'] = "There is " . number_format($invalid_count) . " invalid domain on your list<BR><BR>" . $temp_result_message;

        } else {

            $_SESSION['s_result_message'] = "There are " . number_format($invalid_count) . " invalid domains on your list<BR><BR>" . $temp_result_message;

            if (($invalid_count - $invalid_to_display) == 1) {

                $_SESSION['s_result_message'] .= "<BR>Plus " . number_format($invalid_count - $invalid_to_display) . " other<BR>";

            } elseif (($invalid_count - $invalid_to_display) > 1) {

                $_SESSION['s_result_message'] .= "<BR>Plus " . number_format($invalid_count - $invalid_to_display) . " others<BR>";
            }

        }

    } else {

        $lines = explode("\r\n", $_SESSION['s_quick_search']);
        $quick_search_number_of_domains = count($lines);

        $quick_search_new_segment_formatted = "'" . $_SESSION['s_quick_search'] . "'";
        $quick_search_new_segment_formatted = preg_replace("/\r\n/", "','", $quick_search_new_segment_formatted);
        $quick_search_new_segment_formatted = str_replace(" ", "", $quick_search_new_segment_formatted);
        $quick_search_new_segment_formatted = trim($quick_search_new_segment_formatted);
        $_SESSION['s_quick_search'] = $quick_search_new_segment_formatted;

        $segid_string .= ' AND d.domain IN (' . $_SESSION['s_quick_search'] . ')';

    }

}

if ($pcid != "") {
    $pcid_string = " AND d.cat_id = '$pcid' ";
} else {
    $pcid_string = "";
}
if ($oid != "") {
    $oid_string = " AND o.id = '$oid' ";
} else {
    $oid_string = "";
}
if ($dnsid != "") {
    $dnsid_string = " AND dns.id = '$dnsid' ";
} else {
    $dnsid_string = "";
}
if ($ipid != "") {
    $ipid_string = " AND ip.id = '$ipid' ";
} else {
    $ipid_string = "";
}
if ($whid != "") {
    $whid_string = " AND h.id = '$whid' ";
} else {
    $whid_string = "";
}
if ($rid != "") {
    $rid_string = " AND r.id = '$rid' ";
} else {
    $rid_string = "";
}
if ($raid != "") {
    $raid_string = " AND d.account_id = '$raid' ";
} else {
    $raid_string = "";
}
if ($tld != "") {
    $tld_string = " AND d.tld = '$tld' ";
} else {
    $tld_string = "";
}
if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
    $range_string = " AND (d.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND d.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
} else {
    $range_string = "";
}
if ($_SESSION['s_search_for'] != "") {
    $search_string = " AND d.domain LIKE '%" . $_SESSION['s_search_for'] . "%' ";
} else {
    $search_string = "";
}

if ($sort_by == "") $sort_by = "ed_a";

if ($sort_by == "ed_a") {
    $sort_by_string = " ORDER BY d.expiry_date asc, d.domain asc ";
} elseif ($sort_by == "ed_d") {
    $sort_by_string = " ORDER BY d.expiry_date desc, d.domain asc ";
} elseif ($sort_by == "pc_a") {
    $sort_by_string = " ORDER BY cat.name asc ";
} elseif ($sort_by == "pc_d") {
    $sort_by_string = " ORDER BY cat.name desc ";
} elseif ($sort_by == "dn_a") {
    $sort_by_string = " ORDER BY d.domain asc ";
} elseif ($sort_by == "dn_d") {
    $sort_by_string = " ORDER BY d.domain desc ";
} elseif ($sort_by == "df_a") {
    $sort_by_string = " ORDER BY d.total_cost asc ";
} elseif ($sort_by == "df_d") {
    $sort_by_string = " ORDER BY d.total_cost desc ";
} elseif ($sort_by == "dns_a") {
    $sort_by_string = " ORDER BY dns.name asc ";
} elseif ($sort_by == "dns_d") {
    $sort_by_string = " ORDER BY dns.name desc ";
} elseif ($sort_by == "tld_a") {
    $sort_by_string = " ORDER BY d.tld asc ";
} elseif ($sort_by == "tld_d") {
    $sort_by_string = " ORDER BY d.tld desc ";
} elseif ($sort_by == "ip_a") {
    $sort_by_string = " ORDER BY ip.name asc, ip.ip asc";
} elseif ($sort_by == "ip_d") {
    $sort_by_string = " ORDER BY ip.name desc, ip.ip desc";
} elseif ($sort_by == "wh_a") {
    $sort_by_string = " ORDER BY h.name asc";
} elseif ($sort_by == "wh_d") {
    $sort_by_string = " ORDER BY h.name desc";
} elseif ($sort_by == "o_a") {
    $sort_by_string = " ORDER BY o.name asc, d.domain asc ";
} elseif ($sort_by == "o_d") {
    $sort_by_string = " ORDER BY o.name desc, d.domain asc ";
} elseif ($sort_by == "r_a") {
    $sort_by_string = " ORDER BY r.name asc, d.domain asc ";
} elseif ($sort_by == "r_d") {
    $sort_by_string = " ORDER BY r.name desc, d.domain asc ";
} elseif ($sort_by == "ra_a") {
    $sort_by_string = " ORDER BY r.name asc, d.domain asc ";
} elseif ($sort_by == "ra_d") {
    $sort_by_string = " ORDER BY r.name desc, d.domain asc ";
} else {
    $sort_by_string = " ORDER BY d.expiry_date asc, d.domain asc ";
}

$dfd_columns = $customField->getCustomFieldsSql($connection, 'domain_fields', 'dfd');

$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.total_cost, d.function, d.notes, d.privacy, d.active, d.insert_time, d.update_time, ra.id AS ra_id, ra.username, r.id AS r_id, r.name AS registrar_name, o.id AS o_id, o.name AS owner_name, cat.id AS pcid, cat.name AS category_name, cat.stakeholder, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, c.currency, cc.conversion, dns.id as dnsid, dns.name as dns_name, ip.id AS ipid, ip.ip AS ip, ip.name AS ip_name, ip.rdns, h.id AS whid, h.name AS wh_name" . $dfd_columns . "
        FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, categories AS cat, fees AS f, currencies AS c, currency_conversions AS cc, dns AS dns, ip_addresses AS ip, hosting AS h, domain_field_data AS dfd
        WHERE d.account_id = ra.id
          AND ra.registrar_id = r.id
          AND ra.owner_id = o.id
          AND d.cat_id = cat.id
          AND d.fee_id = f.id
          AND d.dns_id = dns.id
          AND d.ip_id = ip.id
          AND d.hosting_id = h.id
          AND f.currency_id = c.id
          AND c.id = cc.currency_id
          AND d.id = dfd.domain_id
          AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
          $is_active_string
          $segid_string
          $pcid_string
          $oid_string
          $dnsid_string
          $ipid_string
          $whid_string
          $rid_string
          $raid_string
          $range_string
          $tld_string
          $search_string
          $quick_search_string
          $sort_by_string";
$_SESSION['s_raw_list_type'] = 'domains';
$_SESSION['s_raw_list_query'] = $sql;

$sql_grand_total = "SELECT SUM(d.total_cost * cc.conversion) AS grand_total
                    FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, categories AS cat, fees AS f, currencies AS c, currency_conversions AS cc, dns AS dns, ip_addresses AS ip, hosting AS h
                    WHERE d.account_id = ra.id
                      AND ra.registrar_id = r.id
                      AND ra.owner_id = o.id
                      AND d.cat_id = cat.id
                      AND d.fee_id = f.id
                      AND d.dns_id = dns.id
                      AND d.ip_id = ip.id
                      AND d.hosting_id = h.id
                      AND f.currency_id = c.id
                      AND c.id = cc.currency_id
                      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                      $is_active_string
                      $segid_string
                      $pcid_string
                      $oid_string
                      $dnsid_string
                      $ipid_string
                      $whid_string
                      $rid_string
                      $raid_string
                      $range_string
                      $tld_string
                      $search_string
                      $quick_search_string
                      $sort_by_string";

$result_grand_total = mysqli_query($connection, $sql_grand_total) or $error->outputOldSqlError($connection);
while ($row_grand_total = mysqli_fetch_object($result_grand_total)) {
    $grand_total = $row_grand_total->grand_total;
}

$grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

if ($segid != "") {

    $result = mysqli_query($connection, $sql);

    $active_domains = "'";
    while ($row = mysqli_fetch_object($result)) {
        $active_domains .= $row->domain . "', '";
    }
    $active_domains .= "'";
    $active_domains = substr($active_domains, 0, -4);

    $sql_filter_update = "UPDATE segment_data
                          SET filtered = '0'
                          WHERE active = '1'
                            AND segment_id = '$segid'";
    $result_filter_update = mysqli_query($connection, $sql_filter_update);

    $sql_filter_update = "UPDATE segment_data
                          SET filtered = '1'
                          WHERE active = '1'
                            AND segment_id = '$segid'
                            AND domain NOT IN ($active_domains)";
    $result_filter_update = mysqli_query($connection, $sql_filter_update);

    $sql_filter_update = "UPDATE segment_data
                          SET filtered = '1'
                          WHERE active = '1'
                            AND segment_id = '$segid'
                            AND domain NOT LIKE '%" . $search_for . "%'";
    $result_filter_update = mysqli_query($connection, $sql_filter_update);

    $sql_filter_update = "UPDATE segment_data
                          SET filtered = '1'
                          WHERE active = '1'
                            AND segment_id = '$segid'
                            AND domain NOT IN (" . $_SESSION['s_quick_search'] . ")";
    $result_filter_update = mysqli_query($connection, $sql_filter_update);

}

if ($export_data == "1") {

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    $total_rows = number_format(mysqli_num_rows($result));

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('domain_results', strtotime($time->time()));

    $row_contents = array('Domain Search Results Export');
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    if ($segid == "") {

        $row_contents = array(
            'Total Cost:',
            $grand_total,
            $_SESSION['s_default_currency']
        );
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            'Number of Domains:',
            $total_rows
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    } else {

        $row_contents = array(
            'Total Cost:',
            $grand_total,
            $_SESSION['s_default_currency']
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    }

    if ($tld != "") {

        $row_contents = array(
            'TLD',
            '.' . $tld
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($segid != "") {

        $sql_segment = "SELECT domain
                        FROM segment_data
                        WHERE segment_id = '" . $segid . "'
                          AND inactive = '1'
                        ORDER BY domain";
        $result_segment = mysqli_query($connection, $sql_segment);
        $totalrows_inactive = mysqli_num_rows($result_segment);

        $sql_segment = "SELECT domain
                        FROM segment_data
                        WHERE segment_id = '" . $segid . "'
                          AND missing = '1'
                        ORDER BY domain";
        $result_segment = mysqli_query($connection, $sql_segment);
        $totalrows_missing = mysqli_num_rows($result_segment);

        $sql_segment = "SELECT domain
                        FROM segment_data
                        WHERE segment_id = '" . $segid . "'
                          AND filtered = '1'
                        ORDER BY domain";
        $result_segment = mysqli_query($connection, $sql_segment);
        $totalrows_filtered = mysqli_num_rows($result_segment);

        if ($segid != "") {

            $sql_segment = "SELECT number_of_domains
                            FROM segments
                            WHERE id = '" . $segid . "'";
            $result_segment = mysqli_query($connection, $sql_segment);
            while ($row_segment = mysqli_fetch_object($result_segment)) {
                $number_of_domains = $row_segment->number_of_domains;
            }

        }

        $row_contents = array('[Segment Results]');
        $export->writeRow($export_file, $row_contents);

        $sql_filter = "SELECT `name`
                       FROM segments
                       WHERE id = '" . $segid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'Segment Filter:',
                $row_filter->name
            );
            $export->writeRow($export_file, $row_contents);

        }

        $row_contents = array(
            'Domains in Segment:',
            number_format($number_of_domains)
        );
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            'Matching Domains:',
            $total_rows
        );
        $export->writeRow($export_file, $row_contents);

        if ($totalrows_inactive > 0) {

            $row_contents = array(
                'Matching But Inactive Domains:',
                number_format($totalrows_inactive)
            );
            $export->writeRow($export_file, $row_contents);

        }

        if ($totalrows_filtered > 0) {

            $row_contents = array(
                'Matching But Filtered Domains:',
                number_format($totalrows_filtered)
            );
            $export->writeRow($export_file, $row_contents);

        }

        if ($totalrows_missing > 0) {

            $row_contents = array(
                'Missing Domains:',
                number_format($totalrows_missing)
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    $row_contents = array('[Search Filters]');
    $export->writeRow($export_file, $row_contents);

    if ($_SESSION['s_search_for'] != "") {

        $row_contents = array(
            'Keyword Search:',
            $_SESSION['s_search_for']
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($_SESSION['s_quick_search'] != "") {

        $formatted_quick_search = str_replace("'", "", $_SESSION['s_quick_search']);
        $formatted_quick_search = str_replace(",", ", ", $formatted_quick_search);

        $row_contents = array(
            'Quick Domain Search:',
            $formatted_quick_search
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($rid > 0) {

        $sql_filter = "SELECT `name`
                       FROM registrars
                       WHERE id = '" . $rid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'Registrar:',
                $row_filter->name
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($raid > 0) {

        $sql_filter = "SELECT r.name AS registrar_name, o.name AS owner_name, ra.username
                       FROM registrar_accounts AS ra, registrars AS r, owners AS o
                       WHERE ra.registrar_id = r.id
                         AND ra.owner_id = o.id
                         AND ra.id = '" . $raid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'Registrar Account:',
                $row_filter->registrar_name . " - " . $row_filter->owner_name . " - " . $row_filter->username
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($dnsid > 0) {

        $sql_filter = "SELECT `name`
                       FROM dns
                       WHERE id = '" . $dnsid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'DNS Profile:',
                $row_filter->name
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($ipid > 0) {

        $sql_filter = "SELECT `name`, ip
                       FROM ip_addresses
                       WHERE id = '" . $ipid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'IP Address:',
                $row_filter->name . ' (' . $row_filter->ip . ')'
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($whid > 0) {

        $sql_filter = "SELECT `name`
                       FROM hosting
                       WHERE id = '" . $whid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'Web Host:',
                $row_filter->name
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($pcid > 0) {

        $sql_filter = "SELECT `name`
                       FROM categories
                       WHERE id = '" . $pcid . "'";
        $result_filter = mysqli_query($connection, $sql_filter);

        while ($row_filter = mysqli_fetch_object($result_filter)) {

            $row_contents = array(
                'Category:',
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

    $row_contents[$count++] = 'Domain Status:';

    if ($is_active == "ALL") {

        $row_contents[$count++] = 'ALL';

    } elseif ($is_active == "LIVE" || $is_active == "") {

        $row_contents[$count++] = 'LIVE (Active / Transfers / Pending)';

    } elseif ($is_active == "0") {

        $row_contents[$count++] = 'Expired';

    } elseif ($is_active == "1") {

        $row_contents[$count++] = 'Active';

    } elseif ($is_active == "2") {

        $row_contents[$count++] = 'In Transfer';

    } elseif ($is_active == "3") {

        $row_contents[$count++] = 'Pending (Renewal)';

    } elseif ($is_active == "4") {

        $row_contents[$count++] = 'Pending (Other)';

    } elseif ($is_active == "5") {

        $row_contents[$count++] = 'Pending (Registration)';

    } elseif ($is_active == "10") {

        $row_contents[$count++] = 'Sold';

    }
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    unset($row_contents);
    $count = 0;

    $row_contents[$count++] = "Domain Status";
    $row_contents[$count++] = "Expiry Date";
    $row_contents[$count++] = "Initial Fee";
    $row_contents[$count++] = "Renewal Fee";
    $row_contents[$count++] = "Transfer Fee";
    $row_contents[$count++] = "Privacy Fee";
    $row_contents[$count++] = "Misc Fee";
    $row_contents[$count++] = "Total Yearly Cost";
    $row_contents[$count++] = "Domain";
    $row_contents[$count++] = "TLD";
    $row_contents[$count++] = "Function";
    $row_contents[$count++] = "WHOIS Status";
    $row_contents[$count++] = "Registrar";
    $row_contents[$count++] = "Registrar Account";
    $row_contents[$count++] = "Username";
    $row_contents[$count++] = "DNS Profile";
    $row_contents[$count++] = "IP Address Name";
    $row_contents[$count++] = "IP Address";
    $row_contents[$count++] = "IP Address rDNS";
    $row_contents[$count++] = "Web Host";
    $row_contents[$count++] = "Category";
    $row_contents[$count++] = "Category Stakeholder";
    $row_contents[$count++] = "Owner";
    $row_contents[$count++] = "Notes";
    $row_contents[$count++] = "Inserted";
    $row_contents[$count++] = "Updated";
    $row_contents[$count++] = "CUSTOM FIELDS";

    $sql_field = "SELECT `name`
                  FROM domain_fields
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
        $temp_transfer_fee = $row->transfer_fee * $row->conversion;
        $temp_privacy_fee = $row->privacy_fee * $row->conversion;
        $temp_misc_fee = $row->misc_fee * $row->conversion;
        $temp_total_cost = $row->total_cost * $row->conversion;

        if ($row->active == "0") {
            $domain_status = "EXPIRED";
        } elseif ($row->active == "1") {
            $domain_status = "ACTIVE";
        } elseif ($row->active == "2") {
            $domain_status = "IN TRANSFER";
        } elseif ($row->active == "3") {
            $domain_status = "PENDING (RENEWAL)";
        } elseif ($row->active == "4") {
            $domain_status = "PENDING (OTHER)";
        } elseif ($row->active == "5") {
            $domain_status = "PENDING (REGISTRATION)";
        } elseif ($row->active == "10") {
            $domain_status = "SOLD";
        } else {
            $domain_status = "ERROR -- PROBLEM WITH CODE IN DOMAINS.PHP";
        }

        if ($row->privacy == "1") {
            $privacy_status = "Private";
        } elseif ($row->privacy == "0") {
            $privacy_status = "Public";
        }

        $export_initial_fee = $currency->format($temp_initial_fee, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        $export_renewal_fee = $currency->format($temp_renewal_fee, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        $export_transfer_fee = $currency->format($temp_transfer_fee, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        $export_privacy_fee = $currency->format($temp_privacy_fee, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        $export_misc_fee = $currency->format($temp_misc_fee, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        $export_total_cost = $currency->format($temp_total_cost, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        unset($row_contents);
        $count = 0;

        $row_contents[$count++] = $domain_status;
        $row_contents[$count++] = $row->expiry_date;
        $row_contents[$count++] = $export_initial_fee;
        $row_contents[$count++] = $export_renewal_fee;
        $row_contents[$count++] = $export_transfer_fee;
        $row_contents[$count++] = $export_privacy_fee;
        $row_contents[$count++] = $export_misc_fee;
        $row_contents[$count++] = $export_total_cost;
        $row_contents[$count++] = $row->domain;
        $row_contents[$count++] = '.' . $row->tld;
        $row_contents[$count++] = $row->function;
        $row_contents[$count++] = $privacy_status;
        $row_contents[$count++] = $row->registrar_name;
        $row_contents[$count++] = $row->registrar_name . ', ' . $row->owner_name . '(' . $row->username . ')';
        $row_contents[$count++] = $row->username;
        $row_contents[$count++] = $row->dns_name;
        $row_contents[$count++] = $row->ip_name;
        $row_contents[$count++] = $row->ip;
        $row_contents[$count++] = $row->rdns;
        $row_contents[$count++] = $row->wh_name;
        $row_contents[$count++] = $row->category_name;
        $row_contents[$count++] = $row->stakeholder;
        $row_contents[$count++] = $row->owner_name;
        $row_contents[$count++] = $row->notes;
        $row_contents[$count++] = $time->toUserTimezone($row->insert_time);
        $row_contents[$count++] = $time->toUserTimezone($row->update_time);
        $row_contents[$count++] = '';

        $dfd_columns_array = $customField->getCustomFields($connection, 'domain_fields');

        if ($dfd_columns_array != "") {

            foreach ($dfd_columns_array as $column) {

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
    <?php
    $layout = new DomainMOD\Layout();
    echo $layout->jumpMenu();
    ?>
</head>
<body onLoad="document.forms[0].elements[14].focus()">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php
if ($_SESSION['s_has_registrar'] != '1') {
    echo "<strong><div class=\"highlight\">0</div></strong> Domain Registrars found. Please <a href=\"assets/add/registrar.php\">click here</a> to add one.<BR><BR>";
    exit;
}

if ($_SESSION['s_has_registrar_account'] != '1' && $_SESSION['s_has_registrar'] == '1') {
    echo "<strong><div class=\"highlight\">0</div></strong> Domain Registrar Accounts found. Please <a href=\"assets/add/registrar-account.php\">click here</a> to add one.<BR><BR>";
    exit;
}

if ($_SESSION['s_has_domain'] != '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {
    echo "<strong><div class=\"highlight\">0</div></strong> Domains found. Please <a href=\"add/domain.php\">click here</a> to add one.<BR><BR>";
    exit;
}
$totalrows = mysqli_num_rows(mysqli_query($connection, $sql));
$parameters = array($totalrows, 15, $result_limit, "&pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by", $_REQUEST[numBegin], $_REQUEST[begin], $_REQUEST[num]);
$navigate = $layout->pageBrowser($parameters);
$sql = $sql . $navigate[0];
$result = mysqli_query($connection, $sql);
$total_rows = number_format(mysqli_num_rows($result));

if ($segid != "") {

    $sql_segment = "SELECT number_of_domains
                    FROM segments
                    WHERE id = '$segid'";
    $result_segment = mysqli_query($connection, $sql_segment);
    while ($row_segment = mysqli_fetch_object($result_segment)) {
        $number_of_domains = $row_segment->number_of_domains;
    }

}
?>
<form name="domain_search_form" method="post">
    <div class="search-block-outer">
        <div class="search-block-inner">
            <div class="search-block-left">
                &nbsp;&nbsp;
                <?php
                // SEGMENT
                $sql_segment = "SELECT id, `name`
                                FROM segments
                                ORDER BY `name` ASC";
                $result_segment = mysqli_query($connection, $sql_segment);

                echo "<select name=\"segid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Segment Filter - OFF</option>";
                while ($row_segment = mysqli_fetch_object($result_segment)) {
                    echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&segid=$row_segment->id&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                    if ($row_segment->id == $segid) echo " selected";
                    echo ">";
                    echo "$row_segment->name</option>";
                }
                echo "</select>";
                ?>
                <BR><BR>

                &nbsp;&nbsp;
                <?php
                // REGISTRAR
                if ($is_active == "0") {
                    $is_active_string = " AND d.active = '0' ";
                } elseif ($is_active == "1") {
                    $is_active_string = " AND d.active = '1' ";
                } elseif ($is_active == "2") {
                    $is_active_string = " AND d.active = '2' ";
                } elseif ($is_active == "3") {
                    $is_active_string = " AND d.active = '3' ";
                } elseif ($is_active == "4") {
                    $is_active_string = " AND d.active = '4' ";
                } elseif ($is_active == "5") {
                    $is_active_string = " AND d.active = '5' ";
                } elseif ($is_active == "6") {
                    $is_active_string = " AND d.active = '6' ";
                } elseif ($is_active == "7") {
                    $is_active_string = " AND d.active = '7' ";
                } elseif ($is_active == "8") {
                    $is_active_string = " AND d.active = '8' ";
                } elseif ($is_active == "9") {
                    $is_active_string = " AND d.active = '9' ";
                } elseif ($is_active == "10") {
                    $is_active_string = " AND d.active = '10' ";
                } elseif ($is_active == "LIVE") {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == "ALL") {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid != "") {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid != "") {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid != "") {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid != "") {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid != "") {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($raid != "") {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = '$tld' ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND d.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND d.domain LIKE '%" . $_SESSION['s_search_for'] . "%'";
                } else {
                    $search_string = "";
                }
                if ($_SESSION['s_quick_search'] != "") {
                    $quick_search_string = " AND d.domain IN (" . $_SESSION['s_quick_search'] . ") ";
                } else {
                    $quick_search_string = "";
                }
                if ($segid != "") {
                    $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $sql_registrar = "SELECT r.id, r.name
                                  FROM registrars AS r, domains AS d
                                  WHERE r.id = d.registrar_id
                                    $is_active_string
                                    $pcid_string
                                    $oid_string
                                    $dnsid_string
                                    $ipid_string
                                    $whid_string
                                    $raid_string
                                    $range_string
                                    $tld_string
                                    $search_string
                                    $quick_search_string
                                    $segment_string
                                  GROUP BY r.name
                                  ORDER BY r.name asc";
                $result_registrar = mysqli_query($connection, $sql_registrar);
                echo "<select name=\"rid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Registrar - ALL</option>";
                while ($row_registrar = mysqli_fetch_object($result_registrar)) {
                    echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$row_registrar->id&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                    if ($row_registrar->id == $rid) echo " selected";
                    echo ">";
                    echo "$row_registrar->name</option>";
                }
                echo "</select>";
                ?>
                <BR><BR>

                &nbsp;&nbsp;
                <?php
                // REGISTRAR ACCOUNT
                if ($is_active == "0") {
                    $is_active_string = " AND d.active = '0' ";
                } elseif ($is_active == "1") {
                    $is_active_string = " AND d.active = '1' ";
                } elseif ($is_active == "2") {
                    $is_active_string = " AND d.active = '2' ";
                } elseif ($is_active == "3") {
                    $is_active_string = " AND d.active = '3' ";
                } elseif ($is_active == "4") {
                    $is_active_string = " AND d.active = '4' ";
                } elseif ($is_active == "5") {
                    $is_active_string = " AND d.active = '5' ";
                } elseif ($is_active == "6") {
                    $is_active_string = " AND d.active = '6' ";
                } elseif ($is_active == "7") {
                    $is_active_string = " AND d.active = '7' ";
                } elseif ($is_active == "8") {
                    $is_active_string = " AND d.active = '8' ";
                } elseif ($is_active == "9") {
                    $is_active_string = " AND d.active = '9' ";
                } elseif ($is_active == "10") {
                    $is_active_string = " AND d.active = '10' ";
                } elseif ($is_active == "LIVE") {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == "ALL") {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid != "") {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid != "") {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid != "") {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid != "") {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid != "") {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid != "") {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = '$tld' ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND d.domain LIKE '%" . $_SESSION['s_search_for'] . "%'";
                } else {
                    $search_string = "";
                }
                if ($_SESSION['s_quick_search'] != "") {
                    $quick_search_string = " AND d.domain IN (" . $_SESSION['s_quick_search'] . ") ";
                } else {
                    $quick_search_string = "";
                }
                if ($segid != "") {
                    $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $sql_account = "SELECT ra.id AS ra_id, ra.username, r.name AS r_name, o.name AS o_name
                                FROM registrar_accounts AS ra, registrars AS r, owners AS o, domains AS d
                                WHERE ra.registrar_id = r.id
                                  AND ra.owner_id = o.id
                                  AND ra.id = d.account_id
                                  $is_active_string
                                  $pcid_string
                                  $oid_string
                                  $dnsid_string
                                  $ipid_string
                                  $whid_string
                                  $rid_string
                                  $tld_string
                                  $search_string
                                  $quick_search_string
                                  $segment_string
                                GROUP BY r.name, o.name, ra.username
                                ORDER BY r.name asc, o.name asc, ra.username asc";
                $result_account = mysqli_query($connection, $sql_account);
                echo "<select name=\"raid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Registrar Account - ALL</option>";
                while ($row_account = mysqli_fetch_object($result_account)) {
                    echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$row_account->ra_id&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                    if ($row_account->ra_id == $raid) echo " selected";
                    echo ">";
                    echo "$row_account->r_name, $row_account->o_name ($row_account->username)</option>";
                }
                echo "</select>";
                ?>
                <BR><BR>

                &nbsp;&nbsp;
                <?php
                // DNS
                if ($is_active == "0") {
                    $is_active_string = " AND d.active = '0' ";
                } elseif ($is_active == "1") {
                    $is_active_string = " AND d.active = '1' ";
                } elseif ($is_active == "2") {
                    $is_active_string = " AND d.active = '2' ";
                } elseif ($is_active == "3") {
                    $is_active_string = " AND d.active = '3' ";
                } elseif ($is_active == "4") {
                    $is_active_string = " AND d.active = '4' ";
                } elseif ($is_active == "5") {
                    $is_active_string = " AND d.active = '5' ";
                } elseif ($is_active == "6") {
                    $is_active_string = " AND d.active = '6' ";
                } elseif ($is_active == "7") {
                    $is_active_string = " AND d.active = '7' ";
                } elseif ($is_active == "8") {
                    $is_active_string = " AND d.active = '8' ";
                } elseif ($is_active == "9") {
                    $is_active_string = " AND d.active = '9' ";
                } elseif ($is_active == "10") {
                    $is_active_string = " AND d.active = '10' ";
                } elseif ($is_active == "LIVE") {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == "ALL") {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid != "") {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid != "") {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($ipid != "") {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid != "") {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid != "") {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid != "") {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = '$tld' ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND d.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND d.domain LIKE '%" . $_SESSION['s_search_for'] . "%'";
                } else {
                    $search_string = "";
                }
                if ($_SESSION['s_quick_search'] != "") {
                    $quick_search_string = " AND d.domain IN (" . $_SESSION['s_quick_search'] . ") ";
                } else {
                    $quick_search_string = "";
                }
                if ($segid != "") {
                    $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $sql_dns = "SELECT dns.id, dns.name
                            FROM dns AS dns, domains AS d
                            WHERE dns.id = d.dns_id
                              $is_active_string
                              $pcid_string
                              $oid_string
                              $ipid_string
                              $whid_string
                              $rid_string
                              $raid_string
                              $range_string
                              $tld_string
                              $search_string
                              $quick_search_string
                              $segment_string
                            GROUP BY dns.name
                            ORDER BY dns.name asc";
                $result_dns = mysqli_query($connection, $sql_dns);
                echo "<select name=\"dnsid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">DNS Profile - ALL</option>";
                while ($row_dns = mysqli_fetch_object($result_dns)) {
                    echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$row_dns->id&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                    if ($row_dns->id == $dnsid) echo " selected";
                    echo ">";
                    echo "$row_dns->name</option>";
                }
                echo "</select>";
                ?>
                <BR><BR>

                &nbsp;&nbsp;
                <?php
                // IP ADDRESS
                if ($is_active == "0") {
                    $is_active_string = " AND d.active = '0' ";
                } elseif ($is_active == "1") {
                    $is_active_string = " AND d.active = '1' ";
                } elseif ($is_active == "2") {
                    $is_active_string = " AND d.active = '2' ";
                } elseif ($is_active == "3") {
                    $is_active_string = " AND d.active = '3' ";
                } elseif ($is_active == "4") {
                    $is_active_string = " AND d.active = '4' ";
                } elseif ($is_active == "5") {
                    $is_active_string = " AND d.active = '5' ";
                } elseif ($is_active == "6") {
                    $is_active_string = " AND d.active = '6' ";
                } elseif ($is_active == "7") {
                    $is_active_string = " AND d.active = '7' ";
                } elseif ($is_active == "8") {
                    $is_active_string = " AND d.active = '8' ";
                } elseif ($is_active == "9") {
                    $is_active_string = " AND d.active = '9' ";
                } elseif ($is_active == "10") {
                    $is_active_string = " AND d.active = '10' ";
                } elseif ($is_active == "LIVE") {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == "ALL") {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid != "") {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid != "") {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid != "") {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($whid != "") {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid != "") {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid != "") {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = '$tld' ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND d.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND d.domain LIKE '%" . $_SESSION['s_search_for'] . "%'";
                } else {
                    $search_string = "";
                }
                if ($_SESSION['s_quick_search'] != "") {
                    $quick_search_string = " AND d.domain IN (" . $_SESSION['s_quick_search'] . ") ";
                } else {
                    $quick_search_string = "";
                }
                if ($segid != "") {
                    $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $sql_ip = "SELECT ip.id, ip.name, ip.ip
                           FROM ip_addresses AS ip, domains AS d
                           WHERE ip.id = d.ip_id
                             $is_active_string
                             $pcid_string
                             $oid_string
                             $dnsid_string
                             $whid_string
                             $rid_string
                             $raid_string
                             $range_string
                             $tld_string
                             $search_string
                             $quick_search_string
                             $segment_string
                           GROUP BY ip.name
                           ORDER BY ip.name asc";
                $result_ip = mysqli_query($connection, $sql_ip);
                echo "<select name=\"ipid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">IP Address - ALL</option>";
                while ($row_ip = mysqli_fetch_object($result_ip)) {
                    echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$row_ip->id&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                    if ($row_ip->id == $ipid) echo " selected";
                    echo ">";
                    echo "$row_ip->name ($row_ip->ip)</option>";
                }
                echo "</select>";
                ?>
                <BR><BR>

                &nbsp;&nbsp;
                <?php
                // WEB HOSTING PROVIDER
                if ($is_active == "0") {
                    $is_active_string = " AND d.active = '0' ";
                } elseif ($is_active == "1") {
                    $is_active_string = " AND d.active = '1' ";
                } elseif ($is_active == "2") {
                    $is_active_string = " AND d.active = '2' ";
                } elseif ($is_active == "3") {
                    $is_active_string = " AND d.active = '3' ";
                } elseif ($is_active == "4") {
                    $is_active_string = " AND d.active = '4' ";
                } elseif ($is_active == "5") {
                    $is_active_string = " AND d.active = '5' ";
                } elseif ($is_active == "6") {
                    $is_active_string = " AND d.active = '6' ";
                } elseif ($is_active == "7") {
                    $is_active_string = " AND d.active = '7' ";
                } elseif ($is_active == "8") {
                    $is_active_string = " AND d.active = '8' ";
                } elseif ($is_active == "9") {
                    $is_active_string = " AND d.active = '9' ";
                } elseif ($is_active == "10") {
                    $is_active_string = " AND d.active = '10' ";
                } elseif ($is_active == "LIVE") {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == "ALL") {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid != "") {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid != "") {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid != "") {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid != "") {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($rid != "") {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid != "") {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = '$tld' ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND d.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND d.domain LIKE '%" . $_SESSION['s_search_for'] . "%'";
                } else {
                    $search_string = "";
                }
                if ($_SESSION['s_quick_search'] != "") {
                    $quick_search_string = " AND d.domain IN (" . $_SESSION['s_quick_search'] . ") ";
                } else {
                    $quick_search_string = "";
                }
                if ($segid != "") {
                    $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $sql_hosting = "SELECT h.id, h.name
                                FROM hosting AS h, domains AS d
                                WHERE h.id = d.hosting_id
                                  $is_active_string
                                  $pcid_string
                                  $oid_string
                                  $dnsid_string
                                  $ipid_string
                                  $rid_string
                                  $raid_string
                                  $range_string
                                  $tld_string
                                  $search_string
                                  $quick_search_string
                                  $segment_string
                                GROUP BY h.name
                                ORDER BY h.name asc";
                $result_hosting = mysqli_query($connection, $sql_hosting);
                echo "<select name=\"whid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Web Hosting Provider - ALL</option>";
                while ($row_hosting = mysqli_fetch_object($result_hosting)) {
                    echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$row_hosting->id&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                    if ($row_hosting->id == $whid) echo " selected";
                    echo ">";
                    echo "$row_hosting->name</option>";
                }
                echo "</select>";
                ?>
                <BR><BR>

                &nbsp;&nbsp;
                <?php
                // CATEGORY
                if ($is_active == "0") {
                    $is_active_string = " AND d.active = '0' ";
                } elseif ($is_active == "1") {
                    $is_active_string = " AND d.active = '1' ";
                } elseif ($is_active == "2") {
                    $is_active_string = " AND d.active = '2' ";
                } elseif ($is_active == "3") {
                    $is_active_string = " AND d.active = '3' ";
                } elseif ($is_active == "4") {
                    $is_active_string = " AND d.active = '4' ";
                } elseif ($is_active == "5") {
                    $is_active_string = " AND d.active = '5' ";
                } elseif ($is_active == "6") {
                    $is_active_string = " AND d.active = '6' ";
                } elseif ($is_active == "7") {
                    $is_active_string = " AND d.active = '7' ";
                } elseif ($is_active == "8") {
                    $is_active_string = " AND d.active = '8' ";
                } elseif ($is_active == "9") {
                    $is_active_string = " AND d.active = '9' ";
                } elseif ($is_active == "10") {
                    $is_active_string = " AND d.active = '10' ";
                } elseif ($is_active == "LIVE") {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == "ALL") {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($oid != "") {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid != "") {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid != "") {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid != "") {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid != "") {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid != "") {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = '$tld' ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND d.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND d.domain LIKE '%" . $_SESSION['s_search_for'] . "%'";
                } else {
                    $search_string = "";
                }
                if ($_SESSION['s_quick_search'] != "") {
                    $quick_search_string = " AND d.domain IN (" . $_SESSION['s_quick_search'] . ") ";
                } else {
                    $quick_search_string = "";
                }
                if ($segid != "") {
                    $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $sql_category = "SELECT c.id, c.name
                                 FROM categories AS c, domains AS d
                                 WHERE c.id = d.cat_id
                                   $is_active_string
                                   $oid_string
                                   $dnsid_string
                                   $ipid_string
                                   $whid_string
                                   $rid_string
                                   $raid_string
                                   $range_string
                                   $tld_string
                                   $search_string
                                   $quick_search_string
                                   $segment_string
                                 GROUP BY c.name
                                 ORDER BY c.name asc";
                $result_category = mysqli_query($connection, $sql_category);
                echo "<select name=\"pcid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
                echo "<option value=\"domains.php?pcid=&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Category - ALL</option>";
                while ($row_category = mysqli_fetch_object($result_category)) {
                    echo "<option value=\"domains.php?pcid=$row_category->id&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                    if ($row_category->id == $pcid) echo " selected";
                    echo ">";
                    echo "$row_category->name</option>";
                }
                echo "</select>";
                ?>
                <BR><BR>

                &nbsp;&nbsp;
                <?php
                // OWNER
                if ($is_active == "0") {
                    $is_active_string = " AND d.active = '0' ";
                } elseif ($is_active == "1") {
                    $is_active_string = " AND d.active = '1' ";
                } elseif ($is_active == "2") {
                    $is_active_string = " AND d.active = '2' ";
                } elseif ($is_active == "3") {
                    $is_active_string = " AND d.active = '3' ";
                } elseif ($is_active == "4") {
                    $is_active_string = " AND d.active = '4' ";
                } elseif ($is_active == "5") {
                    $is_active_string = " AND d.active = '5' ";
                } elseif ($is_active == "6") {
                    $is_active_string = " AND d.active = '6' ";
                } elseif ($is_active == "7") {
                    $is_active_string = " AND d.active = '7' ";
                } elseif ($is_active == "8") {
                    $is_active_string = " AND d.active = '8' ";
                } elseif ($is_active == "9") {
                    $is_active_string = " AND d.active = '9' ";
                } elseif ($is_active == "10") {
                    $is_active_string = " AND d.active = '10' ";
                } elseif ($is_active == "LIVE") {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == "ALL") {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid != "") {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($dnsid != "") {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid != "") {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid != "") {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid != "") {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid != "") {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = '$tld' ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= '" . $_SESSION['s_start_date'] . "' AND d.expiry_date <= '" . $_SESSION['s_end_date'] . "')";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND d.domain LIKE '%" . $_SESSION['s_search_for'] . "%'";
                } else {
                    $search_string = "";
                }
                if ($_SESSION['s_quick_search'] != "") {
                    $quick_search_string = " AND d.domain IN (" . $_SESSION['s_quick_search'] . ") ";
                } else {
                    $quick_search_string = "";
                }
                if ($segid != "") {
                    $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $sql_owner = "SELECT o.id, o.name
                              FROM owners AS o, domains AS d
                              WHERE o.id = d.owner_id
                                $is_active_string
                                $pcid_string
                                $dnsid_string
                                $ipid_string
                                $whid_string
                                $rid_string
                                $raid_string
                                $range_string
                                $tld_string
                                $search_string
                                $quick_search_string
                                $segment_string
                              GROUP BY o.name
                              ORDER BY o.name asc";
                $result_owner = mysqli_query($connection, $sql_owner);
                echo "<select name=\"oid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
                echo "<option value=\"domains.php?pcid=$pcid&oid=&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Owner - ALL</option>";
                while ($row_owner = mysqli_fetch_object($result_owner)) {
                    echo "<option value=\"domains.php?pcid=$pcid&oid=$row_owner->id&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                    if ($row_owner->id == $oid) echo " selected";
                    echo ">";
                    echo "$row_owner->name</option>";
                }
                echo "</select>";
                ?>
                <BR><BR>

                &nbsp;&nbsp;
                <?php
                // TLD
                if ($is_active == "0") {
                    $is_active_string = " WHERE active = '0' ";
                } elseif ($is_active == "1") {
                    $is_active_string = " WHERE active = '1' ";
                } elseif ($is_active == "2") {
                    $is_active_string = " WHERE active = '2' ";
                } elseif ($is_active == "3") {
                    $is_active_string = " WHERE active = '3' ";
                } elseif ($is_active == "4") {
                    $is_active_string = " WHERE active = '4' ";
                } elseif ($is_active == "5") {
                    $is_active_string = " WHERE active = '5' ";
                } elseif ($is_active == "6") {
                    $is_active_string = " WHERE active = '6' ";
                } elseif ($is_active == "7") {
                    $is_active_string = " WHERE active = '7' ";
                } elseif ($is_active == "8") {
                    $is_active_string = " WHERE active = '8' ";
                } elseif ($is_active == "9") {
                    $is_active_string = " WHERE active = '9' ";
                } elseif ($is_active == "10") {
                    $is_active_string = " WHERE active = '10' ";
                } elseif ($is_active == "LIVE") {
                    $is_active_string = " WHERE active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == "ALL") {
                    $is_active_string = " WHERE active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " WHERE active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid != "") {
                    $pcid_string = " AND cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid != "") {
                    $oid_string = " AND owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid != "") {
                    $dnsid_string = " AND dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid != "") {
                    $ipid_string = " AND ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid != "") {
                    $whid_string = " AND hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid != "") {
                    $rid_string = " AND registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid != "") {
                    $raid_string = " AND account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (expiry_date >= '" . $_SESSION['s_start_date'] . "' AND expiry_date <= '" . $_SESSION['s_end_date'] . "')";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND domain LIKE '%" . $_SESSION['s_search_for'] . "%'";
                } else {
                    $search_string = "";
                }
                if ($_SESSION['s_quick_search'] != "") {
                    $quick_search_string = " AND domain IN (" . $_SESSION['s_quick_search'] . ") ";
                } else {
                    $quick_search_string = "";
                }
                if ($segid != "") {
                    $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $sql_tld = "SELECT tld, count(*) AS total_tld_count
                            FROM domains
                            $is_active_string
                              $pcid_string
                              $oid_string
                              $dnsid_string
                              $ipid_string
                              $whid_string
                              $rid_string
                              $raid_string
                              $range_string
                              $search_string
                              $quick_search_string
                              $segment_string
                            GROUP BY tld
                            ORDER BY tld asc";
                $result_tld = mysqli_query($connection, $sql_tld);
                echo "<select name=\"tld\" onChange=\"MM_jumpMenu('parent',this,0)\">";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">TLD - ALL</option>";
                while ($row_tld = mysqli_fetch_object($result_tld)) {
                    echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$row_tld->tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                    if ($row_tld->tld == $tld) echo " selected";
                    echo ">";
                    echo ".$row_tld->tld</option>";
                }
                echo "</select>";
                ?>

                &nbsp;&nbsp;
                <?php
                // STATUS
                if ($pcid != "") {
                    $pcid_string = " AND cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid != "") {
                    $oid_string = " AND owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid != "") {
                    $dnsid_string = " AND dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid != "") {
                    $ipid_string = " AND ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid != "") {
                    $whid_string = " AND hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid != "") {
                    $rid_string = " AND registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid != "") {
                    $raid_string = " AND account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND tld = '$tld' ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (expiry_date >= '" . $_SESSION['s_start_date'] . "' AND expiry_date <= '" . $_SESSION['s_end_date'] . "')";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND domain LIKE '%" . $_SESSION['s_search_for'] . "%'";
                } else {
                    $search_string = "";
                }
                if ($_SESSION['s_quick_search'] != "") {
                    $quick_search_string = " AND domain IN (" . $_SESSION['s_quick_search'] . ") ";
                } else {
                    $quick_search_string = "";
                }
                if ($segid != "") {
                    $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $sql_active = "SELECT active, count(*) AS total_count
                               FROM domains
                               WHERE id != '0'
                                 $pcid_string
                                 $oid_string
                                 $dnsid_string
                                 $ipid_string
                                 $whid_string
                                 $rid_string
                                 $raid_string
                                 $range_string
                                 $tld_string
                                 $search_string
                                 $quick_search_string
                                 $segment_string
                               GROUP BY active
                               ORDER BY active asc";
                $result_active = mysqli_query($connection, $sql_active);
                echo "<select name=\"is_active\" onChange=\"MM_jumpMenu('parent',this,0)\">";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=LIVE&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                if ($is_active == "LIVE") echo " selected";
                echo ">";
                echo "\"Live\" (Active / Transfers / Pending)</option>";
                while ($row_active = mysqli_fetch_object($result_active)) {
                    echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$row_active->active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                    if ($row_active->active == $is_active) echo " selected";
                    echo ">";
                    if ($row_active->active == "0") {
                        echo "Expired";
                    } elseif ($row_active->active == "10") {
                        echo "Sold";
                    } elseif ($row_active->active == "1") {
                        echo "Active";
                    } elseif ($row_active->active == "2") {
                        echo "In Transfer";
                    } elseif ($row_active->active == "3") {
                        echo "Pending (Renewal)";
                    } elseif ($row_active->active == "4") {
                        echo "Pending (Other)";
                    } elseif ($row_active->active == "5") {
                        echo "Pending (Registration)";
                    }
                    echo "</option>";
                }
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=ALL&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\"";
                if ($is_active == "ALL") echo " selected";
                echo ">";
                echo "ALL</option>";
                echo "</select>";
                ?>

                &nbsp;&nbsp;
                <?php
                // NUMBER OF DOMAINS TO DISPLAY
                echo "<select name=\"result_limit\" onChange=\"MM_jumpMenu('parent',this,0)\">";

                if ($_SESSION['s_number_of_domains'] != "10" && $_SESSION['s_number_of_domains'] != "50" && $_SESSION['s_number_of_domains'] != "100" && $_SESSION['s_number_of_domains'] != "500" && $_SESSION['s_number_of_domains'] != "1000" && $_SESSION['s_number_of_domains'] != "1000000") {
                    echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=" . $_SESSION['s_number_of_domains'] . "&sort_by=$sort_by&from_dropdown=1\"";
                    if ($result_limit == $_SESSION['s_number_of_domains']) echo " selected";
                    echo ">";
                    echo "" . $_SESSION['s_number_of_domains'] . "</option>";
                }

                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=10&sort_by=$sort_by&from_dropdown=1\"";
                if ($result_limit == "10") echo " selected";
                echo ">";
                echo "10</option>";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=50&sort_by=$sort_by&from_dropdown=1\"";
                if ($result_limit == "50") echo " selected";
                echo ">";
                echo "50</option>";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=100&sort_by=$sort_by&from_dropdown=1\"";
                if ($result_limit == "100") echo " selected";
                echo ">";
                echo "100</option>";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=500&sort_by=$sort_by&from_dropdown=1\"";
                if ($result_limit == "500") echo " selected";
                echo ">";
                echo "500</option>";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=1000&sort_by=$sort_by&from_dropdown=1\"";
                if ($result_limit == "1000") echo " selected";
                echo ">";
                echo "1,000</option>";
                echo "<option value=\"domains.php?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&start_date=$start_date&end_date=$end_date&tld=$tld&segid=$segid&is_active=$is_active&result_limit=1000000&sort_by=$sort_by&from_dropdown=1\"";
                if ($result_limit == "1000000") echo " selected";
                echo ">";
                echo "ALL</option>";
                echo "</select>";
                ?>
                <BR><BR>&nbsp;&nbsp;
                <strong>Expiry Date:</strong>&nbsp;&nbsp;<input name="start_date" type="text" value="<?php echo $_SESSION['s_start_date']; ?>" size="10" maxlength="10">
                &nbsp;<strong>to</strong>&nbsp;
                <input name="end_date" type="text" value="<?php echo $_SESSION['s_end_date']; ?>" size="10" maxlength="10">
                <BR>
                <input type="hidden" name="sort_by" value="<?php echo $sort_by; ?>">
            </div>
            <div class="search-block-right">
                [<a href="domains.php">RESET SEARCH FILTERS</a>]<BR>
                <BR><BR>
                <strong>Keyword Search:</strong><BR><BR>
                <input name="search_for" type="text" value="<?php echo $_SESSION['s_search_for']; ?>" size="20">&nbsp;&nbsp;
                <input type="submit" name="button" value="Search &raquo;">&nbsp;&nbsp;<BR><BR>
                <?php
                $_SESSION['s_quick_search'] = preg_replace("/', '/", "\r\n", $_SESSION['s_quick_search']);
                $_SESSION['s_quick_search'] = preg_replace("/','/", "\r\n", $_SESSION['s_quick_search']);
                $_SESSION['s_quick_search'] = preg_replace("/'/", "", $_SESSION['s_quick_search']);
                ?>
                <BR><strong>Domain Search (one domain per line):</strong><BR><BR>
                <textarea name="quick_search" cols="30" rows="11"><?php echo $_SESSION['s_quick_search']; ?></textarea>
                <input type="hidden" name="pcid" value="<?php echo $pcid; ?>">
                <input type="hidden" name="oid" value="<?php echo $oid; ?>">
                <input type="hidden" name="dnsid" value="<?php echo $dnsid; ?>">
                <input type="hidden" name="ipid" value="<?php echo $ipid; ?>">
                <input type="hidden" name="whid" value="<?php echo $whid; ?>">
                <input type="hidden" name="rid" value="<?php echo $rid; ?>">
                <input type="hidden" name="raid" value="<?php echo $raid; ?>">
                <input type="hidden" name="tld" value="<?php echo $tld; ?>">
                <input type="hidden" name="segid" value="<?php echo $segid; ?>">
                <input type="hidden" name="is_active" value="<?php echo $is_active; ?>">
                <input type="hidden" name="result_limit" value="<?php echo $result_limit; ?>">
                <input type="hidden" name="begin" value="0">
                <input type="hidden" name="num" value="1">
                <input type="hidden" name="numBegin" value="1">
            </div>
        </div>
    </div>
</form>
<div style="clear: both;"></div>
<BR>
<?php if ($segid != "") {

    $sql_segment = "SELECT domain
                    FROM segment_data
                    WHERE segment_id = '$segid'
                      AND inactive = '1'
                    ORDER BY domain";
    $result_segment = mysqli_query($connection, $sql_segment);
    $totalrows_inactive = mysqli_num_rows($result_segment);

    $sql_segment = "SELECT domain
                    FROM segment_data
                    WHERE segment_id = '$segid'
                      AND missing = '1'
                    ORDER BY domain";
    $result_segment = mysqli_query($connection, $sql_segment);
    $totalrows_missing = mysqli_num_rows($result_segment);

    $sql_segment = "SELECT domain
                    FROM segment_data
                    WHERE segment_id = '$segid'
                      AND filtered = '1'
                    ORDER BY domain";
    $result_segment = mysqli_query($connection, $sql_segment);
    $totalrows_filtered = mysqli_num_rows($result_segment);
    ?>
    <strong>Domains in Segment:</strong> <?php echo number_format($number_of_domains); ?>
    <BR><BR><strong>Matching Domains:</strong> <?php echo $totalrows; ?>
    <?php if ($totalrows_inactive > 0) { ?>
        <BR><BR><strong>Matching But Inactive Domains:</strong> <?php echo number_format($totalrows_inactive); ?> [<a
            class="invisiblelink" target="_blank" href="results.php?type=inactive&segid=<?php echo $segid; ?>">view</a>]
    <?php } ?>
    <?php if ($totalrows_filtered > 0) { ?>
        <BR><BR><strong>Matching But Filtered Domains:</strong> <?php echo number_format($totalrows_filtered); ?> [<a
            class="invisiblelink" target="_blank" href="results.php?type=filtered&segid=<?php echo $segid; ?>">view</a>]
    <?php } ?>
    <?php if ($totalrows_missing > 0) { ?>
        <BR><BR><strong>Missing Domains:</strong> <?php echo number_format($totalrows_missing); ?> [<a
            class="invisiblelink" target="_blank" href="results.php?type=missing&segid=<?php echo $segid; ?>">view</a>]
    <?php } ?>

<?php } ?>
<?php if (mysqli_num_rows($result) > 0) { ?>
    <?php if ($segid != "") { ?>
        <BR><BR><strong>Total Cost:</strong> <?php echo $grand_total; ?> <?php echo $_SESSION['s_default_currency']; ?>
        <BR><BR>
    <?php } else { ?>
        <strong>Total Cost:</strong> <?php echo $grand_total; ?> <?php echo $_SESSION['s_default_currency']; ?><BR><BR>
        <strong>Number of Domains:</strong> <?php echo number_format($totalrows); ?><BR><BR>
    <?php } ?>
    <?php include(DIR_INC . "layout/pagination.menu.inc.php"); ?>
    <?php if ($totalrows != '0') { ?>
        <table class="main_table" cellpadding="0" cellspacing="0">
            <tr class="main_table_row_heading_active">
                <?php if ($_SESSION['s_display_domain_expiry_date'] == "1") { ?>
                    <td class="main_table_cell_heading_active">
                        <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php
                        echo $dnsid; ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid;
                        ?>&raid=<?php echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php
                        echo $end_date; ?>&segid=<?php echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php
                        echo $is_active; ?>&result_limit=<?php echo $result_limit; ?>&sort_by=<?php
                        if ($sort_by == "ed_a") {
                            echo "ed_d";
                        } else {
                            echo "ed_a";
                        }
                        ?>&from_dropdown=1"><div class="main_table_heading">Expiry Date</div></a>
                    </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_fee'] == "1") { ?>
                    <td class="main_table_cell_heading_active">
                        <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php
                        echo $dnsid; ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid;
                        ?>&raid=<?php echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php
                        echo $end_date; ?>&segid=<?php echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php
                        echo $is_active; ?>&result_limit=<?php echo $result_limit;
                        ?>&sort_by=<?php
                        if ($sort_by == "df_a") {
                            echo "df_d";
                        } else {
                            echo "df_a";
                        }
                        ?>&from_dropdown=1"><div class="main_table_heading">Fee</div></a>
                    </td>
                <?php } ?>
                <td class="main_table_cell_heading_active">
                    <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                    ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                    echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&segid=<?php
                    echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php echo $is_active; ?>&result_limit=<?php
                    echo $result_limit; ?>&sort_by=<?php
                    if ($sort_by == "dn_a") {
                        echo "dn_d";
                    } else {
                        echo "dn_a";
                    } ?>&from_dropdown=1"><div class="main_table_heading">Domain Name</div></a>
                </td>
                <?php if ($_SESSION['s_display_domain_tld'] == "1") { ?>
                    <td class="main_table_cell_heading_active">
                        <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                        ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                        echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php
                        echo $end_date; ?>&segid=<?php echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php
                        echo $is_active; ?>&result_limit=<?php echo $result_limit; ?>&sort_by=<?php
                        if ($sort_by == "tld_a") {
                            echo "tld_d";
                        } else {
                            echo "tld_a";
                        } ?>&from_dropdown=1"><div class="main_table_heading">TLD</div></a>
                    </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_registrar'] == "1") { ?>
                    <td class="main_table_cell_heading_active">
                        <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                        ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                        echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date;
                        ?>&segid=<?php echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php echo $is_active;
                        ?>&result_limit=<?php echo $result_limit; ?>&sort_by=<?php
                        if ($sort_by == "r_a") {
                            echo "r_d";
                        } else {
                            echo "r_a";
                        } ?>&from_dropdown=1"><div class="main_table_heading">Registrar</div></a>
                    </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_account'] == "1") { ?>
                    <td class="main_table_cell_heading_active">
                        <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                        ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                        echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date;
                        ?>&segid=<?php echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php echo $is_active;
                        ?>&result_limit=<?php echo $result_limit; ?>&sort_by=<?php
                        if ($sort_by == "ra_a") {
                            echo "ra_d";
                        } else {
                            echo "ra_a";
                        } ?>&from_dropdown=1"><div class="main_table_heading">Registrar Account</div></a>
                    </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_dns'] == "1") { ?>
                    <td class="main_table_cell_heading_active">
                        <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                        ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                        echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date;
                        ?>&segid=<?php echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php echo $is_active;
                        ?>&result_limit=<?php echo $result_limit; ?>&sort_by=<?php
                        if ($sort_by == "dns_a") {
                            echo "dns_d";
                        } else {
                            echo "dns_a";
                        } ?>&from_dropdown=1"><div class="main_table_heading">DNS Profile</div></a>
                    </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_ip'] == "1") { ?>
                    <td class="main_table_cell_heading_active">
                        <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                        ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                        echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date;
                        ?>&segid=<?php echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php echo $is_active;
                        ?>&result_limit=<?php echo $result_limit; ?>&sort_by=<?php
                        if ($sort_by == "ip_a") {
                            echo "ip_d";
                        } else {
                            echo "ip_a";
                        } ?>&from_dropdown=1"><div class="main_table_heading">IP Address</div></a>
                    </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_host'] == "1") { ?>
                    <td class="main_table_cell_heading_active">
                        <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                        ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                        echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date;
                        ?>&segid=<?php echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php echo $is_active;
                        ?>&result_limit=<?php echo $result_limit; ?>&sort_by=<?php
                        if ($sort_by == "wh_a") {
                            echo "wh_d";
                        } else {
                            echo "wh_a";
                        } ?>&from_dropdown=1"><div class="main_table_heading">Web Host</div></a>
                    </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_category'] == "1") { ?>
                    <td class="main_table_cell_heading_active">
                        <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                        ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                        echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date;
                        ?>&segid=<?php echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php echo $is_active;
                        ?>&result_limit=<?php echo $result_limit; ?>&sort_by=<?php
                        if ($sort_by == "pc_a") {
                            echo "pc_d";
                        } else {
                            echo "pc_a";
                        } ?>&from_dropdown=1"><div class="main_table_heading">Category</div></a>
                    </td>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_owner'] == "1") { ?>
                    <td class="main_table_cell_heading_active">
                        <a href="domains.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                        ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                        echo $raid; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date;
                        ?>&segid=<?php echo $segid; ?>&tld=<?php echo $tld; ?>&is_active=<?php echo $is_active;
                        ?>&result_limit=<?php echo $result_limit; ?>&sort_by=<?php
                        if ($sort_by == "o_a") {
                            echo "o_d";
                        } else {
                            echo "o_a";
                        } ?>&from_dropdown=1"><div class="main_table_heading">Owner</div></a>
                    </td>
                <?php } ?>
            </tr>
            <?php while ($row = mysqli_fetch_object($result)) { ?>
                <tr class="main_table_row_active">
                    <?php if ($_SESSION['s_display_domain_expiry_date'] == "1") { ?>
                        <td class="main_table_cell_active">
                            <a class="invisiblelink"
                               href="edit/domain.php?did=<?php echo $row->id; ?>"><?php echo $row->expiry_date; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_fee'] == "1") { ?>
                        <td class="main_table_cell_active">
                            <a class="invisiblelink"
                               href="assets/edit/registrar-fees.php?rid=<?php echo $row->r_id; ?>">
                                <?php
                                $converted_total_cost = $row->total_cost * $row->conversion;
                                $temp_output_amount = $currency->format($converted_total_cost,
                                    $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
                                    $_SESSION['s_default_currency_symbol_space']);
                                echo $temp_output_amount;
                                ?>
                            </a>
                        </td>
                    <?php } ?>
                    <td class="main_table_cell_active">
                        <?php if ($row->active == "0") {
                            echo "<a title=\"Inactive Domain\"><strong><div class=\"highlight\">x</div></strong></a>&nbsp;";
                        } elseif ($row->active == "10") {
                            echo "<a title=\"Sold\"><strong><div class=\"highlight\">S</div></strong></a>&nbsp;";
                            echo "<a title=\"Sold\"></a>";
                        } elseif ($row->active == "2") {
                            echo "<a title=\"In Transfer\"><strong><div class=\"highlight\">T</div></strong></a>&nbsp;";
                        } elseif ($row->active == "3") {
                            echo "<a title=\"Pending (Renewal)\"><strong><div class=\"highlight\">PRn</div></strong></a>&nbsp;";
                        } elseif ($row->active == "4") {
                            echo "<a title=\"Pending (Other)\"><strong><div class=\"highlight\">PO</div></strong></a>&nbsp;";
                        } elseif ($row->active == "5") {
                            echo "<a title=\"Pending (Registration)\"><strong><div class=\"highlight\">PRg</div></strong></a>&nbsp;";
                        }
                        ?><a class="invisiblelink"
                             href="edit/domain.php?did=<?php echo $row->id; ?>"><?php echo $row->domain; ?></a><?php if ($row->privacy == "1") {
                            echo "&nbsp;<a title=\"Private WHOIS Registration\"><strong><div class=\"highlight\">prv</div></strong></a>&nbsp;";
                        } else {
                            echo "&nbsp;";
                        } ?>[<a class="invisiblelink" target="_blank" href="http://<?php echo $row->domain; ?>">v</a>] [<a
                            target="_blank" class="invisiblelink"
                            href="http://who.is/whois/<?php echo $row->domain; ?>">w</a>]
                    </td>
                    <?php if ($_SESSION['s_display_domain_tld'] == "1") { ?>
                        <td class="main_table_cell_active">
                            <a class="invisiblelink"
                               href="edit/domain.php?did=<?php echo $row->id; ?>">.<?php echo $row->tld; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_registrar'] == "1") { ?>
                        <td class="main_table_cell_active">
                            <a class="invisiblelink"
                               href="assets/edit/registrar.php?rid=<?php echo $row->r_id; ?>"><?php echo $row->registrar_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_account'] == "1") { ?>
                        <td class="main_table_cell_active">
                            <a class="invisiblelink"
                               href="assets/edit/registrar.php?rid=<?php echo $row->r_id; ?>"><?php echo $row->registrar_name; ?></a>,
                            <a class="invisiblelink"
                               href="assets/edit/account-owner.php?oid=<?php echo $row->o_id; ?>"><?php echo $row->owner_name; ?></a>
                            (<a class="invisiblelink"
                                href="assets/edit/registrar-account.php?raid=<?php echo $row->ra_id; ?>"><?php echo substr($row->username, 0, 15); ?><?php if (strlen($row->username) >= 16) echo "..."; ?></a>)
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_dns'] == "1") { ?>
                        <td class="main_table_cell_active">
                            <a class="invisiblelink"
                               href="assets/edit/dns.php?dnsid=<?php echo $row->dnsid; ?>"><?php echo $row->dns_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_ip'] == "1") { ?>
                        <td class="main_table_cell_active">
                            <a class="invisiblelink"
                               href="assets/edit/ip-address.php?ipid=<?php echo $row->ipid; ?>"><?php echo $row->ip_name; ?>
                                (<?php echo $row->ip; ?>)</a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_host'] == "1") { ?>
                        <td class="main_table_cell_active">
                            <a class="invisiblelink"
                               href="assets/edit/host.php?whid=<?php echo $row->whid; ?>"><?php echo $row->wh_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_category'] == "1") { ?>
                        <td class="main_table_cell_active">
                            <a class="invisiblelink"
                               href="assets/edit/category.php?pcid=<?php echo $row->pcid; ?>"><?php echo $row->category_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_owner'] == "1") { ?>
                        <td class="main_table_cell_active">
                            <a class="invisiblelink"
                               href="assets/edit/account-owner.php?oid=<?php echo $row->o_id; ?>"><?php echo $row->owner_name; ?></a>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
        </table>
        <BR>
    <?php } ?>
    <?php include(DIR_INC . "layout/pagination.menu.inc.php"); ?>
<?php } else { ?>
    <BR><BR>Your search returned zero results.
<?php } ?>
<?php include(DIR_INC . "layout/footer.inc.php");  //@formatter:on ?>
</body>
</html>
