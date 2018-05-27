<?php
/**
 * /_includes/init.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
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
// PHP named constants
define('DIR_ROOT', dirname(dirname(__FILE__)));
define('DIR_INC', DIR_ROOT . '/_includes');
define('DIR_TEMP', DIR_ROOT . '/temp');
define('WEBROOT_THEME', '_includes/theme');
define('EMAIL_ENCODING_TYPE', 'UTF-8'); // UTF-8 or iso-8859-1

// PHP.ini overrides
date_default_timezone_set('UTC');
@error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);

if ($_SESSION['s_system_local_php_log'] == '1') {

    @ini_set("error_log", DIR_ROOT . "/domainmod.log");

}

// For Troubleshooting -- Include the helper file if it exists
if (file_exists(DIR_ROOT . '/helper.php')) {

    require_once DIR_ROOT . '/helper.php';

}
