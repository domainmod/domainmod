<?php
/**
 * /classes/DomainMOD/DwClean.php
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

class DwClean
{
    public function __construct()
    {
        $this->system = new System();
    }

    public function all()
    {
        $this->prep();
        $wrap_at = 75;
        $this->wrapLine('raw', $wrap_at);
        $this->wrapLine('txtdata', $wrap_at);
        $this->lines();
        $this->types();
        $this->content();
        $this->reorderRecords();
    }

    public function prep()
    {
        $this->system->db()->query("
            DELETE FROM dw_dns_records
            WHERE type = ':RAW'
            AND raw = ''");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET type = 'COMMENT'
            WHERE type = ':RAW'");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET type = 'ZONE TTL'
            WHERE type = '\$TTL'");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET nlines = '1'
            WHERE nlines = '0'");

        $this->system->db()->query("
            UPDATE dw_dns_records AS r, dw_dns_zones AS z
            SET r.zonefile = z.zonefile
            WHERE r.dns_zone_id = z.id");
    }

    public function wrapLine($field, $wrap_at)
    {
        $tmpq = $this->system->db()->query("
            SELECT id, " . $field . "
            FROM dw_dns_records
            WHERE " . $field . " != ''");
        $result = $tmpq->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                $wrapped = wordwrap($row->{$field}, $wrap_at, "<BR>", true);

                $tmpq = $this->system->db()->prepare("
                    UPDATE dw_dns_records
                    SET formatted_output = :wrapped
                    WHERE id = :id");
                $tmpq->execute(['wrapped' => $wrapped,
                                'id' => $row->id]);

            }

        }
    }

    public function lines()
    {
        $this->system->db()->query("
            UPDATE dw_dns_records
            SET `formatted_line` = concat(' | ', `line`, ' | ')
            WHERE line >= 10");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET `formatted_line` = concat(' | 0', `line`, ' | ')
            WHERE line < 10");
    }

    public function types()
    {
        $this->system->db()->query("
            UPDATE dw_dns_records
            SET `formatted_type` = concat('<strong" . ">" . "', `type`, '</strong" . ">" . "')");
    }

    public function content()
    {
        $this->system->db()->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`name`, ' | ', `address`, ' | ', `ttl`)
            WHERE type = 'A'");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`formatted_output`, ' | ', ttl)
            WHERE type = 'COMMENT'");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`name`, ' | ', `cname`, ' |', `ttl`)
            WHERE type = 'CNAME'");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`preference`, ' | ', `exchange`, ' | ', `ttl`)
            WHERE type = 'MX'");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`nsdname`, ' | ', `ttl`)
            WHERE type = 'NS'");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`name`, ' | ', `mname`, ' | ', `rname`, ' | ', `ttl`, '<BR" . ">" . "',
                '<strong" . ">" . "Serial:</strong" . ">" . " ', `serial`, ' |
                <strong" . ">" . "Refresh:</strong" . ">" . " ', `refresh`, ' |
                <strong" . ">" . "Retry:</strong" . ">" . " ', `retry`, ' |
                <strong" . ">" . "Expire:</strong" . ">" . " ', `expire`, ' |
                <strong" . ">" . "Minimum TTL:</strong" . ">" . " ', `minimum`)
            WHERE type = 'SOA'");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`name`, ' | ', `ttl`)
            WHERE type = 'SRV'");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`name`, ' | ', `formatted_output`, ' | ', `ttl`)
            WHERE type = 'TXT'");

        $this->system->db()->query("
            UPDATE dw_dns_records
            SET formatted_output = ttl
            WHERE type = 'ZONE TTL'");
    }

    public function reorderRecords()
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

        $tmpq = $this->system->db()->prepare("
            UPDATE dw_dns_records
            SET new_order = :new_order
            WHERE type = :key");

        foreach ($type_order as $key) {

            $tmpq->execute(['new_order' => $new_order,
                            'key' => $key]);
            $new_order++;
        }
    }

} //@formatter:on
