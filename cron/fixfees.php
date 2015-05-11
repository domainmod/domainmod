<?php
/**
 * /cron/fixfees.php
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
include("../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
require_once(DIR_INC . "classes/Autoloader.class.php");
include(DIR_INC . "timestamps/current-timestamp.inc.php");

spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();

include(DIR_INC . "config-demo.inc.php");

if ($demo_install != "1") {

    $sql_domain_fee_fix1 = "UPDATE domains
                            SET fee_fixed = '0',
                                fee_id = '0'";
    $result_domain_fee_fix1 = mysqli_query($connection, $sql_domain_fee_fix1) or $error->outputOldSqlError($connection);

    $sql_domain_fee_fix2 = "UPDATE fees
                            SET fee_fixed = '0',
                                update_time = '" . mysqli_real_escape_string($connection, $current_timestamp) . "'";
    $result_domain_fee_fix2 = mysqli_query($connection, $sql_domain_fee_fix2) or $error->outputOldSqlError($connection);

    $sql_domain_fee_fix3 = "SELECT id, registrar_id, tld
                            FROM fees
                            WHERE fee_fixed = '0'";
    $result_domain_fee_fix3 = mysqli_query($connection, $sql_domain_fee_fix3) or $error->outputOldSqlError($connection);

    while ($row_domain_fee_fix3 = mysqli_fetch_object($result_domain_fee_fix3)) {

        $sql_domain_fee_fix4 = "UPDATE domains
                                SET fee_id = '" . $row_domain_fee_fix3->id . "'
                                WHERE registrar_id = '" . $row_domain_fee_fix3->registrar_id . "'
                                  AND tld = '" . $row_domain_fee_fix3->tld . "'
                                  AND fee_fixed = '0'";
        $result_domain_fee_fix4 = mysqli_query($connection, $sql_domain_fee_fix4) or $error->outputOldSqlError($connection);

        $sql_domain_fee_fix5 = "UPDATE domains d
                                JOIN fees f ON d.fee_id = f.id
                                SET d.fee_fixed = '1',
                                    d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                                WHERE d.registrar_id = '" . $row_domain_fee_fix3->registrar_id. "'
                                  AND d.tld = '" .$row_domain_fee_fix3->tld. "'
                                  AND d.privacy = '1'
                                  AND d.active NOT IN ('0', '10')";
        $result_domain_fee_fix5 = mysqli_query($connection, $sql_domain_fee_fix5) or $error->outputOldSqlError($connection);

        $sql_domain_fee_fix6 = "UPDATE domains d
                                JOIN fees f ON d.fee_id = f.id
                                SET d.fee_fixed = '1',
                                    d.total_cost = f.renewal_fee + f.misc_fee
                                WHERE d.registrar_id = '" . $row_domain_fee_fix3->registrar_id. "'
                                  AND d.tld = '" .$row_domain_fee_fix3->tld. "'
                                  AND d.privacy = '0'
                                  AND d.active NOT IN ('0', '10')";
        $result_domain_fee_fix6 = mysqli_query($connection, $sql_domain_fee_fix6) or $error->outputOldSqlError($connection);

        $sql_domain_fee_fix7 = "UPDATE fees
                                SET fee_fixed = '1',
                                    update_time = '" . mysqli_real_escape_string($connection, $current_timestamp) . "'
                                WHERE registrar_id = '" . $row_domain_fee_fix3->registrar_id . "'
                                  AND tld = '" . $row_domain_fee_fix3->tld . "'";
        $result_domain_fee_fix7 = mysqli_query($connection, $sql_domain_fee_fix7) or $error->outputOldSqlError($connection);

    }

    $sql_ssl_fee_fix1 = "UPDATE ssl_certs
                         SET fee_fixed = '0',
                             fee_id = '0'";
    $result_ssl_fee_fix1 = mysqli_query($connection, $sql_ssl_fee_fix1) or $error->outputOldSqlError($connection);

    $sql_ssl_fee_fix2 = "UPDATE ssl_fees
                         SET fee_fixed = '0',
                             update_time = '" . mysqli_real_escape_string($connection, $current_timestamp) . "'";
    $result_ssl_fee_fix2 = mysqli_query($connection, $sql_ssl_fee_fix2) or $error->outputOldSqlError($connection);

    $sql_ssl_fee_fix3 = "SELECT id, ssl_provider_id, type_id
                         FROM ssl_fees
                         WHERE fee_fixed = '0'";
    $result_ssl_fee_fix3 = mysqli_query($connection, $sql_ssl_fee_fix3) or $error->outputOldSqlError($connection);

    while ($row_ssl_fee_fix3 = mysqli_fetch_object($result_ssl_fee_fix3)) {

        $sql_ssl_fee_fix4 = "UPDATE ssl_certs
                             SET fee_id = '$row_ssl_fee_fix3->id'
                             WHERE ssl_provider_id = '$row_ssl_fee_fix3->ssl_provider_id'
                               AND type_id = '$row_ssl_fee_fix3->type_id'
                               AND fee_fixed = '0'";
        $result_ssl_fee_fix4 = mysqli_query($connection, $sql_ssl_fee_fix4) or $error->outputOldSqlError($connection);

        $sql_domain_fee_fix5 = "UPDATE ssl_certs sslc
                                JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                                SET sslc.fee_fixed = '1',
                                    sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                                WHERE sslc.ssl_provider_id = '" . $row_ssl_fee_fix3->ssl_provider_id . "'
                                  AND sslc.type_id = '" . $row_ssl_fee_fix3->type_id . "'
                                  AND sslc.active NOT IN ('0', '10')";
        $result_domain_fee_fix5 = mysqli_query($connection, $sql_domain_fee_fix5) or $error->outputOldSqlError($connection);

        $sql_ssl_fee_fix6 = "UPDATE ssl_fees
                             SET fee_fixed = '1',
                                 update_time = '" . mysqli_real_escape_string($connection, $current_timestamp) . "'
                             WHERE ssl_provider_id = '$row_ssl_fee_fix3->ssl_provider_id'
                               AND type_id = '$row_ssl_fee_fix3->type_id'";
        $result_ssl_fee_fix6 = mysqli_query($connection, $sql_ssl_fee_fix6) or $error->outputOldSqlError($connection);

    }

}
