<?php
/**
 * /admin/dw/edit-server.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/dw-edit-server.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);
$pdo = $deeb->cnxx;

$del = (int) $_GET['del'];

$dwsid = (int) $_GET['dwsid'];
$new_name = $sanitize->text($_POST['new_name']);
$new_host = $sanitize->text($_POST['new_host']);
$new_protocol = $_POST['new_protocol'];
$new_port = (int) $_POST['new_port'];
$new_username = $sanitize->text($_POST['new_username']);
$new_api_token = $sanitize->text($_POST['new_api_token']);
$new_hash = $sanitize->text($_POST['new_hash']);
$new_notes = $sanitize->text($_POST['new_notes']);
$new_dwsid = (int) $_POST['new_dwsid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_name == "" || $new_host == "" || $new_protocol == "" || $new_port === 0 || $new_username == "" || ($new_api_token == "" && $new_hash == "")
    ) {

        if ($new_name == "") {
            $_SESSION['s_message_danger'] .= _('Enter a display name for the server') . '<BR>';
        }
        if ($new_host == "") {
            $_SESSION['s_message_danger'] .= _('Enter the hostname') . '<BR>';
        }
        if ($new_protocol == "") {
            $_SESSION['s_message_danger'] .= _('Enter the protocol') . '<BR>';
        }
        if ($new_port === 0) {
            $_SESSION['s_message_danger'] .= _('Enter the port') . '<BR>';
        }
        if ($new_username == "") {
            $_SESSION['s_message_danger'] .= _('Enter the username') . '<BR>';
        }
        if ($new_api_token == "" && $new_hash == "") {
            $_SESSION['s_message_danger'] .= _('Enter either the API token or remote access key/hash') . '<BR>';
        }

    } else {

        $stmt = $pdo->prepare("
            UPDATE dw_servers
            SET `name` = :new_name,
                `host` = :new_host,
                protocol = :new_protocol,
                `port` = :new_port,
                username = :new_username,
                api_token = :new_api_token,
                `hash` = :new_hash,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :new_dwsid");
        $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
        $stmt->bindValue('new_host', $new_host, PDO::PARAM_STR);
        $stmt->bindValue('new_protocol', $new_protocol, PDO::PARAM_STR);
        $stmt->bindValue('new_port', $new_port, PDO::PARAM_INT);
        $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
        $stmt->bindValue('new_api_token', $new_api_token, PDO::PARAM_STR);
        $stmt->bindValue('new_hash', $new_hash, PDO::PARAM_LOB);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('new_dwsid', $new_dwsid, PDO::PARAM_INT);
        $stmt->execute();

        $dwsid = $new_dwsid;

        $_SESSION['s_message_success'] .= sprintf(_('Server %s (%s) updated'), $new_name, $new_host) . '<BR>';

        header("Location: servers.php");
        exit;

    }

} else {

    $stmt = $pdo->prepare("
        SELECT `name`, `host`, protocol, `port`, username, api_token, `hash`, notes
        FROM dw_servers
        WHERE id = :dwsid");
    $stmt->bindValue('dwsid', $dwsid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_name = $result->name;
        $new_host = $result->host;
        $new_protocol = $result->protocol;
        $new_port = $result->port;
        $new_username = $result->username;
        $new_api_token = $result->api_token;
        $new_hash = $result->hash;
        $new_notes = $result->notes;

    }

}
if ($del === 1) {

    $stmt = $pdo->prepare("
        SELECT `name`, `host`
        FROM dw_servers
        WHERE id = :dwsid");
    $stmt->bindValue('dwsid', $dwsid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_name = $result->name;
        $new_host = $result->host;

    }

    $stmt = $pdo->prepare("
        DELETE FROM dw_accounts
        WHERE server_id = :dwsid");
    $stmt->bindValue('dwsid', $dwsid, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare("
        DELETE FROM dw_dns_records
        WHERE server_id = :dwsid");
    $stmt->bindValue('dwsid', $dwsid, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare("
        DELETE FROM dw_dns_zones
        WHERE server_id = :dwsid");
    $stmt->bindValue('dwsid', $dwsid, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare("
        DELETE FROM dw_servers
        WHERE id = :dwsid");
    $stmt->bindValue('dwsid', $dwsid, PDO::PARAM_INT);
    $stmt->execute();

    $dwstats = new DomainMOD\DwStats();
    $dwstats->updateDwTotalsTable();

    $_SESSION['s_message_success'] .= sprintf(_('Server %s (%s) deleted'), $new_name, $new_host) . '<BR>';

    header("Location: servers.php");
    exit;

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_name', _('Name') . ' (100)', _('Enter the display name for this server'), $unsanitize->text($new_name), '100', '', '1', '', '');
echo $form->showInputText('new_host', _('Host Name') . ' (100)', _('Enter the host name of your WHM installation (ie. server1.example.com).'), $unsanitize->text($new_host), '100', '', '1', '', '');
echo $form->showDropdownTop('new_protocol', _('Protocol') . ' (5)', _('Enter the protocol you connect with.'), '1', '');
echo $form->showDropdownOption('https', _('Secured (https)'), $new_protocol);
echo $form->showDropdownOption('http', _('Unsecured (http)'), $new_protocol);
echo $form->showDropdownBottom('');
echo $form->showInputText('new_port', _('Port') . ' (5)', _('Enter the port that you connect to (usually 2086 or 2087).'), $new_port, '5', '', '1', '', '');
echo $form->showInputText('new_username', _('Username') . ' (100)', _('Enter the username for your WHM installation.'), $unsanitize->text($new_username), '100', '', '1', '', '');
?>
<div class="domainmod-css-random-padding"><strong><?php echo _("Only one of the below items is required, either the API Token or the Remote Access Key/Hash. The Remote Access Key/Hash will be getting removed from WHM in version 68 though, so if your WHM already supports the API Token that's what you should use."); ?></strong></div>
<?php
echo $form->showInputText('new_api_token', _('API Token') . ' (255)', _('Enter the API token.'), $unsanitize->text($new_api_token), '255', '', '1', '', '');
echo $form->showInputTextarea('new_hash', _('Remote Access Key/Hash'), _('Enter the remote access key/hash for you WHM installation. You can retrieve this from your WHM by logging in and searching for "Remote Access". Click on the "Setup Remote Access Key" option on the left, and your hash will be displayed on the right-hand side of the screen.'), $unsanitize->text($new_hash), '1', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_dwsid', $dwsid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->deleteButton(_('Server'), $new_name, 'edit-server.php?dwsid=' . $dwsid . '&del=1');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
