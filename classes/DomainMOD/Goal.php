<?php
/**
 * /classes/DomainMOD/Goal.php
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

class Goal
{
    public $deeb;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->time = new Time();
    }

    public function installation()
    {
        $pdo = $this->deeb->cnxx;

        $act_software_version = SOFTWARE_VERSION;
        $act_ip_address = $this->getIp();
        $act_agent = $this->getAgent();
        $act_language = $this->getLanguage();

        $stmt = $pdo->prepare("
            INSERT INTO goal_activity
            (type, old_version, new_version, ip, agent, `language`, insert_time)
             VALUES
            ('i', 'n/a', :act_software_version, :act_ip_address, :act_agent, :act_language, :insert_time)");
        $stmt->bindValue('act_software_version', $act_software_version, \PDO::PARAM_STR);
        $stmt->bindValue('act_ip_address', $act_ip_address, \PDO::PARAM_STR);
        $stmt->bindValue('act_agent', $act_agent, \PDO::PARAM_LOB);
        $stmt->bindValue('act_language', $act_language, \PDO::PARAM_STR);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();
    }

    public function upgrade($act_old_version)
    {
        $pdo = $this->deeb->cnxx;

        $act_new_version = SOFTWARE_VERSION;
        $act_ip_address = $this->getIp();
        $act_agent = $this->getAgent();
        $act_language = $this->getLanguage();

        $stmt = $pdo->prepare("
            INSERT INTO goal_activity
            (type, old_version, new_version, ip, agent, `language`, insert_time)
             VALUES
            ('u', :act_old_version, :act_new_version, :act_ip_address, :act_agent, :act_language, :insert_time)");
        $stmt->bindValue('act_old_version', $act_old_version, \PDO::PARAM_STR);
        $stmt->bindValue('act_new_version', $act_new_version, \PDO::PARAM_STR);
        $stmt->bindValue('act_ip_address', $act_ip_address, \PDO::PARAM_STR);
        $stmt->bindValue('act_agent', $act_agent, \PDO::PARAM_LOB);
        $stmt->bindValue('act_language', $act_language, \PDO::PARAM_STR);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();
    }

    public function remote()
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT id, type, old_version, new_version, ip, agent, `language`, insert_time
            FROM goal_activity
            WHERE new_activity = '1'
            ORDER BY id ASC")->fetchAll();

        if ($result) {

            $stmt = $pdo->prepare("
                UPDATE goal_activity
                SET new_activity = '0',
                    update_time = :timestamp
                WHERE id = :id");
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('timestamp', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->bindParam('id', $bind_id, \PDO::PARAM_INT);

            foreach ($result as $row) {

                $bind_id = $row->id;
                $stmt->execute();

                $base_url = $this->getBaseUrl($row->type, $row->old_version, $row->new_version);
                $goal_url = $base_url . '&ip=' . urlencode($row->ip) . '&a=' . urlencode($row->agent) . '&l=' .
                urlencode($row->language) . '&ti=' . urlencode($row->insert_time) . '&tu=' .
                urlencode($this->time->stamp());
                $this->triggerGoal($goal_url);

            }

        }
    }

    public function getBaseUrl($goal, $old_version, $new_version)
    {
        if ($goal == 'i') { // install
            return 'https://domainmod.org/installed/index.php?v=' . urlencode($new_version);
        } else { // upgrade
            return 'https://domainmod.org/upgraded/index.php?ov=' . urlencode($old_version) . '&nv=' .
                urlencode($new_version);
        }
    }

    public function getIp()
    {
        $server_addr = $_SERVER['SERVER_ADDR'] ?? '';

        if ($server_addr == '' || $server_addr == '127.0.0.1' || $server_addr == '::1') {
            return $_SERVER['REMOTE_ADDR'] ?? '';
        } else {
            return $server_addr;
        }
    }

    public function getAgent()
    {
        return $this->cleanHeader($_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    public function getLanguage()
    {
        $language = $this->cleanHeader($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($language == '') $language = 'en';

        // goal_activity.language is VARCHAR(100)
        return substr($language, 0, 100);
    }

    public function cleanHeader($value)
    {
        // Keep printable ASCII only, so a header the utf8 columns can't store doesn't make the insert fail
        return preg_replace('/[^\x20-\x7E]/', '', (string) $value);
    }

    public function triggerGoal($goal_url)
    {
        $system = new System();
        $system->getFileContents('Log Goal', 'error', $goal_url);
    }

} //@formatter:on
