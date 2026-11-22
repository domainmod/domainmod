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
        <?php if (DomainMOD\Hosting::isAvailable()) { ?>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/hosting/" class="nav-link<?php if ($software_section == "hosting") echo " active"; ?>" aria-label="<?php echo _('Hosting'); ?>">
                <i class="nav-icon bi bi-hdd-rack-fill"></i>
                <p>
                    <?php echo _('Hosting'); ?>
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
        <?php
        // The Watch List, once the database update that adds it has run, unless it's turned off in System Settings (see
        // DomainMOD\WatchList::isEnabled())
        if (DomainMOD\WatchList::isEnabled()) { ?>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/watch/" class="nav-link<?php if ($software_section == "watch") echo " active"; ?>" aria-label="<?php echo _('Watch List'); ?>">
                <i class="nav-icon bi bi-binoculars"></i>
                <p>
                    <?php echo _('Watch List'); ?>
                </p>
            </a>
        </li>
        <?php } ?>
        <?php
	    // The Import Queue only shows while any of its queues has rows (see SslQueue::inMenu()). Users reach the add pages
	    // from the Domains and SSL Certificates lists and the Dashboard.
	    if (DomainMOD\SslQueue::inMenu($_SESSION)) { ?>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/queue/" class="nav-link<?php if ($software_section == "queue") echo " active"; ?>" aria-label="<?php echo _('Import Queue'); ?>">
                <i class="nav-icon bi bi-hourglass-split"></i>
                <p>
                    <?php echo _('Import Queue'); ?>
                </p>
            </a>
        </li>
        <?php } ?>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/bulk/" class="nav-link<?php if ($software_section == "bulk") echo " active"; ?>" aria-label="<?php echo _('Bulk Updater'); ?>">
                <i class="nav-icon bi bi-files"></i>
                <p>
                    <?php echo _('Bulk Updater'); ?>
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
            <a href="<?php echo $web_root; ?>/reporting/" class="nav-link<?php if ($software_section == "reporting" && $slug != "reporting-domain-fees" && $slug != "reporting-ssl-fees" && $slug != "reporting-hosting-fees") echo " active"; ?>" aria-label="<?php echo _('Reporting'); ?>">
                <i class="nav-icon bi bi-bar-chart-fill"></i>
                <p>
                    <?php echo _('Reporting'); ?>
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/history/" class="nav-link<?php if ($software_section == "history") echo " active"; ?>" aria-label="<?php echo _('Change History'); ?>">
                <i class="nav-icon bi bi-clock-history"></i>
                <p>
                    <?php echo _('Change History'); ?>
                </p>
            </a>
        </li>
        <?php // The fee reports live in Reporting, but they're listed under Assets, so Assets is open and highlighted on
        // those pages.
        //
        // Assets, Settings, Maintenance, and Administration each have a landing page, so their name is a link to it, and
        // their arrow is a separate button that opens and closes the submenu without leaving the page (see
        // js/sidebar-menu.js).
        $menu_assets_open = $software_section == "assets" || $slug == "reporting-domain-fees" || $slug == "reporting-ssl-fees" || $slug == "reporting-hosting-fees"; ?>
        <li class="nav-item domainmod-menu-parent<?php if ($menu_assets_open) echo " menu-open"; ?>">
            <a href="<?php echo $web_root; ?>/assets/" class="nav-link<?php if ($menu_assets_open) echo " active"; ?>" aria-label="<?php echo _('Assets'); ?>">
                <i class="nav-icon bi bi-boxes"></i>
                <p><?php echo _('Assets') ?></p>
            </a>
            <button type="button" class="domainmod-menu-toggle" aria-label="<?php echo _('Show or hide the Assets menu'); ?>" aria-expanded="<?php echo $menu_assets_open ? 'true' : 'false'; ?>" aria-controls="menu-assets">
                <i class="nav-arrow bi bi-chevron-right" aria-hidden="true"></i>
            </button>
            <ul class="nav nav-treeview" id="menu-assets">
                <li class="nav-header"><?php echo _('Domains') ?></li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/registrars.php" class="nav-link<?php if ($slug == "assets-registrars" || $slug == "assets-add-registrar" || $slug == "assets-edit-registrar" || $slug == "assets-registrar-fees" || $slug == "assets-add-registrar-fee" || $slug == "assets-edit-registrar-fee") echo " active"; ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <span aria-hidden="true"><?php echo _('Registrars') ?></span>
                            <span class="visually-hidden"><?php echo _('Domain Registrars') ?></span>
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/registrar-accounts.php" class="nav-link<?php if ($slug == "assets-registrar-accounts" || $slug == "assets-add-registrar-account" || $slug == "assets-edit-registrar-account") echo " active"; ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <?php // TRANSLATORS: Sidebar item under the Domains heading, short for Domain Registrar Accounts ?>
                            <span aria-hidden="true"><?php echo _('Accounts') ?></span>
                            <span class="visually-hidden"><?php echo _('Domain Registrar Accounts') ?></span>
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/dns.php" class="nav-link<?php if ($slug == "assets-dns" || $slug == "assets-add-dns" || $slug == "assets-edit-dns") echo " active"; ?>" aria-label="<?php echo _('DNS Profiles'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('DNS Profiles') ?></p>
                    </a>
                </li>
                <?php
                // Hosting Providers move to their own Hosting group once the hosting section is available
                if (!DomainMOD\Hosting::isAvailable()) { ?>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/hosting.php" class="nav-link<?php if ($slug == "assets-hosting" || $slug == "assets-add-host" || $slug == "assets-edit-host") echo " active"; ?>">
                        <i class="nav-icon bi"></i>
                        <?php // TRANSLATORS: Sidebar item under the Domains heading ?>
                        <p><?php echo _('Hosting Providers') ?></p>
                    </a>
                </li>
                <?php } ?>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/reporting/domains/registrar-fees.php?all=0" class="nav-link<?php if ($slug == "reporting-domain-fees") echo " active"; ?>" aria-label="<?php echo _('Domain Fees'); ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <?php // TRANSLATORS: Sidebar item under the Domains heading, short for Domain Fees ?>
                            <span aria-hidden="true"><?php echo _('Fees') ?></span>
                            <span class="visually-hidden"><?php echo _('Domain Fees') ?></span>
                        </p>
                    </a>
                </li>
                <?php if (DomainMOD\Hosting::isAvailable()) { ?>
                <li class="nav-header"><?php echo _('Hosting') ?></li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/hosting.php" class="nav-link<?php if ($slug == "assets-hosting" || $slug == "assets-add-host" || $slug == "assets-edit-host" || $slug == "assets-hosting-fees" || $slug == "assets-add-hosting-fee" || $slug == "assets-edit-hosting-fee") echo " active"; ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <?php // TRANSLATORS: Sidebar item under the Hosting heading, short for Hosting Providers ?>
                            <span aria-hidden="true"><?php echo _('Providers') ?></span>
                            <span class="visually-hidden"><?php echo _('Hosting Providers') ?></span>
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/hosting-accounts.php" class="nav-link<?php if ($slug == "assets-hosting-accounts" || $slug == "assets-add-hosting-account" || $slug == "assets-edit-hosting-account") echo " active"; ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <?php // TRANSLATORS: Sidebar item under the Hosting heading, short for Hosting Accounts ?>
                            <span aria-hidden="true"><?php echo _('Accounts') ?></span>
                            <span class="visually-hidden"><?php echo _('Hosting Accounts') ?></span>
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/hosting-types.php" class="nav-link<?php if ($slug == "assets-hosting-types" || $slug == "assets-add-hosting-type" || $slug == "assets-edit-hosting-type") echo " active"; ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <?php // TRANSLATORS: Sidebar item under the Hosting heading, short for Hosting Service Types ?>
                            <span aria-hidden="true"><?php echo _('Types') ?></span>
                            <span class="visually-hidden"><?php echo _('Hosting Service Types') ?></span>
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/reporting/hosting/provider-fees.php?all=0" class="nav-link<?php if ($slug == "reporting-hosting-fees") echo " active"; ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <?php // TRANSLATORS: Sidebar item under the Hosting heading, short for Hosting Fees ?>
                            <span aria-hidden="true"><?php echo _('Fees') ?></span>
                            <span class="visually-hidden"><?php echo _('Hosting Fees') ?></span>
                        </p>
                    </a>
                </li>
                <?php } ?>
                <li class="nav-header"><?php echo _('SSL Certificates') ?></li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/ssl-providers.php" class="nav-link<?php if ($slug == "assets-ssl-providers" || $slug == "assets-add-ssl-provider" || $slug == "assets-edit-ssl-provider" || $slug == "assets-ssl-provider-fees" || $slug == "assets-add-ssl-provider-fee" || $slug == "assets-edit-ssl-provider-fee") echo " active"; ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <?php // TRANSLATORS: Sidebar item under the SSL Certificates heading, short for SSL Providers ?>
                            <span aria-hidden="true"><?php echo _('Providers') ?></span>
                            <span class="visually-hidden"><?php echo _('SSL Providers') ?></span>
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/ssl-accounts.php" class="nav-link<?php if ($slug == "assets-ssl-accounts" || $slug == "assets-add-ssl-account" || $slug == "assets-edit-ssl-account") echo " active"; ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <?php // TRANSLATORS: Sidebar item under the SSL Certificates heading, short for SSL Provider Accounts ?>
                            <span aria-hidden="true"><?php echo _('Accounts') ?></span>
                            <span class="visually-hidden"><?php echo _('SSL Provider Accounts') ?></span>
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/ssl-types.php" class="nav-link<?php if ($slug == "assets-ssl-types" || $slug == "assets-add-ssl-type" || $slug == "assets-edit-ssl-type") echo " active"; ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <?php // TRANSLATORS: Sidebar item under the SSL Certificates heading, short for SSL Certificate Types ?>
                            <span aria-hidden="true"><?php echo _('Types') ?></span>
                            <span class="visually-hidden"><?php echo _('SSL Certificate Types') ?></span>
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/reporting/ssl/provider-fees.php?all=0" class="nav-link<?php if ($slug == "reporting-ssl-fees") echo " active"; ?>" aria-label="<?php echo _('SSL Fees'); ?>">
                        <i class="nav-icon bi"></i>
                        <p>
                            <?php // TRANSLATORS: Sidebar item under the SSL Certificates heading, short for SSL Fees ?>
                            <span aria-hidden="true"><?php echo _('Fees') ?></span>
                            <span class="visually-hidden"><?php echo _('SSL Fees') ?></span>
                        </p>
                    </a>
                </li>
                <li class="nav-header"><?php echo _('Shared') ?></li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/account-owners.php" class="nav-link<?php if ($slug == "assets-owners" || $slug == "assets-add-owner" || $slug == "assets-edit-owner") echo " active"; ?>" aria-label="<?php echo _('Account Owners'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Account Owners') ?></p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/categories.php" class="nav-link<?php if ($slug == "assets-categories" || $slug == "assets-add-category" || $slug == "assets-edit-category") echo " active"; ?>" aria-label="<?php echo _('Categories'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Categories') ?></p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/assets/ip-addresses.php" class="nav-link<?php if ($slug == "assets-ip-addresses" || $slug == "assets-add-ip-address" || $slug == "assets-edit-ip-address") echo " active"; ?>" aria-label="<?php echo _('IP Addresses'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('IP Addresses') ?></p>
                    </a>
                </li>
            </ul>
        </li>
        <?php $menu_settings_open = $software_section == "settings"; ?>
        <li class="nav-item domainmod-menu-parent<?php if ($menu_settings_open) echo " menu-open"; ?>">
            <a href="<?php echo $web_root; ?>/settings/" class="nav-link<?php if ($menu_settings_open) echo " active"; ?>" aria-label="<?php echo _('Settings'); ?>">
                <i class="nav-icon bi bi-gear-fill"></i>
                <p><?php echo _('Settings') ?></p>
            </a>
            <button type="button" class="domainmod-menu-toggle" aria-label="<?php echo _('Show or hide the Settings menu'); ?>" aria-expanded="<?php echo $menu_settings_open ? 'true' : 'false'; ?>" aria-controls="menu-settings">
                <i class="nav-arrow bi bi-chevron-right" aria-hidden="true"></i>
            </button>
            <ul class="nav nav-treeview" id="menu-settings">
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/settings/profile/" class="nav-link<?php if ($slug == "settings-profile") echo " active"; ?>" aria-label="<?php echo _('User Profile'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('User Profile') ?></p>
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
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/settings/two-factor/" class="nav-link<?php if ($slug == "settings-two-factor") echo " active"; ?>" aria-label="<?php echo _('Security'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Security') ?></p>
                    </a>
                </li>
            </ul>
        </li>
        <?php
        // Maintenance has the fee and rate updates. Read-only users can't run them, so they don't get the section at all.
        if (($_SESSION['s_read_only'] ?? '') != '1') {
            $menu_maintenance_open = $software_section == "maintenance"; ?>
        <li class="nav-item domainmod-menu-parent<?php if ($menu_maintenance_open) echo " menu-open"; ?>">
            <a href="<?php echo $web_root; ?>/maintenance/" class="nav-link<?php if ($menu_maintenance_open) echo " active"; ?>" aria-label="<?php echo _('Maintenance'); ?>">
                <i class="nav-icon bi bi-tools"></i>
                <p><?php echo _('Maintenance') ?></p>
            </a>
            <button type="button" class="domainmod-menu-toggle" aria-label="<?php echo _('Show or hide the Maintenance menu'); ?>" aria-expanded="<?php echo $menu_maintenance_open ? 'true' : 'false'; ?>" aria-controls="menu-maintenance">
                <i class="nav-arrow bi bi-chevron-right" aria-hidden="true"></i>
            </button>
            <ul class="nav nav-treeview" id="menu-maintenance">
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/maintenance/update-domain-fees.php?<?php echo (new DomainMOD\Csrf())->query(); ?>" class="nav-link" aria-label="<?php echo _('Update Domain Fees'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Update Domain Fees') ?></p>
                    </a>
                </li>
                <?php if (DomainMOD\Hosting::isAvailable()) { ?>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/maintenance/update-hosting-fees.php?<?php echo (new DomainMOD\Csrf())->query(); ?>" class="nav-link" aria-label="<?php echo _('Update Hosting Fees'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Update Hosting Fees') ?></p>
                    </a>
                </li>
                <?php } ?>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/maintenance/update-ssl-fees.php?<?php echo (new DomainMOD\Csrf())->query(); ?>" class="nav-link" aria-label="<?php echo _('Update SSL Fees'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Update SSL Fees') ?></p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/maintenance/update-conversions.php?<?php echo (new DomainMOD\Csrf())->query(); ?>" class="nav-link" aria-label="<?php echo _('Update Conversion Rates'); ?>">
                        <i class="nav-icon bi"></i>
                        <p><?php echo _('Update Conversion Rates') ?></p>
                    </a>
                </li>
            </ul>
        </li>
        <?php } ?>

        <?php if (isset($_SESSION['s_is_admin']) && $_SESSION['s_is_admin'] === 1) {
            $menu_admin_open = $software_section == "admin" || $software_section == "dw"; ?>
            <li class="nav-item domainmod-menu-parent<?php if ($menu_admin_open) echo " menu-open"; ?>">
                <a href="<?php echo $web_root; ?>/admin/" class="nav-link<?php if ($menu_admin_open) echo " active"; ?>" aria-label="<?php echo _('Administration'); ?>">
                    <i class="nav-icon bi bi-wrench"></i>
                    <p><?php echo _('Administration') ?></p>
                </a>
                <button type="button" class="domainmod-menu-toggle" aria-label="<?php echo _('Show or hide the Administration menu'); ?>" aria-expanded="<?php echo $menu_admin_open ? 'true' : 'false'; ?>" aria-controls="menu-admin">
                    <i class="nav-arrow bi bi-chevron-right" aria-hidden="true"></i>
                </button>
                <ul class="nav nav-treeview" id="menu-admin">
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/users/" class="nav-link<?php if ($slug == "admin-users-main" || $slug == "admin-users-add" || $slug == 'admin-users-edit') echo " active"; ?>" aria-label="<?php echo _('Users'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Users') ?></p>
                        </a>
                    </li>
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
                        <a href="<?php echo $web_root; ?>/admin/domain-fields/" class="nav-link<?php if ($slug == "admin-custom-domain-fields" || $slug == 'admin-add-custom-domain-field' || $slug == 'admin-edit-custom-domain-field') echo " active"; ?>" aria-label="<?php echo _('Custom Domain Fields'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Custom Domain Fields') ?></p>
                        </a>
                    </li>
                    <?php if (DomainMOD\Hosting::isAvailable()) { ?>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/hosting-fields/" class="nav-link<?php if ($slug == "admin-custom-hosting-fields" || $slug == "admin-add-custom-hosting-field" || $slug == "admin-edit-custom-hosting-field") echo " active"; ?>" aria-label="<?php echo _('Custom Hosting Fields'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Custom Hosting Fields') ?></p>
                        </a>
                    </li>
                    <?php } ?>
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
                        <a href="<?php echo $web_root; ?>/admin/backup/" class="nav-link<?php if ($slug == "admin-backup-main") echo " active"; ?>" aria-label="<?php echo _('Backup & Restore'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Backup & Restore') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/upgrade/" class="nav-link<?php if ($slug == "admin-upgrade-main") echo " active"; ?>" aria-label="<?php echo _('Upgrade'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Upgrade') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/maintenance/" class="nav-link<?php if ($slug == "admin-maintenance-main") echo " active"; ?>" aria-label="<?php echo _('Cleanup'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Cleanup') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/tools/" class="nav-link<?php if ($slug == "admin-tools-main") echo " active"; ?>" aria-label="<?php echo _('Tools'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Tools') ?></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $web_root; ?>/admin/dw/" class="nav-link<?php if ($software_section == "dw") echo " active"; ?>" aria-label="<?php echo _('Data Warehouse'); ?>">
                            <i class="nav-icon bi"></i>
                            <p><?php echo _('Data Warehouse') ?></p>
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
            <a href="#" class="nav-link" aria-label="<?php echo _('Help'); ?>">
                <i class="nav-icon bi bi-question-circle-fill"></i>
                <p>
                    <?php echo _('Help') ?>
                    <i class="nav-arrow bi bi-chevron-right"></i>
                </p>
            </a>
            <ul class="nav nav-treeview">
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
        </li>
        <li class="nav-item">
            <a href="<?php echo $web_root; ?>/logout.php" class="nav-link" aria-label="<?php echo _('Logout'); ?>">
                <i class="nav-icon bi bi-box-arrow-right"></i>
                <p>
                    <?php echo _('Logout'); ?>
                </p>
            </a>
        </li>
    </ul>
</nav>
<!-- /.sidebar-menu -->
