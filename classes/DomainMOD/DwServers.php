<?php
/**
 * /classes/DomainMOD/DwServers.php
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

class DwServers
{
    public $deeb;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
    }

    public function get()
    {
        return $this->deeb->cnxx->query("
            SELECT id, `host`, protocol, `port`, username, api_token, `hash`
            FROM dw_servers
            ORDER BY `name`")->fetchAll();
    }

    public function processEachServer($result)
    {
        $build = new DwBuild();
        $accounts = new DwAccounts();
        $zones = new DwZones();
        $time = new Time();

        $pdo = $this->deeb->cnxx;
        $stmt = $pdo->prepare("
            UPDATE dw_servers
            SET build_start_time = :build_start_time,
                build_status = '0'
            WHERE id = :id");
        $stmt->bindParam('build_start_time', $bind_build_start_time, \PDO::PARAM_STR);
        $stmt->bindParam('id', $bind_id, \PDO::PARAM_INT);

        foreach ($result as $row) {

            $bind_build_start_time = $time->stamp();
            $bind_id = $row->id;
            $stmt->execute();

            $api_call = $accounts->getApiCall();
            $api_results = $build->apiCall($api_call, $row->host, $row->protocol, $row->port, $row->username,
                $row->api_token, $row->hash);
            $accounts->insertAccounts($api_results, $row->id);

            $api_call = $zones->getApiCall();
            $api_results = $build->apiCall($api_call, $row->host, $row->protocol, $row->port, $row->username,
                $row->api_token, $row->hash);
            $zones->insertZones($api_results, $row->id);

            $result_zones = $zones->getInsertedZones($row->id);
            $zones->processEachZone($result_zones, $row->id, $row->protocol, $row->host, $row->port, $row->username,
                $row->api_token, $row->hash);
            $this->serverFinish($row->id, $bind_build_start_time);

        }
    }

    public function serverFinish($server_id, $build_start_time)
    {
        $pdo = $this->deeb->cnxx;

        $build = new DwBuild();

        list($build_end_time, $total_build_time) = $build->getBuildTime($build_start_time);

        $stmt = $pdo->prepare("
            UPDATE dw_servers
            SET build_status = '1',
                build_end_time = :build_end_time,
                build_time = :total_build_time,
                has_ever_been_built = '1'
            WHERE id = :server_id");
        $stmt->bindValue('build_end_time', $build_end_time, \PDO::PARAM_STR);
        $stmt->bindValue('total_build_time', $total_build_time, \PDO::PARAM_INT);
        $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

} //@formatter:on
