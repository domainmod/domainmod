<?php
/**
 * /maintenance/update-hosting-fees.php
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$maint = new DomainMOD\Maintenance();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();
$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
$csrf = new DomainMOD\Csrf();
$csrf->check($_SERVER['HTTP_REFERER'] ?? '');
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

$pdo = DomainMOD\Database::getInstance()->cnxx;

if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';

} else {

    /*
     * The recalculation holds the hosting lock and runs in its own transaction (see
     * DomainMOD\Maintenance::updateHostingFees()), and every cost it really moves is recorded in the Change History. A
     * page waits as long as the other hosting pages do, and says the hosting data is busy if it can't start.
     */
    if ($maint->updateHostingFees(DomainMOD\Hosting::PAGE_WAIT, (int) $_SESSION['s_user_id'])) {

        $queryB = new DomainMOD\QueryBuild();

        $sql = $queryB->missingFees('hosting_services');
        $_SESSION['s_missing_hosting_fees'] = $system->checkForRows($sql);

        $_SESSION['s_message_success'] .= _('Hosting Service Fees updated') . '<BR>';

    } else {

        $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';

    }

}

// An htmx request gets just the result message, shown in the task list's card, instead of a redirect
if ($system->isHtmxRequest()) {
    echo $system->htmxTaskResult();
    exit;
}

header("Location: index.php");
exit;
