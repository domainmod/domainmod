<?php
// update-exchange-rates.php
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

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "index.php";
include("../_includes/auth/admin-user-check.inc.php");

include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/auth/auth-check.inc.php");

$page_title = "Update Exchange Rates";
$software_section = "system";

$sql = "select currency
		from currencies
		where default_currency = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	$default_currency = $row->currency;
}

$sql = "update currencies
		set conversion = '1', 
			update_time = '$current_timestamp'
		where currency = '$default_currency'";
$result = mysql_query($sql,$connection);

$sql = "select currency
		from currencies
		where currency != '$default_currency'";
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
	
	$sql2 = "update currencies
			 set conversion = '$value', 
			 	 update_time = '$current_timestamp'
			 where currency = '$row->currency'";
	$result2 = mysql_query($sql2,$connection);

}

$_SESSION['session_result_message'] .= "Exchange Rates Updated<BR>";

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>