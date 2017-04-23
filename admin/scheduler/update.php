<?php
/**
 * /admin/scheduler/update.php
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
require_once('../../_includes/start-session.inc.php');
require_once('../../_includes/init.inc.php');

require_once(DIR_ROOT . 'classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

require_once(DIR_ROOT . 'vendor/autoload.php');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'config-demo.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$a = $_REQUEST['a'];
$id = $_REQUEST['id'];
$new_hour = $_REQUEST['new_hour'];

if ($a == 'u') {

    $new_time = date('Y-m-d H:i:s', mktime($new_hour, '00', '00', date("m"), date("d"), date("Y")));
    $new_time_utc = $time->toUtcTimezone($new_time);
    $hour = date("G", strtotime($new_time_utc));
    $full_expression = '0 ' . $hour . ' * * * *';

    $query = "UPDATE scheduler
              SET expression = ?,
                  next_run = ?
              WHERE id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('ssi', $full_expression, $new_time_utc, $id);
        $q->execute();
        $q->close();

    } else $error->outputSqlError($dbcon, '1', 'ERROR');

    $message = 'Task Updated<BR>';

} elseif ($a == 'e') {

    $query = "SELECT expression
              FROM scheduler
              WHERE id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $id);
        $q->execute();
        $q->store_result();
        $q->bind_result($temp_expression);

        while ($q->fetch()) {

            $full_expression = $temp_expression;

        }

        $q->close();

    } else $error->outputSqlError($dbcon, '1', 'ERROR');

    $cron = \Cron\CronExpression::factory($full_expression);
    $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    $query = "UPDATE scheduler
              SET active = '1', 
                  next_run = ?
              WHERE id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('si', $next_run, $id);
        $q->execute();
        $q->close();

    } else $error->outputSqlError($dbcon, '1', 'ERROR');

    $message = 'Task Enabled<BR>';

} elseif ($a == 'd') {

    $query = "UPDATE scheduler
              SET active = '0', 
                  next_run = '0000-00-00 00:00:00'
              WHERE id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $id);
        $q->execute();
        $q->close();

    } else $error->outputSqlError($dbcon, '1', 'ERROR');

    $message = 'Task Disabled<BR>';

}

$_SESSION['s_message_success'] .= $message;

header("Location: index.php");
exit;
