<?php
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
include("../timestamps/current-timestamp-basic.inc.php");

$sql = "update ssl_certs 
		set fee_fixed = '0', 
		update_time = '$current_timestamp',
		fee_id = '0'";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "update ssl_fees 
		set fee_fixed = '0',
		update_time = '$current_timestamp'";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "select id, ssl_provider_id, type_id
		from ssl_fees
		where fee_fixed = '0'";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {

	$sql2 = "update ssl_certs
			 set fee_id = '$row->id',
			 fee_fixed = '1',
			 update_time = '$current_timestamp'
			 where ssl_provider_id = '$row->ssl_provider_id' 
			 and type_id = '$row->type_id'
			 and fee_fixed = '0'";
	$result2 = mysql_query($sql2,$connection);
	
	$sql3 = "update ssl_fees
			 set fee_fixed = '1',
	 		 update_time = '$current_timestamp'
			 where ssl_provider_id = '$row->ssl_provider_id'
			 and type_id = '$row->type_id'";
	$result3 = mysql_query($sql3,$connection);
	
}

include("check-for-missing-ssl-fees.inc.php");

$_SESSION['session_result_message'] = "All SSL Fees Have Been Fixed<BR>";

header("Location: ../../system/index.php");
exit;
?>