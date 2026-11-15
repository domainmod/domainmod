<?php
/**
 * /service-worker.php
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
// The service worker, which only gives DomainMOD a friendly offline page. It caches the offline page and the exact files
// that page loads, and nothing else: no DomainMOD page, report, export, or data is ever kept on the device. Navigations
// (GET only, so a form post is never caught) go to the network, and the offline page is shown only when the network
// fails. It's PHP so it can write the install's folder and version into the script. It needs no database or session.
require_once __DIR__ . '/_includes/init.inc.php';

if (!file_exists(DIR_INC . '/config.inc.php')) {
    http_response_code(404);
    exit;
}

require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$layout = new DomainMOD\Layout();

// The cache's name has a short hash of the install's folder, so two installs on one site never share or delete each
// other's caches, and the version, so an upgrade replaces this install's cache
$cache_prefix = 'domainmod-offline-' . substr(hash('sha256', WEB_ROOT . '/'), 0, 6) . '-';
// Right-to-left installs cache the mirrored stylesheets (see below), so the direction is in the name too, and a change of
// the server's language replaces the cache
$cache_name = $cache_prefix . SOFTWARE_VERSION . ($layout->isRtl() ? '-rtl' : '');

// The offline page and exactly the files it loads, with the same versioned addresses offline.php writes. The font files
// are asked for by the font stylesheet at relative, unversioned addresses, so they're listed that way.
$fonts = WEB_ROOT . '/' . WEBROOT_THEME . '/plugins/source-sans-3/files/';
$offline_files = array(
    WEB_ROOT . '/offline.php',
    $layout->assetUrl('js/offline-colour-mode.js'),
    $layout->assetUrl(WEBROOT_THEME . '/plugins/source-sans-3/source-sans-3.css'),
    // The pair offline.php loads: the mirrored builds in right-to-left languages. Neither file has a session, so both
    // take the language from the server and always agree
    $layout->assetUrl(WEBROOT_THEME . '/dist/css/adminlte' . ($layout->isRtl() ? '.rtl.min.css' : '.min.css')),
    $layout->assetUrl(WEBROOT_THEME . '/dist/css/adminlte-colors' . ($layout->isRtl() ? '.rtl.min.css' : '.min.css')),
    $layout->assetUrl('css/base.css'),
    WEB_ROOT . '/images/icons/icon-192.png',
    $fonts . 'source-sans-3-latin-400-normal.woff2',
    $fonts . 'source-sans-3-latin-600-normal.woff2',
    $fonts . 'source-sans-3-latin-700-normal.woff2');

header('Content-Type: text/javascript; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');
?>
'use strict';

var CACHE_PREFIX = <?php echo json_encode($cache_prefix, JSON_UNESCAPED_SLASHES); ?>;
var CACHE_NAME = <?php echo json_encode($cache_name, JSON_UNESCAPED_SLASHES); ?>;
var OFFLINE_PAGE = <?php echo json_encode(WEB_ROOT . '/offline.php', JSON_UNESCAPED_SLASHES); ?>;
var OFFLINE_FILES = <?php echo json_encode($offline_files, JSON_UNESCAPED_SLASHES); ?>;

// The offline files, as absolute addresses, so a request can be matched against the list exactly
var offline_urls = OFFLINE_FILES.map(function (path) {
    return new URL(path, self.location.origin).href;
});

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE_NAME).then(function (cache) {
        return cache.addAll(OFFLINE_FILES);
    }).then(function () {
        return self.skipWaiting();
    }));
});

// Only this install's older caches are deleted, so other installs on the same site are left alone
self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (names) {
        return Promise.all(names.filter(function (name) {
            return name.indexOf(CACHE_PREFIX) === 0 && name !== CACHE_NAME;
        }).map(function (name) {
            return caches.delete(name);
        }));
    }).then(function () {
        return self.clients.claim();
    }));
});

self.addEventListener('fetch', function (event) {
    var request = event.request;
    if (request.method !== 'GET') return;

    // A person opening a page: the network as normal (any real answer, error pages and sign-in redirects included, is
    // passed through), and the offline page only when the network fails
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(function () {
            return caches.match(OFFLINE_PAGE, {cacheName: CACHE_NAME}).then(function (page) {
                return page || Response.error();
            });
        }));
        return;
    }

    // One of the offline page's own files: the network first, then the cached copy if the network fails. Nothing is
    // ever stored here, so the cache can't pick up anything else.
    if (offline_urls.indexOf(request.url) !== -1) {
        event.respondWith(fetch(request).catch(function () {
            return caches.match(request.url, {cacheName: CACHE_NAME}).then(function (file) {
                return file || Response.error();
            });
        }));
    }
});
