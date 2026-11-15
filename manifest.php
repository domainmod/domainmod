<?php
/**
 * /manifest.php
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
// The web app manifest, so browsers can offer to install DomainMOD as an app (its own icon and window). It's PHP so the
// addresses include the install's folder ($web_root). It needs no database or session, since browsers fetch it without
// the user's cookies, so its one sentence is in the language DomainMOD uses without a session (the server's, or
// English). Before DomainMOD is set up there's no config.inc.php, so it answers 404 and browsers simply don't offer it.
require_once __DIR__ . '/_includes/init.inc.php';

if (!file_exists(DIR_INC . '/config.inc.php')) {
    http_response_code(404);
    exit;
}

require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';

$icon = function ($file, $size, $purpose) {
    return array('src' => WEB_ROOT . '/images/icons/' . $file, 'sizes' => $size . 'x' . $size, 'type' => 'image/png', 'purpose' => $purpose);
};

$manifest = array(
    'id' => WEB_ROOT . '/',
    'name' => SOFTWARE_TITLE,
    'short_name' => SOFTWARE_TITLE,
    'description' => _('Domain and internet asset manager'),
    'start_url' => WEB_ROOT . '/',
    'scope' => WEB_ROOT . '/',
    'display' => 'standalone',
    'background_color' => '#2e3440',
    'theme_color' => '#2e3440',
    'icons' => array(
        $icon('icon-192.png', 192, 'any'),
        $icon('icon-512.png', 512, 'any'),
        $icon('icon-maskable-192.png', 192, 'maskable'),
        $icon('icon-maskable-512.png', 512, 'maskable')));

header('Content-Type: application/manifest+json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
