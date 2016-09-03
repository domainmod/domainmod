<?php
/**
 * /domains/index.php
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
include(DIR_INC . "settings/domains-main.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);

$export_data = $_GET['export_data'];
$pcid = $_REQUEST['pcid'];
$oid = $_REQUEST['oid'];
$dnsid = $_REQUEST['dnsid'];
$ipid = $_REQUEST['ipid'];
$whid = $_REQUEST['whid'];
$rid = $_REQUEST['rid'];
$raid = $_REQUEST['raid'];
$tld = $_REQUEST['tld'];
$segid = $_REQUEST['segid'];
$is_active = $_REQUEST['is_active'];
$search_for = $_REQUEST['search_for'];
$from_dropdown = $_REQUEST['from_dropdown'];
$expand = $_REQUEST['expand'];
$daterange = $_REQUEST['daterange'];

list($new_start_date, $new_end_date) = $date->splitAndCheckRange($daterange);

if ($_SESSION['s_system_large_mode'] == '1') {

    $result_limit = $_REQUEST['result_limit'];
    $sort_by = $_REQUEST['sort_by'];
    $numBegin = $_REQUEST['numBegin'];
    $begin = $_REQUEST['begin'];
    $num = $_REQUEST['num'];

}

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

            $_SESSION['s_search_for'] = $search_for;

        } elseif ($numBegin != "" && $_SESSION['s_system_large_mode'] == '1') {

            // $_SESSION['s_search_for'] = $_SESSION['s_search_for'];

        } else {

            $_SESSION['s_search_for'] = "";

        }

        if ($new_start_date != '' && $new_start_date != 'YYYY-MM-DD') {

            $_SESSION['s_start_date'] = $new_start_date;
            $_SESSION['s_end_date'] = $new_end_date;

        } elseif ($numBegin != "" && $_SESSION['s_system_large_mode'] == '1') {

            // $_SESSION['s_start_date'] = $_SESSION['s_start_date'];
            // $_SESSION['s_end_date'] = $_SESSION['s_end_date'];

        } else {

            $_SESSION['s_start_date'] = 'YYYY-MM-DD';
            $_SESSION['s_end_date'] = 'YYYY-MM-DD';

        }

    }

}

if ($_SESSION['s_start_date'] == '') $_SESSION['s_start_date'] = 'YYYY-MM-DD';
if ($_SESSION['s_end_date'] == '') $_SESSION['s_end_date'] = 'YYYY-MM-DD';

if ($_SESSION['s_system_large_mode'] == '1') {

    if ($result_limit == "") $result_limit = $_SESSION['s_number_of_domains'];

}

if ($is_active == "") $is_active = "LIVE";

if ($tld == "0") $tld = "";

if ($is_active == "0") { $is_active_string = " AND d.active = '0' ";
} elseif ($is_active == "1") { $is_active_string = " AND d.active = '1' ";
} elseif ($is_active == "2") { $is_active_string = " AND d.active = '2' ";
} elseif ($is_active == "3") { $is_active_string = " AND d.active = '3' ";
} elseif ($is_active == "4") { $is_active_string = " AND d.active = '4' ";
} elseif ($is_active == "5") { $is_active_string = " AND d.active = '5' ";
} elseif ($is_active == "6") { $is_active_string = " AND d.active = '6' ";
} elseif ($is_active == "7") { $is_active_string = " AND d.active = '7' ";
} elseif ($is_active == "8") { $is_active_string = " AND d.active = '8' ";
} elseif ($is_active == "9") { $is_active_string = " AND d.active = '9' ";
} elseif ($is_active == "10") { $is_active_string = " AND d.active = '10' ";
} elseif ($is_active == "LIVE") { $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
} elseif ($is_active == "ALL") { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
} else { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
}

if ($segid != "") {

    $seg_sql = "SELECT segment
                FROM segments
                WHERE id = '" . $segid . "'";
    $seg_result = mysqli_query($connection, $seg_sql);
    while ($seg_row = mysqli_fetch_object($seg_result)) {
        $temp_segment = $seg_row->segment;
    }
    $segid_string = " AND d.domain IN ($temp_segment)";

} else {

    $segid_string = "";
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

if ($_SESSION['s_system_large_mode'] == '1') {

    if ($sort_by == "") $sort_by = "ed_a";

    if ($sort_by == "ed_a") { $sort_by_string = " ORDER BY d.expiry_date asc, d.domain asc ";
    } elseif ($sort_by == "ed_d") { $sort_by_string = " ORDER BY d.expiry_date desc, d.domain asc ";
    } elseif ($sort_by == "pc_a") { $sort_by_string = " ORDER BY cat.name asc ";
    } elseif ($sort_by == "pc_d") { $sort_by_string = " ORDER BY cat.name desc ";
    } elseif ($sort_by == "dn_a") { $sort_by_string = " ORDER BY d.domain asc ";
    } elseif ($sort_by == "dn_d") { $sort_by_string = " ORDER BY d.domain desc ";
    } elseif ($sort_by == "df_a") { $sort_by_string = " ORDER BY d.total_cost asc ";
    } elseif ($sort_by == "df_d") { $sort_by_string = " ORDER BY d.total_cost desc ";
    } elseif ($sort_by == "dns_a") { $sort_by_string = " ORDER BY dns.name asc ";
    } elseif ($sort_by == "dns_d") { $sort_by_string = " ORDER BY dns.name desc ";
    } elseif ($sort_by == "tld_a") { $sort_by_string = " ORDER BY d.tld asc ";
    } elseif ($sort_by == "tld_d") { $sort_by_string = " ORDER BY d.tld desc ";
    } elseif ($sort_by == "ip_a") { $sort_by_string = " ORDER BY ip.name asc, ip.ip asc";
    } elseif ($sort_by == "ip_d") { $sort_by_string = " ORDER BY ip.name desc, ip.ip desc";
    } elseif ($sort_by == "wh_a") { $sort_by_string = " ORDER BY h.name asc";
    } elseif ($sort_by == "wh_d") { $sort_by_string = " ORDER BY h.name desc";
    } elseif ($sort_by == "o_a") { $sort_by_string = " ORDER BY o.name asc, d.domain asc ";
    } elseif ($sort_by == "o_d") { $sort_by_string = " ORDER BY o.name desc, d.domain asc ";
    } elseif ($sort_by == "r_a") { $sort_by_string = " ORDER BY r.name asc, d.domain asc ";
    } elseif ($sort_by == "r_d") { $sort_by_string = " ORDER BY r.name desc, d.domain asc ";
    } elseif ($sort_by == "ra_a") { $sort_by_string = " ORDER BY r.name asc, d.domain asc ";
    } elseif ($sort_by == "ra_d") { $sort_by_string = " ORDER BY r.name desc, d.domain asc ";
    } else { $sort_by_string = " ORDER BY d.expiry_date asc, d.domain asc "; }

} else {

    $sort_by_string = '';

}

$dfd_columns = $customField->getCustomFieldsSql($connection, 'domain_fields', 'dfd');

$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.total_cost, d.function, d.notes, d.autorenew, d.privacy, d.creation_type_id, d.created_by, d.active, d.insert_time, d.update_time, ra.id AS ra_id, ra.username, r.id AS r_id, r.name AS registrar_name, o.id AS o_id, o.name AS owner_name, cat.id AS pcid, cat.name AS category_name, cat.stakeholder, f.id AS f_id, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, c.currency, cc.conversion, dns.id as dnsid, dns.name as dns_name, ip.id AS ipid, ip.ip AS ip, ip.name AS ip_name, ip.rdns, h.id AS whid, h.name AS wh_name" . $dfd_columns . "
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
                      $search_string";

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

}

if ($export_data == "1") {

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    $total_rows = number_format(mysqli_num_rows($result));

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('domain_results', strtotime($time->stamp()));

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

        $row_contents[$count++] = 'Pending (Transfer)';

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
    $row_contents[$count++] = "Renewal Status";
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
    $row_contents[$count++] = "Creation Type";
    $row_contents[$count++] = "Created By";
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
            $domain_status = "PENDING (TRANSFER)";
        } elseif ($row->active == "3") {
            $domain_status = "PENDING (RENEWAL)";
        } elseif ($row->active == "4") {
            $domain_status = "PENDING (OTHER)";
        } elseif ($row->active == "5") {
            $domain_status = "PENDING (REGISTRATION)";
        } elseif ($row->active == "10") {
            $domain_status = "SOLD";
        }

        if ($row->autorenew == "1") {
            $autorenew_status = "Auto Renewal";
        } elseif ($row->autorenew == "0") {
            $autorenew_status = "Manual Renewal";
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
        
        $creation_type = $system->getCreationType($connection, $row->creation_type_id);

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
        $row_contents[$count++] = $autorenew_status;
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
        $row_contents[$count++] = $creation_type;
        if ($row->created_by == '0') {
            $row_contents[$count++] = 'Unknown';
        } else {
            $user = new DomainMOD\User();
            $row_contents[$count++] = $user->getFullName($connection, $row->created_by);
        }
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
    <?php include(DIR_INC . "layout/date-range-picker-head.inc.php"); ?>
    <?php echo $layout->jumpMenu(); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php
$sql_supported = "SELECT `name`
                  FROM api_registrars
                  ORDER BY name ASC";
$result_supported = mysqli_query($connection, $sql_supported);
$supported_registrars = '';
while ($row_supported = mysqli_fetch_object($result_supported)) {

    $supported_registrars .= ', ' . $row_supported->name;

}
$supported_registrars = substr($supported_registrars, 2);


// Double check to make sure there are still no domains in the system
if ($_SESSION['s_has_domain'] == '0') {
    
    $queryB = new DomainMOD\QueryBuild();
    $sql_asset_check = $queryB->singleAsset('domains');
    $_SESSION['s_has_domain'] = $system->checkForRows($connection, $sql_asset_check);

}

if ($_SESSION['s_has_domain'] != '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {

    $go_text1 = ' [<a href="' . $web_root . '/queue/info.php">go</a>]';
    $go_text2 = ' [<a href="' . $web_root . '/bulk/">go</a>]';
    $go_text3 = ' [<a href="' . $web_root . '/domains/add.php">go</a>]';

} else {

    $go_text1 = '';
    $go_text2 = '';
    $go_text3 = '';

}

$subtext1 = 'Before you can start adding domains to DomainMOD you need to add at least one registrar and a registrar account. Once you\'re created these you can use any of the below options to add your domains.<BR><BR>';

$subtext2 .= '
<h4>Domain Queue' . $go_text1 . '</h4>
The easiest option for adding domains is to use the Domain Queue, which allows you to supply a list of domains and let ' . $software_title . ' take care of the rest. This option uses your domain registrar\'s API to retrieve information required to add domains, so unfortunately it only works if your registrar has an API and support for it has been built into DomainMOD. Before using this option make sure your API credentials have been saved with your registrar account.<BR>
<BR>
Currently Supported Registrars: ' . $supported_registrars . '<BR>
<BR>
<h4>Bulk Updater' . $go_text2 . '</h4>
The Bulk Updater also allows you to supply a list of domains to be added, however you need to manually choose the options for the domains you\'re adding, and all of the domains will have the same settings. The Bulk Updater generally works best if you\'re adding a list of newly registered domains, since they will have the same expiry date and will generally have the same settings.<BR>
<BR>
<h4>Manually' . $go_text3 . '</h4>
Domains can also be added one-by-one, which allows you to choose custom settings for each domain.
';

if ($_SESSION['s_has_registrar'] != '1') {
    echo "<BR><strong>0</strong> Registrars found. <a href=\"../assets/add/registrar.php\">Click here to add one</a>.<BR><BR>";
    echo $subtext1 . $subtext2 . '<BR><BR>';
}

if ($_SESSION['s_has_registrar_account'] != '1' && $_SESSION['s_has_registrar'] == '1') {
    echo "<BR><strong>0</strong> Registrar Accounts found. <a href=\"../assets/add/registrar-account.php\">Click here to add one</a>.<BR><BR>";
    echo $subtext1 . $subtext2 . '<BR><BR>';
}

if ($_SESSION['s_has_domain'] != '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {
    echo "<BR><strong>0</strong> Domains found. Please choose one of the below options to start adding domains.<BR><BR>";
    echo $subtext2 . '<BR><BR>';
}

if ($_SESSION['s_system_large_mode'] == '1') {

    $totalrows = mysqli_num_rows(mysqli_query($connection, $sql));
    $parameters = array($totalrows, 15, $result_limit, "&pcid=" . $pcid . "&oid=" . $oid . "&dnsid=" . $dnsid . "&ipid=" . $ipid . "&whid=" . $whid . "&rid=" . $rid . "&raid=" . $raid . "&daterange=" . $daterange . "&tld=" . $tld . "&segid=" . $segid . "&is_active=" . $is_active . "&result_limit=" . $result_limit . "&sort_by=" . $sort_by, $_REQUEST[numBegin], $_REQUEST[begin], $_REQUEST[num]);
    $navigate = $layout->pageBrowser($parameters);
    $sql = $sql . $navigate[0];

}

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

if ($_SERVER['REQUEST_METHOD'] == 'POST' || $expand == '1') {
    $box_type = 'expanded';
    $box_icon = 'minus';
} else {
    $box_type = 'collapsed';
    $box_icon = 'plus';
}

if ($_SESSION['s_has_domain'] == '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {

    if ($_SESSION['s_system_large_mode'] != '1') {

        $result_limit = '';
        $sort_by = '';

    } ?>

    <div class="box box-default <?php echo $box_type; ?>-box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">Advanced Filtering [<a href="<?php echo $web_root; ?>/domains/">reset filters</a>]
            </h3>
            <div class="box-tools">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i
                        class="fa fa-<?php echo $box_icon; ?>"></i>
                </button>
            </div>
        </div>
        <div class="box-body">
            <?php
            echo $form->showFormTop('');

            // SEGMENT
            $sql_segment = "SELECT id, `name`
                            FROM segments
                            ORDER BY `name` ASC";
            $result_segment = mysqli_query($connection, $sql_segment);

            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1', '', 'Segment Filter - OFF', 'null');
            while ($row_segment = mysqli_fetch_object($result_segment)) {

                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&segid=' . $row_segment->id . '&tld=' . $tld . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $row_segment->id, $row_segment->name, $segid);

            }
            echo $form->showDropdownBottom('');


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
                                $segment_string
                              GROUP BY r.name
                              ORDER BY r.name asc";
            $result_registrar = mysqli_query($connection, $sql_registrar);

            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1', '', 'Registrar - ALL', 'null');
            while ($row_registrar = mysqli_fetch_object($result_registrar)) {

                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $row_registrar->id . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $row_registrar->id, $row_registrar->name, $rid);

            }
            echo $form->showDropdownBottom('');


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
                              $range_string
                              $tld_string
                              $search_string
                              $segment_string
                            GROUP BY r.name, o.name, ra.username
                            ORDER BY r.name asc, o.name asc, ra.username asc";
            $result_account = mysqli_query($connection, $sql_account);
            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1', '', 'Registrar Account - ALL', 'null');
            while ($row_account = mysqli_fetch_object($result_account)) {

                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $row_account->ra_id . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $row_account->ra_id, $row_account->r_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $raid);

            }
            echo $form->showDropdownBottom('');


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
                          $segment_string
                        GROUP BY dns.name
                        ORDER BY dns.name asc";
            $result_dns = mysqli_query($connection, $sql_dns);
            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1', '', 'DNS Profile - ALL', 'null');
            while ($row_dns = mysqli_fetch_object($result_dns)) {

                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $row_dns->id . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $row_dns->id, $row_dns->name, $dnsid);

            }
            echo $form->showDropdownBottom('');


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
                         $segment_string
                       GROUP BY ip.name
                       ORDER BY ip.name asc";
            $result_ip = mysqli_query($connection, $sql_ip);
            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1', '', 'IP Address - ALL', 'null');
            while ($row_ip = mysqli_fetch_object($result_ip)) {

                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $row_ip->id . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ')', $ipid);

            }
            echo $form->showDropdownBottom('');


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
                              $segment_string
                            GROUP BY h.name
                            ORDER BY h.name asc";
            $result_hosting = mysqli_query($connection, $sql_hosting);
            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1', '', 'Web Hosting Provider - ALL', 'null');
            while ($row_hosting = mysqli_fetch_object($result_hosting)) {

                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $row_hosting->id . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $row_hosting->id, $row_hosting->name, $whid);

            }
            echo $form->showDropdownBottom('');


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
                               $segment_string
                             GROUP BY c.name
                             ORDER BY c.name asc";
            $result_category = mysqli_query($connection, $sql_category);
            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?pcid=&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1', '', 'Category - ALL', 'null');
            while ($row_category = mysqli_fetch_object($result_category)) {

                echo $form->showDropdownOptionJump('index.php?pcid=' . $row_category->id . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $row_category->id, $row_category->name, $pcid);

            }
            echo $form->showDropdownBottom('');


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
                            $segment_string
                          GROUP BY o.name
                          ORDER BY o.name asc";
            $result_owner = mysqli_query($connection, $sql_owner);
            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1', '', 'Owner - ALL', 'null');
            while ($row_owner = mysqli_fetch_object($result_owner)) {

                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $row_owner->id . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $row_owner->id, $row_owner->name, $oid);

            }
            echo $form->showDropdownBottom('');


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
                          $segment_string
                        GROUP BY tld
                        ORDER BY tld asc";
            $result_tld = mysqli_query($connection, $sql_tld);
            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1', '', 'TLD - ALL', 'null');
            while ($row_tld = mysqli_fetch_object($result_tld)) {

                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $row_tld->tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $row_tld->tld, $row_tld->tld, $tld);

            }
            echo $form->showDropdownBottom('');


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
                             $segment_string
                           GROUP BY active
                           ORDER BY active asc";
            $result_active = mysqli_query($connection, $sql_active);
            echo $form->showDropdownTopJump('', '', '', '');
            echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=LIVE&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $is_active, '"Live" Domains (Active / Transfers / Pending)', 'LIVE');
            while ($row_active = mysqli_fetch_object($result_active)) {

                if ($row_active->active == "0") {
                    $display_text = "Expired";
                } elseif ($row_active->active == "1") {
                    $display_text = "Active";
                } elseif ($row_active->active == "2") {
                    $display_text = "Pending (Transfer)";
                } elseif ($row_active->active == "3") {
                    $display_text = "Pending (Renewal)";
                } elseif ($row_active->active == "4") {
                    $display_text = "Pending (Other)";
                } elseif ($row_active->active == "5") {
                    $display_text = "Pending (Registration)";
                } elseif ($row_active->active == "10") {
                    $display_text = "Sold";
                }

                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $row_active->active . '&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $is_active, $display_text, $row_active->active);

            }
            echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=ALL&result_limit=' . $result_limit . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $is_active, 'ALL', 'ALL');
            echo $form->showDropdownBottom('');

            if ($_SESSION['s_system_large_mode'] == '1') {

                // NUMBER OF DOMAINS TO DISPLAY
                echo $form->showDropdownTopJump('', '', '', '');

                if ($_SESSION['s_number_of_domains'] != "10" && $_SESSION['s_number_of_domains'] != "50" && $_SESSION['s_number_of_domains'] != "100" && $_SESSION['s_number_of_domains'] != "500" && $_SESSION['s_number_of_domains'] != "1000" && $_SESSION['s_number_of_domains'] != "1000000") {

                    echo $form->showDropdownOptionJump('index.php.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=' . $_SESSION['s_number_of_domains'] . '&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $result_limit, $_SESSION['s_number_of_domains'], $_SESSION['s_number_of_domains']);

                }

                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=10&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $result_limit, '10', '10');
                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=50&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $result_limit, '50', '50');
                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=100&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $result_limit, '100', '100');
                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=500&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $result_limit, '500', '500');
                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=1000&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $result_limit, '1,000', '1000');
                echo $form->showDropdownOptionJump('index.php?pcid=' . $pcid . '&oid=' . $oid . '&dnsid=' . $dnsid . '&ipid=' . $ipid . '&whid=' . $whid . '&rid=' . $rid . '&raid=' . $raid . '&start_date=' . $new_start_date . '&end_date=' . $new_end_date . '&tld=' . $tld . '&segid=' . $segid . '&is_active=' . $is_active . '&result_limit=1000000&sort_by=' . $sort_by . '&from_dropdown=1&expand=1&null=', $result_limit, 'ALL', '1000000');

                echo $form->showDropdownBottom('');

            } ?>


            <?php echo $form->showInputText('search_for', 'Domain Keyword Search', '', $_SESSION['s_search_for'], '100', '', '', '', ''); ?>

            <?php
            if ($new_start_date == "") {
                $new_start_date = $time->toUserTimezone($time->timeBasic(), 'Y-m-d');
            }
            if ($new_end_date == "") {
                $new_end_date = '3000-12-31';
            }
            echo $form->showInputText('daterange', 'Expiring Between', '', $new_start_date . ' - ' . $new_end_date, '23', '', '', '', '');

            echo $form->showInputHidden('pcid', $pcid);
            echo $form->showInputHidden('oid', $oid);
            echo $form->showInputHidden('dnsid', $dnsid);
            echo $form->showInputHidden('whid', $whid);
            echo $form->showInputHidden('rid', $rid);
            echo $form->showInputHidden('raid', $raid);
            echo $form->showInputHidden('tld', $tld);
            echo $form->showInputHidden('segid', $segid);
            echo $form->showInputHidden('result_limit', $result_limit);
            echo $form->showInputHidden('sort_by', $sort_by);
            echo $form->showInputHidden('is_active', $is_active);
            echo $form->showInputHidden('begin', '0');
            echo $form->showInputHidden('num', '1');
            echo $form->showInputHidden('numBegin', '1');
            echo $form->showSubmitButton('Apply Filters', '', '');
            ?>
            &nbsp;&nbsp;&nbsp;<a
                href="<?php echo $web_root; ?>/domains/"><?php echo $layout->showButton('button', 'Reset Filters'); ?></a><?php

            echo $form->showFormBottom(''); ?>

        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->
    <BR><?php

}

if ($segid != "") {

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
    <strong>Domains in Segment:</strong> <?php echo number_format($number_of_domains); ?><BR><BR>

    <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
        <strong>Matching Domains:</strong> <?php echo number_format($totalrows); ?><BR><BR>
    <?php } else { ?>
        <strong>Matching Domains:</strong> <?php echo number_format(mysqli_num_rows($result)); ?><BR><BR>
    <?php } ?>

    <?php if ($totalrows_inactive > 0) { ?>
        <strong>Matching But Inactive Domains:</strong> <?php echo number_format($totalrows_inactive); ?> [<a
            target="_blank" href="results.php?type=inactive&segid=<?php echo urlencode($segid); ?>">view</a>]<BR><BR>
    <?php } ?>
    <?php if ($totalrows_filtered > 0) { ?>
        <strong>Matching But Filtered Domains:</strong> <?php echo number_format($totalrows_filtered); ?> [<a
            target="_blank" href="results.php?type=filtered&segid=<?php echo urlencode($segid); ?>">view</a>]<BR><BR>
    <?php } ?>
    <?php if ($totalrows_missing > 0) { ?>
        <strong>Missing Domains:</strong> <?php echo number_format($totalrows_missing); ?> [<a
            target="_blank" href="results.php?type=missing&segid=<?php echo urlencode($segid); ?>">view</a>]<BR><BR>
    <?php }

}

if (mysqli_num_rows($result) > 0) { ?>

    <a href="add.php"><?php echo $layout->showButton('button', 'Add Domain'); ?></a>
    <a href="<?php echo $web_root; ?>/queue/info.php"><?php echo $layout->showButton('button', 'Add Domains To Queue'); ?></a>
    <a target="_blank" href="<?php echo $web_root; ?>/raw.php"><?php echo $layout->showButton('button', 'Raw List'); ?></a>
    <a href="index.php?<?php echo urlencode($_SERVER['QUERY_STRING']); ?>&export_data=1"><?php echo $layout->showButton('button', 'Export'); ?></a>

    <?php if ($segid != "") { ?>
        <BR><BR><strong>Total Cost:</strong> <?php echo htmlentities($grand_total, ENT_QUOTES); ?> <?php echo htmlentities($_SESSION['s_default_currency'], ENT_QUOTES); ?>
        <BR><BR>
    <?php } else { ?>
        <BR><BR><strong>Total Cost:</strong> <?php echo htmlentities($grand_total, ENT_QUOTES); ?> <?php echo htmlentities($_SESSION['s_default_currency'], ENT_QUOTES); ?><BR>

        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
            <strong>Number of Domains:</strong> <?php echo number_format($totalrows); ?><BR><BR>
        <?php } else { ?>
            <strong>Number of Domains:</strong> <?php echo number_format(mysqli_num_rows($result)); ?><BR><BR>
        <?php } ?>

    <?php }

    if ($totalrows != '0') {

        if ($_SESSION['s_system_large_mode'] == '1') {
            include(DIR_INC . "layout/pagination-large-mode.inc.php");
        } ?>

        <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <?php if ($_SESSION['s_system_large_mode'] != '1') { ?>
                    <th width="20px"></th>
                <?php } ?>

                <th class="all">
                    <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                        <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php echo urlencode($dnsid);
                        ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid); ?>&raid=<?php
                        echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date); ?>&segid=<?php
                        echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active); ?>&result_limit=<?php
                        echo urlencode($result_limit); ?>&sort_by=<?php
                        if ($sort_by == "dn_a") {
                            echo "dn_d";
                        } else {
                            echo "dn_a";
                        } ?>&from_dropdown=1" style="color:#000000;">Domain</a>
                    <?php } else { ?>
                            Domain
                    <?php } ?>
                </th>
                <?php if ($_SESSION['s_display_domain_expiry_date'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php
                            echo urlencode($dnsid); ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid);
                            ?>&raid=<?php echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php
                            echo urlencode($new_end_date); ?>&segid=<?php echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php
                            echo urlencode($is_active); ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "ed_a") {
                                echo "ed_d";
                            } else {
                                echo "ed_a";
                            }
                            ?>&from_dropdown=1" style="color:#000000;">Expiry</a>
                        <?php } else { ?>
                            Expiry
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_fee'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php
                            echo urlencode($dnsid); ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid);
                            ?>&raid=<?php echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php
                            echo urlencode($new_end_date); ?>&segid=<?php echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php
                            echo urlencode($is_active); ?>&result_limit=<?php echo urlencode($result_limit);
                            ?>&sort_by=<?php
                            if ($sort_by == "df_a") {
                                echo "df_d";
                            } else {
                                echo "df_a";
                            }
                            ?>&from_dropdown=1" style="color:#000000;">Fee</a>
                        <?php } else { ?>
                                Fee
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_tld'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                        <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php echo urlencode($dnsid);
                        ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid); ?>&raid=<?php
                        echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php
                        echo urlencode($new_end_date); ?>&segid=<?php echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php
                        echo urlencode($is_active); ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                        if ($sort_by == "tld_a") {
                            echo "tld_d";
                        } else {
                            echo "tld_a";
                        } ?>&from_dropdown=1" style="color:#000000;">TLD</a>
                    <?php } else { ?>
                            TLD
                    <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_registrar'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php echo urlencode($dnsid);
                            ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid); ?>&raid=<?php
                            echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "r_a") {
                                echo "r_d";
                            } else {
                                echo "r_a";
                            } ?>&from_dropdown=1" style="color:#000000;">Registrar</a>
                        <?php } else { ?>
                                Registrar
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_account'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php echo urlencode($dnsid);
                            ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid); ?>&raid=<?php
                            echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "ra_a") {
                                echo "ra_d";
                            } else {
                                echo "ra_a";
                            } ?>&from_dropdown=1" style="color:#000000;">Account</a>
                        <?php } else { ?>
                                Account
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_dns'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php echo urlencode($dnsid);
                            ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid); ?>&raid=<?php
                            echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "dns_a") {
                                echo "dns_d";
                            } else {
                                echo "dns_a";
                            } ?>&from_dropdown=1" style="color:#000000;">DNS</a>
                        <?php } else { ?>
                                DNS
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_ip'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php echo urlencode($dnsid);
                            ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid); ?>&raid=<?php
                            echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "ip_a") {
                                echo "ip_d";
                            } else {
                                echo "ip_a";
                            } ?>&from_dropdown=1" style="color:#000000;">IP</a>
                        <?php } else { ?>
                                IP
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_host'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php echo urlencode($dnsid);
                            ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid); ?>&raid=<?php
                            echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "wh_a") {
                                echo "wh_d";
                            } else {
                                echo "wh_a";
                            } ?>&from_dropdown=1" style="color:#000000;">Host</a>
                        <?php } else { ?>
                                Host
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_category'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php echo urlencode($dnsid);
                            ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid); ?>&raid=<?php
                            echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "pc_a") {
                                echo "pc_d";
                            } else {
                                echo "pc_a";
                            } ?>&from_dropdown=1" style="color:#000000;">Category</a>
                        <?php } else { ?>
                                Category
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_owner'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo urlencode($pcid); ?>&oid=<?php echo urlencode($oid); ?>&dnsid=<?php echo urlencode($dnsid);
                            ?>&ipid=<?php echo urlencode($ipid); ?>&whid=<?php echo urlencode($whid); ?>&rid=<?php echo urlencode($rid); ?>&raid=<?php
                            echo urlencode($raid); ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo urlencode($segid); ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "o_a") {
                                echo "o_d";
                            } else {
                                echo "o_a";
                            } ?>&from_dropdown=1" style="color:#000000;">Owner</a>
                        <?php } else { ?>
                                Owner
                        <?php } ?>
                    </th>
                <?php } ?>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_object($result)) { ?>
                <tr>

                    <?php if ($_SESSION['s_system_large_mode'] != '1') { ?>
                        <td></td>
                    <?php } ?>

                    <td>
                        <?php if ($row->active == "0") {
                            echo "<a title=\"Inactive Domain\"><strong>x</strong></a>&nbsp;";
                        } elseif ($row->active == "2") {
                            echo "<a title=\"Pending (Transfer)\"><strong>T</strong></a>&nbsp;";
                        } elseif ($row->active == "3") {
                            echo "<a title=\"Pending (Renewal)\"><strong>PRn</strong></a>&nbsp;";
                        } elseif ($row->active == "4") {
                            echo "<a title=\"Pending (Other)\"><strong>PO</strong></a>&nbsp;";
                        } elseif ($row->active == "5") {
                            echo "<a title=\"Pending (Registration)\"><strong>PRg</strong></a>&nbsp;";
                        } elseif ($row->active == "10") {
                            echo "<a title=\"Sold\"><strong>S</strong></a>&nbsp;";
                        }
                        ?>
                        <a href="edit.php?did=<?php echo $row->id; ?>"><?php echo $row->domain; ?></a><?php if ($row->privacy == "1") {
                            echo "&nbsp;<a title=\"Private WHOIS Registration\"><strong>prv</strong></a>";
                        } ?>
                    </td>
                    <?php if ($_SESSION['s_display_domain_expiry_date'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                            <a href="edit.php?did=<?php echo $row->id; ?>"><?php echo $row->expiry_date; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_fee'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                            <a href="../assets/edit/registrar-fee.php?rid=<?php echo $row->r_id; ?>&fee_id=<?php echo $row->f_id; ?>">
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
                    <?php if ($_SESSION['s_display_domain_tld'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                            <a href="edit.php?did=<?php echo $row->id; ?>">.<?php echo $row->tld; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_registrar'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                            <a href="../assets/edit/registrar.php?rid=<?php echo $row->r_id; ?>"><?php echo $row->registrar_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_account'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                            <a href="../assets/edit/registrar.php?rid=<?php echo $row->r_id; ?>"><?php echo $row->registrar_name; ?></a>,
                            <a href="../assets/edit/account-owner.php?oid=<?php echo $row->o_id; ?>"><?php echo $row->owner_name; ?></a>
                            (<a href="../assets/edit/registrar-account.php?raid=<?php echo $row->ra_id; ?>"><?php echo substr($row->username, 0, 15); ?><?php if (strlen($row->username) >= 16) echo "..."; ?></a>)
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_dns'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                            <a href="../assets/edit/dns.php?dnsid=<?php echo $row->dnsid; ?>"><?php echo $row->dns_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_ip'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                            <a href="../assets/edit/ip-address.php?ipid=<?php echo $row->ipid; ?>"><?php echo $row->ip_name; ?>
                                (<?php echo $row->ip; ?>)</a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_host'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                            <a href="../assets/edit/host.php?whid=<?php echo $row->whid; ?>"><?php echo $row->wh_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_category'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                            <a href="../assets/edit/category.php?pcid=<?php echo $row->pcid; ?>"><?php echo $row->category_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_owner'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' style="padding-left:20px;"'; } ?>>
                            <a href="../assets/edit/account-owner.php?oid=<?php echo $row->o_id; ?>"><?php echo $row->owner_name; ?></a>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
        </tbody>
        </table><BR><?php
    }

    if ($_SESSION['s_system_large_mode'] == '1') {
        include(DIR_INC . "layout/pagination-large-mode.inc.php");
    }

} else {

    if ($_SESSION['s_has_domain'] == '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') { ?>

        Your search returned zero results.<?php

    }

} ?>
<?php include(DIR_INC . "layout/footer.inc.php");  //@formatter:on ?>
<?php include(DIR_INC . "layout/date-range-picker-footer.inc.php"); ?>
</body>
</html>
