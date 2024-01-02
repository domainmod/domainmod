<?php
/**
 * /raw.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/_includes/start-session.inc.php';
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$_SESSION['s_raw_list_type'] = $_SESSION['s_raw_list_type'] ?? '';
if ($_SESSION['s_raw_list_type'] == 'domains') {

    $page_title = _('Domains (Raw List)');
    $software_section = "domains";

} elseif ($_SESSION['s_raw_list_type'] == 'ssl-certs') {

    $page_title = _('SSL Certificates (Raw List)');
    $software_section = "ssl-certs";

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition text-sm<?php echo $layout->bodyDarkMode(); ?>">
<?php
$result = $pdo->query($_SESSION['s_raw_list_query'])->fetchAll();

if ($_SESSION['s_raw_list_type'] == 'domains') {

    foreach ($result as $row) {

        echo $row->domain . "<BR>";

    }

} elseif ($_SESSION['s_raw_list_type'] == 'ssl-certs') {

    foreach ($result as $row) {

        echo $row->name . "<BR>";

    }

}
?>
</body>
</html>
