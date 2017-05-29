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
        if ($api_results !== false) {

            $xml = simplexml_load_string($api_results);

            $tmpq = $this->system->db()->prepare("
                INSERT INTO dw_dns_records
                (server_id, dns_zone_id, domain, mname, rname, `serial`, refresh, retry, `expire`,
                 minimum, nsdname, `name`, ttl, class, type, address, cname, `exchange`, preference,
                 txtdata, line, nlines, raw, insert_time)
                VALUES
                (:server_id, :zone_id, :domain, :mname, :rname, :serial, :refresh, :retry, :expire, :minimum, :nsdname,
                 :name, :ttl, :class, :type, :address, :cname, :exchange, :preference, :txtdata, :line, :lines, :raw,
                 :insert_time)");

            foreach ($xml->result->record as $hit) {

                $tmpq->execute(array(
                               'server_id' => $server_id,
                               'zone_id' => $zone_id,
                               'domain' => $domain,
                               'mname' => $hit->mname,
                               'rname' => $hit->rname,
                               'serial' => $hit->serial,
                               'refresh' => $hit->refresh,
                               'retry' => $hit->retry,
                               'expire' => $hit->expire,
                               'minimum' => $hit->minimum,
                               'nsdname' => $hit->nsdname,
                               'name' => $hit->name,
                               'ttl' => $hit->ttl,
                               'class' => $hit->class,
                               'type' => $hit->type,
                               'address' => $hit->address,
                               'cname' => $hit->cname,
                               'exchange' => $hit->exchange,
                               'preference' => $hit->preference,
                               'txtdata' => $hit->txtdata,
                               'line' => $hit->Line,
                               'lines' => $hit->Lines,
                               'raw' => $hit->raw,
                               'insert_time' => $this->time->stamp()));

            }

        }
    }

    public function getTotalDwRecords()
    {
        $tmpq = $this->system->db()->query("
            SELECT count(*)
            FROM `dw_dns_records`");
        return $tmpq->fetchColumn();
    }

} //@formatter:on
