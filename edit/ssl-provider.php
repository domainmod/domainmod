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

$page_title = "Editting An SSL Provider";
$software_section = "ssl-providers";

// 'Delete SSL Provider' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslpid = $_GET['sslpid'];

// Form Variables
$new_ssl_provider = $_POST['new_ssl_provider'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];
$IS_SUBMITTED_SSL_PROVIDER = $_POST['IS_SUBMITTED_SSL_PROVIDER'];
$new_type_id = $_POST['new_type_id'];
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_currency_id = $_POST['new_currency_id'];
$IS_SUBMITTED_FEE = $_POST['IS_SUBMITTED_FEE'];
$new_sslpid = $_POST['new_sslpid'];
$new_default_provider = $_POST['new_default_provider'];

if ($IS_SUBMITTED_SSL_PROVIDER == "1") {

	if ($new_ssl_provider != "" && $new_url != "") {

		if ($new_default_provider == "1") {

			$sql = "UPDATE ssl_providers
					SET default_provider = '0',
					    update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "SELECT default_provider
					FROM ssl_providers
					WHERE default_provider = '1'
					  AND id != '$new_sslpid'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_default_provider = $row->default_provider; }
			if ($temp_default_provider == "") { $new_default_provider = "1"; }
		
		}

		$sql = "UPDATE ssl_providers
				SET name = '" . mysql_real_escape_string($new_ssl_provider) . "', 
					url = '" . mysql_real_escape_string($new_url) . "', 
					notes = '" . mysql_real_escape_string($new_notes) . "',
					default_provider = '$new_default_provider',
					update_time = '$current_timestamp'
				WHERE id = '$new_sslpid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sslpid = $new_sslpid;
		
		$_SESSION['session_result_message'] = "SSL Provider <font class=\"highlight\">$new_ssl_provider</font> Updated<BR>";

	} else {

		if ($new_ssl_provider == "") $_SESSION['session_result_message'] .= "Please enter the SSL provider's name<BR>";
		if ($new_url == "") $_SESSION['session_result_message'] .= "Please enter the SSL provider's URL<BR>";

	}

} elseif ($IS_SUBMITTED_FEE == "1") {

	if ($new_sslpid == "" || $new_initial_fee == "" || $new_renewal_fee == "") { 

		$_SESSION['session_result_message'] = "Please enter all fields before submitting the new fee<BR>";

	} else {
		
		if ($new_initial_fee == "0" && $new_renewal_fee == "0") {

			$sql = "DELETE FROM ssl_fees
					WHERE ssl_provider_id = '$new_sslpid'
					  AND type_id = '$new_type_id'";
			mysql_query($sql,$connection);
			
			$sql = "UPDATE ssl_certs
					SET fee_id = '0',
						update_time = '$current_timestamp'
					WHERE ssl_provider_id = '$new_sslpid'
					  AND type_id = '$new_type_id'";
			mysql_query($sql,$connection);

			$sql = "SELECT type
					FROM ssl_cert_types
					WHERE id = '$new_type_id'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) {
					$temp_type = $row->type;
			}

			$_SESSION['session_result_message'] = "The fee for <font class=\"highlight\">$temp_type</font> has been deleted<BR>";

			header("Location: ssl-provider.php?sslpid=$new_sslpid");
			exit;

		}
		
		$sql = "SELECT *
				FROM ssl_fees
				WHERE ssl_provider_id = '$new_sslpid'
				  AND type_id = '$new_type_id'";
		$result = mysql_query($sql,$connection);

		if (mysql_num_rows($result) > 0) {
			
			$sql = "UPDATE ssl_fees
					SET initial_fee = '$new_initial_fee',
						renewal_fee = '$new_renewal_fee',
						currency_id = '$new_currency_id',
						update_time = '$current_timestamp'
					WHERE ssl_provider_id = '$new_sslpid'
					  AND type_id = '$new_type_id'";
			mysql_query($sql,$connection);

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
			$result = mysql_query($sql,$connection);
	
			$sslpid = $new_sslpid;
			
			$sql = "SELECT type
					FROM ssl_cert_types
					WHERE id = '$new_type_id'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) {
					$temp_type = $row->type;
			}

			$_SESSION['session_result_message'] = "The fee for <font class=\"highlight\">$temp_type</font> has been updated<BR>";

		} else {
			
			$sql = "INSERT INTO ssl_fees
					(ssl_provider_id, type_id, initial_fee, renewal_fee, currency_id, insert_time) VALUES 
					('$new_sslpid', '$new_type_id', '$new_initial_fee', '$new_renewal_fee', '$new_currency_id', '$current_timestamp')";
			$result = mysql_query($sql,$connection);

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
			$result = mysql_query($sql,$connection);

			$sql = "SELECT type
					FROM ssl_cert_types
					WHERE id = '$new_type_id'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) {
					$temp_type = $row->type;
			}

			$_SESSION['session_result_message'] = "The fee for <font class=\"highlight\">$temp_type</font> has been submitted<BR>";
	
		}

	}

}

include("../_includes/system/check-for-missing-ssl-fees.inc.php");

$sql = "SELECT name, url, notes, default_provider
		FROM ssl_providers
		WHERE id = '$sslpid'";
$result = mysql_query($sql,$connection);

while ($row = mysql_fetch_object($result)) { 

	$new_ssl_provider = $row->name;
	$new_url = $row->url;
	$new_notes = $row->notes;
	$new_default_provider = $row->default_provider;

}
if ($del == "1") {

	$sql = "SELECT ssl_provider_id
			FROM ssl_accounts
			WHERE ssl_provider_id = '$sslpid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_ssl_accounts = 1;
	}

	$sql = "SELECT ssl_provider_id
			FROM ssl_certs
			WHERE ssl_provider_id = '$sslpid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_ssl_certs = 1;
	}

	if ($existing_ssl_accounts > 0 || $existing_ssl_certs > 0) {
		
		if ($existing_ssl_accounts > 0) $_SESSION['session_result_message'] .= "This SSL Provider has SSL Accounts associated with it and cannot be deleted<BR>";
		if ($existing_ssl_certs > 0) $_SESSION['session_result_message'] .= "This SSL Provider has SSL Certificates associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['session_result_message'] = "Are you sure you want to delete this SSL Provider?<BR><BR><a href=\"$PHP_SELF?sslpid=$sslpid&really_del=1\">YES, REALLY DELETE THIS SSL PROVIDER</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM ssl_fees
			WHERE ssl_provider_id = '$sslpid'";
	$result = mysql_query($sql,$connection);

	$sql = "DELETE FROM ssl_accounts
			WHERE ssl_provider_id = '$sslpid'";
	$result = mysql_query($sql,$connection);

	$sql = "DELETE FROM ssl_providers
			WHERE id = '$sslpid'";
	$result = mysql_query($sql,$connection);

	$_SESSION['session_result_message'] = "SSL Provider <font class=\"highlight\">$new_ssl_provider</font> Deleted<BR>";

	include("../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../ssl-providers.php");
	exit;

}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="edit_ssl_provider_form" method="post" action="<?=$PHP_SELF?>">
<strong>SSL Provider Name:</strong><BR><BR>
<input name="new_ssl_provider" type="text" value="<?php if ($new_ssl_provider != "") echo $new_ssl_provider; ?>" size="50" maxlength="255">
<BR><BR>
<strong>SSL Provider's URL:</strong><BR><BR>
<input name="new_url" type="text" value="<?php if ($new_url != "") echo $new_url; ?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default SSL Provider?:</strong>&nbsp;
<input name="new_default_provider" type="checkbox" value="1"<?php if ($new_default_provider == "1") echo " checked"; ?>>
<BR><BR><BR>
<input type="hidden" name="new_sslpid" value="<?=$sslpid?>">
<input type="hidden" name="IS_SUBMITTED_SSL_PROVIDER" value="1">
<input type="submit" name="button" value="Update This SSL Provider &raquo;">
</form>
<BR><BR>
<font class="headline">Active Types</font><BR><BR>
<?php
$sql = "SELECT sslcf.type AS full_tf_string
		FROM ssl_certs AS sslc, ssl_cert_types AS sslcf
		WHERE sslc.type_id = sslcf.id
		  AND sslc.ssl_provider_id = '$sslpid'
		GROUP BY full_tf_string
		ORDER BY full_tf_string asc";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	$temp_full_ft_string = $temp_full_ft_string .= "$row->full_tf_string / ";
}
	$all_types = substr($temp_full_ft_string, 0, -2); 
?>
<?=$all_types?>
<BR><BR><BR><BR>
<?php
$sql = "SELECT sslcf.type AS full_tf_string
		FROM ssl_certs AS sslc, ssl_cert_types AS sslcf
		WHERE sslc.type_id = sslcf.id
		  AND sslc.ssl_provider_id = '$sslpid'
		  AND sslc.fee_id = '0'
		GROUP BY full_tf_string
		ORDER BY full_tf_string asc";
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
    <strong><font class="highlight">*</font> Please update the fees below in order to ensure proper SSL accounting.</strong>
    <BR><BR><BR>
<?php
}
?>
<font class="headline">Add/Update Fee</font><BR><BR>
<form name="edit_ssl_provider_fee_form" method="post" action="<?=$PHP_SELF?>">
<table border="0" cellspacing="0" cellpadding="0">
	<tr>
    	<td width="280" valign="top">
        	<strong>Type</strong><BR><BR>
		  <select name="new_type_id">
		  	<?php
			$sql = "SELECT id, type
					FROM ssl_cert_types
					WHERE active = '1'
					ORDER BY type asc";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) {
			?>
			    <option value="<?=$row->id?>"><?=$row->type?></option>
			<?php
			}
			?>
	      </select>
		</td>
		<td width="120" valign="top">
        	<strong>Initial Fee</strong><BR><BR>
	    	<input name="new_initial_fee" type="text" value="" size="10">
        </td>
		<td width="120" valign="top">
        	<strong>Renewal Fee</strong><BR><BR>
	    	<input name="new_renewal_fee" type="text" value="" size="10">
		</td>
	  	<td valign="top">
        	<strong>Currency</strong><BR><BR>
		  <select name="new_currency_id">
		  	<?php
			$sql = "SELECT id, currency, name 
					FROM currencies
					WHERE active = '1'
					ORDER BY currency asc";
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
    	<td height="18" width="250" valign="top"><strong>Type</strong></td>
        <td width="100"><strong>Initial Fee</strong></td>
        <td width="100"><strong>Renewal Fee</strong></td>
        <td width="100"><strong>Currency</strong></td>
	</tr>
<?php
$sql = "SELECT f.initial_fee, f.renewal_fee, c.currency, sslcf.type
		FROM ssl_fees AS f, currencies AS c, ssl_cert_types AS sslcf
		WHERE f.currency_id = c.id
		  AND f.type_id = sslcf.id
		  AND f.ssl_provider_id = '$sslpid'
		  AND c.active = '1'
		ORDER BY sslcf.type asc";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
?>
	<tr>
    	<td height="18" valign="middle"><?=$row->type?>&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td><?=$row->initial_fee?></td>
        <td><?=$row->renewal_fee?></td>
        <td><?=$row->currency?></td>
	</tr>
<?php
}
?>
</table>
<BR><BR><a href="<?=$PHP_SELF?>?sslpid=<?=$sslpid?>&del=1">DELETE THIS SSL PROVIDER</a>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>