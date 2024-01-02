<?php
/**
 * /classes/DomainMOD/DwClean.php
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

class DwClean
{
    public $deeb;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
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
        $pdo = $this->deeb->cnxx;
        $pdo->query("
            DELETE FROM dw_dns_records
            WHERE type = ':RAW'
            AND raw = ''");

        $pdo->query("
            UPDATE dw_dns_records
            SET type = 'COMMENT'
            WHERE type = ':RAW'");

        $pdo->query("
            UPDATE dw_dns_records
            SET type = 'ZONE TTL'
            WHERE type = '\$TTL'");

        $pdo->query("
            UPDATE dw_dns_records
            SET nlines = '1'
            WHERE nlines = '0'");

        $pdo->query("
            UPDATE dw_dns_records AS r, dw_dns_zones AS z
            SET r.zonefile = z.zonefile
            WHERE r.dns_zone_id = z.id");
    }

    public function wrapLine($field, $wrap_at)
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT id, " . $field . "
            FROM dw_dns_records
            WHERE " . $field . " != ''")->fetchAll();

        if ($result) {

            $stmt = $pdo->prepare("
                UPDATE dw_dns_records
                SET formatted_output = :wrapped
                WHERE id = :id");
            $stmt->bindParam('wrapped', $bind_wrapped, \PDO::PARAM_LOB);
            $stmt->bindParam('id', $bind_id, \PDO::PARAM_INT);

            foreach ($result as $row) {

                $bind_wrapped = wordwrap($row->{$field}, $wrap_at, "<BR>", true);
                $bind_id = $row->id;
                $stmt->execute();

            }

        }
    }

    public function lines()
    {
        $pdo = $this->deeb->cnxx;
        $pdo->query("
            UPDATE dw_dns_records
            SET `formatted_line` = concat(' | ', `line`, ' | ')
            WHERE line >= 10");

        $pdo->query("
            UPDATE dw_dns_records
            SET `formatted_line` = concat(' | 0', `line`, ' | ')
            WHERE line < 10");
    }

    public function types()
    {
        $this->deeb->cnxx->query("
            UPDATE dw_dns_records
            SET `formatted_type` = concat('<strong" . ">" . "', `type`, '</strong" . ">" . "')");
    }

    public function content()
    {
        $pdo = $this->deeb->cnxx;
        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`name`, ' | ', `address`, ' | ', `ttl`)
            WHERE type = 'A'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`formatted_output`, ' | ', ttl)
            WHERE type = 'COMMENT'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`name`, ' | ', `cname`, ' |', `ttl`)
            WHERE type = 'CNAME'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`preference`, ' | ', `exchange`, ' | ', `ttl`)
            WHERE type = 'MX'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`nsdname`, ' | ', `ttl`)
            WHERE type = 'NS'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`name`, ' | ', `mname`, ' | ', `rname`, ' | ', `ttl`, '<BR" . ">" . "',
                '<strong" . ">" . _("Serial") . ":</strong" . ">" . " ', `serial`, ' |
                <strong" . ">" . _("Refresh") . ":</strong" . ">" . " ', `refresh`, ' |
                <strong" . ">" . _("Retry") . ":</strong" . ">" . " ', `retry`, ' |
                <strong" . ">" . _("Expire") . ":</strong" . ">" . " ', `expire`, ' |
                <strong" . ">" . _("Minimum TTL") . ":</strong" . ">" . " ', `minimum`)
            WHERE type = 'SOA'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`name`, ' | ', `ttl`)
            WHERE type = 'SRV'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`name`, ' | ', `formatted_output`, ' | ', `ttl`)
            WHERE type = 'TXT'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = ttl
            WHERE type = 'ZONE TTL'");
    }

    public function reorderRecords()
    {
        $pdo = $this->deeb->cnxx;

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

        $stmt = $pdo->prepare("
            UPDATE dw_dns_records
            SET new_order = :new_order
            WHERE type = :type");
        $stmt->bindValue('new_order', $new_order, \PDO::PARAM_INT);
        $stmt->bindParam('type', $bind_type, \PDO::PARAM_STR);

        foreach ($type_order as $bind_type) {

            $stmt->execute();

            $new_order++;

        }
    }

} //@formatter:on
