<?php
/**
 * /classes/DomainMOD/DwDisplay.php
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

class DwDisplay
{
    public $deeb;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
    }

    public function account($server_id, $domain)
    {
        $result_account = $this->getAccount($server_id, $domain);
        $result = '';

        foreach ($result_account as $row) {

            ob_start();  ?>

            <td align="left" valign="top">
                <strong><?php echo _('Created'); ?>:</strong> <?php echo date("Y M jS", $row->unix_startdate); ?><BR>
                <strong><?php echo _('Contact'); ?>:</strong> <?php echo $this->escape($row->email); ?><BR>
                <strong><?php echo _('IP Address'); ?>:</strong> <?php echo $this->escape($row->ip); ?><BR>
                <strong><?php echo _('Hosting Plan'); ?>:</strong> <?php echo $this->escape($row->plan); ?><BR>
                <strong><?php echo _('cPanel Theme'); ?>:</strong> <?php echo $this->escape($row->theme); ?><BR>
                <strong><?php echo _('User') . ', ' . _('Owner'); ?>:</strong> <?php echo $this->escape($row->user); ?>, <?php echo $this->escape($row->owner); ?>
            </td>
            <td align="left" valign="top">
                <strong><?php echo _('Shell'); ?>:</strong> <?php echo $this->escape($row->shell); ?><BR>
                <strong><?php echo _('Home'); ?>:</strong> /<?php echo $this->escape($row->partition); ?> /<?php echo $this->escape($row->user); ?><BR>
                <strong><?php echo _('HD Quota'); ?>:</strong> <?php echo $this->escape($row->disklimit); ?>
                <?php if ($row->disklimit != "unlimited") echo " MB"; ?><BR>
                <strong>HD Used:</strong> <?php echo number_format((float) $row->diskused); ?> MB
            </td>
            <td align="left" valign="top">
                <strong><?php echo _('POP'); ?>:</strong> <?php echo $this->escape($row->maxpop); ?><BR>
                <strong><?php echo _('Lists'); ?>:</strong> <?php echo $this->escape($row->maxlst); ?><BR>
                <strong><?php echo _('Addons'); ?>:</strong> <?php echo $this->escape($row->maxaddons); ?><BR>
                <strong><?php echo _('Subdomains'); ?>:</strong> <?php echo $this->escape($row->maxsub); ?><BR>
                <strong><?php echo _('SQL'); ?>:</strong> <?php echo $this->escape($row->maxsql); ?><BR>
                <strong><?php echo _('FTP'); ?>:</strong> <?php echo $this->escape($row->maxftp); ?><BR>
                <strong><?php echo _('Parked'); ?>:</strong> <?php echo $this->escape($row->maxparked); ?><BR><BR>
            </td>
            <td align="left" valign="top">
                <strong><?php echo _('Suspended') . '?'; ?></strong> <?php echo $this->escape($row->suspended); ?><BR>
                <strong><?php echo _('When') . '?'; ?></strong> <?php echo $this->escape($row->suspendtime); ?><BR>
                <strong><?php echo _('Why') . '?'; ?></strong> <?php echo $this->escape($row->suspendreason); ?>
            </td><?php

            $result = ob_get_clean();
        }

        return $result;
    }

    public function getAccount($server_id, $domain)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT a.unix_startdate, a.email, a.ip, a.plan, a.theme, a.`user`, a.`owner`, a.shell, a.`partition`,
                a.disklimit, a.diskused, a.maxpop, a.maxlst, a.maxaddons, a.maxsub, a.maxsql, a.maxftp, a.maxparked,
                a.suspended, a.suspendtime, a.suspendreason, s.name AS server_name
            FROM dw_accounts AS a, dw_servers AS s
            WHERE a.server_id = s.id
              AND a.server_id = :server_id
              AND a.domain = :domain");
        $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function accountSidebar($server_name, $domain, $show_heading, $show_url)
    { 
        ob_start();
        echo $this->showHeading($domain, $show_heading);
        // server names are stored HTML-escaped (they're typed in DomainMOD), so they're decoded before wrapping
        echo $this->wrapEscaped(htmlspecialchars_decode((string) $server_name, ENT_QUOTES), 20) . "<BR>";
        echo $this->showUrl($domain, 'zone', 'list-zones.php', $show_url);
        $result = ob_get_clean();
        return $result;
    } 

    public function showHeading($heading, $show_heading)
    {
        $result = '';
        
        if ($show_heading == '1') {
            $result = "<strong>" . $this->wrapEscaped($heading, 20) . "</strong><BR><BR>";
        }
        return $result;
    } 

    public function showUrl($domain, $text, $url, $show_url)
    { 
        $result = '';
        
        if ($show_url == '1') {
            $result = "[<a href=\"" . $url . "?domain=" . $this->escape(rawurlencode((string) $domain)) . "\">" . $this->escape($text) . "</a>]";
        }
        return $result;
    } 

    public function zone($server_id, $domain)
    { 
        ob_start();

        $result_zones = $this->getZone($server_id, $domain);

        foreach ($result_zones as $row_zone) {

            $result_records = $this->getRecords($row_zone->zone_id); ?>

            <table><?php

                foreach ($result_records as $row_records) { ?>

                    <tr>
                        <td align="right" valign="top">
                            <?php echo $row_records->formatted_type; ?>
                        </td>
                        <td align="center" valign="top"><?php
                            echo $row_records->formatted_line; ?>
                        </td>
                        <td align="left" valign="top"><?php
                            echo $row_records->formatted_output; ?>
                        </td>
                    </tr><?php

                } ?>

            </table><?php

        }

        $result = ob_get_clean();

        return $result;
    }

    public function zoneSidebar($server_id, $domain, $show_heading, $show_url)
    { 
        ob_start();
        $zone = $this->getZonefile($server_id, $domain);
        echo $this->showHeading($zone, $show_heading);
        $server_name = $this->getServerName($server_id);
        echo $this->escape(htmlspecialchars_decode((string) $server_name, ENT_QUOTES)) . "<BR>";
        echo $this->showURL($domain, 'account', 'list-accounts.php', $show_url);
        $result = ob_get_clean();

        return $result;
    } 

    // Text from the WHM server (or a server's name), safe to put in the page
    public static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Wrapped at $width characters, then escaped, with <BR> line breaks (escaping first could split an entity)
    public static function wrapEscaped($value, $width)
    {
        return implode('<BR>', array_map(array(self::class, 'escape'), self::wrapLines($value, $width)));
    }

    /*
     * Text wrapped at $width characters (not bytes, so a multi-byte character such as ü is never cut in half): at spaces
     * where it can, and inside words longer than $width, like wordwrap() with cut_long_words. Bytes that aren't valid
     * UTF-8 are replaced first
     */
    public static function wrapLines($value, $width)
    {
        $value = (string) $value;
        if (!mb_check_encoding($value, 'UTF-8')) $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        $lines = array();
        foreach (preg_split('/\R/u', $value) as $paragraph) {
            // null until the line has started, so leading and repeated spaces are kept, as wordwrap() keeps them
            $line = null;
            foreach (explode(' ', $paragraph) as $word) {
                while (mb_strlen($word, 'UTF-8') > $width) {
                    if ($line !== null) { $lines[] = $line; $line = null; }
                    $lines[] = mb_substr($word, 0, $width, 'UTF-8');
                    $word = mb_substr($word, $width, null, 'UTF-8');
                }
                $candidate = $line === null ? $word : $line . ' ' . $word;
                if ($line === null || mb_strlen($candidate, 'UTF-8') <= $width) {
                    $line = $candidate;
                } else {
                    $lines[] = $line;
                    $line = $word;
                }
            }
            // explode() always returns at least one piece, so the first one has always started the line here
            $lines[] = $line;
        }
        return $lines;
    }

    public function getZonefile($server_id, $domain)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT zonefile
            FROM dw_dns_zones
            WHERE server_id = :server_id
              AND domain = :domain");
        $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public function getServerName($server_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM dw_servers
            WHERE id = :server_id");
        $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public function getZone($server_id, $domain)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT z.id AS zone_id, z.domain, z.zonefile, s.id AS server_id, s.name AS server_name
            FROM dw_dns_zones AS z, dw_servers AS s
            WHERE z.server_id = s.id
              AND z.server_id = :server_id
              AND z.domain = :domain");
        $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getRecords($zone_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT address, cname, `exchange`, `expire`, line, minimum, mname, `name`, nsdname, preference, raw,
                refresh, retry, rname, `serial`, ttl, txtdata, type, formatted_line, formatted_type,
                formatted_output
            FROM dw_dns_records
            WHERE dns_zone_id = :zone_id
            ORDER BY new_order");
        $stmt->bindValue('zone_id', $zone_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

} //@formatter:on
