<?php
/**
 * /classes/DomainMOD/Scheduler.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
namespace DomainMOD;

class Scheduler
{

    public function isRunning($connection, $id)
    {
        $sql_running = "UPDATE scheduler SET is_running = '1' WHERE id = '" . $id . "'";
        return mysqli_query($connection, $sql_running);
    }

    public function isFinished($connection, $id)
    {
        $sql_finished = "UPDATE scheduler SET is_running = '0' WHERE id = '" . $id . "'";
        return mysqli_query($connection, $sql_finished);
    }

    public function updateTime($connection, $id, $timestamp, $next_run, $active)
    {
        $time = new Timestamp();
        $current_time = $time->time();
        $duration = $this->getTimeDifference($timestamp, $current_time);
        if ($active == '1') {
            $sql_update = "UPDATE scheduler
                           SET last_run = '" . $timestamp . "',
                               last_duration = '" . $duration . "',
                               next_run = '" . $next_run . "'
                           WHERE id = '" . $id . "'";
        } else {
            $sql_update = "UPDATE scheduler
                           SET last_run = '" . $timestamp . "',
                               duration = '" . $duration . "'
                           WHERE id = '" . $id . "'";
        }
        return mysqli_query($connection, $sql_update);
    }

    public function getTimeDifference($start_time, $end_time)
    {
        $difference = (strtotime($end_time) - strtotime($start_time));
        $minutes = intval($difference / 60);
        $seconds = $difference - ($minutes * 60);
        if ($minutes != '0') {
            $result = " (<em>" . $minutes . "m " . $seconds . "s</em>)";
        } else {
            $result = " (<em>" . $seconds . "s</em>)";
        }
        return $result;
    }

    public function show($connection, $id)
    {
        $time = new Timestamp();
        $row = mysqli_fetch_object($this->getTask($connection, $id));
        $hour = explode(" ", $row->expression);
        ob_start(); ?>
        <tr>
        <td class='main_table_cell_active_top_aligned' width='600'>
            <strong><?php echo $row->name; ?></strong><BR>
            <?php echo $row->description?><BR><BR><BR>
        </td>
        <td class='main_table_cell_active_top_aligned'>
            <strong>Runs:</strong> <?php echo $row->interval; ?><BR>
            <strong>Status:</strong> <?php echo $this->createActive($row->active, $row->id); ?><BR>
            <?php ?>
            <?php if ($row->last_run != '0000-00-00 00:00:00') {
                $last_run = $time->toUserTimezone($this->getDateOutput($row->last_run));
            } else {
                $last_run = 'n/a';

            }?>
            <strong>Last Run:</strong> <?php echo $last_run; ?><?php echo $row->last_duration; ?><BR><?php
            if ($row->next_run != '0000-00-00 00:00:00') {
                $next_run = $time->toUserTimezone($this->getDateOutput($row->next_run));
                $hour = date('H', strtotime($next_run));
            } else {
                $next_run = 'n/a';

            }?>
            <strong>Next Run:</strong> <?php echo $next_run; ?>
            <BR><BR>
            <?php if ($row->active == '1') { ?>
                <form name="edit_task_form" method="post" action="<?php echo $_SESSION['web_root'];
                ?>/admin/scheduler/update.php">
                    <select name="new_hour">
                        <?php echo $this->hourSelect($hour); ?>
                    </select>
                    <input type="hidden" name="a" value="u">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">&nbsp;&nbsp;
                    <input type="submit" name="button" value="Change Time &raquo;">
                </form><BR><BR>
            <?php } ?>
        </td>
        </tr><?php
        return ob_get_clean();
    }

    public function getTask($connection, $id)
    {
        $sql_task = "SELECT id, `name`, description, `interval`, expression, last_run, last_duration, next_run, active
                     FROM scheduler
                     WHERE id = '" . $id . "'
                     ORDER BY sort_order ASC";
        return mysqli_query($connection, $sql_task);
    }

    public function createActive($active, $id)
    {
        $result = '<strong><font color=\'green\'>Active</font></strong> [<a class=\'invisiblelink\'
            href=\'update.php?a=d&id=' . $id . '\'>disable</a>] [<a class=\'invisiblelink\'
            href=\'run.php?id=' . $id . '\'>run now</a>]';
        if ($active == '0') {
            $result = '<strong><font color=\'red\'>Inactive</font></strong> [<a class=\'invisiblelink\'
                href=\'update.php?a=e&id=' . $id . '\'>enable</a>] [<a class=\'invisiblelink\'
                href=\'run.php?id=' . $id . '\'>run now</a>]';
        }
        return $result;
    }

    public function getDateOutput($next_run)
    {
        if ($next_run == '0000-00-00 00:00:00') {
            return 'n/a';
        } else {
            return $next_run;
        }
    }

    public function hourSelect($hour)
    {
        $hours = array('00' => '00:00', '01' => '01:00', '02' => '02:00', '03' => '03:00', '04' => '04:00', '05' => '05:00',
                       '06' => '06:00', '07' => '07:00', '08' => '08:00', '09' => '09:00', '10' => '10:00',
                       '11' => '11:00', '12' => '12:00', '13' => '13:00', '14' => '14:00', '15' => '15:00',
                       '16' => '16:00', '17' => '17:00', '18' => '18:00', '19' => '19:00', '20' => '20:00',
                       '21' => '21:00', '22' => '22:00', '23' => '23:00');
        ob_start();
        foreach ($hours AS $key => $value) { ?>
            <option value="<?php echo $key; ?>"<?php if ($hour == $key) echo ' selected'; ?>><?php echo $value; ?>
            </option><?php
        }
        return ob_get_clean();
    }

}
