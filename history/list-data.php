<?php
/**
 * /history/list-data.php
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
 * The Change History list's rows, one page at a time, for DataTables' server-side mode (see js/server-list.js), as
 * domains/list-data.php does for the Domains list. It answers in JSON and changes nothing. The filters come from the
 * request, the way the page put them in the table's address, never from the session. The bootstrap's restore gate holds
 * the shared restore lock until the request ends, so it never reads history part-way into a restore.
 */
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$date = new DomainMOD\Date();
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

// Only the list's own page may read it, so another site can't read a signed-in user's history through their browser
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') $respond(405, array('error' => $generic_error));
if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] !== 'same-origin') {
    $respond(403, array('error' => $generic_error));
}

if ($system->authCheckResult() !== 'valid') {
    $respond(401, array('error' => _('Your session has ended. Please sign in again.'), 'signin' => true));
}

// The request's shape: every value is a single value, and order is a short list of column and direction pairs
$single_keys = array('draw', 'start', 'length', 'layout', 'type', 'id', 'life', 'name', 'source', 'user', 'field', 'op',
    'range', 'daterange');
if (!DomainMOD\ListQuery::requestShapeOk($_GET, $single_keys)) $respond(400, array('error' => $generic_error));
$search = $_GET['search'] ?? array();
$order = $_GET['order'] ?? array();

if (mb_strlen($_GET['name'] ?? '', 'UTF-8') > DomainMOD\ChangeHistoryList::MAX_SEARCH_LENGTH) {
    $respond(400, array('error' => $generic_error));
}
$search_tokens = DomainMOD\ChangeHistoryList::searchTokens($search['value'] ?? '');
if ($search_tokens === false) $respond(400, array('error' => $generic_error));

// What the rest of the request needs from the session (the viewer's time zone among it), so the session can be let go
// of now, as the Domains list's endpoint does
$user_id = (int) ($_SESSION['s_user_id'] ?? 0);
$settings = DomainMOD\ChangeHistoryListColumns::sessionCopy($_SESSION);
$page_sizes = DomainMOD\ChangeHistoryList::pageSizes($_SESSION['s_number_of_domains'] ?? 50);
session_write_close();

try {

    $pdo = $deeb->cnxx;
    if (!DomainMOD\ChangeHistory::isAvailable($pdo)) {
        // The request's own draw, so DataTables shows the empty answer rather than discarding it as stale
        $respond(200, array('draw' => (int) filter_var($_GET['draw'] ?? 0, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 0, 'min_range' => 0, 'max_range' => 2147483647))), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array()));
    }

    $columns = new DomainMOD\ChangeHistoryListColumns($settings);
    $columns->setFactNames(DomainMOD\Export::creationTypeNames($pdo), DomainMOD\ChangeHistoryListColumns::usernames($pdo));
    $sort_map = $columns->sortMap();
    if (count($order) > count($columns->columns())) $respond(400, array('error' => $generic_error));
    $order_requests = array();
    foreach ($order as $entry) {
        $order_requests[] = array(ctype_digit($entry['column']) ? (int) $entry['column'] : -1, $entry['dir']);
    }

    $state = DomainMOD\ChangeHistoryList::normalizeState($_GET);
    list($start_date, $end_date) = $date->splitAndCheckRange($sanitize->text($state['daterange']));
    list($from_time, $to_time) = DomainMOD\ChangeHistoryList::rangeTimes($state['range'], $settings['s_default_timezone'],
        $start_date === 'YYYY-MM-DD' ? '' : $start_date, $end_date === 'YYYY-MM-DD' ? '' : $end_date);

    $history_list = new DomainMOD\ChangeHistoryList($pdo, $user_id);
    $filter = $history_list->filterParts($state, $from_time, $to_time);
    $search_sql = $history_list->searchSql($search_tokens, $columns->searchExpressions());

    list($records_total, $records_filtered) = $history_list->counts($filter['sql'], $search_sql, $filter['params']);

    $start = DomainMOD\ChangeHistoryList::checkedStart($_GET['start'] ?? 0);
    $length = DomainMOD\ChangeHistoryList::checkedLength($_GET['length'] ?? '', $page_sizes);
    $data = array();
    if ($start < $records_filtered) {
        $order_sql = DomainMOD\ChangeHistoryList::orderSql($order_requests, $sort_map, $columns->defaultOrder());
        $rows = $history_list->rows($filter['sql'], $search_sql, $order_sql, $start, $length, $filter['params']);
        $columns->setOperationCounts($history_list->operationCounts(array_map(function ($row) {
            return $row->operation_id;
        }, $rows)));
        foreach ($rows as $row) {
            $data[] = $columns->cells($row, '');
        }
    }

    $respond(200, array('draw' => (int) filter_var($_GET['draw'] ?? 0, FILTER_VALIDATE_INT, array('options' => array(
        'default' => 0, 'min_range' => 0, 'max_range' => 2147483647))), 'recordsTotal' => $records_total,
        'recordsFiltered' => $records_filtered, 'data' => $data));

} catch (Throwable $e) {

    (new DomainMOD\Log('/history/list-data.php'))->error('Unable to load the Change History list', array('Error' => $e->getMessage()));
    $respond(500, array('error' => $generic_error));

} //@formatter:on
