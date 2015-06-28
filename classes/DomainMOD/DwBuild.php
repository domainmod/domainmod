<?php
/**
 * /classes/DomainMOD/DwBuild.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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

class DwBuild
{

    public function build($connection)
    {

        $accounts = new DwAccounts();
        $zones = new DwZones();
        $records = new DwRecords();
        $stats = new DwStats();

        $result = $this->getServers($connection);
        if ($this->checkForHosts($result) == '0')
            return false;
        $this->dropDwTables($connection);

        $build_start_time_o = $this->time();

        $sql = "UPDATE dw_servers
                SET build_status_overall = '0',
                    build_start_time_overall = '" . $build_start_time_o . "',
                    build_end_time_overall = '0000-00-00 00:00:00',
                    build_time = '0',
                    build_status = '0',
                    build_start_time = '0000-00-00 00:00:00',
                    build_end_time = '0000-00-00 00:00:00',
                    build_time_overall = '0',
                    dw_accounts = '0',
                    dw_dns_zones = '0',
                    dw_dns_records = '0'";
        mysqli_query($connection, $sql);

        $accounts->createTable($connection);
        $zones->createTable($connection);
        $records->createTable($connection);
        $this->processEachServer($connection, $result);
        $records->cleanupRecords($connection);
        $records->reorderRecords($connection);
        $result = $this->getServers($connection);
        $stats->updateServerStats($connection, $result);
        $stats->updateDwTotalsTable($connection);
        $this->buildFinish($connection, $build_start_time_o);
        list($temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records) = $stats->getServerTotals($connection);
        $has_empty = $this->checkDwAssets($temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records);
        $this->updateEmpty($connection, $has_empty);

        $result_message = 'Data Warehouse Rebuilt.<BR>';

        return $result_message;

    }

    public function getServers($connection)
    {

        $sql = "SELECT id, `host`, protocol, `port`, username, `hash`
                FROM dw_servers
                ORDER BY `name`";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function checkForHosts($result)
    {

        if (mysqli_num_rows($result) >= 1) {

            return '1';

        } else {

            return '0';

        }

    }

    public function dropDwTables($connection)
    {

        $sql_accounts = "DROP TABLE IF EXISTS dw_accounts";
        mysqli_query($connection, $sql_accounts);

        $sql_zones = "DROP TABLE IF EXISTS dw_dns_zones";
        mysqli_query($connection, $sql_zones);

        $sql_records = "DROP TABLE IF EXISTS dw_dns_records";
        mysqli_query($connection, $sql_records);

        return true;

    }

    public function time()
    {

        $time = new Timestamp();

        return $time->time();

    }

    public function processEachServer($connection, $result)
    {

        $accounts = new DwAccounts();
        $zones = new DwZones();

        while ($row = mysqli_fetch_object($result)) {

            $build_start_time = $this->time();

            $sql = "UPDATE dw_servers
                    SET build_start_time = '" . $build_start_time . "',
                        build_status = '0'
                    WHERE id = '" . $row->id . "'";
            mysqli_query($connection, $sql);

            $api_results = $accounts->apiGetAccounts($row->protocol, $row->host, $row->port, $row->username,
                $row->hash);
            $accounts->insertAccounts($connection, $api_results, $row->id);
            $api_results = $zones->apiGetZones($row->protocol, $row->host, $row->port, $row->username, $row->hash);
            $zones->insertZones($connection, $api_results, $row->id);
            $result_zones = $zones->getInsertedZones($connection, $row->id);
            $zones->processEachZone($connection, $result_zones, $row->id, $row->protocol, $row->host, $row->port,
                $row->username, $row->hash);
            $this->serverFinish($connection, $row->id, $build_start_time);

        }

        return true;

    }

    public function apiCall($api_type, $protocol, $host, $port, $username, $hash)
    {

        $query = $protocol . "://" . $host . ":" . $port . $api_type;
        $header = '';
        $curl = curl_init(); # Create Curl Object
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); # Allow certs that do not match the domain
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); # Allow self-signed certs
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); # Return contents of transfer on curl_exec
        $header[0] = "Authorization: WHM " . $username . ":" . preg_replace("'(\r|\n)'", "", $hash); # Remove newlines
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header); # Set curl header
        curl_setopt($curl, CURLOPT_URL, $query); # Set your URL
        $api_results = curl_exec($curl); # Execute Query, assign to $api_results
        if ($api_results === false) {
            error_log("curl_exec error \"" . curl_error($curl) . "\" for " . $query . "");
        }
        curl_close($curl);

        return $api_results;

    }

    public function serverFinish($connection, $server_id, $build_start_time)
    {

        list($build_end_time, $total_build_time) = $this->getBuildTime($build_start_time);

        $sql = "UPDATE dw_servers
                SET build_status = '1',
                    build_end_time = '" . $build_end_time . "',
                    build_time = '" . $total_build_time . "',
                    has_ever_been_built = '1'
                WHERE id = '" . $server_id . "'";
        mysqli_query($connection, $sql);

        return true;

    }

    public function getBuildTime($build_start_time)
    {

        $build_end_time = $this->time();

        $total_build_time = (strtotime($build_end_time) - strtotime($build_start_time));

        return array($build_end_time, $total_build_time);

    }

    public function buildFinish($connection, $build_start_time_o)
    {

        list($build_end_time_o, $total_build_time_o) = $this->getBuildTime($build_start_time_o);

        $sql = "UPDATE dw_servers
                SET build_status_overall = '1',
                    build_end_time_overall = '" . $build_end_time_o . "',
                    build_time_overall = '" . $total_build_time_o . "',
                    has_ever_been_built_overall = '1'";
        mysqli_query($connection, $sql);

        return true;

    }

    public function checkDwAssets($temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records)
    {

        $empty_assets = '0';

        if ($temp_dw_accounts == "0" && $temp_dw_dns_zones == "0" && $temp_dw_dns_records == "0") {

            $empty_assets = '1';

        }

        return $empty_assets;

    }

    public function updateEmpty($connection, $empty_assets)
    {

        if ($empty_assets == '1') {

            $sql = "UPDATE dw_servers
                    SET build_status_overall = '0',
                        build_start_time_overall = '0000-00-00 00:00:00',
                        build_end_time_overall = '0000-00-00 00:00:00',
                        build_time = '0',
                        build_status = '0',
                        build_start_time = '0000-00-00 00:00:00',
                        build_end_time = '0000-00-00 00:00:00',
                        build_time_overall = '0',
                        build_status_overall = '0',
                        dw_accounts = '0',
                        dw_dns_zones = '0',
                        dw_dns_records = '0'";
            mysqli_query($connection, $sql);

        }

        return true;

    }

}
