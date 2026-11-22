<?php
/**
 * /domains/name-search.php
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
 * The Bulk Updater's domain picker search (see js/bulk-domain-picker.js and DomainNameSearch): the domains whose names
 * contain what was typed, a few at a time, in JSON. It only reads, so it records nothing in the Change History.
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

// Only DomainMOD's own pages may read it, so another site can't read a signed-in user's domains through their browser
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') $respond(405, array('error' => $generic_error));
if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] !== 'same-origin') {
    $respond(403, array('error' => $generic_error));
}

if ($system->authCheckResult() !== 'valid') {
    $respond(401, array('error' => _('Your session has ended. Please sign in again.'), 'signin' => true));
}

// The Bulk Updater is for users who can make changes. The flag is the database's, from the row just checked, so a user
// made read-only during their session is refused at once.
if ($system->authFlags()['read_only'] !== 0) {
    $respond(403, array('error' => _('You are not authorized to perform that action.')));
}

// The request's shape: q is one value, not blank, and no longer than a domain can be
if (!DomainMOD\ListQuery::requestShapeOk($_GET, array('q'))) $respond(400, array('error' => $generic_error));
$q = $_GET['q'] ?? '';
$typed = trim($q);
if ($typed === '' || !mb_check_encoding($typed, 'UTF-8')
    || mb_strlen($typed, 'UTF-8') > DomainMOD\DomainNameSearch::MAX_QUERY_LENGTH) {
    $respond(400, array('error' => $generic_error));
}

// Nothing else here needs the session, so it's let go of now, and a slow search doesn't hold up this user's other tabs
session_write_close();

try {

    $search = new DomainMOD\DomainNameSearch($deeb->cnxx, new DomainMOD\Domain());
    $found = $search->find($typed);
    $respond(200, array('q' => $q, 'results' => $found['results'], 'more' => $found['more']));

} catch (Throwable $e) {

    (new DomainMOD\Log('/domains/name-search.php'))->error('Unable to search the domain names', array('Error' => $e->getMessage()));
    $respond(500, array('error' => $generic_error));

} //@formatter:on
