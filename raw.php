<?php
/**
 * /raw.php
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
require_once __DIR__ . '/_includes/start-session.inc.php';
require_once __DIR__ . '/_includes/init.inc.php';

require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/database.inc.php';

$system->authCheck();

if ($_SESSION['s_raw_list_type'] == 'domains') {

    $page_title = "Domains (Raw List)";
    $software_section = "domains";

} elseif ($_SESSION['s_raw_list_type'] == 'ssl-certs') {

    $page_title = "SSL Certificates (Raw List)";
    $software_section = "ssl-certs";

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body>
<?php
$result = mysqli_query($dbcon, $_SESSION['s_raw_list_query']);

if ($_SESSION['s_raw_list_type'] == 'domains') {

    while ($row = mysqli_fetch_object($result)) {

        echo $row->domain . "<BR>";

    }

} elseif ($_SESSION['s_raw_list_type'] == 'ssl-certs') {

    while ($row = mysqli_fetch_object($result)) {

        echo $row->name . "<BR>";

    }

}
?>
</body>
</html>
