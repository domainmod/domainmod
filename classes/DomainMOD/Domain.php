<?php
/**
 * /classes/DomainMOD/Domain.php
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

class Domain
{

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

        /*
        if (preg_match('/^[A-Z0-9.-]+\.[A-Z0-9-]{2,50}$/i', $input_domain, $output_domain)) {

            return $output_domain;

        } else {

            return false;

        }
        */

        return $input_domain;

    }

    public function renew($conn, $domain, $renewal_years, $notes)
    {
        $expiry_date = $this->getExpiry($conn, $domain);
        $new_expiry = $this->getNewExpiry($expiry_date, $renewal_years);
        $this->writeNewExpiry($conn, $domain, $new_expiry, $notes);
    }

    public function getExpiry($conn, $domain)
    {
        $query = "SELECT expiry_date
                  FROM domains
                  WHERE domain = ?";
        $q = $conn->stmt_init();
        $q->prepare($query);
        $q->bind_param('s', $domain);
        $q->execute();
        $q->store_result();
        $q->bind_result($expiry);
        while ($q->fetch()) { $expiry_date = $expiry; }
        $q->close();
        return $expiry_date;
    }

    public function getNewExpiry($expiry_date, $renewal_years)
    {
        $expiry_pieces = explode("-", $expiry_date);
        return $expiry_pieces[0] + $renewal_years . "-" . $expiry_pieces[1] . "-" . $expiry_pieces[2];
    }

    public function writeNewExpiry($conn, $domain, $new_expiry, $notes)
    {
        $time = new Time();
        $timestamp = $time->stamp();

        if ($notes != '') {

            $query = "UPDATE domains
                      SET expiry_date = ?,
                          notes = CONCAT(?, '\r\n\r\n', notes),
                          update_time = ?
                      WHERE domain = ?";
            $q = $conn->stmt_init();
            $q->prepare($query);
            $q->bind_param('ssss', $new_expiry, $notes, $timestamp, $domain);

        } else {

            $query = "UPDATE domains
                      SET expiry_date = ?,
                          update_time = ?
                      WHERE domain = ?";
            $q = $conn->stmt_init();
            $q->prepare($query);
            $q->bind_param('sss', $new_expiry, $timestamp, $domain);

        }

        $q->execute();
        $q->close();
    }

}
