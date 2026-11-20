<?php
/**
 * /admin/upgrade/index.php
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
$time = new DomainMOD\Time();
$guide = new DomainMOD\UpgradeGuide();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-upgrade-main.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);

// Check Now: debug.inc.php has already checked the security token. Read-only admins can see the page, but not check.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');

    $check_result = $system->checkLatestVersion(SOFTWARE_VERSION);

    if ($check_result === 'failed') {

        $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
        $_SESSION['s_message_danger'] .= _('The newest version couldn\'t be checked. Please try again later.') . '<BR>';

    } else {

        $_SESSION['s_message_success'] = $_SESSION['s_message_success'] ?? '';
        $_SESSION['s_message_success'] .= _('Checked for a new version') . '<BR>';

    }

    // Back to the steps that were shown, so a type the admin chose stays chosen
    $shown = $_GET['type'] ?? '';
    $query = in_array($shown, array_merge(DomainMOD\UpgradeGuide::TYPES, array('unknown')), true) ? '?type=' . $shown : '';
    header('Location: ' . WEB_ROOT . '/admin/upgrade/' . $query);
    exit;

}

/*
 * The newest release from the last successful check (null before the database update that saves it), and whether
 * it's newer than this installation. Before the update, or before the first check, only the saved yes or no is known.
 */
$latest = $system->getLatestVersion();
$latest_version = ($latest !== null && DomainMOD\System::isReleaseVersion($latest['version'])) ? $latest['version'] : '';
if ($latest_version !== '') {
    $upgrade_available = version_compare(SOFTWARE_VERSION, $latest_version, '<');
} else {
    $upgrade_available = ($_SESSION['s_system_upgrade_available'] ?? '') == '1';
}

// How DomainMOD was installed, and the type whose steps are shown: the detected one, unless the admin chose another
$install_type = new DomainMOD\InstallType();
$detected = $install_type->detect(INSTALL_TYPE);
$chosen = $_GET['type'] ?? '';
$shown_type = in_array($chosen, array_merge(DomainMOD\UpgradeGuide::TYPES, array('unknown')), true) ? $chosen : $detected['type'];

$context = array('root' => DIR_ROOT, 'version' => $latest_version, 'git' => $detected['git'],
                 'backup_url' => WEB_ROOT . '/admin/backup/');
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>

<div class="domainmod-intro">
<p><?php echo sprintf(_('See whether a new version of %s is available and find the upgrade steps for your installation method.'), SOFTWARE_TITLE); ?></p>
</div>

<?php
// The versions, with Check Now. The card body is a two-column grid on wider screens, so each item spans both columns.
$checked = $latest['checked'] ?? '';
$facts = array(
    array('label' => _('Installed Version'), 'value' => SOFTWARE_VERSION, 'ltr' => true),
    array('label' => _('Database Version'), 'value' => (string) ($_SESSION['s_system_db_version'] ?? ''), 'ltr' => true),
    array('label' => _('Latest Release'), 'value' => $latest_version !== '' ? $latest_version : _('Unknown'), 'ltr' => $latest_version !== ''));
if ($latest !== null) {
    $facts[] = array('label' => _('Last Checked'), 'value' => $time->toUserTimezone($checked, 'Y-m-d H:i', _('Never')),
                     'ltr' => !DomainMOD\Time::isUnset($checked));
}

if ($upgrade_available && $latest_version !== '') {
    // TRANSLATORS: %s is a version number, such as 4.27.0
    $status = $system->showMessageInfo(sprintf(_('Version %s is available.'), $layout->ltr($latest_version)));
} elseif ($upgrade_available) {
    $status = $system->showMessageInfo(sprintf(_('A new version of %s is available.'), SOFTWARE_TITLE));
} elseif ($latest_version === SOFTWARE_VERSION) {
    // Only when they match: a saved version older than this one (checked before a manual upgrade) says nothing
    $status = $system->showMessageSuccessInline(_('You\'re running the latest version.'));
} else {
    $status = '';
}

ob_start(); ?>
<div class="domainmod-span-all"><?php echo $layout->factList($facts); ?></div>
<?php if ($status !== '') { ?><div class="domainmod-span-all"><?php echo $status; ?></div><?php } ?>
<?php // Read-only users can't change anything, so Check Now (which saves what it finds) isn't offered to them
if (($_SESSION['s_read_only'] ?? '') != '1') { ?>
<form action="<?php echo $chosen === $shown_type ? '?type=' . htmlspecialchars($shown_type, ENT_QUOTES, 'UTF-8') : ''; ?>" method="post" class="domainmod-span-all mb-3">
<?php echo $form->showCsrfField(); ?>
<?php echo $layout->showButton('submit', _('Check Now')); ?>
</form><?php
}
echo '<div class="domainmod-form">' . $layout->formSection(_('Versions'), 'versions', ob_get_clean()) . '</div>';

// How it was installed, and the switch that shows another type's steps instead (it changes only the steps shown)
$reasons = array_map(array($guide, 'signalText'), $detected['signals'] ?: array(''));
ob_start(); ?>
<div class="domainmod-span-all mb-3">
    <p class="mb-1"><?php
        // TRANSLATORS: %s is how DomainMOD was installed, such as Docker, Git, or Zip File
        echo sprintf(_('Installed with: %s'), '<strong>' . htmlspecialchars($guide->typeName($detected['type']), ENT_QUOTES, 'UTF-8') . '</strong>'); ?></p>
    <p class="mb-0"><?php echo implode(' ', $reasons); ?></p>
</div>
<form action="" method="get" class="domainmod-span-all">
<?php
echo $form->showDropdownTop('type', _('Not right? Choose yours'), '', '', '');
foreach (array_merge(DomainMOD\UpgradeGuide::TYPES, array('unknown')) as $type) {
    echo $form->showDropdownOption($type, htmlspecialchars($guide->typeName($type), ENT_QUOTES, 'UTF-8'), $shown_type);
}
echo $form->showDropdownBottom('');
echo $layout->showButton('submit', _('Show Steps')); ?>
</form>
<p class="domainmod-span-all form-text mb-3"><?php
    // TRANSLATORS: %1$s is a line of code ($install_type = 'zip';) and %2$s a file name (config.inc.php). Don't translate them.
    echo sprintf(_('To always show the steps for one type, add %1$s to %2$s (docker, git, zip, or softaculous).'),
        $guide->code("\$install_type = 'zip';"), $guide->code('config.inc.php')); ?></p><?php
echo '<div class="domainmod-form">' . $layout->formSection(_('Your Installation'), 'installation', ob_get_clean()) . '</div>';

// The steps for the type shown, then the note about the downloads appearing after the release
ob_start(); ?>
<div class="domainmod-span-all mb-3">
<?php echo $guide->steps($shown_type, $context); ?>
<p class="form-text mt-3 mb-0"><?php echo _('The zip file on domainmod.org and the Docker images can take an hour or two to appear after a new release is published on GitHub. If a download still offers the older version, please try again later.'); ?></p>
</div><?php
$steps_title = $shown_type === 'unknown' ? _('How to Upgrade') : sprintf(_('How to Upgrade (%s)'), $guide->typeName($shown_type));
echo '<div class="domainmod-form">' . $layout->formSection($steps_title, 'steps', ob_get_clean()) . '</div>';

/*
 * The requirements this server meets for the installed version (the new release's own aren't known until its files
 * arrive), and the new release's notes on GitHub, which list any new requirements
 */
ob_start(); ?>
<div class="domainmod-span-all mb-3"><?php
    if ($upgrade_available && $latest_version !== '') { ?>
    <p><?php
        // TRANSLATORS: %2$s is a version number, such as 4.27.0. %1$s and %3$s make "the release notes" a link.
        echo sprintf(_('The %1$sv%2$s release notes%3$s list any new requirements. Please check them before upgrading.'),
            '<a href="' . htmlspecialchars(DomainMOD\UpgradeGuide::RELEASE_NOTES_URL . $latest_version, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">',
            htmlspecialchars($latest_version, ENT_QUOTES, 'UTF-8'), '</a>'); ?></p><?php
    } ?>
    <h3 class="h6"><?php
        // TRANSLATORS: %s is a version number, such as 4.27.0
        echo sprintf(_('Requirements for your installed version (v%s)'), htmlspecialchars(SOFTWARE_VERSION, ENT_QUOTES, 'UTF-8')); ?></h3>
    <?php echo $system->requirementsChecklist(false, true); ?>
</div><?php
echo '<div class="domainmod-form">' . $layout->formSection(_('Requirements'), 'requirements', ob_get_clean()) . '</div>';
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/copy-command.js'); ?>"></script>
</body>
</html>
