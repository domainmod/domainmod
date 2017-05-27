<?php
/**
 * /classes/DomainMOD/DwStats.php
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

class DwStats
{
    public function __construct()
    {
        $this->system = new System();
        $this->time = new Time();
    }

    public function updateServerStats($result)
    {
        foreach ($result as $row) {

            $total_dw_accounts = $this->getTotals($row->id, 'dw_accounts');
            $total_dw_dns_zones = $this->getTotals($row->id, 'dw_dns_zones');
            $total_dw_dns_records = $this->getTotals($row->id, 'dw_dns_records');
            $this->updateServerTotals($row->id, $total_dw_accounts, $total_dw_dns_zones, $total_dw_dns_records);

        }
    }

    public function getTotals($server_id, $table)
    {
        $tmpq = $this->system->db()->prepare("
            SELECT count(*)
            FROM `" . $table . "`
            WHERE server_id = :server_id");
        $tmpq->execute(['server_id' => $server_id]);
        return $tmpq->fetchColumn();
    }

    public function updateServerTotals($server_id, $total_accounts, $total_dns_zones, $total_dns_records)
    {
        $tmpq = $this->system->db()->prepare("
            UPDATE dw_servers
            SET dw_accounts = :total_accounts,
                dw_dns_zones = :total_dns_zones,
                dw_dns_records = :total_dns_records
            WHERE id = :server_id");
        $tmpq->execute(['total_accounts' => $total_accounts,
                        'total_dns_zones' => $total_dns_zones,
                        'total_dns_records' => $total_dns_records,
                        'server_id' => $server_id]);
    }

    public function updateDwTotalsTable()
    {
        $accounts = new DwAccounts();
        $zones = new DwZones();
        $records = new DwRecords();

        $this->deleteTotalsTable();
        $this->recreateDwTotalsTable();
        $total_dw_servers = $this->getTotalDwServers();
        $total_dw_accounts = $accounts->getTotalDwAccounts();
        $total_dw_zones = $zones->getTotalDwZones();
        $total_dw_records = $records->getTotalDwRecords();
        $this->updateTable($total_dw_servers, $total_dw_accounts, $total_dw_zones, $total_dw_records);
    }

    public function deleteTotalsTable()
    {
        $this->system->db()->query("DROP TABLE IF EXISTS dw_server_totals");
    }

    public function recreateDwTotalsTable()
    {
        $this->system->db()->query("
            CREATE TABLE IF NOT EXISTS `dw_server_totals` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `dw_servers` INT(10) NOT NULL,
                `dw_accounts` INT(10) NOT NULL,
                `dw_dns_zones` INT(10) NOT NULL,
                `dw_dns_records` INT(10) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;");
    }

    public function getTotalDwServers()
    {
        $tmpq = $this->system->db()->query("
            SELECT count(*)
            FROM `dw_servers`");
        return $tmpq->fetchColumn();
    }

    public function updateTable($total_dw_servers, $total_dw_accounts, $total_dw_dns_zones, $total_dw_records)
    {
        $tmpq = $this->system->db()->prepare("
            INSERT INTO dw_server_totals
            (dw_servers, dw_accounts, dw_dns_zones, dw_dns_records, insert_time)
            VALUES
            (:total_dw_servers, :total_dw_accounts, :total_dw_dns_zones,
             :total_dw_records, :insert_time)");
        $tmpq->execute(['total_dw_servers' => $total_dw_servers,
                        'total_dw_accounts' => $total_dw_accounts,
                        'total_dw_dns_zones' => $total_dw_dns_zones,
                        'total_dw_records' => $total_dw_records,
                        'insert_time' => $this->time->stamp()]);
    }

    public function getServerTotals()
    {
        $tmpq = $this->system->db()->query("
            SELECT dw_accounts, dw_dns_zones, dw_dns_records
            FROM dw_server_totals");
        $result = $tmpq->fetch();

        return array($result->dw_accounts, $result->dw_dns_zones, $result->dw_dns_records);
    }

} //@formatter:on
