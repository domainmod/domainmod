<?php
/**
 * /reporting/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2021 Greg Chetcuti <greg@chetcuti.com>
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

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-main.inc.php';

$system->authCheck();

$report = $_REQUEST['report'];
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php echo $layout->jumpMenu(); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo sprintf(_('Before running any reports you should %supdate the conversion rates%s.'), '<a href="' . $web_root . '/maintenance/update-conversions.php">', '</a>'); ?>

<h3><?php echo _('Domain Reports'); ?></h3>
<?php
echo $form->showFormTop('');
echo $form->showDropdownTopJump('', '', '', '');
echo $form->showDropdownOptionJump($web_root . '/reporting/', '', _('Click to select a Domain Report'), '');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-category.php', '', _('Cost by Category'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-dns.php', '', _('Cost by DNS Profile'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-ip-address.php', '', _('Cost by IP Address'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-month.php', '', _('Cost by Month'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-owner.php', '', _('Cost by Owner'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-registrar.php', '', _('Cost by Registrar'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-tld.php', '', _('Cost by TLD'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-host.php', '', _('Cost by Web Host'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/registrar-fees.php?all=0', '', _('Registrar Fees'), 'null');
echo $form->showDropdownBottom('');
echo $form->showFormBottom('');
?>

<h3><?php echo _('SSL Certificate Reports'); ?></h3>
<?php
echo $form->showFormTop('');
echo $form->showDropdownTopJump('', '', '', '');
echo $form->showDropdownOptionJump($web_root . '/reporting/', '', _('Click to select an SSL Report'), '');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-category.php', '', _('Cost by Category'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-domain.php', '', _('Cost by Domain'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-ip-address.php', '', _('Cost by IP Address'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-month.php', '', _('Cost by Month'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-owner.php', '', _('Cost by Owner'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-provider.php', '', _('Cost by Provider'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-type.php', '', _('Cost by Type'), 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/provider-fees.php?all=0', '', _('Provider Fees'), 'null');
echo $form->showDropdownBottom('');
echo $form->showFormBottom('');
?>

<h3><?php echo _('Data Warehouse Reports'); ?></h3>
<?php
echo $form->showFormTop('');
echo $form->showDropdownTopJump('', '', '', '');
echo $form->showDropdownOptionJump($web_root . '/reporting/', '', _('Click to select a DW Report'), '');
echo $form->showDropdownOptionJump($web_root . '/reporting/dw/potential-problems.php?generate=1', '', _('Potential Problems'), 'null');
echo $form->showDropdownBottom('');
echo $form->showFormBottom('');
?>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
