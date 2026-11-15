<?php
/**
 * /install/ready/index.php
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
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/install.ready.inc.php';
require_once DIR_INC . '/install-languages.inc.php';

$system->loginCheck();
$system->installCheck();

// Every earlier step has to be finished first, so the Install button never shows with an answer missing
$missing_step = $system->installFirstMissingStep();
if ($missing_step !== '') {
    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('Please finish this step first.') . '<BR>';
    header('Location: ' . $missing_step);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // The installer runs while signed out, so it checks the security token itself (see DomainMOD\Csrf). install/go.php
    // checks it again, along with every answer, before it changes anything.
    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['REQUEST_URI'] ?? '');

    header("Location: ../go.php?" . $csrf->query());
    exit;

}

// Every choice, each with a link back to the step that sets it. The values are escaped, and the password isn't shown.
$esc = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$language_code = $_SESSION['s_installation_language'];
$admin_url = WEB_ROOT . '/install/administrator/';
$summary = array(
    array(_('Settings'), array(
        array(_('Language'), $installer_language_names[$language_code] ?? $language_code, WEB_ROOT . '/install/language/'),
        array(_('Currency'), $unsanitize->text($_SESSION['s_installation_currency']), WEB_ROOT . '/install/currency/'),
        array(_('Time Zone'), $unsanitize->text($_SESSION['s_installation_timezone']), WEB_ROOT . '/install/timezone/'))),
    array(_('Administrator Account'), array(
        array(_('Name'), $unsanitize->text($_SESSION['s_installation_admin_first_name']) . ' ' . $unsanitize->text($_SESSION['s_installation_admin_last_name']), $admin_url),
        array(_('Username'), $unsanitize->text($_SESSION['s_installation_admin_username']), $admin_url),
        array(_('Email Address'), $unsanitize->text($_SESSION['s_installation_admin_email']), $admin_url))),
    array(_('System Email'), array(
        array(_('Email Address'), $unsanitize->text($_SESSION['new_system_email']), WEB_ROOT . '/install/email-system/'))));
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
<p><?php echo _('Check your choices, then install.'); ?></p>
<div class="domainmod-requirements domainmod-install-summary">
    <div class="domainmod-requirements-list"><?php
    foreach ($summary as $group_index => $group) {
        list($group_label, $rows) = $group; ?>
        <div class="domainmod-requirements-group" id="domainmod-install-summary-<?php echo $group_index; ?>"><?php echo $esc($group_label); ?></div>
        <ul class="domainmod-requirements-rows" aria-labelledby="domainmod-install-summary-<?php echo $group_index; ?>"><?php
        foreach ($rows as $row) { ?>
            <li>
                <div>
                    <span class="domainmod-requirements-name"><?php echo $esc($row[0]); ?><span class="visually-hidden">:</span></span>
                    <span class="domainmod-system-info-value"><?php echo $esc($row[1]); ?></span>
                    <a class="domainmod-install-summary-change" href="<?php echo $esc($row[2]); ?>" aria-label="<?php echo $esc(sprintf(_('Change %1$s, %2$s'), $group_label, $row[0])); ?>"><?php echo _('Change'); ?></a>
                </div>
            </li><?php
        } ?>
        </ul><?php
    } ?>
    </div>
</div>
<?php // The Install button shows a spinner while the installation runs (js/install-ready.js), like the CSV Importer's, and
      // the status line below tells screen readers it has started ?>
<div class="visually-hidden" role="status" id="domainmod-install-status"></div>
<form method="post" class="domainmod-form domainmod-install-ready-form" data-busy-text="<?php echo htmlspecialchars(_('Installing…'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo $form->showCsrfField(); ?>
<?php
$install_back_url = WEB_ROOT . '/install/email-system/';
$install_next_label = sprintf(_('Install %s'), SOFTWARE_TITLE);
$install_next_large = true;
require DIR_INC . '/layout/install-buttons.inc.php';
?>
</form>
<?php require_once DIR_INC . '/layout/footer-install.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/install-ready.js'); ?>"></script>
</body>
</html>
