<?php
/**
 * /admin/scheduler/update.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);
$pdo = $deeb->cnxx;

$a = $_REQUEST['a'];
$id = $_REQUEST['id'];
$new_hour = $_REQUEST['new_hour'];

if ($a == 'u') {

    $new_time = date('Y-m-d H:i:s', mktime($new_hour, '00', '00', date("m"), date("d"), date("Y")));
    $new_time_utc = $time->toUtcTimezone($new_time);
    $hour = date("G", strtotime($new_time_utc));
    $full_expression = '0 ' . $hour . ' * * * *';

    $stmt = $pdo->prepare("
        UPDATE scheduler
        SET expression = :full_expression,
            next_run = :new_time_utc
        WHERE id = :id");
    $stmt->bindValue('full_expression', $full_expression, PDO::PARAM_STR);
    $stmt->bindValue('new_time_utc', $new_time_utc, PDO::PARAM_STR);
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $message = _('Task Updated') . '<BR>';

} elseif ($a == 'e') {

    $stmt = $pdo->prepare("
        SELECT expression
        FROM scheduler
        WHERE id = :id");
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $full_expression = $stmt->fetchColumn();

    $cron = \Cron\CronExpression::factory($full_expression);
    $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        UPDATE scheduler
        SET active = '1', 
            next_run = :next_run
        WHERE id = :id");
    $stmt->bindValue('next_run', $next_run, PDO::PARAM_STR);
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $message = _('Task Enabled') . '<BR>';

} elseif ($a == 'd') {

    $stmt = $pdo->prepare("
        UPDATE scheduler
        SET active = '0', 
            next_run = '1970-01-01 00:00:00'
        WHERE id = :id");
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $message = _('Task Disabled') . '<BR>';

}

$_SESSION['s_message_success'] .= $message;

header("Location: index.php");
exit;
