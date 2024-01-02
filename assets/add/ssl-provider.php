<?php
/**
 * /assets/add/ssl-provider.php
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
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-add-ssl-provider.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

$new_ssl_provider = isset($_POST['new_ssl_provider']) ? $sanitize->text($_POST['new_ssl_provider']): '';
$new_url = isset($_POST['new_url']) ? $sanitize->text($_POST['new_url']): '';
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($validate->text($new_ssl_provider)) {

        $pdo = $deeb->cnxx;

        $stmt = $pdo->prepare("
            INSERT INTO ssl_providers
            (`name`, url, notes, created_by, insert_time)
            VALUES
            (:new_ssl_provider, :new_url, :new_notes, :created_by, :timestamp)");
        $stmt->bindValue('new_ssl_provider', $new_ssl_provider, PDO::PARAM_STR);
        $stmt->bindValue('new_url', $new_url, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $stmt->bindValue('created_by', $_SESSION['s_user_id'], PDO::PARAM_INT);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->execute();

        $_SESSION['s_message_success'] .= sprintf(_('SSL Provider %s added'), $new_ssl_provider) . '<BR>';

        if ($_SESSION['s_has_ssl_provider'] != '1') {

            $system->checkExistingAssets();

            header("Location: ../../ssl/index.php");

        } else {

            header("Location: ../ssl-providers.php");

        }
        exit;

    } else {

        if (!$validate->text($new_ssl_provider)) $_SESSION['s_message_danger'] .= _("Enter the SSL provider's name") . '<BR>';

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
echo $form->showInputText('new_ssl_provider', _('SSL Provider Name') . ' (100)', '', $unsanitize->text($new_ssl_provider), '100', '', '1', '', '');
echo $form->showInputText('new_url', _("SSL Provider's URL"), '', $unsanitize->text($new_url), '100', '', '', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showSubmitButton(_('Add SSL Provider'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
