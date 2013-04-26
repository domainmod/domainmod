<?php
// /system/update-conversion-rates.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/auth/auth-check.inc.php");

$page_title = "Update Conversion Rates";
$software_section = "system";

$sql = "UPDATE currencies
		SET conversion = '1', 
			update_time = '$current_timestamp'
		WHERE default_currency = '1'";
$result = mysql_query($sql,$connection);

$sql = "SELECT currency
		FROM currencies
		WHERE default_currency = '1'";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) { 
	$default_currency = $row->currency; 
	$_SESSION['session_default_currency'] = $row->currency;
}

$sql = "SELECT currency
		FROM currencies
		WHERE default_currency = '0'";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	
	$from = $row->currency;
	$to = $default_currency;
	$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
	$handle = @fopen($full_url, "r");
	 
	if ($handle) {

		$handle_result = fgets($handle, 4096);
		fclose($handle);

	}
	
	$data = explode(",",$handle_result);
	$value = $data[1];
	
	$sql_update = "UPDATE currencies
				   SET conversion = '$value', 
				   	   update_time = '$current_timestamp'
				   WHERE currency = '$row->currency'";
	$result_update = mysql_query($sql_update,$connection);

}

$_SESSION['session_result_message'] .= "Conversion Rates Updated<BR>";

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>