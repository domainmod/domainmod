<?php
/**
 * /classes/DomainMOD/Domain.php
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

class Domain
{
    public $log;
    public $system;
    public $time;

    public function __construct()
    {
        $this->log = new Log('class.domain');
        $this->system = new System();
        $this->time = new Time();
    }

    public function findInvalidDomains($lines)
    {
        $invalid_to_display = 5;
        $invalid_domains = 0;
        $invalid_count = 0;
        $result_message = '';

        while (list($key, $domain) = each($lines)) {

            if (!$this->checkFormat($domain)) {

                if ($invalid_count < $invalid_to_display) {

                    $result_message .= "Line " . number_format($key + 1) . " contains an invalid domain<BR>";

                }

                $invalid_domains = 1;
                $invalid_count++;

            }

        }

        return array($invalid_to_display, $invalid_domains, $invalid_count, $result_message);
    }

    public function checkFormat($input_domain)
    {
        if (
            // positive
            preg_match("/^(.+?)\.(.+?)$/", $input_domain) && // has at least one period in the middle

            // negative
            $input_domain[0] != '.' && // is the first character a period
            $input_domain[strlen($input_domain) - 1] != '.' && // is the last character a period
            !preg_match("/\\s/", $input_domain) && // are there any spaces
            !preg_match("/\*/", $input_domain) // are there any asterisks
        ) {

            return true;

        } else {

            return false;

        }
    }

    public function renew($domain, $renewal_years, $notes)
    {
        $expiry_date = $this->getExpiry($domain);
        $new_expiry = $this->getNewExpiry($expiry_date, $renewal_years);
        $this->writeNewExpiry($domain, $new_expiry, $notes);
    }

    public function getDomain($domain_id)
    {
        $pdo = $this->system->db();

        $stmt = $pdo->prepare("
            SELECT domain
            FROM domains
            WHERE id = :domain_id");
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = "Unable to retrieve domain";
            $log_extra = array('Domain ID' => $domain_id);
            $this->log->error($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getExpiry($domain)
    {
        $pdo = $this->system->db();

        $stmt = $pdo->prepare("
            SELECT expiry_date
            FROM domains
            WHERE domain = :domain");
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = "Unable to retrieve domain's expiry date";
            $log_extra = array('Domain' => $domain);
            $this->log->error($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getNewExpiry($expiry_date, $renewal_years)
    {
        $expiry_pieces = explode("-", $expiry_date);
        return $expiry_pieces[0] + $renewal_years . "-" . $expiry_pieces[1] . "-" . $expiry_pieces[2];
    }

    public function writeNewExpiry($domain, $new_expiry, $notes)
    {
        $pdo = $this->system->db();

        if ($notes != '') {

            $stmt = $pdo->prepare("
                UPDATE domains
                SET expiry_date = :new_expiry,
                    notes = CONCAT(:notes, '\r\n\r\n', notes),
                    update_time = :update_time
                WHERE domain = :domain");
            $stmt->bindValue('new_expiry', $new_expiry, \PDO::PARAM_STR);
            $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->execute();

        } else {

            $stmt = $pdo->prepare("
                UPDATE domains
                SET expiry_date = :new_expiry,
                    update_time = :update_time
                WHERE domain = :domain");
            $stmt->bindValue('new_expiry', $new_expiry, \PDO::PARAM_STR);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->execute();

        }
    }

    public function getTld($domain)
    {
        return preg_replace("/^((.*?)\.)(.*)$/", "\\3", $domain);
    }

    public function getDomainPart($domain)
    {
        $temp = preg_replace("/^((.*?)\.)(.*)$/", "\\1", $domain);
        return rtrim($temp, ".");
    }

} //@formatter:on
