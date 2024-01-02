<?php
/**
 * /_includes/start-session.inc.php
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
// If the /helpers/top.php file exists, load it
$full_filename = __DIR__ . '/../helpers/top.php';
if (file_exists($full_filename)) require_once $full_filename;

// If the /helpers/top.php file does NOT exists, set variable defaults
if (!isset($disable_csp)) $disable_csp = 0;
if (!isset($force_https)) $force_https = 0;

if ($force_https === 1) {

    session_name("__Secure-domainmod-gc-cookie");
    @ini_set('session.cookie_secure', '1');

} else {

    session_name("domainmod-gc-cookie");
    @ini_set('session.cookie_secure', '0');

}
@ini_set('session.cookie_httponly', '1');
@ini_set('session.cookie_path', '/');
@ini_set('session.cookie_samesite', 'Lax');
session_start();
