<?php
/**
 * /assets/index.php
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
<?php
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-main.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

/*
 * How many rows each asset list shows, for the tiles. With Display Inactive Assets off, the lists leave out the rows
 * nothing uses, so each count uses the same check as its list (a domain whose active isn't 0 or 10, or an SSL
 * certificate whose active isn't 0). The two account lists group their rows, so they're counted the same way.
 */
$show_all = ($_SESSION['s_display_inactive_assets'] ?? '0') == '1';
$domain_used = function ($column, $id) use ($show_all) {
    return $show_all ? '' : " WHERE EXISTS (SELECT 1 FROM domains AS d WHERE d.$column = $id AND d.active NOT IN ('0', '10'))";
};
$cert_used = function ($column, $id) use ($show_all) {
    return $show_all ? '' : " WHERE EXISTS (SELECT 1 FROM ssl_certs AS sc WHERE sc.$column = $id AND sc.active NOT IN ('0'))";
};
$either_used = function ($column, $id) use ($show_all) {
    return $show_all ? '' : " WHERE EXISTS (SELECT 1 FROM domains AS d WHERE d.$column = $id AND d.active NOT IN ('0', '10'))
                                OR EXISTS (SELECT 1 FROM ssl_certs AS sc WHERE sc.$column = $id AND sc.active NOT IN ('0'))";
};

$counts = $pdo->query("
    SELECT 'registrars' AS asset, COUNT(*) AS total FROM registrars AS a" . $domain_used('registrar_id', 'a.id') . "
    UNION ALL
    SELECT 'registrar_accounts', COUNT(*) FROM (
        SELECT ra.id AS raid
        FROM registrar_accounts AS ra, owners AS o, registrars AS r
        WHERE ra.owner_id = o.id
          AND ra.registrar_id = r.id
        GROUP BY ra.username, o.name, r.name) AS a" . $domain_used('account_id', 'a.raid') . "
    UNION ALL
    SELECT 'dns', COUNT(*) FROM dns AS a" . $domain_used('dns_id', 'a.id') . "
    UNION ALL
    SELECT 'hosting', COUNT(*) FROM hosting AS a" . $domain_used('hosting_id', 'a.id') . "
    UNION ALL
    SELECT 'ssl_providers', COUNT(*) FROM ssl_providers AS a" . $cert_used('ssl_provider_id', 'a.id') . "
    UNION ALL
    SELECT 'ssl_accounts', COUNT(*) FROM (
        SELECT sa.id AS sslpaid
        FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp
        WHERE sa.owner_id = o.id
          AND sa.ssl_provider_id = sslp.id
        GROUP BY sa.username, o.name, sslp.name) AS a" . $cert_used('account_id', 'a.sslpaid') . "
    UNION ALL
    SELECT 'ssl_cert_types', COUNT(*) FROM ssl_cert_types AS a" . $cert_used('type_id', 'a.id') . "
    UNION ALL
    SELECT 'owners', COUNT(*) FROM owners AS a" . $either_used('owner_id', 'a.id') . "
    UNION ALL
    SELECT 'categories', COUNT(*) FROM categories AS a" . $either_used('cat_id', 'a.id') . "
    UNION ALL
    SELECT 'ip_addresses', COUNT(*) FROM ip_addresses AS a" . $either_used('ip_id', 'a.id'))->fetchAll(PDO::FETCH_KEY_PAIR);

// Each tile's count line is its own ngettext() call with literal strings, so gettext finds both forms
$total = function ($asset) use ($counts) {
    return (int) ($counts[$asset] ?? 0);
};
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>

<?php echo $layout->calloutTop(); ?>
<?php echo _('Assets are the building blocks of DomainMOD.'); ?>&nbsp;<?php echo _('Assets include things like the domain registrars and accounts that you use, the SSL certificate types that you own, your DNS Servers and IP Addresses, the categories you want to use for grouping similar domains, and so on.'); ?><BR>
<?php echo $layout->calloutBottom(); ?>

<h2 class="h5 mt-4"><?php echo _('Domains'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/assets/registrars.php', _('Domain Registrars'), _('The registrars your domains are with'), 'bi-building', 'secondary', sprintf(ngettext('%s registrar', '%s registrars', $total('registrars')), number_format($total('registrars'))));
    echo $layout->landingTile('/assets/registrar-accounts.php', _('Domain Registrar Accounts'), _('Your accounts at each registrar'), 'bi-person-badge', 'secondary', sprintf(ngettext('%s account', '%s accounts', $total('registrar_accounts')), number_format($total('registrar_accounts'))));
    echo $layout->landingTile('/assets/dns.php', _('DNS Profiles'), _('The DNS servers your domains use'), 'bi-hdd-network', 'secondary', sprintf(ngettext('%s DNS profile', '%s DNS profiles', $total('dns')), number_format($total('dns'))));
    echo $layout->landingTile('/assets/hosting.php', _('Web Hosting Providers'), _('Where your websites are hosted'), 'bi-server', 'secondary', sprintf(ngettext('%s web host', '%s web hosts', $total('hosting')), number_format($total('hosting')))); ?>
</div>

<h2 class="h5 mt-4"><?php echo _('SSL Certificates'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/assets/ssl-providers.php', _('SSL Providers'), _('The providers your SSL certificates are from'), 'bi-shield-lock', 'secondary', sprintf(ngettext('%s SSL provider', '%s SSL providers', $total('ssl_providers')), number_format($total('ssl_providers'))));
    echo $layout->landingTile('/assets/ssl-accounts.php', _('SSL Provider Accounts'), _('Your accounts at each SSL provider'), 'bi-person-lock', 'secondary', sprintf(ngettext('%s account', '%s accounts', $total('ssl_accounts')), number_format($total('ssl_accounts'))));
    echo $layout->landingTile('/assets/ssl-types.php', _('SSL Certificate Types'), _('The kinds of SSL certificate you track'), 'bi-patch-check', 'secondary', sprintf(ngettext('%s type', '%s types', $total('ssl_cert_types')), number_format($total('ssl_cert_types')))); ?>
</div>

<h2 class="h5 mt-4"><?php echo _('Shared'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/assets/account-owners.php', _('Account Owners'), _('The people and companies that own your accounts'), 'bi-people', 'secondary', sprintf(ngettext('%s owner', '%s owners', $total('owners')), number_format($total('owners'))));
    echo $layout->landingTile('/assets/categories.php', _('Categories'), _('Groups for sorting your domains and SSL certificates'), 'bi-folder', 'secondary', sprintf(ngettext('%s category', '%s categories', $total('categories')), number_format($total('categories'))));
    echo $layout->landingTile('/assets/ip-addresses.php', _('IP Addresses'), _('The IP addresses your domains and SSL certificates point to'), 'bi-globe', 'secondary', sprintf(ngettext('%s IP address', '%s IP addresses', $total('ip_addresses')), number_format($total('ip_addresses')))); ?>
</div>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
