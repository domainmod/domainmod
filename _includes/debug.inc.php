<?php
/**
 * /_includes/debug.inc.php
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
define('DEBUG_MODE', $system->getDebugMode());

// Every signed-in POST must carry the session's security token (see DomainMOD\Csrf), or it changes nothing.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_SESSION['s_is_logged_in'] ?? '') == 1) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['REQUEST_URI'] ?? '');

}
