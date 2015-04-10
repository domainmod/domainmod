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
$direct = $_GET['direct'];

if ($direct == "1") { 

	include("../start-session.inc.php");
	include("../config.inc.php");
	include("../database.inc.php");
	include("../software.inc.php");
	include("../auth/auth-check.inc.php");

}

include($_SESSION['full_server_path'] . "/_includes/timestamps/current-timestamp.inc.php");

$sql_ssl_fee_fix1 = "UPDATE ssl_certs 
					 SET fee_fixed = '0', 
						 fee_id = '0'
                     WHERE active NOT IN ('0', '10')";
$result_ssl_fee_fix1 = mysqli_query($connection, $sql_ssl_fee_fix1) or trigger_error(htmlentities(mysqli_error($connection)), E_USER_ERROR);

$sql_ssl_fee_fix2 = "UPDATE ssl_fees 
					 SET fee_fixed = '0',
					 	 update_time = '" . mysqli_real_escape_string($connection, $current_timestamp) . "'";
$result_ssl_fee_fix2 = mysqli_query($connection, $sql_ssl_fee_fix2) or trigger_error(htmlentities(mysqli_error($connection)), E_USER_ERROR);

$sql_ssl_fee_fix3 = "SELECT id, ssl_provider_id, type_id
					 FROM ssl_fees
					 WHERE fee_fixed = '0'";
$result_ssl_fee_fix3 = mysqli_query($connection, $sql_ssl_fee_fix3) or trigger_error(htmlentities(mysqli_error($connection)), E_USER_ERROR);

while ($row_ssl_fee_fix3 = mysqli_fetch_object($result_ssl_fee_fix3)) {

	$sql_ssl_fee_fix4 = "UPDATE ssl_certs
						 SET fee_id = '$row_ssl_fee_fix3->id'
						 WHERE ssl_provider_id = '$row_ssl_fee_fix3->ssl_provider_id'
						   AND type_id = '$row_ssl_fee_fix3->type_id'
						   AND fee_fixed = '0'
						   AND active NOT IN ('0', '10')";
	$result_ssl_fee_fix4 = mysqli_query($connection, $sql_ssl_fee_fix4) or trigger_error(htmlentities(mysqli_error($connection)), E_USER_ERROR);

    $sql_domain_fee_fix5 = "UPDATE ssl_certs sslc
                            JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
							SET sslc.fee_fixed = '1',
							    sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
							WHERE sslc.ssl_provider_id = '" . $row_ssl_fee_fix3->ssl_provider_id . "'
							  AND sslc.type_id = '" . $row_ssl_fee_fix3->type_id . "'
							  AND sslc.active NOT IN ('0', '10')";
    $result_domain_fee_fix5 = mysqli_query($connection, $sql_domain_fee_fix5) or trigger_error(htmlentities(mysqli_error($connection)), E_USER_ERROR);

	$sql_ssl_fee_fix6 = "UPDATE ssl_fees
						 SET fee_fixed = '1',
						 	 update_time = '" . mysqli_real_escape_string($connection, $current_timestamp) . "'
						 WHERE ssl_provider_id = '$row_ssl_fee_fix3->ssl_provider_id'
						   AND type_id = '$row_ssl_fee_fix3->type_id'";
	$result_ssl_fee_fix6 = mysqli_query($connection, $sql_ssl_fee_fix6) or trigger_error(htmlentities(mysqli_error($connection)), E_USER_ERROR);
	
}

$sql_find_missing_ssl_fees = "SELECT count(id) as total_count
							  FROM ssl_certs
							  WHERE fee_id = '0'
							    AND active NOT IN ('0', '10')";
$result_find_missing_ssl_fees = mysqli_query($connection, $sql_find_missing_ssl_fees) or trigger_error(htmlentities(mysqli_error($connection)), E_USER_ERROR);

while ($row_find_missing_ssl_fees = mysqli_fetch_object($result_find_missing_ssl_fees)) { $total_results_find_missing_ssl_fees = $row_find_missing_ssl_fees->total_count; }

if ($total_results_find_missing_ssl_fees != 0) { 
    $_SESSION['missing_ssl_fees'] = 1; 
} else {
    $_SESSION['missing_ssl_fees'] = 0; 
}

if ($direct == "1") {

	$_SESSION['result_message'] .= "SSL Fees Updated<BR>";
	
	header("Location: " . $_SERVER['HTTP_REFERER']);
	exit;

} else {
	
	$_SESSION['result_message'] .= "SSL Fees Updated<BR>";

}
