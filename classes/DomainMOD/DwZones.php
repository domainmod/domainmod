<?php
/**
 * /classes/DomainMOD/DwZones.php
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

class DwZones
{
    public $deeb;
    public $dwbuild;
    public $log;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->dwbuild = new DwBuild();
        $this->log = new Log('class.dwzones');
        $this->time = new Time();
    }

    public function createTable()
    {
        $this->deeb->cnxx->query("
            CREATE TABLE IF NOT EXISTS dw_dns_zones (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                server_id INT(10) UNSIGNED NOT NULL,
                domain VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                zonefile VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                insert_time DATETIME NOT NULL,
                PRIMARY KEY  (id)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");
    }

    public function getApiCall()
    {
        return "/json-api/listzones?api.version=1";
    }

    public function insertZones($api_results, $server_id)
    {
        $pdo = $this->deeb->cnxx;
        $array_results = $this->dwbuild->convertToArray($api_results);

        if ($array_results['metadata']['result'] !== 1) {

            $log_message = 'Unable to retrieve DNS Zones from WHM';
            $log_extra = array('Server ID' => $server_id, 'API Results' => $array_results);
            $this->log->critical($log_message, $log_extra);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO dw_dns_zones
                (server_id, domain, zonefile, insert_time)
                VALUES
                (:server_id, :domain, :zonefile, :insert_time)");
            $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
            $stmt->bindParam('domain', $bind_domain, \PDO::PARAM_STR);
            $stmt->bindParam('zonefile', $bind_zonefile, \PDO::PARAM_STR);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);

            foreach ($array_results['data']['zone'] as $zone) {

                $bind_domain = $zone['domain'] ? $zone['domain'] : '';
                $bind_zonefile = $zone['zonefile'] ? $zone['zonefile'] : '';
                $stmt->execute();

            }

        }
    }

    public function getInsertedZones($server_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id, domain
            FROM dw_dns_zones
            WHERE server_id = :server_id
            ORDER BY domain");
        $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function processEachZone($result_zones, $server_id, $protocol, $host, $port, $username, $api_token, $hash)
    {
        foreach ($result_zones as $row_zones) {

            $build = new DwBuild();
            $records = new DwRecords();

            $api_call = $records->getApiCall($row_zones->domain);
            $api_results = $build->apiCall($api_call, $host, $protocol, $port, $username, $api_token, $hash);
            $records->insertRecords($api_results, $server_id, $row_zones->id, $row_zones->domain);

        }
    }

    public function getTotalDwZones()
    {
        return $this->deeb->cnxx->query("
            SELECT count(*)
            FROM `dw_dns_zones`")->fetchColumn();
    }

    public function checkForZoneTable()
    {
        return $this->deeb->cnxx->query("SHOW TABLES LIKE 'dw_dns_zones'")->fetchColumn();
    }

    public function checkForZones($domain)
    {
        $table_exists = $this->checkForZoneTable();

        if ($table_exists) {

            $pdo = $this->deeb->cnxx;

            $stmt = $pdo->prepare("
                SELECT id
                FROM dw_dns_zones
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
