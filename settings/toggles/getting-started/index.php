<?php
/**
 * /settings/toggles/getting-started/index.php
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

$system = new DomainMOD\System();
$user = new DomainMOD\User();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

/*
 * Hides the Dashboard's Getting Started checklist for the signed-in user, from its "Hide this checklist" button, then
 * goes back to the Dashboard. Display Settings shows it again. Read-only users can hide it too, since it's their own
 * display choice, as on Display Settings. Only a POST with the session's token changes anything.
 */
$system->authCheck();
$dashboard_url = WEB_ROOT . '/dashboard/';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {

    header('Location: ' . $dashboard_url);
    exit;

}

$csrf = new DomainMOD\Csrf();
$csrf->check($dashboard_url);

if ($user->setGettingStartedDisplay($_SESSION['s_user_id'], 0)) {

    $_SESSION['s_display_getting_started'] = 0;
    $_SESSION['s_message_success'] .= sprintf(_('The Getting Started checklist is hidden. You can show it again in %sDisplay Settings%s.'), '<a href="' . WEB_ROOT . '/settings/display/">', '</a>') . '<BR>';

} else {

    $_SESSION['s_message_danger'] .= _("The Getting Started checklist couldn't be hidden. Please try again.") . '<BR>';

}

header('Location: ' . $dashboard_url);
exit;
