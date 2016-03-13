<?php
/**
 * /admin/scheduler/update.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

require DIR_ROOT . 'vendor/autoload.php';

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "config-demo.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$a = $_REQUEST['a'];
$id = $_REQUEST['id'];
$new_hour = $_REQUEST['new_hour'];

if ($a == 'u') {

    $new_time = date('Y-m-d H:i:s', mktime($new_hour, '00', '00', date("m"), date("d"), date("Y")));
    $new_time_utc = $time->toUtcTimezone($new_time);
    $hour = date("G", strtotime($new_time_utc));
    $full_expression = '0 ' . $hour . ' * * * *';

    $sql = "UPDATE scheduler
            SET expression = '" . $full_expression . "',
                next_run = '" . $new_time_utc . "'
            WHERE id = '" . $id . "'";
    mysqli_query($connection, $sql);

    $message = 'Task Updated<BR>';

} elseif ($a == 'e') {

    $sql = "SELECT expression FROM scheduler WHERE id = '" . $id . "'";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {
        $full_expression = $row->expression;
    }

    $cron = \Cron\CronExpression::factory($full_expression);
    $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    $sql = "UPDATE scheduler SET active = '1', next_run = '" . $next_run . "' WHERE id = '" . $id . "'";

    $message = 'Task Enabled<BR>';

} elseif ($a == 'd') {

    $sql = "UPDATE scheduler SET active = '0', next_run = '0000-00-00 00:00:00' WHERE id = '" . $id . "'";

    $message = 'Task Disabled<BR>';

}
mysqli_query($connection, $sql);

$_SESSION['s_message_success'] = $message;

header("Location: index.php");
exit;
