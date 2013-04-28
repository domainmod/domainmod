<?php
// /cron/currencies.php
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
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$sql = "SELECT default_currency
		FROM settings";
$result = mysql_query($sql,$connection);

while ($row = mysql_fetch_object($result)) {
	$temp_default_currency = $row->default_currency;
}

$sql = "UPDATE currencies
		SET conversion = '1', 
			update_time = '" . $current_timestamp . "'
		WHERE currency = '" . $temp_default_currency . "'";
$result = mysql_query($sql,$connection);

$sql = "SELECT c.currency
		FROM currencies AS c, fees AS f, domains AS d
		WHERE c.id = f.currency_id
		  AND f.id = d.fee_id
		  AND d.active NOT IN ('0', '10')
		  AND c.currency != '" . $temp_default_currency . "'
		GROUP BY c.currency";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	
	$exclude_string .= "'" . $row->currency . "', ";

	$from = $row->currency;
	$to = $temp_default_currency;
	$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
	$api_call = @fopen($full_url, "r");

	if ($api_call) {

		$api_call_result = fgets($api_call, 4096);
		fclose($api_call);

	}
	
	$api_call_split = explode(",",$api_call_result);
	$conversion_rate = $api_call_split[1];
	
	$sql_update = "UPDATE currencies
				   SET conversion = '" . $conversion_rate . "', 
				   	   update_time = '" . $current_timestamp . "'
				   WHERE currency = '" . $row->currency . "'";
	$result_update = mysql_query($sql_update,$connection);

}

$exclude_string = substr($exclude_string, 0, -2);

$sql = "SELECT c.currency
		FROM currencies AS c, ssl_fees AS f, ssl_certs AS sslc
		WHERE c.id = f.currency_id
		  AND f.id = sslc.fee_id
		  AND sslc.active NOT IN ('0')
		  AND c.currency != '" . $temp_default_currency . "'
		  AND c.currency NOT IN (" . $exclude_string . ")
		GROUP BY c.currency";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	
	$exclude_string .= "'" . $row->currency . "', ";

	$from = $row->currency;
	$to = $temp_default_currency;
	$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
	$api_call = @fopen($full_url, "r");

	if ($api_call) {

		$api_call_result = fgets($api_call, 4096);
		fclose($api_call);

	}
	
	$api_call_split = explode(",",$api_call_result);
	$conversion_rate = $api_call_split[1];
	
	$sql_update = "UPDATE currencies
				   SET conversion = '" . $conversion_rate . "', 
				   	   update_time = '" . $current_timestamp . "'
				   WHERE currency = '" . $row->currency . "'";
	$result_update = mysql_query($sql_update,$connection);

}
?>