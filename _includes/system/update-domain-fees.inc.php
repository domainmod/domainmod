<?php
// /_includes/system/update-domain-fees.inc.php
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
include($_SESSION['full_server_path'] . "/_includes/config.inc.php");
include($_SESSION['full_server_path'] . "/_includes/database.inc.php");
include($_SESSION['full_server_path'] . "/_includes/software.inc.php");
include($_SESSION['full_server_path'] . "/_includes/auth/auth-check.inc.php");
include($_SESSION['full_server_path'] . "/_includes/timestamps/current-timestamp.inc.php");

$sql_domain_fee_fix1 = "UPDATE domains 
						SET fee_fixed = '0', 
						    update_time = '$current_timestamp',
							fee_id = '0'";
$result_domain_fee_fix1 = mysql_query($sql_domain_fee_fix1,$connection) or die(mysql_error());

$sql_domain_fee_fix2 = "UPDATE fees 
						SET fee_fixed = '0',
							update_time = '$current_timestamp'";
$result_domain_fee_fix2 = mysql_query($sql_domain_fee_fix2,$connection) or die(mysql_error());

$sql_domain_fee_fix3 = "SELECT id, registrar_id, tld
						FROM fees
						WHERE fee_fixed = '0'";
$result_domain_fee_fix3 = mysql_query($sql_domain_fee_fix3,$connection) or die(mysql_error());

while ($row_domain_fee_fix3 = mysql_fetch_object($result_domain_fee_fix3)) {

	$sql_domain_fee_fix4 = "UPDATE domains
							SET fee_id = '$row_domain_fee_fix3->id',
								fee_fixed = '1',
								update_time = '$current_timestamp'
							WHERE registrar_id = '$row_domain_fee_fix3->registrar_id' 
							  AND tld = '$row_domain_fee_fix3->tld'
							  AND fee_fixed = '0'";
	$result_domain_fee_fix4 = mysql_query($sql_domain_fee_fix4,$connection);
	
	$sql_domain_fee_fix5 = "UPDATE fees
							SET fee_fixed = '1',
								update_time = '$current_timestamp'
							WHERE registrar_id = '$row_domain_fee_fix3->registrar_id'
							  AND tld = '$row_domain_fee_fix3->tld'";
	$result_domain_fee_fix5 = mysql_query($sql_domain_fee_fix5,$connection);
	
}

$sql_find_missing_domain_fees = "SELECT count(id) AS total_count
								 FROM domains
								 WHERE fee_id = '0'";
$result_find_missing_domain_fees = mysql_query($sql_find_missing_domain_fees,$connection);

while ($row_find_missing_domain_fees = mysql_fetch_object($result_find_missing_domain_fees)) { $total_results_find_missing_domain_fees = $row_find_missing_domain_fees->total_count; }

if ($total_results_find_missing_domain_fees != 0) { 
    $_SESSION['session_missing_domain_fees'] = 1; 
} else {
    $_SESSION['session_missing_domain_fees'] = 0; 
}
?>