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
// The Registry Status filter (DomainMOD\DomainStatus), only once the 4.27.0 database update has added its columns
$lock = DomainMOD\DomainList::normalizeLock($_REQUEST['lock'] ?? '');

list($new_start_date, $new_end_date) = $date->splitAndCheckRange($daterange);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $from_dropdown = 0;

    $date = new DomainMOD\Date();

    if ($new_start_date > $new_end_date) {

        $_SESSION['s_message_danger'] .= _('The date range is invalid.') . '<BR>';

    }

}

if ($export_data !== 1) {

    if ($from_dropdown !== 1) {

        if ($search_for != "") {

            $_SESSION['s_search_for'] = $search_for;

        } else {

            $_SESSION['s_search_for'] = "";

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

if (($_SESSION['s_start_date'] ?? '') == '') $_SESSION['s_start_date'] = 'YYYY-MM-DD';
if (($_SESSION['s_end_date'] ?? '') == '') $_SESSION['s_end_date'] = 'YYYY-MM-DD';

// A range saved by an older version may not have been checked, so anything that isn't a real date range is dropped.
if (!$date->checkDateFormat($_SESSION['s_start_date']) || !$date->checkDateFormat($_SESSION['s_end_date'])) {

    $_SESSION['s_start_date'] = 'YYYY-MM-DD';
    $_SESSION['s_end_date'] = 'YYYY-MM-DD';

}

// The keyword and date range the list is filtered by. An export reads them from its own request when its link carries
// them (FilterToolbar::exportUrl() always does), without touching the session, so another tab's filters can't leak into
// it. An older export link without them falls back to the session, as before.
$filter_search_for = $_SESSION['s_search_for'] ?? '';
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

// The filter box shows the range the list uses, so a range that was ignored (one that isn't a real date range)
// leaves it empty rather than showing text the list didn't apply.
$daterange = $filter_start_date != 'YYYY-MM-DD' ? $filter_start_date . ' - ' . $filter_end_date : '';

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

// Where an export that can't be made sends the user back: the list, with the export's filters. When an older export
// link leaves out the keyword or the date range, the export uses the saved one, so the saved value is added here for
// the list to keep. from_dropdown is left out, since it would make the list ignore them.
$back_filters = array_diff_key($_GET, array_flip(array('export_data', 'dt_search', 'dt_layout', 'from_dropdown')));
if (!array_key_exists('search_for', $back_filters)) $back_filters['search_for'] = htmlspecialchars_decode($filter_search_for, ENT_QUOTES);
if (!array_key_exists('daterange', $back_filters)) $back_filters['daterange'] = $daterange;

// A segment that no longer exists (a link or tab from before it was deleted) is dropped, as if none were chosen. Its
// stored domain list is read once, here, and handed to the filters, so it can't change between this check and them.
$segment_list = null;
if ($segid !== 0) {

    $segment_row = DomainMOD\Segment::storedRow($pdo, $segid);

    if ($segment_row === false) {

        $_SESSION['s_message_danger'] .= _("That segment no longer exists, so the list isn't filtered by it.") . '<BR>';
        $segid = 0;

        // An export goes back to the list without the segment, with the message, rather than downloading a wider list
        if ($export_data === 1) {

            header('Location: index.php?' . http_build_query(array_diff_key($back_filters, array('segid' => true))));
            exit;

        }

    } else {

        $segment_list = $segment_row->segment;

    }

}

/*
 * The list's custom fields, read once, so the SELECT, the columns, Export, and the custom field filters all describe
 * the same fields. The custom field filters come from the request only, never the session: the panel's form on a POST,
 * otherwise the address, never the two mixed (the form posts to an address that can carry older ones), and for an
 * export only its own address. A request whose fields have changed since its address or form was made (see
 * CustomFieldFilter::requestOk()) loses them, and an export with custom field filters must carry their key (cfk), so
 * an old tab never turns a replaced field's old value into a new filter.
 */
$dfd_fields = $customField->getFilterFields('domain_fields');
$cf = new DomainMOD\CustomFieldFilter($dfd_fields, 'dfd');
$cf_request = ($_SERVER['REQUEST_METHOD'] == 'POST' && $export_data !== 1) ? $_POST : $_GET;
$cf_changed_message = _("A custom field in your filters was changed or deleted, so the list isn't filtered by your custom field filters.");
if (!$cf->requestOk($cf_request) || ($export_data === 1 && DomainMOD\CustomFieldFilter::hasFilterKeys($cf_request)
        && !isset($cf_request['cfk']))) {

    $_SESSION['s_message_danger'] .= $cf_changed_message . '<BR>';

    // An export goes back to the list without them, with the message, rather than exporting an old filter
    if ($export_data === 1) {

        header('Location: index.php?' . http_build_query(array_diff_key(DomainMOD\CustomFieldFilter::withoutFilterKeys($back_filters),
            array('cff' => true, 'cfk' => true))));
        exit;

    }

    $cf_request = DomainMOD\CustomFieldFilter::withoutFilterKeys($cf_request);

}
$cf_state = $cf->normalize($cf_request, $cf_dropped);
if ($cf_dropped) {
    $_SESSION['s_message_danger'] .= _("Some custom field filters were left out because there are too many or they're too long. Use fewer or shorter ones.") . '<BR>';
}
$cf_filter = $cf->conditions($cf_state);
$cf_keys = DomainMOD\CustomFieldFilter::requestKeys($cf_state);
// The custom field filters' keys for the addresses the page makes, so the next request can tell whether their fields
// have changed since (see CustomFieldFilter::requestOk())
$cf_carry = $cf_state ? array('cff' => $cf->fieldsKey()) : array();

// The list's filters as SQL, from the same class as the list's data endpoint (domains/list-data.php), so the first
// page, the pages fetched afterwards, the counts, Export, and the Raw List all agree
$domain_list = new DomainMOD\DomainList($pdo, $_SESSION['s_user_id'], $dfd_fields);
// Whether Export adds the registry status columns (DomainMOD\DomainStatus), which come with the 4.27.0 database update
$export_status = $domain_list->hasStatusColumns();
$list_state = array('pcid' => $pcid, 'oid' => $oid, 'dnsid' => $dnsid, 'ipid' => $ipid, 'whid' => $whid, 'rid' => $rid,
    'raid' => $raid, 'segid' => $segid, 'tld' => $tld, 'is_active' => $is_active, 'lock' => $lock);
$filter_parts = $domain_list->filterParts($list_state, $filter_search_for, $filter_start_date, $filter_end_date,
    $segment_list);

// The segment's "filtered" flags follow the whole filtered list, not the page shown (see DomainList::writeSegmentFlags()).
// They're written first, so if the segment has to be dropped, the filters are built again without it before anything
// else uses them.
if ($segid !== 0) {

    try {

        $segment_flags = $domain_list->writeSegmentFlags($segid,
            DomainMOD\DomainList::filterSql(array('segid_string' => '') + $filter_parts) . $cf_filter['sql'], $segment_list,
            $cf_filter['params'], $cf_state ? $cf->fieldsKey() : null);

    } catch (Throwable $e) {

        // The page and Export work out their own counts, so only domains/results.php is left with the earlier flags
        $segment_flags = 'failed';
        (new DomainMOD\Log('/domains/index.php'))->error('Unable to update the segment flags',
            array('Segment ID' => $segid, 'Error' => $e->getMessage()));

    }

    // The custom fields changed while the flags were being written, so the page loads once more without the custom
    // field filters (an export goes back to the list the same way), and the next load writes the flags without them
    if ($segment_flags === 'fields_changed') {

        $_SESSION['s_message_danger'] .= $cf_changed_message . '<BR>';
        if ($export_data === 1) {

            header('Location: index.php?' . http_build_query(array_diff_key(DomainMOD\CustomFieldFilter::withoutFilterKeys($back_filters),
                array('cff' => true, 'cfk' => true))));

        } else {

            header('Location: index.php?' . http_build_query(array_merge($list_state,
                array_intersect_key($_GET, array('start' => true, 'length' => true)),
                array('search_for' => htmlspecialchars_decode($filter_search_for, ENT_QUOTES), 'daterange' => $daterange,
                    'expand' => $expand))));

        }
        exit;

    }

    if ($segment_flags === 'changed') {

        if ($export_data === 1) {

            // The filters were built from the segment's old list, so the export goes back to the list to load it again
            $_SESSION['s_message_danger'] .= _("The export couldn't be created. Please try again.") . '<BR>';
            header('Location: index.php?' . http_build_query($back_filters));
            exit;

        } elseif (($_GET['segreload'] ?? '') !== '1') {

            // The segment was edited while the page was loading, so the page loads once more with its new list
            header('Location: index.php?' . http_build_query(array_merge($list_state,
                array_intersect_key($_GET, array('start' => true, 'length' => true)),
                array('search_for' => htmlspecialchars_decode($filter_search_for, ENT_QUOTES), 'daterange' => $daterange,
                    'expand' => $expand), $cf_keys, $cf_carry, array('segreload' => 1))));
            exit;

        } else {

            // It changed again, so the list isn't filtered by it, as if it hadn't been chosen
            $_SESSION['s_message_danger'] .= _("That segment changed while the list was loading, so the list isn't filtered by it. Please choose it again.") . '<BR>';
            $segid = 0;
            $segment_list = null;
            $list_state['segid'] = 0;
            $filter_parts = $domain_list->filterParts($list_state, $filter_search_for, $filter_start_date,
                $filter_end_date);

        }

    }

}
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
$lock_string = $filter_parts['lock_string'];

// Export and the Raw List come out in the list's default order (DomainList::EXPORT_ORDER_SQL). The Raw List also keeps
// the query without its order, so it can add the table's search box when its link carries one, and without its
// columns, so it can read only the names, in chunks (see raw.php).
$raw_list_from_sql = $domain_list->fromSql() . "
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
          $lock_string" . $cf_filter['sql'];
$raw_list_sql = $domain_list->selectSql() . $raw_list_from_sql;
$sql = $raw_list_sql . DomainMOD\DomainList::EXPORT_ORDER_SQL;
$_SESSION['s_raw_list_type'] = 'domains';
$_SESSION['s_raw_list_sql'] = $sql;
$_SESSION['s_raw_list_unordered_sql'] = $raw_list_sql;
$_SESSION['s_raw_list_from_sql'] = $raw_list_from_sql;
// The custom field filters' bound values, and their keys, for raw.php's checks (see raw.php)
$_SESSION['s_raw_list_params'] = $cf_filter['params'];
$_SESSION['s_raw_list_filter_keys'] = array_keys($cf_keys);

// The list's filters as they are now, kept for the empty state's counts, since the filter menus below reuse these names
$list_filter_sql = $is_active_string . $segid_string . $pcid_string . $oid_string . $dnsid_string . $ipid_string
    . $whid_string . $rid_string . $raid_string . $range_string . $tld_string . $search_string . $lock_string
    . $cf_filter['sql'];
$list_filter_params = $cf_filter['params'];
$list_has_range = $range_string != '';
$list_has_search = $search_string != '';

// How many domains the filters match, and their total cost (the same joins and filters as the list). Export reads its
// own inside its snapshot, so they aren't read twice.
if ($export_data !== 1) {

    $total_rows = $domain_list->count($list_filter_sql, $list_filter_params);

    $stmt = $pdo->prepare("SELECT SUM(d.total_cost * cc.conversion)" . $domain_list->fromSql() . $list_filter_sql);
    $stmt->execute($list_filter_params);
    $grand_total = $stmt->fetchColumn();
    $stmt->closeCursor();

    $grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
        $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

}

if ($export_data == 1) {

    // Where an export that can't be made sends the user back (see $back_filters)
    $back_url = 'index.php?' . http_build_query($back_filters);

    // A streamed export (see Export::begin()), read and written in chunks, so PHP holds only a chunk of the rows at once
    $export = new DomainMOD\Export();
    $export->begin($back_url);

    // The table's search box, when the link carries it (js/server-list.js adds it), so the export holds the rows the
    // table shows. An older link without it exports what the filters match, as before.
    $table_search_for = $_GET['dt_search'] ?? '';
    $table_search = $domain_list->tableSearchSql($table_search_for, $_GET['dt_layout'] ?? '',
        DomainMOD\DomainListColumns::sessionCopy($_SESSION));

    // The display settings changed after the table was drawn, so its search can't be matched: back to the list, with
    // its filters, to search again
    if ($table_search['stale']) {

        $_SESSION['s_message_danger'] .= _('Your display settings have changed since the list was loaded. Please search the list again.') . '<BR>';
        header('Location: ' . $back_url);
        exit;

    }

    try {

        // The header block's filters, read before the snapshot below (but inside the try, so an error still goes to
        // Export::fail()), since the name lookups write to the log when a name is missing, and nothing can write inside the
        // snapshot
        $search_filter_rows = array();

        $row_contents = array('[' . _('Search Filters') . ']');
        $search_filter_rows[] = $row_contents;

        if ($filter_search_for != "") {

            $row_contents = array(
                _('Keyword Search') . ':',
                $filter_search_for
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($table_search['sql'] !== '') {

            $row_contents = array(
                _('Table Search') . ':',
                $export->protectFormula($table_search_for)
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($rid > 0) {

            $temp_registrar_name = $assets->getRegistrar($rid);

            $row_contents = array(
                _('Registrar') . ':',
                $temp_registrar_name
            );
            $search_filter_rows[] = $row_contents;

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
            $result_account = $stmt->fetch();
            $stmt->closeCursor();

            if ($result_account) {

                $row_contents = array(
                    _('Registrar Account') . ':',
                    $result_account->registrar_name . " - " . $result_account->owner_name . " - " . $result_account->username
                );
                $search_filter_rows[] = $row_contents;

            }

        }

        if ($dnsid > 0) {

            $temp_dns_name = $assets->getDnsName($dnsid);

            $row_contents = array(
                _('DNS Profile') . ':',
                $temp_dns_name
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($ipid > 0) {

            list($temp_ip, $temp_ip_name) = $assets->getIpAndName($ipid);

            $row_contents = array(
                _('IP Address') . ':',
                $temp_ip_name . ' (' . $temp_ip . ')'
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($whid > 0) {

            $temp_hosting_name = $assets->getHost($whid);

            $row_contents = array(
                _('Web Host') . ':',
                $temp_hosting_name
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($pcid > 0) {

            $temp_category = $assets->getCat($pcid);

            $row_contents = array(
                _('Category') . ':',
                $temp_category
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($oid > 0) {

            $temp_owner = $assets->getOwner($oid);

            $row_contents = array(
                _('Owner') . ':',
                $temp_owner
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($lock !== '') {

            $row_contents = array(
                _('Registry Status') . ':',
                DomainMOD\DomainStatus::filterChoices()[$lock]
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($filter_start_date != '' && $filter_start_date != 'YYYY-MM-DD') {

            $row_contents = array(
                _('Expiry Date Range') . ':',
                $filter_start_date  . " to " . $filter_end_date
            );
            $search_filter_rows[] = $row_contents;

        }

        // The custom field filters, by field name, as their chips show them (names and values are the user's own text)
        foreach ($cf->describe($cf_state) as $cf_described) {

            $row_contents = array(
                $export->protectFormula($cf_described['name'] . ':'),
                $export->protectFormula($cf_described['label'])
            );
            $search_filter_rows[] = $row_contents;

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
        $search_filter_rows[] = $row_contents;

        // Everything from here to the last row reads one snapshot of the database, so the header's numbers and the
        // rows always agree, however the data changes in the meantime
        $export->startSnapshot($domain_list);

        // The custom fields as the snapshot has them, which must be the ones the page read (the headings, the cells,
        // and the filters all come from that read), then the full row query once with LIMIT 0, the first query of the
        // custom field data, so a column that's gone fails here, before the file starts, rather than in a chunk
        if (!$cf->sameFields(DomainMOD\CustomField::readFilterFields($pdo, 'domain_fields'))) {

            throw new RuntimeException('The custom fields changed while the export was being made');

        }
        $domain_list->exportPreflight($list_filter_sql . $table_search['sql'], $table_search['params'] + $list_filter_params);

        // With a table search, the export's count and total cost are for the rows it holds, and the domains the search
        // leaves out count as filtered in a segment's results
        $table_search_excluded = 0;
        if ($table_search['sql'] !== '') {

            list($list_rows, $total_rows) = $domain_list->counts($list_filter_sql, $table_search, $list_filter_params);
            $table_search_excluded = $list_rows - $total_rows;

        } else {

            $total_rows = $domain_list->count($list_filter_sql, $list_filter_params);

        }

        $stmt = $pdo->prepare("SELECT SUM(d.total_cost * cc.conversion)" . $domain_list->fromSql() . $list_filter_sql
            . $table_search['sql']);
        $stmt->execute($table_search['params'] + $list_filter_params);
        $grand_total = $currency->format($stmt->fetchColumn(), $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);
        $stmt->closeCursor();

        if ($segid !== 0) {

            // The segment as the snapshot has it, which must be the one the filters were built from
            $snapshot_segment = DomainMOD\Segment::storedRow($pdo, $segid);
            if ($snapshot_segment === false || $snapshot_segment->segment !== $segment_list) {

                throw new RuntimeException('The segment changed while the export was being made');

            }

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

            // Worked out from this export's own filters, not read back from the segment's stored flags
            $totalrows_filtered = $domain_list->segmentFilteredCount($segid, $list_filter_sql, $list_filter_params)
                + $table_search_excluded;

            $number_of_domains = $snapshot_segment->number_of_domains;
            $temp_name = $snapshot_segment->name;

        }

        // The custom fields' columns, headings, and types all come from the page's one read of them (checked against
        // the snapshot above)
        $dfd_types = array_column($dfd_fields, 'type_id', 'field_name');
        $dfd_columns_array = array_column($dfd_fields, 'field_name');

        // The creation types and users, read once rather than once for each domain
        $creation_type_names = DomainMOD\Export::creationTypeNames($pdo);
        $user_names = DomainMOD\Export::userNames($pdo);

        $export_file = $export->openStream(_('domain_results'), strtotime($time->stamp()));

        $row_contents = array(_('Domain Search Results Export'));
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        if ($segid == 0) {

            $row_contents = array(
                _('Number of Domains') . ':',
                $total_rows
            );
            $export->writeRow($export_file, $row_contents);

            $row_contents = array(
                _('Total Cost') . ':',
                $grand_total,
                $_SESSION['s_default_currency']
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

            $row_contents = array('[' . _('Segment Results') . ']');
            $export->writeRow($export_file, $row_contents);

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

        foreach ($search_filter_rows as $row_contents) {

            $export->writeRow($export_file, $row_contents);

        }

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
        // The registry status columns (DomainMOD\DomainStatus) come with the 4.27.0 database update
        if ($export_status) {
            foreach (DomainMOD\DomainStatus::exportHeadings() as $heading) $row_contents[$count++] = $heading;
        }
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

        // The headings are the fields' names, the user's own text
        foreach ($dfd_fields as $row_field) {

            $row_contents[$count++] = $export->protectFormula($row_field['name']);

        }

        $export->writeRow($export_file, $row_contents);

        $export->chunkWritten();

        foreach ($domain_list->exportChunks($list_filter_sql . $table_search['sql'], $table_search['params'] + $list_filter_params) as $result) {

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
                    $autorenew_status = _('Auto-Renewal');
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

                $creation_type = DomainMOD\Export::creationTypeName($creation_type_names, $row->creation_type_id);

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
                if ($export_status) {
                    foreach (DomainMOD\DomainStatus::exportValues($row) as $value) $row_contents[$count++] = $value;
                }
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
                    $row_contents[$count++] = DomainMOD\Export::userName($user_names, $row->created_by);
                }
                $row_contents[$count++] = $time->toUserTimezone($row->insert_time);
                $row_contents[$count++] = $time->toUserTimezone($row->update_time);
                $row_contents[$count++] = '';

                if ($dfd_columns_array != "") {

                    foreach ($dfd_columns_array as $column) {

                        // An empty Date or Time Stamp field is blank, as the list shows it
                        if (in_array((string) ($dfd_types[$column] ?? ''), array('4', '5'), true) && DomainMOD\Time::isUnset($row->{$column})) {

                            $row_contents[$count++] = '';

                        } else {

                            $row_contents[$count++] = $row->{$column};

                        }

                    }

                }

                $export->writeDataRow($export_file, $row_contents);

            }

            $export->chunkWritten();

        }

        $export->finish($total_rows);

    } catch (Throwable $e) {

        $export->fail($e);

    }

    // finish() and fail() both end the request
    exit;

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

// Add Domains Automatically can add the registrar and its account itself (RDAP import), and the Import Queue menu item
// is hidden while the queues are empty, so the two setup messages below are the way in for users who can add domains.
// It's only offered once the database has the RDAP source, as queue/intro.php checks.
$automatic_link = '';
if (($_SESSION['s_read_only'] ?? '') != '1' && ($_SESSION['s_has_registrar'] != '1' || $_SESSION['s_has_registrar_account'] != '1')
    && (new DomainMOD\DomainQueue())->getRdapApiRegistrarId() > 0) {
    $automatic_link = ' ' . sprintf(_('Or use %sAdd Domains Automatically%s, which can add the registrar and account for you.'), '<a href="' . htmlspecialchars($web_root . '/queue/intro.php', ENT_QUOTES, 'UTF-8') . '">', '</a>');
}

if ($_SESSION['s_has_registrar'] != '1') {

    echo $layout->emptyState('bi-building', _('Add a registrar first'), _('Domains belong to a registrar account, so start by adding the registrar you use.') . $automatic_link . ' ' . $first_run_link, '/assets/add/registrar.php', _('Add Registrar'), '/domains/csv-importer/', _('Import from a CSV file instead'), 2, true, true);

}

if ($_SESSION['s_has_registrar_account'] != '1' && $_SESSION['s_has_registrar'] == '1') {

    echo $layout->emptyState('bi-person-badge', _('Add a registrar account next'), _('Add the account you hold at that registrar.') . $automatic_link . ' ' . $first_run_link, '/assets/add/registrar-account.php', _('Add Registrar Account'));

}

if ($_SESSION['s_has_domain'] != '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {

    echo $layout->emptyState('bi-globe2', _('No domains yet'), sprintf(_('Add your first domain, or let %s import them from your registrar.'), SOFTWARE_TITLE) . ' ' . sprintf(_('See %sAdding Domains%s in the User Guide for more ways.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/adding-domains/">', '</a>'), '/domains/add.php', _('Add Domain'), '/queue/intro.php', _('Add Domains Automatically'), 2, true, true);
    $ready_for_domains = 1;

} elseif ($_SESSION['s_has_domain'] == '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {

    $ready_for_domains = 1;

}

/*
 * Server-side processing: the page prints one page of rows, in the default order, and DataTables fetches the others
 * from domains/list-data.php (see js/server-list.js). The page and its size come from the address (the no-JavaScript
 * pager's links), checked as the endpoint checks them, and a page past the end (an old link, after domains were
 * deleted) moves to the last one. A page always starts on a multiple of its size, as DataTables' own paging does.
 */
$list_columns = new DomainMOD\DomainListColumns(DomainMOD\DomainListColumns::sessionCopy($_SESSION), $dfd_fields);
$list_page_sizes = DomainMOD\DomainList::pageSizes($_SESSION['s_number_of_domains']);
$list_length = DomainMOD\DomainList::checkedLength($_GET['length'] ?? '', $list_page_sizes);
$list_start = DomainMOD\DomainList::checkedStart($_GET['start'] ?? 0);
$list_start -= $list_start % $list_length;
if ($list_start >= $total_rows) $list_start = $total_rows > 0 ? (int) (floor(($total_rows - 1) / $list_length) * $list_length) : 0;

$result = $total_rows > 0 ? $domain_list->rows($list_filter_sql, array('sql' => '', 'params' => array()),
    DomainMOD\DomainList::orderSql(array(), $list_columns->sortMap(), $list_columns->defaultOrder()), $list_start,
    $list_length, $list_filter_params) : array();

if ($segid !== 0) {

    $number_of_domains = $segment->getNumberOfDomains($segid);

}

// The status names, as the status menu shows them, so the menu and the status chip always match
$status_names = array('0' => _('Expired'), '1' => _('Active'), '2' => _('Pending (Transfer)'),
    '3' => _('Pending (Renewal)'), '4' => _('Pending (Other)'), '5' => _('Pending (Registration)'), '10' => _('Sold'),
    'LIVE' => _('Live Domains') . ' (' . _('Active') . ' / ' . _('Transfers') . ' / ' . _('Pending') . ')',
    'ALL' => DomainMOD\Format::toUpper(_('All')));

/*
 * The filter toolbar's state (DomainMOD\FilterToolbar): every active filter with its plain-text name for its chip.
 * Each id filter's name is read with one small query, only when that filter is on, and an id that no longer exists
 * still gets a chip.
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
if ($lock !== '') $filter_toolbar->addFilter('lock', $lock, '', DomainMOD\DomainStatus::filterChoices()[$lock], _('Registry Status'));
if ($_SESSION['s_search_for'] != '') {
    $filter_keyword = DomainMOD\FilterToolbar::decode($_SESSION['s_search_for']);
    $filter_toolbar->addFilter('search_for', $filter_keyword, '', '"' . $filter_keyword . '"', _('Keyword Search'));
}
if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
    $filter_toolbar->addFilter('daterange', $_SESSION['s_start_date'] . ' - ' . $_SESSION['s_end_date'], '',
        sprintf(_('Expiring: %1$s to %2$s'), $_SESSION['s_start_date'], $_SESSION['s_end_date']));
}
// The custom field filters' chips, and the keys that let the next request check their fields: the field set's (cff)
// on every address, and the filters' own (cfk) on the state and export addresses
$cf->addChips($filter_toolbar, $cf_state);
if ($cf_state) {
    $filter_toolbar->carry('cff', $cf->fieldsKey());
    $filter_toolbar->carryState('cfk', $cf->filterKey(array_keys($cf_keys)));
}
// The date range picker's inputs (see date-range-picker-footer.inc.php): Expiring Between and the date custom fields
$date_range_fields = array_merge(array('daterange'), $cf->dateKeys());

// The custom field filters for the filter menus' queries, which don't join the custom field data, by the domain id
// each query names (see CustomFieldFilter::subquery()), and a menu's rows with their bound values
$cf_menu_d = $cf->subquery($cf_state, DomainMOD\DomainList::FIELD_DATA_TABLE, DomainMOD\DomainList::FIELD_DATA_ID, 'd.id');
$cf_menu_domains = $cf->subquery($cf_state, DomainMOD\DomainList::FIELD_DATA_TABLE, DomainMOD\DomainList::FIELD_DATA_ID,
    'domains.id');
$menu_rows = function ($sql, array $cf_menu) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($cf_menu['params']);
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
    return $rows;
};

if ($_SESSION['s_has_domain'] == '1' && $_SESSION['s_has_registrar'] == '1' && $_SESSION['s_has_registrar_account'] == '1') {

    // The no-JavaScript page (expand=1) draws the form as a card under the toolbar instead of in the side panel
    $filter_expanded = ($expand === 1);

    echo $filter_toolbar->toolbar($filter_expanded);
    echo $filter_toolbar->panelTop($filter_expanded);

        echo $form->showFormTop('');

        // The Registry Status filter's condition for the menus below, which name the domains table d, or don't alias it
        $lock_string_d = DomainMOD\DomainStatus::filterCondition($lock, 'd.');
        $lock_string_plain = DomainMOD\DomainStatus::filterCondition($lock, ''); ?>

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

                $result_registrar = $menu_rows("
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
                  $lock_string_d
                  {$cf_menu_d['sql']}
                GROUP BY r.name
                ORDER BY r.name asc", $cf_menu_d);

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

                $result_account = $menu_rows("
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
                  $lock_string_d
                  {$cf_menu_d['sql']}
                GROUP BY r.name, o.name, ra.username
                ORDER BY r.name asc, o.name asc, ra.username asc", $cf_menu_d);

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

                $result_dns = $menu_rows("
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
                  $lock_string_d
                  {$cf_menu_d['sql']}
                GROUP BY dns.name
                ORDER BY dns.name asc", $cf_menu_d);

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

                $result_ip = $menu_rows("
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
                  $lock_string_d
                  {$cf_menu_d['sql']}
                GROUP BY ip.name
                ORDER BY ip.name asc", $cf_menu_d);

                echo $form->showDropdownTop('ipid', _('IP Address'), '', '', '');
                echo $form->showDropdownOption('', _('IP Address') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
                foreach ($result_ip as $row_ip) {

                    echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ')', $ipid);

                }
                echo $filter_toolbar->keepOption('ipid', array_column($result_ip, 'id'));
                echo $form->showDropdownBottom(''); ?>

                <?php // The session keeps the keyword HTML-escaped, so it's decoded once here (the field escapes it again), and Apply sends it back unchanged
                echo $form->showInputText('search_for', _('Keyword Search'), '', DomainMOD\FilterToolbar::decode($_SESSION['s_search_for']), '100', '', '', '', ''); ?><?php
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

                $result_hosting = $menu_rows("
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
                  $lock_string_d
                  {$cf_menu_d['sql']}
                GROUP BY h.name
                ORDER BY h.name asc", $cf_menu_d);

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

                $result_category = $menu_rows("
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
                  $lock_string_d
                  {$cf_menu_d['sql']}
                GROUP BY c.name
                ORDER BY c.name asc", $cf_menu_d);

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

                $result_owner = $menu_rows("
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
                  $lock_string_d
                  {$cf_menu_d['sql']}
                GROUP BY o.name
                ORDER BY o.name asc", $cf_menu_d);

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

                $result_tld = $menu_rows("
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
                    $segment_string .
                    $lock_string_plain .
                    $cf_menu_domains['sql'] . "
                GROUP BY tld 
                ORDER BY tld asc", $cf_menu_domains);

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

                $result_active = $menu_rows("
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
                  $lock_string_plain
                  {$cf_menu_domains['sql']}
                GROUP BY active
                ORDER BY active asc", $cf_menu_domains);

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


                // REGISTRY STATUS (DomainMOD\DomainStatus): a fixed list, once the 4.27.0 database update is done
                if ($domain_list->hasStatusColumns()) {

                    echo $form->showDropdownTop('lock', _('Registry Status'), '', '', '');
                    echo $form->showDropdownOption('', _('Registry Status') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
                    foreach (DomainMOD\DomainStatus::filterChoices() as $lock_value => $lock_label) {

                        echo $form->showDropdownOption($lock_value, $lock_label, $lock);

                    }
                    echo $form->showDropdownBottom('');

                } ?>

                <?php
                echo $form->showInputText('daterange', _('Expiring Between'), '', $daterange, '23', '', '', '', '');

                // CUSTOM FIELDS
                echo $cf->panelHtml($form, $cf_state); ?>

                <div class="d-flex flex-wrap gap-2 domainmod-filter-actions">
                    <?php echo $form->showSubmitButton(_('Apply filters'), '', ''); ?>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($web_root . '/domains/index.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo _('Clear filters'); ?></a>
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

    // Worked out from the list's own filters, not read back from the segment's stored flags
    $totalrows_filtered = $domain_list->segmentFilteredCount($segid, $list_filter_sql, $list_filter_params);

    // The segment's summary as facts, with a link to each of its three results lists (domains/results.php) that has any
    $segment_facts = array(
        array('label' => _('Domains in Segment'), 'value' => number_format($number_of_domains)),
        array('label' => _('Matching Domains'), 'value' => number_format($total_rows)));
    if ($result) {
        $segment_facts[] = array('label' => _('Total Cost'), 'value' => $grand_total . ' ' . $_SESSION['s_default_currency'],
            'ltr' => true);
    }
    foreach (array('inactive' => array(_('Matching But Inactive Domains'), $totalrows_inactive),
                 'filtered' => array(_('Matching But Filtered Domains'), $totalrows_filtered),
                 'missing' => array(_('Missing Domains'), $totalrows_missing)) as $results_type => $results_fact) {
        if ($results_fact[1] > 0) {
            $segment_facts[] = array('label' => $results_fact[0], 'value' => number_format($results_fact[1]),
                'url' => 'results.php?type=' . $results_type . '&segid=' . $segid, 'link_label' => _('View'));
        }
    }
    echo $layout->factList($segment_facts);

}

if ($result) {

    // The page's actions, drawn in the page header (see Layout::renderPageActions()). Layout leaves the Add button
    // and its menu out for read-only users.
    $layout->setPageAddAction(_('Add Domain'), 'add.php');
    $layout->addPageAddMenuItem(_('Add Domains Automatically'), $web_root . '/queue/intro.php', 'bi-magic');
    $layout->addPageAddMenuItem(_('CSV Importer'), $web_root . '/domains/csv-importer/', 'bi-filetype-csv');
    // Both keep their ids, since js/server-list.js adds the table's search box to their links (dt_search). The Raw List
    // link always names the list it was made for, its saved query and bound values (dt_list), its type (dt_type), and
    // its custom field filters' key (dt_cfk), so raw.php never shows another tab's list or an old custom field filter.
    $raw_list_url = $web_root . '/raw.php?' . http_build_query(array(
        'dt_list' => DomainMOD\DomainList::savedListKey($raw_list_from_sql, $list_filter_params), 'dt_type' => 'domains',
        'dt_cfk' => $cf->filterKey(array_keys($cf_keys))));
    $layout->addPageAction(_('Raw List'), $raw_list_url, 'bi-list-ul', array('target' => '_blank', 'id' => 'domain-list-raw'));
    // The export carries the filters the list is showing (FilterToolbar::exportUrl()), so it matches after Apply Filters too
    $layout->addPageAction(_('Export'), $filter_toolbar->exportUrl(), 'bi-download', array('id' => 'domain-list-export')); ?>

    <?php // With a segment, the Total Cost is in the segment's summary above
    if ($segid === 0) { ?>
        <strong><?php echo _('Number of Domains'); ?>:</strong> <?php echo number_format($total_rows); ?><BR>
        <strong><?php echo _('Total Cost'); ?>:</strong> <?php echo htmlentities($grand_total, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlentities($_SESSION['s_default_currency'], ENT_QUOTES, 'UTF-8'); ?><BR><BR>

    <?php }

    if ($total_rows) {

        /*
         * Server-side processing (see js/server-list.js): the columns and cells come from DomainListColumns, as the
         * endpoint's do, and the table carries what the script needs, the endpoint's address with the list's whole state
         * (so another tab's filters can't change this tab's rows), the columns, the counts, the page, and its size.
         */
        $list_state_url = $filter_toolbar->stateUrl();
        $list_query = (string) parse_url($list_state_url, PHP_URL_QUERY);
        $list_data_url = $web_root . '/domains/list-data.php?' . ($list_query !== '' ? $list_query . '&' : '')
            . http_build_query(array('layout' => $list_columns->layoutKey()));
        $list_config = array(
            'url' => $list_data_url,
            // Each column carries its class (ListColumns::columnClass()) for the list's styles
            // (css/datatables/domains-main.css), which DataTables adds to the cells it draws
            'columns' => array_map(function ($column) use ($list_columns) {
                return array('orderable' => (bool) $column['sort'], 'control' => $column['key'] === 'control',
                    'className' => $list_columns->columnClass($column));
            }, $list_columns->columns()),
            // The list's styles cut long values short, so the script gives a cut-short cell its full text as a tooltip
            'cellTitles' => true,
            // Every column stays, and the table scrolls sideways in its own box instead of folding columns away
            'responsive' => false,
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
                'filterReload' => _('A custom field in your filters was changed or deleted. Reload the page to see the list without your custom field filters.'),
                'reloadLink' => _('Reload'),
                'close' => _('Close'),
                'paging' => sprintf(_('Pages of %s'), _('Domains')),
                'scroll' => _('Domains'),
            ),
            'signinUrl' => $web_root . '/',
            // Where each Reload link goes: the list's whole state when Display Settings changed, and the state without the
            // custom field filters when one of their fields changed (see js/server-list.js)
            'reloadUrl' => $list_state_url,
            'filterReloadUrl' => $filter_toolbar->stateUrl('search_for', 'daterange', array(), array_keys($cf_keys)),
            // The links that follow the search box (by id), and what each carries with it (see raw.php)
            'searchLinks' => array(
                'domain-list-raw' => array('dt_layout' => $list_columns->layoutKey()),
                'domain-list-export' => array('dt_layout' => $list_columns->layoutKey()),
            ),
        );
        $today = $time->timeBasic(); // for marking past expiry dates ?>

        <div id="<?php echo $slug; ?>-message"></div>
        <?php // The table's scroll box, for when JavaScript is off (js/server-list.js gives DataTables its own) ?>
        <div class="domainmod-list-scroll" tabindex="0" role="region" aria-label="<?php echo htmlspecialchars(_('Domains'), ENT_QUOTES, 'UTF-8'); ?>">
        <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>" data-domainmod-list="<?php echo htmlspecialchars(json_encode($list_config), ENT_QUOTES, 'UTF-8'); ?>">
            <thead>
            <tr>
                <?php // The headings, as ListColumns::headerCells() prints them
                foreach ($list_columns->headerCells() as $header_cell) {
                    echo $header_cell;
                } ?>
            </tr>
            </thead>
            <tbody>
            <?php // Each cell has its column's class, as the headings do, for when JavaScript is off
            $list_cell_classes = array_map(array($list_columns, 'columnClass'), $list_columns->columns());
            foreach ($result as $row) { ?>
                <tr>
                    <?php foreach ($list_columns->cells($row, $today) as $index => $cell) { ?>
                        <td class="<?php echo $list_cell_classes[$index]; ?>"><?php echo $cell; ?></td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
        <?php echo $layout->listPager($total_rows, $list_start, $list_length, $list_state_url, sprintf(_('Pages of %s'), _('Domains'))); ?>
        <BR><?php

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
            || $rid !== 0 || $raid !== 0 || $tld != '' || $list_has_range || $list_has_search || $is_active != 'LIVE'
            || $cf_state !== array() || $lock !== '';

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

        // Read-only users can't add fees, so their link only offers to show the missing ones
        $missing_fees_label = ($_SESSION['s_read_only'] ?? '') == '1' ? _('View the missing fees') : _('Add the missing fees');

        if (!$domain_filters_active) {

            $live_domains = (int) $pdo->query("SELECT count(*) FROM domains WHERE active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')")->fetchColumn();

            if ($live_domains === 0) {

                echo $layout->emptyState('bi-archive', _('No live domains'), _('Your domains are all expired, sold, or inactive.'), '', '', '/domains/index.php?is_active=ALL', _('Show all domains'));

            } else {

                echo $layout->emptyState('bi-cash-coin', _('Some domains are hidden until they have a fee'), _("Domains without a fee can't be costed, so they don't appear in the list yet."), '', '', '/reporting/domains/registrar-fees.php?all=0#missing', $missing_fees_label);

            }

        } else {

            $stmt = $pdo->prepare("SELECT count(*)" . $domains_without_fee_join . $list_filter_sql);
            $stmt->execute($list_filter_params);
            $filtered_domains = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($filtered_domains > 0) {

                echo $layout->emptyState('bi-cash-coin', _('Some domains are hidden until they have a fee'), _("Domains without a fee can't be costed, so they don't appear in the list yet."), '', '', '/reporting/domains/registrar-fees.php?all=0#missing', $missing_fees_label);

            } else {

                echo $layout->emptyState('bi-search', _('No domains match your filters'), _('Try changing or clearing the filters.'), '', '', '/domains/index.php', _('Clear filters'));

            }

        }

    }

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php';  //@formatter:on ?>
<?php require_once DIR_INC . '/layout/date-range-picker-footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/filter-auto-apply.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl('js/server-list.js'); ?>"></script>
</body>
</html>
