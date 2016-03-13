<?php
/**
 * /classes/DomainMOD/DwServers.php
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
//@formatter:off
namespace DomainMOD;

class DwServers
{

    public function get($connection)
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

    public function processEachServer($connection, $result)
    {

        $build = new DwBuild();
        $accounts = new DwAccounts();
        $zones = new DwZones();
        $time = new Time();

        while ($row = mysqli_fetch_object($result)) {

            $build_start_time = $time->stamp();

            $sql = "UPDATE dw_servers
                    SET build_start_time = '" . $build_start_time . "',
                        build_status = '0'
                    WHERE id = '" . $row->id . "'";
            mysqli_query($connection, $sql);

            $api_call = $accounts->getApiCall();
            $api_results = $build->apiCall($api_call, $row->host, $row->protocol, $row->port, $row->username,
                $row->hash);
            $accounts->insertAccounts($connection, $api_results, $row->id);

            $api_call = $zones->getApiCall();
            $api_results = $build->apiCall($api_call, $row->host, $row->protocol, $row->port, $row->username,
                $row->hash);
            $zones->insertZones($connection, $api_results, $row->id);

            $result_zones = $zones->getInsertedZones($connection, $row->id);
            $zones->processEachZone($connection, $result_zones, $row->id, $row->protocol, $row->host, $row->port,
                $row->username, $row->hash);
            $this->serverFinish($connection, $row->id, $build_start_time);

        }

        return true;

    }

    public function serverFinish($connection, $server_id, $build_start_time)
    {

        $build = new DwBuild();

        list($build_end_time, $total_build_time) = $build->getBuildTime($build_start_time);

        $sql = "UPDATE dw_servers
                SET build_status = '1',
                    build_end_time = '" . $build_end_time . "',
                    build_time = '" . $total_build_time . "',
                    has_ever_been_built = '1'
                WHERE id = '" . $server_id . "'";
        mysqli_query($connection, $sql);

        return true;

    }

} //@formatter:on
