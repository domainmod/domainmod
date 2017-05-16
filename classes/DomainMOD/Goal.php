<?php
/**
 * /classes/DomainMOD/Goal.php
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
//@formatter:off
namespace DomainMOD;

class Goal
{

    public function installation($dbcon)
    {
        $error = new Error();
        $time = new Time();
        $act_software_version = SOFTWARE_VERSION;
        $act_ip_address = $this->getIp();
        $act_agent = $_SERVER['HTTP_USER_AGENT'];
        $act_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $timestamp = $time->stamp();
        $sql = "INSERT INTO goal_activity
                (type, old_version, new_version, ip, agent, `language`, insert_time)
                 VALUES
                ('i', 'n/a', '" . $act_software_version . "', '" . $act_ip_address . "', '" . $act_agent . "', '" . $act_language . "', '" . $timestamp . "')";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        return $result;
    }

    public function upgrade($dbcon, $act_old_version)
    {
        $error = new Error();
        $time = new Time();
        $act_new_version = SOFTWARE_VERSION;
        $act_ip_address = $this->getIp();
        $act_agent = $_SERVER['HTTP_USER_AGENT'];
        $act_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $timestamp = $time->stamp();
        $sql = "INSERT INTO goal_activity
                (type, old_version, new_version, ip, agent, `language`, insert_time)
                 VALUES
                ('u', '" . $act_old_version . "', '" . $act_new_version . "', '" . $act_ip_address . "', '" . $act_agent . "', '" . $act_language . "', '" . $timestamp . "')";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        return $result;
    }

    public function remote($dbcon)
    {
        $error = new Error();
        $time = new Time();
        $timestamp = $time->stamp();
        $sql = "SELECT id, type, old_version, new_version, ip, agent, `language`, insert_time
                FROM goal_activity
                WHERE new_activity = '1'
                ORDER BY id ASC";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        while ($row = mysqli_fetch_object($result)) {
            $sql2 = "UPDATE goal_activity
                     SET new_activity = '0',
                         update_time = '" . $timestamp . "'
                     WHERE id = '" . $row->id . "'";
            $result2 = mysqli_query($dbcon, $sql2) or $error->outputSqlError($dbcon, '1', 'ERROR');
            $base_url = $this->getBaseUrl($row->type, $row->old_version, $row->new_version);
            $goal_url = $base_url . '&ip=' . urlencode($row->ip) . '&a=' . urlencode($row->agent) . '&l=' . urlencode($row->language) . '&ti=' . urlencode($row->insert_time) . '&tu=' . urlencode($timestamp);
            $this->triggerGoal($goal_url);
        }
        return;
    }

    public function getBaseUrl($goal, $old_version, $new_version)
    {
        if ($goal == 'i') { // install
            return 'https://domainmod.org/installed/index.php?v=' . urlencode($new_version);
        } else { // upgrade
            return 'https://domainmod.org/upgraded/index.php?ov=' . urlencode($old_version) . '&nv=' . urlencode($new_version);
        }
    }

    public function getIp()
    {
        if ($_SERVER['SERVER_ADDR'] == '127.0.0.1' || $_SERVER['SERVER_ADDR'] == '::1') {
            return $_SERVER['REMOTE_ADDR'];
        } else {
            return $_SERVER['SERVER_ADDR'];
        }
    }

    public function triggerGoal($goal_url)
    {
        $context = stream_context_create(array('https' => array('header' => 'Connection: close\r\n')));
        $result = file_get_contents($goal_url, false, $context);
        if (!$result) {
            $handle = curl_init();
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_URL, $goal_url);
            curl_exec($handle);
            curl_close($handle);
        }
        return;
    }

} //@formatter:on
