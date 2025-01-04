<?php
/**
 * /install/language/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2025 Greg Chetcuti <greg@greg.ca>
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/install.language.inc.php';

$system->loginCheck();
$system->installCheck();

$_ENV['LANG'] = $_ENV['LANG'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $_SESSION['s_installation_language'] = $sanitize->text($_POST['new_language']);

    header("Location: ../requirements/");
    exit;

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <?php
    if ($page_title != "") { ?>
        <title><?php echo $layout->pageTitle($page_title); ?></title><?php
    } else { ?>
        <title><?php echo SOFTWARE_TITLE; ?></title><?php
    } ?>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition text-sm">
<?php require_once DIR_INC . '/layout/header-install.inc.php'; ?>
<?php echo _("We've tried our best to automatically determine your language, but if for some reason we were wrong, please select it below."); ?><BR>
<BR>
<?php echo sprintf(_("Multilingual support is very new, so please bear with us while we get new languages added. And also while we fix our auto-generated translations, some of which are probably pretty bad."), '<strong>', '</strong>'); ?><BR>
<?php
echo $form->showFormTop('');
echo $form->showDropdownTop('new_language', '', '', '', '');
echo $form->showDropdownOption('ar_SA.UTF-8', 'عربي', $_ENV['LANG']);
echo $form->showDropdownOption('bn_BD.UTF-8', 'বাংলা', $_ENV['LANG']);
echo $form->showDropdownOption('zh_CN.UTF-8', '中文（简体）', $_ENV['LANG']);
echo $form->showDropdownOption('zh_TW.UTF-8', '中文（繁體）)', $_ENV['LANG']);
echo $form->showDropdownOption('de_DE.UTF-8', 'Deutsch', $_ENV['LANG']);
echo $form->showDropdownOption('en_CA.UTF-8', 'English (CA)', $_ENV['LANG']);
echo $form->showDropdownOption('en_US.UTF-8', 'English (US)', $_ENV['LANG']);
echo $form->showDropdownOption('es_ES.UTF-8', 'Español', $_ENV['LANG']);
echo $form->showDropdownOption('fr_FR.UTF-8', 'Français', $_ENV['LANG']);
echo $form->showDropdownOption('hi_IN.UTF-8', 'हिंदी', $_ENV['LANG']);
echo $form->showDropdownOption('id_ID.UTF-8', 'Indonesia', $_ENV['LANG']);
echo $form->showDropdownOption('it_IT.UTF-8', 'Italiano', $_ENV['LANG']);
echo $form->showDropdownOption('ja_JP.UTF-8', '日本語', $_ENV['LANG']);
echo $form->showDropdownOption('ko_KR.UTF-8', '한국인', $_ENV['LANG']);
echo $form->showDropdownOption('mr_IN.UTF-8', 'मराठी', $_ENV['LANG']);
echo $form->showDropdownOption('nl_NL.UTF-8', 'Nederlands', $_ENV['LANG']);
echo $form->showDropdownOption('fa_IR.UTF-8', 'فارسی', $_ENV['LANG']);
echo $form->showDropdownOption('pl_PL.UTF-8', 'Polski', $_ENV['LANG']);
echo $form->showDropdownOption('pt_PT.UTF-8', 'Português', $_ENV['LANG']);
echo $form->showDropdownOption('pt_BR.UTF-8', 'Português (BR)', $_ENV['LANG']);
echo $form->showDropdownOption('ru_RU.UTF-8', 'Русский язык', $_ENV['LANG']);
echo $form->showDropdownOption('ta_IN.UTF-8', 'தமிழ்', $_ENV['LANG']);
echo $form->showDropdownOption('te_IN.UTF-8', 'తెలుగు', $_ENV['LANG']);
echo $form->showDropdownOption('tr_TR.UTF-8', 'Türkçe', $_ENV['LANG']);
echo $form->showDropdownOption('ur_PK.UTF-8', 'اردو', $_ENV['LANG']);
echo $form->showDropdownOption('vi_VN.UTF-8', 'Tiếng Việt', $_ENV['LANG']);
echo $form->showDropdownBottom('');
?>
<BR>
<?php
echo $form->showSubmitButton(_('Begin Installation'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer-install.inc.php'; ?>
</body>
</html>
