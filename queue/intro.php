<?php
/**
 * /queue/intro.php
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
$queue = new DomainMOD\DomainQueue();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/queue-info.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;
$queue_rdap_available = $queue->getRdapApiRegistrarId() > 0;
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<div class="domainmod-intro">
<?php
if ($queue_rdap_available) {

    echo '<p>' . sprintf(_('The Domain Queue imports the details of your domains, such as their expiry dates and DNS servers, and adds them to your main %s database along with your domains. There are two ways to use it.'), SOFTWARE_TITLE) . '</p>';
    echo '<p>' . sprintf(_("If %s supports your registrar's API, choose the registrar account and supply a list of domains, and the rest of the work is done for you. Depending on the registrar, you may not even have to supply the list of domains."), SOFTWARE_TITLE) . '</p>';
    echo '<p>' . _("If your registrar isn't supported, paste a list of your domains and their details are looked up using RDAP, the public replacement for WHOIS, or WHOIS itself for domains RDAP doesn't cover. WHOIS privacy and auto renewal are set to No, since neither one includes them.") . '</p>';

} else {

    echo '<p>' . sprintf(_("The Domain Queue allows you to use your domain registrar's API to automatically import the details of your domains, such as expiry date and DNS servers, which are then added to your main %s database along with your domains."), SOFTWARE_TITLE) . ' ';
    echo _('All you have to do is choose the registrar account, supply a list of domains, and the rest of the work is done for you. Depending on the registrar, you may not even have to supply the list of domains.') . '</p>';

} ?>
<p><?php echo sprintf(_("If you use a registrar that isn't already supported, and they have an API, send us an email at %s and we'll see what we can do about adding it."), '<a href="mailto:suggestions@domainmod.org">suggestions@domainmod.org</a>'); ?></p>
<?php
$result = $pdo->query("
    SELECT `name`
    FROM api_registrars
    WHERE `name` != 'RDAP'
    ORDER BY name ASC")->fetchAll();
?>
<?php // The supported registrars in a box that starts closed (like the upgrade notice's requirements), headed with the count: read down each column in alphabetical order, two columns on a phone and three on wider screens, with each name kept left to right ?>
<details class="card mb-3 domainmod-message-why">
    <summary class="card-header fw-semibold"><?php echo _('Currently Supported Registrars') . ' (' . number_format(count($result)) . ')'; ?></summary>
    <div class="card-body">
        <ul class="domainmod-column-list list-unstyled mb-0"><?php

        foreach ($result as $row) { ?>

            <li><?php echo $layout->ltr($row->name); ?></li><?php

        } ?>

        </ul>
    </div>
</details>
<?php // Setting up the cron job, as a slim note (like the warnings on other pages, it isn't a page message, so it isn't announced when the page loads) ?>
<div class="alert alert-info d-flex align-items-start gap-2 py-2">
    <i class="bi bi-info-circle-fill domainmod-message-icon" aria-hidden="true"></i>
    <div class="domainmod-message-text"><?php echo sprintf(_('In order to use the Domain Queue you must set up the cron job that comes with %s.'), SOFTWARE_TITLE) . ' ' . sprintf(_('For more information, please see the %sUser Guide%s.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/getting-started/#cron-job">', '</a>'); ?></div>
</div>
<?php // The main way in as the one red button, and the other as an outline button beside it ?>
<div class="d-flex flex-wrap gap-2 mt-3 mb-3"><?php
echo $layout->linkButton($web_root . '/queue/add/', $queue_rdap_available ? _('Add Domains Using an API') : _('Add Domains To Queue'), 'primary');
if ($queue_rdap_available) echo $layout->linkButton($web_root . '/queue/add/rdap.php', _('Add Domains Without an API')); ?>
</div>
</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
