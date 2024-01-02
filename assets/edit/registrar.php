<?php
/**
 * /assets/edit/registrar.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
$log = new DomainMOD\Log('/assets/edit/registrar.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-registrar.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$rid = (int) ($_REQUEST['rid'] ?? 0);
$new_registrar = isset($_POST['new_registrar']) ? $sanitize->text($_POST['new_registrar']) : '';
$new_url = isset($_POST['new_url']) ? $sanitize->text($_POST['new_url']) : '';
$new_api_registrar_id = (int) ($_POST['new_api_registrar_id'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    if ($validate->text($new_registrar)) {

        $stmt = $pdo->prepare("
            UPDATE registrars
            SET `name` = :new_registrar,
                url = :new_url,
                api_registrar_id = :new_api_registrar_id,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :rid");
        $stmt->bindValue('new_registrar', $new_registrar, PDO::PARAM_STR);
        $stmt->bindValue('new_url', $new_url, PDO::PARAM_STR);
        $stmt->bindValue('new_api_registrar_id', $new_api_registrar_id, PDO::PARAM_INT);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_message_success'] .= sprintf(_('Registrar %s updated'), $new_registrar) . '<BR>';

        header("Location: ../registrars.php");
        exit;

    } else {

        if (!$validate->text($new_registrar)) $_SESSION['s_message_danger'] .= _('Enter the Registrar name') . '<BR>';

    }

} else {

    $stmt = $pdo->prepare("
        SELECT `name`, url, api_registrar_id, notes
        FROM registrars
        WHERE id = :rid");
    $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_registrar = $result->name;
        $new_url = $result->url;
        $new_api_registrar_id = $result->api_registrar_id;
        $new_notes = $result->notes;

    }

}

if ($del === 1) {

    $stmt = $pdo->prepare("
        SELECT registrar_id
        FROM registrar_accounts
        WHERE registrar_id = :rid
        LIMIT 1");
    $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $existing_registrar_accounts = 1;

    }

    $stmt = $pdo->prepare("
        SELECT registrar_id
        FROM domains
        WHERE registrar_id = :rid
        LIMIT 1");
    $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $existing_domains = 1;

    }

    if ($existing_registrar_accounts > 0 || $existing_domains > 0) {

        if ($existing_registrar_accounts > 0) $_SESSION['s_message_danger'] .= _('This Registrar has Registrar Accounts associated with it and cannot be deleted') . '<BR>';
        if ($existing_domains > 0) $_SESSION['s_message_danger'] .= _('This Registrar has domains associated with it and cannot be deleted') . '<BR>';

    } else {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                DELETE FROM fees
                WHERE registrar_id = :rid");
            $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("
                DELETE FROM registrar_accounts
                WHERE registrar_id = :rid");
            $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("
                DELETE FROM registrars
                WHERE id = :rid");
            $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
            $stmt->execute();

            $system->checkExistingAssets();

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('Registrar %s deleted'), $new_registrar) . '<BR>';

            header("Location: ../registrars.php");
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to delete registrar';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    }

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
echo $form->showInputText('new_registrar', _('Registrar Name') . ' (100)', '', $unsanitize->text($new_registrar), '100', '', '1', '', '');
echo $form->showInputText('new_url', _("Registrar's URL") . ' (100)', '', $unsanitize->text($new_url), '100', '', '', '', '');

$result = $pdo->query("
    SELECT id, `name`
    FROM api_registrars
    ORDER BY `name` ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_api_registrar_id', _('API Support'), _('If the registrar has an API please select it from the list below.'), '', '');

    echo $form->showDropdownOption('0', 'n/a', '0');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_api_registrar_id);

    }

    echo $form->showDropdownBottom('');

}

echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('rid', $rid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->deleteButton(_('Registrar'), $new_registrar, 'registrar.php?rid=' . $rid . '&del=1');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
