<?php
/**
 * /classes/DomainMOD/DwZones.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
?>
<?php
namespace DomainMOD;

class DwZones
{

    public function createTable($connection)
    {

        $sql_zones = "CREATE TABLE IF NOT EXISTS dw_dns_zones (
                          id INT(10) NOT NULL AUTO_INCREMENT,
                          server_id INT(10) NOT NULL,
                          domain VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                          zonefile VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                          insert_time DATETIME NOT NULL,
                          PRIMARY KEY  (id)
                          ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1";
        mysqli_query($connection, $sql_zones);

        return true;

    }

    public function getApiCall()
    {

        return "/xml-api/listzones";

    }

    public function insertZones($connection, $api_results, $server_id)
    {

        if ($api_results !== false) {

            $xml = simplexml_load_string($api_results);

            $time = new Time();

            foreach ($xml->zone as $hit) {

                $sql = "INSERT INTO dw_dns_zones
                        (server_id, domain, zonefile, insert_time)
                        VALUES
                        ('" . $server_id . "', '" . $hit->domain . "', '" . $hit->zonefile . "', '" . $time->stamp()
                    . "')";
                mysqli_query($connection, $sql);

            }

        }

        return true;

    }

    public function getInsertedZones($connection, $server_id)
    {

        $sql = "SELECT id, domain
                FROM dw_dns_zones
                WHERE server_id = '" . $server_id . "'
                ORDER BY domain";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function processEachZone($connection, $result_zones, $server_id, $protocol, $host, $port, $username, $hash)
    {

        while ($row_zones = mysqli_fetch_object($result_zones)) {

            $build = new DwBuild();
            $records = new DwRecords();

            $api_call = $records->getApiCall($row_zones->domain);
            $api_results = $build->apiCall($api_call, $host, $protocol, $port, $username, $hash);
            $records->insertRecords($connection, $api_results, $server_id, $row_zones->id, $row_zones->domain);

        }

    }

    public function getTotalDwZones($connection)
    {

        $total_dw_zones = '';

        $sql_zones = "SELECT count(*) AS total_dw_zones
                      FROM `dw_dns_zones`";
        $result_zones = mysqli_query($connection, $sql_zones);

        while ($row_zones = mysqli_fetch_object($result_zones)) {

            $total_dw_zones = $row_zones->total_dw_zones;

        }

        return $total_dw_zones;

    }

}
