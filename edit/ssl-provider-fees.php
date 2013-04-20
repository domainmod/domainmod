<?php
// /edit/ssl-provider-fees.php
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
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Editting An SSL Provider's Fees";
$software_section = "ssl-providers";

// 'Delete SSL Provider Fee' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslpid = $_GET['sslpid'];
$ssltid = $_GET['ssltid'];
$sslfeeid = $_GET['sslfeeid'];

// Form Variables
$new_type_id = $_POST['new_type_id'];
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_currency_id = $_POST['new_currency_id'];
$new_sslpid = $_POST['new_sslpid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_sslpid == "" || $new_type_id == "" || $new_type_id == "0" || $new_initial_fee == "" || $new_renewal_fee == "" && $new_currency_id != "" && $new_currency_id != "0") {
		
		if ($new_initial_fee == "") $_SESSION['session_result_message'] .= "Please enter the initial fee<BR>";
		if ($new_renewal_fee == "") $_SESSION['session_result_message'] .= "Please enter the renewal fee<BR>";
		if ($new_type_id == "" || $new_type_id == "0") $_SESSION['session_result_message'] .= "There was a problem with the SSL Type you chose<BR>";
		if ($new_currency_id == "" || $new_currency_id == "0") $_SESSION['session_result_message'] .= "There was a problem with the currency you chose<BR>";

	} else {
		
		$sql = "SELECT *
				FROM ssl_fees
				WHERE ssl_provider_id = '$new_sslpid'
				  AND type_id = '$new_type_id'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		if (mysql_num_rows($result) > 0) {
			
			$sql = "UPDATE ssl_fees
					SET initial_fee = '$new_initial_fee',
						renewal_fee = '$new_renewal_fee',
						currency_id = '$new_currency_id',
						update_time = '$current_timestamp'
					WHERE ssl_provider_id = '$new_sslpid'
					  AND type_id = '$new_type_id'";
			$result = mysql_query($sql,$connection) or die(mysql_error());

			$sql = "SELECT id
					FROM ssl_fees
					WHERE ssl_provider_id = '$new_sslpid'
					  AND type_id = '$new_type_id'
					  AND currency_id = '$new_currency_id'
					LIMIT 1";
	
			$result = mysql_query($sql,$connection) or die(mysql_error());

			while ($row = mysql_fetch_object($result)) {
				$new_fee_id = $row->id;
			}

			$sql = "UPDATE ssl_certs
					SET fee_id = '$new_fee_id',
						update_time = '$current_timestamp'
					WHERE ssl_provider_id = '$new_sslpid'
					  AND type_id = '$new_type_id'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$sql = "SELECT type
					FROM ssl_cert_types
					WHERE id = '$new_type_id'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			while ($row = mysql_fetch_object($result)) { $temp_type = $row->type; }
	
			$sslpid = $new_sslpid;

			$_SESSION['session_result_message'] = "The fee for <font class=\"highlight\">$temp_type</font> has been updated<BR>";
			
			header("Location: ssl-provider-fees.php?sslpid=$new_sslpid");
			exit;

		} else {
			
			$sql = "INSERT INTO ssl_fees 
					(ssl_provider_id, type_id, initial_fee, renewal_fee, currency_id, insert_time) VALUES 
					('$new_sslpid', '$new_type_id', '$new_initial_fee', '$new_renewal_fee', '$new_currency_id', '$current_timestamp')";
			$result = mysql_query($sql,$connection) or die(mysql_error());

			$sql = "SELECT id
					FROM ssl_fees
					WHERE ssl_provider_id = '$new_sslpid'
					  AND type_id = '$new_type_id'
					  AND currency_id = '$new_currency_id'
					ORDER BY id desc
					LIMIT 1";
	
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			while ($row = mysql_fetch_object($result)) {
				$new_fee_id = $row->id;
			}

			$sql = "UPDATE ssl_certs
					SET fee_id = '$new_fee_id',
						update_time = '$current_timestamp'
					WHERE ssl_provider_id = '$new_sslpid'
					  AND type_id = '$new_type_id'";
			$result = mysql_query($sql,$connection) or die(mysql_error());

			$sql = "SELECT type
					FROM ssl_cert_types
					WHERE id = '$new_type_id'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			while ($row = mysql_fetch_object($result)) { $temp_type = $row->type; }
	
			$_SESSION['session_result_message'] = "The fee for <font class=\"highlight\">$temp_type</font> has been added<BR>";
			
			header("Location: ssl-provider-fees.php?sslpid=$new_sslpid");
			exit;

		}

	}

} else {

	include("../_includes/system/update-ssl-fees.inc.php");
	
}
if ($del == "1") {
	$_SESSION['session_result_message'] = "Are you sure you want to delete this SSL Provider Fee?<BR><BR><a href=\"$PHP_SELF?sslpid=$sslpid&ssltid=$ssltid&sslfeeid=$sslfeeid&really_del=1\">YES, REALLY DELETE THIS SSL PROVIDER FEE</a><BR>";
}
if ($really_del == "1") {

	$sql = "SELECT *
			FROM ssl_fees
			WHERE id = '$sslfeeid'
			  AND ssl_provider_id = '$sslpid'
			  AND type_id = '$ssltid'";
	$result = mysql_query($sql,$connection);
	
	if (mysql_num_rows($result) == 0) {

		$_SESSION['session_result_message'] = "The fee you're trying to delete doesn't exist<BR>";

		header("Location: ssl-provider-fees.php?sslpid=$new_sslpid");
		exit;

	} else {

		$sql = "DELETE FROM ssl_fees
				WHERE id = '$sslfeeid'
				  AND ssl_provider_id = '$sslpid'
				  AND type_id = '$ssltid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE ssl_certs
				SET fee_id = '0',
					update_time = '$current_timestamp'
				WHERE fee_id = '$sslfeeid'
				  AND ssl_provider_id = '$sslpid'
				  AND type_id = '$ssltid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "SELECT type
				FROM ssl_cert_types
				WHERE id = '$ssltid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		while ($row = mysql_fetch_object($result)) { $temp_type = $row->type; }
		
		$_SESSION['session_result_message'] = "The fee for <font class=\"highlight\">$temp_type</font> has been deleted<BR>";

		header("Location: ssl-provider-fees.php?sslpid=$sslpid");
		exit;

	}
	
}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<?php
$sql = "SELECT name
		FROM ssl_providers
		WHERE id = '$sslpid'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $temp_ssl_provider_name = $row->name; } ?>
The below fees are for the SSL provider <a href="ssl-provider.php?sslpid=<?=$sslpid?>"><?=$temp_ssl_provider_name?></a>.<BR><BR>
<?php
$sql = "SELECT t.type
		FROM ssl_certs AS c, ssl_cert_types AS t
		WHERE c.type_id = t.id
		  AND c.ssl_provider_id = '$sslpid'
		  AND c.fee_id = '0'
		GROUP BY t.type
		ORDER BY t.type asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
if (mysql_num_rows($result) > 0) {
?>
    <BR><a name="missingfees"></a><font class="subheadline">Missing SSL Type Fees</font><BR><BR>
    <?php
	$count = 0;
    while ($row = mysql_fetch_object($result)) {
        $temp_all_missing_fees = $temp_all_missing_fees .= "$row->type, ";
		$count++;
    }
	$all_missing_fees = substr($temp_all_missing_fees, 0, -2); 
    ?>
    <?=$all_missing_fees?><BR><BR>
    <?php if ($count > 1) { ?>
	    <strong>Please update the fees for these SSL Types below in order to ensure proper SSL accounting.</strong>
	<?php } else { ?>
	    <strong>Please update the fees for this SSL Type below in order to ensure proper SSL accounting.</strong>
    <?php } ?>
    <BR><BR>
<?php
}
?>
<?php
$sql = "SELECT t.id, t.type
		FROM ssl_certs AS c, ssl_cert_types AS t
		WHERE c.type_id = t.id
		  AND c.ssl_provider_id = '$sslpid'
		  AND c.active not in ('0')
		GROUP BY t.type
		ORDER BY t.type";
$result = mysql_query($sql,$connection) or die(mysql_error());


if (mysql_num_rows($result) != 0) {
?>

    <BR><BR><font class="subheadline">SSL Types Linked to Active SSL Certificates</font><BR><BR>
    
    <?php
    while ($row = mysql_fetch_object($result)) {
        
        $sql_temp = "SELECT fee_id
                     FROM ssl_certs
                     WHERE ssl_provider_id = '$sslpid'
                       AND type_id = '$row->id'";
        $result_temp = mysql_query($sql_temp,$connection) or die(mysql_error());
        while ($row_temp = mysql_fetch_object($result_temp)) { $temp_fee_id = $row_temp->fee_id; }
        
        if ($temp_fee_id == "0") {
            $temp_all_types = $temp_all_types .= "<font class=\"highlight\">$row->type</font>, ";
        } else {
            $temp_all_types = $temp_all_types .= "$row->type, ";
        }
    
    }

	$all_types = substr($temp_all_types, 0, -2); 
	echo $all_types;
	echo "<BR><BR><BR>";

}
?>
<BR>
<font class="subheadline">Add/Update SSL Type Fee</font><BR><BR>
<form name="edit_ssl_provider_fee_form" method="post" action="<?=$PHP_SELF?>">
<table class="main_table">
	<tr class="main_table_row_heading_active">
    	<td class="main_table_cell_heading_active">
        	<strong>SSL Type</strong><BR>
              <select name="new_type_id">
                <?php
                $sql = "SELECT id, type
                        FROM ssl_cert_types
                        WHERE active = '1'
                        ORDER BY default_type desc, type asc";
                $result = mysql_query($sql,$connection);
                while ($row = mysql_fetch_object($result)) {

					if ($row->id == $new_type_id) {
					?>
						<option value="<?=$row->id?>" selected><?php echo "$row->type"; ?></option>
					<?php
					} else {
					?>
						<option value="<?=$row->id?>"><?php echo "$row->type"; ?></option>
					<?php
					}
					?>
                <?php
                }
                ?>
              </select>
        </td>
		<td class="main_table_cell_heading_active">
        	<strong>Initial Fee</strong><BR>
            <input name="new_initial_fee" type="text" value="<?=$new_initial_fee?>" size="10">
        </td>
		<td class="main_table_cell_heading_active">
        	<strong>Renewal Fee</strong><BR>
            <input name="new_renewal_fee" type="text" value="<?=$new_renewal_fee?>" size="10">
		</td>
	  	<td class="main_table_cell_heading_active"><strong>Currency</strong><BR>
		  <select name="new_currency_id" id="new_currency">
		  	<?php
			$sql = "SELECT id, currency, name 
					FROM currencies
					WHERE active = '1'
					ORDER BY default_currency desc, currency";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			while ($row = mysql_fetch_object($result)) {
			
			if ($row->id == $new_currency_id) {
			?>
			    <option value="<?=$row->id?>" selected><?php echo "$row->currency - $row->name"; ?></option>
			<?php
			} else {
			?>
			    <option value="<?=$row->id?>"><?php echo "$row->currency - $row->name"; ?></option>
			<?php
			}
			}
			?>
	      </select>
	    </td>
	</tr>
</table>
<input type="hidden" name="new_sslpid" value="<?=$sslpid?>">
<BR><BR><input type="submit" name="button" value="Add/Update This SSL Fee &raquo;">
</form>
<BR><BR>
<font class="subheadline">SSL Type Fees</font><BR><BR>
<table class="main_table">
	<tr class="main_table_row_heading_active">
    	<td class="main_table_cell_heading_active"><strong>SSL Type</strong></td>
        <td class="main_table_cell_heading_active"><strong>Initial Fee</strong></td>
        <td class="main_table_cell_heading_active"><strong>Renewal Fee</strong></td>
        <td class="main_table_cell_heading_active"><strong>Currency</strong></td>
	</tr>
<?php
$sql = "SELECT f.id as sslfeeid, f.initial_fee, f.renewal_fee, c.currency, t.id as ssltid, t.type
		FROM ssl_fees AS f, currencies AS c, ssl_cert_types AS t
		WHERE f.currency_id = c.id
		  AND f.type_id = t.id
		  AND f.ssl_provider_id = '$sslpid'
		  AND c.active = '1'
		ORDER BY t.type asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) {
?>
	<tr class="main_table_row_active">
    	<td class="main_table_cell_active"><?=$row->type?></td>
        <td class="main_table_cell_active">
			<?php
			$temp_input_amount = $row->initial_fee;
			$temp_input_conversion = "";
			include("../_includes/system/convert-and-format-currency.inc.php");
			echo $temp_output_amount;
            ?>
		</td>
        <td class="main_table_cell_active">
			<?php
			$temp_input_amount = $row->renewal_fee;
			$temp_input_conversion = "";
			include("../_includes/system/convert-and-format-currency.inc.php");
			echo $temp_output_amount;
            ?>
		</td>
        <td class="main_table_cell_active"><?=$row->currency?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[<a class="invisiblelink" href="ssl-provider-fees.php?sslpid=<?=$sslpid?>&ssltid=<?=$row->ssltid?>&sslfeeid=<?=$row->sslfeeid?>&del=1">delete</a>]
        </td>
	</tr>
<?php
}
?>
</table>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>