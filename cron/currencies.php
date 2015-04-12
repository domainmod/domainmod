<?php
/**
 * /cron/currencies.php
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
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/system/functions/error-reporting.inc.php");

include("../_includes/config-demo.inc.php");

if ($demo_install != "1") {

	$sql = "SELECT c.id, c.currency
			FROM currencies AS c, fees AS f, domains AS d
			WHERE c.id = f.currency_id
			  AND f.id = d.fee_id
			  AND d.active NOT IN ('0', '10')
			  GROUP BY c.currency";
	$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);
	
	while ($row = mysqli_fetch_object($result)) {
	
		$exclude_string .= "'" . $row->currency . "', ";
	
		$sql_user = "SELECT user_id, default_currency
					 FROM user_settings";
		$result_user = mysqli_query($connection, $sql_user);
		
		while ($row_user = mysqli_fetch_object($result_user)) {
	
			$sql_existing = "SELECT id
							 FROM currency_conversions
							 WHERE currency_id = '" . $row->id . "'
							   AND user_id = '" . $row_user->user_id . "'";
			$result_existing = mysqli_query($connection, $sql_existing) or outputOldSqlError($connection);
			
			if (mysqli_num_rows($result_existing) == 0) {
				
				$existing_currency = "";
				
			} else {
				
				$existing_currency = "1";
				
			}
		
			if ($existing_currency == "1") {
		
				if ($row->currency == $row_user->default_currency) {
		
					$sql_update = "UPDATE currency_conversions
								   SET conversion = '1',
									   update_time = '" . $current_timestamp . "'
								   WHERE currency_id = '" . $row->id . "'
									 AND user_id = '" . $row_user->user_id . "'";
					$result_update = mysqli_query($connection, $sql_update) or outputOldSqlError($connection);
		
				} else {
		
					$from = $row->currency;
					$to = $row_user->default_currency;
					$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
					$api_call = @fopen($full_url, "r");
				
					if ($api_call) {
				
						$api_call_result = fgets($api_call, 4096);
						fclose($api_call);
				
					}
					
					$api_call_split = explode(",", $api_call_result);
					$conversion_rate = $api_call_split[1];
		
					$sql_update = "UPDATE currency_conversions
								   SET conversion = '" . $conversion_rate . "',
									   update_time = '" . $current_timestamp . "'
								   WHERE currency_id = '" . $row->id . "'
									 AND user_id = '" . $row_user->user_id . "'";
					$result_update = mysqli_query($connection, $sql_update) or outputOldSqlError($connection);
		
				}
		
			} else {
		
				if ($row->currency == $row_user->default_currency) {
		
					$sql_insert = "INSERT INTO currency_conversions
								   (currency_id, user_id, conversion, insert_time, update_time) VALUES 
								   ('" . $row->id . "', '" . $row_user->user_id . "', '1', '" . $current_timestamp . "', '" . $current_timestamp . "')";
					$result_insert = mysqli_query($connection, $sql_insert) or outputOldSqlError($connection);
		
				} else {
		
					$from = $row->currency;
					$to = $row_user->default_currency;
					$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
					$api_call = @fopen($full_url, "r");
				
					if ($api_call) {
				
						$api_call_result = fgets($api_call, 4096);
						fclose($api_call);
				
					}
					
					$api_call_split = explode(",", $api_call_result);
					$conversion_rate = $api_call_split[1];
				
					$sql_insert = "INSERT INTO currency_conversions
								   (currency_id, user_id, conversion, insert_time, update_time) VALUES 
								   ('" . $row->id . "', '" . $row_user->user_id . "', '" . $conversion_rate . "', '" . $current_timestamp . "', '" . $current_timestamp . "')";
					$result_insert = mysqli_query($connection, $sql_insert) or outputOldSqlError($connection);
		
				}
		
			}
	
		}
	
	}
	
	$exclude_string = substr($exclude_string, 0, -2);
	
	$sql = "SELECT c.id, c.currency
			FROM currencies AS c, ssl_fees AS f, ssl_certs AS sslc
			WHERE c.id = f.currency_id
			  AND f.id = sslc.fee_id
			  AND sslc.active NOT IN ('0')
			  AND c.currency NOT IN (" . $exclude_string . ")
			  GROUP BY c.currency";
	$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);
	
	while ($row = mysqli_fetch_object($result)) {
	
		$sql_user = "SELECT user_id, default_currency
					 FROM user_settings";
		$result_user = mysqli_query($connection, $sql_user);
		
		while ($row_user = mysqli_fetch_object($result_user)) {
	
			$sql_existing = "SELECT id
							 FROM currency_conversions
							 WHERE currency_id = '" . $row->id . "'
							   AND user_id = '" . $row_user->user_id . "'";
			$result_existing = mysqli_query($connection, $sql_existing) or outputOldSqlError($connection);
			
			if (mysqli_num_rows($result_existing) == 0) {
				
				$existing_currency = "";
				
			} else {
				
				$existing_currency = "1";
				
			}
		
			if ($existing_currency == "1") {
		
				if ($row->currency == $row_user->default_currency) {
		
					$sql_update = "UPDATE currency_conversions
								   SET conversion = '1',
									   update_time = '" . $current_timestamp . "'
								   WHERE currency_id = '" . $row->id . "'
									 AND user_id = '" . $row_user->user_id . "'";
					$result_update = mysqli_query($connection, $sql_update) or outputOldSqlError($connection);
		
				} else {
		
					$from = $row->currency;
					$to = $row_user->default_currency;
					$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
					$api_call = @fopen($full_url, "r");
				
					if ($api_call) {
				
						$api_call_result = fgets($api_call, 4096);
						fclose($api_call);
				
					}
					
					$api_call_split = explode(",", $api_call_result);
					$conversion_rate = $api_call_split[1];
		
					$sql_update = "UPDATE currency_conversions
								   SET conversion = '" . $conversion_rate . "',
									   update_time = '" . $current_timestamp . "'
								   WHERE currency_id = '" . $row->id . "'
									 AND user_id = '" . $row_user->user_id . "'";
					$result_update = mysqli_query($connection, $sql_update) or outputOldSqlError($connection);
		
				}
		
			} else {
		
				if ($row->currency == $row_user->default_currency) {
		
					$sql_insert = "INSERT INTO currency_conversions
								   (currency_id, user_id, conversion, insert_time, update_time) VALUES 
								   ('" . $row->id . "', '" . $row_user->user_id . "', '1', '" . $current_timestamp . "', '" . $current_timestamp . "')";
					$result_insert = mysqli_query($connection, $sql_insert) or outputOldSqlError($connection);
		
				} else {
		
					$from = $row->currency;
					$to = $row_user->default_currency;
					$full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from . $to ."=X";
					$api_call = @fopen($full_url, "r");
				
					if ($api_call) {
				
						$api_call_result = fgets($api_call, 4096);
						fclose($api_call);
				
					}
					
					$api_call_split = explode(",", $api_call_result);
					$conversion_rate = $api_call_split[1];
				
					$sql_insert = "INSERT INTO currency_conversions
								   (currency_id, user_id, conversion, insert_time, update_time) VALUES 
								   ('" . $row->id . "', '" . $row_user->user_id . "', '" . $conversion_rate . "', '" . $current_timestamp . "', '" . $current_timestamp . "')";
					$result_insert = mysqli_query($connection, $sql_insert) or outputOldSqlError($connection);
		
				}
		
			}
	
		}
	
	}
	
}
