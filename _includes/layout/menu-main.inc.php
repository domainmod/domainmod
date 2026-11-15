<?php
/**
 * /_includes/layout/menu-main.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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

<nav class="mt-3" aria-label="<?php echo _('Main Menu'); ?>">
    <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false" id="navigation">
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/" class="nav-link<?php if ($software_section == "dashboard") echo " active"; ?>" aria-label="<?php echo _('Dashboard'); ?>">
                <i class="nav-icon bi bi-speedometer2"></i>
                <p>
                    <?php echo _('Dashboard'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/domains/" class="nav-link<?php if ($software_section == "domains") echo " active"; ?>" aria-label="<?php echo _('Domains'); ?>">
                <i class="nav-icon bi bi-diagram-3-fill"></i>
                <p>
                    <?php echo _('Domains'); ?>
                </p>
            </a>
        </li>
        <?php 
	    $domains_in_list_queue = $_SESSION['s_domains_in_list_queue'] ?? '';
	    $domains_in_queue = $_SESSION['s_domains_in_queue'] ?? '';
	    if ($domains_in_list_queue == '1' || $domains_in_queue == '1') { ?>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/queue/" class="nav-link<?php if ($software_section == "queue") echo " active"; ?>" aria-label="<?php echo _('Queue'); ?>">
                <i class="nav-icon bi bi-hourglass-split"></i>
                <p>
                    <?php echo _('Queue'); ?>
                </p>
            </a>
        </li>
        <?php } ?>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/ssl/" class="nav-link<?php if ($software_section == "ssl") echo " active"; ?>" aria-label="<?php echo _('SSL Certificates'); ?>">
                <i class="nav-icon bi bi-lock-fill"></i>
                <p>
                    <?php echo _('SSL Certificates'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/reporting/" class="nav-link<?php if ($software_section == "reporting") echo " active"; ?>" aria-label="<?php echo _('Reporting'); ?>">
                <i class="nav-icon bi bi-bar-chart-fill"></i>
                <p>
                    <?php echo _('Reporting'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/segments/" class="nav-link<?php if ($software_section == "segments") echo " active"; ?>" aria-label="<?php echo _('Segments'); ?>">
                <i class="nav-icon bi bi-funnel-fill"></i>
                <p>
                    <?php echo _('Segments'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/bulk/" class="nav-link<?php if ($software_section == "bulk") echo " active"; ?>" aria-label="<?php echo _('Bulk Updater'); ?>">
                <i class="nav-icon bi bi-files"></i>
                <p>
                    <?php echo _('Bulk Updater'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/assets/" class="nav-link<?php if ($software_section == "assets") echo " active"; ?>" aria-label="<?php echo _('Assets'); ?>">
                <i class="nav-icon bi bi-boxes"></i>
                <p>
                    <?php echo _('Assets'); ?>
                </p>
            </a>
        </li>

        <?php if (isset($_SESSION['s_is_admin']) && $_SESSION['s_is_admin'] === 1) { ?>
            <li class="nav-item">
                <a href="<?php echo $web_root; ?>/admin/dw/" class="nav-link<?php if ($software_section == "dw") echo " active"; ?>" aria-label="<?php echo _('Data Warehouse'); ?>">
                    <i class="nav-icon bi bi-database-fill"></i>
                    <p>
                        <?php echo _('Data Warehouse'); ?>
                    </p>
                </a>
            </li>
        <?php } ?>
        <li class="nav-item<?php if ($software_section == "settings") echo " menu-open"; ?>">
            <a href="#" class="nav-link<?php if ($software_section == "settings") echo " active"; ?>" aria-label="<?php echo _('Settings'); ?>">
                <i class="nav-icon bi bi-gear-fill"></i>
                <p>
                    <?php echo _('Settings') ?>
                    <i class="nav-arrow bi bi-chevron-right"></i>
                </p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/settings/profile/" class="nav-link<?php if ($slug == "settings-profile") echo " active"; ?>" aria-label="<?php echo _('User Profile'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('User Profile') ?></p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/settings/password/" class="nav-link<?php if ($slug == "settings-password") echo " active"; ?>" aria-label="<?php echo _('Change Password'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Change Password') ?></p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/settings/display/" class="nav-link<?php if ($slug == "settings-display") echo " active"; ?>" aria-label="<?php echo _('Display Settings'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Display Settings') ?></p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/settings/defaults/" class="nav-link<?php if ($slug == "settings-defaults") echo " active"; ?>" aria-label="<?php echo _('User Defaults'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('User Defaults') ?></p>
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-item<?php if ($software_section == "maintenance") echo " menu-open"; ?>">
            <a href="#" class="nav-link<?php if ($software_section == "maintenance") echo " active"; ?>" aria-label="<?php echo _('Fees & Rates'); ?>">
                <i class="nav-icon bi bi-check-lg"></i>
                <p>
                    <?php echo _('Fees & Rates') ?>
                    <i class="nav-arrow bi bi-chevron-right"></i>
                </p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/maintenance/update-conversions.php?<?php echo (new DomainMOD\Csrf())->query(); ?>" class="nav-link" aria-label="<?php echo _('Update Conversion Rates'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Update Conversion Rates') ?></p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/maintenance/update-domain-fees.php?<?php echo (new DomainMOD\Csrf())->query(); ?>" class="nav-link" aria-label="<?php echo _('Update Domain Fees'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Update Domain Fees') ?></p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/maintenance/update-ssl-fees.php?<?php echo (new DomainMOD\Csrf())->query(); ?>" class="nav-link" aria-label="<?php echo _('Update SSL Fees'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Update SSL Fees') ?></p>
                    </a>
                </li>
            </ul>
        </li>

        <?php if (isset($_SESSION['s_is_admin']) && $_SESSION['s_is_admin'] === 1) { ?>
            <li class="nav-item<?php if ($software_section == "admin") echo " menu-open"; ?>">
                <a href="#" class="nav-link<?php if ($software_section == "admin") echo " active"; ?>" aria-label="<?php echo _('Administration'); ?>">
                    <i class="nav-icon bi bi-wrench"></i>
                    <p>
                        <?php echo _('Administration') ?>
                        <i class="nav-arrow bi bi-chevron-right"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/settings/" class="nav-link<?php if ($slug == "admin-settings") echo " active"; ?>" aria-label="<?php echo _('System Settings'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('System Settings') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/defaults/" class="nav-link<?php if ($slug == "admin-defaults") echo " active"; ?>" aria-label="<?php echo _('System Defaults'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('System Defaults') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/users/" class="nav-link<?php if ($slug == "admin-users-main" || $slug == "admin-users-add" || $slug == 'admin-users-edit') echo " active"; ?>" aria-label="<?php echo _('Users'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Users') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/domain-fields/" class="nav-link<?php if ($slug == "admin-custom-domain-fields" || $slug == 'admin-add-custom-domain-field' || $slug == 'admin-edit-custom-domain-field') echo " active"; ?>" aria-label="<?php echo _('Custom Domain Fields'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Custom Domain Fields') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/ssl-fields/" class="nav-link<?php if ($slug == "admin-custom-ssl-fields" || $slug == "admin-add-custom-ssl-field" || $slug == "admin-edit-custom-ssl-field") echo " active"; ?>" aria-label="<?php echo _('Custom SSL Fields'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Custom SSL Fields') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/scheduler/" class="nav-link<?php if ($slug == "admin-scheduler-main") echo " active"; ?>" aria-label="<?php echo _('Task Scheduler'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Task Scheduler') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/maintenance/" class="nav-link<?php if ($slug == "admin-maintenance-main") echo " active"; ?>" aria-label="<?php echo _('Maintenance'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Maintenance') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/tools/" class="nav-link<?php if ($slug == "admin-tools-main") echo " active"; ?>" aria-label="<?php echo _('Tools'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Tools') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/backup/" class="nav-link<?php if ($slug == "admin-backup-main") echo " active"; ?>" aria-label="<?php echo _('Backup & Restore'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Backup & Restore') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/debug-log/" class="nav-link<?php if ($slug == "admin-debug-log-main") echo " active"; ?>" aria-label="<?php echo _('Debug Log'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Debug Log') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/info/" class="nav-link<?php if ($slug == "admin-info") echo " active"; ?>" aria-label="<?php echo _('System Information'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('System Information') ?></p>
                        </a>
                    </li>
                </ul>
            </li>
        <?php } ?>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/logout.php" class="nav-link" aria-label="<?php echo _('Logout'); ?>">
                <i class="nav-icon bi bi-box-arrow-right"></i>
                <p>
                    <?php echo _('Logout'); ?>
                </p>
            </a>
        </li>
        <li class="nav-header"><?php echo _('HELP') ?></li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/docs/userguide/" class="nav-link" aria-label="<?php echo _('Documentation'); ?>">
                <i class="nav-icon bi bi-book-fill"></i>
                <p>
                    <?php echo _('Documentation'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="https://domainmod.org/support/" class="nav-link" aria-label="<?php echo _('Support'); ?>">
                <i class="nav-icon bi bi-life-preserver"></i>
                <p>
                    <?php echo _('Support'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="https://domainmod.org/news/" class="nav-link" aria-label="<?php echo _('News'); ?>">
                <i class="nav-icon bi bi-newspaper"></i>
                <p>
                    <?php echo _('News'); ?>
                </p>
            </a>
        </li>
<?php /* ?>
        <li class="nav-item">
            <a href="https://domainmod.org/contribute/" class="nav-link" aria-label="<?php echo _('Contribute'); ?>">
                <i class="nav-icon bi bi-hand-thumbs-up-fill"></i>
                <p>
                    <?php echo _('Contribute'); ?>
                </p>
            </a>
        </li>
<?php */ ?>
    </ul>
</nav>
<!-- /.sidebar-menu -->
