<?php
/**
 * /edit/segment.php
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
include("../_includes/system/functions/error-reporting.inc.php");

$page_title = "Editing A Segment";
$software_section = "segment-edit";

$segid = $_GET['segid'];

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$new_name = $_POST['new_name'];
$new_description = $_POST['new_description'];
$new_segment = $_POST['new_segment'];
$new_notes = $_POST['new_notes'];
$new_segid = $_POST['new_segid'];

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
            $query = "UPDATE segments
                      SET `name` = ?,
                          description = ?,
                          segment = ?,
                          number_of_domains = ?,
                          notes = ?,
                          update_time = ?
                      WHERE id = ?";

            if (mysqli_stmt_prepare($stmt, $query)) {

                mysqli_stmt_bind_param($stmt, "sssissi", $new_name, $new_description, $new_segment_formatted, $number_of_domains, $new_notes, $current_timestamp, $segid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

            } else { outputSqlError($connection, "ERROR"); }

            $stmt = mysqli_stmt_init($connection);
            $query = "DELETE FROM segment_data
                      WHERE segment_id = ?";

            if (mysqli_stmt_prepare($stmt, $query)) {

                mysqli_stmt_bind_param($stmt, "i", $new_segid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

            } else { outputSqlError($connection, "ERROR"); }

            foreach ($lines as $domain) {

                $stmt = mysqli_stmt_init($connection);
                $query = "INSERT INTO segment_data (segment_id, domain, update_time) VALUES (?, ?, ?);";

                if (mysqli_stmt_prepare($stmt, $query)) {

                    mysqli_stmt_bind_param($stmt, "iss", $new_segid, $domain, $current_timestamp);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                } else { outputSqlError($connection, "ERROR"); }

            }

            $segid = $new_segid;
			
			$_SESSION['result_message'] = "Segment <font class=\"highlight\">$new_name</font> Updated<BR>";
	
			include("../_includes/system/update-segments.inc.php");

            header("Location: ../segments.php");
			exit;
		
		}

	} else {
	
		if ($new_name == "") $_SESSION['result_message'] .= "Please enter the segment name<BR>";
		if ($new_segment == "") $_SESSION['result_message'] .= "Please enter the segment<BR>";

	}

} else {

    $stmt = mysqli_stmt_init($connection);
    $query = "SELECT id, `name`, description, segment, notes FROM segments WHERE id = ?";

    if (mysqli_stmt_prepare($stmt, $query)) {

        mysqli_stmt_bind_param($stmt, "i", $segid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        mysqli_stmt_bind_result($stmt, $id, $name, $description, $segment, $notes);

        while (mysqli_stmt_fetch($stmt)) {

            $new_id = $id;
            $new_name = $name;
            $new_description = $description;
            $new_segment = $segment;
            $new_notes = $notes;

        }

        mysqli_stmt_close($stmt);

    } else { outputSqlError($connection, "ERROR"); }

    $new_segment = preg_replace("/', '/", "\r\n", $new_segment);
    $new_segment = preg_replace("/','/", "\r\n", $new_segment);
    $new_segment = preg_replace("/'/", "", $new_segment);

}

if ($del == "1") {

	$_SESSION['result_message'] = "Are you sure you want to delete this Segment?<BR><BR><a href=\"$PHP_SELF?segid=$segid&really_del=1\">YES, REALLY DELETE THIS SEGMENT</a><BR>";

}

if ($really_del == "1") {

    $stmt = mysqli_stmt_init($connection);
    $query = "SELECT `name`
              FROM segments
              WHERE id = ?";

    if (mysqli_stmt_prepare($stmt, $query)) {

        mysqli_stmt_bind_param($stmt, "i", $segid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        mysqli_stmt_bind_result($stmt, $name);

        while (mysqli_stmt_fetch($stmt)) {

            $temp_segment_name = $name;

        }

        mysqli_stmt_close($stmt);

    } else { outputSqlError($connection, "ERROR"); }

    $stmt = mysqli_stmt_init($connection);
    $query = "DELETE FROM segments
              WHERE id = ?";

    if (mysqli_stmt_prepare($stmt, $query)) {

        mysqli_stmt_bind_param($stmt, "i", $segid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    } else { outputSqlError($connection, "ERROR"); }

    $stmt = mysqli_stmt_init($connection);
    $query = "DELETE FROM segment_data
              WHERE segment_id = ?";

    if (mysqli_stmt_prepare($stmt, $query)) {

        mysqli_stmt_bind_param($stmt, "i", $segid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    } else { outputSqlError($connection, "ERROR"); }

    $_SESSION['result_message'] = "Segment <font class=\"highlight\">$temp_segment_name</font> Deleted<BR>";

    header("Location: ../segments.php");
	exit;

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
<form name="edit_segment_form" method="post" action="<?php echo $PHP_SELF; ?>">
<strong>Segment Name (35)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_name" type="text" value="<?php if ($new_name != "") echo htmlentities($new_name); ?>" size="25" maxlength="35">
<BR><BR>
<strong>Segment Domains (one per line)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<textarea name="new_segment" cols="60" rows="5"><?php if ($new_segment != "") echo $new_segment; ?></textarea>
<BR><BR>
<strong>Description</strong><BR><BR>
<textarea name="new_description" cols="60" rows="5"><?php if ($new_description != "") echo $new_description; ?></textarea>
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_segid" value="<?php echo $segid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This Segment &raquo;">
</form>
<BR><BR><a href="<?php echo $PHP_SELF; ?>?segid=<?php echo $segid; ?>&del=1">DELETE THIS SEGMENT</a>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>
