<?php
/**
 * /classes/DomainMOD/DwBuild.php
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

class DwBuild
{

    public function build($connection)
    {

        $accounts = new DwAccounts();
        $zones = new DwZones();
        $records = new DwRecords();
        $servers = new DwServers();
        $stats = new DwStats();
        $time = new Timestamp();

        $result = $servers->get($connection);
        if ($servers->checkForHosts($result) == '0')
            return false;
        $this->dropDwTables($connection);

        $build_start_time_o = $time->time();

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
        $servers->processEachServer($connection, $result);

        $clean = new DwClean();
        $clean->all($connection);

        $result = $servers->get($connection);
        $stats->updateServerStats($connection, $result);
        $stats->updateDwTotalsTable($connection);
        $this->buildFinish($connection, $build_start_time_o);
        list($temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records) = $stats->getServerTotals($connection);
        $has_empty = $this->checkDwAssets($temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records);
        $this->updateEmpty($connection, $has_empty);

        $result_message = 'Data Warehouse Rebuilt.';

        return $result_message;

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

    public function getBuildTime($build_start_time)
    {

        $time = new Timestamp();

        $build_end_time = $time->time();

        $total_build_time = (strtotime($build_end_time) - strtotime($build_start_time));

        return array($build_end_time, $total_build_time);

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

    public function apiCall($api_call, $host, $protocol, $port, $username, $hash)
    {

        return $this->apiGet($api_call, $host, $protocol, $port, $username, $hash);

    }

    public function apiGet($api_call, $host, $protocol, $port, $username, $hash)
    {

        $query = $protocol . "://" . $host . ":" . $port . $api_call;
        $header = '';
        $curl = curl_init(); // Create Curl Object
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // Allow certs that do not match the domain
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // Allow self-signed certs
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // Return contents of transfer on curl_exec
        $header[0] = "Authorization: WHM " . $username . ":" . preg_replace("'(\r|\n)'", "", $hash); // Remove newlines
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header); // Set curl header
        curl_setopt($curl, CURLOPT_URL, $query); // Set your URL
        $api_results = curl_exec($curl); // Execute Query, assign to $api_results
        if ($api_results === false) {
            error_log("curl_exec error \"" . curl_error($curl) . "\" for " . $query . "");
        }
        curl_close($curl);

        return $api_results;

    }

}
