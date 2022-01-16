<?php
/**
 * /ssl/notes.php
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

$system->authCheck();
$pdo = $deeb->cnxx;

$sslcid = (int) $_GET['sslcid'];

$stmt = $pdo->prepare("
    SELECT `name`, notes
    FROM ssl_certs
    WHERE id = :sslcid");
$stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch();
$stmt->closeCursor();

if ($result) {

    $new_name = $result->name;
    $new_notes = $result->notes;

}

$page_title = sprintf(_('SSL Certificate Notes (%s)'), $new_name);
$software_section = "ssl-certs";
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php
$page_align = 'left';
require_once DIR_INC . '/layout/header-bare.inc.php'; ?>
<strong><?php echo sprintf(_('Notes For %s'), $new_name); ?></strong><BR>
<BR>
<?php
$format = new DomainMOD\Format();
echo $format->replaceBreaks($new_notes);
?>
<?php require_once DIR_INC . '/layout/footer-bare.inc.php'; ?>
</body>
</html>
