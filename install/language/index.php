<?php
/**
 * /install/language/index.php
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

// The language to pre-select: the one already chosen in this installation, or the closest of the listed languages
// to the browser's (see DomainMOD\BrowserLocale), or English (US) when nothing matches
$installer_languages = array('ar_SA.UTF-8', 'bn_BD.UTF-8', 'zh_CN.UTF-8', 'zh_TW.UTF-8', 'de_DE.UTF-8', 'en_CA.UTF-8',
    'en_US.UTF-8', 'es_ES.UTF-8', 'fr_FR.UTF-8', 'hi_IN.UTF-8', 'id_ID.UTF-8', 'it_IT.UTF-8', 'ja_JP.UTF-8', 'ko_KR.UTF-8',
    'mr_IN.UTF-8', 'nl_NL.UTF-8', 'fa_IR.UTF-8', 'pl_PL.UTF-8', 'pt_PT.UTF-8', 'pt_BR.UTF-8', 'ru_RU.UTF-8', 'ta_IN.UTF-8',
    'te_IN.UTF-8', 'tr_TR.UTF-8', 'ur_PK.UTF-8', 'vi_VN.UTF-8');
if (in_array($_SESSION['s_installation_language'] ?? '', $installer_languages, true)) {
    $selected_language = $_SESSION['s_installation_language'];
} else {
    $browser_locale = new DomainMOD\BrowserLocale();
    $selected_language = $browser_locale->guessLanguage($installer_languages);
    if ($selected_language === '') $selected_language = 'en_US.UTF-8';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // The installer runs while signed out, so it checks the security token itself (see DomainMOD\Csrf).
    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['REQUEST_URI'] ?? '');

    $_SESSION['s_installation_language'] = $sanitize->text($_POST['new_language']);

    header("Location: ../requirements/");
    exit;

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <?php
    if ($page_title != "") { ?>
        <title><?php echo $layout->pageTitle($page_title); ?></title><?php
    } else { ?>
        <title><?php echo SOFTWARE_TITLE; ?></title><?php
    } ?>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag('install'); ?>
<?php require_once DIR_INC . '/layout/header-install.inc.php'; ?>
<?php echo _("We've tried our best to automatically determine your language, but if for some reason we were wrong, please select it below."); ?><BR>
<?php
echo $form->showFormTop('');
echo $form->showDropdownTop('new_language', '', '', '', '');
echo $form->showDropdownOption('ar_SA.UTF-8', 'عربي', $selected_language);
echo $form->showDropdownOption('bn_BD.UTF-8', 'বাংলা', $selected_language);
echo $form->showDropdownOption('zh_CN.UTF-8', '中文（简体）', $selected_language);
echo $form->showDropdownOption('zh_TW.UTF-8', '中文（繁體）', $selected_language);
echo $form->showDropdownOption('de_DE.UTF-8', 'Deutsch', $selected_language);
echo $form->showDropdownOption('en_CA.UTF-8', 'English (CA)', $selected_language);
echo $form->showDropdownOption('en_US.UTF-8', 'English (US)', $selected_language);
echo $form->showDropdownOption('es_ES.UTF-8', 'Español', $selected_language);
echo $form->showDropdownOption('fr_FR.UTF-8', 'Français', $selected_language);
echo $form->showDropdownOption('hi_IN.UTF-8', 'हिंदी', $selected_language);
echo $form->showDropdownOption('id_ID.UTF-8', 'Indonesia', $selected_language);
echo $form->showDropdownOption('it_IT.UTF-8', 'Italiano', $selected_language);
echo $form->showDropdownOption('ja_JP.UTF-8', '日本語', $selected_language);
echo $form->showDropdownOption('ko_KR.UTF-8', '한국인', $selected_language);
echo $form->showDropdownOption('mr_IN.UTF-8', 'मराठी', $selected_language);
echo $form->showDropdownOption('nl_NL.UTF-8', 'Nederlands', $selected_language);
echo $form->showDropdownOption('fa_IR.UTF-8', 'فارسی', $selected_language);
echo $form->showDropdownOption('pl_PL.UTF-8', 'Polski', $selected_language);
echo $form->showDropdownOption('pt_PT.UTF-8', 'Português', $selected_language);
echo $form->showDropdownOption('pt_BR.UTF-8', 'Português (BR)', $selected_language);
echo $form->showDropdownOption('ru_RU.UTF-8', 'Русский язык', $selected_language);
echo $form->showDropdownOption('ta_IN.UTF-8', 'தமிழ்', $selected_language);
echo $form->showDropdownOption('te_IN.UTF-8', 'తెలుగు', $selected_language);
echo $form->showDropdownOption('tr_TR.UTF-8', 'Türkçe', $selected_language);
echo $form->showDropdownOption('ur_PK.UTF-8', 'اردو', $selected_language);
echo $form->showDropdownOption('vi_VN.UTF-8', 'Tiếng Việt', $selected_language);
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
