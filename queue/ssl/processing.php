<?php
/**
 * /queue/ssl/processing.php
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
$ssl_queue = new DomainMOD\SslQueue();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();

// Only the SSL Queue page's processing notice asks this page (with htmx), so anything else goes to the queue page
if (!$system->isHtmxRequest()) {
    header("Location: index.php");
    exit;
}

// While the queue is still processing, send the notice back (it asks again in 5 seconds). Once it has finished, have
// htmx reload the queue page to show the results.
if ($ssl_queue->isAvailable() && $ssl_queue->isProcessing()) {
    header('X-DomainMOD-Result: 1');
    echo $ssl_queue->processingNotice();
    exit;
}

header('HX-Refresh: true');
exit;
