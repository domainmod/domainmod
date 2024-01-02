<?php
/**
 * /_includes/head.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
header('Content-Type: text/html; charset=utf-8');

if (isset($disable_csp) && $disable_csp === 0) {

    define('CURRENT_NONCE', md5(uniqid(rand(), true)));

    $browser_detect = new DomainMOD\Detect();
    $browser_name = $browser_detect->getBrowser();

    // SECONDARY CSP HEADER -- For browsers that don't support script-src-elem and style-src-elem yet
    // This is created by copying the Primary CSP header and moving the data from the -elem fields into their base fields
    // script-src-elem = script-src / stype-src-elem = style-src
    if ($browser_name === 'firefox' || $browser_name === 'safari' || $browser_name === 'seamonkey') {

        $csp_header = "Content-Security-Policy: default-src 'none'; font-src 'self' code.ionicframework.com fonts.gstatic.com; img-src 'self' data:; script-src 'self' 'nonce-" . CURRENT_NONCE . "'; style-src 'self' code.ionicframework.com fonts.googleapis.com; base-uri 'none'; form-action 'self'; frame-ancestors 'none';";

    // PRIMARY CSP HEADER
    // chrome, chromium, opera, all other browers minus firefox, safari, and seamonkey
    } else {

        $csp_header = "Content-Security-Policy: default-src 'none'; font-src 'self' code.ionicframework.com fonts.gstatic.com; img-src 'self' data:; script-src 'none'; script-src-elem 'self' 'nonce-" . CURRENT_NONCE . "'; style-src 'none'; style-src-elem 'self' code.ionicframework.com fonts.googleapis.com; base-uri 'none'; form-action 'self'; frame-ancestors 'none';";

    }

    if (isset($force_https) && $force_https === 1) {

        $csp_header .= ' upgrade-insecure-requests;';

    }

    header($csp_header);

}

if (isset($force_https) && $force_https === 1) {

    header("Strict-Transport-Security: max-age=31536000");

}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("X-XSS-Protection: 1; mode=block");
