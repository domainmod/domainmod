<?php
/**
 * /reporting/ssl/costs.php
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
<?php //@formatter:off
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout;
$date = new DomainMOD\Date();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$currency = new DomainMOD\Currency();
$sanitize = new DomainMOD\Sanitize();
$chart = new DomainMOD\Chart();
$dashboard = new DomainMOD\Dashboard();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

// The Group By choice, from the address (an unknown one opens the default grouping)
$cost_section = 'ssl';
$cost_grouping = DomainMOD\CostReport::grouping($cost_section, $_GET['group'] ?? '');
require_once DIR_INC . '/settings/reporting-ssl-costs.inc.php';

// The report itself, the same for Domain Costs and SSL Costs
require_once DIR_INC . '/layout/cost-report.inc.php'; //@formatter:on
