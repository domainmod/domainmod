<?php
/**
 * /hosting/notes.php
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
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$hsid = (int) ($_GET['hsid'] ?? 0);

// Before the database update there are no hosting tables, so there's nothing to show
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);
$result = false;

if ($hosting_available) {

    $stmt = $pdo->prepare("
        SELECT `name`, notes
        FROM hosting_services
        WHERE id = :hsid");
    $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

}

// The name and notes are stored escaped, so they're unescaped here and escaped once when drawn
if (!$result) {

    // the record was deleted, or the link was changed
    $page_title = _('Notes');
    $new_notes = '';

} else {

    $new_name = $unsanitize->text($result->name);
    $new_notes = trim($unsanitize->text((string) $result->notes));
    // TRANSLATORS: The heading of the page that shows a domain's, SSL certificate's, or hosting service's notes. %s is the domain, the certificate's host or label, or the hosting service's name.
    $page_title = sprintf(_('Notes for %s'), $layout->ltr($new_name));

}

$software_section = "hosting";
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle(strip_tags($page_title)); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php
$page_align = 'left';
require_once DIR_INC . '/layout/header-bare.inc.php'; ?>
<?php
if (!$hosting_available) {

    echo '<p>' . htmlspecialchars(DomainMOD\Hosting::unavailableMessage(), ENT_QUOTES, 'UTF-8') . '</p>';

} elseif (!$result) {

    echo $layout->emptyState('bi-question-circle', _('This record no longer exists.'), _('It may have been deleted.'));

} elseif ($new_notes === '') {

    echo $layout->emptyState('bi-journal', _('No notes yet'), _('Notes you add when editing this hosting service show here.'));

} else { ?>

    <div class="card mb-5">
        <div class="card-body" dir="auto"><?php echo nl2br(htmlspecialchars($new_notes, ENT_QUOTES, 'UTF-8'), false); ?></div>
    </div><?php

}
?>
<?php require_once DIR_INC . '/layout/footer-bare.inc.php'; ?>
</body>
</html>
