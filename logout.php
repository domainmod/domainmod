<?php
/**
 * /logout.php
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
require_once __DIR__ . '/_includes/start-session.inc.php';
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

// Signing out never touches the database, so it always works, even during a restore or an upgrade (this file takes no
// restore lock, see _includes/restore-gate.inc.php)
// A web restore or Finish Restore that finished leaves its sign-in messages in the session before sending the
// administrator here (admin/backup/index.php), so the sign-in page can show them, once. Only the server sets them, so
// they can't be supplied from outside.
$restore_signin = is_array($_SESSION['s_restore_signin'] ?? null) ? $_SESSION['s_restore_signin'] : null;
$_SESSION = array();

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);

}

session_destroy();

if ($restore_signin !== null) {
    // A new session, with a new id, holding only the messages
    session_id(session_create_id());
    session_start();
    foreach (array('danger' => 's_message_danger', 'success' => 's_message_success') as $kind => $key) {
        $_SESSION[$key] = '';
        foreach ((array) ($restore_signin[$kind] ?? array()) as $message) {
            if ((string) $message !== '') $_SESSION[$key] .= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') . '<BR>';
        }
    }
}

header('Location: ' . $web_root . "/");
exit;
