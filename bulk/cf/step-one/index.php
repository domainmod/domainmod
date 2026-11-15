<?php
/**
 * /bulk/cf/step-one/index.php
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
require_once __DIR__ . '/../../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/bulk/cf/step-one/index.php');
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/bulk-main.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

$field_id = (int) ($_REQUEST['field_id'] ?? 0);
$is_submitted = (int) ($_POST['is_submitted'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_submitted === 1) {

    if ($field_id == '') {

        $form->addFieldError('field_id', _('Invalid selection, please try again'), true);

    } else {

        header('Location: ../step-two/?id=' . $field_id);
        exit;

    }

}

?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo $layout->stepIndicator(array(_('Custom Field'), _('Domains and Value')), 1, _('Bulk Updater steps')); ?>
<div class="domainmod-intro"><?php echo _("The Bulk Updater allows you to add or modify multiple domains at the same time, whether it's a couple dozen or a couple thousand, all with a few clicks."); ?></div>
<?php
echo $form->showFormTop('');

if ($field_id == '0') {

    echo $form->showDropdownTop('field_id', '', '', '', '');
    echo $form->showDropdownOption('', _('Choose the Custom Field to Edit'), '');

    $result = $pdo->query("
            SELECT df.id, df.name, df.type_id, cft.name AS type
            FROM domain_fields AS df, custom_field_types AS cft
            WHERE df.type_id = cft.id
            ORDER BY df.name")->fetchAll();

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->type . ')', $field_id);

    }

    echo $form->showDropdownBottom('', 'field_id'); ?>
    <a href='../../'><?php echo $layout->showButton('button', _('Go Back'));
        echo '</a>&nbsp;&nbsp';
        echo $form->showSubmitButton(_('Next Step'), '', '');

}
echo $form->showInputHidden('is_submitted', '1');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
