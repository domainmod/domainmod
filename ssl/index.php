<?php
/**
 * /ssl/index.php
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
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/ssl-main.inc.php';

$system->authCheck();
// Rates for every currency in use, or the lists and reports below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');
$pdo = $deeb->cnxx;

// The keyword search as an SQL condition on a domain column, for the filter menus' counts (see
// DomainList::keywordCondition())
$domain_search_condition = function ($column, $term) use ($pdo) {
    return DomainMOD\DomainList::keywordCondition($pdo, $column, $term);
};

$export_data = (int) ($_GET['export_data'] ?? 0);
$oid = (int) ($_REQUEST['oid'] ?? 0);
// The Domain filter also takes SslList::NO_DOMAIN (certificates with no domain), and any other negative number is no
// filter, as on the list's data endpoint
$did = DomainMOD\SslList::checkedDomainId($_REQUEST['did'] ?? 0);
$sslpid = (int) ($_REQUEST['sslpid'] ?? 0);
$sslpaid = (int) ($_REQUEST['sslpaid'] ?? 0);
$ssltid = (int) ($_REQUEST['ssltid'] ?? 0);
$sslipid = (int) ($_REQUEST['sslipid'] ?? 0);
$sslpcid = (int) ($_REQUEST['sslpcid'] ?? 0);
$is_active = $_REQUEST['is_active'] ?? '';
$search_for = isset($_REQUEST['search_for']) ? $sanitize->text($_REQUEST['search_for']) : '';
$from_dropdown = (int) ($_REQUEST['from_dropdown'] ?? 0);
$expand = (int) ($_REQUEST['expand'] ?? 0);
$daterange = isset($_REQUEST['daterange']) ? $sanitize->text($_REQUEST['daterange']) : '';

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

if (($_SESSION['s_start_date'] ?? '') == '') $_SESSION['s_start_date'] = 'YYYY-MM-DD';
if (($_SESSION['s_end_date'] ?? '') == '') $_SESSION['s_end_date'] = 'YYYY-MM-DD';

// A range saved by an older version may not have been checked, so anything that isn't a real date range is dropped.
if (!$date->checkDateFormat($_SESSION['s_start_date']) || !$date->checkDateFormat($_SESSION['s_end_date'])) {

    $_SESSION['s_start_date'] = 'YYYY-MM-DD';
    $_SESSION['s_end_date'] = 'YYYY-MM-DD';

}

// The keyword and date range the list uses. An export reads them from its own request when its link carries them
// (FilterToolbar::exportUrl() always does), without touching the session, so filters another tab saved there can't end
// up in the file. An older export link without them falls back to the session, as before.
$list_search_for = $_SESSION['s_search_for_ssl'] ?? '';
$list_start_date = $_SESSION['s_start_date'];
$list_end_date = $_SESSION['s_end_date'];

if ($export_data === 1) {

    if (array_key_exists('search_for', $_GET)) $list_search_for = $search_for;

    if (array_key_exists('daterange', $_GET)) {

        // The same test the list uses when it saves a range, so the export matches the list exactly
        if ($new_start_date != '' && $new_start_date != 'YYYY-MM-DD') {

            $list_start_date = $new_start_date;
            $list_end_date = $new_end_date;

        } else {

            $list_start_date = 'YYYY-MM-DD';
            $list_end_date = 'YYYY-MM-DD';

        }

    }

}

// The filter box shows the range the list uses, so a range that was ignored (one that isn't a real date range)
// leaves it empty rather than showing text the list didn't apply.
$daterange = $list_start_date != 'YYYY-MM-DD' ? $list_start_date . ' - ' . $list_end_date : '';

// Anything else (including translated values in links from older versions) falls back to the default
$valid_is_active = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'LIVE', 'ALL');
if (!is_string($is_active) || !in_array($is_active, $valid_is_active, true)) $is_active = 'LIVE';

// Where an export that can't be made sends the user back: the list, with the export's filters. When an older export
// link leaves out the keyword or the date range, the export uses the saved one, so the saved value is added here for
// the list to keep. from_dropdown is left out, since it would make the list ignore them.
$back_filters = array_diff_key($_GET, array_flip(array('export_data', 'dt_search', 'dt_layout', 'from_dropdown')));
if (!array_key_exists('search_for', $back_filters)) $back_filters['search_for'] = htmlspecialchars_decode($list_search_for, ENT_QUOTES);
if (!array_key_exists('daterange', $back_filters)) $back_filters['daterange'] = $daterange;

// The list's filters as SQL, from the same class as the list's data endpoint (ssl/list-data.php), so the first page,
// the pages fetched afterwards, the counts, Export, and the Raw List all agree. The keyword is matched against the
// certificate's name, which is stored HTML-encoded, as the keyword is, or its domain, as the filter menus match it (see
// SslList::filterParts()).
//
// The list's custom fields are read once, so the SELECT, the columns, Export, and the custom field filters all describe
// the same fields, and the custom field filters come from the request only, as on the Domains list (see there)
$sslfd_fields = $customField->getFilterFields('ssl_cert_fields');
$cf = new DomainMOD\CustomFieldFilter($sslfd_fields, 'sslfd');
$cf_request = ($_SERVER['REQUEST_METHOD'] == 'POST' && $export_data !== 1) ? $_POST : $_GET;
if (!$cf->requestOk($cf_request) || ($export_data === 1 && DomainMOD\CustomFieldFilter::hasFilterKeys($cf_request)
        && !isset($cf_request['cfk']))) {

    $_SESSION['s_message_danger'] .= _("A custom field in your filters was changed or deleted, so the list isn't filtered by your custom field filters.") . '<BR>';

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

$ssl_list = new DomainMOD\SslList($pdo, $_SESSION['s_user_id'], $sslfd_fields);
$list_state = array('oid' => $oid, 'did' => $did, 'sslpid' => $sslpid, 'sslpaid' => $sslpaid, 'ssltid' => $ssltid,
    'sslipid' => $sslipid, 'sslpcid' => $sslpcid, 'is_active' => $is_active);
$filter_parts = $ssl_list->filterParts($list_state, $list_search_for, $list_start_date, $list_end_date);
$is_active_string = $filter_parts['is_active_string'];
$oid_string = $filter_parts['oid_string'];
$did_string = $filter_parts['did_string'];
$sslpid_string = $filter_parts['sslpid_string'];
$sslpaid_string = $filter_parts['sslpaid_string'];
$ssltid_string = $filter_parts['ssltid_string'];
$sslipid_string = $filter_parts['sslipid_string'];
$sslpcid_string = $filter_parts['sslpcid_string'];
$range_string = $filter_parts['range_string'];
$search_string = $filter_parts['search_string'];

// Export and the Raw List come out in the list's default order (SslList::EXPORT_ORDER_SQL). The Raw List also keeps the
// query without its order, so it can add the table's search box when its link carries one, and without its columns, so
// it can read only the names, in chunks (see raw.php).
$raw_list_from_sql = $ssl_list->fromSql() . "
          $is_active_string
          $oid_string
          $did_string
          $sslpid_string
          $sslpaid_string
          $ssltid_string
          $sslipid_string
          $sslpcid_string
          $range_string
          $search_string" . $cf_filter['sql'];
$raw_list_sql = $ssl_list->selectSql() . $raw_list_from_sql;
$sql = $raw_list_sql . DomainMOD\SslList::EXPORT_ORDER_SQL;
$_SESSION['s_raw_list_type'] = 'ssl-certs';
$_SESSION['s_raw_list_sql'] = $sql;
$_SESSION['s_raw_list_unordered_sql'] = $raw_list_sql;
$_SESSION['s_raw_list_from_sql'] = $raw_list_from_sql;
// The custom field filters' bound values, and their keys, for raw.php's checks (see raw.php)
$_SESSION['s_raw_list_params'] = $cf_filter['params'];
$_SESSION['s_raw_list_filter_keys'] = array_keys($cf_keys);

// The list's filters as they are now, kept for the empty state's counts, since the filter menus below reuse these names
$list_filter_sql = $is_active_string . $oid_string . $did_string . $sslpid_string . $sslpaid_string . $ssltid_string
    . $sslipid_string . $sslpcid_string . $range_string . $search_string . $cf_filter['sql'];
$list_filter_params = $cf_filter['params'];
$list_has_range = $range_string != '';
$list_has_search = $search_string != '';

// How many certificates the filters match, and their total cost (the same joins and filters as the list). Export reads
// its own inside its snapshot, so they aren't read twice.
if ($export_data !== 1) {

    $total_rows = $ssl_list->count($list_filter_sql, $list_filter_params);

    $stmt = $pdo->prepare("SELECT SUM(sslc.total_cost * cc.conversion)" . $ssl_list->fromSql() . $list_filter_sql);
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
    $table_search = $ssl_list->tableSearchSql($table_search_for, $_GET['dt_layout'] ?? '',
        DomainMOD\SslListColumns::sessionCopy($_SESSION));

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

        $row_contents = array(
            '[' . _('Search Filters') . ']'
        );
        $search_filter_rows[] = $row_contents;

        if ($list_search_for != "") {

            $row_contents = array(
                _('Keyword Search') . ':',
                $list_search_for
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

        if ($did > 0) {

            $domain = new DomainMOD\Domain();
            $temp_domain = $domain->getDomain($did);

            $row_contents = array(
                _('Associated Domain') . ':',
                $temp_domain
            );
            $search_filter_rows[] = $row_contents;

        } elseif ($did === DomainMOD\SslList::NO_DOMAIN) {

            $row_contents = array(
                _('Associated Domain') . ':',
                _('None')
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($sslpid > 0) {

            $temp_name = $assets->getSslProvider($sslpid);

            $row_contents = array(
                _('SSL Provider') . ':',
                $temp_name
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($sslpaid > 0) {

            $stmt = $pdo->prepare("
                SELECT sslp.name AS ssl_provider_name, o.name AS owner_name, sslpa.username
                FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o
                WHERE sslpa.ssl_provider_id = sslp.id
                  AND sslpa.owner_id = o.id
                  AND sslpa.id = :sslpaid");
            $stmt->bindValue('sslpaid', $sslpaid, PDO::PARAM_INT);
            $stmt->execute();
            $account_heading = $stmt->fetch();
            $stmt->closeCursor();

            if ($account_heading) {

                $row_contents = array(
                    _('SSL Provider Account') . ':',
                    $account_heading->ssl_provider_name . " - " . $account_heading->owner_name . " - " . $account_heading->username
                );
                $search_filter_rows[] = $row_contents;


            }

        }

        if ($ssltid > 0) {

            $temp_type = $assets->getSslType($ssltid);

            $row_contents = array(
                _('SSL Type') . ':',
                $temp_type
            );
            $search_filter_rows[] = $row_contents;


        }

        if ($sslipid > 0) {

            list($temp_ip, $temp_ip_name) = $assets->getIpAndName($sslipid);

            $row_contents = array(
                _('SSL IP Address') . ':',
                $temp_ip_name . ' (' . $temp_ip . ')'
            );
            $search_filter_rows[] = $row_contents;

        }

        if ($sslpcid > 0) {

            $temp_category = $assets->getCat($sslpcid);

            $row_contents = array(
                _('SSL Category') . ':',
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

        if ($list_start_date != '' && $list_start_date != 'YYYY-MM-DD') {

            $row_contents = array(
                _('Expiry Date Range') . ':',
                $list_start_date  . " to " . $list_end_date
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

        $row_contents[$count++] = _('SSL Status') . ':';

        if ($is_active == 'ALL') {

            $row_contents[$count++] = DomainMOD\Format::toUpper(_('All'));

        } elseif ($is_active == 'LIVE' || $is_active == "") {

            $row_contents[$count++] = DomainMOD\Format::toUpper(_('Live')) . ' (' . _('Active') . ' / ' . _('Pending') . ')';

        } elseif ($is_active == "0") {

            $row_contents[$count++] = _('Expired');

        } elseif ($is_active == "1") {

            $row_contents[$count++] = _('Active');

        } elseif ($is_active == "3") {

            $row_contents[$count++] = _('Pending (Renewal)');

        } elseif ($is_active == "4") {

            $row_contents[$count++] = _('Pending (Other)');

        } elseif ($is_active == "5") {

            $row_contents[$count++] = _('Pending (Registration)');

        }
        $search_filter_rows[] = $row_contents;

        // Everything from here to the last row reads one snapshot of the database, so the header's numbers and the
        // rows always agree, however the data changes in the meantime
        $export->startSnapshot($ssl_list);

        // The custom fields as the snapshot has them, which must be the ones the page read (the headings, the cells,
        // and the filters all come from that read), then the full row query once with LIMIT 0, the first query of the
        // custom field data, so a column that's gone fails here, before the file starts, rather than in a chunk
        if (!$cf->sameFields(DomainMOD\CustomField::readFilterFields($pdo, 'ssl_cert_fields'))) {

            throw new RuntimeException('The custom fields changed while the export was being made');

        }
        $ssl_list->exportPreflight($list_filter_sql . $table_search['sql'], $table_search['params'] + $list_filter_params);

        // The export's count and total cost, for the rows it holds (with a table search, the rows the search matches)
        $stmt = $pdo->prepare("SELECT count(*) AS total_rows, SUM(sslc.total_cost * cc.conversion) AS grand_total"
            . $ssl_list->fromSql() . $list_filter_sql . $table_search['sql']);
        $stmt->execute($table_search['params'] + $list_filter_params);
        $export_totals = $stmt->fetch();
        $stmt->closeCursor();
        $total_rows = (int) $export_totals->total_rows;
        $grand_total = $currency->format($export_totals->grand_total, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        // The custom fields' columns, headings, and types all come from the page's one read of them (checked against
        // the snapshot above)
        $sslfd_types = array_column($sslfd_fields, 'type_id', 'field_name');
        $sslfd_columns_array = array_column($sslfd_fields, 'field_name');

        // The creation types and users, read once rather than once for each certificate
        $creation_type_names = DomainMOD\Export::creationTypeNames($pdo);
        $user_names = DomainMOD\Export::userNames($pdo);

        $export_file = $export->openStream(_('ssl_results'), strtotime($time->stamp()));

        $row_contents = array(_('SSL Certificate Search Results Export'));
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            _('Number of SSL Certificates') . ':',
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

        foreach ($search_filter_rows as $row_contents) {

            $export->writeRow($export_file, $row_contents);

        }

        $export->writeBlankRow($export_file);

        unset($row_contents);
        $count = 0;

        $row_contents[$count++] = _('SSL Cert Status');
        $row_contents[$count++] = _('Expiry Date');
        $row_contents[$count++] = _('Initial Fee');
        $row_contents[$count++] = _('Renewal Fee');
        $row_contents[$count++] = _('Misc Fee');
        $row_contents[$count++] = _('Total Yearly Cost');
        $row_contents[$count++] = _('Host') . ' / ' . _('Label');
        $row_contents[$count++] = _('Domain');
        $row_contents[$count++] = _('SSL Provider');
        $row_contents[$count++] = _('SSL Provider Account');
        $row_contents[$count++] = _('Username');
        $row_contents[$count++] = _('SSL Type');
        $row_contents[$count++] = _('IP Address Name');
        $row_contents[$count++] = _('IP Address');
        $row_contents[$count++] = _('IP Address rDNS');
        $row_contents[$count++] = _('Category');
        $row_contents[$count++] = _('Owner');
        $row_contents[$count++] = _('Notes');
        $row_contents[$count++] = _('Creation Type');
        $row_contents[$count++] = _('Created By');
        $row_contents[$count++] = _('Inserted');
        $row_contents[$count++] = _('Updated');
        $row_contents[$count++] = DomainMOD\Format::toUpper(_('Custom Fields'));

        // The headings are the fields' names, the user's own text
        foreach ($sslfd_fields as $row_field) {

            $row_contents[$count++] = $export->protectFormula($row_field['name']);

        }

        $export->writeRow($export_file, $row_contents);

        $export->chunkWritten();

        foreach ($ssl_list->exportChunks($list_filter_sql . $table_search['sql'], $table_search['params'] + $list_filter_params) as $result) {

            foreach ($result as $row) {

                $temp_initial_fee = $row->initial_fee * $row->conversion;
                $temp_renewal_fee = $row->renewal_fee * $row->conversion;
                $temp_misc_fee = $row->misc_fee * $row->conversion;
                $temp_total_cost = $row->total_cost * $row->conversion;

                if ($row->active == "0") {
                    $ssl_status = DomainMOD\Format::toUpper(_('Expired'));
                } elseif ($row->active == "1") {
                    $ssl_status = DomainMOD\Format::toUpper(_('Active'));
                } elseif ($row->active == "3") {
                    $ssl_status = DomainMOD\Format::toUpper(_('Pending (Renewal)'));
                } elseif ($row->active == "4") {
                    $ssl_status = DomainMOD\Format::toUpper(_('Pending (Other)'));
                } elseif ($row->active == "5") {
                    $ssl_status = DomainMOD\Format::toUpper(_('Pending (Registration)'));
                } else {
                    $ssl_status = _('ERROR -- PROBLEM WITH CODE IN SSL/INDEX.PHP');
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

                $creation_type = DomainMOD\Export::creationTypeName($creation_type_names, $row->creation_type_id);

                if ($row->created_by == '0') {
                    $created_by = _('Unknown');
                } else {
                    $created_by = DomainMOD\Export::userName($user_names, $row->created_by);
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

                if ($sslfd_columns_array != "") {

                    foreach ($sslfd_columns_array as $column) {

                        // An empty Date or Time Stamp field is blank, as the list shows it
                        if (in_array((string) ($sslfd_types[$column] ?? ''), array('4', '5'), true) && DomainMOD\Time::isUnset($row->{$column})) {

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
// Double check to make sure there are still no SSL certs in the system, and likewise for the SSL providers and SSL
// accounts the empty states below depend on, since the Import Queue adds them in cron, where no session is updated
$queryB = new DomainMOD\QueryBuild();
foreach (array('s_has_ssl_cert' => 'ssl_certs', 's_has_ssl_provider' => 'ssl_providers',
             's_has_ssl_account' => 'ssl_accounts') as $asset_flag => $asset_table) {

    if (($_SESSION[$asset_flag] ?? '0') == '0') {

        $sql_asset_check = $queryB->singleAsset($asset_table);
        $_SESSION[$asset_flag] = $system->checkForRows($sql_asset_check);

    }

}

// The SSL Queue can do the missing setup itself (it adds the provider and account), so once the database update has
// added it, the empty states below offer it as their second action. A certificate doesn't need a domain, so neither
// does the SSL Queue.
$ssl_import_available = (new DomainMOD\SslQueue())->isAvailable();
$ssl_import_action = $ssl_import_available ? '/queue/add/ssl.php' : '';

// Before anything exists, the list walks a new user through the order things are added in: an SSL provider, then an
// account at it, then certificates, one step at a time (Layout::emptyState())
if ($_SESSION['s_has_ssl_provider'] != '1') {

    echo $layout->emptyState('bi-shield-check', _('Add an SSL provider first'), _('SSL certificates belong to an SSL provider account, so start by adding the company you buy them from.'), '/assets/add/ssl-provider.php', _('Add SSL Provider'), $ssl_import_action, _('Add SSL Certificates Automatically'), 2, true, true);

}

if ($_SESSION['s_has_ssl_account'] != '1' && $_SESSION['s_has_ssl_provider'] == '1') {

    echo $layout->emptyState('bi-person-badge', _('Add an SSL provider account next'), _('Add the account you hold at that SSL provider.'), '/assets/add/ssl-provider-account.php', _('Add SSL Account'), $ssl_import_action, _('Add SSL Certificates Automatically'), 2, true, true);

}

if ($_SESSION['s_has_ssl_cert'] != '1' && $_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1') {

    echo $layout->emptyState('bi-shield-lock', _('No SSL certificates yet'), _('Add the SSL certificates you want to keep track of.'), '/ssl/add.php', _('Add SSL Certificate'), $ssl_import_action, _('Add SSL Certificates Automatically'), 2, true, true);
    $ready_for_ssl_certs = 1;

} elseif ($_SESSION['s_has_ssl_cert'] == '1' && $_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1') {

    $ready_for_ssl_certs = 1;

}

/*
 * Server-side processing: the page prints one page of rows, in the default order, and DataTables fetches the others
 * from ssl/list-data.php (see js/server-list.js). The page and its size come from the address (the no-JavaScript
 * pager's links), checked as the endpoint checks them, and a page past the end (an old link, after certificates were
 * deleted) moves to the last one. A page always starts on a multiple of its size, as DataTables' own paging does.
 */
$list_columns = new DomainMOD\SslListColumns(DomainMOD\SslListColumns::sessionCopy($_SESSION), $sslfd_fields);
$list_page_sizes = DomainMOD\SslList::pageSizes($_SESSION['s_number_of_ssl_certs']);
$list_length = DomainMOD\SslList::checkedLength($_GET['length'] ?? '', $list_page_sizes);
$list_start = DomainMOD\SslList::checkedStart($_GET['start'] ?? 0);
$list_start -= $list_start % $list_length;
if ($list_start >= $total_rows) $list_start = $total_rows > 0 ? (int) (floor(($total_rows - 1) / $list_length) * $list_length) : 0;

$result = $total_rows > 0 ? $ssl_list->rows($list_filter_sql, array('sql' => '', 'params' => array()),
    DomainMOD\SslList::orderSql(array(), $list_columns->sortMap(), $list_columns->defaultOrder()), $list_start,
    $list_length, $list_filter_params) : array();

// The status names, as the status menu shows them, so the menu and the status chip always match. Any other stored value
// shows as its number in both.
$status_names = array('0' => _('Expired'), '1' => _('Active'), '3' => _('Pending (Renewal)'), '4' => _('Pending (Other)'),
    '5' => _('Pending (Registration)'),
    'LIVE' => _('Live SSL Certificates') . ' (' . _('Active') . ' / ' . _('Pending') . ')',
    'ALL' => DomainMOD\Format::toUpper(_('All')));

/*
 * The filter toolbar's state (DomainMOD\FilterToolbar, shared with the Domains list): every active filter with its
 * plain-text name for its chip. Each id filter's name is read with one small query, only when that filter is on, and an
 * id that no longer exists still gets a chip.
 */
$filter_toolbar = new DomainMOD\FilterToolbar('ssl-filters', $web_root . '/ssl/index.php', $web_root . '/ssl/index.php');
$filter_name = function ($sql, $id) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $name = $stmt->fetchColumn();
    $stmt->closeCursor();
    return $name === false ? DomainMOD\FilterToolbar::notFound() : DomainMOD\FilterToolbar::decode($name);
};
if ($did === DomainMOD\SslList::NO_DOMAIN) {
    $filter_toolbar->addFilter('did', $did, 0, _('None'), _('Domain'));
} elseif ($did !== 0) {
    $filter_toolbar->addFilter('did', $did, 0, $filter_name("SELECT domain FROM domains WHERE id = :id", $did), _('Domain'));
}
if ($sslpid !== 0) $filter_toolbar->addFilter('sslpid', $sslpid, 0, $filter_name("SELECT `name` FROM ssl_providers WHERE id = :id", $sslpid), _('SSL Provider'));
if ($sslpaid !== 0) $filter_toolbar->addFilter('sslpaid', $sslpaid, 0, $filter_name("
    SELECT CONCAT(sslp.name, ', ', o.name, ' (', sslpa.username, ')')
    FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o
    WHERE sslpa.ssl_provider_id = sslp.id
      AND sslpa.owner_id = o.id
      AND sslpa.id = :id", $sslpaid), _('SSL Provider Account'));
if ($ssltid !== 0) $filter_toolbar->addFilter('ssltid', $ssltid, 0, $filter_name("SELECT type FROM ssl_cert_types WHERE id = :id", $ssltid), _('SSL Type'));
if ($sslipid !== 0) $filter_toolbar->addFilter('sslipid', $sslipid, 0, $filter_name("SELECT CONCAT(`name`, ' (', ip, ')') FROM ip_addresses WHERE id = :id", $sslipid), _('IP Address'));
if ($sslpcid !== 0) $filter_toolbar->addFilter('sslpcid', $sslpcid, 0, $filter_name("SELECT `name` FROM categories WHERE id = :id", $sslpcid), _('Category'));
if ($oid !== 0) $filter_toolbar->addFilter('oid', $oid, 0, $filter_name("SELECT `name` FROM owners WHERE id = :id", $oid), _('Owner'));
$filter_toolbar->addFilter('is_active', $is_active, 'LIVE', $status_names[$is_active] ?? $is_active, _('Status'));
if ($list_search_for != '') {
    $filter_keyword = DomainMOD\FilterToolbar::decode($list_search_for);
    $filter_toolbar->addFilter('search_for', $filter_keyword, '', '"' . $filter_keyword . '"', _('Keyword Search'));
}
if ($list_start_date != '' && $list_start_date != 'YYYY-MM-DD') {
    $filter_toolbar->addFilter('daterange', $list_start_date . ' - ' . $list_end_date, '',
        sprintf(_('Expiring: %1$s to %2$s'), $list_start_date, $list_end_date));
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

// The custom field filters for the filter menus' queries, which don't join the custom field data, by the certificate
// id (never the domain's, since a domain can have several certificates, and a certificate can have no domain), and a
// menu's rows with their bound values
$cf_menu = $cf->subquery($cf_state, DomainMOD\SslList::FIELD_DATA_TABLE, DomainMOD\SslList::FIELD_DATA_ID, 'sslc.id');
$menu_rows = function ($sql) use ($pdo, $cf_menu) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($cf_menu['params']);
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
    return $rows;
};

if ($_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_ssl_cert'] == '1') {

    // The no-JavaScript page (expand=1) draws the form as a card under the toolbar instead of in the side panel
    $filter_expanded = ($expand === 1);

    echo $filter_toolbar->toolbar($filter_expanded);
    echo $filter_toolbar->panelTop($filter_expanded);

    echo $form->showFormTop(''); ?>

    <div><?php
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
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            // The domains of the matching certificates. This one is an inner join on purpose, since it lists domains (the
            // certificates with no domain are counted below, for the None option).
            $result_domain = $menu_rows("
                SELECT d.id, d.domain
                FROM ssl_certs AS sslc JOIN domains AS d ON d.id = sslc.domain_id
                WHERE d.active not in ('0', '10')
                  $is_active_string
                  $oid_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                  {$cf_menu['sql']}
                GROUP BY d.domain
                ORDER BY d.domain asc");

            // Whether any matching certificate has no domain, for the None option (SslList::NO_DOMAIN), which is only
            // offered when it's in use, as the other options are
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id
                WHERE d.id IS NULL
                  $is_active_string
                  $oid_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                  {$cf_menu['sql']}");
            $stmt->execute($cf_menu['params']);
            $no_domain_count = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            echo $form->showDropdownTop('did', _('Domain'), '', '', '');
            echo $form->showDropdownOption('', _('Domain') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            $domain_options = array_column($result_domain, 'id');
            if ($no_domain_count > 0) {

                echo $form->showDropdownOption(DomainMOD\SslList::NO_DOMAIN, _('None'), $did);
                $domain_options[] = DomainMOD\SslList::NO_DOMAIN;

            }
            foreach ($result_domain as $row_domain) {

                echo $form->showDropdownOption($row_domain->id, $row_domain->domain, $did);

            }
            echo $filter_toolbar->keepOption('did', $domain_options);
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
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            $did_string = DomainMOD\SslList::domainCondition($did);
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_ssl_provider = $menu_rows("
                SELECT sslp.id, sslp.name
                FROM ssl_providers AS sslp, ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id
                WHERE sslp.id = sslc.ssl_provider_id
                  $is_active_string
                  $oid_string
                  $did_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                  {$cf_menu['sql']}
                GROUP BY sslp.name
                ORDER BY sslp.name asc");

            echo $form->showDropdownTop('sslpid', _('SSL Provider'), '', '', '');
            echo $form->showDropdownOption('', _('SSL Provider') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_ssl_provider as $row_ssl_provider) {

                echo $form->showDropdownOption($row_ssl_provider->id, $row_ssl_provider->name, $sslpid);

            }
            echo $filter_toolbar->keepOption('sslpid', array_column($result_ssl_provider, 'id'));
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
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            $did_string = DomainMOD\SslList::domainCondition($did);
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_account = $menu_rows("
                SELECT sslpa.id AS sslpa_id, sslpa.username, sslp.name AS sslp_name, o.name AS owner_name
                FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o,
                     ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id
                WHERE sslpa.ssl_provider_id = sslp.id
                  AND sslpa.owner_id = o.id
                  AND sslpa.id = sslc.account_id
                  $is_active_string
                  $oid_string
                  $did_string
                  $sslpid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                  {$cf_menu['sql']}
                GROUP BY sslp.name, o.name, sslpa.username
                ORDER BY sslp.name asc, o.name asc, sslpa.username asc");

            echo $form->showDropdownTop('sslpaid', _('SSL Provider Account'), '', '', '');
            echo $form->showDropdownOption('', _('SSL Provider Account') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_account as $row_account) {

                echo $form->showDropdownOption($row_account->sslpa_id, $row_account->sslp_name . ', ' . $row_account->owner_name . ' (' . $row_account->username . ')', $sslpaid);

            }
            echo $filter_toolbar->keepOption('sslpaid', array_column($result_account, 'sslpa_id'));
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
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            $did_string = DomainMOD\SslList::domainCondition($did);
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_type = $menu_rows("
                SELECT sslc.type_id, sslcf.type
                FROM ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id, ssl_cert_types AS sslcf
                WHERE sslc.type_id = sslcf.id
                  $is_active_string
                  $oid_string
                  $did_string
                  $sslpid_string
                  $sslpaid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                  {$cf_menu['sql']}
                GROUP BY sslcf.type
                ORDER BY sslcf.type asc");

            echo $form->showDropdownTop('ssltid', _('SSL Type'), '', '', '');
            echo $form->showDropdownOption('', _('SSL Type') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_type as $row_type) {

                echo $form->showDropdownOption($row_type->type_id, $row_type->type, $ssltid);

            }
            echo $filter_toolbar->keepOption('ssltid', array_column($result_type, 'type_id'));
            echo $form->showDropdownBottom(''); ?>

            <?php echo $form->showInputText('search_for', _('Keyword Search'), '', DomainMOD\FilterToolbar::decode($_SESSION['s_search_for_ssl']), '100', '', '', '', ''); ?><?php

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
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            $did_string = DomainMOD\SslList::domainCondition($did);
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_ip = $menu_rows("
                SELECT ip.id AS ip_id, ip.name AS ip_name, ip.ip
                FROM ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id, ip_addresses AS ip
                WHERE sslc.ip_id = ip.id
                  $is_active_string
                  $oid_string
                  $did_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                  {$cf_menu['sql']}
                GROUP BY ip.name, ip.ip
                ORDER BY ip.name, ip.ip");

            echo $form->showDropdownTop('sslipid', _('IP Address'), '', '', '');
            echo $form->showDropdownOption('', _('IP Address') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_ip as $row_ip) {

                echo $form->showDropdownOption($row_ip->ip_id, $row_ip->ip_name . ' (' . $row_ip->ip . ')', $sslipid);

            }
            echo $filter_toolbar->keepOption('sslipid', array_column($result_ip, 'ip_id'));
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
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            $did_string = DomainMOD\SslList::domainCondition($did);
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_cat = $menu_rows("
                SELECT c.id AS cat_id, c.name AS cat_name
                FROM ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id, categories AS c
                WHERE sslc.cat_id = c.id
                  $is_active_string
                  $oid_string
                  $did_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $range_string
                  $search_string
                  {$cf_menu['sql']}
               GROUP BY c.name
               ORDER BY c.name");

            echo $form->showDropdownTop('sslpcid', _('Category'), '', '', '');
            echo $form->showDropdownOption('', _('Category') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_cat as $row_cat) {

                echo $form->showDropdownOption($row_cat->cat_id, $row_cat->cat_name, $sslpcid);

            }
            echo $filter_toolbar->keepOption('sslpcid', array_column($result_cat, 'cat_id'));
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
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            $did_string = DomainMOD\SslList::domainCondition($did);
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            if ($_SESSION['s_search_for_ssl'] != "") {
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . " OR " . $domain_search_condition('d.domain', $_SESSION['s_search_for_ssl']) . ")";
            } else {
                $search_string = "";
            }

            $result_owner = $menu_rows("
                SELECT o.id, o.name
                FROM ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id, owners AS o
                WHERE sslc.owner_id = o.id
                  $is_active_string
                  $did_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                  {$cf_menu['sql']}
                GROUP BY o.name
                ORDER BY o.name asc");

            echo $form->showDropdownTop('oid', _('Owner'), '', '', '');
            echo $form->showDropdownOption('', _('Owner') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_owner as $row_owner) {

                echo $form->showDropdownOption($row_owner->id, $row_owner->name, $oid);

            }
            echo $filter_toolbar->keepOption('oid', array_column($result_owner, 'id'));
            echo $form->showDropdownBottom('');


            // STATUS
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
            } elseif ($is_active == 'LIVE') {
                $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
            } elseif ($is_active == 'ALL') {
                $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')";
            }

            if ($oid !== 0) {
                $oid_string = " AND sslc.owner_id = '$oid' ";
            } else {
                $oid_string = "";
            }
            $did_string = DomainMOD\SslList::domainCondition($did);
            if ($sslpid !== 0) {
                $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' ";
            } else {
                $sslpid_string = "";
            }
            if ($sslpaid !== 0) {
                $sslpaid_string = " AND sslc.account_id = '$sslpaid' ";
            } else {
                $sslpaid_string = "";
            }
            if ($ssltid !== 0) {
                $ssltid_string = " AND sslc.type_id = '$ssltid' ";
            } else {
                $ssltid_string = "";
            }
            if ($sslipid !== 0) {
                $sslipid_string = " AND sslc.ip_id = '$sslipid' ";
            } else {
                $sslipid_string = "";
            }
            if ($sslpcid !== 0) {
                $sslpcid_string = " AND sslc.cat_id = '$sslpcid' ";
            } else {
                $sslpcid_string = "";
            }
            if ($_SESSION['s_start_date'] != '' && $_SESSION['s_start_date'] != 'YYYY-MM-DD') {
                $range_string = " AND (sslc.expiry_date >= " . $pdo->quote($_SESSION['s_start_date']) . " AND sslc.expiry_date <= " . $pdo->quote($_SESSION['s_end_date']) . ")";
            } else {
                $range_string = "";
            }
            // The list's own keyword condition, which matches the domain in a subquery, so this menu doesn't need to
            // join the domains
            $search_string = $ssl_list->filterParts($list_state, $_SESSION['s_search_for_ssl'], '', '')['search_string'];

            $result_active = $menu_rows("
                SELECT sslc.active, count(*) AS total_count
                FROM ssl_certs AS sslc
                WHERE sslc.id != '0'
                  $oid_string
                  $did_string
                  $sslpid_string
                  $sslpaid_string
                  $ssltid_string
                  $sslipid_string
                  $sslpcid_string
                  $range_string
                  $search_string
                  {$cf_menu['sql']}
                GROUP BY sslc.active
                ORDER BY sslc.active asc");

            echo $form->showDropdownTop('is_active', _('Status'), '', '', '');
            echo $form->showDropdownOption('LIVE', _('Live SSL Certificates') . ' (' . _('Active') . ' / ' . _('Pending') . ')', 'LIVE');
            foreach ($result_active as $row_active) {

                $display_text = $row_active->active;

                if (isset($status_names[(string) $row_active->active])) $display_text = $status_names[(string) $row_active->active];

                /* TODO: This needs to be fixed, but it's going to be a very big refactoring job, and this is the best temporary band-aid solution. */
                /*
                 * The problem is that the showDropdownOptionJump method uses the == comparison operator instead of ===,
                 * so 0 technically equals "LIVE", and Expired gets selected in the dropdown menu by default, since it
                 * overrides the actual "LIVE" option.
                 *
                 * It would be easy to just change the == operator to ===, however this may break other instances of
                 * the method, so to fix this properly it's going to take some time.
                 *
                 * This issue also exists on the main Domains page.
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

            echo $form->showInputText('daterange', _('Expiring Between'), '', $daterange, '23', '', '', '', '');

            // CUSTOM FIELDS
            echo $cf->panelHtml($form, $cf_state); ?>

            <div class="d-flex flex-wrap gap-2 domainmod-filter-actions">
                <?php echo $form->showSubmitButton(_('Apply filters'), '', ''); ?>
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($web_root . '/ssl/index.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo _('Clear filters'); ?></a>
            </div><?php

            echo $form->showFormBottom(''); ?>
    </div><?php

    echo $filter_toolbar->panelBottom($filter_expanded);

}

if ($result) {

    /*
     * The page's actions, drawn in the page header (see Layout::renderPageActions()). Export's link carries the filters
     * the list actually used, from the filter toolbar's state (FilterToolbar::exportUrl()), since the filter form posts
     * them and they'd otherwise never reach the export. Each active filter is written out (so an inactive-only list
     * keeps is_active=0), and the keyword and date range always are, empty when off. Both links keep their ids, since
     * js/server-list.js adds the table's search box to them (dt_search). Layout leaves the Add button out for read-only
     * users.
     */
    $layout->setPageAddAction(_('Add SSL Certificate'), 'add.php');
    if ($ssl_import_available) $layout->addPageAddMenuItem(_('Add SSL Certificates Automatically'), $web_root . '/queue/add/ssl.php', 'bi-magic');
    // The Raw List link always names the list it was made for (see raw.php)
    $raw_list_url = $web_root . '/raw.php?' . http_build_query(array(
        'dt_list' => DomainMOD\SslList::savedListKey($raw_list_from_sql, $list_filter_params), 'dt_type' => 'ssl-certs',
        'dt_cfk' => $cf->filterKey(array_keys($cf_keys))));
    $layout->addPageAction(_('Raw List'), $raw_list_url, 'bi-list-ul', array('target' => '_blank', 'id' => 'ssl-list-raw'));
    $layout->addPageAction(_('Export'), $filter_toolbar->exportUrl(), 'bi-download', array('id' => 'ssl-list-export')); ?>

    <strong><?php echo _('Number of SSL Certificates'); ?>:</strong> <?php echo number_format($total_rows); ?><BR>
    <strong><?php echo _('Total Cost'); ?>:</strong> <?php echo htmlentities($grand_total, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlentities($_SESSION['s_default_currency'], ENT_QUOTES, 'UTF-8'); ?><BR><BR><?php

    if ($total_rows) {

        /*
         * Server-side processing (see js/server-list.js): the columns and cells come from SslListColumns, as the
         * endpoint's do, and the table carries what the script needs, the endpoint's address with the list's whole state
         * (so another tab's filters can't change this tab's rows), the columns, the counts, the page, and its size.
         */
        $list_state_url = $filter_toolbar->stateUrl();
        $list_query = (string) parse_url($list_state_url, PHP_URL_QUERY);
        $list_data_url = $web_root . '/ssl/list-data.php?' . ($list_query !== '' ? $list_query . '&' : '')
            . http_build_query(array('layout' => $list_columns->layoutKey()));
        $list_config = array(
            'url' => $list_data_url,
            // Each column carries its class (ListColumns::columnClass()) for the list's styles
            // (css/datatables/ssl-main.css), which DataTables adds to the cells it draws
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
                'paging' => sprintf(_('Pages of %s'), _('SSL Certificates')),
                'scroll' => _('SSL Certificates'),
            ),
            'signinUrl' => $web_root . '/',
            // Where each Reload link goes: the list's whole state when Display Settings changed, and the state without the
            // custom field filters when one of their fields changed (see js/server-list.js)
            'reloadUrl' => $list_state_url,
            'filterReloadUrl' => $filter_toolbar->stateUrl('search_for', 'daterange', array(), array_keys($cf_keys)),
            // The links that follow the search box (by id), and what each carries with it (see raw.php)
            'searchLinks' => array(
                'ssl-list-raw' => array('dt_layout' => $list_columns->layoutKey()),
                'ssl-list-export' => array('dt_layout' => $list_columns->layoutKey()),
            ),
        );
        $today = $time->userToday(); // for marking past expiry dates, in the user's time zone ?>

        <div id="<?php echo $slug; ?>-message"></div>
        <?php // The table's scroll box, for when JavaScript is off (js/server-list.js gives DataTables its own) ?>
        <div class="domainmod-list-scroll" tabindex="0" role="region" aria-label="<?php echo htmlspecialchars(_('SSL Certificates'), ENT_QUOTES, 'UTF-8'); ?>">
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
        <?php echo $layout->listPager($total_rows, $list_start, $list_length, $list_state_url, sprintf(_('Pages of %s'), _('SSL Certificates'))); ?>
        <BR><?php

    }

} else {

    $ready_for_ssl_certs = $ready_for_ssl_certs ?? 0;

    // With no certificates at all, the "No SSL certificates yet" empty state above already offers the Add button
    if ($ready_for_ssl_certs === 1 && $_SESSION['s_has_ssl_cert'] == '1') {

        $layout->setPageAddAction(_('Add SSL Certificate'), 'add.php');
        if ($ssl_import_available) $layout->addPageAddMenuItem(_('Add SSL Certificates Automatically'), $web_root . '/queue/add/ssl.php', 'bi-magic');

    }

    if ($_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_ssl_cert'] == '1') {

        // Certificates exist but none are listed. Which empty state shows depends on why (see the empty states plan).
        $ssl_filters_active = $oid !== 0 || $did !== 0 || $sslpid !== 0 || $sslpaid !== 0 || $ssltid !== 0 || $sslipid !== 0
            || $sslpcid !== 0 || $list_has_range || $list_has_search || $is_active != 'LIVE' || $cf_state !== array();

        // The list only shows certificates that have a fee (it joins the fees to work out costs), so these counts
        // leave the fees out, to tell "nothing matches" apart from "hidden until they have a fee"
        $ssl_without_fee_join = "
            FROM ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id, ssl_accounts AS sslpa,
                 ssl_providers AS sslp, owners AS o, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat,
                 ssl_cert_field_data AS sslfd
            WHERE sslc.account_id = sslpa.id
              AND sslpa.ssl_provider_id = sslp.id
              AND sslpa.owner_id = o.id
              AND sslc.type_id = sslcf.id
              AND sslc.ip_id = ip.id
              AND sslc.cat_id = cat.id
              AND sslc.id = sslfd.ssl_id";

        // Read-only users can't add fees, so their link only offers to show the missing ones
        $missing_fees_label = ($_SESSION['s_read_only'] ?? '') == '1' ? _('View the missing fees') : _('Add the missing fees');

        if (!$ssl_filters_active) {

            $live_certs = (int) $pdo->query("SELECT count(*) FROM ssl_certs WHERE active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')")->fetchColumn();

            if ($live_certs === 0) {

                echo $layout->emptyState('bi-archive', _('No live SSL certificates'), _('Your SSL certificates are all expired or inactive.'), '', '', '/ssl/index.php?is_active=ALL', _('Show all SSL certificates'));

            } else {

                echo $layout->emptyState('bi-cash-coin', _('Some SSL certificates are hidden until they have a fee'), _("SSL certificates without a fee can't be costed, so they don't appear in the list yet."), '', '', '/reporting/ssl/provider-fees.php?all=0#missing', $missing_fees_label);

            }

        } else {

            $stmt = $pdo->prepare("SELECT count(*)" . $ssl_without_fee_join . $list_filter_sql);
            $stmt->execute($list_filter_params);
            $filtered_certs = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($filtered_certs > 0) {

                echo $layout->emptyState('bi-cash-coin', _('Some SSL certificates are hidden until they have a fee'), _("SSL certificates without a fee can't be costed, so they don't appear in the list yet."), '', '', '/reporting/ssl/provider-fees.php?all=0#missing', $missing_fees_label);

            } else {

                echo $layout->emptyState('bi-search', _('No SSL certificates match your filters'), _('Try changing or clearing the filters.'), '', '', '/ssl/index.php', _('Clear filters'));

            }

        }

    }

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<?php require_once DIR_INC . '/layout/date-range-picker-footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/filter-auto-apply.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl('js/server-list.js'); ?>"></script>
</body>
</html>
