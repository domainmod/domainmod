<?php
/**
 * /classes/DomainMOD/Log.php
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
//@formatter:off
namespace DomainMOD;

class Log
{
    public $area;
    public $time;
    public $url;
    public $user_id;

    public function __construct($area)
    {
        $this->area = $area;
        $this->time = new Time();

        if (isset($_SERVER['REQUEST_URI'])) {
            // The security token is never stored in the log, and neither is cron.php's key.
            $csrf = new Csrf();
            $this->url = $csrf->removeToken($_SERVER['REQUEST_URI']);
            if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'cron.php') $this->url = $csrf->removeParameter($this->url, 'key');
        } else {
            $this->url = '';
        }

        if (isset($_SESSION['s_user_id'])) {
            $this->user_id = $_SESSION['s_user_id'];
        } else {
            $this->user_id = 0;
        }
    }

    public function addEntry($level, $message, $extra_info)
    {
        $deeb = Database::getInstance();
        $pdo = $deeb->cnxx;
        if (!empty($extra_info)) {
            $extra_info_formatted = $this->formatExtraInfo($extra_info);
        } else {
            $extra_info_formatted = '';
        }

        $stmt = $pdo->prepare("
            INSERT INTO `log`
            (`user_id`, `area`, `level`, `message`, `extra`, `url`, `insert_time`)
            VALUES
            (:user_id, :area, :level, :message, :extra, :url, :insert_time)");
        $stmt->bindValue('user_id', $this->user_id, \PDO::PARAM_INT);
        $stmt->bindValue('area', $this->area, \PDO::PARAM_STR);
        $stmt->bindValue('level', $level, \PDO::PARAM_STR);
        $stmt->bindValue('message', $message, \PDO::PARAM_LOB);
        $stmt->bindValue('extra', $extra_info_formatted, \PDO::PARAM_LOB);
        $stmt->bindValue('url', $this->url, \PDO::PARAM_LOB);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();
    }

    /*
     * An entry's extra details, saved as a JSON object (such as {"Last Error":"","Domain":"example.com"}) so the Debug
     * Log can read them back (see extraPairs()). A value with a malformed UTF-8 byte keeps the rest of its text, with
     * the bad byte replaced. Should the details still not encode, they're saved in the older format ("key":"value",
     * "key":"value"), so an entry never loses them.
     */
    public function formatExtraInfo($extra_info)
    {
        $last_error = error_get_last();
        $last_error_message = array(_('Last Error') => $last_error['message'] ?? ''); // null when there's been no PHP error
        $merged_array = array_merge($last_error_message, $extra_info);

        $json = json_encode((object) $merged_array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json !== false) return $json;

        $extra_info_formatted = '';
        foreach ($merged_array as $key => $value) {
            $extra_info_formatted .= '"' . $key . '":"' . $value . '", ';
        }
        return substr($extra_info_formatted, 0, -2);
    }

    /*
     * An entry's stored extra details as their keys and values, all plain strings, for the Debug Log to show. It reads
     * both a JSON object (newer entries) and the older format, "key":"value" pairs without the braces. Only a flat
     * object counts: string and number values pass as they are, true and false become "true" and "false", and null an
     * empty string. It returns null when the text can't be read that way (such as an older entry whose value held a
     * quote or a backslash, which that format didn't escape, or a nested object or list), so the page shows the stored
     * text instead.
     */
    public static function extraPairs($extra)
    {
        $extra = trim((string) $extra);
        if ($extra === '') return null;

        if ($extra[0] !== '{') {
            // The older format never escaped a backslash, so one would be read as an escape and change the value
            if (strpos($extra, '\\') !== false) return null;
            $extra = '{' . $extra . '}';
        }

        $decoded = json_decode($extra, true, 2, JSON_BIGINT_AS_STRING);
        if (!is_array($decoded) || !$decoded) return null;

        $pairs = array();
        foreach ($decoded as $key => $value) {
            if (is_array($value)) return null;
            if ($value === true) {
                $value = 'true';
            } elseif ($value === false) {
                $value = 'false';
            }
            $pairs[(string) $key] = (string) $value;
        }
        return $pairs;
    }

    /*
     * The pairs from extraPairs() as Layout::factList() pairs, keeping every key: an empty value shows as "-", since
     * factList() leaves out a pair with no value.
     */
    public static function extraFacts($pairs)
    {
        $facts = array();
        foreach ($pairs as $key => $value) {
            $facts[] = array('label' => (string) $key, 'value' => (string) $value === '' ? '-' : (string) $value);
        }
        return $facts;
    }

    /*
     * An entry's level as its translated name and the colour that shows how serious it is: red (danger) for emergency,
     * alert, critical, and error, yellow (warning) for warning, blue (info) for notice and info, and grey (secondary)
     * for debug and anything else (such as log(), whose stored word is shown as it is).
     */
    public static function levelInfo($level)
    {
        $levels = array(
            'emergency' => array(_('Emergency'), 'danger'),
            'alert' => array(_('Alert'), 'danger'),
            'critical' => array(_('Critical'), 'danger'),
            'error' => array(_('Error'), 'danger'),
            'warning' => array(_('Warning'), 'warning'),
            'notice' => array(_('Notice'), 'info'),
            'info' => array(_('Info'), 'info'),
            'debug' => array(_('Debug'), 'secondary'),
        );
        return $levels[strtolower((string) $level)] ?? array((string) $level, 'secondary');
    }

    // An entry's level as a badge with its translated name, escaped here (see levelInfo())
    public static function levelBadge($level)
    {
        list($name, $colour) = self::levelInfo($level);
        return '<span class="badge text-bg-' . $colour . '">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    /*
     * EMERGENCY
     * System is unusable.
     */
    public function emergency($message, $extra_info = array())
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('emergency', $message, $extra_info);
    }

    /*
     * ALERT
     * Action must be taken immediately.
     * Example: Entire website down, database unavailable, etc. This should trigger the SMS alerts and wake you up.
     */
    public function alert($message, $extra_info = array())
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('alert', $message, $extra_info);
    }

    /*
     * CRITICAL
     * Critical conditions.
     * Example: Application component unavailable, unexpected exception.
     */
    public function critical($message, $extra_info = array())
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('critical', $message, $extra_info);
    }

    /*
     * ERROR
     * Runtime errors that do not require immediate action but should typically be logged and monitored.
     */
    public function error($message, $extra_info = array())
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('error', $message, $extra_info);
    }

    /*
     * WARNING
     * Exceptional occurrences that are not errors.
     * Example: Use of deprecated APIs, poor use of an API, undesirable things that are not necessarily wrong.
     */
    public function warning($message, $extra_info = array())
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('warning', $message, $extra_info);
    }

    /*
     * NOTICE
     * Normal but significant events.
     */
    public function notice($message, $extra_info = array())
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('notice', $message, $extra_info);
    }

    /*
     * INFO
     * Interesting events.
     * Example: User logs in, SQL logs.
     */
    public function info($message, $extra_info = array())
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('info', $message, $extra_info);
    }

    /*
     * DEBUG
     * Detailed debug information.
     */
    public function debug($message, $extra_info = array())
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('debug', $message, $extra_info);
    }

    /*
     * LOG
     * Logs with an arbitrary level.
     */
    public function log($message, $extra_info = array())
    {
        if (DEBUG_MODE != 1) return;
        $this->addEntry('log', $message, $extra_info);
    }

} //@formatter:on
