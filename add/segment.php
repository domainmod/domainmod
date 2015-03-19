<?php
// /add/segment.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/system/functions/check-domain-format.inc.php");

$page_title = "Adding A New Segment";
$software_section = "segment-add";

// Form Variables
$new_name = $_POST['new_name'];
$new_description = $_POST['new_description'];
$new_segment = $_POST['new_segment'];
$new_notes = $_POST['new_notes'];

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
			$new_segment_formatted = mysqli_real_escape_string($connection, $new_segment_formatted);
	
			$sql = "INSERT into segments
					(name, description, segment, number_of_domains, notes, insert_time) VALUES 
					('" . mysqli_real_escape_string($connection, $new_name) . "', '" . mysqli_real_escape_string($connection, $new_description) . "', '" . $new_segment_formatted . "', '" . $number_of_domains . "', '" . mysqli_real_escape_string($connection, $new_notes) . "', '" . $current_timestamp . "')";
			$result = mysqli_query($connection, $sql) or die(mysqli_error());
			
			$sql = "SELECT id
					FROM segments
					WHERE name = '" . $new_name . "'
					  AND segment = '" . $new_segment_formatted . "'
					  AND insert_time = '" . $current_timestamp . "'";
			$result = mysqli_query($connection, $sql);
			while ($row = mysqli_fetch_object($result)) { $temp_segment_id = $row->id; }
			
			$sql = "DELETE FROM segment_data
					WHERE segment_id = '" . $temp_segment_id . "'";
			$result = mysqli_query($connection, $sql) or die(mysqli_error());
	
			foreach ($lines as $domain) {
	
				$sql = "INSERT INTO segment_data
						(segment_id, domain, insert_time) VALUES 
						('" . $temp_segment_id . "', '" . $domain . "', '" . $current_timestamp . "');";
				$result = mysqli_query($connection, $sql) or die(mysqli_error());
	
			}
	
			$_SESSION['result_message'] = "Segment <font class=\"highlight\">$new_name</font> Added<BR>";
	
			include("../_includes/system/update-segments.inc.php");
	
			header("Location: ../segments.php");
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
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/layout/header.inc.php"); ?>
<form name="add_segment_form" method="post" action="<?php echo $PHP_SELF; ?>">
<strong>Segment Name (35)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_name" type="text" value="<?php echo $new_name; ?>" size="25" maxlength="35">
<BR><BR>
<strong>Segment Domains (one per line)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<textarea name="new_segment" cols="60" rows="5"><?php echo $new_segment; ?></textarea>
<BR><BR>
<strong>Description</strong><BR><BR>
<textarea name="new_description" cols="60" rows="5"><?php echo $new_description; ?></textarea>
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<BR><BR>
<input type="submit" name="button" value="Add This Segment &raquo;">
</form>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>
