<?php
/**
 * /classes/DomainMOD/Bulk.php
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

class Bulk
{

    public function renewDomains($conn, $domain_list, $renewal_years, $notes)
    {
        foreach ($domain_list AS $each_domain) {

            $expiry_date = $this->getDomainExpiry($conn, $each_domain);
            $new_expiry = $this->getNewDomainExpiry($expiry_date, $renewal_years);
            $this->writeNewDomainExpiry($conn, $each_domain, $new_expiry, $notes);

        }
    }

    public function getDomainExpiry($conn, $domain)
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

    public function getNewDomainExpiry($expiry_date, $renewal_years)
    {
        $expiry_pieces = explode("-", $expiry_date);
        return $expiry_pieces[0] + $renewal_years . "-" . $expiry_pieces[1] . "-" . $expiry_pieces[2];
    }

    public function writeNewDomainExpiry($conn, $domain, $new_expiry, $notes)
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
