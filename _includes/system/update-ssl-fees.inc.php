<?php
/**
 * /_includes/system/update-ssl-fees.inc.php
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
$query = "UPDATE ssl_certs
          SET fee_fixed = '0',
              fee_id = '0'
          WHERE active NOT IN ('0', '10')";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "UPDATE ssl_fees
          SET fee_fixed = '0',
              update_time = ?";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $timestamp = $time->time();

    $q->bind_param('s', $timestamp);
    $q->execute();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT id, ssl_provider_id, type_id
          FROM ssl_fees
          WHERE fee_fixed = '0'";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($id, $ssl_provider_id, $type_id);

    while ($q->fetch()) {

        $query_u = "UPDATE ssl_certs
                    SET fee_id = ?
                    WHERE ssl_provider_id = ?
                      AND type_id = ?
                      AND fee_fixed = '0'
                      AND active NOT IN ('0', '10')";
        $q_u = $conn->stmt_init();

        if ($q_u->prepare($query_u)) {

            $q_u->bind_param('iii', $id, $ssl_provider_id, $type_id);
            $q_u->execute();
            $q_u->close();

        } else { $error->outputSqlError($conn, "ERROR"); }

        $query_u = "UPDATE ssl_certs sslc
                    JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                    SET sslc.fee_fixed = '1',
                        sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                    WHERE sslc.ssl_provider_id = ?
                      AND sslc.type_id = ?
                      AND sslc.active NOT IN ('0', '10')";
        $q_u = $conn->stmt_init();

        if ($q_u->prepare($query_u)) {

            $q_u->bind_param('ii', $ssl_provider_id, $type_id);
            $q_u->execute();
            $q_u->close();

        } else { $error->outputSqlError($conn, "ERROR"); }

        $query_u = "UPDATE ssl_fees
                    SET fee_fixed = '1',
                        update_time = ?
                    WHERE ssl_provider_id = ?
                      AND type_id = ?";
        $q_u = $conn->stmt_init();

        if ($q_u->prepare($query_u)) {

            $timestamp = $time->time();

            $q_u->bind_param('sii', $timestamp, $ssl_provider_id, $type_id);
            $q_u->execute();
            $q_u->close();

        } else { $error->outputSqlError($conn, "ERROR"); }

    }

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT count(id) as total_count
          FROM ssl_certs
          WHERE fee_id = '0'
            AND active NOT IN ('0', '10')";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($total_results_certs);
    $q->fetch();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

if ($total_results_certs != 0) {

    $_SESSION['missing_ssl_fees'] = 1;

} else {

    $_SESSION['missing_ssl_fees'] = 0;

}

$_SESSION['result_message'] .= "SSL Fees Updated<BR>";
