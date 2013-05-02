<?php
// /assets/edit/ip-address.php
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
include("../../_includes/start-session.inc.php");
include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Editting An IP Address";
$software_section = "ip-addresses";

// 'Delete IP Address' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$ipid = $_GET['ipid'];

// Form Variables
$new_name = $_POST['new_name'];
$new_ip = $_POST['new_ip'];
$new_rdns = $_POST['new_rdns'];
$new_ipid = $_POST['new_ipid'];
$new_notes = $_POST['new_notes'];
$new_default_ip_address = $_REQUEST['new_default_ip_address'];

if ($_SESSION['http_referer_set'] != "1") {
	$_SESSION['http_referer'] = $_SERVER['HTTP_REFERER'];
	$_SESSION['http_referer_set'] = "1";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_name != "" && $new_ip != "") {

		if ($new_default_ip_address == "1") {
			
			$sql = "UPDATE ip_addresses
					SET default_ip_address = '0',
					    update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);

		} else { 
		
			$sql = "SELECT default_ip_address
					FROM ip_addresses
					WHERE default_ip_address = '1'
					  AND id != '$new_ipid'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_default_ip = $row->default_ip_address; }
			if ($temp_default_ip == "") { $new_default_ip_address = "1"; }

		}

		$sql_update = "UPDATE ip_addresses
					   SET name = '" . mysql_real_escape_string($new_name) . "',
					   	   ip = '" . mysql_real_escape_string($new_ip) . "',
						   rdns = '" . mysql_real_escape_string($new_rdns) . "',
						   notes = '" . mysql_real_escape_string($new_notes) . "',
						   default_ip_address = '" . $new_default_ip_address . "',
						   update_time = '$current_timestamp'
					   WHERE id = '$new_ipid'";
		$result_update = mysql_query($sql_update,$connection) or die(mysql_error());

		$new_name = $new_name;
		$new_ip = $new_ip;
		$new_rdns = $new_rdns;
		$new_notes = $new_notes;
		$new_default_ip_address = $new_default_ip_address;
		
		$ipid = $new_ipid;
		
		$_SESSION['result_message'] = "IP Address <font class=\"highlight\">$new_name ($new_ip)</font> Updated<BR>";

		$_SESSION['http_referer_set'] = "";
		header("Location: " . $_SESSION['http_referer']);
		exit;

	} else {
	
		if ($new_name == "") $_SESSION['result_message'] .= "Please enter a name for the IP Address<BR>";
		if ($new_ip == "") $_SESSION['result_message'] .= "Please enter the IP Address<BR>";

	}

} else {

	$sql = "SELECT name, ip, rdns, notes, default_ip_address
			FROM ip_addresses
			WHERE id = '$ipid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_name = $row->name;
		$new_ip = $row->ip;
		$new_rdns = $row->rdns;
		$new_notes = $row->notes;
		$new_default_ip_address = $row->default_ip_address;
	
	}

}
if ($del == "1") {

	$sql = "SELECT ip_id
			FROM domains
			WHERE ip_id = '$ipid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_domains = 1;
	}
	
	if ($existing_domains > 0) {

		$_SESSION['result_message'] = "This IP Address has domains associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this IP Address?<BR><BR><a href=\"$PHP_SELF?ipid=$ipid&really_del=1\">YES, REALLY DELETE THIS IP ADDRESS</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM ip_addresses 
			WHERE id = '$ipid'";
	$result = mysql_query($sql,$connection);
	
	$_SESSION['result_message'] = "IP Address <font class=\"highlight\">$new_name ($new_ip)</font> Deleted<BR>";
	
	header("Location: ../ip-addresses.php");
	exit;

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/header.inc.php"); ?>
<form name="edit_ip_address_form" method="post" action="<?=$PHP_SELF?>">
<strong>IP Address Name</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?php if ($new_name != "") echo $new_name; ?>">
<BR><BR>
<strong>IP Address</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_ip" type="text" size="50" maxlength="255" value="<?php if ($new_ip != "") echo $new_ip; ?>">
<BR><BR>
<strong>rDNS</strong><BR><BR>
<input name="new_rdns" type="text" size="50" maxlength="255" value="<?php if ($new_rdns != "") echo $new_rdns; ?>">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default IP Address?</strong>&nbsp;
<input name="new_default_ip_address" type="checkbox" value="1"<?php if ($new_default_ip_address == "1") echo " checked"; ?>>
<BR><BR><BR>
<input type="hidden" name="new_ipid" value="<?=$ipid?>">
<input type="submit" name="button" value="Update This IP Address &raquo;">
</form>
<BR><BR><a href="<?=$PHP_SELF?>?ipid=<?=$ipid?>&del=1">DELETE THIS IP ADDRESS</a>
<?php include("../../_includes/footer.inc.php"); ?>
</body>
</html>