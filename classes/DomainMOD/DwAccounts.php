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
                ip VARCHAR(15) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
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
        if ($api_results !== false) {

            $xml = simplexml_load_string($api_results);

            $tmpq = $this->system->db()->prepare("
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

            foreach ($xml->acct as $hit) {

                $disklimit_formatted = rtrim($hit->disklimit, 'M');
                $diskused_formatted = rtrim($hit->diskused, 'M');

                $tmpq->execute(['server_id' => $server_id,
                                'domain' => $hit->domain,
                                'ip' => $hit->ip,
                                'owner' => $hit->owner,
                                'user' => $hit->user,
                                'email' => $hit->email,
                                'plan' => $hit->plan,
                                'theme' => $hit->theme,
                                'shell' => $hit->shell,
                                'partition' => $hit->partition,
                                'disklimit' => $disklimit_formatted,
                                'diskused' => $diskused_formatted,
                                'maxaddons' => $hit->maxaddons,
                                'maxftp' => $hit->maxftp,
                                'maxlst' => $hit->maxlst,
                                'maxparked' => $hit->maxparked,
                                'maxpop' => $hit->maxpop,
                                'maxsql' => $hit->maxsql,
                                'maxsub' => $hit->maxsub,
                                'startdate' => $hit->startdate,
                                'unix_startdate' => $hit->unix_startdate,
                                'suspended' => $hit->suspended,
                                'suspendreason' => $hit->suspendreason,
                                'suspendtime' => $hit->suspendtime,
                                'max_email_per_hour' => $hit->MAX_EMAIL_PER_HOUR,
                                'max_defer_fail_percentage' => $hit->MAX_DEFER_FAIL_PERCENTAGE,
                                'min_defer_fail_to_trigger_protection' => $hit->MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION,
                                'insert_time' => $this->time->stamp()]);

            }

        }
    }

    public function getTotalDwAccounts()
    {
        $tmpq = $this->system->db()->query("
            SELECT count(*)
            FROM `dw_accounts`");
        return $tmpq->fetchColumn();
    }

} //@formatter:on
