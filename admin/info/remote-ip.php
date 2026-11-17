<?php
/**
 * /admin/info/remote-ip.php
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

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$csrf = new DomainMOD\Csrf();
$csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');

// System Information's Remote IP Address, looked up only when asked, since it's a call to an outside service (ipify).
// It's '' when the lookup fails.
$remote_ip = $system->getIpRemotely();

// An htmx request gets just the address (or Unknown), shown in place of the Look up button, as left-to-right text
if ($system->isHtmxRequest()) {
    header('X-DomainMOD-Result: 1');
    echo $remote_ip !== '' ? $layout->ltr($remote_ip) : htmlspecialchars(_('Unknown'), ENT_QUOTES, 'UTF-8');
    exit;
}

// Without JavaScript, System Information shows the answer once
$_SESSION['s_remote_ip_lookup'] = $remote_ip;

header("Location: index.php");
exit;
