<?php
/**
 * /maintenance/index.php
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

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/maintenance-main.inc.php';

$system->authCheck();
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo $layout->contentBoxTop(_('Update Fees & Rates'), '3'); ?>
<div id="domainmod-maintenance-result" data-domainmod-focus></div>
<?php echo $layout->htmxTaskLink('update-conversions.php?' . (new DomainMOD\Csrf())->query(), _('Update Conversion Rates'), '#domainmod-maintenance-result', 'innerHTML', 'domainmod-maintenance-working-1') . $layout->htmxSpinner('domainmod-maintenance-working-1'); ?><BR><BR>
<?php echo $layout->htmxTaskLink('update-domain-fees.php?' . (new DomainMOD\Csrf())->query(), _('Update Domain Fees'), '#domainmod-maintenance-result', 'innerHTML', 'domainmod-maintenance-working-2') . $layout->htmxSpinner('domainmod-maintenance-working-2'); ?><BR><BR>
<?php echo $layout->htmxTaskLink('update-ssl-fees.php?' . (new DomainMOD\Csrf())->query(), _('Update SSL Fees'), '#domainmod-maintenance-result', 'innerHTML', 'domainmod-maintenance-working-3') . $layout->htmxSpinner('domainmod-maintenance-working-3'); ?><BR><?php
// Hosting's fees, once the database has the hosting tables
if (DomainMOD\Hosting::isAvailable()) { ?>
<BR>
<?php echo $layout->htmxTaskLink('update-hosting-fees.php?' . (new DomainMOD\Csrf())->query(), _('Update Hosting Fees'), '#domainmod-maintenance-result', 'innerHTML', 'domainmod-maintenance-working-4') . $layout->htmxSpinner('domainmod-maintenance-working-4'); ?><BR><?php
} ?>
<?php echo $layout->contentBoxBottom(); ?>
<?php //@formatter:on ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
