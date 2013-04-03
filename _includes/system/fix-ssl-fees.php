<?php
// fix-ssl-fees.php
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
session_start();

include("../config.inc.php");
include("../database.inc.php");
include("../software.inc.php");
include("../auth/auth-check.inc.php");
include("../timestamps/current-timestamp.inc.php");

$sql = "UPDATE ssl_certs 
		SET fee_fixed = '0', 
			update_time = '$current_timestamp',
			fee_id = '0'";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "UPDATE ssl_fees 
		SET fee_fixed = '0',
			update_time = '$current_timestamp'";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "SELECT id, ssl_provider_id, type_id
		FROM ssl_fees
		WHERE fee_fixed = '0'";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {

	$sql2 = "UPDATE ssl_certs
			 SET fee_id = '$row->id',
			 	 fee_fixed = '1',
			 	 update_time = '$current_timestamp'
			 WHERE ssl_provider_id = '$row->ssl_provider_id' 
			   AND type_id = '$row->type_id'
			   AND fee_fixed = '0'";
	$result2 = mysql_query($sql2,$connection);
	
	$sql3 = "UPDATE ssl_fees
			 SET fee_fixed = '1',
	 		 	 update_time = '$current_timestamp'
			 WHERE ssl_provider_id = '$row->ssl_provider_id'
			   AND type_id = '$row->type_id'";
	$result3 = mysql_query($sql3,$connection);
	
}

include("check-for-missing-ssl-fees.inc.php");

$_SESSION['session_result_message'] .= "All SSL fees have been fixed<BR>";

header("Location: ../../system/index.php");
exit;
?>