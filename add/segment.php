<?php
/**
 * /add/segment.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/classes/Domain.class.php");
include("../_includes/classes/Error.class.php");

$error = new DomainMOD\Error();

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

        $domain = new DomainMOD\Domain();

        while (list($key, $new_domain) = each($lines)) {
	
			if (!$domain->checkDomainFormat($new_domain)) {
				if ($invalid_domain_count < $invalid_domains_to_display) {
					$temp_result_message .= "Line " . number_format($key + 1) . " contains an invalid domain<BR>";
				}
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

            $domain = new DomainMOD\Domain();
			
			while (list($key, $new_domain) = each($lines)) {
	
				if (!$domain->checkDomainFormat($new_domain)) {
					echo "invalid domain $key"; exit;
				}
	
			}

			$new_segment_formatted = "'" . $new_segment;
			$new_segment_formatted = $new_segment_formatted . "'";
			$new_segment_formatted = preg_replace("/\r\n/", "','", $new_segment_formatted);
			$new_segment_formatted = str_replace (" ", "", $new_segment_formatted);
			$new_segment_formatted = trim($new_segment_formatted);

            $stmt = mysqli_stmt_init($connection);
            $query = "INSERT into segments (`name`, description, segment, number_of_domains, notes, insert_time) VALUES (?, ?, ?, ?, ?, ?)";

            if (mysqli_stmt_prepare($stmt, $query)) {

                mysqli_stmt_bind_param($stmt, "sssiss", $new_name, $new_description, $new_segment_formatted, $number_of_domains, $new_notes, $current_timestamp);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

            } else { $error->outputSqlError($connection, "ERROR"); }

            $stmt = mysqli_stmt_init($connection);
            $query = "SELECT id FROM segments WHERE name = ? AND segment = ? AND insert_time = ?";

            if (mysqli_stmt_prepare($stmt, $query)) {

                mysqli_stmt_bind_param($stmt, "sss", $new_name, $new_segment_formatted, $current_timestamp);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                mysqli_stmt_bind_result($stmt, $id);

                while (mysqli_stmt_fetch($stmt)) {

                    $temp_segment_id = $id;

                }

                mysqli_stmt_close($stmt);

            } else { $error->outputSqlError($connection, "ERROR"); }

            $stmt = mysqli_stmt_init($connection);
            $query = "DELETE FROM segment_data WHERE segment_id = ?";

            if (mysqli_stmt_prepare($stmt, $query)) {

                mysqli_stmt_bind_param($stmt, "i", $temp_segment_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

            } else { $error->outputSqlError($connection, "ERROR"); }

            foreach ($lines as $domain) {

                $stmt = mysqli_stmt_init($connection);
                $query = "INSERT INTO segment_data (segment_id, domain, insert_time) VALUES (?, ?, ?)";

                if (mysqli_stmt_prepare($stmt, $query)) {

                    mysqli_stmt_bind_param($stmt, "iss", $temp_segment_id, $domain, $current_timestamp);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                } else { $error->outputSqlError($connection, "ERROR"); }

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
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/layout/header.inc.php"); ?>
<form name="add_segment_form" method="post" action="segment.php">
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
