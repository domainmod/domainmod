<?php
/**
 * /_includes/layout/menu-main.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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

<nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <!-- Add icons to the links using the .nav-icon class
             with font-awesome or any other icon font library -->
        <li class="nav-header">NAVIGATION</li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/" class="nav-link<?php if ($software_section == "dashboard") echo " active"; ?>">
                <i class="nav-icon fas fa-tachometer-alt"></i>
                <p>
                    <?php echo _('Dashboard'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/domains/" class="nav-link<?php if ($software_section == "domains") echo " active"; ?>">
                <i class="nav-icon fas fa-sitemap"></i>
                <p>
                    <?php echo _('Domains'); ?>
                </p>
            </a>
        </li>
        <?php if ($_SESSION['s_domains_in_list_queue'] == '1' || $_SESSION['s_domains_in_queue'] == '1') { ?>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/queue/" class="nav-link<?php if ($software_section == "queue") echo " active"; ?>">
                <i class="nav-icon fas fa-hourglass"></i>
                <p>
                    <?php echo _('Queue'); ?>
                </p>
            </a>
        </li>
        <?php } ?>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/ssl/" class="nav-link<?php if ($software_section == "ssl") echo " active"; ?>">
                <i class="nav-icon fas fa-lock"></i>
                <p>
                    <?php echo _('SSL Certificates'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/assets/" class="nav-link<?php if ($software_section == "assets") echo " active"; ?>">
                <i class="nav-icon fas fa-cubes"></i>
                <p>
                    <?php echo _('Assets'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/segments/" class="nav-link<?php if ($software_section == "segments") echo " active"; ?>">
                <i class="nav-icon fas fa-filter"></i>
                <p>
                    <?php echo _('Segments'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/bulk/" class="nav-link<?php if ($software_section == "bulk") echo " active"; ?>">
                <i class="nav-icon fas fa-copy"></i>
                <p>
                    <?php echo _('Bulk Updater'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/reporting/" class="nav-link<?php if ($software_section == "reporting") echo " active"; ?>">
                <i class="nav-icon fas fa-chart-bar"></i>
                <p>
                    <?php echo _('Reporting'); ?>
                </p>
            </a>
        </li>

        <?php if ($_SESSION['s_is_admin'] === 1) { ?>
            <li class="nav-item">
                <a href="<?php echo $web_root; ?>/admin/dw/" class="nav-link<?php if ($software_section == "dw") echo " active"; ?>">
                    <i class="nav-icon fas fa-database"></i>
                    <p>
                        <?php echo _('Data Warehouse'); ?>
                    </p>
                </a>
            </li>
        <?php } ?>
        <li class="nav-item<?php if ($software_section == "settings") echo " menu-open"; ?>">
            <a href="#" class="nav-link<?php if ($software_section == "settings") echo " active"; ?>">
                <i class="nav-icon fas fa-cogs"></i>
                <p>
                    Settings
                    <i class="right fas fa-angle-left"></i>
                </p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/settings/display/" class="nav-link<?php if ($slug == "settings-display") echo " active"; ?>">
                        <i class="far fa-circle-disabled nav-icon"></i>
                        <p>Display Settings</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/settings/defaults/" class="nav-link<?php if ($slug == "settings-defaults") echo " active"; ?>">
                        <i class="far fa-circle-disabled nav-icon"></i>
                        <p>User Defaults</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/settings/profile/" class="nav-link<?php if ($slug == "settings-profile") echo " active"; ?>">
                        <i class="far fa-circle-disabled nav-icon"></i>
                        <p>User Profile</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/settings/password/" class="nav-link<?php if ($slug == "settings-password") echo " active"; ?>">
                        <i class="far fa-circle-disabled nav-icon"></i>
                        <p>Change Password</p>
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-item<?php if ($software_section == "maintenance") echo " menu-open"; ?>">
            <a href="#" class="nav-link<?php if ($software_section == "maintenance") echo " active"; ?>">
                <i class="nav-icon fas fa-check"></i>
                <p>
                    Maintenance
                    <i class="right fas fa-angle-left"></i>
                </p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/maintenance/update-conversions.php" class="nav-link">
                        <i class="far fa-circle-disabled nav-icon"></i>
                        <p>Update Conversion Rates</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/maintenance/update-domain-fees.php" class="nav-link">
                        <i class="far fa-circle-disabled nav-icon"></i>
                        <p>Update Domain Fees</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/maintenance/update-ssl-fees.php" class="nav-link">
                        <i class="far fa-circle-disabled nav-icon"></i>
                        <p>Update SSL Fees</p>
                    </a>
                </li>
            </ul>
        </li>

        <?php if ($_SESSION['s_is_admin'] === 1) { ?>
            <li class="nav-item<?php if ($software_section == "admin") echo " menu-open"; ?>">
                <a href="#" class="nav-link<?php if ($software_section == "admin") echo " active"; ?>">
                    <i class="nav-icon fas fa-wrench"></i>
                    <p>
                        Administration
                        <i class="right fas fa-angle-left"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/settings/" class="nav-link<?php if ($slug == "admin-settings") echo " active"; ?>">
                            <i class="far fa-circle-disabled nav-icon"></i>
                            <p>System Settings</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/defaults/" class="nav-link<?php if ($slug == "admin-defaults") echo " active"; ?>">
                            <i class="far fa-circle-disabled nav-icon"></i>
                            <p>System Defaults</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/users/" class="nav-link<?php if ($slug == "admin-users-main" || $slug == "admin-users-add" || $slug == 'admin-users-edit') echo " active"; ?>">
                            <i class="far fa-circle-disabled nav-icon"></i>
                            <p>Users</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/domain-fields/" class="nav-link<?php if ($slug == "admin-custom-domain-fields" || $slug == 'admin-add-custom-domain-field' || $slug == 'admin-edit-custom-domain-field') echo " active"; ?>">
                            <i class="far fa-circle-disabled nav-icon"></i>
                            <p>Custom Domain Fields</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/ssl-fields/" class="nav-link<?php if ($slug == "admin-custom-ssl-fields" || $slug == "admin-add-custom-ssl-field" || $slug == "admin-edit-custom-ssl-field") echo " active"; ?>">
                            <i class="far fa-circle-disabled nav-icon"></i>
                            <p>Custom SSL Fields</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/scheduler/" class="nav-link<?php if ($slug == "admin-scheduler-main") echo " active"; ?>">
                            <i class="far fa-circle-disabled nav-icon"></i>
                            <p>Task Scheduler</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/maintenance/" class="nav-link<?php if ($slug == "admin-maintenance-main") echo " active"; ?>">
                            <i class="far fa-circle-disabled nav-icon"></i>
                            <p>Maintenance</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/backup/" class="nav-link<?php if ($slug == "admin-backup-main") echo " active"; ?>">
                            <i class="far fa-circle-disabled nav-icon"></i>
                            <p>Backup & Restore</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/debug-log/" class="nav-link<?php if ($slug == "admin-debug-log-main") echo " active"; ?>">
                            <i class="far fa-circle-disabled nav-icon"></i>
                            <p>Debug Log</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/info/" class="nav-link<?php if ($slug == "admin-info") echo " active"; ?>">
                            <i class="far fa-circle-disabled nav-icon"></i>
                            <p>System Information</p>
                        </a>
                    </li>
                </ul>
            </li>
        <?php } ?>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/logout.php" class="nav-link">
                <i class="nav-icon fas fa-sign-out-alt"></i>
                <p>
                    <?php echo _('Logout'); ?>
                </p>
            </a>
        </li>
        <li class="nav-header">HELP</li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/docs/userguide/" class="nav-link">
                <i class="nav-icon fas fa-book"></i>
                <p>
                    <?php echo _('Documentation'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="https://domainmod.org/support/" class="nav-link">
                <i class="nav-icon fas fa-life-ring"></i>
                <p>
                    <?php echo _('Support'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="https://domainmod.org/news/" class="nav-link">
                <i class="nav-icon fas fa-newspaper"></i>
                <p>
                    <?php echo _('News'); ?>
                </p>
            </a>
        </li>
<?php /* ?>
        <li class="nav-item">
            <a href="https://domainmod.org/contribute/" class="nav-link">
                <i class="nav-icon fas fa-thumbs-up"></i>
                <p>
                    <?php echo _('Contribute'); ?>
                </p>
            </a>
        </li>
<?php */ ?>
    </ul>
</nav>
<!-- /.sidebar-menu -->
