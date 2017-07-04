<?php
/**
 * /update.php
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
require_once __DIR__ . '/_includes/start-session.inc.php';
require_once __DIR__ . '/_includes/init.inc.php';

require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$log = new DomainMOD\Log('/update.php');
$upgrade = new DomainMOD\Upgrade();
$time = new DomainMOD\Time();
$goal = new DomainMOD\Goal();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_INC . '/debug.inc.php';

$pdo = $system->db();

$current_db_version = $system->getDbVersion();

$message_danger_cli = '';
$message_success_cli = '';

if ($current_db_version !== SOFTWARE_VERSION) {

    $timestamp = $time->stamp();
    require_once DIR_INC . '/update.inc.php';

}

if ($message_danger_cli != '') echo $message_danger_cli;

if ($message_success_cli != '') echo $message_success_cli;

exit;
