<?php
/**
 * /maintenance/update-ssl-fees.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once('../_includes/start-session.inc.php');
require_once('../_includes/init.inc.php');

require_once(DIR_ROOT . 'classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$maint = new DomainMOD\Maintenance();
$time = new DomainMOD\Time();
$timestamp = $time->stamp();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);

$maint->updateSslFees($dbcon);

$queryB = new DomainMOD\QueryBuild();

$sql = $queryB->missingFees('ssl_certs');
$_SESSION['s_missing_ssl_fees'] = $system->checkForRows($dbcon, $sql);

$_SESSION['s_message_success'] .= "SSL Certificate Fees updated<BR>";

header("Location: index.php");
exit;
