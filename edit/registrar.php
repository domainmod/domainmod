<?php
// registrar.php
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

$page_title = "Editting A Registrar";
$software_section = "registrars";

// 'Delete Registrar' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$rid = $_GET['rid'];

// Form Variables
$new_registrar = $_POST['new_registrar'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];
$IS_SUBMITTED_REGISTRAR = $_POST['IS_SUBMITTED_REGISTRAR'];
$new_tld = $_POST['new_tld'];
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_currency_id = $_POST['new_currency_id'];
$IS_SUBMITTED_FEE = $_POST['IS_SUBMITTED_FEE'];
$new_rid = $_POST['new_rid'];

if ($IS_SUBMITTED_REGISTRAR == "1") {

	if ($new_registrar != "" && $new_url != "") {

		$sql = "UPDATE registrars
				SET name = '" . mysql_real_escape_string($new_registrar) . "', 
					url = '" . mysql_real_escape_string($new_url) . "', 
					notes = '" . mysql_real_escape_string($new_notes) . "',
					update_time = '$current_timestamp'
				WHERE id = '$new_rid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$rid = $new_rid;

		$_SESSION['session_result_message'] = "Registrar Updated<BR>";
		
	} else {

		if ($new_registrar == "") $_SESSION['session_result_message'] .= "Please Enter The Registrar's Name<BR>";
		if ($new_url == "") $_SESSION['session_result_message'] .= "Please Enter The Registrar's URL<BR>";

	}

	header("Location: registrar.php?rid=$new_rid");
	exit;

} elseif ($IS_SUBMITTED_FEE == "1") {

	if ($new_rid == "" || $new_tld == "" || $new_initial_fee == "" || $new_renewal_fee == "") { 

		$_SESSION['session_result_message'] = "Please Enter All Fields Before Submitting The New Fee<BR>";

	} else {
		
		$new_tld = trim($new_tld, ". \t\n\r\0\x0B");
		
		if ($new_initial_fee == "0" && $new_renewal_fee == "0") {

			$sql = "DELETE FROM fees
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'";
			mysql_query($sql,$connection);
			
			$sql = "UPDATE domains
					SET fee_id = '0',
						update_time = '$current_timestamp'
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'";
			mysql_query($sql,$connection);
			
			$_SESSION['session_result_message'] = "Fee Deleted<BR>";

			header("Location: registrar.php?rid=$new_rid");
			exit;
			
		}
		
		$sql = "SELECT *
				FROM fees
				WHERE registrar_id = '$new_rid'
				  AND tld = '$new_tld'";
		$result = mysql_query($sql,$connection);

		if (mysql_num_rows($result) > 0) {
			
			$sql = "UPDATE fees
					SET initial_fee = '$new_initial_fee',
						renewal_fee = '$new_renewal_fee',
						currency_id = '$new_currency_id',
						update_time = '$current_timestamp'
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'";
			mysql_query($sql,$connection);

			$sql = "SELECT id
					FROM fees
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'
					  AND currency_id = '$new_currency_id'
					LIMIT 1";
	
			$result = mysql_query($sql,$connection) or die(mysql_error());

			while ($row = mysql_fetch_object($result)) {
				$new_fee_id = $row->id;
			}

			$sql = "UPDATE domains
					SET fee_id = '$new_fee_id',
						update_time = '$current_timestamp'
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'";
			$result = mysql_query($sql,$connection);
	
			$rid = $new_rid;

			$_SESSION['session_result_message'] = "Fee Updated Successfully<BR>";
			
			header("Location: registrar.php?rid=$new_rid");
			exit;

		} else {
			
			$sql = "INSERT INTO fees 
					(registrar_id, tld, initial_fee, renewal_fee, currency_id, insert_time) VALUES 
					('$new_rid', '" . mysql_real_escape_string($new_tld) . "', '$new_initial_fee', '$new_renewal_fee', '$new_currency_id', '$current_timestamp')";
			$result = mysql_query($sql,$connection);

			$sql = "SELECT id
					FROM fees
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'
					  AND currency_id = '$new_currency_id'
					ORDER BY id desc
					LIMIT 1";
	
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			while ($row = mysql_fetch_object($result)) {
				$new_fee_id = $row->id;
			}

			$sql = "UPDATE domains
					SET fee_id = '$new_fee_id',
						update_time = '$current_timestamp'
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'";
			$result = mysql_query($sql,$connection);
	
			$_SESSION['session_result_message'] = "Fee Submitted Successfully<BR>";
			
			header("Location: registrar.php?rid=$new_rid");
			exit;

		}

	}

	header("Location: registrar.php?rid=$new_rid");
	exit;

}

include("../_includes/system/check-for-missing-domain-fees.inc.php");

$sql = "SELECT name, url, notes
		FROM registrars
		WHERE id = '$rid'";
$result = mysql_query($sql,$connection);

while ($row = mysql_fetch_object($result)) { 

	$new_registrar = $row->name;
	$new_url = $row->url;
	$new_notes = $row->notes;

}
if ($del == "1") {

	$sql = "SELECT registrar_id
			FROM registrar_accounts
			WHERE registrar_id = '$rid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_registrar_accounts = 1;
	}

	$sql = "SELECT registrar_id
			FROM domains
			WHERE registrar_id = '$rid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_domains = 1;
	}

	if ($existing_registrar_accounts > 0 || $existing_domains > 0) {
		
		if ($existing_registrar_accounts > 0) $_SESSION['session_result_message'] .= "This Registrar has Registrar Accounts associated with it and cannot be deleted.<BR>";
		if ($existing_domains > 0) $_SESSION['session_result_message'] .= "This Registrar has domains associated with it and cannot be deleted.<BR>";

	} else {

		$_SESSION['session_result_message'] = "Are you sure you want to delete this Registrar?<BR><BR><a href=\"$PHP_SELF?rid=$rid&really_del=1\">YES, REALLY DELETE THIS REGISTRAR</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM fees
			WHERE registrar_id = '$rid'";
	$result = mysql_query($sql,$connection);

	$sql = "DELETE FROM registrar_accounts
			WHERE registrar_id = '$rid'";
	$result = mysql_query($sql,$connection);

	$sql = "DELETE FROM registrars 
			WHERE id = '$rid'";
	$result = mysql_query($sql,$connection);

	$_SESSION['session_result_message'] = "Registrar Deleted ($new_registrar)<BR>";
	
	header("Location: ../registrars.php");
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
<form name="edit_registrar_form" method="post" action="<?=$PHP_SELF?>">
<strong>Registrar Name:</strong><BR><BR>
<input name="new_registrar" type="text" value="<?php if ($new_registrar != "") echo $new_registrar; ?>" size="50" maxlength="255">
<BR><BR>
<strong>Registrar's URL:</strong><BR><BR>
<input name="new_url" type="text" value="<?php if ($new_url != "") echo $new_url; ?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR><BR>
<input type="hidden" name="new_rid" value="<?=$rid?>">
<input type="hidden" name="IS_SUBMITTED_REGISTRAR" value="1">
<input type="submit" name="button" value="Update This Registrar &raquo;">
</form>
<BR><BR>
<font class="headline">Active TLDs</font><BR><BR>
<?php
$sql = "SELECT tld 
		FROM domains
		WHERE registrar_id = '$rid'
		  AND active not in ('0', '10')
		GROUP BY tld
		ORDER BY tld asc";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	$temp_all_tlds = $temp_all_tlds .= "$row->tld, ";
}
	$all_tlds = substr($temp_all_tlds, 0, -2); 
?>
<?=$all_tlds?>
<BR><BR><BR><BR>
<?php
$sql = "SELECT tld
		FROM domains
		WHERE registrar_id = '$rid'
		  AND fee_id = '0'
		GROUP BY tld
		ORDER BY tld asc";
$result = mysql_query($sql,$connection);
if (mysql_num_rows($result) > 0) {
?>
    <a name="missingfees"></a><font class="headline">Missing or Out Of Date TLD Fees</font><BR><BR>
    <?php
    while ($row = mysql_fetch_object($result)) {
        $temp_all_missing_fees = $temp_all_missing_fees .= "$row->tld, ";
    }
	$all_missing_fees = substr($temp_all_missing_fees, 0, -2); 
    ?>
    <?=$all_missing_fees?><BR><BR>
    <strong><font color="#DD0000">*</font> Please update the fees for these TLDs below in order to ensure proper domain accounting.</strong>
    <BR><BR><BR><BR>
<?php
}
?>
<font class="headline">Add/Update TLD Fee</font><BR><BR>
<form name="edit_registrar_fee_form" method="post" action="<?=$PHP_SELF?>">
<table border="0" cellspacing="0" cellpadding="0">
	<tr>
    	<td width="200" valign="top"><strong>TLD</strong><BR><BR>
   	    <input name="new_tld" type="text" value="" size="10"></td>
		<td width="200" valign="top"><strong>Initial Fee</strong><BR><BR>
	    <input name="new_initial_fee" type="text" value="" size="10"></td>
		<td width="200" valign="top"><strong>Renewal Fee</strong><BR><BR>
	    <input name="new_renewal_fee" type="text" value="" size="10"></td>
	  	<td valign="top"><strong>Currency</strong><BR><BR>
		  <select name="new_currency_id" id="new_currency">
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
<input type="hidden" name="new_rid" value="<?=$rid?>">
<input type="hidden" name="IS_SUBMITTED_FEE" value="1">
<BR><BR><input type="submit" name="button" value="Add/Update This TLD Fee &raquo;">
</form>
<BR><BR>
<font class="headline">TLD Fees</font><BR><BR>
<table border="0" cellspacing="0" cellpadding="0">
	<tr>
    	<td width="120" height="20" valign="top"><strong>TLD</strong></td>
        <td width="120"><strong>Initial Fee</strong></td>
        <td width="120"><strong>Renewal Fee</strong></td>
        <td><strong>Currency</strong></td>
	</tr>
<?php
$sql = "SELECT f.tld, f.initial_fee, f.renewal_fee, c.currency 
		FROM fees AS f, currencies AS c
		WHERE f.currency_id = c.id
		  AND f.registrar_id = '$rid'
		  AND c.active = '1'
		ORDER BY f.tld asc";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
?>
	<tr>
    	<td height="20" valign="middle">.<?=$row->tld?></td>
        <td><?php echo number_format($row->initial_fee, 2, '.', ','); ?></td>
        <td><?php echo number_format($row->renewal_fee, 2, '.', ','); ?></td>
        <td><?=$row->currency?></td>
	</tr>
<?php
}
?>
</table>
<BR><BR><a href="<?=$PHP_SELF?>?rid=<?=$rid?>&del=1">DELETE THIS REGISTRAR</a>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>