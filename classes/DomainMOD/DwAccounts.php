<?php
/**
 * /classes/DomainMOD/DwAccounts.php
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

class DwAccounts
{
    public $system;
    public $time;

    public function __construct()
    {
        $this->system = new System();
        $this->time = new Time();
    }

    public function createTable()
    {
        $this->system->db()->query("
            CREATE TABLE IF NOT EXISTS dw_accounts (
                id INT(10) NOT NULL AUTO_INCREMENT,
                server_id INT(10) NOT NULL,
                domain VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                ip VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `owner` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `user` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                plan VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                theme VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                shell VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `partition` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                disklimit VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                diskused VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxaddons VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxftp VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxlst VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxparked VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxpop VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxsql VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxsub VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                startdate VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                unix_startdate INT(10) NOT NULL,
                suspended INT(1) NOT NULL,
                suspendreason VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                suspendtime INT(10) NOT NULL,
                MAX_EMAIL_PER_HOUR INT(10) NOT NULL,
                MAX_DEFER_FAIL_PERCENTAGE INT(10) NOT NULL,
                MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION INT(10) NOT NULL,
                insert_time DATETIME NOT NULL,
                PRIMARY KEY  (id)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");
    }

    public function getApiCall()
    {
        return "/xml-api/listaccts?searchtype=domain&search=";
    }

    public function insertAccounts($api_results, $server_id)
    {
        $pdo = $this->system->db();

        if ($api_results !== false) {

            $xml = simplexml_load_string($api_results);

            $stmt = $pdo->prepare("
                INSERT INTO dw_accounts
                (server_id, domain, ip, `owner`, `user`, email, plan, theme, shell, `partition`, disklimit,
                 diskused, maxaddons, maxftp, maxlst, maxparked, maxpop, maxsql, maxsub, startdate,
                 unix_startdate, suspended, suspendreason, suspendtime, MAX_EMAIL_PER_HOUR,
                 MAX_DEFER_FAIL_PERCENTAGE, MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION, insert_time)
                VALUES
                (:server_id, :domain, :ip, :owner, :user, :email, :plan, :theme, :shell, :partition, :disklimit,
                 :diskused, :maxaddons, :maxftp, :maxlst, :maxparked, :maxpop, :maxsql, :maxsub, :startdate,
                 :unix_startdate, :suspended, :suspendreason, :suspendtime, :max_email_per_hour,
                 :max_defer_fail_percentage, :min_defer_fail_to_trigger_protection, :insert_time)");
            $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
            $stmt->bindParam('domain', $bind_domain, \PDO::PARAM_STR);
            $stmt->bindParam('ip', $bind_ip, \PDO::PARAM_STR);
            $stmt->bindParam('owner', $bind_owner, \PDO::PARAM_STR);
            $stmt->bindParam('user', $bind_user, \PDO::PARAM_STR);
            $stmt->bindParam('email', $bind_email, \PDO::PARAM_STR);
            $stmt->bindParam('plan', $bind_plan, \PDO::PARAM_STR);
            $stmt->bindParam('theme', $bind_theme, \PDO::PARAM_STR);
            $stmt->bindParam('shell', $bind_shell, \PDO::PARAM_STR);
            $stmt->bindParam('partition', $bind_partition, \PDO::PARAM_STR);
            $stmt->bindParam('disklimit', $bind_disklimit_formatted, \PDO::PARAM_STR);
            $stmt->bindParam('diskused', $bind_diskused_formatted, \PDO::PARAM_STR);
            $stmt->bindParam('maxaddons', $bind_maxaddons, \PDO::PARAM_STR);
            $stmt->bindParam('maxftp', $bind_maxftp, \PDO::PARAM_STR);
            $stmt->bindParam('maxlst', $bind_maxlst, \PDO::PARAM_STR);
            $stmt->bindParam('maxparked', $bind_maxparked, \PDO::PARAM_STR);
            $stmt->bindParam('maxpop', $bind_maxpop, \PDO::PARAM_STR);
            $stmt->bindParam('maxsql', $bind_maxsql, \PDO::PARAM_STR);
            $stmt->bindParam('maxsub', $bind_maxsub, \PDO::PARAM_STR);
            $stmt->bindParam('startdate', $bind_startdate, \PDO::PARAM_STR);
            $stmt->bindParam('unix_startdate', $bind_unix_startdate, \PDO::PARAM_INT);
            $stmt->bindParam('suspended', $bind_suspended, \PDO::PARAM_INT);
            $stmt->bindParam('suspendreason', $bind_suspendreason, \PDO::PARAM_STR);
            $stmt->bindParam('suspendtime', $bind_suspendtime, \PDO::PARAM_INT);
            $stmt->bindParam('max_email_per_hour', $bind_MAX_EMAIL_PER_HOUR, \PDO::PARAM_INT);
            $stmt->bindParam('max_defer_fail_percentage', $bind_MAX_DEFER_FAIL_PERCENTAGE, \PDO::PARAM_INT);
            $stmt->bindParam('min_defer_fail_to_trigger_protection', $bind_MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);

            foreach ($xml->acct as $hit) {

                $bind_domain = $hit->domain;
                $bind_ip = $hit->ip;
                $bind_owner = $hit->owner;
                $bind_user = $hit->user;
                $bind_email = $hit->email;
                $bind_plan = $hit->plan;
                $bind_theme = $hit->theme;
                $bind_shell = $hit->shell;
                $bind_partition = $hit->partition;
                $bind_disklimit_formatted = rtrim($hit->disklimit, 'M');
                $bind_diskused_formatted = rtrim($hit->diskused, 'M');
                $bind_maxaddons = $hit->maxaddons;
                $bind_maxftp = $hit->maxftp;
                $bind_maxlst = $hit->maxlst;
                $bind_maxparked = $hit->maxparked;
                $bind_maxpop = $hit->maxpop;
                $bind_maxsql = $hit->maxsql;
                $bind_maxsub = $hit->maxsub;
                $bind_startdate = $hit->startdate;
                $bind_unix_startdate = $hit->unix_startdate;
                $bind_suspended = $hit->suspended;
                $bind_suspendreason = $hit->suspendreason;
                $bind_suspendtime = $hit->suspendtime;
                $bind_MAX_EMAIL_PER_HOUR = $hit->MAX_EMAIL_PER_HOUR;
                $bind_MAX_DEFER_FAIL_PERCENTAGE = $hit->MAX_DEFER_FAIL_PERCENTAGE;
                $bind_MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION = $hit->MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION;
                $stmt->execute();

            }

        }
    }

    public function getTotalDwAccounts()
    {
        $pdo = $this->system->db();

        $stmt = $pdo->query("
            SELECT count(*)
            FROM `dw_accounts`");
        return $stmt->fetchColumn();
    }

} //@formatter:on
