<?php
/**
 * /classes/DomainMOD/Log.php
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

class Log
{

    public function __construct($area)
    {
        $this->system = new System();
        $this->time = new Time();
        $this->area = $area;
        if ($_SESSION['s_user_id']) {
            $this->user_id = $_SESSION['s_user_id'];
        } else {
            $this->user_id = 0;
        }
        $this->url = $_SERVER['REQUEST_URI'];
    }

    public function addEntry($level, $message, $extra_info)
    {
        if ($extra_info != '') {
            $extra_info_formatted = $this->formatExtraInfo($extra_info);
        } else {
            $extra_info_formatted = '';
        }
        $tmpq = $this->system->db()->prepare("INSERT INTO `log`
                                             (`user_id`, `area`, `level`, `message`, `extra`, `url`, `insert_time`)
                                             VALUES
                                             (:user_id, :area, :level, :message, :extra, :url, :insert_time)");
        $tmpq->execute(['user_id' => $this->user_id,
                        'area' => $this->area,
                        'level' => $level,
                        'message' => $message,
                        'extra' => $extra_info_formatted,
                        'url' => $this->url,
                        'insert_time' => $this->time->stamp()]);
    }

    public function formatExtraInfo($extra_info)
    {
        $extra_info_formatted = '';
        foreach ($extra_info as $key => $value) {
            $extra_info_formatted .= '"' . $key . '":"' . $value . '", ';
        }
        return substr($extra_info_formatted, 0, -2);
    }
    /*
     * EMERGENCY
     * System is unusable.
     */
    public function emergency($message, $extra_info = '')
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('emergency', $message, $extra_info);
    }

    /*
     * ALERT
     * Action must be taken immediately.
     * Example: Entire website down, database unavailable, etc. This should trigger the SMS alerts and wake you up.
     */
    public function alert($message, $extra_info = '')
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('alert', $message, $extra_info);
    }

    /*
     * CRITICAL
     * Critical conditions.
     * Example: Application component unavailable, unexpected exception.
     */
    public function critical($message, $extra_info = '')
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('critical', $message, $extra_info);
    }

    /*
     * ERROR
     * Runtime errors that do not require immediate action but should typically be logged and monitored.
     */
    public function error($message, $extra_info = '')
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('error', $message, $extra_info);
    }

    /*
     * WARNING
     * Exceptional occurrences that are not errors.
     * Example: Use of deprecated APIs, poor use of an API, undesirable things that are not necessarily wrong.
     */
    public function warning($message, $extra_info = '')
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('warning', $message, $extra_info);
    }

    /*
     * NOTICE
     * Normal but significant events.
     */
    public function notice($message, $extra_info = '')
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('notice', $message, $extra_info);
    }

    /*
     * INFO
     * Interesting events.
     * Example: User logs in, SQL logs.
     */
    public function info($message, $extra_info = '')
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('info', $message, $extra_info);
    }

    /*
     * DEBUG
     * Detailed debug information.
     */
    public function debug($message, $extra_info = '')
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('debug', $message, $extra_info);
    }

    /*
     * LOG
     * Logs with an arbitrary level.
     */
    public function log($message, $extra_info = '')
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('log', $message, $extra_info);
    }

} //@formatter:on
