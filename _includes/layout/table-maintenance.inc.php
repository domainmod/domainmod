<?php
/**
 * /_includes/layout/table-maintenance.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
?>
<?php
if ($_SESSION['s_needs_database_upgrade'] == 1) { ?>
    <div class="maintenance_warning_outer">
    <div class="maintenance_warning_inner">
        <strong>Database Upgrade Available! (clear up the below issues to make this table disappear)</strong><BR><BR>
        <LI>You are running an older version of the <?php echo $software_title; ?> database.</LI>
        <BR>&nbsp;&nbsp;&nbsp;<a href="<?php echo $web_root; ?>/notice.php?a=u">Click here to upgrade your database</a>
    </div>
    </div><?php
    exit;
}

if ($_SESSION['s_missing_domain_fees'] == 1 || $_SESSION['s_missing_ssl_fees'] == 1) { ?>
    <div class="maintenance_warning_outer">
    <div class="maintenance_warning_inner">
        <strong>Maintenance Warning! (clear up the below issues to make this table disappear)</strong><BR><BR>
        <?php
        if ($_SESSION['s_missing_domain_fees'] == 1) { ?>
            <LI>Some of your Registrars/TLDs are missing domain fees. <a
                href="<?php echo $web_root . "/assets/edit/registrar-fees-missing.php\">Click here to fix this</a>. If you've already updated all new TLDs, you should <a href=\"" . $web_root; ?>/settings/update-domain-fees.php">update
                the domain fees system-wide</a> (this may take some time).</LI><?php
        } ?>
        <?php
        if ($_SESSION['s_missing_ssl_fees'] == 1) { ?>
            <LI>Some of your SSL Certificate Types are missing fees. <a
                href="<?php echo $web_root . "/assets/edit/ssl-provider-fees-missing.php\">Click here to fix this</a>. If you've already updated all new SSL Types, you should <a href=\"" . $web_root; ?>/settings/update-ssl-fees.php">update
                the SSL fees system-wide</a> (this may take some time).</LI><?php
        } ?>
    </div>
    </div><?php
}
