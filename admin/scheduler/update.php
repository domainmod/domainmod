<?php
/**
 * /admin/scheduler/update.php
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$schedule = new DomainMOD\Scheduler();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$csrf = new DomainMOD\Csrf();
$csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data
$pdo = $deeb->cnxx;

$a = $_REQUEST['a'] ?? '';
$id = (int) ($_REQUEST['id'] ?? 0);
$new_hour = $_REQUEST['new_hour'] ?? 0;

// A change waits for a running task, so the task can't overwrite it when it finishes (see Scheduler::finishTask()).
if (in_array($a, array('u', 'e', 'd'), true) && !$schedule->getTaskLock($id)) {

    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That task is already running. Please try again once it finishes.') . '<BR>';
    header("Location: index.php");
    exit;

}

if ($a == 'u') {

    // The form only sends a whole hour from 0 to 23, so ignore anything else rather than saving a wrong time.
    if (!is_string($new_hour) || !ctype_digit($new_hour) || (int) $new_hour > 23) {
        $schedule->releaseTaskLock($id);
        header("Location: index.php");
        exit;
    }

    // The time is saved as typed, in the admin's own time zone, and the task follows that zone's clock changes.
    $timezone = $schedule->getZone($_SESSION['s_default_timezone'] ?? 'UTC')->getName();
    $full_expression = '0 ' . (int) $new_hour . ' * * *';
    $new_time_utc = $schedule->getNextRun($full_expression, $timezone);

    $stmt = $pdo->prepare("
        UPDATE scheduler
        SET expression = :full_expression,
            `timezone` = :timezone,
            next_run = :new_time_utc
        WHERE id = :id");
    $stmt->bindValue('full_expression', $full_expression, PDO::PARAM_STR);
    $stmt->bindValue('timezone', $timezone, PDO::PARAM_STR);
    $stmt->bindValue('new_time_utc', $new_time_utc, PDO::PARAM_STR);
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $message = _('Task Updated') . '<BR>';

} elseif ($a == 'e') {

    $task = $schedule->getTask($id);

    if ($task) {

        $next_run = $schedule->getNextRun($task->expression, $task->timezone);

        $stmt = $pdo->prepare("
            UPDATE scheduler
            SET active = '1',
                next_run = :next_run
            WHERE id = :id");
        $stmt->bindValue('next_run', $next_run, PDO::PARAM_STR);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $message = _('Task Enabled') . '<BR>';

    }

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

if (in_array($a, array('u', 'e', 'd'), true)) $schedule->releaseTaskLock($id);

$_SESSION['s_message_success'] = ($_SESSION['s_message_success'] ?? '') . ($message ?? '');

header("Location: index.php");
exit;
