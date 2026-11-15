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
require_once DIR_INC . '/install-languages.inc.php';
$installer_languages = array_keys($installer_language_names);
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
foreach ($installer_language_names as $language_code => $language_name) {
    echo $form->showDropdownOption($language_code, $language_name, $selected_language);
}
echo $form->showDropdownBottom('');
$install_back_url = '';
$install_next_label = sprintf(_('Next: %s'), _('Software Requirements'));
require DIR_INC . '/layout/install-buttons.inc.php';
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer-install.inc.php'; ?>
</body>
</html>
