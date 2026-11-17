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
$did = (int) ($_REQUEST['did'] ?? 0);
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

// The list's filters as SQL, from the same class as the list's data endpoint (ssl/list-data.php), so the first page,
// the pages fetched afterwards, the counts, Export, and the Raw List all agree. The keyword is matched against the
// certificate's name, which is stored HTML-encoded, as the keyword is (see SslList::filterParts()).
$sslfd_columns = $customField->getCustomFieldsSql('ssl_cert_fields', 'sslfd');
$ssl_list = new DomainMOD\SslList($pdo, $_SESSION['s_user_id'], $sslfd_columns);
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
// query without its order, so it can add the table's search box when its link carries one (see raw.php).
$raw_list_sql = $ssl_list->selectSql() . $ssl_list->fromSql() . "
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
$sql = $raw_list_sql . DomainMOD\SslList::EXPORT_ORDER_SQL;
$_SESSION['s_raw_list_type'] = 'ssl-certs';
$_SESSION['s_raw_list_sql'] = $sql;
$_SESSION['s_raw_list_unordered_sql'] = $raw_list_sql;

// The list's filters as they are now, kept for the empty state's counts, since the filter menus below reuse these names
$list_filter_sql = $is_active_string . $oid_string . $did_string . $sslpid_string . $sslpaid_string . $ssltid_string
    . $sslipid_string . $sslpcid_string . $range_string . $search_string;
$list_has_range = $range_string != '';
$list_has_search = $search_string != '';

// How many certificates the filters match (the same joins and filters as the main query)
$total_rows = $ssl_list->count($list_filter_sql);

// The total covers exactly the listed certificates (the same joins and filters as the list and Export)
$grand_total = $pdo->query("SELECT SUM(sslc.total_cost * cc.conversion)" . $ssl_list->fromSql() . $list_filter_sql)->fetchColumn();

$grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

if ($export_data == 1) {

    // The table's search box, when the link carries it (js/server-list.js adds it), so the export holds the rows the
    // table shows. An older link without it exports what the filters match, as before.
    $table_search_for = $_GET['dt_search'] ?? '';
    $table_search = $ssl_list->tableSearchSql($table_search_for, $_GET['dt_layout'] ?? '',
        DomainMOD\SslListColumns::sessionCopy($_SESSION));

    // The display settings changed after the table was drawn, so its search can't be matched: back to the list, with
    // its filters, to search again
    if ($table_search['stale']) {

        $_SESSION['s_message_danger'] .= _('Your display settings have changed since the list was loaded. Please search the list again.') . '<BR>';
        $back_query = http_build_query(array_diff_key($_GET, array_flip(array('export_data', 'dt_search', 'dt_layout'))));
        header('Location: index.php' . ($back_query !== '' ? '?' . $back_query : ''));
        exit;

    }

    $stmt = $pdo->prepare($raw_list_sql . $table_search['sql'] . DomainMOD\SslList::EXPORT_ORDER_SQL);
    $stmt->execute($table_search['params']);
    $result = $stmt->fetchAll();

    // With a table search, the export's count and total cost are for the rows it holds
    if ($table_search['sql'] !== '') {

        $total_rows = count($result);

        $stmt = $pdo->prepare("SELECT SUM(sslc.total_cost * cc.conversion)" . $ssl_list->fromSql() . $list_filter_sql
            . $table_search['sql']);
        $stmt->execute($table_search['params']);
        $grand_total = $currency->format($stmt->fetchColumn(), $_SESSION['s_default_currency_symbol'],
            $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

    }

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('ssl_results'), strtotime($time->stamp()));

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

    $row_contents = array(
        '[' . _('Search Filters') . ']'
    );
    $export->writeRow($export_file, $row_contents);

    if ($list_search_for != "") {

        $row_contents = array(
            _('Keyword Search') . ':',
            $list_search_for
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($table_search['sql'] !== '') {

        $row_contents = array(
            _('Table Search') . ':',
            $export->protectFormula($table_search_for)
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($did > 0) {

        $domain = new DomainMOD\Domain();
        $temp_domain = $domain->getDomain($did);

        $row_contents = array(
            _('Associated Domain') . ':',
            $temp_domain
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($sslpid > 0) {

        $temp_name = $assets->getSslProvider($sslpid);

        $row_contents = array(
            _('SSL Provider') . ':',
            $temp_name
        );
        $export->writeRow($export_file, $row_contents);

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
        // Its own variable, so the certificates in $result are still there for the rows below
        $account_heading = $stmt->fetch();
        $stmt->closeCursor();

        if ($account_heading) {

            $row_contents = array(
                _('SSL Provider Account') . ':',
                $account_heading->ssl_provider_name . " - " . $account_heading->owner_name . " - " . $account_heading->username
            );
            $export->writeRow($export_file, $row_contents);


        }

    }

    if ($ssltid > 0) {

        $temp_type = $assets->getSslType($ssltid);

        $row_contents = array(
            _('SSL Type') . ':',
            $temp_type
        );
        $export->writeRow($export_file, $row_contents);


    }

    if ($sslipid > 0) {

        list($temp_ip, $temp_ip_name) = $assets->getIpAndName($sslipid);

        $row_contents = array(
            _('SSL IP Address') . ':',
            $temp_ip_name . ' (' . $temp_ip . ')'
        );
        $export->writeRow($export_file, $row_contents);

    }

    if ($sslpcid > 0) {

        $temp_category = $assets->getCat($sslpcid);

        $row_contents = array(
            _('SSL Category') . ':',
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

    if ($list_start_date != '' && $list_start_date != 'YYYY-MM-DD') {

        $row_contents = array(
            _('Expiry Date Range') . ':',
            $list_start_date  . " to " . $list_end_date
        );
        $export->writeRow($export_file, $row_contents);

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
    $export->writeRow($export_file, $row_contents);

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

    $result_field = $pdo->query("
        SELECT `name`
        FROM ssl_cert_fields
        ORDER BY `name` ASC")->fetchAll();

    if ($result_field) {

        foreach ($result_field as $row_field) {

            $row_contents[$count++] = $row_field->name;

        }

    }

    $export->writeRow($export_file, $row_contents);

    // Each custom field's type, by field name
    $sslfd_types = $pdo->query("
        SELECT field_name, type_id
        FROM ssl_cert_fields")->fetchAll(PDO::FETCH_KEY_PAIR);

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

        $creation_type = $system->getCreationType($row->creation_type_id);

        if ($row->created_by == '0') {
            $created_by = _('Unknown');
        } else {
            $user = new DomainMOD\User();
            $created_by = $user->getFullName($row->created_by);
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

        $sslfd_columns_array = $customField->getCustomFields('ssl_cert_fields');

        if ($sslfd_columns_array != "") {

            foreach ($sslfd_columns_array as $column) {

                // An empty Date or Time Stamp field is blank, as the list shows it
                if (in_array($sslfd_types[$column] ?? '', array('4', '5')) && DomainMOD\Time::isUnset($row->{$column})) {

                    $row_contents[$count++] = '';

                } else {

                    $row_contents[$count++] = $row->{$column};

                }

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
// Double check to make sure there are still no SSL certs in the system
if ($_SESSION['s_has_ssl_cert'] == '0') {

    $queryB = new DomainMOD\QueryBuild();
    $sql_asset_check = $queryB->singleAsset('ssl_certs');
    $_SESSION['s_has_ssl_cert'] = $system->checkForRows($sql_asset_check);

}

// Before anything exists, the list walks a new user through the order things are added in: an SSL provider, then an
// account at it, then a domain, then certificates, one step at a time (Layout::emptyState())
if ($_SESSION['s_has_ssl_provider'] != '1') {

    echo $layout->emptyState('bi-shield-check', _('Add an SSL provider first'), _('SSL certificates belong to an SSL provider account, so start by adding the company you buy them from.'), '/assets/add/ssl-provider.php', _('Add SSL Provider'));

}

if ($_SESSION['s_has_ssl_account'] != '1' && $_SESSION['s_has_ssl_provider'] == '1') {

    echo $layout->emptyState('bi-person-badge', _('Add an SSL provider account next'), _('Add the account you hold at that SSL provider.'), '/assets/add/ssl-provider-account.php', _('Add SSL Account'));

}

if ($_SESSION['s_has_domain'] != '1' && $_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1') {

    // Adding a domain may need a registrar and an account first, so this goes to the Domains list, which guides that
    echo $layout->emptyState('bi-globe2', _('Add a domain first'), _('Each SSL certificate belongs to a domain, so add the domain it covers.'), '/domains/', _('Go to Domains'), '', '', 2, false);

}

if ($_SESSION['s_has_ssl_cert'] != '1' && $_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_domain'] == '1') {

    echo $layout->emptyState('bi-shield-lock', _('No SSL certificates yet'), _('Add the SSL certificates you want to keep track of.'), '/ssl/add.php', _('Add SSL Certificate'));
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
$list_columns = new DomainMOD\SslListColumns(DomainMOD\SslListColumns::sessionCopy($_SESSION), $sslfd_columns);
$list_page_sizes = DomainMOD\SslList::pageSizes($_SESSION['s_number_of_ssl_certs']);
$list_length = DomainMOD\SslList::checkedLength($_GET['length'] ?? '', $list_page_sizes);
$list_start = DomainMOD\SslList::checkedStart($_GET['start'] ?? 0);
$list_start -= $list_start % $list_length;
if ($list_start >= $total_rows) $list_start = $total_rows > 0 ? (int) (floor(($total_rows - 1) / $list_length) * $list_length) : 0;

$result = $total_rows > 0 ? $ssl_list->rows($list_filter_sql, array('sql' => '', 'params' => array()),
    DomainMOD\SslList::orderSql(array(), $list_columns->sortMap(), $list_columns->defaultOrder()), $list_start,
    $list_length) : array();

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
if ($did !== 0) $filter_toolbar->addFilter('did', $did, 0, $filter_name("SELECT domain FROM domains WHERE id = :id", $did), _('Domain'));
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

if ($_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_ssl_cert'] == '1' && $_SESSION['s_has_domain'] == '1') {

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

            $result_domain = $pdo->query("
                SELECT d.id, d.domain
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
                ORDER BY d.domain asc")->fetchAll();

            echo $form->showDropdownTop('did', _('Domain'), '', '', '');
            echo $form->showDropdownOption('', _('Domain') . ' - ' . DomainMOD\Format::toUpper(_('All')), 'null');
            foreach ($result_domain as $row_domain) {

                echo $form->showDropdownOption($row_domain->id, $row_domain->domain, $did);

            }
            echo $filter_toolbar->keepOption('did', array_column($result_domain, 'id'));
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
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
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

            $result_ssl_provider = $pdo->query("
                SELECT sslp.id, sslp.name
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
                ORDER BY sslp.name asc")->fetchAll();

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
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
            }
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

            $result_account = $pdo->query("
                SELECT sslpa.id AS sslpa_id, sslpa.username, sslp.name AS sslp_name, o.name AS owner_name
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
                ORDER BY sslp.name asc, o.name asc, sslpa.username asc")->fetchAll();

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
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
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

            $result_type = $pdo->query("
                SELECT sslc.type_id, sslcf.type
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
                ORDER BY sslcf.type asc")->fetchAll();

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
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
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

            $result_ip = $pdo->query("
                SELECT ip.id AS ip_id, ip.name AS ip_name, ip.ip
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
                ORDER BY ip.name, ip.ip")->fetchAll();

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
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
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

            $result_cat = $pdo->query("
                SELECT c.id AS cat_id, c.name AS cat_name
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
               ORDER BY c.name")->fetchAll();

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

            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
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

            $result_owner = $pdo->query("
                SELECT o.id, o.name
                FROM ssl_certs AS sslc, domains AS d, owners AS o
                WHERE sslc.domain_id = d.id
                  AND sslc.owner_id = o.id
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
                ORDER BY o.name asc")->fetchAll();

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
            if ($did !== 0) {
                $did_string = " AND sslc.domain_id = '$did' ";
            } else {
                $did_string = "";
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
                $search_string = " AND (sslc.name LIKE " . $pdo->quote('%' . $_SESSION['s_search_for_ssl'] . '%') . ")";
            } else {
                $search_string = "";
            }

            $result_active = $pdo->query("
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
                GROUP BY sslc.active
                ORDER BY sslc.active asc")->fetchAll();

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

            echo $form->showInputText('daterange', _('Expiring Between'), '', $daterange, '23', '', '', '', ''); ?>

            <div class="d-flex flex-wrap gap-2 domainmod-filter-actions">
                <?php echo $form->showSubmitButton(_('Apply Filters'), '', ''); ?>
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($web_root . '/ssl/index.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo _('Reset Filters'); ?></a>
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
    $layout->addPageAction(_('Raw List'), $web_root . '/raw.php', 'bi-list-ul', array('target' => '_blank', 'id' => 'ssl-list-raw'));
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
                'paging' => sprintf(_('Pages of %s'), _('SSL Certificates')),
            ),
            'signinUrl' => $web_root . '/',
            // The links that follow the search box (by id), and what each carries with it (see raw.php)
            'searchLinks' => array(
                'ssl-list-raw' => array('dt_layout' => $list_columns->layoutKey(), 'dt_list' => DomainMOD\SslList::savedListKey($raw_list_sql)),
                'ssl-list-export' => array('dt_layout' => $list_columns->layoutKey()),
            ),
        );
        $today = $time->timeBasic(); // for marking past expiry dates ?>

        <div id="<?php echo $slug; ?>-message"></div>
        <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>" data-domainmod-list="<?php echo htmlspecialchars(json_encode($list_config), ENT_QUOTES, 'UTF-8'); ?>">
            <thead>
            <tr>
                <?php // The control column's heading has hidden text for screen readers (see ListColumns::headerCells())
                foreach ($list_columns->headerCells() as $header_cell) {
                    echo $header_cell;
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
        <?php echo $layout->listPager($total_rows, $list_start, $list_length, $list_state_url, sprintf(_('Pages of %s'), _('SSL Certificates'))); ?>
        <BR><?php

    }

} else {

    $ready_for_ssl_certs = $ready_for_ssl_certs ?? 0;

    // With no certificates at all, the "No SSL certificates yet" empty state above already offers the Add button
    if ($ready_for_ssl_certs === 1 && $_SESSION['s_has_ssl_cert'] == '1') {

        $layout->setPageAddAction(_('Add SSL Certificate'), 'add.php');

    }

    if ($_SESSION['s_has_ssl_provider'] == '1' && $_SESSION['s_has_ssl_account'] == '1' && $_SESSION['s_has_ssl_cert'] == '1' && $_SESSION['s_has_domain'] == '1') {

        // Certificates exist but none are listed. Which empty state shows depends on why (see the empty states plan).
        $ssl_filters_active = $oid !== 0 || $did !== 0 || $sslpid !== 0 || $sslpaid !== 0 || $ssltid !== 0 || $sslipid !== 0
            || $sslpcid !== 0 || $list_has_range || $list_has_search || $is_active != 'LIVE';

        // The list only shows certificates that have a fee (it joins the fees to work out costs), so these counts
        // leave the fees out, to tell "nothing matches" apart from "hidden until they have a fee"
        $ssl_without_fee_join = "
            FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, domains AS d,
                 ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat, ssl_cert_field_data AS sslfd
            WHERE sslc.account_id = sslpa.id
              AND sslpa.ssl_provider_id = sslp.id
              AND sslpa.owner_id = o.id
              AND sslc.domain_id = d.id
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

            $filtered_certs = (int) $pdo->query("SELECT count(*)" . $ssl_without_fee_join . $list_filter_sql)->fetchColumn();

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
