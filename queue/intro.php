<?php
/**
 * /queue/intro.php
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
require_once DIR_INC . '/settings/queue-info.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo sprintf(_("The Domain Queue allows you to use your domain registrar's API to automatically import the details of your domains, such as expiry date and DNS servers, which are then added to your main %s database along with your domains."), SOFTWARE_TITLE) . '&nbsp;'; ?>
<?php echo _('All you have to do is choose the registrar account, supply a list of domains, and the rest of the work is done for you. Depending on the registrar, you may not even have to supply the list of domains.'); ?><BR>
<BR>
<?php echo sprintf(_("If you use a registrar that isn't already supported, and they have an API, send us an email at %s and we'll see what we can do about adding it."), '<a href="mailto:suggestions@domainmod.org">suggestions@domainmod.org</a>'); ?><BR>
<BR>
<?php
$result = $pdo->query("
    SELECT `name`
    FROM api_registrars
    ORDER BY name ASC")->fetchAll();

foreach ($result as $row) {

    $supported_registrars .= ', ' . $row->name;

}

$supported_registrars = substr($supported_registrars, 2);
?>
<strong><?php echo _('Currently Supported Registrars'); ?></strong>: <?php echo $supported_registrars; ?><BR>
<BR>
<strong><?php echo strtoupper(_('Note')); ?>:</strong> <?php echo sprintf(_('In order to use the Domain Queue you must setup the cron job that comes with %s.'), SOFTWARE_TITLE) . '&nbsp;'; ?>
<?php echo sprintf(_('For more information please see the %sUser Guide%s.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/getting-started/#cron-job">', '</a>'); ?><BR>
<BR>
<a href="<?php echo $web_root; ?>/queue/add/"><?php echo $layout->showButton('button', _('Add Domains To Queue')); ?></a>
<BR><BR>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
