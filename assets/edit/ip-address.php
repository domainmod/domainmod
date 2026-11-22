<?php
/**
 * /assets/edit/ip-address.php
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
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-ip-address.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$ipid = (int) ($_GET['ipid'] ?? 0);
$new_name = isset($_POST['new_name']) ? $sanitize->text($_POST['new_name']) : '';
$new_ip = isset($_POST['new_ip']) ? $sanitize->text($_POST['new_ip']) : '';
$new_rdns = isset($_POST['new_rdns']) ? $sanitize->text($_POST['new_rdns']) : '';
$new_ipid = (int) ($_POST['new_ipid'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    if ($validate->text($new_name) && $validate->text($new_ip)) {

        $stmt = $pdo->prepare("
            UPDATE ip_addresses
            SET name = :new_name,
                ip = :new_ip,
                rdns = :new_rdns,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :new_ipid");
        $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
        $stmt->bindValue('new_ip', $new_ip, PDO::PARAM_STR);
        $stmt->bindValue('new_rdns', $new_rdns, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('new_ipid', $new_ipid, PDO::PARAM_INT);
        $stmt->execute();

        $ipid = $new_ipid;

        $_SESSION['s_message_success'] .= sprintf(_('IP Address %s (%s) updated'), $new_name, $new_ip) . '<BR>';

        header("Location: ../ip-addresses.php");
        exit;

    } else {

        if (!$validate->text($new_name)) $form->addFieldError('new_name', _('Enter a name for the IP Address'));
        if (!$validate->text($new_ip)) $form->addFieldError('new_ip', _('Enter the IP Address'));

    }

} else {

    $stmt = $pdo->prepare("
        SELECT `name`, ip, rdns, notes
        FROM ip_addresses
        WHERE id = :ipid");
    $stmt->bindValue('ipid', $ipid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_name = $result->name;
        $new_ip = $result->ip;
        $new_rdns = $result->rdns;
        $new_notes = $result->notes;

    }

}

if ($del === 1) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    /*
     * Once the database has the hosting tables, the checks and the delete hold the hosting lock, so a hosting service
     * can't be given this IP address between the two
     */
    $hosting_available = DomainMOD\Hosting::isAvailable($pdo);
    if ($hosting_available && !DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

        $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';
        header("Location: ip-address.php?ipid=" . $ipid);
        exit;

    }

    $deleted = false;

    try {

        $stmt = $pdo->prepare("
            SELECT ip_id
            FROM domains
            WHERE ip_id = :ipid
            LIMIT 1");
        $stmt->bindValue('ipid', $ipid, PDO::PARAM_INT);
        $stmt->execute();
        $existing_domains = $stmt->fetchColumn() !== false;
        $stmt->closeCursor();

        $existing_hosting_services = false;

        if ($hosting_available) {

            $stmt = $pdo->prepare("
                SELECT id
                FROM hosting_services
                WHERE ip_id = :ipid
                LIMIT 1");
            $stmt->bindValue('ipid', $ipid, PDO::PARAM_INT);
            $stmt->execute();
            $existing_hosting_services = $stmt->fetchColumn() !== false;
            $stmt->closeCursor();

        }

        if ($existing_domains || $existing_hosting_services) {

            if ($existing_domains) $_SESSION['s_message_danger'] .= _('This IP Address has domains associated with it and cannot be deleted.') . '<BR>';
            if ($existing_hosting_services) $_SESSION['s_message_danger'] .= _('This IP Address has hosting services associated with it and cannot be deleted.') . '<BR>';

        } else {

            $stmt = $pdo->prepare("
                DELETE FROM ip_addresses
                WHERE id = :ipid");
            $stmt->bindValue('ipid', $ipid, PDO::PARAM_INT);
            $stmt->execute();

            $deleted = true;

        }

    } finally {

        if ($hosting_available) DomainMOD\Hosting::unlock($pdo);

    }

    if ($deleted) {

        $_SESSION['s_message_success'] .= sprintf(_('IP Address %s (%s) deleted'), $new_name, $new_ip) . '<BR>';

        header("Location: ../ip-addresses.php");
        exit;

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_name', _('IP Address Name') . ' (100)', '', $unsanitize->text($new_name), '100', '', '1', '', '');
echo $form->showInputText('new_ip', _('IP Address') . ' (100)', '', $unsanitize->text($new_ip), '100', '', '1', '', '');
echo $form->showInputText('new_rdns', _('rDNS') . ' (100)', '', $unsanitize->text($new_rdns), '100', '', '', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_ipid', $ipid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->dangerZone(_('IP Address'), $new_name, 'ip-address.php?ipid=' . $ipid . '&del=1', _('Delete this IP address'), (DomainMOD\Hosting::isAvailable($pdo) ? sprintf(_('This removes %s. It can only be deleted when no domains or hosting services use it. It can\'t be undone.'), '<strong>' . htmlentities(html_entity_decode($new_name, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>') : sprintf(_('This removes %s. It can only be deleted when no domains use it. It can\'t be undone.'), '<strong>' . htmlentities(html_entity_decode($new_name, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>')), 2);
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
