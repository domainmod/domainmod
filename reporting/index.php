<?php
// /reporting/index.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");

$page_title = "Reporting";
$software_section = "reporting";
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
Before running any reports you should <a href="../system/update-conversion-rates.php">update the conversion rates</a>.<BR><BR><BR>
<font class="subheadline">Domains</font><BR><BR>
&raquo; <a href="domains/cost-breakdown-by-month.php">Cost Breakdown by Month</a>&nbsp;&nbsp;/&nbsp;&nbsp;<a href="domains/registrar-fee-breakdown.php">Registrar Fee Breakdown</a>&nbsp;&nbsp;/&nbsp;&nbsp;<a href="domains/tld-breakdown.php">TLD Breakdown</a><BR><BR><BR>
<font class="subheadline">SSL Certificates</font><BR><BR>
&raquo; <a href="ssl/cost-breakdown-by-month.php">Cost Breakdown by Month</a>&nbsp;&nbsp;/&nbsp;&nbsp;<a href="ssl/ssl-provider-fee-breakdown.php">SSL Provider Fee Breakdown</a><BR><BR>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>