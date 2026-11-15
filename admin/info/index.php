<?php
/**
 * /admin/info/index.php
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

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-info.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<!body class="hold-transition skin-red sidebar-mini">
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>

<?php
$connection_status = $deeb->getConnectionStatus();
if ($connection_status === 'encrypted (certificate checked)') {
    $connection_text = _('Encrypted (certificate checked)');
} elseif ($connection_status === 'encrypted (certificate not checked)') {
    $connection_text = _('Encrypted (certificate not checked)');
} else {
    $connection_text = _('Not encrypted');
}
// A connection to the same machine doesn't cross the network, so it doesn't need the advice
$connection_is_local = in_array(strtolower(DB_HOSTNAME), array('localhost', '127.0.0.1', '::1'), true);
$connection_advice = ($connection_status !== 'encrypted (certificate checked)' && !$connection_is_local)
    ? sprintf(_('This connection isn\'t protected from an attacker on the network. If your database is on a different server, add %s to config.inc.php to encrypt the connection and check the server\'s certificate.'), '<code>$dbsslmode = \'verify\';</code>')
      . ' ' . sprintf(_('See %sEncrypted Database Connections%s in the User Guide.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/database-encryption/">', '</a>')
    : '';

// The system details, in the same grouped list as the requirements below them: each group's rows show a name and its
// value. Values come from the server, so they're escaped, apart from the version's emphasis and the advice's code.
$esc = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$info_groups = array(
    array(SOFTWARE_TITLE, array(
        array(_('Version'), $esc(SOFTWARE_VERSION) . ' (<em>' . $esc($_SESSION['s_system_db_version']) . '</em>)'))),
    array(_('Web Server'), array(
        array(_('Local IP Address'), $esc($_SERVER['SERVER_ADDR'] ?? '')),
        array(_('Remote IP Address'), $esc($system->getIpRemotely())),
        array(_('Web Server OS'), $esc(php_uname())),
        array(_('Web Server Software'), $esc($_SERVER['SERVER_SOFTWARE'] ?? '')))),
    array('PHP', array(
        array(_('PHP Version'), $esc(phpversion())),
        array(_('PHP Error Log Location'), $esc(ini_get('error_log'))))),
    array(_('Database'), array(
        array(_('MySQL Version'), $esc($pdo->query('select version()')->fetchColumn())),
        array(_('MySQL Mode'), $esc($pdo->query('select @@sql_mode')->fetchColumn())),
        array(_('Database Connection'), $esc($connection_text), $connection_advice))));
?>
<div class="domainmod-requirements domainmod-system-info">
    <div class="domainmod-requirements-list"><?php
    foreach ($info_groups as $group_index => $group) {
        list($group_label, $rows) = $group; ?>
        <div class="domainmod-requirements-group" id="domainmod-system-info-<?php echo $group_index; ?>"><?php echo $esc($group_label); ?></div>
        <ul class="domainmod-requirements-rows" aria-labelledby="domainmod-system-info-<?php echo $group_index; ?>"><?php
        foreach ($rows as $row) { ?>
            <li>
                <div>
                    <span class="domainmod-requirements-name"><?php echo $esc($row[0]); ?><span class="visually-hidden">:</span></span>
                    <span class="domainmod-system-info-value"><?php echo $row[1]; ?></span><?php
                    if (!empty($row[2])) { ?>
                    <span class="domainmod-requirements-fix"><?php echo $row[2]; ?></span><?php
                    } ?>
                </div>
            </li><?php
        } ?>
        </ul><?php
    } ?>
    </div>
</div>
<h2 class="h5 mt-4 mb-3"><?php echo _('Software Requirements'); ?></h2>
<?php echo $system->requirementsChecklist(); ?>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
