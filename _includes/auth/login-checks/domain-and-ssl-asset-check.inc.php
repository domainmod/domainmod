<?php
/**
 * /_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php
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
$query = "SELECT id
          FROM registrars
          LIMIT 1";
$q = $conn->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();

    if ($q->num_rows() === 0) {

        $_SESSION['need_registrar'] = "1";

    } else {

        $_SESSION['need_registrar'] = "0";

    }

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT id
          FROM registrar_accounts
          LIMIT 1";
$q = $conn->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();

    if ($q->num_rows() === 0) {

        $_SESSION['need_registrar_account'] = "1";

    } else {

        $_SESSION['need_registrar_account'] = "0";

    }

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT id
          FROM domains
          LIMIT 1";
$q = $conn->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();

    if ($q->num_rows() === 0) {

        $_SESSION['need_domain'] = "1";

    } else {

        $_SESSION['need_domain'] = "0";

    }

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT id
          FROM ssl_providers
          LIMIT 1";
$q = $conn->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();

    if ($q->num_rows() === 0) {

        $_SESSION['need_ssl_provider'] = "1";

    } else {

        $_SESSION['need_ssl_provider'] = "0";

    }

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT id
          FROM ssl_accounts
          LIMIT 1";
$q = $conn->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();

    if ($q->num_rows() === 0) {

        $_SESSION['need_ssl_account'] = "1";

    } else {

        $_SESSION['need_ssl_account'] = "0";

    }

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT id
          FROM ssl_certs
          LIMIT 1";
$q = $conn->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();

    if ($q->num_rows() === 0) {

        $_SESSION['need_ssl_cert'] = "1";

    } else {

        $_SESSION['need_ssl_cert'] = "0";

    }

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }
