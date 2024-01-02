<?php
/**
 * /classes/DomainMOD/DwRecords.php
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

class DwRecords
{
    public $deeb;
    public $dwbuild;
    public $log;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->dwbuild = new DwBuild();
        $this->log = new Log('class.dwrecords');
        $this->time = new Time();
    }

    public function createTable()
    {
        $this->deeb->cnxx->query("
            CREATE TABLE IF NOT EXISTS dw_dns_records (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                server_id INT(10) UNSIGNED NOT NULL,
                dns_zone_id INT(10) UNSIGNED NOT NULL,
                domain VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                zonefile VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                new_order INT(10) UNSIGNED NOT NULL,
                mname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                rname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `serial` INT(10) UNSIGNED NOT NULL,
                refresh VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                retry INT(10) UNSIGNED NOT NULL,
                expire VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                minimum INT(10) UNSIGNED NOT NULL,
                nsdname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                ttl INT(10) UNSIGNED NOT NULL,
                class VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                address VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                cname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `exchange` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                preference INT(10) UNSIGNED NOT NULL,
                txtdata LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                line INT(10) UNSIGNED NOT NULL,
                nlines VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                raw LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                formatted_line VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                formatted_type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                formatted_output LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                insert_time DATETIME NOT NULL,
                PRIMARY KEY  (id)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");
    }

    public function getApiCall($domain)
    {
        return "/json-api/dumpzone?api.version=1&domain=" . $domain;
    }

    public function insertRecords($api_results, $server_id, $zone_id, $domain)
    {
        $pdo = $this->deeb->cnxx;
        $array_results = $this->dwbuild->convertToArray($api_results);

        if ($array_results['metadata']['result'] !== 1) {

            $log_message = 'Unable to retrieve DNS Records from WHM';
            $log_extra = array('Server ID' => $server_id, 'Domain' => $domain, 'Zone ID' => $zone_id, 'API Results' => $array_results);
            $this->log->critical($log_message, $log_extra);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO dw_dns_records
                (server_id, dns_zone_id, domain, mname, rname, `serial`, refresh, retry, `expire`,
                 minimum, nsdname, `name`, ttl, class, type, address, cname, `exchange`, preference,
                 txtdata, line, nlines, raw, insert_time)
                VALUES
                (:server_id, :zone_id, :domain, :mname, :rname, :serial, :refresh, :retry, :expire, :minimum, :nsdname,
                 :name, :ttl, :class, :type, :address, :cname, :exchange, :preference, :txtdata, :line, :nlines, :raw,
                 :insert_time)");
            $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
            $stmt->bindValue('zone_id', $zone_id, \PDO::PARAM_INT);
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->bindParam('mname', $bind_mname, \PDO::PARAM_STR);
            $stmt->bindParam('rname', $bind_rname, \PDO::PARAM_STR);
            $stmt->bindParam('serial', $bind_serial, \PDO::PARAM_INT);
            $stmt->bindParam('refresh', $bind_refresh, \PDO::PARAM_STR);
            $stmt->bindParam('retry', $bind_retry, \PDO::PARAM_INT);
            $stmt->bindParam('expire', $bind_expire, \PDO::PARAM_STR);
            $stmt->bindParam('minimum', $bind_minimum, \PDO::PARAM_INT);
            $stmt->bindParam('nsdname', $bind_nsdname, \PDO::PARAM_STR);
            $stmt->bindParam('name', $bind_name, \PDO::PARAM_STR);
            $stmt->bindParam('ttl', $bind_ttl, \PDO::PARAM_INT);
            $stmt->bindParam('class', $bind_class, \PDO::PARAM_STR);
            $stmt->bindParam('type', $bind_type, \PDO::PARAM_STR);
            $stmt->bindParam('address', $bind_address, \PDO::PARAM_STR);
            $stmt->bindParam('cname', $bind_cname, \PDO::PARAM_STR);
            $stmt->bindParam('exchange', $bind_exchange, \PDO::PARAM_STR);
            $stmt->bindParam('preference', $bind_preference, \PDO::PARAM_INT);
            $stmt->bindParam('txtdata', $bind_txtdata, \PDO::PARAM_LOB);
            $stmt->bindParam('line', $bind_line, \PDO::PARAM_INT);
            $stmt->bindParam('nlines', $bind_nlines, \PDO::PARAM_STR);
            $stmt->bindParam('raw', $bind_raw, \PDO::PARAM_LOB);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);

            foreach ($array_results['data']['zone'][0]['record'] as $record) {

                $bind_mname = $record['mname'] ? $record['mname'] : '';
                $bind_rname = $record['rname'] ? $record['rname'] : '';
                $bind_serial = $record['serial'] ? $record['serial'] : 0;
                $bind_refresh = $record['refresh'] ? $record['refresh'] : '';
                $bind_retry = $record['retry'] ? $record['retry'] : 0;
                $bind_expire = $record['expire'] ? $record['expire'] : '';
                $bind_minimum = $record['minimum'] ? $record['minimum'] : 0;
                $bind_nsdname = $record['nsdname'] ? $record['nsdname'] : '';
                $bind_name = $record['name'] ? $record['name'] : '';
                $bind_ttl = $record['ttl'] ? $record['ttl'] : 0;
                $bind_class = $record['class'] ? $record['class'] : '';
                $bind_type = $record['type'] ? $record['type'] : '';
                $bind_address = $record['address'] ? $record['address'] : '';
                $bind_cname = $record['cname'] ? $record['cname'] : '';
                $bind_exchange = $record['exchange'] ? $record['exchange'] : '';
                $bind_preference = $record['preference'] ? $record['preference'] : 0;
                $bind_txtdata = $record['txtdata'] ? $record['txtdata'] : '';
                $bind_line = $record['Line'] ? $record['Line'] : 0;
                $bind_nlines = $record['Lines'] ? $record['Lines'] : '';
                $bind_raw = $record['raw'] ? $record['raw'] : '';
                $stmt->execute();

            }

        }
    }

    public function getTotalDwRecords()
    {
        return $this->deeb->cnxx->query("
            SELECT count(*)
            FROM `dw_dns_records`")->fetchColumn();
    }

} //@formatter:on
