<?php
// /_includes/system/update-conversion-rates.inc.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<?php
// // CODE TO USE
// // 
// // Update all conversion rates for the specified user
// // Input: $temp_input_user_id / $temp_input_default_currency
// $temp_input_user_id = $xxxxx;
// $temp_input_default_currency = $xxxxx;
// include("_includes/system/update-conversion-rates.inc.php");
?>
<?php
$direct = $_GET['direct'];

if ($direct == "1") { 

	include("../start-session.inc.php");
	include("../config.inc.php");
	include("../database.inc.php");
	include("../software.inc.php");
	include("../auth/auth-check.inc.php");
	$temp_input_user_id = $_SESSION['user_id'];
	$temp_input_default_currency = $_SESSION['default_currency'];

}

include($_SESSION['full_server_path'] . "/_includes/timestamps/current-timestamp.inc.php");

$sql_ucr = "SELECT c.id, c.currency
			FROM currencies AS c, fees AS f, domains AS d
			WHERE c.id = f.currency_id
			  AND f.id = d.fee_id
			  AND d.active NOT IN ('0', '10')
			GROUP BY c.currency";
$result_ucr = mysqli_query($connection, $sql_ucr) or die(mysqli_error());

while ($row_ucr = mysqli_fetch_object($result_ucr)) {

	$sql_ucr_existing = "SELECT id
						 FROM currency_conversions
						 WHERE currency_id = '" . $row_ucr->id . "'
						   AND user_id = '" . $temp_input_user_id . "'";
	$result_ucr_existing = mysqli_query($connection, $sql_ucr_existing) or die(mysqli_error());
	
	if (mysqli_num_rows($result_ucr_existing) == 0) {
		
		$existing_currency = "";
		
	} else {
		
		$existing_currency = "1";
		
	}

	$exclude_string .= "'" . $row_ucr->currency . "', ";
	
	if ($existing_currency == "1") {

		if ($row_ucr->currency == $temp_input_default_currency) {

			$sql_ucr_update = "UPDATE currency_conversions
							   SET conversion = '1',
							   	   update_time = '" . mysqli_real_escape_string($current_timestamp) . "'
							   WHERE currency_id = '" . $row_ucr->id . "'
							     AND user_id = '" . $temp_input_user_id . "'";
			$result_ucr_update = mysqli_query($connection, $sql_ucr_update) or die(mysqli_error());

		} else {

			$from = $row_ucr->currency;
			$to = $temp_input_default_currency;
			$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
			$api_call = @fopen($full_url, "r");
		
			if ($api_call) {
		
				$api_call_result = fgets($api_call, 4096);
				fclose($api_call);
		
			}
			
			$api_call_split = explode(",",$api_call_result);
			$conversion_rate = $api_call_split[1];

			$sql_ucr_update = "UPDATE currency_conversions
							   SET conversion = '" . $conversion_rate . "',
							   	   update_time = '" . mysqli_real_escape_string($current_timestamp) . "'
							   WHERE currency_id = '" . $row_ucr->id . "'
							     AND user_id = '" . $temp_input_user_id . "'";
			$result_ucr_update = mysqli_query($connection, $sql_ucr_update) or die(mysqli_error());

		}

	} else {

		if ($row_ucr->currency == $temp_input_default_currency) {

			$sql_ucr_insert = "INSERT INTO currency_conversions
							   (currency_id, user_id, conversion, insert_time, update_time) VALUES 
							   ('" . $row_ucr->id . "', '" . $temp_input_user_id . "', '1', '" . mysqli_real_escape_string($current_timestamp) . "', '" . mysqli_real_escape_string($current_timestamp) . "')";
			$result_ucr_insert = mysqli_query($connection, $sql_ucr_insert) or die(mysqli_error());

		} else {

			$from = $row_ucr->currency;
			$to = $temp_input_default_currency;
			$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
			$api_call = @fopen($full_url, "r");
		
			if ($api_call) {
		
				$api_call_result = fgets($api_call, 4096);
				fclose($api_call);
		
			}
			
			$api_call_split = explode(",",$api_call_result);
			$conversion_rate = $api_call_split[1];
		
			$sql_ucr_insert = "INSERT INTO currency_conversions
							   (currency_id, user_id, conversion, insert_time, update_time) VALUES 
							   ('" . $row_ucr->id . "', '" . $temp_input_user_id . "', '" . $conversion_rate . "', '" . mysqli_real_escape_string($current_timestamp) . "', '" . mysqli_real_escape_string($current_timestamp) . "')";
			$result_ucr_insert = mysqli_query($connection, $sql_ucr_insert) or die(mysqli_error());

		}

	}

}

$exclude_string = substr($exclude_string, 0, -2);

$sql_ucr = "SELECT c.id, c.currency
			FROM currencies AS c, ssl_fees AS f, ssl_certs AS sslc
			WHERE c.id = f.currency_id
			  AND f.id = sslc.fee_id
			  AND sslc.active NOT IN ('0')
			  AND c.currency NOT IN (" . $exclude_string . ")
			GROUP BY c.currency";
$result_ucr = mysqli_query($connection, $sql_ucr) or die(mysqli_error());


while ($row_ucr = mysqli_fetch_object($result_ucr)) {

	$sql_ucr_existing = "SELECT id
						 FROM currency_conversions
						 WHERE currency_id = '" . $row_ucr->id . "'
						   AND user_id = '" . $temp_input_user_id . "'";
	$result_ucr_existing = mysqli_query($connection, $sql_ucr_existing) or die(mysqli_error());

	if (mysqli_num_rows($result_ucr_existing) == 0) {
		
		$existing_currency = "";
		
	} else {
		
		$existing_currency = "1";
		
	}

	if ($existing_currency == "1") {

		if ($row_ucr->currency == $temp_input_default_currency) {

			$sql_ucr_update = "UPDATE currency_conversions
							   SET conversion = '1',
							   	   update_time = '" . mysqli_real_escape_string($current_timestamp) . "'
							   WHERE currency_id = '" . $row_ucr->id . "'
							     AND user_id = '" . $temp_input_user_id . "'";
			$result_ucr_update = mysqli_query($connection, $sql_ucr_update) or die(mysqli_error());

		} else {

			$from = $row_ucr->currency;
			$to = $temp_input_default_currency;
			$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
			$api_call = @fopen($full_url, "r");
		
			if ($api_call) {
		
				$api_call_result = fgets($api_call, 4096);
				fclose($api_call);
		
			}
			
			$api_call_split = explode(",",$api_call_result);
			$conversion_rate = $api_call_split[1];

			$sql_ucr_update = "UPDATE currency_conversions
							   SET conversion = '" . $conversion_rate . "',
							   	   update_time = '" . mysqli_real_escape_string($current_timestamp) . "'
							   WHERE currency_id = '" . $row_ucr->id . "'
							     AND user_id = '" . $temp_input_user_id . "'";
			$result_ucr_update = mysqli_query($connection, $sql_ucr_update) or die(mysqli_error());

		}

	} else {

		if ($row_ucr->currency == $temp_input_default_currency) {

			$sql_ucr_insert = "INSERT INTO currency_conversions
							   (currency_id, user_id, conversion, insert_time, update_time) VALUES 
							   ('" . $row_ucr->id . "', '" . $temp_input_user_id . "', '1', '" . mysqli_real_escape_string($current_timestamp) . "', '" . mysqli_real_escape_string($current_timestamp) . "')";
			$result_ucr_insert = mysqli_query($connection, $sql_ucr_insert) or die(mysqli_error());

		} else {

			$from = $row_ucr->currency;
			$to = $temp_input_default_currency;
			$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
			$api_call = @fopen($full_url, "r");
		
			if ($api_call) {
		
				$api_call_result = fgets($api_call, 4096);
				fclose($api_call);
		
			}
			
			$api_call_split = explode(",",$api_call_result);
			$conversion_rate = $api_call_split[1];
		
			$sql_ucr_insert = "INSERT INTO currency_conversions
							   (currency_id, user_id, conversion, insert_time, update_time) VALUES 
							   ('" . $row_ucr->id . "', '" . $temp_input_user_id . "', '" . $conversion_rate . "', '" . mysqli_real_escape_string($current_timestamp) . "', '" . mysqli_real_escape_string($current_timestamp) . "')";
			$result_ucr_insert = mysqli_query($connection, $sql_ucr_insert) or die(mysqli_error());

		}

	}

}

if ($direct == "1") {

	$_SESSION['result_message'] .= "Conversion Rates Updated<BR>";
	
	header("Location: " . $_SERVER['HTTP_REFERER']);
	exit;

} else {
	
	$_SESSION['result_message'] .= "Conversion Rates Updated<BR>";

}
?>
