<?php
// segments.php
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

$page_title = "Segment Filters";
$software_section = "segments";

function str_stop($string, $max_length){ 
    if (strlen($string) > $max_length){ 
        $string = substr($string, 0, $max_length); 
        $pos = strrpos($string, ", "); 
        if($pos === false) { 
               return substr($string, 0, $max_length)."..."; 
           } 
        return substr($string, 0, $pos)."..."; 
    }else{ 
        return $string; 
    } 
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
$sql = "SELECT id, name, description, segment
		FROM segments
		WHERE active = '1'
		ORDER BY name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
Segments identify a specific subset of domains, which can be used to help filter and manage your <a href="domains.php">domain results</a>.
<BR><BR>
<?php 
$sql_segment_check = "SELECT id
					  FROM segments
					  WHERE active = '1'
					  LIMIT 1";
$result_segment_check = mysql_query($sql_segment_check,$connection);
if (mysql_num_rows($result_segment_check) == 0) {
?>
	You don't currently have any Segments. <a href="add/segment.php">Click here to add one</a>.<BR>
<?php
}
if (mysql_num_rows($result) > 0) { ?>
    <table width="100%" border="0" cellspacing="0" cellpadding="5">
    <tr height="30">
        <td width="250">
            <font class="subheadline">Segments (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td width="350">
            <font class="subheadline">Segment Description</font>
        </td>
        <td>
            <font class="subheadline">Segment</font>
        </td>
    </tr>

    <?php 
	while ($row = mysql_fetch_object($result)) { ?>

        <tr height="20">
            <td valign="top">
                <a class="subtlelink" href="edit/segment.php?segid=<?=$row->id?>"><?=$row->name?></a>
            </td>
            <td valign="top">
                <?php
                $temp_description = preg_replace("/\r\n/", "<BR>", $row->description);
                echo $temp_description;
                ?><BR><BR>
            </td>
            <td valign="top">
                <?php
                $temp_segment = preg_replace("/','/", ", ", $row->segment);
                $temp_segment = preg_replace("/'/", "", $temp_segment);
                echo str_stop($temp_segment, 250);
                ?>
            </td>
        </tr>

    <?php 
	} ?>
    </table>
<?php 
} ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>