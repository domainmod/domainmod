<?php
/**
 * /settings/toggles/dark-mode/index.php
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
<?php
require_once __DIR__ . '/../../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$user = new DomainMOD\User();

require_once DIR_INC . '/head.inc.php';

// Saved in the background by the header's colour mode switcher (js/colour-mode-save.js), so it answers with a status
// code rather than a page or a redirect: 204 when saved, 401 when the session has ended, 405 for anything but a POST,
// and 403 or 400 when the request can't be verified or the choice isn't one of the three. The session is checked
// before authCheck(), which would send the request to the sign-in page and remember this address to return to.
if (($_SESSION['s_is_logged_in'] ?? 0) != 1) {
    http_response_code(401);
    exit;
}

$system->authCheck();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$csrf = new DomainMOD\Csrf();
if (!$csrf->isValid()) {
    http_response_code(403);
    exit;
}

$colour_modes = array('light' => 0, 'dark' => 1, 'auto' => 2);
$colour_mode = $_POST['colour_mode'] ?? '';
if (!is_string($colour_mode) || !isset($colour_modes[$colour_mode])) {
    http_response_code(400);
    exit;
}

$_SESSION['s_dark_mode'] = $colour_modes[$colour_mode];
$user->setDarkMode();

http_response_code(204);
exit;
