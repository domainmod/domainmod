<?php
// segments-missing.php
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
session_start();

include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");
include("_includes/timestamps/current-timestamp-basic.inc.php");

$page_title = "Segments - Missing Domains";
$software_section = "segments";

$segid = $_GET['segid'];
$export = $_GET['export'];

$sql = "SELECT domain
		FROM segment_data
		WHERE segment_id = '$segid'
		  AND domain NOT IN (SELECT domain FROM domains)
		ORDER BY domain";
$result = mysql_query($sql,$connection);

$full_export = "";

if ($export == "1") {

	$full_export .= "\"Missing Domains\"\n";

	while ($row = mysql_fetch_object($result)) {
		
		$full_export .= "\"$row->domain\"\n";
	}

	$full_export .= "\n";
	
	$export = "0";
	
header('Content-Type: text/plain');
$full_content_disposition = "Content-Disposition: attachment; filename=\"export_missing_$current_timestamp_basic.csv\"";
header("$full_content_disposition");
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
echo $full_export;
exit;
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
<?php
$sql_name = "SELECT name
			 FROM segments
			 WHERE id = '$segid'";
$result_name = mysql_query($sql_name,$connection);
while ($row_name = mysql_fetch_object($result_name)) { $segment_name = $row_name->name; }
?>
The below domains are in the segment <strong><font class="highlight"><?=$segment_name?></font></strong>, but they are not in your <?=$software_title?> database.<BR><BR>
<a href="<?=$_SERVER['HTTP_REFERER']?>">&laquo; Back to Domains</a><BR><BR>
<a href="segments-missing.php?segid=<?=$segid?>&export=1">Export Results</a><BR><BR>
<?php
while ($row = mysql_fetch_object($result)) {
	echo $row->domain . "<BR>";
}
?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>