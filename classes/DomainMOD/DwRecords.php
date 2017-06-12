<?php
/**
 * /classes/DomainMOD/DwRecords.php
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

class DwRecords
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
            CREATE TABLE IF NOT EXISTS dw_dns_records (
                id INT(10) NOT NULL AUTO_INCREMENT,
                server_id INT(10) NOT NULL,
                dns_zone_id INT(10) NOT NULL,
                domain VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                zonefile VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                new_order INT(10) NOT NULL,
                mname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                rname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `serial` INT(20) NOT NULL,
                refresh INT(10) NOT NULL,
                retry INT(10) NOT NULL,
                expire VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                minimum INT(10) NOT NULL,
                nsdname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                ttl INT(10) NOT NULL,
                class VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                address VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                cname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `exchange` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                preference INT(10) NOT NULL,
                txtdata VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                line INT(10) NOT NULL,
                nlines INT(10) NOT NULL,
                raw LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                formatted_line VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                formatted_type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                formatted_output LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                insert_time DATETIME NOT NULL,
                PRIMARY KEY  (id)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");
    }

    public function getApiCall($domain)
    {
        return "/xml-api/dumpzone?domain=" . $domain;
    }

    public function insertRecords($api_results, $server_id, $zone_id, $domain)
    {
        $pdo = $this->system->db();

        if ($api_results !== false) {

            $xml = simplexml_load_string($api_results);

            $stmt = $pdo->prepare("
                INSERT INTO dw_dns_records
                (server_id, dns_zone_id, domain, mname, rname, `serial`, refresh, retry, `expire`,
                 minimum, nsdname, `name`, ttl, class, type, address, cname, `exchange`, preference,
                 txtdata, line, nlines, raw, insert_time)
                VALUES
                (:server_id, :zone_id, :domain, :mname, :rname, :serial, :refresh, :retry, :expire, :minimum, :nsdname,
                 :name, :ttl, :class, :type, :address, :cname, :exchange, :preference, :txtdata, :line, :lines, :raw,
                 :insert_time)");
            $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
            $stmt->bindValue('zone_id', $zone_id, \PDO::PARAM_INT);
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->bindParam('mname', $bind_mname, \PDO::PARAM_STR);
            $stmt->bindParam('rname', $bind_rname, \PDO::PARAM_STR);
            $stmt->bindParam('serial', $bind_serial, \PDO::PARAM_INT);
            $stmt->bindParam('refresh', $bind_refresh, \PDO::PARAM_INT);
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
            $stmt->bindParam('txtdata', $bind_txtdata, \PDO::PARAM_STR);
            $stmt->bindParam('line', $bind_line, \PDO::PARAM_INT);
            $stmt->bindParam('lines', $bind_lines, \PDO::PARAM_INT);
            $stmt->bindParam('raw', $bind_raw, \PDO::PARAM_LOB);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);

            foreach ($xml->result->record as $hit) {

                $bind_mname = $hit->mname;
                $bind_rname = $hit->rname;
                $bind_serial = $hit->serial;
                $bind_refresh = $hit->refresh;
                $bind_retry = $hit->retry;
                $bind_expire = $hit->expire;
                $bind_minimum = $hit->minimum;
                $bind_nsdname = $hit->nsdname;
                $bind_name = $hit->name;
                $bind_ttl = $hit->ttl;
                $bind_class = $hit->class;
                $bind_type = $hit->type;
                $bind_address = $hit->address;
                $bind_cname = $hit->cname;
                $bind_exchange = $hit->exchange;
                $bind_preference = $hit->preference;
                $bind_txtdata = $hit->txtdata;
                $bind_line = $hit->Line;
                $bind_lines = $hit->Lines;
                $bind_raw = $hit->raw;
                $stmt->execute();

            }

        }
    }

    public function getTotalDwRecords()
    {
        return $this->system->db()->query("
            SELECT count(*)
            FROM `dw_dns_records`")->fetchColumn();
    }

} //@formatter:on
