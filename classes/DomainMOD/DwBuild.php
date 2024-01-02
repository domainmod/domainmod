<?php
/**
 * /classes/DomainMOD/DwBuild.php
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

class DwBuild
{
    public $deeb;
    public $log;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.dwbuild');
    }

    public function build()
    {
        $accounts = new DwAccounts();
        $zones = new DwZones();
        $records = new DwRecords();
        $servers = new DwServers();
        $stats = new DwStats();
        $time = new Time();

        $pdo = $this->deeb->cnxx;
        $result = $servers->get();

        if (!$result) {

            $log_message = 'There are no web servers to process in the DW';
            $this->log->info($log_message);
            return $log_message;

        }

        $this->dropDwTables();

        $build_start_time_o = $time->stamp();

        $stmt = $pdo->prepare("
            UPDATE dw_servers
            SET build_status_overall = '0',
                build_start_time_overall = :build_start_time_o,
                build_end_time_overall = '1970-01-01 00:00:00',
                build_time = '0',
                build_status = '0',
                build_start_time = '1970-01-01 00:00:00',
                build_end_time = '1970-01-01 00:00:00',
                build_time_overall = '0',
                dw_accounts = '0',
                dw_dns_zones = '0',
                dw_dns_records = '0'");
        $stmt->bindValue('build_start_time_o', $build_start_time_o, \PDO::PARAM_STR);
        $stmt->execute();

        $accounts->createTable();
        $zones->createTable();
        $records->createTable();
        $servers->processEachServer($result);

        $clean = new DwClean();
        $clean->all();

        $result = $servers->get();
        $stats->updateServerStats($result);
        $stats->updateDwTotalsTable();
        $this->buildFinish($build_start_time_o);
        list($temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records) = $stats->getServerTotals();
        $has_empty = $this->checkDwAssets($temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records);
        $this->updateEmpty($has_empty);

        $result_message = _('The Data Warehouse has been rebuilt');
        return $result_message;
    }

    public function dropDwTables()
    {
        $pdo = $this->deeb->cnxx;
        $pdo->query("DROP TABLE IF EXISTS dw_accounts");
        $pdo->query("DROP TABLE IF EXISTS dw_dns_zones");
        $pdo->query("DROP TABLE IF EXISTS dw_dns_records");
    }

    public function buildFinish($build_start_time_o)
    {
        $pdo = $this->deeb->cnxx;

        list($build_end_time_o, $total_build_time_o) = $this->getBuildTime($build_start_time_o);

        $stmt = $pdo->prepare("
            UPDATE dw_servers
            SET build_status_overall = '1',
                build_end_time_overall = :build_end_time_o,
                build_time_overall = :total_build_time_o,
                has_ever_been_built_overall = '1'");
        $stmt->bindValue('build_end_time_o', $build_end_time_o, \PDO::PARAM_STR);
        $stmt->bindValue('total_build_time_o', $total_build_time_o, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function getBuildTime($build_start_time)
    {
        $time = new Time();

        $build_end_time = $time->stamp();

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

    public function updateEmpty($empty_assets)
    {
        if ($empty_assets == '1') {

            $this->deeb->cnxx->query("
                UPDATE dw_servers
                SET build_status_overall = '0',
                    build_start_time_overall = '1970-01-01 00:00:00',
                    build_end_time_overall = '1970-01-01 00:00:00',
                    build_time = '0',
                    build_status = '0',
                    build_start_time = '1970-01-01 00:00:00',
                    build_end_time = '1970-01-01 00:00:00',
                    build_time_overall = '0',
                    build_status_overall = '0',
                    dw_accounts = '0',
                    dw_dns_zones = '0',
                    dw_dns_records = '0'");

        }
    }

    public function apiCall($api_call, $host, $protocol, $port, $username, $api_token, $hash)
    {
        return $this->apiGet($api_call, $host, $protocol, $port, $username, $api_token, $hash);
    }

    public function apiGet($api_call, $host, $protocol, $port, $username, $api_token, $hash)
    {
        $query = $protocol . "://" . $host . ":" . $port . $api_call;
        $curl = curl_init(); // Create Curl Object
        $header = array();
        if ($api_token != "") {
            $header[0] = "Authorization: WHM " . $username . ":" . $api_token;
        } else {
            $header[0] = "Authorization: WHM " . $username . ":" . preg_replace("'(\r|\n)'", "", $hash); // Remove newlines
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header); // Set curl header
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Return contents of transfer on curl_exec
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // Allow certs that do not match the domain
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Allow self-signed certs
        curl_setopt($curl, CURLOPT_URL, $query); // Set your URL
        $api_results = curl_exec($curl); // Execute Query, assign to $api_results
        if ($api_results === false) {
            error_log("curl_exec error \"" . curl_error($curl) . "\" for " . $query . "");
        }
        curl_close($curl);

        return $api_results;
    }

    public function convertToArray($api_result)
    {
        return json_decode($api_result, true);
    }

} //@formatter:on
