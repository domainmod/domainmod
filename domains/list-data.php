<?php
/**
 * /domains/list-data.php
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
/*
 * The Domains list's rows, one page at a time, for DataTables' server-side mode (see js/domain-list.js). It answers in
 * JSON and changes nothing: no session writes, segment flags, saved Raw List, or currency rates (the page load does
 * those). The list's filters come from the request, the way the page put them in the table's address, never from the
 * session, so another tab's filters can't change this tab's rows.
 */
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$date = new DomainMOD\Date();
$time = new DomainMOD\Time();
$sanitize = new DomainMOD\Sanitize();

require_once DIR_INC . '/head.inc.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$respond = function ($status, array $body) {
    http_response_code($status);
    echo json_encode($body, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};
$generic_error = _("That didn't work. Please reload the page and try again.");

// Only the list's own page may read it, so another site can't read a signed-in user's domains through their browser
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') $respond(405, array('error' => $generic_error));
if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] !== 'same-origin') {
    $respond(403, array('error' => $generic_error));
}

if ($system->authCheckResult() !== 'valid') {
    $respond(401, array('error' => _('Your session has ended. Please sign in again.'), 'signin' => true));
}

// The request's shape: every value is a single value, and order is a short list of column and direction pairs
$single_keys = array('draw', 'start', 'length', 'layout', 'pcid', 'oid', 'dnsid', 'ipid', 'whid', 'rid', 'raid', 'tld',
    'segid', 'is_active', 'search_for', 'daterange');
foreach ($single_keys as $key) {
    if (isset($_GET[$key]) && !is_string($_GET[$key])) $respond(400, array('error' => $generic_error));
}
$search = $_GET['search'] ?? array();
if (!is_array($search) || (isset($search['value']) && !is_string($search['value']))) {
    $respond(400, array('error' => $generic_error));
}
$order = $_GET['order'] ?? array();
if (!is_array($order) || !array_is_list($order)) $respond(400, array('error' => $generic_error));
foreach ($order as $entry) {
    if (!is_array($entry) || !is_string($entry['column'] ?? null) || !is_string($entry['dir'] ?? null)) {
        $respond(400, array('error' => $generic_error));
    }
}

// The keyword (matched whole, as on the page) and the search box (split into words) have size limits, checked before
// any SQL is built
$search_for = $sanitize->text($_GET['search_for'] ?? '');
if (mb_strlen($_GET['search_for'] ?? '', 'UTF-8') > DomainMOD\DomainList::MAX_SEARCH_LENGTH) {
    $respond(400, array('error' => $generic_error));
}
$search_tokens = DomainMOD\DomainList::searchTokens($search['value'] ?? '');
if ($search_tokens === false) $respond(400, array('error' => $generic_error));

// What the rest of the request needs from the session, so the session can be let go of now. Holding it would hold up
// this user's next draw and their other tabs until a slow search finishes.
$user_id = (int) ($_SESSION['s_user_id'] ?? 0);
$settings = DomainMOD\DomainListColumns::sessionCopy($_SESSION);
$page_sizes = DomainMOD\DomainList::pageSizes($_SESSION['s_number_of_domains'] ?? 50);
session_write_close();

try {

    $pdo = $deeb->cnxx;
    $dfd_columns = (new DomainMOD\CustomField())->getCustomFieldsSql('domain_fields', 'dfd');
    $columns = new DomainMOD\DomainListColumns($settings, $dfd_columns);

    // Display Settings or the custom fields changed after this tab drew its table, so its columns no longer line up
    if (($_GET['layout'] ?? '') !== $columns->layoutKey()) {
        $respond(409, array('error' => _('Your display settings have changed. Reload the page to see them.'), 'reload' => true));
    }

    $sort_map = $columns->sortMap();
    if (count($order) > count($sort_map)) $respond(400, array('error' => $generic_error));
    $order_requests = array();
    foreach ($order as $entry) {
        $order_requests[] = array(ctype_digit($entry['column']) ? (int) $entry['column'] : -1, $entry['dir']);
    }

    $state = DomainMOD\DomainList::normalizeState($_GET);
    list($start_date, $end_date) = $date->splitAndCheckRange($sanitize->text($_GET['daterange'] ?? ''));

    $domain_list = new DomainMOD\DomainList($pdo, $user_id, $dfd_columns);
    $filter_sql = DomainMOD\DomainList::filterSql($domain_list->filterParts($state, $search_for, $start_date, $end_date));
    $search_sql = $domain_list->searchSql($search_tokens, $columns->searchExpressions());

    // Text custom fields of numbers sort as numbers, checked over the filtered domains, as DataTables did
    $columns->useNumericSort($pdo, $domain_list->fromSql() . $filter_sql);
    $sort_map = $columns->sortMap();

    list($records_total, $records_filtered) = $domain_list->counts($filter_sql, $search_sql);

    $start = DomainMOD\DomainList::checkedStart($_GET['start'] ?? 0);
    $length = DomainMOD\DomainList::checkedLength($_GET['length'] ?? '', $page_sizes);
    $data = array();
    if ($start < $records_filtered) {
        $order_sql = DomainMOD\DomainList::orderSql($order_requests, $sort_map, $columns->defaultOrder());
        $today = $time->timeBasic();
        foreach ($domain_list->rows($filter_sql, $search_sql, $order_sql, $start, $length) as $row) {
            $data[] = $columns->cells($row, $today);
        }
    }

    $respond(200, array('draw' => (int) filter_var($_GET['draw'] ?? 0, FILTER_VALIDATE_INT, array('options' => array(
        'default' => 0, 'min_range' => 0, 'max_range' => 2147483647))), 'recordsTotal' => $records_total,
        'recordsFiltered' => $records_filtered, 'data' => $data));

} catch (Throwable $e) {

    (new DomainMOD\Log('/domains/list-data.php'))->error('Unable to load the Domains list', array('Error' => $e->getMessage()));
    $respond(500, array('error' => $generic_error));

} //@formatter:on
