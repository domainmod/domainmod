<?php
/**
 * /bulk/cf/step-two/index.php
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
require_once __DIR__ . '/../../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/bulk/cf/step-two/index.php');
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$date = new DomainMOD\Date();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$domain = new DomainMOD\Domain();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$custom_field = new DomainMOD\CustomField();

$timestamp = $time->stamp();
$timestamp_basic = $time->timeBasic();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/bulk-main.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

$raw_domain_list = isset($_POST['raw_domain_list']) ? $sanitize->text($_POST['raw_domain_list']) : '';
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';
$id = (int) ($_REQUEST['id'] ?? 0);
$type_id = $custom_field->getTypeId('domain_fields', $id);
$is_submitted = (int) ($_POST['is_submitted'] ?? 0);

// Custom Fields
$result = $pdo->query("
    SELECT field_name
    FROM domain_fields
    ORDER BY name")->fetchAll();

if ($result) {

    $count = 0;

    foreach ($result as $row) {

        $field_array[$count] = $row->field_name;
        $count++;

    }

    foreach ($field_array as $field) {

        $full_field = "new_" . $field . "";
        ${'new_' . $field} = $_POST[$full_field] ?? '';

    }

}

$choose_text = _('Click here to choose the new');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_submitted === 1) {

    if ($id == '') {

        $_SESSION['s_message_danger'] = _('Invalid selection, please try again');

    } else {

        $format = new DomainMOD\Format();

        $domain_array = $format->cleanAndSplitDomains($raw_domain_list);

        if ($raw_domain_list == "") {

            $_SESSION['s_message_danger'] .= _('Enter the list of domains to apply the action to') . '<BR>';

        } else {

            list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) = $domain->findInvalidDomains($domain_array);

            if ($raw_domain_list == "" || $invalid_domains == 1) {

                if ($invalid_domains == 1) {

                    if ($invalid_count == 1) {

                        $_SESSION['s_message_danger'] .= sprintf(_('There is %s invalid domain on your list'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

                    } else {

                        $_SESSION['s_message_danger'] .= sprintf(_('There are %s invalid domains on your list'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

                        if (($invalid_count - $invalid_to_display) == 1) {

                            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s other'), number_format($invalid_count - $invalid_to_display)) . '<BR>';

                        } elseif (($invalid_count - $invalid_to_display) > 1) {

                            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s others'), number_format($invalid_count - $invalid_to_display)) . '<BR>';
                        }

                    }

                } else {

                    $_SESSION['s_message_danger'] .= _('Enter the list of domains to apply the action to') . '<BR>';

                }
                $submission_failed = 1;

            } else {

                try {

                    $pdo->beginTransaction();

                    $in_list = str_repeat('?, ', count($domain_array) - 1) . '?';
                    $sql = "SELECT id
                            FROM domains
                            WHERE domain IN (" . $in_list . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($domain_array);
                    $result = $stmt->fetchAll();

                    $domain_id_list = array();

                    foreach ($result as $row) {

                        $domain_id_list[] = $row->id;

                    }
                    $in_list = str_repeat('?, ', count($domain_id_list) - 1) . '?';

                    $stmt = $pdo->prepare("
                        SELECT `name`, field_name
                        FROM domain_fields
                        WHERE id = :id");
                    $stmt->bindValue('id', $id, PDO::PARAM_INT);
                    $stmt->execute();

                    $result = $stmt->fetch();
                    $stmt->closeCursor();

                    if ($result) {

                        $temp_name = $result->name;
                        $temp_field_name = $result->field_name;

                    }

                    $full_field = "new_" . $temp_field_name;

                    $stmt = $pdo->prepare("
                        UPDATE domain_field_data
                        SET `" . $temp_field_name . "` = ?,
                             update_time = ?
                        WHERE domain_id IN (" . $in_list . ")");
                    $array1 = array(${$full_field}, $timestamp);
                    $array2 = $domain_id_list;
                    $full_array = array_merge($array1, $array2);
                    $stmt->execute($full_array);

                    if ($new_notes != "") {

                        $stmt = $pdo->prepare("
                            UPDATE domains
                            SET notes = CONCAT(?, '\r\n\r\n', notes),
                                update_time = ?
                            WHERE id IN (" . $in_list . ")");
                        $array1 = array($new_notes, $timestamp);
                        $array2 = $domain_id_list;
                        $full_array = array_merge($array1, $array2);
                        $stmt->execute($full_array);

                    }

                    if ($pdo->InTransaction()) $pdo->commit();

                    $_SESSION['s_cdf_data'] = $custom_field->getCDFData();

                    $_SESSION['s_message_success'] .= sprintf(_('Custom Field %s updated'), $name_array[0]) . '<BR>';

                } catch (Exception $e) {

                    if ($pdo->InTransaction()) $pdo->rollback();

                    $log_message = 'Unable to update custom field';
                    $log_extra = array('Error' => $e);
                    $log->critical($log_message, $log_extra);

                    $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                    throw $e;

                }

                $done = "1";
                $new_data_unformatted = implode(", ", $domain_array);

            }

        }

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-picker-head.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php //@formatter:off
$breadcrumb_text = _('Update Custom Domain Field');
$breadcrumb_end = '<li class=\"active\">' . $breadcrumb_text . '</li>';
?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
$done = $done ?? '';
if ($done != '1') {
    echo _("The Bulk Updater allows you add or modify multiple domains at the same time, whether it's a couple dozen or a couple thousand, all with a few clicks.") . '<BR>';
} ?>
<?php if ($done == "1") { ?>

    <?php if ($submission_failed != "1") { ?>

        <BR><strong><?php echo _('The following domains had their') . ' <em>' . $custom_field->getName('domain_fields', $id) . ' (' . $custom_field->getType($type_id) . ')</em>'; ?> field updated:</strong><BR>

        <BR><?php echo htmlentities($new_data_unformatted, ENT_QUOTES, 'UTF-8'); ?><BR><BR>

    <?php } ?>

<?php } ?>

<?php
if ($done != '1') {

    echo $form->showFormTop('');

    if ($type_id !== 0) {

        $text = _('Domains to update (one per line)');

        echo $form->showInputTextarea('raw_domain_list', $text, '', $unsanitize->text($raw_domain_list), '1', '<BR><strong>' . $breadcrumb_text . ':  <BR>' . $custom_field->getName('domain_fields', $id) . ' (' . $custom_field->getType($type_id) . ')</strong><BR><BR>', '');

    }

    if ($type_id === 1) {

        $stmt = $pdo->prepare("
        SELECT df.name, df.field_name, df.description
        FROM domain_fields AS df, custom_field_types AS cft
        WHERE df.type_id = cft.id
          AND df.id = :id");
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                echo $form->showCheckbox('new_' . $row->field_name, '1', $row->name, $row->description, ${'new_' . $row->field_name}, '', '');

            }

        }

    } elseif ($type_id === 2) {

        $stmt = $pdo->prepare("
        SELECT df.name, df.field_name, df.description
        FROM domain_fields AS df, custom_field_types AS cft
        WHERE df.type_id = cft.id
          AND df.id = :id");
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        foreach ($result as $row) {

            echo $form->showInputText('new_' . $row->field_name, $row->name . ' (255)', $row->description, ${'new_' . $row->field_name}, '255', '', '', '', '');

        }

    } elseif ($type_id === 3) {

        $stmt = $pdo->prepare("
        SELECT df.name, df.field_name, df.description
        FROM domain_fields AS df, custom_field_types AS cft
        WHERE df.type_id = cft.id
          AND df.id = :id");
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                echo $form->showInputTextarea('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $row->field_name}, '', '', '');

            }

        }

    } elseif ($type_id === 4) {

        $stmt = $pdo->prepare("
        SELECT df.name, df.field_name, df.description
        FROM domain_fields AS df, custom_field_types AS cft
        WHERE df.type_id = cft.id
          AND df.id = :id");
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                echo $form->showInputText('new_' . $row->field_name, $row->name . ' (10)', $row->description, ${'new_' . $row->field_name}, '10', '', '', '', '');

            }

        }

    } elseif ($type_id === 5) {

        $stmt = $pdo->prepare("
        SELECT df.name, df.field_name, df.description
        FROM domain_fields AS df, custom_field_types AS cft
        WHERE df.type_id = cft.id
          AND df.id = :id");
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                echo $form->showInputText('new_' . $row->field_name, $row->name . ' (19)', $row->description, ${'new_' . $row->field_name}, '19', '', '', '', '');

            }

        }

    } elseif ($type_id === 6) {

        $stmt = $pdo->prepare("
        SELECT df.name, df.field_name, df.description
        FROM domain_fields AS df, custom_field_types AS cft
        WHERE df.type_id = cft.id
          AND df.id = :id");
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        foreach ($result as $row) {

            echo $form->showInputText('new_' . $row->field_name, $row->name . ' (255)', $row->description, ${'new_' . $row->field_name}, '255', '', '', '', '');

        }

    }

    if ($type_id !== 0) {

        $notes_heading = _('Notes (will be appended to current domain notes)');

        echo $form->showInputTextarea('new_notes', $notes_heading, '', $unsanitize->text($new_notes), '', '', ''); ?>

        <a href='../step-one/'><?php echo $layout->showButton('button', _('Go Back')); ?></a>&nbsp;&nbsp;<?php

        echo $form->showSubmitButton(_('Update Custom Domain Field'), '', '');

    }

    echo $form->showInputHidden('is_submitted', '1');
    echo $form->showFormBottom('');

} else { ?>

    <a href='../step-one/'><?php echo $layout->showButton('button', _('Go Back')); ?></a><BR><BR><?php

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
</body>
</html>
