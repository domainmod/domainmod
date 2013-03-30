<?php
// menu-main.inc.php
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
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/domains.php">Domains</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/registrars.php">Registrars</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/registrar-accounts.php">Registrar Accounts</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/dns.php">DNS Profiles</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/ip-addresses.php">IP Addresses</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/companies.php">Companies</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/categories.php">Categories</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/tlds.php">TLDs</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/currencies.php">Currencies</a> ]
<BR><BR>
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-certs.php">SSL Certificates</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-providers.php">SSL Providers</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-accounts.php">SSL Accounts</a> ]&nbsp;
<BR><BR>
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/segments.php">Segment Filters</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/bulk-actions.php">Perform Bulk Actions</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/export-domains.php">Export Domains</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/export-ssl-certs.php">Export SSL Certs</a> ]&nbsp;
<?php /* ?>
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/help/">Help!</a> ]&nbsp;
<?php */ ?>
<?php include($full_server_path . "/_includes/layout/menu-sub.inc.php"); ?>