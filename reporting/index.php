<?php
/**
 * /reporting/index.php
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
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$form = new DomainMOD\Form();
$layout = new DomainMOD\Layout();
$system = new DomainMOD\System();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-main.inc.php';

$system->authCheck();

$report = $_REQUEST['report'];

echo $layout->jumpMenu();
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
Before running any reports you should <a href="<?php echo $web_root; ?>/maintenance/update-conversions.php">update the conversion rates</a>.

<h3>Domain Reports</h3>
<?php
echo $form->showFormTop('');
echo $form->showDropdownTopJump('', '', '', '');
echo $form->showDropdownOptionJump($web_root . '/reporting/', '', 'Click to select a Domain Report', '');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-category.php?all=1', '', 'Cost by Category', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-dns.php?all=1', '', 'Cost by DNS Profile', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-ip-address.php?all=1', '', 'Cost by IP Address', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-month.php?all=1', '', 'Cost by Month', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-owner.php?all=1', '', 'Cost by Owner', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-registrar.php?all=1', '', 'Cost by Registrar', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-tld.php?all=1', '', 'Cost by TLD', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/cost-by-host.php?all=1', '', 'Cost by Web Host', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/domains/registrar-fees.php?all=0', '', 'Registrar Fees', 'null');
echo $form->showDropdownBottom('');
echo $form->showFormBottom('');
?>

<h3>SSL Certificate Reports</h3>
<?php
echo $form->showFormTop('');
echo $form->showDropdownTopJump('', '', '', '');
echo $form->showDropdownOptionJump($web_root . '/reporting/', '', 'Click to select an SSL Report', '');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-category.php?all=1', '', 'Cost by Category', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-domain.php?all=1', '', 'Cost by Domain', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-ip-address.php?all=1', '', 'Cost by IP Address', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-month.php?all=1', '', 'Cost by Month', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-owner.php?all=1', '', 'Cost by Owner', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-provider.php?all=1', '', 'Cost by Provider', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/cost-by-type.php?all=1', '', 'Cost by Type', 'null');
echo $form->showDropdownOptionJump($web_root . '/reporting/ssl/provider-fees.php?all=0', '', 'Provider Fees', 'null');
echo $form->showDropdownBottom('');
echo $form->showFormBottom('');
?>

<h3>Data Warehouse Reports</h3>
<?php
echo $form->showFormTop('');
echo $form->showDropdownTopJump('', '', '', '');
echo $form->showDropdownOptionJump($web_root . '/reporting/', '', 'Click to select a DW Report', '');
echo $form->showDropdownOptionJump($web_root . '/reporting/dw/potential-problems.php?generate=1', '', 'Potential Problems', 'null');
echo $form->showDropdownBottom('');
echo $form->showFormBottom('');
?>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
