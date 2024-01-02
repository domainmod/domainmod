<?php
/**
 * /classes/DomainMOD/DwAccounts.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
    public $deeb;
    public $dwbuild;
    public $log;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->dwbuild = new DwBuild();
        $this->log = new Log('class.dwaccounts');
        $this->time = new Time();
    }

    public function createTable()
    {
        $this->deeb->cnxx->query("
            CREATE TABLE IF NOT EXISTS dw_accounts (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                server_id INT(10) UNSIGNED NOT NULL,
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
                unix_startdate VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                suspended TINYINT(1) NOT NULL,
                suspendreason VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                suspendtime VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                max_email_per_hour VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                max_defer_fail_percentage VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                min_defer_fail_to_trigger_protection VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                insert_time DATETIME NOT NULL,
                PRIMARY KEY  (id)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");
    }

    public function getApiCall()
    {
        return "/json-api/listaccts?api.version=1&searchtype=domain&search=";
    }

    public function insertAccounts($api_results, $server_id)
    {
        $pdo = $this->deeb->cnxx;
        $array_results = $this->dwbuild->convertToArray($api_results);

        if ($array_results['metadata']['result'] !== 1) {

            $log_message = 'Unable to retrieve Accounts from WHM';
            $log_extra = array('Server ID' => $server_id, 'API Results' => $array_results);
            $this->log->critical($log_message, $log_extra);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO dw_accounts
                (server_id, domain, ip, `owner`, `user`, email, plan, theme, shell, `partition`, disklimit,
                 diskused, maxaddons, maxftp, maxlst, maxparked, maxpop, maxsql, maxsub, startdate,
                 unix_startdate, suspended, suspendreason, suspendtime, max_email_per_hour,
                 max_defer_fail_percentage, min_defer_fail_to_trigger_protection, insert_time)
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
            $stmt->bindParam('unix_startdate', $bind_unix_startdate, \PDO::PARAM_STR);
            $stmt->bindParam('suspended', $bind_suspended, \PDO::PARAM_INT);
            $stmt->bindParam('suspendreason', $bind_suspendreason, \PDO::PARAM_STR);
            $stmt->bindParam('suspendtime', $bind_suspendtime, \PDO::PARAM_STR);
            $stmt->bindParam('max_email_per_hour', $bind_max_email_per_hour, \PDO::PARAM_STR);
            $stmt->bindParam('max_defer_fail_percentage', $bind_max_defer_fail_percentage, \PDO::PARAM_STR);
            $stmt->bindParam('min_defer_fail_to_trigger_protection', $bind_min_defer_fail_to_trigger_protection, \PDO::PARAM_STR);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);

            foreach ($array_results['data']['acct'] as $account) {

                $bind_domain = $account['domain'] ? $account['domain'] : '';
                $bind_ip = $account['ip'] ? $account['ip'] : '';
                $bind_owner = $account['owner'] ? $account['owner'] : '';
                $bind_user = $account['user'] ? $account['user'] : '';
                $bind_email = $account['email'] ? $account['email'] : '';
                $bind_plan = $account['plan'] ? $account['plan'] : '';
                $bind_theme = $account['theme'] ? $account['theme'] : '';
                $bind_shell = $account['shell'] ? $account['shell'] : '';
                $bind_partition = $account['partition'] ? $account['partition'] : '';
                $bind_disklimit_formatted = $account['disklimit'] ? rtrim($account['disklimit'], 'M') : '';
                $bind_diskused_formatted = $account['diskused'] ? rtrim($account['diskused'], 'M') : '';
                $bind_maxaddons = $account['maxaddons'] ? $account['maxaddons'] : '';
                $bind_maxftp = $account['maxftp'] ? $account['maxftp'] : '';
                $bind_maxlst = $account['maxlst'] ? $account['maxlst'] : '';
                $bind_maxparked = $account['maxparked'] ? $account['maxparked'] : '';
                $bind_maxpop = $account['maxpop'] ? $account['maxpop'] : '';
                $bind_maxsql = $account['maxsql'] ? $account['maxsql'] : '';
                $bind_maxsub = $account['maxsub'] ? $account['maxsub'] : '';
                $bind_startdate = $account['startdate'] ? $account['startdate'] : '';
                $bind_unix_startdate = $account['unix_startdate'] ? $account['unix_startdate'] : '';
                $bind_suspended = $account['suspended'] ? $account['suspended'] : 0;
                $bind_suspendreason = $account['suspendreason'] ? $account['suspendreason'] : '';
                $bind_suspendtime = $account['suspendtime'] ? $account['suspendtime'] : '';
                $bind_max_email_per_hour = $account['max_email_per_hour'] ? $account['max_email_per_hour'] : '';
                $bind_max_defer_fail_percentage = $account['max_defer_fail_percentage'] ? $account['max_defer_fail_percentage'] : '';
                $bind_min_defer_fail_to_trigger_protection = $account['min_defer_fail_to_trigger_protection'] ? $account['min_defer_fail_to_trigger_protection'] : '';
                $stmt->execute();

            }

        }

    }

    public function getTotalDwAccounts()
    {
        return $this->deeb->cnxx->query("
            SELECT count(*)
            FROM `dw_accounts`")->fetchColumn();
    }

    public function checkForAccountTable()
    {
        return $this->deeb->cnxx->query("SHOW TABLES LIKE 'dw_accounts'")->fetchColumn();
    }

    public function checkForAccounts($domain)
    {
        $table_exists = $this->checkForAccountTable();

        if ($table_exists) {

            $pdo = $this->deeb->cnxx;

            $stmt = $pdo->prepare("
                SELECT id
                FROM dw_accounts
                WHERE domain = :domain
                LIMIT 1");
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetchColumn();

            if ($result) {

                return 1;

            } else {

                return 0;

            }

        } else {

            return 0;

        }

    }

} //@formatter:on
