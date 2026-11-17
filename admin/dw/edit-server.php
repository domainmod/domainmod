<?php
/**
 * /admin/dw/edit-server.php
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

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/dw-edit-server.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] == 'POST') $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$dwsid = (int) ($_GET['dwsid'] ?? 0);
$new_name = isset($_POST['new_name']) ? $sanitize->text($_POST['new_name']) : '';
$new_host = isset($_POST['new_host']) ? $sanitize->text($_POST['new_host']) : '';
$new_protocol = $_POST['new_protocol'] ?? '';
$new_port = (int) ($_POST['new_port'] ?? 0);
$new_username = isset($_POST['new_username']) ? $sanitize->text($_POST['new_username']) : '';
$new_api_token = isset($_POST['new_api_token']) ? $sanitize->text($_POST['new_api_token']) : '';
$new_hash = isset($_POST['new_hash']) ? $sanitize->text($_POST['new_hash']) : '';
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']): '';
$new_dwsid = (int) ($_POST['new_dwsid'] ?? 0);
// an unticked checkbox isn't posted, so a missing value means certificate checks are off
$new_verify_ssl = (int) ($_POST['new_verify_ssl'] ?? 0) === 1 ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_name == "" || $new_host == "" || $new_protocol == "" || $new_port === 0 || $new_username == "" || ($new_api_token == "" && $new_hash == "")
    ) {

        if ($new_name == "") {
            $form->addFieldError('new_name', _('Enter a display name for the server'));
        }
        if ($new_host == "") {
            $form->addFieldError('new_host', _('Enter the hostname'));
        }
        if ($new_protocol == "") {
            $form->addFieldError('new_protocol', _('Enter the protocol'));
        }
        if ($new_port === 0) {
            $form->addFieldError('new_port', _('Enter the port'));
        }
        if ($new_username == "") {
            $form->addFieldError('new_username', _('Enter the username'));
        }
        if ($new_api_token == "" && $new_hash == "") {
            $form->addFieldError(array('new_api_token', 'new_hash'), _('Enter either the API token or remote access key/hash'));
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
                verify_ssl = :new_verify_ssl,
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
        $stmt->bindValue('new_verify_ssl', $new_verify_ssl, PDO::PARAM_INT);
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
        SELECT `name`, `host`, protocol, `port`, username, api_token, `hash`, verify_ssl, notes
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
        $new_verify_ssl = (int) $result->verify_ssl;
        $new_notes = $result->notes;

    }

}
if ($del === 1) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

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
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
// The fields in titled cards, the same way as the edit domain page (Layout::formSection())
ob_start();
echo $form->showInputText('new_name', _('Name') . ' (100)', _('Enter the display name for this server'), $unsanitize->text($new_name), '100', '', '1', '', '');
echo $form->showInputText('new_host', _('Host Name') . ' (100)', _('Enter the host name of your WHM installation (e.g. server1.example.com).'), $unsanitize->text($new_host), '100', '', '1', '', '');
echo $form->showDropdownTop('new_protocol', _('Protocol') . ' (5)', _('Enter the protocol you connect with.'), '1', '');
echo $form->showDropdownOption('https', _('Secured (https)'), $new_protocol);
echo $form->showDropdownOption('http', _('Unsecured (http)'), $new_protocol);
echo $form->showDropdownBottom('', 'new_protocol');
echo $form->showInputText('new_port', _('Port') . ' (5)', _('Enter the port that you connect to (usually 2086 or 2087).'), $new_port, '5', '', '1', '', '');
echo $form->showSwitch(_('Verify SSL Certificate') . '?', _("Leave this on unless your server uses a self-signed certificate and you connect over a network you trust. When it's on, the server's SSL certificate must be valid for the host name you entered."), 'new_verify_ssl', $new_verify_ssl, '<div class="domainmod-span-all mb-3">', '</div>');
echo $layout->formSection(_('Server'), 'server', ob_get_clean());

// Either credential works, so neither one is marked as required, and they share one field error
ob_start(); ?>
<p class="domainmod-span-all"><?php echo _('Enter an API token or a remote access key. Only one is needed, and an API token is the better choice if your WHM supports it.'); ?></p><?php
echo $form->showInputText('new_username', _('Username') . ' (100)', _('Enter the username for your WHM installation.'), $unsanitize->text($new_username), '100', '', '1', '', '');
echo $form->showInputText('new_api_token', _('API Token') . ' (255)', _('Enter the API token.'), $unsanitize->text($new_api_token), '255', '', '', '', '');
echo $form->showInputTextarea('new_hash', _('Remote Access Key/Hash'), _('Enter the remote access key/hash for your WHM installation. You can retrieve this from your WHM by logging in and searching for "Remote Access". Click on the "Setup Remote Access Key" option on the left, and your hash will be displayed on the right-hand side of the screen.'), $unsanitize->text($new_hash), '', '<div class="domainmod-span-all">', '</div>');
echo $layout->formSection(_('Credentials'), 'credentials', ob_get_clean());

ob_start();
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '<div class="domainmod-span-all">', '</div>');
echo $layout->formSection(_('Notes'), 'notes', ob_get_clean());

echo $form->showInputHidden('new_dwsid', $dwsid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->dangerZone(_('Server'), $new_name, 'edit-server.php?dwsid=' . $dwsid . '&del=1', _('Delete this server'), sprintf(_('This removes %s and its accounts, DNS zones, and records from the Data Warehouse. It can\'t be undone.'), '<strong>' . htmlentities(html_entity_decode($new_name, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>'), 2);
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
