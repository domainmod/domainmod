<?php
/**
 * /segments/edit.php
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/segments/edit.php');
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/segments-edit.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$segid = (int) ($_GET['segid'] ?? 0);

$del = (int) ($_GET['del'] ?? 0);

$new_name = isset($_POST['new_name']) ? $sanitize->text($_POST['new_name']) : '';
$new_description = isset($_POST['new_description']) ? $sanitize->text($_POST['new_description']) : '';
$raw_domain_list = isset($_POST['raw_domain_list']) ? $sanitize->text($_POST['raw_domain_list']) : '';
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';
$new_segid = (int) ($_POST['new_segid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    $format = new DomainMOD\Format();
    $domain_array = $format->cleanAndSplitDomains($raw_domain_list);

    if ($new_name != "" && $raw_domain_list != "") {

        $number_of_domains = count($domain_array);

        $domain = new DomainMOD\Domain();

        list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) =
            $domain->findInvalidDomains($domain_array);

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

            }
            $submission_failed = 1;

        } else {

            try {

                $pdo->beginTransaction();

                $domain = new DomainMOD\Domain();

                foreach ($domain_array as $key => $new_domain) {

                    if (!$domain->checkFormat($new_domain)) {
                        echo _('invalid domain') . ' ' . $key;
                        exit;
                    }

                }

                $new_data_formatted = $format->formatForMysql($domain_array);

                $stmt = $pdo->prepare("
                    UPDATE segments
                    SET `name` = :new_name,
                        description = :new_description,
                        segment = :new_data_formatted,
                        number_of_domains = :number_of_domains,
                        notes = :new_notes,
                        update_time = :timestamp
                    WHERE id = :segid");
                $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
                $stmt->bindValue('new_description', $new_description, PDO::PARAM_LOB);
                $stmt->bindValue('new_data_formatted', $new_data_formatted, PDO::PARAM_LOB);
                $stmt->bindValue('number_of_domains', $number_of_domains, PDO::PARAM_INT);
                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
                $stmt->execute();

                // Delete domains that have been removed from the segment
                $stmt = $pdo->prepare("
                    DELETE FROM segment_data
                    WHERE segment_id = :new_segid
                      AND domain NOT IN (" . $new_data_formatted . ")");
                $stmt->bindValue('new_segid', $new_segid, PDO::PARAM_INT);
                $stmt->execute();

                // Get the old segment domains
                $stmt = $pdo->prepare("
                    SELECT domain
                    FROM segment_data
                    WHERE segment_id = :new_segid");
                $stmt->bindValue('new_segid', $new_segid, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Find the domains that need updating (ie. they're in both the old segment as well as the new segment)
                $common_domains = array_intersect($domain_array, $result);
                $common_domains_formatted = $format->formatForMysql($common_domains);

                // Update domains that were already in the segment
                $stmt = $pdo->prepare("
                        UPDATE segment_data
                        SET update_time = :timestamp
                        WHERE segment_id = :new_segid
                          AND domain IN (" . $common_domains_formatted . ")");
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->bindValue('new_segid', $new_segid, PDO::PARAM_INT);
                $stmt->execute();

                // Find the domains that need inserting (ie. they don't exist in the old segment)
                $new_domains = array_diff($domain_array, $result);

                $stmt = $pdo->prepare("
                    INSERT INTO segment_data
                    (segment_id, domain, insert_time)
                    VALUES
                    (:new_segid, :domain, :timestamp)");
                $stmt->bindValue('new_segid', $new_segid, PDO::PARAM_INT);
                $stmt->bindParam('domain', $bind_new_domain, PDO::PARAM_STR);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);

                foreach ($new_domains as $bind_new_domain) {

                        // Insert domains that were not already in the segment
                        $stmt->execute();

                }

                $segid = $new_segid;

                $maint->updateSegments();

                if ($pdo->InTransaction()) $pdo->commit();

                $_SESSION['s_message_success'] .= sprintf(_('Segment %s update'), $new_name) . '<BR>';

                header("Location: ../segments/");
                exit;

            } catch (Exception $e) {

                if ($pdo->InTransaction()) $pdo->rollback();

                $log_message = 'Unable to update segment';
                $log_extra = array('Error' => $e);
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                throw $e;

            }

        }

    } else {

        if ($new_name == "") $_SESSION['s_message_danger'] .= _('Enter the Segment name') . '<BR>';
        if ($raw_domain_list == "") $_SESSION['s_message_danger'] .= _('Enter the Segment') . '<BR>';

    }

} else {

    $stmt = $pdo->prepare("
        SELECT id, `name`, description, segment, notes
        FROM segments
        WHERE id = :segid");
    $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_id = $result->id;
        $new_name = $result->name;
        $new_description = $result->description;
        $domain_array_formatted = $result->segment;
        $new_notes = $result->notes;

    }

    $raw_domain_list = preg_replace("/', '/", "\r\n", $domain_array_formatted);
    $raw_domain_list = preg_replace("/','/", "\r\n", $raw_domain_list);
    $raw_domain_list = preg_replace("/'/", "", $raw_domain_list);

}

if ($del === 1) {

    try {

        $pdo->beginTransaction();

        $segment = new DomainMOD\Segment();
        $temp_segment_name = $segment->getName($segid);

        $stmt = $pdo->prepare("
            DELETE FROM segments
            WHERE id = :segid");
        $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            DELETE FROM segment_data
            WHERE segment_id = :segid");
        $stmt->bindValue('segid', $segid, PDO::PARAM_INT);
        $stmt->execute();

        if ($pdo->InTransaction()) $pdo->commit();

        $_SESSION['s_message_success'] .= sprintf(_('Segment %s deleted'), $temp_segment_name) . '<BR>';

        header("Location: ../segments/");
        exit;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();

        $log_message = 'Unable to delete segment';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

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
echo $form->showInputText('new_name', _('Segment Name') . ' (35)', '', $unsanitize->text($new_name), '35', '', '1', '', '');
echo $form->showInputTextarea('raw_domain_list', _('Segment Domains (one per line)'), '', $unsanitize->text($raw_domain_list), '1', '', '');
echo $form->showInputTextarea('new_description', _('Description'), '', $unsanitize->text($new_description), '', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_segid', $segid);
echo $form->showSubmitButton(_('Update Segment'), '', '');
echo $form->showFormBottom('');
$layout->deleteButton(_('Segment'), $new_name, 'edit.php?segid=' . $segid . '&del=1');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
