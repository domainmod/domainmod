<?php
/**
 * /classes/DomainMOD/Segment.php
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

class Segment
{
    public $deeb;
    public $log;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.segment');
    }

    public function trimLength($input_segment, $max_length)
    {
        if (strlen($input_segment) > $max_length) {

            $output_segment = substr($input_segment, 0, $max_length);
            $pos = strrpos($output_segment, ", ");

            if ($pos === false) {

                return substr($output_segment, 0, $max_length) . "...";

            }

            return substr($output_segment, 0, $pos) . "...";

        } else {

            return $input_segment;

        }
    }

    // The query behind the segment's Matching But Inactive (inactive) or Matching But Filtered (filtered) list, for the
    // signed-in user's currency conversions (bind user_id and segid). The summary counts every domain with the flag, so
    // the list joins everything else with left joins: a domain without a fee, category, DNS profile, IP address, host,
    // or account is still listed.
    public static function resultsSql($flag)
    {
        $flag = $flag === 'filtered' ? 'filtered' : 'inactive';

        return "
            SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.autorenew, d.privacy, d.active, d.insert_time, d.update_time, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
            FROM domains AS d
            LEFT JOIN registrar_accounts AS ra ON d.account_id = ra.id
            LEFT JOIN registrars AS r ON ra.registrar_id = r.id
            LEFT JOIN owners AS o ON ra.owner_id = o.id
            LEFT JOIN fees AS f ON d.registrar_id = f.registrar_id AND d.tld = f.tld
            LEFT JOIN currency_conversions AS cc ON f.currency_id = cc.currency_id AND cc.user_id = :user_id
            LEFT JOIN categories AS cat ON d.cat_id = cat.id
            LEFT JOIN dns ON d.dns_id = dns.id
            LEFT JOIN ip_addresses AS ip ON d.ip_id = ip.id
            LEFT JOIN hosting AS h ON d.hosting_id = h.id
            WHERE d.domain IN (SELECT domain FROM segment_data WHERE segment_id = :segid AND " . $flag . " = '1')
            ORDER BY d.domain ASC";
    }

    public function getSegment($seg_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `segment`
            FROM segments
            WHERE id = :seg_id");
        $stmt->bindValue('seg_id', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Segment';
            $log_extra = array('Segment ID' => $seg_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getName($seg_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM segments
            WHERE id = :seg_id");
        $stmt->bindValue('seg_id', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Segment name';
            $log_extra = array('Segment ID' => $seg_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getNumberOfDomains($seg_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT number_of_domains
            FROM segments
            WHERE id = :seg_id");
        $stmt->bindValue('seg_id', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Number of Domains for Segment';
            $log_extra = array('Segment ID' => $seg_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

} //@formatter:on
