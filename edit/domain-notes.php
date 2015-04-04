<?php
/**
 * /edit/domain-notes.php
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

$page_title = "Viewing a Domain's Notes";
$software_section = "domains";

$did = $_GET['did'];

$sql = "SELECT domain, notes
		FROM domains
		WHERE id = '$did'";
$result = mysqli_query($connection, $sql);

while ($row = mysqli_fetch_object($result)) {

    $new_domain = $row->domain;
    $new_notes = $row->notes;

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../_includes/layout/head-tags-bare.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header-bare.inc.php"); ?>
<strong>Notes For <?php echo $new_domain; ?></strong><BR>
<BR>
<?php
$temp_input_string = $new_notes;
include("../_includes/system/display-note-formatting.inc.php");
$new_notes = $temp_output_string;
echo $new_notes;
?>
<?php include("../_includes/layout/footer-bare.inc.php"); ?>
</body>
</html>
