<?php
/**
 * /domains/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$date = new DomainMOD\Date();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$currency = new DomainMOD\Currency();
$customField = new DomainMOD\CustomField();
$segment = new DomainMOD\Segment();
$validate = new DomainMOD\Validate();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/domains-main.inc.php';

$system->authCheck();
// Rates for every currency in use, or the lists and reports below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');
$pdo = $deeb->cnxx;

// The keyword search as an SQL condition on a domain column (see DomainList::keywordCondition())
$domain_search_condition = function ($column, $term) use ($pdo) {
    return DomainMOD\DomainList::keywordCondition($pdo, $column, $term);
};

$export_data = (int) ($_GET['export_data'] ?? 0);
$pcid = (int) ($_REQUEST['pcid'] ?? 0);
$oid = (int) ($_REQUEST['oid'] ?? 0);
$dnsid = (int) ($_REQUEST['dnsid'] ?? 0);
$ipid = (int) ($_REQUEST['ipid'] ?? 0);
$whid = (int) ($_REQUEST['whid'] ?? 0);
$rid = (int) ($_REQUEST['rid'] ?? 0);
$raid = (int) ($_REQUEST['raid'] ?? 0);
$tld = isset($_REQUEST['tld']) ? $sanitize->text($_REQUEST['tld']) : '';
$segid = (int) ($_REQUEST['segid'] ?? 0);
$is_active = $_REQUEST['is_active'] ?? '';
$search_for = isset($_REQUEST['search_for']) ? $sanitize->text($_REQUEST['search_for']) : '';
$from_dropdown = (int) ($_REQUEST['from_dropdown'] ?? 0);
$expand = (int) ($_REQUEST['expand'] ?? 0);
$daterange = isset($_REQUEST['daterange']) ? $sanitize->text($_REQUEST['daterange']) : '';

list($new_start_date, $new_end_date) = $date->splitAndCheckRange($daterange);

if ($_SESSION['s_system_large_mode'] == '1') {

    // These go into the query's LIMIT and the page links, so they must be whole numbers. An unknown sort falls back
    // to the default below.
    $result_limit = isset($_REQUEST['result_limit']) && (int) $_REQUEST['result_limit'] > 0 ? (int) $_REQUEST['result_limit'] : '';
    $sort_by = isset($_REQUEST['sort_by']) && preg_match('/^[a-z]{1,3}_[ad]$/', (string) $_REQUEST['sort_by']) ? $_REQUEST['sort_by'] : '';
    $numBegin = isset($_REQUEST['numBegin']) ? max(0, (int) $_REQUEST['numBegin']) : '';
    $begin = isset($_REQUEST['begin']) ? max(0, (int) $_REQUEST['begin']) : '';
    $num = isset($_REQUEST['num']) ? max(0, (int) $_REQUEST['num']) : '';

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $from_dropdown = 0;

    $date = new DomainMOD\Date();

    if ($new_start_date > $new_end_date) {

        $_SESSION['s_message_danger'] .= _('The date range is invalid') . '<BR>';

    }

}

if ($export_data !== 1) {

    $numBegin = $numBegin ?? '';

    if ($from_dropdown !== 1) {

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

// A range saved by an older version may not have been checked, so anything that isn't a real date range is dropped.
if (!$date->checkDateFormat($_SESSION['s_start_date']) || !$date->checkDateFormat($_SESSION['s_end_date'])) {

    $_SESSION['s_start_date'] = 'YYYY-MM-DD';
    $_SESSION['s_end_date'] = 'YYYY-MM-DD';

}

// The keyword and date range the list is filtered by. An export reads them from its own request when its link carries
// them (FilterToolbar::exportUrl() always does), without touching the session, so another tab's filters can't leak into
// it. An older export link without them falls back to the session, as before.
$filter_search_for = $_SESSION['s_search_for'];
$filter_start_date = $_SESSION['s_start_date'];
$filter_end_date = $_SESSION['s_end_date'];

if ($export_data === 1) {

    if (array_key_exists('search_for', $_GET)) $filter_search_for = $search_for;

    if (array_key_exists('daterange', $_GET)) {

        // The same test the list uses when it saves a range, so the export matches the list exactly
        if ($new_start_date != '' && $new_start_date != 'YYYY-MM-DD') {

            $filter_start_date = $new_start_date;
            $filter_end_date = $new_end_date;

        } else {

            $filter_start_date = 'YYYY-MM-DD';
            $filter_end_date = 'YYYY-MM-DD';

        }

    }

}

if ($_SESSION['s_system_large_mode'] == '1') {

    if ($result_limit == "") $result_limit = $_SESSION['s_number_of_domains'];

}

// Anything else (including translated values in links from older versions) falls back to the default
$valid_is_active = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'LIVE', 'ALL');
if (!is_string($is_active) || !in_array($is_active, $valid_is_active, true)) $is_active = 'LIVE';

if ($tld && $tld != '') {

    if ($validate->tld($tld) == false || $tld == '0') {

        $tld = '';

    } else {

        // the stored form, so xn--p1ai finds domains under рф (quoted by PDO wherever it's used in SQL below)
        $tld = DomainMOD\Domain::normalizeTld(htmlspecialchars_decode($tld, ENT_QUOTES));

    }

}

// The list's filters as SQL, from the same class as the list's data endpoint (domains/list-data.php), so the first
// page, the pages fetched afterwards, the counts, Export, and the Raw List all agree
$dfd_columns = $customField->getCustomFieldsSql('domain_fields', 'dfd');
$domain_list = new DomainMOD\DomainList($pdo, $_SESSION['s_user_id'], $dfd_columns);
$list_state = array('pcid' => $pcid, 'oid' => $oid, 'dnsid' => $dnsid, 'ipid' => $ipid, 'whid' => $whid, 'rid' => $rid,
    'raid' => $raid, 'segid' => $segid, 'tld' => $tld, 'is_active' => $is_active);
$filter_parts = $domain_list->filterParts($list_state, $filter_search_for, $filter_start_date, $filter_end_date);
$is_active_string = $filter_parts['is_active_string'];
$segid_string = $filter_parts['segid_string'];
$pcid_string = $filter_parts['pcid_string'];
$oid_string = $filter_parts['oid_string'];
$dnsid_string = $filter_parts['dnsid_string'];
$ipid_string = $filter_parts['ipid_string'];
$whid_string = $filter_parts['whid_string'];
$rid_string = $filter_parts['rid_string'];
$raid_string = $filter_parts['raid_string'];
$range_string = $filter_parts['range_string'];
$tld_string = $filter_parts['tld_string'];
$search_string = $filter_parts['search_string'];

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

$sql = $domain_list->selectSql() . $domain_list->fromSql() . "
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
$_SESSION['s_raw_list_sql'] = $sql;

// The list's filters as they are now, kept for the empty state's counts, since the filter menus below reuse these names
$list_filter_sql = $is_active_string . $segid_string . $pcid_string . $oid_string . $dnsid_string . $ipid_string
    . $whid_string . $rid_string . $raid_string . $range_string . $tld_string . $search_string;
$list_has_range = $range_string != '';
$list_has_search = $search_string != '';

// How many domains the filters match (the same joins and filters as the main query)
$total_rows = $domain_list->count($list_filter_sql);

// The total leaves out the custom field join the list uses, as it always has (a known quirk, kept for now)
$grand_total = $pdo->query("
    SELECT SUM(d.total_cost * cc.conversion)
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
      $search_string")->fetchColumn();

$grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

if ($segid !== 0) {

    // The segment's flags follow the whole filtered list, not the page shown, so only the domains are read here
    $active_domains = $pdo->query("SELECT d.domain" . $domain_list->fromSql() . $list_filter_sql)->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("
        UPDATE segment_data
        SET filtered = '0'
        WHERE active = '1'
          AND segment_id = :segid");
    $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
    $stmt->execute();

    if ($active_domains) {

        $active_domains = implode(', ', array_map(array($pdo, 'quote'), $active_domains));

        $stmt = $pdo->prepare("
            UPDATE segment_data
            SET filtered = '1'
            WHERE active = '1'
              AND segment_id = :segid
              AND domain NOT IN (" . $active_domains . ")");
        $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
        $stmt->execute();

    } else {

        // None of the segment's domains are left by the other filters, so every one of them is filtered out
        $stmt = $pdo->prepare("
            UPDATE segment_data
            SET filtered = '1'
            WHERE active = '1'
              AND segment_id = :segid");
        $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
        $stmt->execute();

    }

    $stmt = $pdo->prepare("
        UPDATE segment_data
        SET filtered = '1'
        WHERE active = '1'
          AND segment_id = :segid
          AND NOT " . $domain_search_condition('domain', $search_for));
    $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
    $stmt->execute();

}

if ($export_data == 1) {

    $result = $pdo->query($sql)->fetchAll();

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('domain_results'), strtotime($time->stamp()));

    $row_contents = array(_('Domain Search Results Export'));
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    if ($segid == 0) {

        $row_contents = array(
            _('Total Cost') . ':',
            $grand_total,
            $_SESSION['s_default_currency']
        );
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            _('Number of Domains') . ':',
            $total_rows
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    } else {

        $row_contents = array(
            _('Total Cost') . ':',
            $grand_total,
            $_SESSION['s_default_currency']
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    }

    if ($tld != "") {

        $row_contents = array(
            _('TLD'),
            '.' . $tld
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($segid !== 0) {

        $stmt = $pdo->prepare("
            SELECT count(*)
            FROM segment_data
            WHERE segment_id = :segid
              AND inactive = '1'");
        $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
        $stmt->execute();
        $totalrows_inactive = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT count(*)
            FROM segment_data
            WHERE segment_id = :segid
              AND missing = '1'");
        $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
        $stmt->execute();
        $totalrows_missing = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT count(*)
            FROM segment_data
            WHERE segment_id = :segid
              AND filtered = '1'");
        $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
        $stmt->execute();
        $totalrows_filtered = $stmt->fetchColumn();

        if ($segid !== 0) {

            $number_of_domains = $segment->getNumberOfDomains($segid);

        }

        $row_contents = array('[' . _('Segment Results') . ']');
        $export->writeRow($export_file, $row_contents);

        $temp_name = $segment->getName($segid);

        $row_contents = array(
            _('Segment Filter') . ':',
            $temp_name
        );
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            _('Domains in Segment') . ':',
            number_format($number_of_domains)
        );
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            _('Matching Domains') . ':',
            $total_rows
        );
        $export->writeRow($export_file, $row_contents);

        if ($totalrows_inactive > 0) {

            $row_contents = array(
                _('Matching But Inactive Domains') . ':',
                number_format($totalrows_inactive)
            );
            $export->writeRow($export_file, $row_contents);

        }

        if ($totalrows_filtered > 0) {

            $row_contents = array(
                _('Matching But Filtered Domains') . ':',
                number_format($totalrows_filtered)
            );
            $export->writeRow($export_file, $row_contents);

        }

        if ($totalrows_missing > 0) {

            $row_contents = array(
                _('Missing Domains') . ':',
                number_format($totalrows_missing)
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    $row_contents = array('[' . _('Search Filters') . ']');
    $export->writeRow($export_file, $row_contents);

    if ($filter_search_for != "") {

        $row_contents = array(
            _('Keyword Search') . ':',
            $filter_search_for
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($rid > 0) {

        $temp_registrar_name = $assets->getRegistrar($rid);

        $row_contents = array(
            _('Registrar') . ':',
            $temp_registrar_name
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($raid > 0) {

        $stmt = $pdo->prepare("
            SELECT r.name AS registrar_name, o.name AS owner_name, ra.username
            FROM registrar_accounts AS ra, registrars AS r, owners AS o
            WHERE ra.registrar_id = r.id
              AND ra.owner_id = o.id
              AND ra.id = :raid");
        $stmt->bindValue('raid', $raid, PDO::PARAM_INT);
        $stmt->execute();
        // Its own variable, so the domains in $result are still there for the rows below
        $result_account = $stmt->fetch();
        $stmt->closeCursor();

        if ($result_account) {

            $row_contents = array(
                _('Registrar Account') . ':',
                $result_account->registrar_name . " - " . $result_account->owner_name . " - " . $result_account->username
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($dnsid > 0) {

        $temp_dns_name = $assets->getDnsName($dnsid);

        $row_contents = array(
            _('DNS Profile') . ':',
            $temp_dns_name
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($ipid > 0) {

        list($temp_ip, $temp_ip_name) = $assets->getIpAndName($ipid);

        $row_contents = array(
            _('IP Address') . ':',
            $temp_ip_name . ' (' . $temp_ip . ')'
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($whid > 0) {

        $temp_hosting_name = $assets->getHost($whid);

        $row_contents = array(
            _('Web Host') . ':',
            $temp_hosting_name
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($pcid > 0) {

        $temp_category = $assets->getCat($pcid);

        $row_contents = array(
            _('Category') . ':',
            $temp_category
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($oid > 0) {

        $temp_owner = $assets->getOwner($oid);

        $row_contents = array(
            _('Owner') . ':',
            $temp_owner
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($filter_start_date != '' && $filter_start_date != 'YYYY-MM-DD') {

        $row_contents = array(
            _('Expiry Date Range') . ':',
            $filter_start_date  . " to " . $filter_end_date
        );
        $export->writeRow($export_file, $row_contents);

    }

    unset($row_contents);
    $count = 0;

    $row_contents[$count++] = _('Domain Status') . ':';

    if ($is_active == 'ALL') {

        $row_contents[$count++] = DomainMOD\Format::toUpper(_('All'));

    } elseif ($is_active == 'LIVE' || $is_active == "") {

        $row_contents[$count++] = DomainMOD\Format::toUpper(_('Live')) . ' (' . _('Active') . ' / ' . _('Transfers') . ' / ' . _('Pending') . ')';

    } elseif ($is_active == "0") {

        $row_contents[$count++] = _('Expired');

    } elseif ($is_active == "1") {

        $row_contents[$count++] = _('Active');

    } elseif ($is_active == "2") {

        $row_contents[$count++] = _('Pending (Transfer)');

    } elseif ($is_active == "3") {

        $row_contents[$count++] = _('Pending (Renewal)');

    } elseif ($is_active == "4") {

        $row_contents[$count++] = _('Pending (Other)');

    } elseif ($is_active == "5") {

        $row_contents[$count++] = _('Pending (Registration)');

    } elseif ($is_active == "10") {

        $row_contents[$count++] = _('Sold');

    }
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    unset($row_contents);
    $count = 0;

    $row_contents[$count++] = _('Domain Status');
    $row_contents[$count++] = _('Expiry Date');
    $row_contents[$count++] = _('Initial Fee');
    $row_contents[$count++] = _('Renewal Fee');
    $row_contents[$count++] = _('Transfer Fee');
    $row_contents[$count++] = _('Privacy Fee');
    $row_contents[$count++] = _('Misc Fee');
    $row_contents[$count++] = _('Total Yearly Cost');
    $row_contents[$count++] = _('Domain');
    $row_contents[$count++] = _('TLD');
    $row_contents[$count++] = _('Function');
    $row_contents[$count++] = _('Renewal Status');
    $row_contents[$count++] = _('WHOIS Status');
    $row_contents[$count++] = _('Registrar');
    $row_contents[$count++] = _('Registrar Account');
    $row_contents[$count++] = _('Username');
    $row_contents[$count++] = _('DNS Profile');
    $row_contents[$count++] = _('IP Address Name');
    $row_contents[$count++] = _('IP Address');
    $row_contents[$count++] = _('IP Address rDNS');
    $row_contents[$count++] = _('Web Host');
    $row_contents[$count++] = _('Category');
    $row_contents[$count++] = _('Category Stakeholder');
    $row_contents[$count++] = _('Owner');
    $row_contents[$count++] = _('Notes');
    $row_contents[$count++] = _('Creation Type');
    $row_contents[$count++] = _('Created By');
    $row_contents[$count++] = _('Inserted');
    $row_contents[$count++] = _('Updated');
    $row_contents[$count++] = DomainMOD\Format::toUpper(_('Custom Fields'));

    $result_field = $pdo->query("
        SELECT `name`
        FROM domain_fields
        ORDER BY `name` ASC")->fetchAll();

    if ($result_field) {

        foreach ($result_field as $row_field) {

            $row_contents[$count++] = $row_field->name;

        }

    }

    $export->writeRow($export_file, $row_contents);

    foreach ($result as $row) {

        $temp_initial_fee = $row->initial_fee * $row->conversion;
        $temp_renewal_fee = $row->renewal_fee * $row->conversion;
        $temp_transfer_fee = $row->transfer_fee * $row->conversion;
        $temp_privacy_fee = $row->privacy_fee * $row->conversion;
        $temp_misc_fee = $row->misc_fee * $row->conversion;
        $temp_total_cost = $row->total_cost * $row->conversion;

        // Blank for any value not listed below, rather than the previous domain's label.
        $domain_status = '';
        $autorenew_status = '';
        $privacy_status = '';

        if ($row->active == "0") {
            $domain_status = DomainMOD\Format::toUpper(_('Expired'));
        } elseif ($row->active == "1") {
            $domain_status = DomainMOD\Format::toUpper(_('Active'));
        } elseif ($row->active == "2") {
            $domain_status = DomainMOD\Format::toUpper(_('Pending (Transfer)'));
        } elseif ($row->active == "3") {
            $domain_status = DomainMOD\Format::toUpper(_('Pending (Renewal)'));
        } elseif ($row->active == "4") {
            $domain_status = DomainMOD\Format::toUpper(_('Pending (Other)'));
        } elseif ($row->active == "5") {
            $domain_status = DomainMOD\Format::toUpper(_('Pending (Registration)'));
        } elseif ($row->active == "10") {
            $domain_status = DomainMOD\Format::toUpper(_('Sold'));
        }

        if ($row->autorenew == "1") {
            $autorenew_status = _('Auto Renewal');
        } elseif ($row->autorenew == "0") {
            $autorenew_status = _('Manual Renewal');
        }

        if ($row->privacy == "1") {
            $privacy_status = _('Private');
        } elseif ($row->privacy == "0") {
            $privacy_status = _('Public');
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

        $creation_type = $system->getCreationType($row->creation_type_id);

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
            $row_contents[$count++] = _('Unknown');
        } else {
            $user = new DomainMOD\User();
            $row_contents[$count++] = $user->getFullName($row->created_by);
        }
        $row_contents[$count++] = $time->toUserTimezone($row->insert_time);
        $row_contents[$count++] = $time->toUserTimezone($row->update_time);
        $row_contents[$count++] = '';

        $dfd_columns_array = $customField->getCustomFields('domain_fields');

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
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-range-picker-head.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
$result_supported = $pdo->query("
    SELECT `name`
    FROM api_registrars
    ORDER BY name ASC")->fetchAll();

$supported_registrars = '';
foreach ($result_supported as $row_supported) {

    $supported_registrars .= ', ' . $row_supported->name;

}
$supported_registrars = substr($supported_registrars, 2);

// Double check to make sure there are still no domains in the system
if ($_SESSION['s_has_domain'] == '0') {
    
    $queryB = new DomainMOD\QueryBuild();
    $sql_asset_check = $queryB->singleAsset('domains');
    $_SESSION['s_has_domain'] = $system->checkForRows($sql_asset_check);

}

// Before anything exists, the list walks a new user through the order things are added in: a registrar, then an
// account at it, then domains, one step at a time (Layout::emptyState())
$first_run_link = sprintf(_('See %sFirst Run%s in the User Guide for more.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/first-run/">', '</a>');

if ($_SESSION['s_has_registrar'] != '1') {

    echo $layout->emptyState('bi-building', _('Add a registrar first'), _('Domains belong to a registrar account, so start by adding the registrar you use.') . ' ' . $first_run_link, '/assets/add/registrar.php', _('Add Registrar'), '/domains/csv-importer/', _('Import from a CSV file instead'), 2, true, true);

}

if ($_SESSION['s_has_registrar_account'] != '1' && $_SESSION['s_has_registrar'] == '1') {

    echo $layout->emptyState('bi-person-badge', _('Add a registrar account next'), _('Add the account you hold at that registrar.') . ' ' . $first_run_link, '/assets/add/registrar-account.php', _('Add Registrar Account'));

}

if ($_SESSION['s_has_domain'] != '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {

    echo $layout->emptyState('bi-globe2', _('No domains yet'), sprintf(_('Add your first domain, or let %s import them from your registrar.'), SOFTWARE_TITLE) . ' ' . sprintf(_('See %sAdding Domains%s in the User Guide for more ways.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/adding-domains/">', '</a>'), '/domains/add.php', _('Add Domain'), '/queue/intro.php', _('Add Domains Automatically'), 2, true, true);
    $ready_for_domains = 1;

} elseif ($_SESSION['s_has_domain'] == '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {

    $ready_for_domains = 1;

}

/*
 * Server-side processing (outside Large Mode): the page prints one page of rows, in the default order, and DataTables
 * fetches the others from domains/list-data.php (see js/domain-list.js). The page and its size come from the address
 * (the no-JavaScript pager's links), checked as the endpoint checks them, and a page past the end (an old link, after
 * domains were deleted) moves to the last one. A page always starts on a multiple of its size, as DataTables' own
 * paging does.
 */
$list_columns = new DomainMOD\DomainListColumns(DomainMOD\DomainListColumns::sessionCopy($_SESSION), $dfd_columns);
$list_page_sizes = DomainMOD\DomainList::pageSizes($_SESSION['s_number_of_domains']);
$list_length = DomainMOD\DomainList::checkedLength($_GET['length'] ?? '', $list_page_sizes);
$list_start = DomainMOD\DomainList::checkedStart($_GET['start'] ?? 0);
$list_start -= $list_start % $list_length;
if ($list_start >= $total_rows) $list_start = $total_rows > 0 ? (int) (floor(($total_rows - 1) / $list_length) * $list_length) : 0;

if ($_SESSION['s_system_large_mode'] == '1') {

    $temp_numbegin = max(0, (int) ($_REQUEST['numBegin'] ?? 0));
    $temp_begin = max(0, (int) ($_REQUEST['begin'] ?? 0));
    $temp_num = max(0, (int) ($_REQUEST['num'] ?? 0));

    $parameters = array($total_rows, 15, $result_limit, "&pcid=" . $pcid . "&oid=" . $oid . "&dnsid=" . $dnsid . "&ipid=" . $ipid . "&whid=" . $whid . "&rid=" . $rid . "&raid=" . $raid . "&daterange=" . $daterange . "&tld=" . $tld . "&segid=" . $segid . "&is_active=" . $is_active . "&result_limit=" . $result_limit . "&sort_by=" . $sort_by, $temp_numbegin, $temp_begin, $temp_num);
    $navigate = $layout->pageBrowser($parameters);
    $sql = $sql . $navigate[0];

    $result = $pdo->query($sql)->fetchAll();

} else {

    $result = $total_rows > 0 ? $domain_list->rows($list_filter_sql, array('sql' => '', 'params' => array()),
        DomainMOD\DomainList::orderSql(array(), $list_columns->sortMap(), $list_columns->defaultOrder()), $list_start,
        $list_length) : array();

}

if ($segid !== 0) {

    $number_of_domains = $segment->getNumberOfDomains($segid);

}

if ($_SESSION['s_system_large_mode'] != '1') {

    $result_limit = '';
    $sort_by = '';

}

// The status names, as the status menu shows them, so the menu and the status chip always match
$status_names = array('0' => _('Expired'), '1' => _('Active'), '2' => _('Pending (Transfer)'),
    '3' => _('Pending (Renewal)'), '4' => _('Pending (Other)'), '5' => _('Pending (Registration)'), '10' => _('Sold'),
    'LIVE' => _('Live Domains') . ' (' . _('Active') . ' / ' . _('Transfers') . ' / ' . _('Pending') . ')',
    'ALL' => DomainMOD\Format::toUpper(_('All')));

/*
 * The filter toolbar's state (DomainMOD\FilterToolbar): every active filter with its plain-text name for its chip, and
 * in Large Mode the results per page and sort order, which every link carries without being chips. Each id filter's
 * name is read with one small query, only when that filter is on, and an id that no longer exists still gets a chip.
 */
$filter_toolbar = new DomainMOD\FilterToolbar('domain-filters', $web_root . '/domains/index.php', $web_root . '/domains/index.php');
$filter_name = function ($sql, $id) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $name = $stmt->fetchColumn();
    $stmt->closeCursor();
    return $name === false ? DomainMOD\FilterToolbar::notFound() : DomainMOD\FilterToolbar::decode($name);
};
if ($segid !== 0) $filter_toolbar->addFilter('segid', $segid, 0, $filter_name("SELECT `name` FROM segments WHERE id = :id", $segid), _('Segment'));
if ($rid !== 0) $filter_toolbar->addFilter('rid', $rid, 0, $filter_name("SELECT `name` FROM registrars WHERE id = :id", $rid), _('Registrar'));
if ($raid !== 0) $filter_toolbar->addFilter('raid', $raid, 0, $filter_name("
    SELECT CONCAT(r.name, ', ', o.name, ' (', ra.username, ')')
    FROM registrar_accounts AS ra, registrars AS r, owners AS o
    WHERE ra.registrar_id = r.id
      AND ra.owner_id = o.id
      AND ra.id = :id", $raid), _('Registrar Account'));
if ($dnsid !== 0) $filter_toolbar->addFilter('dnsid', $dnsid, 0, $filter_name("SELECT `name` FROM dns WHERE id = :id", $dnsid), _('DNS Profile'));
if ($ipid !== 0) $filter_toolbar->addFilter('ipid', $ipid, 0, $filter_name("SELECT CONCAT(`name`, ' (', ip, ')') FROM ip_addresses WHERE id = :id", $ipid), _('IP Address'));
if ($whid !== 0) $filter_toolbar->addFilter('whid', $whid, 0, $filter_name("SELECT `name` FROM hosting WHERE id = :id", $whid), _('Web Hosting Provider'));
if ($pcid !== 0) $filter_toolbar->addFilter('pcid', $pcid, 0, $filter_name("SELECT `name` FROM categories WHERE id = :id", $pcid), _('Category'));
if ($oid !== 0) $filter_toolbar->addFilter('oid', $oid, 0, $filter_name("SELECT `name` FROM owners WHERE id = :id", $oid), _('Owner'));
if ($tld != '') $filter_toolbar->addFilter('tld', $tld, '', '.' . $tld, _('TLD'));
$filter_toolbar->addFilter('is_active', $is_active, 'LIVE', $status_names[$is_active] ?? $is_active, _('Status'));
if ($_SESSION['s_search_for'] != '') {
    $filter_keyword = DomainMOD\FilterToolbar::decode($_SESSION['s_search_for']);
    $filter_toolbar->addFilter('search_for', $filter_keyword, '', '"' . $filter_keyword . '"', _('Keyword Search'));
}
if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
    $filter_toolbar->addFilter('daterange', $_SESSION['s_start_date'] . ' - ' . $_SESSION['s_end_date'], '',
        sprintf(_('Expiring: %1$s to %2$s'), $_SESSION['s_start_date'], $_SESSION['s_end_date']));
}
if ($_SESSION['s_system_large_mode'] == '1') {
    $filter_toolbar->addCarried('result_limit', $result_limit);
    $filter_toolbar->addCarried('sort_by', $sort_by);
}

if ($_SESSION['s_has_domain'] == '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {

    // The no-JavaScript page (expand=1) draws the form as a card under the toolbar instead of in the side panel
    $filter_expanded = ($expand === 1);

    echo $filter_toolbar->toolbar($filter_expanded);
    echo $filter_toolbar->panelTop($filter_expanded);

        echo $form->showFormTop(''); ?>

        <div><?php
                // SEGMENT
                $result_segment = $pdo->query("
                SELECT id, `name`
                FROM segments
                ORDER BY `name` ASC")->fetchAll();

                echo $form->showDropdownTop('segid', _('Segment'), '', '', '');
                echo $form->showDropdownOption('', _('Segment Filter') . ' - ' . DomainMOD\Format::toUpper(_('Off')), 'null');
                foreach ($result_segment as $row_segment) {

                    echo $form->showDropdownOption($row_segment->id, $row_segment->name, $segid);

                }
                echo $filter_toolbar->keepOption('segid', array_column($result_segment, 'id'));
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
                } elseif ($is_active == 'LIVE') {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == 'ALL') {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid !== 0) {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid !== 0) {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid !== 0) {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid !== 0) {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid !== 0) {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($raid !== 0) {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = " . $pdo->quote($tld) . " ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND d.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND " . $domain_search_condition('d.domain', $_SESSION['s_search_for']) . "";
                } else {
                    $search_string = "";
                }
                if ($segid !== 0) {
                    $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $result_registrar = $pdo->query("
                SELECT r.id, r.name
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
                ORDER BY r.name asc")->fetchAll();

                echo $form->showDropdownTop('rid', _('Registrar'), '', '', '');
                echo $form->showDropdownOption('', _('Registrar') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
                foreach ($result_registrar as $row_registrar) {

                    echo $form->showDropdownOption($row_registrar->id, $row_registrar->name, $rid);

                }
                echo $filter_toolbar->keepOption('rid', array_column($result_registrar, 'id'));
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
                } elseif ($is_active == 'LIVE') {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == 'ALL') {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid !== 0) {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid !== 0) {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid !== 0) {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid !== 0) {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid !== 0) {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid !== 0) {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = " . $pdo->quote($tld) . " ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND " . $domain_search_condition('d.domain', $_SESSION['s_search_for']) . "";
                } else {
                    $search_string = "";
                }
                if ($segid !== 0) {
                    $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $result_account = $pdo->query("
                SELECT ra.id AS ra_id, ra.username, r.name AS r_name, o.name AS o_name
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
                ORDER BY r.name asc, o.name asc, ra.username asc")->fetchAll();

                echo $form->showDropdownTop('raid', _('Registrar Account'), '', '', '');
                echo $form->showDropdownOption('', _('Registrar Account') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
                foreach ($result_account as $row_account) {

                    echo $form->showDropdownOption($row_account->ra_id, $row_account->r_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $raid);

                }
                echo $filter_toolbar->keepOption('raid', array_column($result_account, 'ra_id'));
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
                } elseif ($is_active == 'LIVE') {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == 'ALL') {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid !== 0) {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid !== 0) {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($ipid !== 0) {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid !== 0) {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid !== 0) {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid !== 0) {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = " . $pdo->quote($tld) . " ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND d.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND " . $domain_search_condition('d.domain', $_SESSION['s_search_for']) . "";
                } else {
                    $search_string = "";
                }
                if ($segid !== 0) {
                    $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $result_dns = $pdo->query("
                SELECT dns.id, dns.name
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
                ORDER BY dns.name asc")->fetchAll();

                echo $form->showDropdownTop('dnsid', _('DNS Profile'), '', '', '');
                echo $form->showDropdownOption('', _('DNS Profile') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
                foreach ($result_dns as $row_dns) {

                    echo $form->showDropdownOption($row_dns->id, $row_dns->name, $dnsid);

                }
                echo $filter_toolbar->keepOption('dnsid', array_column($result_dns, 'id'));
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
                } elseif ($is_active == 'LIVE') {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == 'ALL') {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid !== 0) {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid !== 0) {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid !== 0) {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($whid !== 0) {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid !== 0) {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid !== 0) {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = " . $pdo->quote($tld) . " ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND d.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND " . $domain_search_condition('d.domain', $_SESSION['s_search_for']) . "";
                } else {
                    $search_string = "";
                }
                if ($segid !== 0) {
                    $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $result_ip = $pdo->query("
                SELECT ip.id, ip.name, ip.ip
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
                ORDER BY ip.name asc")->fetchAll();

                echo $form->showDropdownTop('ipid', _('IP Address'), '', '', '');
                echo $form->showDropdownOption('', _('IP Address') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
                foreach ($result_ip as $row_ip) {

                    echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ')', $ipid);

                }
                echo $filter_toolbar->keepOption('ipid', array_column($result_ip, 'id'));
                echo $form->showDropdownBottom(''); ?>

                <?php // The session keeps the keyword HTML-escaped, so it's decoded once here (the field escapes it again), and Apply sends it back unchanged
                echo $form->showInputText('search_for', _('Keyword Search'), '', DomainMOD\FilterToolbar::decode($_SESSION['s_search_for']), '100', '', '', '', ''); ?><?php
                echo $form->showInputHidden('sort_by', $sort_by);
                echo $form->showInputHidden('begin', '0');
                echo $form->showInputHidden('num', '1');
                echo $form->showInputHidden('numBegin', '1');
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
                } elseif ($is_active == 'LIVE') {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == 'ALL') {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid !== 0) {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid !== 0) {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid !== 0) {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid !== 0) {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($rid !== 0) {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid !== 0) {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = " . $pdo->quote($tld) . " ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND d.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND " . $domain_search_condition('d.domain', $_SESSION['s_search_for']) . "";
                } else {
                    $search_string = "";
                }
                if ($segid !== 0) {
                    $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $result_hosting = $pdo->query("
                SELECT h.id, h.name
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
                ORDER BY h.name asc")->fetchAll();

                echo $form->showDropdownTop('whid', _('Web Hosting Provider'), '', '', '');
                echo $form->showDropdownOption('', _('Web Hosting Provider') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
                foreach ($result_hosting as $row_hosting) {

                    echo $form->showDropdownOption($row_hosting->id, $row_hosting->name, $whid);

                }
                echo $filter_toolbar->keepOption('whid', array_column($result_hosting, 'id'));
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
                } elseif ($is_active == 'LIVE') {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == 'ALL') {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($oid !== 0) {
                    $oid_string = " AND d.owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid !== 0) {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid !== 0) {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid !== 0) {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid !== 0) {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid !== 0) {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = " . $pdo->quote($tld) . " ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND d.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND " . $domain_search_condition('d.domain', $_SESSION['s_search_for']) . "";
                } else {
                    $search_string = "";
                }
                if ($segid !== 0) {
                    $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $result_category = $pdo->query("
                SELECT c.id, c.name
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
                ORDER BY c.name asc")->fetchAll();

                echo $form->showDropdownTop('pcid', _('Category'), '', '', '');
                echo $form->showDropdownOption('', _('Category') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
                foreach ($result_category as $row_category) {

                    echo $form->showDropdownOption($row_category->id, $row_category->name, $pcid);

                }
                echo $filter_toolbar->keepOption('pcid', array_column($result_category, 'id'));
                echo $form->showDropdownBottom('');


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
                } elseif ($is_active == 'LIVE') {
                    $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == 'ALL') {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid !== 0) {
                    $pcid_string = " AND d.cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($dnsid !== 0) {
                    $dnsid_string = " AND d.dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid !== 0) {
                    $ipid_string = " AND d.ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid !== 0) {
                    $whid_string = " AND d.hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid !== 0) {
                    $rid_string = " AND d.registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid !== 0) {
                    $raid_string = " AND d.account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($tld != "") {
                    $tld_string = " AND d.tld = " . $pdo->quote($tld) . " ";
                } else {
                    $tld_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (d.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND d.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND " . $domain_search_condition('d.domain', $_SESSION['s_search_for']) . "";
                } else {
                    $search_string = "";
                }
                if ($segid !== 0) {
                    $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $result_owner = $pdo->query("
                SELECT o.id, o.name
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
                ORDER BY o.name asc")->fetchAll();

                echo $form->showDropdownTop('oid', _('Owner'), '', '', '');
                echo $form->showDropdownOption('', _('Owner') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
                foreach ($result_owner as $row_owner) {

                    echo $form->showDropdownOption($row_owner->id, $row_owner->name, $oid);

                }
                echo $filter_toolbar->keepOption('oid', array_column($result_owner, 'id'));
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
                } elseif ($is_active == 'LIVE') {
                    $is_active_string = " WHERE active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ";
                } elseif ($is_active == 'ALL') {
                    $is_active_string = " WHERE active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                } else {
                    $is_active_string = " WHERE active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ";
                }

                if ($pcid !== 0) {
                    $pcid_string = " AND cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid !== 0) {
                    $oid_string = " AND owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid !== 0) {
                    $dnsid_string = " AND dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid !== 0) {
                    $ipid_string = " AND ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid !== 0) {
                    $whid_string = " AND hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid !== 0) {
                    $rid_string = " AND registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid !== 0) {
                    $raid_string = " AND account_id = '$raid' ";
                } else {
                    $raid_string = "";
                }
                if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                    $range_string = " AND (expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND " . $domain_search_condition('domain', $_SESSION['s_search_for']);
                } else {
                    $search_string = "";
                }
                if ($segid !== 0) {
                    $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $result_tld = $pdo->query("
                SELECT tld, count(*) AS total_tld_count
                FROM domains" .
                    $is_active_string .
                    $pcid_string .
                    $oid_string .
                    $dnsid_string .
                    $ipid_string .
                    $whid_string .
                    $rid_string .
                    $raid_string .
                    $range_string .
                    $search_string .
                    $segment_string . " 
                GROUP BY tld 
                ORDER BY tld asc")->fetchAll();

                echo $form->showDropdownTop('tld', _('TLD'), '', '', '');
                echo $form->showDropdownOption('', _('TLD') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
                foreach ($result_tld as $row_tld) {

                    echo $form->showDropdownOption($row_tld->tld, $row_tld->tld, $tld);

                }
                echo $filter_toolbar->keepOption('tld', array_column($result_tld, 'tld'));
                echo $form->showDropdownBottom('');


                // STATUS
                if ($pcid !== 0) {
                    $pcid_string = " AND cat_id = '$pcid' ";
                } else {
                    $pcid_string = "";
                }
                if ($oid !== 0) {
                    $oid_string = " AND owner_id = '$oid' ";
                } else {
                    $oid_string = "";
                }
                if ($dnsid !== 0) {
                    $dnsid_string = " AND dns_id = '$dnsid' ";
                } else {
                    $dnsid_string = "";
                }
                if ($ipid !== 0) {
                    $ipid_string = " AND ip_id = '$ipid' ";
                } else {
                    $ipid_string = "";
                }
                if ($whid !== 0) {
                    $whid_string = " AND hosting_id = '$whid' ";
                } else {
                    $whid_string = "";
                }
                if ($rid !== 0) {
                    $rid_string = " AND registrar_id = '$rid' ";
                } else {
                    $rid_string = "";
                }
                if ($raid !== 0) {
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
                    $range_string = " AND (expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
                } else {
                    $range_string = "";
                }
                if ($_SESSION['s_search_for'] != "") {
                    $search_string = " AND " . $domain_search_condition('domain', $_SESSION['s_search_for']);
                } else {
                    $search_string = "";
                }
                if ($segid !== 0) {
                    $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') ";
                } else {
                    $segment_string = "";
                }

                $result_active = $pdo->query("
                SELECT active, count(*) AS total_count
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
                ORDER BY active asc")->fetchAll();

                echo $form->showDropdownTop('is_active', _('Status'), '', '', '');
                echo $form->showDropdownOption('LIVE', _('Live Domains') . ' (' . _('Active') . ' / ' . _('Transfers') . ' / ' . _('Pending') . ')', 'LIVE');
                foreach ($result_active as $row_active) {

                    $display_text = $row_active->active;

                    if ($row_active->active == "0") {
                        $display_text = _('Expired');
                    } elseif ($row_active->active == "1") {
                        $display_text = _('Active');
                    } elseif ($row_active->active == "2") {
                        $display_text = _('Pending (Transfer)');
                    } elseif ($row_active->active == "3") {
                        $display_text = _('Pending (Renewal)');
                    } elseif ($row_active->active == "4") {
                        $display_text = _('Pending (Other)');
                    } elseif ($row_active->active == "5") {
                        $display_text = _('Pending (Registration)');
                    } elseif ($row_active->active == "10") {
                        $display_text = _('Sold');
                    }

                    /* TODO: This needs to be fixed, but it's going to be a very big refactoring job, and this is the best temporary band-aid solution. */
                    /*
                     * The problem is that the showDropdownOptionJump method uses the == comparison operator instead of ===,
                     * so 0 technically equals "LIVE", and Expired gets selected in the dropdown menu by default, since it
                     * overrides the actual "LIVE" option.
                     *
                     * It would be easy to just change the == operator to ===, however this may break other instances of
                     * the method, so to fix this properly it's going to take some time.
                     *
                     * This issue also exists on the main SSL page.
                     *
                     * START
                     */
                    if ($row_active->active === 0) $row_active->active = '0';
                    /*
                     * END
                     */

                    echo $form->showDropdownOption($row_active->active, $display_text, $is_active);

                }
                echo $form->showDropdownOption('ALL', DomainMOD\Format::toUpper(_('All')), $is_active);
                echo $filter_toolbar->keepOption('is_active', array_merge(array('LIVE', 'ALL'), array_map('strval', array_column($result_active, 'active'))));
                echo $form->showDropdownBottom('');

                if ($_SESSION['s_system_large_mode'] == '1') {

                    // NUMBER OF DOMAINS TO DISPLAY
                    echo $form->showDropdownTop('result_limit', _('Results Per Page'), '', '', '');

                    if ($_SESSION['s_number_of_domains'] != "10" && $_SESSION['s_number_of_domains'] != "50" && $_SESSION['s_number_of_domains'] != "100" && $_SESSION['s_number_of_domains'] != "500" && $_SESSION['s_number_of_domains'] != "1000" && $_SESSION['s_number_of_domains'] != "1000000") {

                        echo $form->showDropdownOption($result_limit, $_SESSION['s_number_of_domains'], $_SESSION['s_number_of_domains']);

                    }

                    echo $form->showDropdownOption($result_limit, '10', '10');
                    echo $form->showDropdownOption($result_limit, '50', '50');
                    echo $form->showDropdownOption($result_limit, '100', '100');
                    echo $form->showDropdownOption($result_limit, '500', '500');
                    echo $form->showDropdownOption($result_limit, '1,000', '1000');
                    echo $form->showDropdownOption($result_limit, DomainMOD\Format::toUpper(_('All')), '1000000');

                    echo $form->showDropdownBottom('');

                } ?>

                <?php
                echo $form->showInputText('daterange', _('Expiring Between'), '', $daterange, '23', '', '', '', ''); ?>

                <div class="d-flex flex-wrap gap-2 domainmod-filter-actions">
                    <?php echo $form->showSubmitButton(_('Apply Filters'), '', ''); ?>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($web_root . '/domains/index.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo _('Reset Filters'); ?></a>
                </div><?php

                echo $form->showFormBottom(''); ?>
        </div><?php

    echo $filter_toolbar->panelBottom($filter_expanded);

}

if ($segid !== 0) {

    $stmt = $pdo->prepare("
        SELECT count(*)
        FROM segment_data
        WHERE segment_id = :segid
          AND inactive = '1'");
    $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
    $stmt->execute();
    $totalrows_inactive = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT count(*)
        FROM segment_data
        WHERE segment_id = :segid
          AND missing = '1'");
    $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
    $stmt->execute();
    $totalrows_missing = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT count(*)
        FROM segment_data
        WHERE segment_id = :segid
          AND filtered = '1'");
    $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
    $stmt->execute();
    $totalrows_filtered = $stmt->fetchColumn();
    ?>
    <strong><?php echo _('Domains in Segment'); ?>:</strong> <?php echo number_format($number_of_domains); ?><BR><BR>

    <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
        <strong><?php echo _('Matching Domains'); ?>:</strong> <?php echo number_format($total_rows); ?><BR><BR>
    <?php } else { ?>
        <strong><?php echo _('Matching Domains'); ?>:</strong> <?php echo number_format($total_rows); ?><BR><BR>
    <?php } ?>

    <?php if ($totalrows_inactive > 0) { ?>
        <strong><?php echo _('Matching But Inactive Domains'); ?>:</strong> <?php echo number_format($totalrows_inactive); ?> [<a
            target="_blank" href="results.php?type=inactive&segid=<?php echo $segid; ?>"><?php echo DomainMOD\Format::toLower(_('View')); ?></a>]<BR><BR>
    <?php } ?>
    <?php if ($totalrows_filtered > 0) { ?>
        <strong><?php echo _('Matching But Filtered Domains'); ?>:</strong> <?php echo number_format($totalrows_filtered); ?> [<a
            target="_blank" href="results.php?type=filtered&segid=<?php echo $segid; ?>"><?php echo DomainMOD\Format::toLower(_('View')); ?></a>]<BR><BR>
    <?php } ?>
    <?php if ($totalrows_missing > 0) { ?>
        <strong><?php echo _('Missing Domains'); ?>:</strong> <?php echo number_format($totalrows_missing); ?> [<a
            target="_blank" href="results.php?type=missing&segid=<?php echo $segid; ?>"><?php echo DomainMOD\Format::toLower(_('View')); ?></a>]<BR><BR>
    <?php }

}

if ($result) {

    // The page's actions, drawn in the page header (see Layout::renderPageActions())
    $layout->setPageAddAction(_('Add Domain'), 'add.php');
    $layout->addPageAddMenuItem(_('Add Domains Automatically'), $web_root . '/queue/intro.php', 'bi-magic');
    $layout->addPageAddMenuItem(_('CSV Importer'), $web_root . '/domains/csv-importer/', 'bi-filetype-csv');
    $layout->addPageAction(_('Raw List'), $web_root . '/raw.php', 'bi-list-ul', array('target' => '_blank'));
    // The export carries the filters the list is showing (FilterToolbar::exportUrl()), so it matches after Apply Filters too
    $layout->addPageAction(_('Export'), $filter_toolbar->exportUrl(), 'bi-download'); ?>

    <?php if ($segid !== 0) { ?>
        <strong><?php echo _('Total Cost'); ?>:</strong> <?php echo htmlentities($grand_total, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlentities($_SESSION['s_default_currency'], ENT_QUOTES, 'UTF-8'); ?>
        <BR><BR>
    <?php } else { ?>
        <strong><?php echo _('Total Cost'); ?>:</strong> <?php echo htmlentities($grand_total, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlentities($_SESSION['s_default_currency'], ENT_QUOTES, 'UTF-8'); ?><BR>

        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
            <strong><?php echo _('Number of Domains'); ?>:</strong> <?php echo number_format($total_rows); ?><BR><BR>
        <?php } else { ?>
            <strong><?php echo _('Number of Domains'); ?>:</strong> <?php echo number_format($total_rows); ?><BR><BR>
        <?php } ?>

    <?php }

    if ($total_rows && $_SESSION['s_system_large_mode'] != '1') {

        /*
         * Server-side processing (see js/domain-list.js): the columns and cells come from DomainListColumns, as the
         * endpoint's do, and the table carries what the script needs, the endpoint's address with the list's whole state
         * (so another tab's filters can't change this tab's rows), the columns, the counts, the page, and its size.
         */
        $list_state_url = $filter_toolbar->stateUrl();
        $list_query = (string) parse_url($list_state_url, PHP_URL_QUERY);
        $list_data_url = $web_root . '/domains/list-data.php?' . ($list_query !== '' ? $list_query . '&' : '')
            . http_build_query(array('layout' => $list_columns->layoutKey()));
        $list_config = array(
            'url' => $list_data_url,
            'columns' => array_map(function ($column) {
                return array('orderable' => (bool) $column['sort'], 'control' => $column['key'] === 'control');
            }, $list_columns->columns()),
            'order' => $list_columns->defaultOrder(),
            'start' => $list_start,
            'length' => $list_length,
            'sizes' => $list_page_sizes['sizes'],
            'total' => $total_rows,
            'messages' => array(
                'error' => _("That didn't work. Please reload the page and try again."),
                'signin' => _('Your session has ended. Please sign in again.'),
                'signinLink' => _('Sign In'),
                'reload' => _('Your display settings have changed. Reload the page to see them.'),
                'reloadLink' => _('Reload'),
                'close' => _('Close'),
                'paging' => sprintf(_('Pages of %s'), _('Domains')),
            ),
            'signinUrl' => $web_root . '/',
        );
        $today = $time->timeBasic(); // for marking past expiry dates ?>

        <div id="<?php echo $slug; ?>-message"></div>
        <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>" data-domainmod-list="<?php echo htmlspecialchars(json_encode($list_config), ENT_QUOTES, 'UTF-8'); ?>">
            <thead>
            <tr>
                <?php foreach ($list_columns->columns() as $column) {
                    if ($column['key'] === 'control') { ?>
                        <th width="20px"></th>
                    <?php } elseif ($column['key'] === 'domain') { ?>
                        <th class="all"><?php echo $column['title']; ?></th>
                    <?php } else { ?>
                        <th><?php echo $column['title']; ?></th>
                    <?php }
                } ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($result as $row) { ?>
                <tr>
                    <?php foreach ($list_columns->cells($row, $today) as $cell) { ?>
                        <td><?php echo $cell; ?></td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php echo $layout->listPager($total_rows, $list_start, $list_length, $list_state_url, sprintf(_('Pages of %s'), _('Domains'))); ?>
        <BR><?php

    } elseif ($total_rows) {

        // Large Mode keeps its own table, sort links, and paging until it retires (its normal-mode branches below no
        // longer run, and go with it)
        if ($_SESSION['s_system_large_mode'] == '1') {
            require DIR_INC . '/layout/pagination-large-mode.inc.php';
        } ?>

        <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <?php if ($_SESSION['s_system_large_mode'] != '1') { ?>
                    <th width="20px"></th>
                <?php } ?>

                <th class="all">
                    <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                        <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                        ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                        echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date); ?>&segid=<?php
                        echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active); ?>&result_limit=<?php
                        echo urlencode($result_limit); ?>&sort_by=<?php
                        if ($sort_by == "dn_a") {
                            echo "dn_d";
                        } else {
                            echo "dn_a";
                        } ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('Domain'); ?></a>
                    <?php } else { ?>
                            <?php echo _('Domain'); ?>
                    <?php } ?>
                </th>
                <?php if ($_SESSION['s_display_domain_expiry_date'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php
                            echo $dnsid; ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid;
                            ?>&raid=<?php echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php
                            echo urlencode($new_end_date); ?>&segid=<?php echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php
                            echo urlencode($is_active); ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "ed_a") {
                                echo "ed_d";
                            } else {
                                echo "ed_a";
                            }
                            ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('Expiry'); ?></a>
                        <?php } else { ?>
                            <?php echo _('Expiry'); ?>
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_fee'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php
                            echo $dnsid; ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid;
                            ?>&raid=<?php echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php
                            echo urlencode($new_end_date); ?>&segid=<?php echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php
                            echo urlencode($is_active); ?>&result_limit=<?php echo urlencode($result_limit);
                            ?>&sort_by=<?php
                            if ($sort_by == "df_a") {
                                echo "df_d";
                            } else {
                                echo "df_a";
                            }
                            ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('Fee'); ?> (<?php echo $_SESSION['s_default_currency']; ?>)</a>
                        <?php } else { ?>
                                <?php echo _('Fee'); ?> (<?php echo $_SESSION['s_default_currency']; ?>)
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_tld'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                        <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                        ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                        echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php
                        echo urlencode($new_end_date); ?>&segid=<?php echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php
                        echo urlencode($is_active); ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                        if ($sort_by == "tld_a") {
                            echo "tld_d";
                        } else {
                            echo "tld_a";
                        } ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('TLD'); ?></a>
                    <?php } else { ?>
                            <?php echo _('TLD'); ?>
                    <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_registrar'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                            ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                            echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "r_a") {
                                echo "r_d";
                            } else {
                                echo "r_a";
                            } ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('Registrar'); ?></a>
                        <?php } else { ?>
                                <?php echo _('Registrar'); ?>
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_account'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                            ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                            echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "ra_a") {
                                echo "ra_d";
                            } else {
                                echo "ra_a";
                            } ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('Account'); ?></a>
                        <?php } else { ?>
                                <?php echo _('Account'); ?>
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_dns'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                            ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                            echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "dns_a") {
                                echo "dns_d";
                            } else {
                                echo "dns_a";
                            } ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('DNS'); ?></a>
                        <?php } else { ?>
                                <?php echo _('DNS'); ?>
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_ip'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                            ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                            echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "ip_a") {
                                echo "ip_d";
                            } else {
                                echo "ip_a";
                            } ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('IP'); ?></a>
                        <?php } else { ?>
                                <?php echo _('IP'); ?>
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_host'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                            ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                            echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "wh_a") {
                                echo "wh_d";
                            } else {
                                echo "wh_a";
                            } ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('Host'); ?></a>
                        <?php } else { ?>
                                <?php echo _('Host'); ?>
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_category'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                            ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                            echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "pc_a") {
                                echo "pc_d";
                            } else {
                                echo "pc_a";
                            } ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('Category'); ?></a>
                        <?php } else { ?>
                                <?php echo _('Category'); ?>
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_display_domain_owner'] == "1") { ?>
                    <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                        <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>
                            <a href="index.php?pcid=<?php echo $pcid; ?>&oid=<?php echo $oid; ?>&dnsid=<?php echo $dnsid;
                            ?>&ipid=<?php echo $ipid; ?>&whid=<?php echo $whid; ?>&rid=<?php echo $rid; ?>&raid=<?php
                            echo $raid; ?>&start_date=<?php echo urlencode($new_start_date); ?>&end_date=<?php echo urlencode($new_end_date);
                            ?>&segid=<?php echo $segid; ?>&tld=<?php echo urlencode($tld); ?>&is_active=<?php echo urlencode($is_active);
                            ?>&result_limit=<?php echo urlencode($result_limit); ?>&sort_by=<?php
                            if ($sort_by == "o_a") {
                                echo "o_d";
                            } else {
                                echo "o_a";
                            } ?>&from_dropdown=1" class="domainmod-css-color-black"><?php echo _('Owner'); ?></a>
                        <?php } else { ?>
                                <?php echo _('Owner'); ?>
                        <?php } ?>
                    </th>
                <?php } ?>
                <?php if ($_SESSION['s_cdf_data']) {

                    foreach ($_SESSION['s_cdf_data'] as $field) {

                        if ($field['value'] == '1' && $field['type_id'] != '3') { // Don't show column for Text Areas ?>

                            <th<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>

                                <?php if ($_SESSION['s_system_large_mode'] == '1') { ?>

                                    <span class="domainmod-css-color-black"><?php echo $field['name']; ?></span>

                                <?php } else { ?>

                                    <?php echo $field['name']; ?>

                                <?php } ?>

                            </th><?php

                        }

                    }

                } ?>
            </tr>
            </thead>
            <tbody>
            <?php $today = $time->timeBasic(); // for marking past expiry dates below ?>
            <?php foreach ($result as $row) { ?>
                <tr>

                    <?php if ($_SESSION['s_system_large_mode'] != '1') { ?>
                        <td></td>
                    <?php } ?>

                    <td>
                        <?php if ($row->active == "0") {
                            echo $layout->statusBadge('x', _('Inactive Domain'), 'secondary', 'bi-pause-circle') . "&nbsp;";
                        } elseif ($row->active == "2") {
                            echo $layout->statusBadge(_('Pending Transfer Characters'), _('Pending (Transfer)'), 'warning', 'bi-arrow-left-right') . "&nbsp;";
                        } elseif ($row->active == "3") {
                            echo $layout->statusBadge(_('Pending Renewal Characters'), _('Pending (Renewal)'), 'warning', 'bi-arrow-repeat') . "&nbsp;";
                        } elseif ($row->active == "4") {
                            echo $layout->statusBadge(_('Pending Other Characters'), _('Pending (Other)'), 'warning', 'bi-three-dots') . "&nbsp;";
                        } elseif ($row->active == "5") {
                            echo $layout->statusBadge(_('Pending Registration Characters'), _('Pending (Registration)'), 'warning', 'bi-plus-circle') . "&nbsp;";
                        } elseif ($row->active == "10") {
                            echo $layout->statusBadge(_('Pending Sold Characters'), _('Sold'), 'success', 'bi-tag') . "&nbsp;";
                        }
                        ?>
                        <a href="edit.php?did=<?php echo $row->id; ?>"><?php echo $row->domain; ?></a><?php if ($row->privacy == "1") {
                            echo "&nbsp;" . $layout->statusBadge(_('Private WHOIS Characters'), _('Private WHOIS Registration'), 'muted', 'bi-shield-lock-fill');
                        } ?>
                    </td>
                    <?php if ($_SESSION['s_display_domain_expiry_date'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                            <a href="edit.php?did=<?php echo $row->id; ?>"<?php if (!in_array($row->active, array('0', '10')) && $row->expiry_date < $today) echo ' class="domainmod-css-expired" data-bs-toggle="tooltip" data-bs-title="' . htmlspecialchars(_('Expired'), ENT_QUOTES, 'UTF-8') . '" aria-label="' . $row->expiry_date . ', ' . htmlspecialchars(_('Expired'), ENT_QUOTES, 'UTF-8') . '"'; ?>><?php echo $row->expiry_date; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_fee'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                            <a href="../assets/edit/registrar-fee.php?rid=<?php echo $row->r_id; ?>&fee_id=<?php echo $row->f_id; ?>">
                                <?php
                                $converted_total_cost = $row->total_cost * $row->conversion;
                                $temp_output_amount = $currency->format($converted_total_cost,
                                    $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
                                    $_SESSION['s_default_currency_symbol_space']);
                                echo htmlentities($temp_output_amount, ENT_QUOTES, 'UTF-8');
                                ?>
                            </a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_tld'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                            <a href="../assets/edit/registrar-fee.php?rid=<?php echo $row->r_id; ?>&fee_id=<?php echo $row->f_id; ?>"><bdi dir="ltr">.<?php echo $row->tld; ?></bdi></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_registrar'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                            <a href="../assets/edit/registrar.php?rid=<?php echo $row->r_id; ?>"><?php echo $row->registrar_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_account'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                            <a href="../assets/edit/registrar.php?rid=<?php echo $row->r_id; ?>"><?php echo $row->registrar_name; ?></a>,
                            <a href="../assets/edit/account-owner.php?oid=<?php echo $row->o_id; ?>"><?php echo $row->owner_name; ?></a>
                            (<a href="../assets/edit/registrar-account.php?raid=<?php echo $row->ra_id; ?>"><?php echo substr($row->username, 0, 15); ?><?php if (strlen($row->username) >= 16) echo "..."; ?></a>)
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_dns'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                            <a href="../assets/edit/dns.php?dnsid=<?php echo $row->dnsid; ?>"><?php echo $row->dns_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_ip'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                            <a href="../assets/edit/ip-address.php?ipid=<?php echo $row->ipid; ?>"><?php echo $row->ip_name; ?>
                                (<?php echo $row->ip; ?>)</a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_host'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                            <a href="../assets/edit/host.php?whid=<?php echo $row->whid; ?>"><?php echo $row->wh_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_category'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                            <a href="../assets/edit/category.php?pcid=<?php echo $row->pcid; ?>"><?php echo $row->category_name; ?></a>
                        </td>
                    <?php } ?>
                    <?php if ($_SESSION['s_display_domain_owner'] == "1") { ?>
                        <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>>
                            <a href="../assets/edit/account-owner.php?oid=<?php echo $row->o_id; ?>"><?php echo $row->owner_name; ?></a>
                        </td>
                    <?php } ?>

                    <?php if ($_SESSION['s_cdf_data']) {

                        foreach ($_SESSION['s_cdf_data'] as $field) {

                            if ($field['value'] == '1' && $field['type_id'] != '3') { // Don't show data for Text Areas ?>

                            <td<?php if ($_SESSION['s_system_large_mode'] == '1') { echo ' class="domainmod-css-padding-left"'; } ?>><?php

                                if ($field['type_id'] == '1') { // Check Box

                                    echo ($row->{$field['field']} == '1' ? 'Yes' : 'No');

                                } elseif ($field['type_id'] == '2') { // Text

                                    echo $layout->customFieldText($row->{$field['field']});

                                } elseif ($field['type_id'] == '4') { // Date

                                    if ($row->{$field['field']} == '1970-01-01') {

                                        echo '';

                                    } else {

                                        echo $layout->customFieldText($row->{$field['field']});

                                    }

                                } elseif ($field['type_id'] == '5') { // Time Stamp

                                    if ($row->{$field['field']} == '1970-01-01 00:00:00') {

                                        echo '';

                                    } else {

                                        echo $layout->customFieldText($row->{$field['field']});

                                    }

                                } elseif ($field['type_id'] == '6') { // URL

                                    if ($row->{$field['field']} != '') {

                                        echo $layout->customFieldLink($row->{$field['field']});

                                    }

                                } ?>

                            </td><?php

                            }

                        }

                    } ?>

                </tr>
            <?php } ?>
        </tbody>
        </table><BR><?php

    }

    if ($_SESSION['s_system_large_mode'] == '1') {

        require DIR_INC . '/layout/pagination-large-mode.inc.php';
    }

} else {

    $ready_for_domains = $ready_for_domains ?? 0;

    // With no domains at all, the "No domains yet" empty state above already offers the ways to add them
    if ($ready_for_domains === 1 && $_SESSION['s_has_domain'] == '1') {

        $layout->setPageAddAction(_('Add Domain'), 'add.php');
        $layout->addPageAddMenuItem(_('Add Domains Automatically'), $web_root . '/queue/intro.php', 'bi-magic');
        $layout->addPageAddMenuItem(_('CSV Importer'), $web_root . '/domains/csv-importer/', 'bi-filetype-csv');

    }

    if ($_SESSION['s_has_domain'] == '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {

        // Domains exist but none are listed. Which empty state shows depends on why (see the empty states plan).
        $domain_filters_active = $segid !== 0 || $pcid !== 0 || $oid !== 0 || $dnsid !== 0 || $ipid !== 0 || $whid !== 0
            || $rid !== 0 || $raid !== 0 || $tld != '' || $list_has_range || $list_has_search || $is_active != 'LIVE';

        // The list only shows domains that have a fee (it joins the fees to work out costs), so these counts leave
        // the fees out, to tell "nothing matches" apart from "hidden until they have a fee"
        $domains_without_fee_join = "
            FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, categories AS cat, dns AS dns, ip_addresses AS ip, hosting AS h, domain_field_data AS dfd
            WHERE d.account_id = ra.id
              AND ra.registrar_id = r.id
              AND ra.owner_id = o.id
              AND d.cat_id = cat.id
              AND d.dns_id = dns.id
              AND d.ip_id = ip.id
              AND d.hosting_id = h.id
              AND d.id = dfd.domain_id";

        if ($_SESSION['s_system_large_mode'] == '1' && $total_rows > 0) {

            // A bookmarked page beyond the end of the list. The link back writes every filter out, including the
            // keyword and date range kept in the session, since Large Mode's own paging links rely on numBegin to keep
            // them.
            $first_page_query = array('pcid' => $pcid, 'oid' => $oid, 'dnsid' => $dnsid, 'ipid' => $ipid, 'whid' => $whid,
                'rid' => $rid, 'raid' => $raid, 'tld' => $tld, 'segid' => $segid, 'is_active' => $is_active,
                'result_limit' => $result_limit, 'sort_by' => $sort_by, 'search_for' => $_SESSION['s_search_for']);
            if ($list_has_range) $first_page_query['daterange'] = $_SESSION['s_start_date'] . ' - ' . $_SESSION['s_end_date'];
            echo $layout->emptyState('bi-arrow-left-circle', _('That page is past the end of the list'), _('There are fewer domains than when this page was linked.'), '', '', '/domains/index.php?' . http_build_query($first_page_query), _('Go to the first page'));

        } elseif (!$domain_filters_active) {

            $live_domains = (int) $pdo->query("SELECT count(*) FROM domains WHERE active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')")->fetchColumn();

            if ($live_domains === 0) {

                echo $layout->emptyState('bi-archive', _('No live domains'), _('Your domains are all expired, sold, or inactive.'), '', '', '/domains/index.php?is_active=ALL', _('Show all domains'));

            } else {

                echo $layout->emptyState('bi-cash-coin', _('Some domains are hidden until they have a fee'), _("Domains without a fee can't be costed, so they don't appear in the list yet."), '', '', '/assets/registrar-fees-missing.php', _('Add the missing fees'));

            }

        } else {

            $filtered_domains = (int) $pdo->query("SELECT count(*)" . $domains_without_fee_join . $list_filter_sql)->fetchColumn();

            if ($filtered_domains > 0) {

                echo $layout->emptyState('bi-cash-coin', _('Some domains are hidden until they have a fee'), _("Domains without a fee can't be costed, so they don't appear in the list yet."), '', '', '/assets/registrar-fees-missing.php', _('Add the missing fees'));

            } else {

                echo $layout->emptyState('bi-search', _('No domains match your filters'), _('Try changing or clearing the filters.'), '', '', '/domains/index.php', _('Clear filters'));

            }

        }

    }

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php';  //@formatter:on ?>
<?php require_once DIR_INC . '/layout/date-range-picker-footer.inc.php'; ?>
<?php if ($_SESSION['s_system_large_mode'] != '1') { ?>
<script src="<?php echo $layout->assetUrl('js/domain-list.js'); ?>"></script>
<?php } ?>
</body>
</html>
