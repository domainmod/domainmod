<?php
/**
 * /bulk/index.php
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

$system = new DomainMOD\System();
$log = new DomainMOD\Log('/bulk/index.php');
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/bulk-main.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);

$action = $sanitize->text($_REQUEST['action']);
$is_submitted = (int) $_POST['is_submitted'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_submitted === 1) {

    if ($action == 'UCF') {

        header("Location: cf/step-one/");
        exit;

    } else {

        if ($action == '') {

            $_SESSION['s_message_danger'] = _('Invalid selection, please try again');

        } else {

            header("Location: main/index.php?action=" . $action);
            exit;

        }

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-picker-head.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo _("The Bulk Updater allows you add or modify multiple domains at the same time, whether it's a couple dozen or a couple thousand, all with a few clicks."); ?><BR>
<?php

if ($action == '') {

    echo $form->showFormTop('');
    echo $form->showDropdownTop('action', '', '', '', '');
    echo $form->showDropdownOption('', _('Choose Action'), $action);
    echo $form->showDropdownOption('AD', _('Add Domains'), $action);
    echo $form->showDropdownOption('AN', _('Add A Note'), $action);
    echo $form->showDropdownOption('FR', _('Renew Domains (Update Expiry Date, Mark Active, Add Note)'), $action);
    echo $form->showDropdownOption('RENEW', _('Renew Domains (Update Expiry Date Only)'), $action);
    echo $form->showDropdownOption('A', _('Mark as Active'), $action);
    echo $form->showDropdownOption('T', _('Mark as Pending Transfer'), $action);
    echo $form->showDropdownOption('PRg', _('Mark as Pending Registration'), $action);
    echo $form->showDropdownOption('PRn', _('Mark as Pending Renewal'), $action);
    echo $form->showDropdownOption('PO', _('Mark as Pending (Other)'), $action);
    echo $form->showDropdownOption('E', _('Mark as Expired'), $action);
    echo $form->showDropdownOption('S', _('Mark as Sold'), $action);
    echo $form->showDropdownOption('AURNE', _('Mark as Auto Renewal'), $action);
    echo $form->showDropdownOption('AURND', _('Mark as Manual Renewal'), $action);
    echo $form->showDropdownOption('PRVE', _('Mark as Private WHOIS'), $action);
    echo $form->showDropdownOption('PRVD', _('Mark as Public WHOIS'), $action);
    echo $form->showDropdownOption('CPC', _('Change Category'), $action);
    echo $form->showDropdownOption('CDNS', _('Change DNS Profile'), $action);
    echo $form->showDropdownOption('CED', _('Change Expiry Date'), $action);
    echo $form->showDropdownOption('CIP', _('Change IP Address'), $action);
    echo $form->showDropdownOption('CRA', _('Change Registrar Account'), $action);
    echo $form->showDropdownOption('CWH', _('Change Web Hosting Provider'), $action);
    echo $form->showDropdownOption('UCF', _('Update Custom Domain Field'), $action);
    echo $form->showDropdownOption('DD', _('Delete Domains'), $action);
    echo $form->showDropdownBottom('');
    echo $form->showSubmitButton(_('Next Step'), '', '');
    echo $form->showInputHidden('is_submitted', '1');
    echo $form->showFormBottom('');

} else {

    echo '<BR>';

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
</body>
</html>
