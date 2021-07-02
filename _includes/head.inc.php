<?php
/**
 * /_includes/head.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2021 Greg Chetcuti <greg@chetcuti.com>
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

if ($disable_csp !== 1) {

    define('CURRENT_NONCE', md5(uniqid(rand(), true)));

    $csp_policy = "Content-Security-Policy: default-src 'none'; font-src 'self' code.ionicframework.com fonts.gstatic.com maxcdn.bootstrapcdn.com; img-src 'self'; script-src-elem 'self' 'nonce-" . CURRENT_NONCE . "'; style-src-elem 'self' code.ionicframework.com fonts.googleapis.com maxcdn.bootstrapcdn.com; base-uri 'none'; form-action 'self'; frame-ancestors 'none';";

    if ($force_https !== 0) {

        $csp_policy .= ' upgrade-insecure-requests;';

    }

    header($csp_policy);

}

if ($force_https !== 0) {

    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("X-XSS-Protection: 1; mode=block");
