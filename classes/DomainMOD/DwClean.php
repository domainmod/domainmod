<?php
/**
 * /classes/DomainMOD/DwClean.php
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

class DwClean
{
//@formatter:off
    public function all($connection)
    {
        $this->prep($connection);
        $wrap_at = 75;
        $this->wrapLine($connection, 'raw', $wrap_at);
        $this->wrapLine($connection, 'txtdata', $wrap_at);
        $this->lines($connection);
        $this->types($connection);
        $this->content($connection);
        $this->reorderRecords($connection);

        return true;
    }

    public function prep($connection)
    {
        $sql = "DELETE FROM dw_dns_records WHERE type = ':RAW' AND raw = ''";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records SET type = 'COMMENT' WHERE type = ':RAW'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records SET type = 'ZONE TTL' WHERE type = '\$TTL'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records SET nlines = '1' WHERE nlines = '0'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records AS r, dw_dns_zones AS z
                SET r.zonefile = z.zonefile
                WHERE r.dns_zone_id = z.id";
        mysqli_query($connection, $sql);

        return true;
    }

    public function wrapLine($connection, $field, $wrap_at)
    {
        $sql_wrap = "SELECT id, " . $field . "
                     FROM dw_dns_records
                     WHERE " . $field . " != ''";
        $result_wrap = mysqli_query($connection, $sql_wrap);

        while ($row = mysqli_fetch_object($result_wrap)) {
            $wrapped = wordwrap($row->{$field}, $wrap_at, "<BR>", true);

            $sql_update = "UPDATE dw_dns_records
                           SET formatted_output = '" . $wrapped . "'
                           WHERE id = '" . $row->id . "'";
            mysqli_query($connection, $sql_update);
        }

        return true;
    }

    public function lines($connection)
    {
        $sql = "UPDATE dw_dns_records SET `formatted_line` = concat(' | ', `line`, ' | ') WHERE line >= 10";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records SET `formatted_line` = concat(' | 0', `line`, ' | ') WHERE line < 10";
        mysqli_query($connection, $sql);

        return true;
    }

    public function types($connection)
    {
        $sql = "UPDATE dw_dns_records SET `formatted_type` = concat('<strong" . ">" . "', `type`, '</strong" . ">" . "')";
        mysqli_query($connection, $sql);

        return true;
    }

    public function content($connection)
    {
        $sql = "UPDATE dw_dns_records
                SET formatted_output = concat(`name`, ' | ', `address`, ' | ', `ttl`)
                WHERE type = 'A'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET formatted_output = concat(`formatted_output`, ' | ', ttl)
                WHERE type = 'COMMENT'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET formatted_output = concat(`name`, ' | ', `cname`, ' |', `ttl`)
                WHERE type = 'CNAME'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET formatted_output = concat(`preference`, ' | ', `exchange`, ' | ', `ttl`)
                WHERE type = 'MX'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET formatted_output = concat(`nsdname`, ' | ', `ttl`)
                WHERE type = 'NS'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET formatted_output = concat(`name`, ' | ', `mname`, ' | ', `rname`, ' | ', `ttl`, '<BR" . ">" . "',
                    '<strong" . ">" . "Serial:</strong" . ">" . " ', `serial`, ' |
                    <strong" . ">" . "Refresh:</strong" . ">" . " ', `refresh`, ' |
                    <strong" . ">" . "Retry:</strong" . ">" . " ', `retry`, ' |
                    <strong" . ">" . "Expire:</strong" . ">" . " ', `expire`, ' |
                    <strong" . ">" . "Minimum TTL:</strong" . ">" . " ', `minimum`)
                WHERE type = 'SOA'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET formatted_output = concat(`name`, ' | ', `ttl`)
                WHERE type = 'SRV'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET formatted_output = concat(`name`, ' | ', `formatted_output`, ' | ', `ttl`)
                WHERE type = 'TXT'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE dw_dns_records
                SET formatted_output = ttl
                WHERE type = 'ZONE TTL'";
        mysqli_query($connection, $sql);

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
                    SET new_order = '" . $new_order . "'
                    WHERE type = '" . $key . "'";
            mysqli_query($connection, $sql);
            $new_order++;

        }

        return true;
    }

} //@formatter:on
