<?php
/**
 * /classes/DomainMOD/System.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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

class System
{

    public function installCheck($connection, $web_root)
    {

        $full_install_path = DIR_ROOT . "install/";

        if (is_dir($full_install_path) &&

            !mysqli_num_rows(mysqli_query($connection, "SHOW TABLES LIKE '" . `dw_servers` . "'"))) {

            $installation_mode = 1;
            $result_message = "<a href=\"" . $web_root . "/install/\">Click here to install</a><BR>";

        } else {

            $installation_mode = 0;
            $result_message = '';

        }

        return array($installation_mode, $result_message);

    }

    public function performMaintenance($connection)
    {

        // Delete all unused domain fees
        $sql = "DELETE FROM fees
                WHERE id NOT IN (SELECT fee_id FROM domains)";
        mysqli_query($connection, $sql);

        // Delete all unused SSL certificate fees
        $sql = "DELETE FROM ssl_fees
                WHERE id NOT IN (SELECT fee_id FROM ssl_certs)";
        mysqli_query($connection, $sql);

        return "Maintenance Completed<BR>";

    }

    public function updateTlds($connection)
    {

        $sql = "SELECT id, domain
                FROM domains
                ORDER BY domain asc";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $row->domain);

            $sql_update = "UPDATE domains
                           SET tld = '" . $tld . "'
                           WHERE id = '" . $row->id . "'";
            mysqli_query($connection, $sql_update);

        }

        return "TLDs Updated<BR>";

    }

    public function updateSegments($connection)
    {

        $sql = "UPDATE segment_data
                SET active = '1'
                WHERE domain IN (SELECT domain FROM domains WHERE active NOT IN ('0', '10'))";
        mysqli_query($connection, $sql);

        $sql = "UPDATE segment_data
                SET inactive = '1'
                WHERE domain IN (SELECT domain FROM domains WHERE active IN ('0', '10'))";
        mysqli_query($connection, $sql);

        $sql  = "UPDATE segment_data
                 SET missing = '1'
                 WHERE domain NOT IN (SELECT domain FROM domains)";
        mysqli_query($connection, $sql);

        $message = "Segments Updated<BR>";

        return $message;

    }

    public function checkMissingFees($type, $connection)
    {

        if ($type == "S") {
            $sql = "SELECT id
                    FROM ssl_certs
                    WHERE fee_id = '0'
                      AND active NOT IN ('0', '10')";

        } else {

            $sql = "SELECT id
                    FROM domains
                    WHERE fee_id = '0'
                      AND active NOT IN ('0', '10')";

        }

        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) >= 1) {

            return 1;

        } else {

            return 0;

        }

    }

}
