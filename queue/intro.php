<?php
/**
 * /queue/intro.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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

require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/queue-info.inc.php';
require_once DIR_INC . '/database.inc.php';

$pdo = $system->db();
$system->authCheck();
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
The Domain Queue allows you to use your domain registrar's API to automatically import the details of your domains, such as expiry date and DNS servers, which are then added to your main DomainMOD database along with your domains. All you have to do is choose the registrar account, supply a list of domains, and the rest of the work is done for you. Depending on the registrar, you may not even have to supply the list of domains.<BR>
<BR>
If you use a registrar that isn't already supported, and they have an API, send us an email at <a href="mailto:suggestions@domainmod.org">suggestions@domainmod.org</a> and we'll see what we can do about adding it.<BR>
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
<strong>Currently Supported Registrars</strong>: <?php echo $supported_registrars; ?><BR>
<BR>
<strong>NOTE:</strong> In order to use the Domain Queue you must setup the cron job that comes with DomainMOD. For more information please see the <a target="_blank" href="https://domainmod.org/docs/userguide/getting-started/#cron-job">User Guide</a>.<BR>
<BR>
<a href="<?php echo $web_root; ?>/queue/add.php"><?php echo $layout->showButton('button', 'Add Domains To Queue'); ?></a>
<BR><BR>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
