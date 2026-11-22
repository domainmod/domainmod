<?php
/**
 * /bulk/cf/step-two/index.php
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

// Opened without choosing a field (or with one that doesn't exist), so go back to step one
if ($id < 1 || $type_id === false) {

    header('Location: ../step-one/');
    exit;

}
$is_submitted = (int) ($_POST['is_submitted'] ?? 0);

// Custom Fields. Their values are kept in their own array, keyed by field name, so a field named like one of the
// settings above (such as notes) can't overwrite it (see DomainMOD\CustomField::inputName()).
$custom_field_types = $custom_field->getFieldTypes('domain_fields');
$new_custom_fields = DomainMOD\CustomField::postedValues(array_keys($custom_field_types), $_POST);

$choose_text = _('Click here to choose the new');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_submitted === 1) {

    if ($id == '') {

        $_SESSION['s_message_danger'] = _('Invalid selection. Please try again.');

    } else {

        $format = new DomainMOD\Format();

        // A Date or Time Stamp that isn't a real date or time is marked under the field, and no domain is changed
        $chosen_field = (string) $custom_field->getFieldName('domain_fields', $id);
        $custom_fields_valid = DomainMOD\CustomField::markInvalidValues($form, array($chosen_field => $type_id), $new_custom_fields);

        $domain_array = $format->cleanAndSplitDomains($raw_domain_list);

        if ($raw_domain_list == "") {

            $form->addFieldError('raw_domain_list', _('Enter the list of domains to apply the action to'));

        } else {

            list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) = $domain->findInvalidDomains($domain_array);

            // The listed domains that aren't in DomainMOD, so the update stops before any domain is changed if there are
            // any, as in the main Bulk Updater
            $missing_to_display = array();
            $missing_count = 0;
            if ($invalid_domains != 1 && $domain_array) {
                list($missing_to_display, $missing_count) = $domain->findMissingDomains($domain_array);
            }

            if ($raw_domain_list == "" || $invalid_domains == 1 || !$domain_array || $missing_count > 0 || !$custom_fields_valid) {

                if ($invalid_domains == 1) {

                    if ($invalid_count == 1) {

                        $_SESSION['s_message_danger'] .= sprintf(_('There is %s invalid domain on your list.'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

                    } else {

                        $_SESSION['s_message_danger'] .= sprintf(_('There are %s invalid domains on your list.'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

                        if (($invalid_count - $invalid_to_display) == 1) {

                            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s other'), number_format($invalid_count - $invalid_to_display)) . '<BR>';

                        } elseif (($invalid_count - $invalid_to_display) > 1) {

                            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s others'), number_format($invalid_count - $invalid_to_display)) . '<BR>';
                        }

                    }

                } elseif ($missing_count > 0) {

                    $form->addFieldError('raw_domain_list', $domain->missingDomainsMessage($missing_to_display, $missing_count));

                } elseif (!$domain_array) {

                    $form->addFieldError('raw_domain_list', _('Enter the list of domains to apply the action to'));

                }
                $submission_failed = 1;

            } else {

                try {

                    // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                    $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                    $pdo->beginTransaction();

                    $in_list = str_repeat('?, ', count($domain_array) - 1) . '?';
                    $sql = "SELECT id, domain
                            FROM domains
                            WHERE domain IN (" . $in_list . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($domain_array);
                    $result = $stmt->fetchAll();

                    $domain_id_list = array();
                    $domain_names = array();

                    foreach ($result as $row) {

                        $domain_id_list[] = $row->id;
                        $domain_names[(int) $row->id] = strtolower((string) $row->domain);

                    }

                    // In the order the domains were pasted (then by id), so Change History lists the rows in that order.
                    // The capture below still takes its locks in ascending id order.
                    $pasted = array();
                    foreach ($domain_array as $pasted_name) {
                        if (!isset($pasted[strtolower((string) $pasted_name)])) $pasted[strtolower((string) $pasted_name)] = count($pasted);
                    }
                    usort($domain_id_list, function ($a, $b) use ($domain_names, $pasted) {
                        return array($pasted[$domain_names[(int) $a]] ?? PHP_INT_MAX, (int) $a)
                            <=> array($pasted[$domain_names[(int) $b]] ?? PHP_INT_MAX, (int) $b);
                    });
                    $in_list = str_repeat('?, ', count($domain_id_list) - 1) . '?';

                    $temp_name = '';

                    // Read with a shared lock, so the field can't be deleted while this runs, and the write below and the
                    // Change History both use it as it is now
                    $stmt = $pdo->prepare("
                        SELECT `name`, field_name
                        FROM domain_fields
                        WHERE id = :id
                        LOCK IN SHARE MODE");
                    $stmt->bindValue('id', $id, PDO::PARAM_INT);
                    $stmt->execute();

                    $result = $stmt->fetch();
                    $stmt->closeCursor();

                    if (!$result) {

                        if ($pdo->InTransaction()) $pdo->rollback();
                        $_SESSION['s_message_danger'] .= _('That custom field no longer exists.') . '<BR>';
                        header('Location: ../step-one/');
                        exit;

                    }

                    $temp_name = $result->name;
                    $temp_field_name = $result->field_name;

                    // The change is recorded in the Change History, in the same transaction: the one custom field and the
                    // notes, which the same submission can add to (see DomainMOD\ChangeHistory)
                    $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_CF', $new_notes);
                    // The notes only when this submission adds one
                    $history_before = $history_op->capture('domain', $domain_id_list, $new_notes !== '' ? array('notes') : array(),
                        array($temp_field_name));

                    $stmt = $pdo->prepare("
                        UPDATE domain_field_data
                        SET `" . $temp_field_name . "` = ?,
                             update_time = ?
                        WHERE domain_id IN (" . $in_list . ")");
                    $array1 = array($new_custom_fields[$temp_field_name] ?? '', $timestamp);
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

                    $history_op->compare($history_before);
                    $history_op->finish();

                    if ($pdo->InTransaction()) $pdo->commit();

                    $_SESSION['s_cdf_data'] = $custom_field->getCDFData();

                    $_SESSION['s_message_success'] .= sprintf(_('Custom Field %s updated'), $temp_name) . '<BR>';

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
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-picker-head.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php //@formatter:off
$breadcrumb_text = _('Update Custom Domain Field');
$breadcrumb_end = '<li class=\"active\">' . $breadcrumb_text . '</li>';
?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo $layout->stepIndicator(array(_('Custom Field'), _('Domains and Value')), 2, _('Bulk Updater steps')); ?>
<?php
$done = $done ?? '';
$submission_failed = $submission_failed ?? 0;
if ($done != '1') {
    echo $layout->calloutTop() . _("The Bulk Updater allows you to add or modify multiple domains at the same time, whether it's a couple dozen or a couple thousand, all with a few clicks.") . $layout->calloutBottom();
} ?>
<?php if ($done == "1") { ?>

    <?php if ($submission_failed != "1") { ?>

        <h2 class="h5"><?php echo sprintf(_('The following domains had their %s field updated'), '<em>' . $custom_field->getName('domain_fields', $id) . ' (' . $custom_field->getType($type_id) . ')</em>'); ?></h2>
        <p><?php echo htmlentities($new_data_unformatted, ENT_QUOTES, 'UTF-8'); ?></p>

    <?php } ?>

<?php } ?>

<?php
if ($done != '1') {

    echo $form->showFormTop('');

    if ($type_id !== 0) {

        $text = _('Domains to update (one per line)');

        // The field being updated, as the heading over the form (its name is stored escaped, as every custom field's is),
        // then a search box that adds existing domains to the list, and the list's count under it
        $before_list = '<h2 class="h5">' . $custom_field->getName('domain_fields', $id) . ' (' . $custom_field->getType($type_id) . ')</h2>'
            . $form->showDomainPicker('raw_domain_list', _('Find a domain to add to the list'),
                _('Type part of a domain name, then pick it to add it to the list below. You can also type or paste domains straight into the list.'),
                WEB_ROOT . '/domains/name-search.php');
        echo $form->showInputTextarea('raw_domain_list', $text, '', $unsanitize->text($raw_domain_list), '1', $before_list,
            $form->showDomainListCount('raw_domain_list'));

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

                echo $form->showCheckbox(DomainMOD\CustomField::inputName($row->field_name), '1', $row->name, $row->description, $new_custom_fields[$row->field_name] ?? '', '', '');

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

            echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name . ' (255)', $row->description, $new_custom_fields[$row->field_name] ?? '', '255', '', '', '', '');

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

                echo $form->showInputTextarea(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$row->field_name] ?? '', '', '', '');

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

                echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name . ' (10)', $row->description, $new_custom_fields[$row->field_name] ?? '', '10', '', '', '', '', '', 'ltr');

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

                echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name . ' (19)', $row->description, $new_custom_fields[$row->field_name] ?? '', '19', '', '', '', '', '', 'ltr');

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

            echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name . ' (255)', $row->description, $new_custom_fields[$row->field_name] ?? '', '255', '', '', '', '', '', 'ltr');

        }

    }

    if ($type_id !== 0) {

        $notes_heading = _('Notes (will be appended to current domain notes)');

        echo $form->showInputTextarea('new_notes', $notes_heading, '', $unsanitize->text($new_notes), '', '', ''); ?>

        <?php // Go Back and the main button in one row, Back on the start side (css/base.css) ?>
        <div class="domainmod-back-next"><?php echo $layout->linkButton('../step-one/', _('Go Back'), 'secondary', 'bi-arrow-left');

        echo $form->showSubmitButton(_('Update Custom Domain Field'), '', ''); ?></div><?php

    }

    echo $form->showInputHidden('is_submitted', '1');
    echo $form->showFormBottom('');

} else { ?>

    <div class="domainmod-back-next"><?php echo $layout->linkButton('../step-one/', _('Go Back'), 'secondary', 'bi-arrow-left'); ?></div><?php

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/bulk-domain-picker.js'); ?>"></script>
</body>
</html>
