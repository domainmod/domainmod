<?php
/**
 * /watch/list-data.php
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
 * The Watch List's rows, one page at a time, for DataTables' server-side mode (see js/server-list.js), as
 * domains/list-data.php does for the Domains list. It answers in JSON and changes nothing. The filters come from the
 * request, the way the page put them in the table's address, never from the session.
 */
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();

require_once DIR_INC . '/head.inc.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$respond = function ($status, array $body) {
    http_response_code($status);
    echo json_encode($body, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};
$generic_error = _("That didn't work. Please reload the page and try again.");

// Only the list's own page may read it, so another site can't read a signed-in user's list through their browser
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') $respond(405, array('error' => $generic_error));
if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] !== 'same-origin') {
    $respond(403, array('error' => $generic_error));
}

if ($system->authCheckResult() !== 'valid') {
    $respond(401, array('error' => _('Your session has ended. Please sign in again.'), 'signin' => true));
}

// The request's shape: every value is a single value, and order is a short list of column and direction pairs
$single_keys = array('draw', 'start', 'length', 'layout', 'stage', 'reason', 'tld', 'drop', 'added', 'archived');
if (!DomainMOD\ListQuery::requestShapeOk($_GET, $single_keys)) $respond(400, array('error' => $generic_error));
$search = $_GET['search'] ?? array();
$order = $_GET['order'] ?? array();

$search_tokens = DomainMOD\WatchList::searchTokens($search['value'] ?? '');
if ($search_tokens === false) $respond(400, array('error' => $generic_error));

// What the rest of the request needs from the session, so the session can be let go of now
$user_id = (int) ($_SESSION['s_user_id'] ?? 0);
$settings = DomainMOD\WatchListColumns::sessionCopy($_SESSION);
$page_sizes = DomainMOD\WatchList::pageSizes($_SESSION['s_number_of_domains'] ?? 50);
session_write_close();

$draw = (int) filter_var($_GET['draw'] ?? 0, FILTER_VALIDATE_INT, array('options' => array('default' => 0,
    'min_range' => 0, 'max_range' => 2147483647)));

try {

    $pdo = $deeb->cnxx;
    if (!DomainMOD\WatchList::isEnabled()) {
        $respond(200, array('draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array()));
    }

    $columns = new DomainMOD\WatchListColumns($settings);
    // A table drawn with other columns (a read-only change in another tab) has to be reloaded
    if (($_GET['layout'] ?? '') !== $columns->layoutKey()) $respond(409, array('error' => $generic_error, 'reload' => true));
    $watch_check = new DomainMOD\WatchCheck($pdo);
    $columns->setFastSlots(DomainMOD\WatchCheck::fastSlots($watch_check->activeRows(), $watch_check->movedCandidates(), time()), time());
    $sort_map = $columns->sortMap();
    if (count($order) > count($columns->columns())) $respond(400, array('error' => $generic_error));
    $order_requests = array();
    foreach ($order as $entry) {
        $order_requests[] = array(ctype_digit($entry['column']) ? (int) $entry['column'] : -1, $entry['dir']);
    }

    $watch_list = new DomainMOD\WatchList($pdo, $user_id);
    $filter = $watch_list->filterParts(DomainMOD\WatchList::normalizeState($_GET));
    $search_sql = $watch_list->searchSql($search_tokens, $columns->searchExpressions());

    list($records_total, $records_filtered) = $watch_list->counts($filter['sql'], $search_sql, $filter['params']);

    $start = DomainMOD\WatchList::checkedStart($_GET['start'] ?? 0);
    $length = DomainMOD\WatchList::checkedLength($_GET['length'] ?? '', $page_sizes);
    $data = array();
    if ($start < $records_filtered) {
        $order_sql = DomainMOD\WatchList::orderSql($order_requests, $sort_map, $columns->defaultOrder());
        foreach ($watch_list->rows($filter['sql'], $search_sql, $order_sql, $start, $length, $filter['params']) as $row) {
            $data[] = $columns->cells($row, '');
        }
    }

    $respond(200, array('draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_filtered,
        'data' => $data));

} catch (Throwable $e) {

    (new DomainMOD\Log('/watch/list-data.php'))->error('Unable to load the Watch List', array('Error' => $e->getMessage()));
    $respond(500, array('error' => $generic_error));

} //@formatter:on
