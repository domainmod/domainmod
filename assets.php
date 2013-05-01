<?php
// /assets.php
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
include("_includes/start-session.inc.php");
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");

$page_title = "Asset Management";
$software_section = "assets";
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
<font class="subheadline">Domains</font><BR>
<a href="<?php if ($web_root != "/") echo $web_root; ?>/registrars.php">Domain Registrars</a><BR>
<a href="<?php if ($web_root != "/") echo $web_root; ?>/registrar-accounts.php">Domain Registrar Accounts</a><BR>
<a href="<?php if ($web_root != "/") echo $web_root; ?>/dns.php">DNS Servers</a><BR>
<a href="<?php if ($web_root != "/") echo $web_root; ?>/hosting.php">Web Hosting</a><BR>
<BR>
<font class="subheadline">SSL Certificates</font><BR>
<a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-providers.php">SSL Providers</a><BR>
<a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-accounts.php">SSL Provider Accounts</a><BR>
<a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-types.php">SSL Certificate Types</a><BR>
<BR>
<font class="subheadline">Shared</font><BR>
<a href="<?php if ($web_root != "/") echo $web_root; ?>/ip-addresses.php">IP Addresses</a><BR>
<a href="<?php if ($web_root != "/") echo $web_root; ?>/categories.php">Categories</a><BR>
<a href="<?php if ($web_root != "/") echo $web_root; ?>/owners.php">Owners</a><BR>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>