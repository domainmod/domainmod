<?php
/**
 * /hosting/index.php
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
require_once DIR_INC . '/settings/hosting-main.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Before the database update there are no hosting tables, so the page only says when Hosting becomes available
if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $datatable_options = '';
    require_once DIR_INC . '/doctype.inc.php';
    echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<p><?php echo htmlspecialchars(DomainMOD\Hosting::unavailableMessage(), ENT_QUOTES, 'UTF-8'); ?></p>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html><?php
    exit;

}

// Rates for every currency in use, or the list's totals would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');

$export_data = (int) ($_GET['export_data'] ?? 0);
$oid = (int) ($_REQUEST['oid'] ?? 0);
$hid = (int) ($_REQUEST['hid'] ?? 0);
$hpaid = (int) ($_REQUEST['hpaid'] ?? 0);
$htid = (int) ($_REQUEST['htid'] ?? 0);
$hterm = DomainMOD\HostingList::checkedTerm($_REQUEST['hterm'] ?? 0);
// The IP Address filter also takes HostingList::NO_IP (services with no IP address), and any other negative number is
// no filter, as on the list's data endpoint
$hipid = DomainMOD\HostingList::checkedIpId($_REQUEST['hipid'] ?? 0);
$hcid = (int) ($_REQUEST['hcid'] ?? 0);
$is_active = $_REQUEST['is_active'] ?? '';
$search_for = isset($_REQUEST['search_for']) && is_string($_REQUEST['search_for']) ? $sanitize->text($_REQUEST['search_for']) : '';
$from_dropdown = (int) ($_REQUEST['from_dropdown'] ?? 0);
$expand = (int) ($_REQUEST['expand'] ?? 0);
$daterange = isset($_REQUEST['daterange']) && is_string($_REQUEST['daterange']) ? $sanitize->text($_REQUEST['daterange']) : '';

list($new_start_date, $new_end_date) = $date->splitAndCheckRange($daterange);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $from_dropdown = 0;

    if ($new_start_date > $new_end_date) {

        $_SESSION['s_message_danger'] .= _('The date range is invalid.') . '<BR>';

    }

}

if ($export_data !== 1) {

    if ($from_dropdown !== 1) {

        $_SESSION['s_search_for_hosting'] = $search_for;

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
// up in the file. A link without them falls back to the session.
$list_search_for = $_SESSION['s_search_for_hosting'] ?? '';
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

// Anything else falls back to the default
if (!is_string($is_active) || !in_array($is_active, DomainMOD\HostingList::VALID_IS_ACTIVE, true)) $is_active = 'LIVE';

// Where an export that can't be made sends the user back: the list, with the export's filters. When an export link
// leaves out the keyword or the date range, the export uses the saved one, so the saved value is added here for the
// list to keep. from_dropdown is left out, since it would make the list ignore them.
$back_filters = array_diff_key($_GET, array_flip(array('export_data', 'dt_search', 'dt_layout', 'from_dropdown')));
if (!array_key_exists('search_for', $back_filters)) $back_filters['search_for'] = htmlspecialchars_decode($list_search_for, ENT_QUOTES);
if (!array_key_exists('daterange', $back_filters)) $back_filters['daterange'] = $daterange;

// The list's filters as SQL, from the same class as the list's data endpoint (hosting/list-data.php), so the first
// page, the pages fetched afterwards, the counts, Export, and the Raw List all agree (see HostingList::filterParts()).
//
// The list's custom fields are read once, so the SELECT, the columns, Export, and the custom field filters all describe
// the same fields, and the custom field filters come from the request only, as on the SSL Certificates list
$hsfd_fields = $customField->getFilterFields('hosting_service_fields');
$cf = new DomainMOD\CustomFieldFilter($hsfd_fields, 'hsfd');
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

$hosting_list = new DomainMOD\HostingList($pdo, $_SESSION['s_user_id'], $hsfd_fields);
$list_state = array('oid' => $oid, 'hid' => $hid, 'hpaid' => $hpaid, 'htid' => $htid, 'hterm' => $hterm,
    'hipid' => $hipid, 'hcid' => $hcid, 'is_active' => $is_active);
$filter_parts = $hosting_list->filterParts($list_state, $list_search_for, $list_start_date, $list_end_date);

// Export and the Raw List come out in the list's default order (HostingList::EXPORT_ORDER_SQL). The Raw List also keeps
// the query without its order, so it can add the table's search box when its link carries one, and without its columns,
// so it can read only the names, in chunks (see raw.php).
$list_filter_sql = DomainMOD\HostingList::filterSql($filter_parts) . $cf_filter['sql'];
$list_filter_params = $cf_filter['params'];
$raw_list_from_sql = $hosting_list->fromSql() . $list_filter_sql;
$raw_list_sql = $hosting_list->selectSql() . $raw_list_from_sql;
$_SESSION['s_raw_list_type'] = 'hosting';
$_SESSION['s_raw_list_sql'] = $raw_list_sql . DomainMOD\HostingList::EXPORT_ORDER_SQL;
$_SESSION['s_raw_list_unordered_sql'] = $raw_list_sql;
$_SESSION['s_raw_list_from_sql'] = $raw_list_from_sql;
// The custom field filters' bound values, and their keys, for raw.php's checks (see raw.php)
$_SESSION['s_raw_list_params'] = $list_filter_params;
$_SESSION['s_raw_list_filter_keys'] = array_keys($cf_keys);

$list_has_range = $filter_parts['range_string'] != '';
$list_has_search = $filter_parts['search_string'] != '';

// The table search the address carries (dt_search, with the column layout it was made for, dt_layout), so the list
// opens on the same search (js/server-list.js adds it to the Export and Raw List links)
$table_search_for = isset($_GET['dt_search']) && is_string($_GET['dt_search']) ? $_GET['dt_search'] : '';
$table_search_layout = isset($_GET['dt_layout']) && is_string($_GET['dt_layout']) ? $_GET['dt_layout'] : '';
$table_search = $hosting_list->tableSearchSql($table_search_for, $table_search_layout,
    DomainMOD\HostingListColumns::sessionCopy($_SESSION));

// How many hosting services the filters match, and their total yearly cost in the user's currency (the same joins and
// filters as the list), with how many of them have no fee, which the total leaves out. Export reads its own inside its
// snapshot, so they aren't read twice.
if ($export_data !== 1) {

    // A search made for other display settings can't be matched, so the list opens without it (the first draw's links
    // and pager then follow the list as it's shown)
    if ($table_search['stale']) {

        $_SESSION['s_message_danger'] .= _('Your display settings have changed since the list was loaded. Please search the list again.') . '<BR>';

    }
    if ($table_search['sql'] === '') $table_search_for = '';

    // The total and the count the table's search leaves, as the list's data endpoint reads them
    list($total_rows, $filtered_rows) = $hosting_list->counts($list_filter_sql, $table_search, $list_filter_params);

    $stmt = $pdo->prepare(DomainMOD\HostingList::totalsSql() . $hosting_list->fromSql() . $list_filter_sql);
    $stmt->execute($list_filter_params);
    $list_totals = $stmt->fetch();
    $stmt->closeCursor();
    $no_fee_count = (int) $list_totals->no_fee_count;

    $grand_total = $currency->format($list_totals->grand_total, $_SESSION['s_default_currency_symbol'],
        $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

}

// The status names, as the status menu shows them, so the menu, the status chip, and the export always match. Any other
// stored value shows as its number.
$status_names = array();
foreach (DomainMOD\Hosting::statusOptions() as $status_value => $status_label) $status_names[(string) $status_value] = $status_label;
$status_names['LIVE'] = _('Live Hosting Services') . ' (' . _('Active') . ' / ' . _('Pending') . ')';
$status_names['ALL'] = DomainMOD\Format::toUpper(_('All'));

if ($export_data == 1) {

    // Where an export that can't be made sends the user back (see $back_filters)
    $back_url = 'index.php?' . http_build_query($back_filters);

    // A streamed export (see Export::begin()), read and written in chunks, so PHP holds only a chunk of the rows at once
    $export = new DomainMOD\Export();
    $export->begin($back_url);

    // The table's search box (see $table_search above), when the link carries it, so the export holds the rows the
    // table shows. A link without it exports what the filters match.
    //
    // The display settings changed after the table was drawn, so its search can't be matched: back to the list, with
    // its filters, to search again
    if ($table_search['stale']) {

        $_SESSION['s_message_danger'] .= _('Your display settings have changed since the list was loaded. Please search the list again.') . '<BR>';
        header('Location: ' . $back_url);
        exit;

    }

    try {

        // The header block's filters, read before the snapshot below (but inside the try, so an error still goes to
        // Export::fail()), since the name lookups write to the log when a name is missing, and nothing can write inside
        // the snapshot
        $search_filter_rows = array();
        $search_filter_rows[] = array('[' . _('Search Filters') . ']');

        if ($list_search_for != "") {

            $search_filter_rows[] = array(_('Keyword Search') . ':', $list_search_for);

        }

        if ($table_search['sql'] !== '') {

            $search_filter_rows[] = array(_('Table Search') . ':', $export->protectFormula($table_search_for));

        }

        // The other id filters' names, each read with one small query (a name that's gone is left out, as on the SSL
        // Certificates list)
        $export_filter_name = function ($sql, $id) use ($pdo) {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue('id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $name = $stmt->fetchColumn();
            $stmt->closeCursor();
            return $name;
        };
        $export_filters = array(
            array($hid, _('Hosting Provider'), "SELECT `name` FROM hosting WHERE id = :id"),
            array($hpaid, _('Hosting Account'), "
                SELECT CONCAT(h.name, ' - ', o.name, ' - ', hpa.username)
                FROM hosting_provider_accounts AS hpa, hosting AS h, owners AS o
                WHERE hpa.hosting_id = h.id
                  AND hpa.owner_id = o.id
                  AND hpa.id = :id"),
            array($htid, _('Service Type'), "SELECT type FROM hosting_types WHERE id = :id"),
            array($hipid, _('IP Address'), "SELECT CONCAT(`name`, ' (', ip, ')') FROM ip_addresses WHERE id = :id"),
            array($hcid, _('Category'), "SELECT `name` FROM categories WHERE id = :id"),
            array($oid, _('Owner'), "SELECT `name` FROM owners WHERE id = :id"));
        foreach ($export_filters as $export_filter) {

            if ($export_filter[0] <= 0) continue;
            $temp_name = $export_filter_name($export_filter[2], $export_filter[0]);
            if ($temp_name !== false) $search_filter_rows[] = array($export_filter[1] . ':', $temp_name);

        }

        if ($hipid === DomainMOD\HostingList::NO_IP) {

            $search_filter_rows[] = array(_('IP Address') . ':', _('None'));

        }

        if ($hterm !== 0) {

            $search_filter_rows[] = array(_('Billing Term') . ':', DomainMOD\Hosting::termLabel($hterm));

        }

        if ($list_start_date != '' && $list_start_date != 'YYYY-MM-DD') {

            $search_filter_rows[] = array(_('Renewal Date Range') . ':', sprintf(_('%1$s to %2$s'), $list_start_date, $list_end_date));

        }

        // The custom field filters, by field name, as their chips show them (names and values are the user's own text)
        foreach ($cf->describe($cf_state) as $cf_described) {

            $search_filter_rows[] = array($export->protectFormula($cf_described['name'] . ':'),
                $export->protectFormula($cf_described['label']));

        }

        $search_filter_rows[] = array(_('Status') . ':', $status_names[(string) $is_active] ?? $is_active);

        // Everything from here to the last row reads one snapshot of the database, so the header's numbers and the
        // rows always agree, however the data changes in the meantime
        $export->startSnapshot($hosting_list);

        // The custom fields as the snapshot has them, which must be the ones the page read, then the full row query
        // once with LIMIT 0, so a column that's gone fails here, before the file starts, rather than in a chunk
        if (!$cf->sameFields(DomainMOD\CustomField::readFilterFields($pdo, 'hosting_service_fields'))) {

            throw new RuntimeException('The custom fields changed while the export was being made');

        }
        $hosting_list->exportPreflight($list_filter_sql . $table_search['sql'], $table_search['params'] + $list_filter_params);

        // The export's count and total yearly cost, for the rows it holds (with a table search, the rows it matches)
        $stmt = $pdo->prepare("SELECT count(*) AS total_rows, SUM(" . DomainMOD\Hosting::yearlyCostSql('hs', 'hs.total_cost * cc.conversion')
            . ") AS grand_total" . $hosting_list->fromSql() . $list_filter_sql . $table_search['sql']);
        $stmt->execute($table_search['params'] + $list_filter_params);
        $export_totals = $stmt->fetch();
        $stmt->closeCursor();
        $total_rows = (int) $export_totals->total_rows;
        $grand_total = $currency->format($export_totals->grand_total, $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

        // The custom fields' columns, headings, and types all come from the page's one read of them (checked against
        // the snapshot above)
        $hsfd_types = array_column($hsfd_fields, 'type_id', 'field_name');
        $hsfd_columns_array = array_column($hsfd_fields, 'field_name');

        // The creation types and users, read once rather than once for each hosting service
        $creation_type_names = DomainMOD\Export::creationTypeNames($pdo);
        $user_names = DomainMOD\Export::userNames($pdo);

        $export_file = $export->openStream(_('hosting_results'), strtotime($time->stamp()));

        $export->writeRow($export_file, array(_('Hosting Services Search Results Export')));
        $export->writeBlankRow($export_file);
        $export->writeRow($export_file, array(_('Number of Hosting Services') . ':', $total_rows));
        $export->writeRow($export_file, array(_('Total Yearly Cost') . ':', $grand_total, $_SESSION['s_default_currency']));
        $export->writeBlankRow($export_file);

        foreach ($search_filter_rows as $row_contents) {

            $export->writeRow($export_file, $row_contents);

        }

        $export->writeBlankRow($export_file);

        // The fees in the user's currency, then in the fee's own currency
        $row_contents = array(
            _('Status'),
            _('Renewal Date'),
            _('Renews On Day'),
            _('Billing Term'),
            _('Auto Renew'),
            _('Initial Fee'),
            _('Renewal Fee'),
            _('Misc Fee'),
            _('Cost Per Term'),
            _('Yearly Cost'),
            sprintf(_('Initial Fee (%s)'), _('Fee Currency')),
            sprintf(_('Renewal Fee (%s)'), _('Fee Currency')),
            sprintf(_('Misc Fee (%s)'), _('Fee Currency')),
            sprintf(_('Cost Per Term (%s)'), _('Fee Currency')),
            _('Fee Currency'),
            _('Name'),
            _('Hostname'),
            _('Hosting Provider'),
            _('Hosting Account'),
            _('Service Type'),
            _('IP Address Name'),
            _('IP Address'),
            _('IP Address rDNS'),
            _('Category'),
            _('Owner'),
            _('Notes'),
            _('Creation Type'),
            _('Created By'),
            _('Inserted'),
            _('Updated'),
            DomainMOD\Format::toUpper(_('Custom Fields')));

        // The headings are the fields' names, the user's own text
        foreach ($hsfd_fields as $row_field) {

            $row_contents[] = $export->protectFormula($row_field['name']);

        }

        $export->writeRow($export_file, $row_contents);

        $export->chunkWritten();

        $money = function ($amount) use ($currency) {
            return $currency->format($amount, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'],
                $_SESSION['s_default_currency_symbol_space']);
        };

        foreach ($hosting_list->exportChunks($list_filter_sql . $table_search['sql'], $table_search['params'] + $list_filter_params) as $result) {

            foreach ($result as $row) {

                // A service with no fee has empty fee cells, as the other exports show a missing value, and with a fee
                // but no conversion rate, the amounts in the user's currency are empty (never the zero that multiplying
                // by a missing rate would give), while the fee's own-currency amounts are kept
                $has_fee = $row->f_id !== null;
                $has_rate = $has_fee && $row->conversion !== null;
                $converted = function ($amount) use ($money, $row, $has_rate) {
                    return $has_rate ? $money($amount * $row->conversion) : '';
                };
                $yearly_cost = $has_rate ? DomainMOD\Hosting::yearlyCost($row->total_cost * $row->conversion, (string) $row->billing_term) : null;

                $created_by = $row->created_by == '0' ? _('Unknown') : DomainMOD\Export::userName($user_names, $row->created_by);

                $row_contents = array(
                    DomainMOD\Format::toUpper($status_names[(string) $row->active] ?? (string) $row->active),
                    $row->expiry_date,
                    $row->renewal_day,
                    DomainMOD\Hosting::validTerm((string) $row->billing_term) ? DomainMOD\Hosting::termLabel($row->billing_term) : $row->billing_term,
                    $row->autorenew == '1' ? _('Yes') : _('No'),
                    $converted($row->initial_fee),
                    $converted($row->renewal_fee),
                    $converted($row->misc_fee),
                    $converted($row->total_cost),
                    $yearly_cost === null ? '' : $money($yearly_cost),
                    $has_fee ? $row->initial_fee : '',
                    $has_fee ? $row->renewal_fee : '',
                    $has_fee ? $row->misc_fee : '',
                    $has_fee ? $row->total_cost : '',
                    $has_fee ? $row->fee_currency : '',
                    $row->name,
                    $row->hostname,
                    $row->hosting_provider_name,
                    $row->hosting_provider_name . ', ' . $row->owner_name . ' (' . $row->username . ')',
                    $row->type,
                    $row->ip_id === null ? _('None') : $row->ip_name,
                    $row->ip_id === null ? '' : $row->ip,
                    $row->ip_id === null ? '' : $row->rdns,
                    $row->cat_name,
                    $row->owner_name,
                    $row->notes,
                    DomainMOD\Export::creationTypeName($creation_type_names, $row->creation_type_id),
                    $created_by,
                    $time->toUserTimezone($row->insert_time),
                    $time->toUserTimezone($row->update_time),
                    '');

                foreach ($hsfd_columns_array as $column) {

                    // An empty Date or Time Stamp field is blank, as the list shows it
                    if (in_array((string) ($hsfd_types[$column] ?? ''), array('4', '5'), true) && DomainMOD\Time::isUnset($row->{DomainMOD\CustomField::listAlias($column)})) {

                        $row_contents[] = '';

                    } else {

                        $row_contents[] = $row->{DomainMOD\CustomField::listAlias($column)};

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
// Double check to make sure there are still no hosting services or hosting accounts, which the empty states below
// depend on. Whether any providers exist is read each time.
$queryB = new DomainMOD\QueryBuild();
foreach (array('s_has_hosting_service' => 'hosting_services', 's_has_hosting_provider_account' => 'hosting_provider_accounts') as $asset_flag => $asset_table) {

    if (($_SESSION[$asset_flag] ?? '0') == '0') {

        $_SESSION[$asset_flag] = $system->checkForRows($queryB->singleAsset($asset_table));

    }

}
$has_hosting_provider = $system->checkForRows($queryB->singleAsset('hosting'));

// Before anything exists, the list walks a new user through the order things are added in: a hosting provider, then an
// account at it, then hosting services, one step at a time (Layout::emptyState())
if ($has_hosting_provider != '1') {

    echo $layout->emptyState('bi-hdd-network', _('Add a hosting provider first'), _('Hosting services belong to a hosting account, so start by adding the company you host with.'), '/assets/add/host.php', _('Add Hosting Provider'), '', '', 2, true);

}

if ($_SESSION['s_has_hosting_provider_account'] != '1' && $has_hosting_provider == '1') {

    echo $layout->emptyState('bi-person-badge', _('Add a hosting account next'), _('Add the account you hold at that hosting provider.'), '/assets/add/hosting-provider-account.php', _('Add Hosting Account'), '', '', 2, true);

}

$ready_for_hosting_services = 0;

if ($_SESSION['s_has_hosting_service'] != '1' && $has_hosting_provider == '1' && $_SESSION['s_has_hosting_provider_account'] == '1') {

    echo $layout->emptyState('bi-hdd-stack', _('No hosting services yet'), _('Add the hosting services you want to keep track of.'), '/hosting/add.php', _('Add Service'), '', '', 2, true);
    $ready_for_hosting_services = 1;

} elseif ($_SESSION['s_has_hosting_service'] == '1' && $has_hosting_provider == '1' && $_SESSION['s_has_hosting_provider_account'] == '1') {

    $ready_for_hosting_services = 1;

}

$hosting_ready = $has_hosting_provider == '1' && $_SESSION['s_has_hosting_provider_account'] == '1' && $_SESSION['s_has_hosting_service'] == '1';

/*
 * Server-side processing: the page prints one page of rows, in the default order, and DataTables fetches the others
 * from hosting/list-data.php (see js/server-list.js). The page and its size come from the address (the no-JavaScript
 * pager's links), checked as the endpoint checks them, and a page past the end moves to the last one. A page always
 * starts on a multiple of its size, as DataTables' own paging does. The table's search from the address (see
 * $table_search) applies to the rows and the pager, as the endpoint applies the search box.
 */
$list_columns = new DomainMOD\HostingListColumns(DomainMOD\HostingListColumns::sessionCopy($_SESSION), $hsfd_fields);
$list_page_sizes = DomainMOD\HostingList::pageSizes($_SESSION['s_number_of_hosting_services'] ?? 50);
$list_length = DomainMOD\HostingList::checkedLength($_GET['length'] ?? '', $list_page_sizes);
$list_start = DomainMOD\HostingList::checkedStart($_GET['start'] ?? 0);
$list_start -= $list_start % $list_length;
if ($list_start >= $filtered_rows) $list_start = $filtered_rows > 0 ? (int) (floor(($filtered_rows - 1) / $list_length) * $list_length) : 0;

$result = $filtered_rows > 0 ? $hosting_list->rows($list_filter_sql, $table_search,
    DomainMOD\HostingList::orderSql(array(), $list_columns->sortMap(), $list_columns->defaultOrder()), $list_start,
    $list_length, $list_filter_params) : array();

/*
 * The filter toolbar's state (DomainMOD\FilterToolbar, shared with the Domains and SSL Certificates lists): every active
 * filter with its plain-text name for its chip. Each id filter's name is read with one small query, only when that
 * filter is on, and an id that no longer exists still gets a chip.
 */
$filter_toolbar = new DomainMOD\FilterToolbar('hosting-filters', $web_root . '/hosting/index.php', $web_root . '/hosting/index.php');
$filter_name = function ($sql, $id) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $name = $stmt->fetchColumn();
    $stmt->closeCursor();
    return $name === false ? DomainMOD\FilterToolbar::notFound() : DomainMOD\FilterToolbar::decode($name);
};
if ($hid !== 0) $filter_toolbar->addFilter('hid', $hid, 0, $filter_name("SELECT `name` FROM hosting WHERE id = :id", $hid), _('Hosting Provider'));
if ($hpaid !== 0) $filter_toolbar->addFilter('hpaid', $hpaid, 0, $filter_name("
    SELECT CONCAT(h.name, ', ', o.name, ' (', hpa.username, ')')
    FROM hosting_provider_accounts AS hpa, hosting AS h, owners AS o
    WHERE hpa.hosting_id = h.id
      AND hpa.owner_id = o.id
      AND hpa.id = :id", $hpaid), _('Hosting Account'));
if ($htid !== 0) $filter_toolbar->addFilter('htid', $htid, 0, $filter_name("SELECT type FROM hosting_types WHERE id = :id", $htid), _('Service Type'));
if ($hterm !== 0) $filter_toolbar->addFilter('hterm', $hterm, 0, DomainMOD\Hosting::termLabel($hterm), _('Billing Term'));
if ($hipid === DomainMOD\HostingList::NO_IP) {
    $filter_toolbar->addFilter('hipid', $hipid, 0, _('None'), _('IP Address'));
} elseif ($hipid !== 0) $filter_toolbar->addFilter('hipid', $hipid, 0, $filter_name("SELECT CONCAT(`name`, ' (', ip, ')') FROM ip_addresses WHERE id = :id", $hipid), _('IP Address'));
if ($hcid !== 0) $filter_toolbar->addFilter('hcid', $hcid, 0, $filter_name("SELECT `name` FROM categories WHERE id = :id", $hcid), _('Category'));
if ($oid !== 0) $filter_toolbar->addFilter('oid', $oid, 0, $filter_name("SELECT `name` FROM owners WHERE id = :id", $oid), _('Owner'));
$filter_toolbar->addFilter('is_active', $is_active, 'LIVE', $status_names[$is_active] ?? $is_active, _('Status'));
if ($list_search_for != '') {
    $filter_keyword = DomainMOD\FilterToolbar::decode($list_search_for);
    $filter_toolbar->addFilter('search_for', $filter_keyword, '', '"' . $filter_keyword . '"', _('Keyword Search'));
}
if ($list_start_date != '' && $list_start_date != 'YYYY-MM-DD') {
    $filter_toolbar->addFilter('daterange', $list_start_date . ' - ' . $list_end_date, '',
        sprintf(_('Renewing: %1$s to %2$s'), $list_start_date, $list_end_date));
}
// The custom field filters' chips, and the keys that let the next request check their fields: the field set's (cff)
// on every address, and the filters' own (cfk) on the state and export addresses
$cf->addChips($filter_toolbar, $cf_state);
if ($cf_state) {
    $filter_toolbar->carry('cff', $cf->fieldsKey());
    $filter_toolbar->carryState('cfk', $cf->filterKey(array_keys($cf_keys)));
}
// The date range picker's inputs (see date-range-picker-footer.inc.php): Renewing Between and the date custom fields
$date_range_fields = array_merge(array('daterange'), $cf->dateKeys());

/*
 * The filter menus: each lists the choices the other filters leave, from the list's own conditions, which are all
 * written on the hosting service table (see HostingList::filterParts()), with its own filter left out. The custom field
 * filters are matched by the hosting service's id, as the SSL Certificates list's menus match them.
 */
$cf_menu = $cf->subquery($cf_state, DomainMOD\HostingList::FIELD_DATA_TABLE, DomainMOD\HostingList::FIELD_DATA_ID, 'hs.id');
$menu_filters = function ($key) use ($filter_parts, $cf_menu) {
    return DomainMOD\HostingList::filterSqlWithout($filter_parts, $key) . $cf_menu['sql'];
};
$menu_rows = function ($sql) use ($pdo, $cf_menu) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($cf_menu['params']);
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
    return $rows;
};

if ($hosting_ready) {

    // The no-JavaScript page (expand=1) draws the form as a card under the toolbar instead of in the side panel
    $filter_expanded = ($expand === 1);

    echo $filter_toolbar->toolbar($filter_expanded);
    echo $filter_toolbar->panelTop($filter_expanded);

    echo $form->showFormTop(''); ?>

    <div><?php
            // HOSTING PROVIDER
            $result_provider = $menu_rows("
                SELECT h.id, h.name
                FROM hosting AS h, hosting_services AS hs
                WHERE h.id = hs.hosting_id
                  " . $menu_filters('hid') . "
                GROUP BY h.id, h.name
                ORDER BY h.name ASC");

            echo $form->showDropdownTop('hid', _('Hosting Provider'), '', '', '');
            echo $form->showDropdownOption('', _('Hosting Provider') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_provider as $row_provider) {

                echo $form->showDropdownOption($row_provider->id, $row_provider->name, $hid);

            }
            echo $filter_toolbar->keepOption('hid', array_column($result_provider, 'id'));
            echo $form->showDropdownBottom('');


            // HOSTING ACCOUNT
            $result_account = $menu_rows("
                SELECT hpa.id AS hpa_id, hpa.username, h.name AS h_name, o.name AS owner_name
                FROM hosting_provider_accounts AS hpa, hosting AS h, owners AS o, hosting_services AS hs
                WHERE hpa.hosting_id = h.id
                  AND hpa.owner_id = o.id
                  AND hpa.id = hs.account_id
                  " . $menu_filters('hpaid') . "
                GROUP BY hpa.id, hpa.username, h.name, o.name
                ORDER BY h.name ASC, o.name ASC, hpa.username ASC");

            echo $form->showDropdownTop('hpaid', _('Hosting Account'), '', '', '');
            echo $form->showDropdownOption('', _('Hosting Account') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_account as $row_account) {

                echo $form->showDropdownOption($row_account->hpa_id, $row_account->h_name . ', ' . $row_account->owner_name . ' (' . $row_account->username . ')', $hpaid);

            }
            echo $filter_toolbar->keepOption('hpaid', array_column($result_account, 'hpa_id'));
            echo $form->showDropdownBottom('');


            // SERVICE TYPE
            $result_type = $menu_rows("
                SELECT ht.id AS type_id, ht.type
                FROM hosting_types AS ht, hosting_services AS hs
                WHERE ht.id = hs.type_id
                  " . $menu_filters('htid') . "
                GROUP BY ht.id, ht.type
                ORDER BY ht.type ASC");

            echo $form->showDropdownTop('htid', _('Service Type'), '', '', '');
            echo $form->showDropdownOption('', _('Service Type') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_type as $row_type) {

                echo $form->showDropdownOption($row_type->type_id, $row_type->type, $htid);

            }
            echo $filter_toolbar->keepOption('htid', array_column($result_type, 'type_id'));
            echo $form->showDropdownBottom('');


            // BILLING TERM (only the allowed terms are offered)
            $result_term = $menu_rows("
                SELECT hs.billing_term
                FROM hosting_services AS hs
                WHERE hs.billing_term IN (" . implode(', ', DomainMOD\Hosting::BILLING_TERMS) . ")
                  " . $menu_filters('hterm') . "
                GROUP BY hs.billing_term
                ORDER BY hs.billing_term ASC");

            echo $form->showDropdownTop('hterm', _('Billing Term'), '', '', '');
            echo $form->showDropdownOption('', _('Billing Term') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_term as $row_term) {

                echo $form->showDropdownOption($row_term->billing_term, DomainMOD\Hosting::termLabel($row_term->billing_term), $hterm);

            }
            echo $filter_toolbar->keepOption('hterm', array_column($result_term, 'billing_term'));
            echo $form->showDropdownBottom('');


            // IP ADDRESS. The services with no IP address are counted below, for the None option.
            $result_ip = $menu_rows("
                SELECT ip.id AS ip_id, ip.name AS ip_name, ip.ip
                FROM ip_addresses AS ip, hosting_services AS hs
                WHERE ip.id = hs.ip_id
                  " . $menu_filters('hipid') . "
                GROUP BY ip.id, ip.name, ip.ip
                ORDER BY ip.name, ip.ip");

            // Whether any matching service has no IP address, for the None option (HostingList::NO_IP), which is only
            // offered when it's in use, as the other options are
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM hosting_services AS hs
                WHERE hs.id != '0'
                  " . DomainMOD\HostingList::ipCondition(DomainMOD\HostingList::NO_IP) . "
                  " . $menu_filters('hipid'));
            $stmt->execute($cf_menu['params']);
            $no_ip_count = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            echo $form->showDropdownTop('hipid', _('IP Address'), '', '', '');
            echo $form->showDropdownOption('', _('IP Address') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            $ip_options = array_column($result_ip, 'ip_id');
            if ($no_ip_count > 0) {

                echo $form->showDropdownOption(DomainMOD\HostingList::NO_IP, _('None'), $hipid);
                $ip_options[] = DomainMOD\HostingList::NO_IP;

            }
            foreach ($result_ip as $row_ip) {

                echo $form->showDropdownOption($row_ip->ip_id, $row_ip->ip_name . ' (' . $row_ip->ip . ')', $hipid);

            }
            echo $filter_toolbar->keepOption('hipid', $ip_options);
            echo $form->showDropdownBottom('');


            // CATEGORY
            $result_cat = $menu_rows("
                SELECT cat.id AS cat_id, cat.name AS cat_name
                FROM categories AS cat, hosting_services AS hs
                WHERE cat.id = hs.cat_id
                  " . $menu_filters('hcid') . "
                GROUP BY cat.id, cat.name
                ORDER BY cat.name ASC");

            echo $form->showDropdownTop('hcid', _('Category'), '', '', '');
            echo $form->showDropdownOption('', _('Category') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_cat as $row_cat) {

                echo $form->showDropdownOption($row_cat->cat_id, $row_cat->cat_name, $hcid);

            }
            echo $filter_toolbar->keepOption('hcid', array_column($result_cat, 'cat_id'));
            echo $form->showDropdownBottom('');


            // OWNER
            $result_owner = $menu_rows("
                SELECT o.id, o.name
                FROM owners AS o, hosting_services AS hs
                WHERE o.id = hs.owner_id
                  " . $menu_filters('oid') . "
                GROUP BY o.id, o.name
                ORDER BY o.name ASC");

            echo $form->showDropdownTop('oid', _('Owner'), '', '', '');
            echo $form->showDropdownOption('', _('Owner') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_owner as $row_owner) {

                echo $form->showDropdownOption($row_owner->id, $row_owner->name, $oid);

            }
            echo $filter_toolbar->keepOption('oid', array_column($result_owner, 'id'));
            echo $form->showDropdownBottom('');


            // STATUS
            $result_active = $menu_rows("
                SELECT hs.active, count(*) AS total_count
                FROM hosting_services AS hs
                WHERE hs.id != '0'
                  " . $menu_filters('is_active') . "
                GROUP BY hs.active
                ORDER BY hs.active ASC");

            echo $form->showDropdownTop('is_active', _('Status'), '', '', '');
            echo $form->showDropdownOption('LIVE', $status_names['LIVE'], 'LIVE');
            foreach ($result_active as $row_active) {

                // Status 0 is compared as a string, since the menu's loose comparison would otherwise match it with "LIVE"
                // (see ssl/index.php)
                $active_value = (string) $row_active->active;
                echo $form->showDropdownOption($active_value, $status_names[$active_value] ?? $active_value, $is_active);

            }
            echo $form->showDropdownOption('ALL', $status_names['ALL'], $is_active);
            echo $filter_toolbar->keepOption('is_active', array_merge(array('LIVE', 'ALL'), array_map('strval', array_column($result_active, 'active'))));
            echo $form->showDropdownBottom('');

            echo $form->showInputText('daterange', _('Renewing Between'), '', $daterange, '23', '', '', '', '');

            // CUSTOM FIELDS
            echo $cf->panelHtml($form, $cf_state); ?>

            <div class="d-flex flex-wrap gap-2 domainmod-filter-actions">
                <?php echo $form->showSubmitButton(_('Apply filters'), '', ''); ?>
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($web_root . '/hosting/index.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo _('Clear filters'); ?></a>
            </div><?php

            echo $form->showFormBottom(''); ?>
    </div><?php

    echo $filter_toolbar->panelBottom($filter_expanded);

}

$list_state_url = $filter_toolbar->stateUrl();
$list_query = (string) parse_url($list_state_url, PHP_URL_QUERY);

// The search the page accepted, as the Export and Raw List links carry it before the table's first draw (see below)
$accepted_search = $table_search_for !== '' ? array('dt_search' => $table_search_for, 'dt_layout' => $list_columns->layoutKey()) : array();

if ($total_rows > 0) {

    /*
     * The page's actions, drawn in the page header (see Layout::renderPageActions()). Export's link carries the filters
     * the list actually used, from the filter toolbar's state (FilterToolbar::exportUrl()). Both links keep their ids,
     * since js/server-list.js adds the table's search box to them (dt_search), and both start with the search the page
     * accepted from its address, so an export made before the table's first draw holds the rows the page shows. Layout
     * leaves the Add button out for read-only users.
     */
    $layout->setPageAddAction(_('Add Service'), 'add.php');
    // The Raw List link always names the list it was made for (see raw.php)
    $raw_list_url = $web_root . '/raw.php?' . http_build_query(array(
        'dt_list' => DomainMOD\HostingList::savedListKey($raw_list_from_sql, $list_filter_params), 'dt_type' => 'hosting',
        'dt_cfk' => $cf->filterKey(array_keys($cf_keys))) + $accepted_search);
    $export_url = $filter_toolbar->exportUrl() . ($accepted_search ? '&' . http_build_query($accepted_search) : '');
    $layout->addPageAction(_('Raw List'), $raw_list_url, 'bi-list-ul', array('target' => '_blank', 'id' => 'hosting-list-raw'));
    $layout->addPageAction(_('Export'), $export_url, 'bi-download', array('id' => 'hosting-list-export')); ?>

    <strong><?php echo _('Number of Hosting Services'); ?>:</strong> <?php echo number_format($total_rows); ?><BR>
    <strong><?php echo _('Total Yearly Cost'); ?>:</strong> <?php echo htmlentities($grand_total, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlentities($_SESSION['s_default_currency'], ENT_QUOTES, 'UTF-8'); ?><BR><?php

    // The services with no fee are listed, with "No fee", but can't be costed, so the total leaves them out
    if ($no_fee_count > 0) {

        // Read-only users can't add fees, so their link only offers to show the missing ones
        $missing_fees_label = ($_SESSION['s_read_only'] ?? '') == '1' ? _('View the missing fees') : _('Add the missing fees'); ?>
        <BR><span class="domainmod-list-no-fee"><?php echo htmlspecialchars(sprintf(ngettext("%s hosting service has no fee and isn't in the total.",
            "%s hosting services have no fee and aren't in the total.", $no_fee_count), number_format($no_fee_count)), ENT_QUOTES, 'UTF-8'); ?>
        <a href="<?php echo htmlspecialchars($web_root . '/reporting/hosting/provider-fees.php?all=0#missing', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($missing_fees_label, ENT_QUOTES, 'UTF-8'); ?></a></span><BR><?php

    } ?>
    <BR><?php

    if ($total_rows) {

        /*
         * Server-side processing (see js/server-list.js): the columns and cells come from HostingListColumns, as the
         * endpoint's do, and the table carries what the script needs, the endpoint's address with the list's whole state
         * (so another tab's filters can't change this tab's rows), the columns, the counts, the page, its size, and the
         * table's search the page accepted.
         */
        $list_data_url = $web_root . '/hosting/list-data.php?' . ($list_query !== '' ? $list_query . '&' : '')
            . http_build_query(array('layout' => $list_columns->layoutKey()));
        $list_config = array(
            'url' => $list_data_url,
            // Each column carries its class (ListColumns::columnClass()) for the list's styles
            // (css/datatables/hosting-main.css), which DataTables adds to the cells it draws
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
            // Both counts the page starts with: the filters' total and what the table's search leaves of it, as the
            // endpoint returns them (recordsTotal and recordsFiltered)
            'total' => $total_rows,
            'filtered' => $filtered_rows,
            // The search the page accepted, which the table's search box starts with
            'search' => $table_search_for,
            'messages' => array(
                'error' => _("That didn't work. Please reload the page and try again."),
                'signin' => _('Your session has ended. Please sign in again.'),
                'signinLink' => _('Sign In'),
                'reload' => _('Your display settings have changed. Reload the page to see them.'),
                'filterReload' => _('A custom field in your filters was changed or deleted. Reload the page to see the list without your custom field filters.'),
                'reloadLink' => _('Reload'),
                'close' => _('Close'),
                'paging' => sprintf(_('Pages of %s'), _('Hosting Services')),
                'scroll' => _('Hosting Services'),
            ),
            'signinUrl' => $web_root . '/',
            // Where each Reload link goes: the list's whole state when Display Settings changed, and the state without the
            // custom field filters when one of their fields changed (see js/server-list.js)
            'reloadUrl' => $list_state_url,
            'filterReloadUrl' => $filter_toolbar->stateUrl('search_for', 'daterange', array(), array_keys($cf_keys)),
            // The links that follow the search box (by id), and what each carries with it (see raw.php)
            'searchLinks' => array(
                'hosting-list-raw' => array('dt_layout' => $list_columns->layoutKey()),
                'hosting-list-export' => array('dt_layout' => $list_columns->layoutKey()),
            ),
        );
        // For marking renewal dates that have passed, in the hosting time zone, as every hosting date comparison is
        $today = DomainMOD\Hosting::hostingDate($pdo); ?>

        <div id="<?php echo $slug; ?>-message"></div>
        <?php // The table's scroll box, for when JavaScript is off (js/server-list.js gives DataTables its own) ?>
        <div class="domainmod-list-scroll" tabindex="0" role="region" aria-label="<?php echo htmlspecialchars(_('Hosting Services'), ENT_QUOTES, 'UTF-8'); ?>">
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
        <?php // The pager for when JavaScript is off keeps the table search the page accepted, as the first page shows it
        $pager_url = $list_state_url . ($accepted_search ? (strpos($list_state_url, '?') === false ? '?' : '&')
            . http_build_query($accepted_search) : '');
        echo $layout->listPager($filtered_rows, $list_start, $list_length, $pager_url, sprintf(_('Pages of %s'), _('Hosting Services'))); ?>
        <BR><?php

    }

} else {

    // With no hosting services at all, the "No hosting services yet" empty state above already offers the Add button
    if ($ready_for_hosting_services === 1 && $_SESSION['s_has_hosting_service'] == '1') {

        $layout->setPageAddAction(_('Add Service'), 'add.php');

    }

    if ($hosting_ready) {

        // Hosting services exist but none are listed. Every service is listed, with or without a fee, so with no
        // filters on, the default status is hiding them all (they're all cancelled), and otherwise the filters match
        // none, or only cancelled ones (which the Status filter can show).
        $hosting_filters_active = $oid !== 0 || $hid !== 0 || $hpaid !== 0 || $htid !== 0 || $hterm !== 0 || $hipid !== 0
            || $hcid !== 0 || $list_has_range || $list_has_search || $is_active != 'LIVE' || $cf_state !== array();

        if (!$hosting_filters_active) {

            echo $layout->emptyState('bi-archive', _('No live hosting services'), _('Your hosting services are all cancelled.'), '', '', '/hosting/index.php?is_active=ALL', _('Show all hosting services'));

        } else {

            echo $layout->emptyState('bi-search', _('No hosting services match your filters'), _('Try changing or clearing the filters.'), '', '', '/hosting/index.php', _('Clear filters'));

        }

    }

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<?php require_once DIR_INC . '/layout/date-range-picker-footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/filter-auto-apply.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl('js/server-list.js'); ?>"></script>
</body>
</html>
