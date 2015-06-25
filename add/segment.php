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
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$maint = new DomainMOD\Maintenance();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();
$timestamp = $time->time();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");

$page_title = "Adding A New Segment";
$software_section = "segment-add";

// Form Variables
$new_name = $_POST['new_name'];
$new_description = $_POST['new_description'];
$new_segment = $_POST['new_segment'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $format = new DomainMOD\Format();
    $new_segment = $format->stripSpacing($new_segment);

    if ($new_name != "" && $new_segment != "") {

		$lines = explode("\r\n", $new_segment);

        $domain = new DomainMOD\Domain();

        list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) = $domain->findInvalidDomains($lines);

        if ($new_segment == "" || $invalid_domains == 1) {
		
			if ($invalid_domains == 1) {
	
				if ($invalid_count == 1) {
	
					$_SESSION['result_message'] = "There is " . number_format($invalid_count) . " invalid domain
					    on your list<BR><BR>" . $temp_result_message;
	
				} else {
	
					$_SESSION['result_message'] = "There are " . number_format($invalid_count) . " invalid
					    domains on your list<BR><BR>" . $temp_result_message;
	
					if (($invalid_count-$invalid_to_display) == 1) {
	
						$_SESSION['result_message'] .= "<BR>Plus " .
                            number_format($invalid_count-$invalid_to_display) . " other<BR>";
	
					} elseif (($invalid_count-$invalid_to_display) > 1) {
	
						$_SESSION['result_message'] .= "<BR>Plus " .
                            number_format($invalid_count-$invalid_to_display) . " others<BR>";
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

            $query = "INSERT into segments
                      (`name`, description, segment, number_of_domains, notes, insert_time)
                      VALUES
                      (?, ?, ?, ?, ?, ?)";
            $q = $conn->stmt_init();

            if ($q->prepare($query)) {

                $q->bind_param('sssiss', $new_name, $new_description, $new_segment_formatted, $number_of_domains,
                    $new_notes, $timestamp);
                $q->execute();
                $q->close();

            } else { $error->outputSqlError($conn, "ERROR"); }

            $query = "SELECT id
                      FROM segments
                      WHERE name = ?
                        AND segment = ?
                        AND insert_time = ?";
            $q = $conn->stmt_init();

            if ($q->prepare($query)) {

                $q->bind_param('sss', $new_name, $new_segment_formatted, $timestamp);
                $q->execute();
                $q->store_result();
                $q->bind_result($temp_segment_id);
                $q->fetch();
                $q->close();

            } else { $error->outputSqlError($conn, "ERROR"); }

            $query = "DELETE FROM segment_data
                      WHERE segment_id = ?";
            $q = $conn->stmt_init();

            if ($q->prepare($query)) {

                $q->bind_param('i', $temp_segment_id);
                $q->execute();
                $q->close();

            } else { $error->outputSqlError($conn, "ERROR"); }

            foreach ($lines as $domain) {

                $query = "INSERT INTO segment_data
                          (segment_id, domain, insert_time)
                          VALUES
                          (?, ?, ?)";
                $q = $conn->stmt_init();

                if ($q->prepare($query)) {

                    $q->bind_param('iss', $temp_segment_id, $domain, $timestamp);
                    $q->execute();
                    $q->close();

                } else { $error->outputSqlError($conn, "ERROR"); }

            }

            $_SESSION['result_message'] = "Segment <font class=\"highlight\">$new_name</font> Added<BR>";

            $_SESSION['result_message'] .= $maint->updateSegments($connection);

            header("Location: ../segments.php");
			exit;
			
		}

	} else {
	
		if ($new_name == "") { $_SESSION['result_message'] .= "Please enter the segment name<BR>"; }
		if ($new_segment == "") { $_SESSION['result_message'] .= "Please enter the segment<BR>"; }

	}

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_segment_form" method="post">
<strong>Segment Name (35)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
        </font></a><BR><BR>
<input name="new_name" type="text" value="<?php echo $new_name; ?>" size="25" maxlength="35">
<BR><BR>
<strong>Segment Domains (one per line)</strong><a title="Required Field"><font class="default_highlight"><strong>*
            </strong></font></a><BR><BR>
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
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
