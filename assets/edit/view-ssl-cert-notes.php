<?php
// /assets/edit/view-ssl-cert-notes.php
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

$page_title = "Viewing an SSL Certificates's Notes";
$software_section = "ssl-certs";

$sslcid = $_GET['sslcid'];

$sql = "SELECT name, notes
		FROM ssl_certs
		WHERE id = '$sslcid'";
$result = mysql_query($sql,$connection);

while ($row = mysql_fetch_object($result)) { 

	$new_name = $row->name;
	$new_notes = $row->notes;

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../_includes/head-tags-bare.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/header-bare.inc.php"); ?>
<strong>Notes For <?=$new_name?></strong><BR>
<BR>
<?php
$temp_input_string = $new_notes;
include("../../_includes/system/display-note-formatting.inc.php");
$new_notes = $temp_output_string;
echo $new_notes;
?>
<?php include("../../_includes/footer-bare.inc.php"); ?>
</body>
</html>