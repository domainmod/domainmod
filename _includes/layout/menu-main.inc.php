<?php
/**
 * /_includes/layout/menu-main.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
    [ <a href="<?php echo $web_root; ?>/domains.php">Domains</a> ]&nbsp;
    [ <a href="<?php echo $web_root; ?>/ssl-certs.php">SSL Certificates</a> ]&nbsp;
    [ <a href="<?php echo $web_root; ?>/segments.php">Segments</a> ]&nbsp;
    [ <a href="<?php echo $web_root; ?>/bulk.php">Bulk Updater</a> ]&nbsp;
    <BR><BR>
    [ <a href="<?php echo $web_root; ?>/assets/">Assets</a> ]&nbsp;
    [ <a href="<?php echo $web_root; ?>/reporting/">Reporting</a> ]&nbsp;
<?php if ($_SESSION['is_admin'] === 1) { ?>
    [ <a href="<?php echo $web_root; ?>/admin/dw/">Data Warehouse</a> ]&nbsp;
<?php } ?>
    [ <a href="<?php echo $web_root; ?>/settings/">Settings</a> ]&nbsp;
<?php
include(DIR_INC . "layout/menu-sub.inc.php");
