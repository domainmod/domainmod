<?php
// /_includes/system/update-ssl-fees.inc.php
// 
// Domain Manager - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// Domain Manager is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// Domain Manager is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with Domain Manager. If not, please 
// see http://www.gnu.org/licenses/
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
						 fee_id = '0'";
$result_ssl_fee_fix1 = mysql_query($sql_ssl_fee_fix1,$connection) or die(mysql_error());

$sql_ssl_fee_fix2 = "UPDATE ssl_fees 
					 SET fee_fixed = '0',
					 	 update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
$result_ssl_fee_fix2 = mysql_query($sql_ssl_fee_fix2,$connection) or die(mysql_error());

$sql_ssl_fee_fix3 = "SELECT id, ssl_provider_id, type_id
					 FROM ssl_fees
					 WHERE fee_fixed = '0'";
$result_ssl_fee_fix3 = mysql_query($sql_ssl_fee_fix3,$connection) or die(mysql_error());

while ($row_ssl_fee_fix3 = mysql_fetch_object($result_ssl_fee_fix3)) {

	$sql_ssl_fee_fix4 = "UPDATE ssl_certs
						 SET fee_id = '$row_ssl_fee_fix3->id',
						 	 fee_fixed = '1'
						 WHERE ssl_provider_id = '$row_ssl_fee_fix3->ssl_provider_id' 
						   AND type_id = '$row_ssl_fee_fix3->type_id'
						   AND fee_fixed = '0'";
	$result_ssl_fee_fix4 = mysql_query($sql_ssl_fee_fix4,$connection);
	
	$sql_ssl_fee_fix5 = "UPDATE ssl_fees
						 SET fee_fixed = '1',
						 	 update_time = '" . mysql_real_escape_string($current_timestamp) . "'
						 WHERE ssl_provider_id = '$row_ssl_fee_fix3->ssl_provider_id'
						   AND type_id = '$row_ssl_fee_fix3->type_id'";
	$result_ssl_fee_fix5 = mysql_query($sql_ssl_fee_fix5,$connection);
	
}

$sql_find_missing_ssl_fees = "SELECT count(id) as total_count
							  FROM ssl_certs
							  WHERE fee_id = '0'";
$result_find_missing_ssl_fees = mysql_query($sql_find_missing_ssl_fees,$connection);

while ($row_find_missing_ssl_fees = mysql_fetch_object($result_find_missing_ssl_fees)) { $total_results_find_missing_ssl_fees = $row_find_missing_ssl_fees->total_count; }

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
?>