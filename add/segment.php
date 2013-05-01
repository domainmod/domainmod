<?php
// /add/segment.php
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
include("../_includes/system/functions/check-domain-format.inc.php");

$page_title = "Adding A New Segment";
$software_section = "segments";

// Form Variables
$new_name = $_POST['new_name'];
$new_description = $_POST['new_description'];
$new_segment = $_POST['new_segment'];
$new_notes = $_POST['new_notes'];

if ($_SESSION['http_referer_set'] != "1") {
	$_SESSION['http_referer'] = $_SERVER['HTTP_REFERER'];
	$_SESSION['http_referer_set'] = "1";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	$temp_input_string = $new_segment;
	include("../_includes/system/regex-bulk-form-strip-whitespace.inc.php");
	$new_segment = $temp_output_string;

	if ($new_name != "" && $new_segment != "") {

		$lines = explode("\r\n", $new_segment);
		$invalid_domain_count = 0;
		$invalid_domains_to_display = 5;
		
		while (list($key, $new_domain) = each($lines)) {
	
			if (!CheckDomainFormat($new_domain)) {
				if ($invalid_domain_count < $invalid_domains_to_display) $temp_result_message .= "Line " . number_format($key + 1) . " contains an invalid domain<BR>";
				$invalid_domains = 1;
				$invalid_domain_count++;
			}
	
		}
		
		if ($new_segment == "" || $invalid_domains == 1) { 
		
			if ($invalid_domains == 1) {
	
				if ($invalid_domain_count == 1) {
	
					$_SESSION['result_message'] = "There is " . number_format($invalid_domain_count) . " invalid domain on your list<BR><BR>" . $temp_result_message;
	
				} else {
	
					$_SESSION['result_message'] = "There are " . number_format($invalid_domain_count) . " invalid domains on your list<BR><BR>" . $temp_result_message;
	
					if (($invalid_domain_count-$invalid_domains_to_display) == 1) { 
	
						$_SESSION['result_message'] .= "<BR>Plus " . number_format($invalid_domain_count-$invalid_domains_to_display) . " other<BR>";
	
					} elseif (($invalid_domain_count-$invalid_domains_to_display) > 1) { 
	
						$_SESSION['result_message'] .= "<BR>Plus " . number_format($invalid_domain_count-$invalid_domains_to_display) . " others<BR>";
					}
	
				}
	
			}
			$submission_failed = 1;
	
		} else {

			$lines = explode("\r\n", $new_segment);
			$number_of_domains = count($lines);
			
			while (list($key, $new_domain) = each($lines)) {
	
				if (!CheckDomainFormat($new_domain)) {
					echo "invalid domain $key"; exit;
				}
	
			}

			$new_segment_formatted = "'" . $new_segment;
			$new_segment_formatted = $new_segment_formatted . "'";
			$new_segment_formatted = preg_replace("/\r\n/", "','", $new_segment_formatted);
			$new_segment_formatted = str_replace (" ", "", $new_segment_formatted);
			$new_segment_formatted = trim($new_segment_formatted);
			$new_segment_formatted = mysql_real_escape_string($new_segment_formatted);
	
			$sql = "INSERT into segments
					(name, description, segment, number_of_domains, notes, insert_time) VALUES 
					('" . mysql_real_escape_string($new_name) . "', '" . mysql_real_escape_string($new_description) . "', '$new_segment_formatted', '$number_of_domains', '" . mysql_real_escape_string($new_notes) . "', '$current_timestamp')";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$sql = "SELECT id
					FROM segments
					WHERE name = '$new_name'
					  AND segment = '$new_segment_formatted'
					  AND insert_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_segment_id = $row->id; }
			
			$sql = "DELETE FROM segment_data
					WHERE segment_id = '$temp_segment_id'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
	
			foreach ($lines as $domain) {
	
				$sql = "INSERT INTO segment_data
						(segment_id, domain, insert_time) VALUES 
						('$temp_segment_id', '$domain', '$current_timestamp');";
				$result = mysql_query($sql,$connection) or die(mysql_error());
	
			}
	
			$_SESSION['result_message'] = "Segment <font class=\"highlight\">$new_name</font> Added<BR>";
	
			include("../_includes/system/update-segments.inc.php");
	
			$_SESSION['http_referer_set'] = "";
			header("Location: " . $_SESSION['http_referer']);
			exit;
			
		}

	} else {
	
		if ($new_name == "") { $_SESSION['result_message'] .= "Please enter the segment name<BR>"; }
		if ($new_segment == "") { $_SESSION['result_message'] .= "Please enter the segment<BR>"; }

	}

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/header.inc.php"); ?>
<form name="add_segment_form" method="post" action="<?=$PHP_SELF?>">
<strong>Segment Name</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_name" type="text" value="<?=$new_name?>" size="50" maxlength="255">
<BR><BR>
<strong>Segment Domains (one per line)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<textarea name="new_segment" cols="60" rows="5"><?=$new_segment?></textarea>
<BR><BR>
<strong>Description</strong><BR><BR>
<textarea name="new_description" cols="60" rows="5"><?=$new_description?></textarea>
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR><BR>
<input type="submit" name="button" value="Add This Segment &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>