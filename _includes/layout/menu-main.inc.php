<?php
// /_includes/layout/menu-main.inc.php
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
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/registrar-accounts.php">Accounts</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/dns.php">DNS</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/ip-addresses.php">IPs</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/categories.php">Categories</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/owners.php">Owners</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/currencies.php">Currencies</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/tlds.php">TLDs</a> ]&nbsp;
<BR><BR>
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-certs.php">SSL Certificates</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-providers.php">SSL Providers</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-accounts.php">SSL Accounts</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-types.php">SSL Types</a> ]&nbsp;
<BR><BR>
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/segments.php">Segment Filters</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/bulk-actions.php">Perform Bulk Actions</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/domain-renewals.php">Domain Renewals</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/ssl-cert-renewals.php">SSL Renewals</a> ]&nbsp;
[ <a href="<?php if ($web_root != "/") echo $web_root; ?>/system/">Control Panel</a> ]&nbsp;
<?php include($full_server_path . "/_includes/layout/menu-sub.inc.php"); ?>