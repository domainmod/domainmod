<?php
// /_includes/system/export/header.inc.php
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
// // CODE TO USE
// // 
// // Export to CSV (Header)
// // Input: $export_filename
// include("_includes/system/export/header.inc.php");
?>
<?php
$export = "0";
$file_content = "";
$row_content = array();
$blank_line = array('');
$count = 0;

header('Content-Encoding: UTF-8');
header('Content-Type: text/csv; charset=UTF-8');
$temp_full_content_disposition = "Content-Disposition: attachment; filename=\"" . $export_filename . "\"";
header("" . $temp_full_content_disposition . "");
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header("Pragma: no-cache");

$file_content = fopen('php://output', 'w');
fprintf($file_content, chr(0xEF).chr(0xBB).chr(0xBF));
?>