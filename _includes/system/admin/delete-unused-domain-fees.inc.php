<?php
/**
 * /_includes/system/admin/delete-unused-domain-fees.inc.php
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
$direct = $_GET['direct'];

if ($direct == "1") {

    include("../../start-session.inc.php");
    include("../../config.inc.php");
    include("../../database.inc.php");
    include("../../software.inc.php");
    include("../../auth/auth-check.inc.php");

}

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "../../../invalid.php";
include("../../auth/admin-user-check.inc.php");

$sql = "DELETE FROM fees
        WHERE id NOT IN (SELECT fee_id FROM domains)";
$result = mysqli_query($connection, $sql);

if ($direct == "1") {

    $_SESSION['result_message'] .= "Unused Domain Fees Deleted<BR>";

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;

} else {

    $_SESSION['result_message'] .= "Unused Domain Fees Deleted<BR>";

}
