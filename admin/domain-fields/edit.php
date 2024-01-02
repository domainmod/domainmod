<?php
/**
 * /admin/domain-fields/edit.php
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
<?php //@formatter:off
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/admin/domain-fields/edit.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$custom_field = new DomainMOD\CustomField();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-edit-custom-domain-field.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$cdfid = (int) ($_GET['cdfid'] ?? 0);

$new_name = isset($_POST['new_name']) ? $sanitize->text($_POST['new_name']) : '';
$new_description = isset($_POST['new_description']) ? $sanitize->text($_POST['new_description']) : '';
$new_cdfid = (int) ($_POST['new_cdfid'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

if ($new_cdfid === 0) $new_cdfid = $cdfid;

$stmt = $pdo->prepare("
    SELECT id
    FROM domain_fields
    WHERE id = :cdfid");
$stmt->bindValue('cdfid', $cdfid, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchColumn();

if (!$result) {

    $_SESSION['s_message_danger'] .= _("The Custom Domain Field you're trying to edit is invalid") . '<BR>';

    header("Location: ../domain-fields/");
    exit;

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_name != '') {

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT field_name
            FROM domain_fields
            WHERE id = :new_cdfid");
        $stmt->bindValue('new_cdfid', $new_cdfid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if ($result) {

            $stmt = $pdo->prepare("
                UPDATE domain_fields
                SET `name` = :new_name,
                    description = :new_description,
                    notes = :new_notes,
                    update_time = :timestamp
                WHERE id = :new_cdfid");
            $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
            $stmt->bindValue('new_description', $new_description, PDO::PARAM_STR);
            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
            $timestamp = $time->stamp();
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('new_cdfid', $new_cdfid, PDO::PARAM_INT);
            $stmt->execute();

        }

        if ($pdo->InTransaction()) $pdo->commit();

        $_SESSION['s_cdf_data'] = $custom_field->getCDFData();

        $_SESSION['s_message_success'] .= sprintf(_('Custom Domain Field %s (%s) updated'), $new_name, $result) . '<BR>';

        header("Location: ../domain-fields/");
        exit;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();

        $log_message = 'Unable to update custom domain field';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_name == '') $_SESSION['s_message_danger'] .= _('Enter the display name') . '<BR>';

    } else {

        $stmt = $pdo->prepare("
            SELECT f.name, f.field_name, f.description, f.notes, t.name AS field_type
            FROM domain_fields AS f, custom_field_types AS t
            WHERE f.type_id = t.id
              AND f.id = :cdfid
            ORDER BY f.name");
        $stmt->bindValue('cdfid', $cdfid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result) {

            $new_name = $result->name;
            $new_field_name = $result->field_name;
            $new_description = $result->description;
            $new_notes = $result->notes;
            $new_field_type = $result->field_type;

        }

    }

}

if ($del === 1) {

    if ($cdfid === 0) {

        $_SESSION['s_message_danger'] .= _('The Custom Domain Field cannot be deleted') . '<BR>';

    } else {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT `name`, field_name
                FROM domain_fields
                WHERE id = :cdfid");
            $stmt->bindValue('cdfid', $cdfid, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if ($result) {

                $pdo->query("
                    ALTER TABLE `domain_field_data`
                    DROP `" . $result->field_name . "`");

                $stmt = $pdo->prepare("
                    DELETE FROM domain_fields
                    WHERE id = :cdfid");
                $stmt->bindValue('cdfid', $cdfid, PDO::PARAM_INT);
                $stmt->execute();

                $pdo->query("
                    ALTER TABLE `user_settings`
                    DROP `dispcdf_" . $result->field_name . "`");

            }

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_cdf_data'] = $custom_field->getCDFData();

            $_SESSION['s_message_success'] .= sprintf(_('Custom Domain Field %s (%s) delete'), $result->name, $result->field_name) . '<BR>';

            header("Location: ../domain-fields/");
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to delete custom domain field';
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
echo $form->showInputText('new_name', _('Display Name') . ' (75)', '', $unsanitize->text($new_name), '75', '', '1', '', '');
?>
<strong><?php echo _('Database Field Name'); ?></strong><BR><?php echo $new_field_name; ?><BR><BR>
<strong><?php echo _('Data Type'); ?></strong><BR><?php echo $new_field_type; ?><BR><BR>
<?php
echo $form->showInputText('new_description', _('Description') . ' (255)', '', $unsanitize->text($new_description), '255', '', '', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_cdfid', $cdfid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->deleteButton(_('Custom Domain Field'), $new_name, 'edit.php?cdfid=' . $cdfid . '&del=1');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
