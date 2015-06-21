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

            !mysqli_num_rows(mysqli_query($connection, "SHOW TABLES LIKE '" . `dw_servers` . "'"))
        ) {

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
                ORDER BY domain ASC";
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

        $sql = "UPDATE segment_data
                 SET missing = '1'
                 WHERE domain NOT IN (SELECT domain FROM domains)";
        mysqli_query($connection, $sql);

        $message = "Segments Updated<BR>";

        return $message;

    }

    public function updateDomainFees($connection, $timestamp)
    {

        $sql = "UPDATE domains
                    SET fee_fixed = '0',
                        fee_id = '0'
                    WHERE active NOT IN ('0', '10')";
        mysqli_query($connection, $sql);

        $sql = "UPDATE fees
                    SET fee_fixed = '0',
                        update_time = '" . $timestamp . "'";
        mysqli_query($connection, $sql);

        $sql = "SELECT id, registrar_id, tld
                    FROM fees
                    WHERE fee_fixed = '0'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql2 = "UPDATE domains
                         SET fee_id = '" . $row->id . "'
                         WHERE registrar_id = '" . $row->registrar_id . "'
                           AND tld = '" . $row->tld . "'
                           AND fee_fixed = '0'
                           AND active NOT IN ('0', '10')";
            mysqli_query($connection, $sql2);

            $sql2 = "UPDATE domains d
                         JOIN fees f ON d.fee_id = f.id
                         SET d.fee_fixed = '1',
                             d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                         WHERE d.registrar_id = '" . $row->registrar_id . "'
                           AND d.tld = '" . $row->tld . "'
                           AND d.privacy = '1'
                           AND d.active NOT IN ('0', '10')";
            mysqli_query($connection, $sql2);

            $sql2 = "UPDATE domains d
                         JOIN fees f ON d.fee_id = f.id
                         SET d.fee_fixed = '1',
                             d.total_cost = f.renewal_fee + f.misc_fee
                         WHERE d.registrar_id = '" . $row->registrar_id . "'
                           AND d.tld = '" . $row->tld . "'
                           AND d.privacy = '0'
                           AND d.active NOT IN ('0', '10')";
            mysqli_query($connection, $sql2);

            $sql2 = "UPDATE fees
                         SET fee_fixed = '1',
                             update_time = '" . $timestamp . "'
                         WHERE registrar_id = '" . $row->registrar_id . "'
                           AND tld = '" . $row->tld . "'";
            mysqli_query($connection, $sql2);

        }

        return 1;

    }

    public function updateSslFees($connection, $timestamp)
    {

        $sql = "UPDATE ssl_certs
                    SET fee_fixed = '0',
                        fee_id = '0'";
        mysqli_query($connection, $sql);

        $sql = "UPDATE ssl_fees
                    SET fee_fixed = '0',
                        update_time = '" . $timestamp . "'";
        mysqli_query($connection, $sql);

        $sql = "SELECT id, ssl_provider_id, type_id
                    FROM ssl_fees
                    WHERE fee_fixed = '0'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql2 = "UPDATE ssl_certs
                         SET fee_id = '$row->id'
                         WHERE ssl_provider_id = '$row->ssl_provider_id'
                           AND type_id = '$row->type_id'
                           AND fee_fixed = '0'";
            mysqli_query($connection, $sql2);

            $sql2 = "UPDATE ssl_certs sslc
                         JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                         SET sslc.fee_fixed = '1',
                             sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                         WHERE sslc.ssl_provider_id = '" . $row->ssl_provider_id . "'
                           AND sslc.type_id = '" . $row->type_id . "'
                           AND sslc.active NOT IN ('0', '10')";
            mysqli_query($connection, $sql2);

            $sql2 = "UPDATE ssl_fees
                         SET fee_fixed = '1',
                             update_time = '" . mysqli_real_escape_string($connection, $timestamp) . "'
                         WHERE ssl_provider_id = '$row->ssl_provider_id'
                           AND type_id = '$row->type_id'";
            mysqli_query($connection, $sql2);

        }

        return 1;

    }

    public function checkMissingDomainFees($connection)
    {

        $sql = "SELECT id
                FROM domains
                WHERE fee_id = '0'
                  AND active NOT IN ('0', '10')";
        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) >= 1) {

            return 1;

        } else {

            return 0;

        }

    }

    public function checkMissingSslFees($connection)
    {

        $sql = "SELECT id
                FROM ssl_certs
                WHERE fee_id = '0'
                  AND active NOT IN ('0', '10')";
        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) >= 1) {

            return 1;

        } else {

            return 0;

        }

    }

}
