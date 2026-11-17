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
// The config line is left-to-right code, so it's isolated (as Layout::ltr() does) and kept on one line, which keeps it
// readable in a right-to-left language.
$connection_advice = ($connection_status !== 'encrypted (certificate checked)' && !$connection_is_local)
    ? sprintf(_('This connection isn\'t protected from an attacker on the network. If your database is on a different server, add %s to config.inc.php to encrypt the connection and check the server\'s certificate.'), '<bdi dir="ltr" class="text-nowrap"><code>$dbsslmode = \'verify\';</code></bdi>')
      . ' ' . sprintf(_('See %sEncrypted Database Connections%s in the User Guide.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/database-encryption/">', '</a>')
    : '';

// The system details, in the same grouped list as the requirements below them: each group's rows show a name and its
// value. Values come from the server, so they're escaped, apart from the version's emphasis and the advice's code.
// Left-to-right values (versions, addresses, paths, and the MySQL mode) are isolated with Layout::ltr(), so in a
// right-to-left language they keep their own order, rather than reading as "(4.25.0) 4.25.0". The version row builds
// the same <bdi> by hand to keep its emphasis. The connection status is translated text, so it isn't isolated.
$esc = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };

// The Remote IP Address comes from an outside service (ipify), so it's looked up only when asked: Look up fetches it in
// place with htmx (remote-ip.php), and the address replaces the button. Without JavaScript, remote-ip.php comes back
// here with the answer, shown once. The wrapper takes focus back and is read out when the answer arrives.
if (isset($_SESSION['s_remote_ip_lookup'])) {
    $remote_ip_value = $_SESSION['s_remote_ip_lookup'] !== '' ? $layout->ltr($_SESSION['s_remote_ip_lookup']) : $esc(_('Unknown'));
    unset($_SESSION['s_remote_ip_lookup']);
} else {
    $remote_ip_value = '<span id="domainmod-remote-ip" tabindex="-1" data-domainmod-focus data-domainmod-refocus>'
        . $layout->htmxTaskLink('remote-ip.php?' . (new DomainMOD\Csrf())->query(),
            '<i class="bi bi-search me-1" aria-hidden="true"></i>' . $esc(_('Look up')), '#domainmod-remote-ip', 'innerHTML',
            'domainmod-remote-ip-working', ' class="btn btn-sm btn-outline-secondary domainmod-css-button"')
        . '</span>' . $layout->htmxSpinner('domainmod-remote-ip-working');
}
$info_groups = array(
    array(SOFTWARE_TITLE, array(
        array(_('Version'), '<bdi dir="ltr">' . $esc(SOFTWARE_VERSION) . ' (<em>' . $esc($_SESSION['s_system_db_version']) . '</em>)</bdi>'))),
    array(_('Web Server'), array(
        array(_('Local IP Address'), $layout->ltr($_SERVER['SERVER_ADDR'] ?? '')),
        array(_('Remote IP Address'), $remote_ip_value),
        array(_('Web Server OS'), $layout->ltr(php_uname())),
        array(_('Web Server Software'), $layout->ltr($_SERVER['SERVER_SOFTWARE'] ?? '')))),
    array('PHP', array(
        array(_('PHP Version'), $layout->ltr(phpversion())),
        array(_('PHP Error Log Location'), $layout->ltr(ini_get('error_log'))))),
    array(_('Database'), array(
        array(_('MySQL Version'), $layout->ltr($pdo->query('select version()')->fetchColumn())),
        array(_('MySQL Mode'), $layout->ltr($pdo->query('select @@sql_mode')->fetchColumn())),
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
