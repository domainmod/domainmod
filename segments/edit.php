<?php
/**
 * /segments/edit.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$maint = new DomainMOD\Maintenance();
$form = new DomainMOD\Form();
$time = new DomainMOD\Time();
$timestamp = $time->stamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/segments-edit.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$segid = $_GET['segid'];

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$new_name = $_POST['new_name'];
$new_description = $_POST['new_description'];
$new_segment = $_POST['new_segment'];
$new_notes = $_POST['new_notes'];
$new_segid = $_POST['new_segid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    $format = new DomainMOD\Format();
    $new_segment = $format->stripSpacing($new_segment);

    if ($new_name != "" && $new_segment != "") {

        $lines = array_unique(explode("\r\n", $new_segment));
        $number_of_domains = count($lines);

        $domain = new DomainMOD\Domain();

        list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) =
            $domain->findInvalidDomains($lines);

        if ($new_segment == "" || $invalid_domains == 1) {

            if ($invalid_domains == 1) {

                if ($invalid_count == 1) {

                    $_SESSION['s_message_danger'] .= "There is " . number_format($invalid_count) . " invalid domain
                        on your list<BR><BR>" . $temp_result_message;

                } else {

                    $_SESSION['s_message_danger'] .= "There are " . number_format($invalid_count) . " invalid
                        domains on your list<BR><BR>" . $temp_result_message;

                    if (($invalid_count - $invalid_to_display) == 1) {

                        $_SESSION['s_message_danger'] .= "<BR>Plus " .
                            number_format($invalid_count - $invalid_to_display) . " other<BR>";

                    } elseif (($invalid_count - $invalid_to_display) > 1) {

                        $_SESSION['s_message_danger'] .= "<BR>Plus " .
                            number_format($invalid_count - $invalid_to_display) . " others<BR>";
                    }

                }

            }
            $submission_failed = 1;

        } else {

            $lines = array_unique(explode("\r\n", $new_segment));

            $domain = new DomainMOD\Domain();

            while (list($key, $new_domain) = each($lines)) {

                if (!$domain->checkFormat($new_domain)) {
                    echo "invalid domain $key";
                    exit;
                }

            }

            $new_segment_formatted = "'" . $new_segment;
            $new_segment_formatted = $new_segment_formatted . "'";
            $new_segment_formatted = preg_replace("/\r\n/", "','", $new_segment_formatted);
            $new_segment_formatted = str_replace(" ", "", $new_segment_formatted);
            $new_segment_formatted = trim($new_segment_formatted);

            $query = "UPDATE segments
                      SET `name` = ?,
                          description = ?,
                          segment = ?,
                          number_of_domains = ?,
                          notes = ?,
                          update_time = ?
                      WHERE id = ?";
            $q = $conn->stmt_init();

            if ($q->prepare($query)) {

                $q->bind_param('sssissi', $new_name, $new_description, $new_segment_formatted, $number_of_domains,
                    $new_notes, $timestamp, $segid);
                $q->execute();
                $q->close();

            } else {
                $error->outputSqlError($conn, "ERROR");
            }

            $query = "DELETE FROM segment_data
                      WHERE segment_id = ?";
            $q = $conn->stmt_init();

            if ($q->prepare($query)) {

                $q->bind_param('i', $new_segid);
                $q->execute();
                $q->close();

            } else {
                $error->outputSqlError($conn, "ERROR");
            }

            foreach ($lines as $domain) {

                $query = "INSERT INTO segment_data
                          (segment_id, domain, update_time)
                          VALUES
                          (?, ?, ?)";
                $q = $conn->stmt_init();

                if ($q->prepare($query)) {

                    $q->bind_param('iss', $new_segid, $domain, $timestamp);
                    $q->execute();
                    $q->close();

                } else {
                    $error->outputSqlError($conn, "ERROR");
                }

            }

            $segid = $new_segid;

            $_SESSION['s_message_success'] .= "Segment " . $new_name . " Updated<BR>";

            $maint->updateSegments($connection);

            header("Location: ../segments/");
            exit;

        }

    } else {

        if ($new_name == "") $_SESSION['s_message_danger'] .= "Enter the segment name<BR>";
        if ($new_segment == "") $_SESSION['s_message_danger'] .= "Enter the segment<BR>";

    }

} else {

    $query = "SELECT id, `name`, description, segment, notes
              FROM segments
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $segid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_id, $new_name, $new_description, $new_segment, $new_notes);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $new_segment = preg_replace("/', '/", "\r\n", $new_segment);
    $new_segment = preg_replace("/','/", "\r\n", $new_segment);
    $new_segment = preg_replace("/'/", "", $new_segment);

}

if ($del == "1") {

    $_SESSION['s_message_danger'] .= "Are you sure you want to delete this Segment?<BR><BR><a
        href=\"edit.php?segid=$segid&really_del=1\">YES, REALLY DELETE THIS SEGMENT</a><BR>";

}

if ($really_del == "1") {

    $query = "SELECT `name`
              FROM segments
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $segid);
        $q->execute();
        $q->store_result();
        $q->bind_result($temp_segment_name);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "DELETE FROM segments
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $segid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "DELETE FROM segment_data
              WHERE segment_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $segid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $_SESSION['s_message_success'] .= "Segment " . $temp_segment_name . " Deleted<BR>";

    header("Location: ../segments/");
    exit;

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_name', 'Segment Name (35)', '', $new_name, '35', '', '1', '', '');
echo $form->showInputTextarea('new_segment', 'Segment Domains (one per line)', '', $new_segment, '1', '', '');
echo $form->showInputTextarea('new_description', 'Description', '', $new_description, '', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('new_segid', $segid);
echo $form->showSubmitButton('Update Segment', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="edit.php?segid=<?php echo urlencode($segid); ?>&del=1">DELETE THIS SEGMENT</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
