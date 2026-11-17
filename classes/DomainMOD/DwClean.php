<?php
/**
 * /classes/DomainMOD/DwClean.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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

                // wrapped first, then HTML-escaped (the text comes from the WHM server), so the wrapping can't split
                // an escape sequence; the line breaks are the only HTML added
                $bind_wrapped = DwDisplay::wrapEscaped($row->{$field}, $wrap_at);
                $bind_id = $row->id;
                $stmt->execute();

            }

        }
    }

    /*
     * An SQL expression for a column HTML-escaped (the same characters as htmlspecialchars() with ENT_QUOTES). DNS
     * record text comes from the WHM server (a cPanel user controls their own zone's records), so types() and
     * content() escape every field they build the record HTML from; the stored fields stay as WHM sent them, for the
     * CSV export
     */
    protected function escapedColumn($column)
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`" . $column . "`, '&', '&amp;'), '<', '&lt;'), '>', '&gt;'), '\"', '&quot;'), '''', '&#039;')";
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
            SET `formatted_type` = concat('<strong" . ">" . "', " . $this->escapedColumn('type') . ", '</strong" . ">" . "')");
    }

    public function content()
    {
        $pdo = $this->deeb->cnxx;
        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(" . $this->escapedColumn('name') . ", ' | ', " . $this->escapedColumn('address') . ", ' | ', `ttl`)
            WHERE type = 'A'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(`formatted_output`, ' | ', ttl)
            WHERE type = 'COMMENT'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(" . $this->escapedColumn('name') . ", ' | ', " . $this->escapedColumn('cname') . ", ' |', `ttl`)
            WHERE type = 'CNAME'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(" . $this->escapedColumn('preference') . ", ' | ', " . $this->escapedColumn('exchange') . ", ' | ', `ttl`)
            WHERE type = 'MX'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(" . $this->escapedColumn('nsdname') . ", ' | ', `ttl`)
            WHERE type = 'NS'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(" . $this->escapedColumn('name') . ", ' | ', " . $this->escapedColumn('mname') . ", ' | ', " . $this->escapedColumn('rname') . ", ' | ', `ttl`, '<BR" . ">" . "',
                '<strong" . ">" . _("Serial") . ":</strong" . ">" . " ', `serial`, ' |
                <strong" . ">" . _("Refresh") . ":</strong" . ">" . " ', " . $this->escapedColumn('refresh') . ", ' |
                <strong" . ">" . _("Retry") . ":</strong" . ">" . " ', `retry`, ' |
                <strong" . ">" . _("Expire") . ":</strong" . ">" . " ', " . $this->escapedColumn('expire') . ", ' |
                <strong" . ">" . _("Minimum TTL") . ":</strong" . ">" . " ', `minimum`)
            WHERE type = 'SOA'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(" . $this->escapedColumn('name') . ", ' | ', `ttl`)
            WHERE type = 'SRV'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = concat(" . $this->escapedColumn('name') . ", ' | ', `formatted_output`, ' | ', `ttl`)
            WHERE type = 'TXT'");

        $pdo->query("
            UPDATE dw_dns_records
            SET formatted_output = ttl
            WHERE type = 'ZONE TTL'");
    }

    /*
     * Gives each record type its place in the zone's display order (new_order): the usual types first, in the order
     * below, then any others the zones have (such as AAAA or CAA), alphabetically, so each type's records stay together.
     */
    public function reorderRecords()
    {
        $pdo = $this->deeb->cnxx;

        $found_types = $pdo->query("
            SELECT DISTINCT type
            FROM dw_dns_records")->fetchAll(\PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("
            UPDATE dw_dns_records
            SET new_order = :new_order
            WHERE type = :type");
        $stmt->bindParam('new_order', $bind_new_order, \PDO::PARAM_INT);
        $stmt->bindParam('type', $bind_type, \PDO::PARAM_STR);

        foreach (self::typeOrder($found_types) as $bind_new_order => $bind_type) {

            $stmt->execute();

        }
    }

    /*
     * The record types in display order, numbered from 1: the usual types in a set order, then the other types in
     * $found_types, alphabetically. The type column ignores case, so a type is only listed once, whatever its case.
     */
    public static function typeOrder($found_types)
    {
        $usual_types = array('COMMENT', 'ZONE TTL', 'SOA', 'NS', 'MX', 'A', 'CNAME', 'TXT', 'SRV');

        $listed = array_map('strtoupper', $usual_types);
        $other_types = array();
        foreach ($found_types as $type) {
            $type = (string) $type;
            if (in_array(strtoupper($type), $listed, true)) continue;
            $listed[] = strtoupper($type);
            $other_types[] = $type;
        }
        sort($other_types, SORT_STRING | SORT_FLAG_CASE);

        $order = array_merge($usual_types, $other_types);
        return array_combine(range(1, count($order)), $order);
    }

} //@formatter:on
