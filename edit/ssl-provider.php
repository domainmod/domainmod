<?php
// ssl-provider.php
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
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
$software_section = "ssl-providers";

$sslpid = $_GET['sslpid'];

// Form Variables
$new_ssl_provider = mysql_real_escape_string($_POST['new_ssl_provider']);
$new_url = mysql_real_escape_string($_POST['new_url']);
$new_notes = mysql_real_escape_string($_POST['new_notes']);
$IS_SUBMITTED_SSL_PROVIDER = $_POST['IS_SUBMITTED_SSL_PROVIDER'];
$new_type_id = $_POST['new_type_id'];
$new_function_id = $_POST['new_function_id'];
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_currency_id = $_POST['new_currency_id'];
$IS_SUBMITTED_FEE = $_POST['IS_SUBMITTED_FEE'];
$new_sslpid = $_POST['new_sslpid'];

if ($IS_SUBMITTED_SSL_PROVIDER == "1") {

	if ($new_ssl_provider != "" && $new_url != "") {

		$sql = "update ssl_providers
				set name = '$new_ssl_provider', 
				url = '$new_url', 
				notes = '$new_notes',
				update_time = '$current_timestamp'
				where id = '$new_sslpid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$new_ssl_provider = stripslashes($new_ssl_provider);

		$sslpid = $new_sslpid;
		
		$_SESSION['session_result_message'] = "SSL Provider Updated<BR>";

	} else {

		if ($new_ssl_provider == "") $_SESSION['session_result_message'] .= "Please Enter The SSL Provider's Name<BR>";
		if ($new_url == "") $_SESSION['session_result_message'] .= "Please Enter The SSL Provider's URL<BR>";

	}

} elseif ($IS_SUBMITTED_FEE == "1") {

	if ($new_sslpid == "" || $new_initial_fee == "" || $new_renewal_fee == "") { 

		$_SESSION['session_result_message'] = "Please Enter All Fields Before Submitting The New Fee<BR>";

	} else {
		
		if ($new_initial_fee == "0" && $new_renewal_fee == "0") {

			$sql = "delete from ssl_fees
					where ssl_provider_id = '$new_sslpid'
					and type_id = '$new_type_id'
					and function_id = '$new_function_id'";
			mysql_query($sql,$connection);
			
			$sql = "update ssl_certs
					set fee_id = '0',
					update_time = '$current_timestamp'
					where ssl_provider_id = '$new_sslpid'
					and type_id = '$new_type_id'
					and function_id = '$new_function_id'";
			mysql_query($sql,$connection);
			
			header("Location: ssl-provider.php?sslpid=$new_sslpid");
			exit;
		}
		
		$sql = "select *
				from ssl_fees
				where ssl_provider_id = '$new_sslpid'
				and type_id = '$new_type_id'
				and function_id = '$new_function_id'";
		$result = mysql_query($sql,$connection);

		if (mysql_num_rows($result) > 0) {
			
			$sql = "update ssl_fees
					set initial_fee = '$new_initial_fee',
					renewal_fee = '$new_renewal_fee',
					currency_id = '$new_currency_id',
					update_time = '$current_timestamp'
					where ssl_provider_id = '$new_sslpid'
					and type_id = '$new_type_id'
					and function_id = '$new_function_id'";
			mysql_query($sql,$connection);

			$sql = "select id
					from ssl_fees
					where ssl_provider_id = '$new_sslpid'
					and type_id = '$new_type_id'
					and function_id = '$new_function_id'
					and currency_id = '$new_currency_id'
					limit 1";
	
			$result = mysql_query($sql,$connection) or die(mysql_error());

			while ($row = mysql_fetch_object($result)) {
				$new_fee_id = $row->id;
			}

			$sql = "update ssl_certs
					set fee_id = '$new_fee_id',
					update_time = '$current_timestamp'
					where ssl_provider_id = '$new_sslpid'
					and type_id = '$new_type_id'
					and function_id = '$new_function_id'";
			$result = mysql_query($sql,$connection);
	
			$sslpid = $new_sslpid;

		$_SESSION['session_result_message'] = "Fee Updated Successfully<BR>";

		} else {
			
			$sql = "insert into ssl_fees
					(ssl_provider_id, type_id, function_id, initial_fee, renewal_fee, currency_id, insert_time)
					values ('$new_sslpid', '$new_type_id', '$new_function_id', '$new_initial_fee', '$new_renewal_fee', '$new_currency_id', '$current_timestamp')";
			$result = mysql_query($sql,$connection);

			$sql = "select id
					from ssl_fees
					where ssl_provider_id = '$new_sslpid'
					and type_id = '$new_type_id'
					and function_id = '$new_function_id'
					and currency_id = '$new_currency_id'
					order by id desc
					limit 1";
	
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			while ($row = mysql_fetch_object($result)) {
				$new_fee_id = $row->id;
			}

			$sql = "update ssl_certs
					set fee_id = '$new_fee_id',
					update_time = '$current_timestamp'
					where ssl_provider_id = '$new_sslpid'
					and type_id = '$new_type_id'
					and function_id = '$new_function_id'";
			$result = mysql_query($sql,$connection);
	
			$_SESSION['session_result_message'] = "Fee Submitted Successfully<BR>";
	
		}

	}

}

include("../_includes/system/check-for-missing-ssl-fees.inc.php");

$sql = "select name, url, notes
		from ssl_providers
		where id = '$sslpid'";
$result = mysql_query($sql,$connection);

while ($row = mysql_fetch_object($result)) { 

	$new_ssl_provider = $row->name;
	$new_url = $row->url;
	$new_notes = $row->notes;

}
$page_title = "Editting An SSL Provider";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="form1" method="post" action="<?=$PHP_SELF?>">
<strong>SSL Provider Name:</strong><BR><BR>
<input name="new_ssl_provider" type="text" value="<?php if ($new_ssl_provider != "") echo stripslashes($new_ssl_provider); ?>" size="50" maxlength="255">
<BR><BR>
<strong>SSL Provider's URL:</strong><BR><BR>
<input name="new_url" type="text" value="<?php if ($new_url != "") echo stripslashes($new_url); ?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
<BR><BR><BR>
<input type="hidden" name="new_sslpid" value="<?=$sslpid?>">
<input type="hidden" name="IS_SUBMITTED_SSL_PROVIDER" value="1">
<input type="submit" name="button" value="Update This SSL Provider &raquo;">
</form>
<BR><BR>
<font class="headline">Active Types</font><BR><BR>
<?php
$sql = "select concat(sslcf.function, ' (', sslct.type, ')') as full_tf_string
		from ssl_certs as sslc, ssl_cert_types as sslct, ssl_cert_functions as sslcf
		where sslc.type_id = sslct.id
		and sslc.function_id = sslcf.id
		and sslc.active = '1'
		and sslc.ssl_provider_id = '$sslpid'
		group by full_tf_string
		order by full_tf_string asc";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	$temp_full_ft_string = $temp_full_ft_string .= "$row->full_tf_string / ";
}
	$all_types = substr($temp_full_ft_string, 0, -2); 
?>
<?=$all_types?>
<BR><BR><BR><BR>
<?php
$sql = "select concat(sslcf.function, ' (', sslct.type, ')') as full_tf_string
		from ssl_certs as sslc, ssl_cert_types as sslct, ssl_cert_functions as sslcf
		where sslc.type_id = sslct.id
		and sslc.function_id = sslcf.id
		and sslc.active = '1'
		and sslc.ssl_provider_id = '$sslpid'
		and sslc.fee_id = '0'
		group by full_tf_string
		order by full_tf_string asc";

$result = mysql_query($sql,$connection);
if (mysql_num_rows($result) > 0) {
?>
    <a name="missingfees"></a><font class="headline">Missing or Out Of Date Fees</font><BR><BR>
    <?php
    while ($row = mysql_fetch_object($result)) {
        $temp_all_missing_fees = $temp_all_missing_fees .= "$row->full_tf_string / ";
    }
        $all_missing_fees = substr($temp_all_missing_fees, 0, -2); 
    ?>
    <?=$all_missing_fees?><BR><BR>
    <strong><font color="#DD0000">*</font> Please update the fees below in order to ensure proper SSL accounting.</strong>
    <BR><BR><BR>
<?php
}
?>
<font class="headline">Add/Update Fee</font><BR><BR>
<form name="form1" method="post" action="<?=$PHP_SELF?>">
<table border="0" cellspacing="0" cellpadding="0">
	<tr>
    	<td width="265" valign="top">
        	<strong>Function</strong><BR><BR>
		  <select name="new_function_id">
		  	<?php
			$sql = "select id, function
					from ssl_cert_functions
					where active = '1'
					order by function asc";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) {
			?>
		    <option value="<?=$row->id?>"><?=$row->function?></option>
			<?php
			}
			?>
	      </select>
		</td>
    	<td width="110" valign="top">
        	<strong>Type</strong><BR><BR>
		  <select name="new_type_id">
		  	<?php
			$sql = "select id, type
					from ssl_cert_types
					where active = '1'
					order by type asc";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) {
			?>
		    <option value="<?=$row->id?>"><?=$row->type?></option>
			<?php
			}
			?>
	      </select>
		</td>
		<td width="110" valign="top">
        	<strong>Initial Fee</strong><BR><BR>
	    	<input name="new_initial_fee" type="text" value="" size="10">
        </td>
		<td width="110" valign="top">
        	<strong>Renewal Fee</strong><BR><BR>
	    	<input name="new_renewal_fee" type="text" value="" size="10">
		</td>
	  	<td valign="top">
        	<strong>Currency</strong><BR><BR>
		  <select name="new_currency_id">
		  	<?php
			$sql = "select id, currency, name 
					from currencies
					where active = '1'
					order by currency asc";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) {
			?>
		    <option value="<?=$row->id?>"><?php echo "$row->currency - $row->name"; ?></option>
			<?php
			}
			?>
	      </select>
	    </td>
	</tr>
</table>
<input type="hidden" name="new_sslpid" value="<?=$sslpid?>">
<input type="hidden" name="IS_SUBMITTED_FEE" value="1">
<BR><BR><input type="submit" name="button" value="Add/Update This Fee &raquo;">
</form>
<BR><BR>
<font class="headline">Fees</font><BR><BR>
<table border="0" cellspacing="0" cellpadding="0">
	<tr>
    	<td height="10" width="300" valign="top"><strong>Type/Function</strong></td>
        <td width="100"><strong>Initial Fee</strong></td>
        <td width="100"><strong>Renewal Fee</strong></td>
        <td width="100"><strong>Currency</strong></td>
	</tr>
<?php
$sql = "select f.initial_fee, f.renewal_fee, c.currency, sslct.type, sslcf.function
		from ssl_fees as f, currencies as c, ssl_cert_types as sslct, ssl_cert_functions as sslcf
		where f.currency_id = c.id
		and f.type_id = sslct.id
		and f.function_id = sslcf.id
		and f.ssl_provider_id = '$sslpid'
		and c.active = '1'
		order by sslcf.function asc, sslct.type asc";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
?>
	<tr>
    	<td height="18" valign="middle"><?=$row->function?> (<?=$row->type?>)&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td>$<?=$row->initial_fee?></td>
        <td>$<?=$row->renewal_fee?></td>
        <td><?=$row->currency?></td>
	</tr>
<?php
}
?>
</table>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>