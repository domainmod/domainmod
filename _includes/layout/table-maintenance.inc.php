<?php
// /_includes/layout/table-maintenance.inc.php
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
<?php if ($_SESSION['needs_database_upgrade'] == 1) { ?>
<div class="maintenance_warning_outer">
	<div class="maintenance_warning_inner">
        <strong>Database Upgrade Available! (clear up the below issues to make this table disappear)</strong><BR><BR>
        <LI>You are running an older version of the <?=$software_title?> database.</LI>
        <BR>&nbsp;&nbsp;&nbsp;<a href="<?php if ($web_root != "") echo $web_root; ?>/system/update-database.php">Click here to upgrade your database</a>
    </div>
</div>
<?php exit; ?>
<?php } ?>
<?php if ($_SESSION['missing_domain_fees'] == 1 || $_SESSION['missing_ssl_fees'] == 1) { ?>
<div class="maintenance_warning_outer">
	<div class="maintenance_warning_inner">
        <strong>Maintenance Warning! (clear up the below issues to make this table disappear)</strong><BR><BR>
        <?php if ($_SESSION['missing_domain_fees'] == 1) { ?>
		        <LI>Some of your Registrars/TLDs are missing domain fees. <a href="<?php if ($web_root != "") echo $web_root; ?>/assets/edit/registrar-fees-missing.php">Click here to fix this</a>.</LI>
        <?php } ?>
        <?php if ($_SESSION['missing_ssl_fees'] == 1) { ?>
		        <LI>Some of your SSL Certificate Types are missing fees. <a href="<?php if ($web_root != "") echo $web_root; ?>/assets/edit/ssl-provider-fees-missing.php">Click here to fix this</a>.</LI>
        <?php } ?>
    </div>
</div>
<?php } ?>
