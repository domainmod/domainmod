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

        $result = $this->getServers($connection);
        if ($this->checkForHosts($result) == '0')
            return false;
        $this->dropDwTables($connection);
        $build_start_time_o = $this->startOverallBuild($connection);
        $this->createDwTables($connection);
        $this->processEachServer($connection, $result);
        $this->cleanupRecords($connection);
        $this->reorderRecords($connection);
        $result = $this->getServers($connection);
        $this->updateServerStats($connection, $result);
        $this->updateDwTotalsTable($connection);
        $this->writeBuildStats($connection, $build_start_time_o);
        list($temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records) = $this->getServerTotals($connection);
        $this->endOverallBuild($connection, $temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records);

        return 'Data Warehouse Rebuilt.<BR>';

    }

    public function time()
    {

        return date("Y-m-d H:i:s", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));

    }

    public function dbQuery($connection, $sql)
    {

        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function getServers($connection)
    {

        $sql = "SELECT id, `host`, protocol, `port`, username, `hash`
                FROM dw_servers
                ORDER BY `name`";
        $result = $this->dbQuery($connection, $sql);

        return $result;

    }

    public function checkForHosts($result)
    {

        if (mysqli_num_rows($result) >= 1) {

            $has_hosts = '1';

        } else {

            $has_hosts = '0';

        }

        return $has_hosts;

    }

    public function dropDwTables($connection)
    {

        $sql_accounts = "DROP TABLE IF EXISTS dw_accounts";
        $this->dbQuery($connection, $sql_accounts);

        $sql_zones = "DROP TABLE IF EXISTS dw_dns_zones";
        $this->dbQuery($connection, $sql_zones);

        $sql_records = "DROP TABLE IF EXISTS dw_dns_records";
        $this->dbQuery($connection, $sql_records);

        return true;

    }

    public function startOverallBuild($connection)
    {

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
        $this->dbQuery($connection, $sql);

        return $build_start_time_o;

    }

    public function createDwTables($connection)
    {

        $sql = "CREATE TABLE IF NOT EXISTS dw_accounts (
                id int(10) NOT NULL auto_increment,
                server_id int(10) NOT NULL,
                domain varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                ip varchar(15) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `owner` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `user` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                email varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                plan varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                theme varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                shell varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `partition` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                disklimit varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                diskused varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxaddons varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxftp varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxlst varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxparked varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxpop varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxsql varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                maxsub varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                startdate varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                unix_startdate int(10) NOT NULL,
                suspended int(1) NOT NULL,
                suspendreason varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                suspendtime int(10) NOT NULL,
                MAX_EMAIL_PER_HOUR int(10) NOT NULL,
                MAX_DEFER_FAIL_PERCENTAGE int(10) NOT NULL,
                MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION int(10) NOT NULL,
                insert_time datetime NOT NULL,
                PRIMARY KEY  (id)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1";
        $this->dbQuery($connection, $sql);

        $sql = "CREATE TABLE IF NOT EXISTS dw_dns_zones (
                id int(10) NOT NULL auto_increment,
                server_id int(10) NOT NULL,
                domain varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                zonefile varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                insert_time datetime NOT NULL,
                PRIMARY KEY  (id)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1";
        $this->dbQuery($connection, $sql);

        $sql = "CREATE TABLE IF NOT EXISTS dw_dns_records (
                id int(10) NOT NULL auto_increment,
                server_id int(10) NOT NULL,
                dns_zone_id int(10) NOT NULL,
                domain varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                zonefile varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL default '',
                new_order int(10) NOT NULL,
                mname varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                rname varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `serial` int(20) NOT NULL,
                refresh int(10) NOT NULL,
                retry int(10) NOT NULL,
                expire varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                minimum int(10) NOT NULL,
                nsdname varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                ttl int(10) NOT NULL,
                class varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                type varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                address varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                cname varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `exchange` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                preference int(10) NOT NULL,
                txtdata varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                line int(10) NOT NULL,
                nlines int(10) NOT NULL,
                raw longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                insert_time datetime NOT NULL,
                PRIMARY KEY  (id)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1";
        $this->dbQuery($connection, $sql);

        return true;

    }
    
    public function processEachServer($connection, $result)
    {

        while ($row = mysqli_fetch_object($result)) {

            $build_start_time = $this->startServerBuild($connection, $row->id);
            $api_results = $this->apiGetAccounts($row->protocol, $row->host, $row->port, $row->username, $row->hash);
            $this->insertAccounts($connection, $api_results, $row->id);
            $api_results = $this->apiGetZones($row->protocol, $row->host, $row->port, $row->username, $row->hash);
            $this->insertZones($connection, $api_results, $row->id);
            $result_zones = $this->getInsertedZones($connection, $row->id);
            $this->processEachZone($connection, $result_zones, $row->id, $row->protocol, $row->host, $row->port,
                $row->username, $row->hash);
            $this->endServerBuild($connection, $row->id, $build_start_time);

        }
        
        return true;
    
    }
    
    public function processEachZone($connection, $result_zones, $server_id, $protocol, $host, $port, $username, $hash)
    {

        while ($row_zones = mysqli_fetch_object($result_zones)) {

            $api_results = $this->apiGetRecords($protocol, $host, $port, $username, $hash, $row_zones->domain);
            $this->insertRecords($connection, $api_results, $server_id, $row_zones->id, $row_zones->domain);

        }
    
    }

    public function startServerBuild($connection, $server_id)
    {

        $build_start_time = $this->time();

        $sql = "UPDATE dw_servers
                SET build_start_time = '" . $build_start_time . "',
                    build_status = '0'
                WHERE id = '" . $server_id . "'";
        $this->dbQuery($connection, $sql);

        return $build_start_time;

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
        if ($api_results == false) {
            error_log("curl_exec error \"" . curl_error($curl) . "\" for " . $query . "");
        }
        curl_close($curl);

        return $api_results;

    }

    public function apiGetAccounts($protocol, $host, $port, $username, $hash)
    {

        $api_type = "/xml-api/listaccts?searchtype=domain&search=";

        $api_results = $this->apiCall($api_type, $protocol, $host, $port, $username, $hash);

        return $api_results;

    }

    public function insertAccounts($connection, $api_results, $server_id)
    {

        if ($api_results != false) {

            $xml = simplexml_load_string($api_results);

            foreach ($xml->acct as $hit) {

                $disklimit_formatted = rtrim($hit->disklimit, 'M');
                $diskused_formatted = rtrim($hit->diskused, 'M');

                $sql = "INSERT INTO dw_accounts
                        (server_id, domain, ip, `owner`, `user`, email, plan, theme, shell, `partition`, disklimit,
                         diskused, maxaddons, maxftp, maxlst, maxparked, maxpop, maxsql, maxsub, startdate,
                         unix_startdate, suspended, suspendreason, suspendtime, MAX_EMAIL_PER_HOUR,
                         MAX_DEFER_FAIL_PERCENTAGE, MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION, insert_time)
                        VALUES
                        ('" . $server_id . "', '" . $hit->domain . "', '" . $hit->ip . "', '" . $hit->owner
                    . "', '" . $hit->user . "', '" . $hit->email . "', '" . $hit->plan . "', '" . $hit->theme
                    . "', '" . $hit->shell . "', '" . $hit->partition . "', '" . $disklimit_formatted
                    . "', '" . $diskused_formatted . "', '" . $hit->maxaddons . "', '" . $hit->maxftp . "', '"
                    . $hit->maxlst . "', '" . $hit->maxparked. "', '" . $hit->maxpop . "', '" . $hit->maxsql
                    . "', '" . $hit->maxsub . "', '" . $hit->startdate . "', '" . $hit->unix_startdate
                    . "', '" . $hit->suspended . "', '" . $hit->suspendreason . "', '" . $hit->suspendtime
                    . "', '" . $hit->MAX_EMAIL_PER_HOUR . "', '" . $hit->MAX_DEFER_FAIL_PERCENTAGE . "', '"
                    . $hit->MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION . "', '" . $this->time() . "')";
                $this->dbQuery($connection, $sql);

            }

        }

        return true;

    }

    public function apiGetZones($protocol, $host, $port, $username, $hash)
    {

        $api_type = "/xml-api/listzones";

        $api_results = $this->apiCall($api_type, $protocol, $host, $port, $username, $hash);

        return $api_results;

    }

    public function insertZones($connection, $api_results, $server_id)
    {

        if ($api_results != false) {

            $xml = simplexml_load_string($api_results);

            foreach ($xml->zone as $hit) {

                $sql = "INSERT INTO dw_dns_zones
                        (server_id, domain, zonefile, insert_time)
                        VALUES
                        ('" . $server_id . "', '" . $hit->domain . "', '" . $hit->zonefile . "', '" . $this->time()
                    . "')";
                $this->dbQuery($connection, $sql);

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
        $result = $this->dbQuery($connection, $sql);

        return $result;

    }
    
    public function apiGetRecords($protocol, $host, $port, $username, $hash, $domain)
    {

        $api_type = "/xml-api/dumpzone?domain=" . $domain . "";

        $api_results = $this->apiCall($api_type, $protocol, $host, $port, $username, $hash);

        return $api_results;

    }

    public function insertRecords($connection, $api_results, $server_id, $zone_id, $domain)
    {

        if ($api_results != false) {

            $xml = simplexml_load_string($api_results);

            foreach ($xml->result->record as $hit) {

                $sql = "INSERT INTO dw_dns_records
                        (server_id, dns_zone_id, domain, mname, rname, `serial`, refresh, retry, expire,
                         minimum, nsdname, `name`, ttl, class, type, address, cname, `exchange`, preference,
                         txtdata, line, nlines, raw, insert_time)
                        VALUES
                        ('" . $server_id . "', '" . $zone_id . "', '" . $domain . "', '" .
                         $hit->mname . "', '" . $hit->rname . "', '" . $hit->serial . "', '" . $hit->refresh . "', '" .
                         $hit->retry . "', '" . $hit->expire . "', '" . $hit->minimum . "', '" . $hit->nsdname . "', '"
                         . $hit->name . "', '" . $hit->ttl . "', '" . $hit->class . "', '" . $hit->type . "', '" .
                         $hit->address . "', '" . $hit->cname . "', '" . $hit->exchange . "', '" . $hit->preference .
                         "', '" . $hit->txtdata . "', '" . $hit->Line . "', '" . $hit->Lines . "', '" . $hit->raw .
                         "', '" . $this->time() . "')";
                $this->dbQuery($connection, $sql);

            }

        }

        return true;

    }

    public function endServerBuild($connection, $server_id, $build_start_time)
    {

        $build_end_time = $this->time();

        $total_build_time = (strtotime($build_end_time) - strtotime($build_start_time));

        $sql = "UPDATE dw_servers
                SET build_status = '1',
                    build_end_time = '" . $build_end_time . "',
                    build_time = '" . $total_build_time . "',
                    has_ever_been_built = '1'
                WHERE id = '" . $server_id . "'";
        $this->dbQuery($connection, $sql);

        return true;

    }
    
    public function cleanupRecords($connection)
    {
        
        $sql = "DELETE FROM dw_dns_records
                WHERE type = ':RAW'
                  AND raw = ''";
        $this->dbQuery($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET type = 'COMMENT'
                WHERE type = ':RAW'";
        $this->dbQuery($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET type = 'ZONE TTL'
                WHERE type = '\$TTL'";
        $this->dbQuery($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET nlines = '1'
                WHERE nlines = '0'";
        $this->dbQuery($connection, $sql);

        $sql = "SELECT domain, zonefile
                FROM dw_dns_zones";
        $result = $this->dbQuery($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_update = "UPDATE dw_dns_records
                           SET zonefile = '" . $row->zonefile . "'
                           WHERE domain = '" . $row->domain . "'";
            $this->dbQuery($connection, $sql);

        }

        return true;

    }

    public function reorderRecords($connection)
    {

        $type_order = array();
        $count = 0;
        $new_order = 1;
        $type_order[$count++] = 'COMMENT';
        $type_order[$count++] = 'ZONE TTL';
        $type_order[$count++] = 'SOA';
        $type_order[$count++] = 'NS';
        $type_order[$count++] = 'MX';
        $type_order[$count++] = 'A';
        $type_order[$count++] = 'CNAME';
        $type_order[$count++] = 'TXT';
        $type_order[$count++] = 'SRV';

        foreach ($type_order as $key) {

            $sql = "UPDATE dw_dns_records
                    SET new_order = '" . $new_order++ . "'
                    WHERE type = '" . $key . "'";
            $this->dbQuery($connection, $sql);

        }

        return true;

    }

    public function updateServerStats($connection, $result)
    {

        while ($row = mysqli_fetch_object($result)) {

            $total_dw_accounts = $this->getTotalAccounts($connection, $row->id);
            $total_dw_dns_zones = $this->getTotalZones($connection, $row->id);
            $total_dw_dns_records = $this->getTotalRecords($connection, $row->id);
            $this->updateServerTotals($connection, $row->id, $total_dw_accounts, $total_dw_dns_zones,
                $total_dw_dns_records);

        }

    }

    public function getTotalAccounts($connection, $server_id)
    {

        $sql = "SELECT count(*) AS total_dw_accounts
                FROM dw_accounts
                WHERE server_id = '" . $server_id . "'";
        $result = $this->dbQuery($connection, $sql);

        $total_dw_accounts = 0;

        while ($row = mysqli_fetch_object($result)) {

            $total_dw_accounts = $row->total_dw_accounts;

        }

        return $total_dw_accounts;

    }

    public function getTotalZones($connection, $server_id)
    {

        $sql = "SELECT count(*) AS total_dw_dns_zones
                FROM dw_dns_zones
                WHERE server_id = '" . $server_id . "'";
        $result = $this->dbQuery($connection, $sql);

        $total_dw_dns_zones = 0;

        while ($row = mysqli_fetch_object($result)) {

            $total_dw_dns_zones = $row->total_dw_dns_zones;

        }

        return $total_dw_dns_zones;

    }

    public function getTotalRecords($connection, $server_id)
    {

        $sql = "SELECT count(*) AS total_dw_dns_records
                FROM dw_dns_records
                WHERE server_id = '" . $server_id . "'";
        $result = $this->dbQuery($connection, $sql);

        $total_dw_dns_records = 0;

        while ($row = mysqli_fetch_object($result)) {

            $total_dw_dns_records = $row->total_dw_dns_records;

        }

        return $total_dw_dns_records;

    }

    public function updateServerTotals($connection, $server_id, $total_dw_accounts, $total_dw_dns_zones,
                                       $total_dw_dns_records)
    {

        $sql = "UPDATE dw_servers
                SET dw_accounts = '" . $total_dw_accounts . "',
                    dw_dns_zones = '" . $total_dw_dns_zones . "',
                    dw_dns_records = '" . $total_dw_dns_records . "'
                WHERE id = '" . $server_id . "'";
        $this->dbQuery($connection, $sql);

        return true;

    }

    public function updateDwTotalsTable($connection)
    {

        $this->deleteTotalsTable($connection);
        $this->recreateDwTotalsTable($connection);
        $total_dw_servers = $this->getTotalDwServers($connection);
        $total_dw_accounts = $this->getTotalDwAccounts($connection);
        $total_dw_zones = $this->getTotalDwZones($connection);
        $total_dw_records = $this->getTotalDwRecords($connection);
        $this->updateTable($connection, $total_dw_servers, $total_dw_accounts, $total_dw_zones, $total_dw_records);

    }

    public function deleteTotalsTable($connection)
    {

        $sql = "DROP TABLE IF EXISTS dw_server_totals";
        $this->dbQuery($connection, $sql);

        return true;

    }

    public function recreateDwTotalsTable($connection)
    {

        $sql = "CREATE TABLE IF NOT EXISTS `dw_server_totals` (
                    `id` int(10) NOT NULL auto_increment,
                    `dw_servers` int(10) NOT NULL,
                    `dw_accounts` int(10) NOT NULL,
                    `dw_dns_zones` int(10) NOT NULL,
                    `dw_dns_records` int(10) NOT NULL,
                    `insert_time` datetime NOT NULL,
                    PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
        $this->dbQuery($connection, $sql);

        return true;

    }

    public function getTotalDwServers($connection)
    {

        $total_dw_servers = 0;

        $sql = "SELECT count(*) AS total_dw_servers
                FROM dw_servers";
        $this->dbQuery($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $total_dw_servers = $row->total_dw_servers;

        }

        return $total_dw_servers;

    }

    public function getTotalDwAccounts($connection)
    {

        $total_dw_accounts = 0;

        $sql = "SELECT count(*) AS total_dw_accounts
                FROM dw_accounts";
        $result = $this->dbQuery($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $total_dw_accounts = $row->total_dw_accounts;

        }

        return $total_dw_accounts;

    }

    public function getTotalDwZones($connection)
    {

        $total_dw_zones = 0;

        $sql = "SELECT count(*) AS total_dw_zones
                FROM dw_dns_zones";
        $result = $this->dbQuery($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $total_dw_zones = $row->total_dw_zones;

        }

        return $total_dw_zones;

    }

    public function getTotalDwRecords($connection)
    {

        $total_dw_records = 0;

        $sql = "SELECT count(*) AS total_dw_records
                FROM dw_dns_records";
        $result = $this->dbQuery($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $total_dw_records = $row->total_dw_records;

        }

        return $total_dw_records;

    }

    public function updateTable($connection, $total_dw_servers, $total_dw_accounts, $total_dw_dns_zones, $total_dw_records)
    {

        $sql = "INSERT INTO dw_server_totals
                (dw_servers, dw_accounts, dw_dns_zones, dw_dns_records, insert_time)
                VALUES
                ('" . $total_dw_servers . "', '" . $total_dw_accounts . "', '" . $total_dw_dns_zones . "', '" . $total_dw_records . "', '" . $this->time() . "')";
        $this->dbQuery($connection, $sql);

        return true;

    }

    public function writeBuildStats($connection, $build_start_time_o)
    {

        $build_end_time_o = $this->time();

        $total_build_time_o = (strtotime($build_end_time_o) - strtotime($build_start_time_o));

        $sql = "UPDATE dw_servers
                SET build_status_overall = '1',
                    build_end_time_overall = '" . $build_end_time_o . "',
                    build_time_overall = '" . $total_build_time_o . "',
                    has_ever_been_built_overall = '1'";
        $this->dbQuery($connection, $sql);

        return true;

    }

    public function getServerTotals($connection)
    {

        $temp_dw_accounts = '0';
        $temp_dw_dns_zones = '0';
        $temp_dw_dns_records = '0';

        $sql = "SELECT dw_accounts, dw_dns_zones, dw_dns_records
                FROM dw_server_totals";
        $result = $this->dbQuery($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $temp_dw_accounts = $row->dw_accounts;
            $temp_dw_dns_zones = $row->dw_dns_zones;
            $temp_dw_dns_records = $row->dw_dns_records;

        }

        return array($temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records);

    }

    public function endOverallBuild($connection, $temp_dw_accounts, $temp_dw_dns_zones, $temp_dw_dns_records)
    {

        if ($temp_dw_accounts == "0" && $temp_dw_dns_zones == "0" && $temp_dw_dns_records == "0") {

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
            $this->dbQuery($connection, $sql);

        }

        return true;

    }

}
