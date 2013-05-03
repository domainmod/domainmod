<?php
// /assets/add/dns.php
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

$page_title = "Adding A New DNS Profile";
$software_section = "dns";

// Form Variables
$new_name = $_POST['new_name'];
$new_notes = $_POST['new_notes'];
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

if ($_SESSION['http_referer_set'] != "1") {
	$_SESSION['http_referer'] = $_SERVER['HTTP_REFERER'];
	$_SESSION['http_referer_set'] = "1";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_name != '' && $new_dns1 != "" && $new_dns2 != "") {

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

		$sql = "INSERT INTO dns 
				(name, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3, ip4, ip5, ip6, ip7, ip8, ip9, ip10, notes, number_of_servers, insert_time) VALUES 
				('" . mysql_real_escape_string($new_name) . "', '" . mysql_real_escape_string($new_dns1) . "', '" . mysql_real_escape_string($new_dns2) . "', '" . mysql_real_escape_string($new_dns3) . "', '" . mysql_real_escape_string($new_dns4) . "', '" . mysql_real_escape_string($new_dns5) . "', '" . mysql_real_escape_string($new_dns6) . "', '" . mysql_real_escape_string($new_dns7) . "', '" . mysql_real_escape_string($new_dns8) . "', '" . mysql_real_escape_string($new_dns9) . "', '" . mysql_real_escape_string($new_dns10) . "', '" . mysql_real_escape_string($new_ip1) . "', '" . mysql_real_escape_string($new_ip2) . "', '" . mysql_real_escape_string($new_ip3) . "', '" . mysql_real_escape_string($new_ip4) . "', '" . mysql_real_escape_string($new_ip5) . "', '" . mysql_real_escape_string($new_ip6) . "', '" . mysql_real_escape_string($new_ip7) . "', '" . mysql_real_escape_string($new_ip8) . "', '" . mysql_real_escape_string($new_ip9) . "', '" . mysql_real_escape_string($new_ip10) . "', '" . mysql_real_escape_string($new_notes) . "', '$new_number_of_servers', '$current_timestamp')";

		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['result_message'] = "DNS Profile <font class=\"highlight\">$new_name</font> Added<BR>";

		$_SESSION['http_referer_set'] = "";
		header("Location: " . $_SESSION['http_referer']);
		exit;
		
	} else {
	
		if ($new_name == "") $_SESSION['result_message'] .= "Please enter a name for the DNS profile<BR>";
		if ($new_dns1 == "" || $new_dns2 == "") $_SESSION['result_message'] .= "Please enter at least two DNS servers<BR>";

	}

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../../_includes/header.inc.php"); ?>
<form name="add_dns_form" method="post" action="<?=$PHP_SELF?>">
<strong>Profile Name</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?=$new_name?>">
<BR><BR>
<table class="dns_table">
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 1</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
            <input name="new_dns1" type="text" size="28" maxlength="255" value="<?=$new_dns1?>">
		</td>
    	<td class="dns_table_right">
            <strong>IP Address 1</strong><BR><BR>
            <input name="new_ip1" type="text" size="28" maxlength="255" value="<?=$new_ip1?>">
		</td>
	</tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 2</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
            <input name="new_dns2" type="text" size="28" maxlength="255" value="<?=$new_dns2?>">
		</td>
    	<td class="dns_table_right">
            <strong>IP Address 2</strong><BR><BR>
            <input name="new_ip2" type="text" size="28" maxlength="255" value="<?=$new_ip2?>">
		</td>
	</tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 3</strong><BR><BR>
            <input name="new_dns3" type="text" size="28" maxlength="255" value="<?=$new_dns3?>">
		</td>
    	<td class="dns_table_right">
            <strong>IP Address 3</strong><BR><BR>
            <input name="new_ip3" type="text" size="28" maxlength="255" value="<?=$new_ip3?>">
		</td>
	</tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 4</strong><BR><BR>
            <input name="new_dns4" type="text" size="28" maxlength="255" value="<?=$new_dns4?>">
		</td>
    	<td class="dns_table_right">
            <strong>IP Address 4</strong><BR><BR>
            <input name="new_ip4" type="text" size="28" maxlength="255" value="<?=$new_ip4?>">
		</td>
	</tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 5</strong><BR><BR>
            <input name="new_dns5" type="text" size="28" maxlength="255" value="<?=$new_dns5?>">
		</td>
    	<td class="dns_table_right">
            <strong>IP Address 5</strong><BR><BR>
            <input name="new_ip5" type="text" size="28" maxlength="255" value="<?=$new_ip5?>">
		</td>
	</tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 6</strong><BR><BR>
            <input name="new_dns6" type="text" size="28" maxlength="255" value="<?=$new_dns6?>">
		</td>
    	<td class="dns_table_right">
            <strong>IP Address 6</strong><BR><BR>
            <input name="new_ip6" type="text" size="28" maxlength="255" value="<?=$new_ip6?>">
		</td>
	</tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 7</strong><BR><BR>
            <input name="new_dns7" type="text" size="28" maxlength="255" value="<?=$new_dns7?>">
		</td>
    	<td class="dns_table_right">
            <strong>IP Address 7</strong><BR><BR>
            <input name="new_ip7" type="text" size="28" maxlength="255" value="<?=$new_ip7?>">
		</td>
	</tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 8</strong><BR><BR>
            <input name="new_dns8" type="text" size="28" maxlength="255" value="<?=$new_dns8?>">
		</td>
    	<td class="dns_table_right">
            <strong>IP Address 8</strong><BR><BR>
            <input name="new_ip8" type="text" size="28" maxlength="255" value="<?=$new_ip8?>">
		</td>
	</tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 9</strong><BR><BR>
            <input name="new_dns9" type="text" size="28" maxlength="255" value="<?=$new_dns9?>">
		</td>
    	<td class="dns_table_right">
            <strong>IP Address 9</strong><BR><BR>
            <input name="new_ip9" type="text" size="28" maxlength="255" value="<?=$new_ip9?>">
		</td>
	</tr>
	<tr>
    	<td class="dns_table_left">
            <strong>DNS Server 10</strong><BR><BR>
            <input name="new_dns10" type="text" size="28" maxlength="255" value="<?=$new_dns10?>">
		</td>
    	<td class="dns_table_right">
            <strong>IP Address 10</strong><BR><BR>
            <input name="new_ip10" type="text" size="28" maxlength="255" value="<?=$new_ip10?>">
		</td>
	</tr>
</table>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR><BR>
<input type="submit" name="button" value="Add This DNS Profile &raquo;">
</form>
<?php include("../../_includes/footer.inc.php"); ?>
</body>
</html>