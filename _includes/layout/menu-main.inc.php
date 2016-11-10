<?php
/**
 * /_includes/layout/menu-main.inc.php
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
      <ul class="sidebar-menu">
        <li class="header">NAVIGATION</li>
        <li<?php if ($software_section == "dashboard") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/dashboard/"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a></li>

        <li<?php if ($software_section == "domains") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/domains/"><i class="fa fa-sitemap"></i> <span>Domains</span></a></li>

        <?php if ($_SESSION['s_domains_in_list_queue'] == '1' || $_SESSION['s_domains_in_queue'] == '1') { ?>
        <li<?php if ($software_section == "queue") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/queue/"><i class="fa fa-hourglass-2"></i> <span>Queue</span></a></li>
        <?php } ?>

        <li<?php if ($software_section == "ssl") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/ssl/"><i class="fa fa-lock"></i> <span>SSL Certificates</span></a></li>
        <li<?php if ($software_section == "assets") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/assets/"><i class="fa fa-cubes"></i> <span>Assets</span></a></li>
        <li<?php if ($software_section == "segments") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/segments/"><i class="fa fa-filter"></i> <span>Segments</span></a></li>

        <?php if ($_SESSION['s_read_only'] == '0') { ?>
          
          <li<?php if ($slug == "bulk-main") { echo " class=\"active\""; } ?>><a href="<?php echo $web_root; ?>/bulk/"><i class="fa fa-copy"></i> <span>Bulk Updater</span></a></li>
        
        <?php } ?>

        <li<?php if ($software_section == "reporting") { echo " class=\"active\""; } ?>><a href="<?php echo $web_root; ?>/reporting/"><i class="fa fa-bar-chart"></i> <span>Reporting</span></a></li>

        <?php if ($_SESSION['s_is_admin'] === 1) { //@formatter:off ?>
          <li<?php if ($software_section == "dw") { echo " class=\"active\""; } ?>><a href="<?php echo $web_root; ?>/admin/dw/"><i class="fa fa-database"></i> <span>Data Warehouse</span></a></li>
        <?php } ?>

        <li class="treeview<?php if ($software_section == "settings") echo " active"; ?>">
          <a href="#">
            <i class="fa fa-gears"></i> <span>Settings</span>
            <i class="fa fa-angle-left pull-right"></i>
          </a>
          <ul class="treeview-menu">
              <li<?php if ($slug == "settings-display") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/settings/display/"><i class="fa"></i>Display Settings</a></li>
              <li<?php if ($slug == "settings-defaults") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/settings/defaults/"><i class="fa"></i>User Defaults</a></li>
              <li<?php if ($slug == "settings-profile") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/settings/profile/"><i class="fa"></i>User Profile</a></li>
              <li<?php if ($slug == "settings-password") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/settings/password/"><i class="fa"></i>Change Password</a></li>
          </ul>
        </li>

        <?php if ($_SESSION['s_read_only'] == '0') { ?>

            <li class="treeview<?php if ($software_section == "maintenance") echo " active"; ?>">
              <a href="#">
                <i class="fa fa-check"></i> <span>Maintenance</span>
                <i class="fa fa-angle-left pull-right"></i>
              </a>
              <ul class="treeview-menu">
                  <li><a href="<?php echo $web_root; ?>/maintenance/update-conversions.php"><i class="fa"></i>Update Conversion Rates</a></li>
                  <li><a href="<?php echo $web_root; ?>/maintenance/update-domain-fees.php"><i class="fa"></i>Update Domain Fees</a></li>
                  <li><a href="<?php echo $web_root; ?>/maintenance/update-ssl-fees.php"><i class="fa"></i>Update SSL Fees</a></li>
              </ul>
            </li>

        <?php } ?>

        <?php if ($_SESSION['s_is_admin'] === 1) { //@formatter:off ?>

            <li class="treeview<?php if ($software_section == "admin") echo " active"; ?>">
              <a href="#">
                <i class="fa fa-wrench"></i> <span>Administration</span>
                <i class="fa fa-angle-left pull-right"></i>
              </a>
              <ul class="treeview-menu">
                  <li<?php if ($slug == "admin-settings") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/admin/settings/"><i class="fa"></i>System Settings</a></li>
                  <li<?php if ($slug == "admin-defaults") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/admin/defaults/"><i class="fa"></i>System Defaults</a></li>
                  <li<?php if ($slug == "admin-users-main" || $slug == "admin-users-add" || $slug == "admin-users-edit") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/admin/users/"><i class="fa"></i>Users</a></li>
                  <li<?php if ($slug == "admin-custom-domain-fields" || $slug == "admin-add-custom-domain-field" || $slug == "admin-edit-custom-domain-field") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/admin/domain-fields/"><i class="fa"></i>Custom Domain Fields</a></li>
                  <li<?php if ($slug == "admin-custom-ssl-fields" || $slug == "admin-add-custom-ssl-field" || $slug == "admin-edit-custom-ssl-field") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/admin/ssl-fields/"><i class="fa"></i>Custom SSL Fields</a></li>
                  <li<?php if ($slug == "admin-scheduler-main") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/admin/scheduler/"><i class="fa"></i>Task Scheduler</a></li>
                  <li<?php if ($slug == "admin-maintenance-main") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/admin/maintenance/"><i class="fa"></i>Maintenance</a></li>
                  <li<?php if ($slug == "admin-info") echo " class=\"active\""; ?>><a href="<?php echo $web_root; ?>/admin/info/"><i class="fa"></i>System Information</a></li>
              </ul>
            </li>

        <?php } ?>

        <li class="header">HELP</li>
        <li><a target="_blank" href="http://domainmod.org/news/"><i class="fa fa-newspaper-o"></i> <span>News</span></a></li>
        <li><a target="_blank" href="http://domainmod.org/support/"><i class="fa fa-support"></i> <span>Support</span></a></li>
      </ul>
