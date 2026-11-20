<?php
/**
 * /admin/users/reset-two-factor.php
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
$two_factor = new DomainMOD\TwoFactor();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

/*
 * Resets a user's two-factor sign-in (admin/users/edit.php's Reset MFA button, a POST with its
 * confirmation box ticked). The session's admin and read-only flags are only loaded at sign-in, so TwoFactor::adminReset()
 * checks them again in the database, and refuses if they've changed.
 */
$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$csrf = new DomainMOD\Csrf();
$csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

$uid = (int) ($_POST['uid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] != 'POST' || DEMO_INSTALLATION == true || !$two_factor->isAvailableNow()) {

    $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';
    header('Location: index.php');
    exit;

}

if (($_POST['confirm_reset'] ?? '') !== '1') {

    $_SESSION['s_message_danger'] .= _('Tick the box to confirm the reset.') . '<BR>';
    header('Location: edit.php?uid=' . $uid);
    exit;

}

$result = $two_factor->adminReset($_SESSION['s_user_id'], $_SESSION['s_stored_hash'] ?? '', $_SESSION['s_two_factor_stamp'] ?? '', $uid);

if (is_array($result)) {

    $_SESSION['s_message_success'] .= sprintf(_('MFA has been reset for %s.'), $result['username']) . '<BR>';
    $two_factor->redirectThenNotice('edit.php?uid=' . $uid, 'reset', $result);

} elseif ($result === 'primary') {

    $_SESSION['s_message_danger'] .= _("You don't have permissions to edit the primary administrator account.") . '<BR>';

} elseif ($result === 'own') {

    $_SESSION['s_message_danger'] .= _('To change MFA for your own account, use the Security page under Settings.') . '<BR>';

} elseif ($result === 'off') {

    $_SESSION['s_message_danger'] .= _("This user doesn't have MFA on.") . '<BR>';

} else {

    $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';

}

header('Location: edit.php?uid=' . $uid);
exit;
