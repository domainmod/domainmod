<?php
/**
 * /assets/edit/dns.php
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
require_once(DIR_INC . "functions.inc.php");
include(DIR_INC . "timestamps/current-timestamp.inc.php");

spl_autoload_register('classAutoloader');

$error = new DomainMOD\Error();

$page_title = "Editing A DNS Profile";
$software_section = "dns-edit";

// 'Delete DNS Profile' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$dnsid = $_GET['dnsid'];

// Form Variables
$new_name = $_POST['new_name'];
$new_dns1 = $_POST['new_dns1'];
$new_dns2 = $_POST['new_dns2'];
$new_dns3 = $_POST['new_dns3'];
$new_dns4 = $_POST['new_dns4'];
$new_dns5 = $_POST['new_dns5'];
$new_dns6 = $_POST['new_dns6'];
$new_dns7 = $_POST['new_dns7'];
$new_dns8 = $_POST['new_dns8'];
$new_dns9 = $_POST['new_dns9'];
$new_dns10 = $_POST['new_dns10'];
$new_ip1 = $_POST['new_ip1'];
$new_ip2 = $_POST['new_ip2'];
$new_ip3 = $_POST['new_ip3'];
$new_ip4 = $_POST['new_ip4'];
$new_ip5 = $_POST['new_ip5'];
$new_ip6 = $_POST['new_ip6'];
$new_ip7 = $_POST['new_ip7'];
$new_ip8 = $_POST['new_ip8'];
$new_ip9 = $_POST['new_ip9'];
$new_ip10 = $_POST['new_ip10'];
$new_dnsid = $_POST['new_dnsid'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_name != "" && $new_dns1 != "" && $new_dns2 != "") {

		$new_number_of_servers = 10;
		
		if ($new_dns10 == '') { $new_number_of_servers = '9'; }
		if ($new_dns9 == '') { $new_number_of_servers = '8'; }
		if ($new_dns8 == '') { $new_number_of_servers = '7'; }
		if ($new_dns7 == '') { $new_number_of_servers = '6'; }
		if ($new_dns6 == '') { $new_number_of_servers = '5'; }
		if ($new_dns5 == '') { $new_number_of_servers = '4'; }
		if ($new_dns4 == '') { $new_number_of_servers = '3'; }
		if ($new_dns3 == '') { $new_number_of_servers = '2'; }
		if ($new_dns2 == '') { $new_number_of_servers = '1'; }
		if ($new_dns1 == '') { $new_number_of_servers = '0'; }

		$sql_update = "UPDATE dns
					   SET name = '" . mysqli_real_escape_string($connection, $new_name) . "',
					   	   dns1 = '" . mysqli_real_escape_string($connection, $new_dns1) . "',
						   dns2 = '" . mysqli_real_escape_string($connection, $new_dns2) . "',
						   dns3 = '" . mysqli_real_escape_string($connection, $new_dns3) . "',
						   dns4 = '" . mysqli_real_escape_string($connection, $new_dns4) . "',
						   dns5 = '" . mysqli_real_escape_string($connection, $new_dns5) . "',
						   dns6 = '" . mysqli_real_escape_string($connection, $new_dns6) . "',
						   dns7 = '" . mysqli_real_escape_string($connection, $new_dns7) . "',
						   dns8 = '" . mysqli_real_escape_string($connection, $new_dns8) . "',
						   dns9 = '" . mysqli_real_escape_string($connection, $new_dns9) . "',
						   dns10 = '" . mysqli_real_escape_string($connection, $new_dns10) . "',
						   ip1 = '" . mysqli_real_escape_string($connection, $new_ip1) . "',
						   ip2 = '" . mysqli_real_escape_string($connection, $new_ip2) . "',
						   ip3 = '" . mysqli_real_escape_string($connection, $new_ip3) . "',
						   ip4 = '" . mysqli_real_escape_string($connection, $new_ip4) . "',
						   ip5 = '" . mysqli_real_escape_string($connection, $new_ip5) . "',
						   ip6 = '" . mysqli_real_escape_string($connection, $new_ip6) . "',
						   ip7 = '" . mysqli_real_escape_string($connection, $new_ip7) . "',
						   ip8 = '" . mysqli_real_escape_string($connection, $new_ip8) . "',
						   ip9 = '" . mysqli_real_escape_string($connection, $new_ip9) . "',
						   ip10 = '" . mysqli_real_escape_string($connection, $new_ip10) . "',
						   notes = '" . mysqli_real_escape_string($connection, $new_notes) . "',
						   number_of_servers = '" . $new_number_of_servers . "',
						   update_time = '" . $current_timestamp . "'
					   WHERE id = '" . $new_dnsid . "'";
		$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

		$new_name = $new_name;
		$new_dns1 = $new_dns1;
		$new_notes = $new_notes;
		$new_number_of_servers = $new_number_of_servers;
		
		$dnsid = $new_dnsid;
		
		$_SESSION['result_message'] = "DNS Profile <font class=\"highlight\">$new_name</font> Updated<BR>";

		header("Location: ../dns.php");
		exit;

	} else {
	
		if ($new_name == "") $_SESSION['result_message'] .= "Please enter a name for the DNS profile<BR>";
		if ($new_dns1 == "") $_SESSION['result_message'] .= "Please enter the first DNS server<BR>";
		if ($new_dns2 == "") $_SESSION['result_message'] .= "Please enter the second DNS server<BR>";

	}

} else {

	$sql = "SELECT name, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3, ip4, ip5, ip6, ip7, ip8, ip9, ip10, notes
			FROM dns
			WHERE id = '" . $dnsid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) { 
	
		$new_name = $row->name;
		$new_dns1 = $row->dns1;
		$new_dns2 = $row->dns2;
		$new_dns3 = $row->dns3;
		$new_dns4 = $row->dns4;
		$new_dns5 = $row->dns5;
		$new_dns6 = $row->dns6;
		$new_dns7 = $row->dns7;
		$new_dns8 = $row->dns8;
		$new_dns9 = $row->dns9;
		$new_dns10 = $row->dns10;
		$new_ip1 = $row->ip1;
		$new_ip2 = $row->ip2;
		$new_ip3 = $row->ip3;
		$new_ip4 = $row->ip4;
		$new_ip5 = $row->ip5;
		$new_ip6 = $row->ip6;
		$new_ip7 = $row->ip7;
		$new_ip8 = $row->ip8;
		$new_ip9 = $row->ip9;
		$new_ip10 = $row->ip10;
		$new_notes = $row->notes;
	
	}

}
if ($del == "1") {

	$sql = "SELECT dns_id
			FROM domains
			WHERE dns_id = '" . $dnsid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_domains = 1;
	}
	
	if ($existing_domains > 0) {

		$_SESSION['result_message'] = "This DNS Profile has domains associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this DNS Profile?<BR><BR><a href=\"dns.php?dnsid=$dnsid&really_del=1\">YES, REALLY DELETE THIS DNS PROFILE</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM dns 
			WHERE id = '" . $dnsid . "'";
	$result = mysqli_query($connection, $sql);
	
	$_SESSION['result_message'] = "DNS Profile <font class=\"highlight\">$new_name</font> Deleted<BR>";
	
	header("Location: ../dns.php");
	exit;

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="edit_dns_form" method="post">
<strong>Profile Name</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?php if ($new_name != "") echo htmlentities($new_name); ?>">
<BR><BR>
<table class="dns_table">
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 1</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
            <input name="new_dns1" type="text" size="28" maxlength="255" value="<?php if ($new_dns1 != "") echo $new_dns1; ?>">
		</td>
        <td class="dns_table_right">
            <strong>IP Address 1</strong><BR><BR>
        	<input name="new_ip1" type="text" size="28" maxlength="255" value="<?php if ($new_ip1 != "") echo $new_ip1; ?>">
		</td>
    </tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 2</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
            <input name="new_dns2" type="text" size="28" maxlength="255" value="<?php if ($new_dns2 != "") echo $new_dns2; ?>">
		</td>
        <td class="dns_table_right">
            <strong>IP Address 2</strong><BR><BR>
        	<input name="new_ip2" type="text" size="28" maxlength="255" value="<?php if ($new_ip2 != "") echo $new_ip2; ?>">
		</td>
    </tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 3</strong><BR><BR>
            <input name="new_dns3" type="text" size="28" maxlength="255" value="<?php if ($new_dns3 != "") echo $new_dns3; ?>">
		</td>
        <td class="dns_table_right">
            <strong>IP Address 3</strong><BR><BR>
        	<input name="new_ip3" type="text" size="28" maxlength="255" value="<?php if ($new_ip3 != "") echo $new_ip3; ?>">
		</td>
    </tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 4</strong><BR><BR>
            <input name="new_dns4" type="text" size="28" maxlength="255" value="<?php if ($new_dns4 != "") echo $new_dns4; ?>">
		</td>
        <td class="dns_table_right">
            <strong>IP Address 4</strong><BR><BR>
        	<input name="new_ip4" type="text" size="28" maxlength="255" value="<?php if ($new_ip4 != "") echo $new_ip4; ?>">
		</td>
    </tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 5</strong><BR><BR>
            <input name="new_dns5" type="text" size="28" maxlength="255" value="<?php if ($new_dns5 != "") echo $new_dns5; ?>">
		</td>
        <td class="dns_table_right">
            <strong>IP Address 5</strong><BR><BR>
        	<input name="new_ip5" type="text" size="28" maxlength="255" value="<?php if ($new_ip5 != "") echo $new_ip5; ?>">
		</td>
    </tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 6</strong><BR><BR>
            <input name="new_dns6" type="text" size="28" maxlength="255" value="<?php if ($new_dns6 != "") echo $new_dns6; ?>">
		</td>
        <td class="dns_table_right">
            <strong>IP Address 6</strong><BR><BR>
        	<input name="new_ip6" type="text" size="28" maxlength="255" value="<?php if ($new_ip6 != "") echo $new_ip6; ?>">
		</td>
    </tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 7</strong><BR><BR>
            <input name="new_dns7" type="text" size="28" maxlength="255" value="<?php if ($new_dns7 != "") echo $new_dns7; ?>">
		</td>
        <td class="dns_table_right">
            <strong>IP Address 7</strong><BR><BR>
        	<input name="new_ip7" type="text" size="28" maxlength="255" value="<?php if ($new_ip7 != "") echo $new_ip7; ?>">
		</td>
    </tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 8</strong><BR><BR>
            <input name="new_dns8" type="text" size="28" maxlength="255" value="<?php if ($new_dns8 != "") echo $new_dns8; ?>">
		</td>
        <td class="dns_table_right">
            <strong>IP Address 8</strong><BR><BR>
        	<input name="new_ip8" type="text" size="28" maxlength="255" value="<?php if ($new_ip8 != "") echo $new_ip8; ?>">
		</td>
    </tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 9</strong><BR><BR>
            <input name="new_dns9" type="text" size="28" maxlength="255" value="<?php if ($new_dns9 != "") echo $new_dns9; ?>">
		</td>
        <td class="dns_table_right">
            <strong>IP Address 9</strong><BR><BR>
        	<input name="new_ip9" type="text" size="28" maxlength="255" value="<?php if ($new_ip9 != "") echo $new_ip9; ?>">
		</td>
    </tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 10</strong><BR><BR>
            <input name="new_dns10" type="text" size="28" maxlength="255" value="<?php if ($new_dns10 != "") echo $new_dns10; ?>">
		</td>
        <td class="dns_table_right">
            <strong>IP Address 10</strong><BR><BR>
        	<input name="new_ip10" type="text" size="28" maxlength="255" value="<?php if ($new_ip10 != "") echo $new_ip10; ?>">
		</td>
    </tr>
</table>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_dnsid" value="<?php echo $dnsid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This DNS Profile &raquo;">
</form>
<BR><BR><a href="dns.php?dnsid=<?php echo $dnsid; ?>&del=1">DELETE THIS DNS PROFILE</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
