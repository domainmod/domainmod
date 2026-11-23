<?php
/**
 * /_includes/init.inc.php
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
// PHP named constants
define('DIR_ROOT', dirname(dirname(__FILE__)));
const DIR_INC = DIR_ROOT . '/_includes';
const DIR_TEMP = DIR_ROOT . '/temp';
const DIR_LOCALES = DIR_ROOT . '/locales';
const WEBROOT_THEME = 'theme';
const EMAIL_ENCODING_TYPE = 'UTF-8'; // UTF-8 or iso-8859-1

// PHP.ini overrides
date_default_timezone_set('UTC');
@error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

$_SESSION['s_system_local_php_log'] = $_SESSION['s_system_local_php_log'] ?? '';

if ($_SESSION['s_system_local_php_log'] == '1') {

    @ini_set('error_log', DIR_ROOT . '/domainmod.log');

}

// Language Settings
// A signed-in user's own language always comes first. The language chosen in the installer is only for the installer
// and the first sign-in after it, since checks.php clears it once that sign-in has loaded the user's settings, but a
// session from before that can still hold it.
if (($_SESSION['s_is_logged_in'] ?? '') == 1 && isset($_SESSION['s_default_language'])) {

    define('DEFAULT_LANGUAGE', $_SESSION['s_default_language']);

} elseif (isset($_SESSION['s_installation_language'])) {

    define('DEFAULT_LANGUAGE', $_SESSION['s_installation_language']);

} elseif (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'two-factor.php'
    && ($_SESSION['s_two_factor_pending']['language'] ?? '') !== ''
    && time() <= (int) ($_SESSION['s_two_factor_pending']['language_until'] ?? 0)) {

    // The code step (two-factor.php) shows in the user's own language while its sign-in can still be finished. Any other
    // page, and an expired or abandoned code step, keeps the sign-in page's language.
    define('DEFAULT_LANGUAGE', $_SESSION['s_two_factor_pending']['language']);

} elseif (isset($_SESSION['s_default_language'])) {

    define('DEFAULT_LANGUAGE', $_SESSION['s_default_language']);

} elseif (isset($_ENV['LANG'])) {

    define('DEFAULT_LANGUAGE', $_ENV['LANG']);

} else {

    define('DEFAULT_LANGUAGE', 'en_US.UTF-8');

}
const DEFAULT_ENCODING_TYPE = 'UTF-8';
const LOCALES_DOMAIN = 'main';
putenv("LANG=DEFAULT_LANGUAGE");
putenv("LC_ALL=DEFAULT_LANGUAGE");
setlocale(LC_ALL, DEFAULT_LANGUAGE);
bindtextdomain(LOCALES_DOMAIN, DIR_LOCALES);
bind_textdomain_codeset(LOCALES_DOMAIN, DEFAULT_ENCODING_TYPE);
textdomain(LOCALES_DOMAIN);

// The restore gate: every request takes the restore lock shared before anything reads the database (see the file)
require_once __DIR__ . '/restore-gate.inc.php';

// If the /helpers/init.php file exists, load it
$full_filename = __DIR__ . '/../helpers/init.php';
if (file_exists($full_filename)) require_once $full_filename;
