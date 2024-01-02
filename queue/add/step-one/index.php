<?php
/**
 * /queue/add/step-one/index.php
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
require_once __DIR__ . '/../../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/queue/add/step-one/index.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$domain = new DomainMOD\Domain();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/queue-add.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

$new_raid = (int) ($_REQUEST['new_raid'] ?? 0);
$is_submitted = (int) ($_REQUEST['is_submitted'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_submitted === 1) {

    if ($new_raid > 0) {

        header("Location: ../step-two/?new_raid=$new_raid");
        exit;

    } else {

        $_SESSION['s_message_danger'] .= _('Please choose the registrar account') . '<BR>';

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<strong><?php echo _('Domain Queue & API Prerequisites'); ?></strong><BR>
<?php echo sprintf(_('Before you can add domains to %s using the Domain Queue you must first do the following'), SOFTWARE_TITLE) . ':'; ?>
<ol>
    <li><?php echo _('Ensure that the registrar has an API and that your account has been granted access to it'); ?></li>
    <li><?php echo sprintf(_('Enable API Support on the %sregistrar asset%s'), '<a href="' . $web_root . '/assets/registrars.php">', '</a>'); ?></li>
    <li><?php echo sprintf(_('Save the required API credentials with the %sregistrar account asset%s'), '<a href="' . $web_root . '/assets/registrar-accounts.php">', '</a>'); ?></li>
</ol><?php

echo $form->showFormTop('');

echo $form->showDropdownTop('new_raid', '', '', '', '');

$result_account = $pdo->query("
    SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
    FROM registrar_accounts AS ra, owners AS o, registrars AS r
    WHERE ra.owner_id = o.id
      AND ra.registrar_id = r.id
      AND r.api_registrar_id != '0'
    ORDER BY r_name, o_name, ra.username")->fetchAll();

echo $form->showDropdownOption('', _('Choose the Registrar Account to import'), '');

foreach ($result_account as $row_account) {

    echo $form->showDropdownOption($row_account->id, $row_account->r_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $new_raid);

}
echo $form->showDropdownBottom('');
echo $form->showInputHidden('is_submitted', '1');
echo $form->showSubmitButton(_('Next Step'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
