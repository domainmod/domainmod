<?php
// /_includes/layout/menu-main.inc.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
[ <a href="<?php echo $web_root; ?>/domains.php">Domains</a> ]&nbsp;
[ <a href="<?php echo $web_root; ?>/ssl-certs.php">SSL Certificates</a> ]&nbsp;
[ <a href="<?php echo $web_root; ?>/segments.php">Segments</a> ]&nbsp;
[ <a href="<?php echo $web_root; ?>/bulk-updater.php">Bulk Updater</a> ]&nbsp;
<BR><BR>
[ <a href="<?php echo $web_root; ?>/assets/">Asset Management</a> ]&nbsp;
[ <a href="<?php echo $web_root; ?>/reporting/">Reporting</a> ]&nbsp;
<?php if ($_SESSION['is_admin'] == 1) { ?>[ <a href="<?php echo $web_root; ?>/system/admin/dw/intro.php">DW</a> ]&nbsp;&nbsp;<?php } ?>
[ <a href="<?php echo $web_root; ?>/system/">Control Panel</a> ]&nbsp;
<?php include($full_server_path . "/_includes/layout/menu-sub.inc.php"); ?>
