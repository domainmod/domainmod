<?php
/**
 * /ssl/list-data.php
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
 * The SSL Certificates list's rows, one page at a time, for DataTables' server-side mode (see js/server-list.js). It
 * answers in JSON and changes nothing: no session writes, saved Raw List, or currency rates (the page load does those).
 * The list's filters come from the request, the way the page put them in the table's address, never from the session,
 * so another tab's filters can't change this tab's rows.
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

// Only the list's own page may read it, so another site can't read a signed-in user's certificates through their
// browser
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') $respond(405, array('error' => $generic_error));
if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] !== 'same-origin') {
    $respond(403, array('error' => $generic_error));
}

if ($system->authCheckResult() !== 'valid') {
    $respond(401, array('error' => _('Your session has ended. Please sign in again.'), 'signin' => true));
}

// The request's shape: every value is a single value, and order is a short list of column and direction pairs
$single_keys = array('draw', 'start', 'length', 'layout', 'oid', 'did', 'sslpid', 'sslpaid', 'ssltid', 'sslipid',
    'sslpcid', 'is_active', 'search_for', 'daterange');
// Every custom field filter key (current field or not), cff, and cfk are single values too
$single_keys = array_merge($single_keys, DomainMOD\CustomFieldFilter::incomingKeys($_GET));
if (!DomainMOD\ListQuery::requestShapeOk($_GET, $single_keys)) $respond(400, array('error' => $generic_error));
$search = $_GET['search'] ?? array();
$order = $_GET['order'] ?? array();

// The keyword (matched whole, as on the page) and the search box (split into words) have size limits, checked before
// any SQL is built
$search_for = $sanitize->text($_GET['search_for'] ?? '');
if (mb_strlen($_GET['search_for'] ?? '', 'UTF-8') > DomainMOD\SslList::MAX_SEARCH_LENGTH) {
    $respond(400, array('error' => $generic_error));
}
$search_tokens = DomainMOD\SslList::searchTokens($search['value'] ?? '');
if ($search_tokens === false) $respond(400, array('error' => $generic_error));

// What the rest of the request needs from the session, so the session can be let go of now. Holding it would hold up
// this user's next draw and their other tabs until a slow search finishes.
$user_id = (int) ($_SESSION['s_user_id'] ?? 0);
$settings = DomainMOD\SslListColumns::sessionCopy($_SESSION);
$page_sizes = DomainMOD\SslList::pageSizes($_SESSION['s_number_of_ssl_certs'] ?? 50);
session_write_close();

/*
 * The two checks of this tab's table against a read of the custom fields, in order, as a 409 reply's body, or null when
 * both pass: first the custom field filters' key (cfk, see CustomFieldFilter::endpointOk()), so a filtered field deleted
 * or replaced since the page loaded is never filtered on (the filter reply, whose Reload drops the custom field
 * filters), then the column layout, so Display Settings or a shown field changed since then is noticed (the display
 * settings reply, whose Reload keeps every filter)
 */
$conflict = function (array $fields) use ($settings) {
    $conflict = DomainMOD\CustomFieldFilter::endpointConflict($_GET, $fields, 'sslfd',
        (new DomainMOD\SslListColumns($settings, $fields))->layoutKey());
    if ($conflict === 'filter') {
        return array('error' => _('A custom field in your filters was changed or deleted. Reload the page to see the list without your custom field filters.'),
            'reload' => true, 'reason' => 'filter');
    }
    if ($conflict === 'layout') {
        return array('error' => _('Your display settings have changed. Reload the page to see them.'), 'reload' => true);
    }
    return null;
};

try {

    $pdo = $deeb->cnxx;
    $sslfd_fields = DomainMOD\CustomField::readFilterFields($pdo, 'ssl_cert_fields');
    $conflict_reply = $conflict($sslfd_fields);
    if ($conflict_reply !== null) $respond(409, $conflict_reply);
    $columns = new DomainMOD\SslListColumns($settings, $sslfd_fields);

    $sort_map = $columns->sortMap();
    if (count($order) > count($sort_map)) $respond(400, array('error' => $generic_error));
    $order_requests = array();
    foreach ($order as $entry) {
        $order_requests[] = array(ctype_digit($entry['column']) ? (int) $entry['column'] : -1, $entry['dir']);
    }

    $state = DomainMOD\SslList::normalizeState($_GET);
    list($start_date, $end_date) = $date->splitAndCheckRange($sanitize->text($_GET['daterange'] ?? ''));

    // The custom field filters, checked as the page checks them, as bound parameters
    $cf = new DomainMOD\CustomFieldFilter($sslfd_fields, 'sslfd');
    $cf_state = $cf->normalize($_GET);
    $cf_filter = $cf->conditions($cf_state);

    $ssl_list = new DomainMOD\SslList($pdo, $user_id, $sslfd_fields);
    $filter_sql = DomainMOD\SslList::filterSql($ssl_list->filterParts($state, $search_for, $start_date, $end_date))
        . $cf_filter['sql'];
    $search_sql = $ssl_list->searchSql($search_tokens, $columns->searchExpressions());

    // Text custom fields of numbers sort as numbers, checked over the filtered certificates, as DataTables did
    $columns->useNumericSort($pdo, $ssl_list->fromSql() . $filter_sql, $cf_filter['params']);
    $sort_map = $columns->sortMap();

    list($records_total, $records_filtered) = $ssl_list->counts($filter_sql, $search_sql, $cf_filter['params']);

    $start = DomainMOD\SslList::checkedStart($_GET['start'] ?? 0);
    $length = DomainMOD\SslList::checkedLength($_GET['length'] ?? '', $page_sizes);
    $data = array();
    if ($start < $records_filtered) {
        $order_sql = DomainMOD\SslList::orderSql($order_requests, $sort_map, $columns->defaultOrder());
        $today = $time->timeBasic();
        foreach ($ssl_list->rows($filter_sql, $search_sql, $order_sql, $start, $length, $cf_filter['params']) as $row) {
            $data[] = $columns->cells($row, $today);
        }
    }

    // A filtered field deleted and added again under the same name while the queries ran wouldn't make them fail, so
    // with custom field filters on, the fields are read once more, and when they've changed, the checks run again
    if ($cf_state) {
        $fields_now = DomainMOD\CustomField::readFilterFields($pdo, 'ssl_cert_fields');
        if (!$cf->sameFields($fields_now)) {
            $conflict_reply = $conflict($fields_now);
            if ($conflict_reply !== null) $respond(409, $conflict_reply);
        }
    }

    $respond(200, array('draw' => (int) filter_var($_GET['draw'] ?? 0, FILTER_VALIDATE_INT, array('options' => array(
        'default' => 0, 'min_range' => 0, 'max_range' => 2147483647))), 'recordsTotal' => $records_total,
        'recordsFiltered' => $records_filtered, 'data' => $data));

} catch (Throwable $e) {

    // A custom field deleted after the fields were read makes a query fail on its missing column: the checks run again
    // on a fresh read, and when both pass (a shown field that isn't filtered went), it's the display settings reply
    if (DomainMOD\CustomFieldFilter::fieldsChangedError($e)) {
        try {
            $conflict_reply = $conflict(DomainMOD\CustomField::readFilterFields($deeb->cnxx, 'ssl_cert_fields'));
        } catch (Throwable $ignored) {
            $conflict_reply = null;
        }
        $respond(409, $conflict_reply ?? array('error' => _('Your display settings have changed. Reload the page to see them.'),
            'reload' => true));
    }

    (new DomainMOD\Log('/ssl/list-data.php'))->error('Unable to load the SSL Certificates list', array('Error' => $e->getMessage()));
    $respond(500, array('error' => $generic_error));

} //@formatter:on
