<?php
/**
 * /domains/csv-importer/index.php
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
$log = new DomainMOD\Log('/domains/csv-importer/index.php');
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/csv-importer-main.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

use iamdual\Uploader;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    try {

        if (isset($_FILES["file"])) {

            $upload = new Uploader($_FILES["file"]);
            $upload->allowed_extensions(array("csv"));
            $upload->max_size(50); // in MB

            // Import straight from PHP's upload file, which PHP deletes when the request ends, rather than moving it
            // into the web-accessible temp folder
            if ($upload->get_tmp_name() !== null && $upload->get_tmp_name() !== '' && $system->isInsideWebRoot(dirname($upload->get_tmp_name()))) {

                // Don't process an upload that PHP stored somewhere web-accessible
                $log_message = 'Upload refused because PHP\'s upload folder is web-accessible';
                $log_extra = array('Upload folder' => dirname($upload->get_tmp_name()));
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
                $_SESSION['s_message_danger'] .= _('This file can\'t be processed because PHP\'s upload folder (upload_tmp_dir) is inside your web root. Please move it outside your web root and try again.');

            } elseif (!$upload->check() || !is_uploaded_file($upload->get_tmp_name())) {

                $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
                $_SESSION['s_message_danger'] .= _('Please choose a CSV template file to import');

            } else {

                $csv = new DomainMOD\Csv();
                $filename = $upload->get_tmp_name();

                if (!$csv->parse($filename, $_SESSION['s_user_id'])) {

                    // The class keeps the first few rows, which is enough to find the problem.
                    $rows = $csv->invalid_rows;
                    $more = $csv->invalid_row_count - count($rows);

                    $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
                    $_SESSION['s_message_danger'] .= sprintf(_('Nothing was imported because these rows don\'t have the %s columns that the CSV Import Template has'), DomainMOD\Csv::COLUMNS) . ': '
                        . implode(', ', $rows) . ($more > 0 ? ' ' . sprintf(_('(and %s more)'), $more) : '') . '.<BR>';

                    header("Location: ./");
                    exit;

                }

                $_SESSION['s_message_success'] = $_SESSION['s_message_success'] ?? '';
                $_SESSION['s_message_success'] .= _('Your CSV Import has been completed');

                if (!empty($csv->invalid_domains)) {

                    $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
                    $_SESSION['s_message_danger'] .= _('These domains weren\'t imported because they aren\'t valid domain names') . ':<BR>'
                        . implode('<BR>', array_map(function ($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }, $csv->invalid_domains)) . '<BR>';

                }

                header("Location: ../");
                exit;

            }

        }

    } catch (Exception $e) {

        $log_message = 'Unable to imports CSV file';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

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
echo sprintf(_('This page allows you to import all of your domain data into %s using a CSV template.'), SOFTWARE_TITLE);
echo '&nbsp;'; echo _('This is a very new feature (i.e. experimental), but all testing so far has gone incredibly well, and I\'ve personally imported many test CSV files with 100% accuracy.'); ?>&nbsp;<?php echo sprintf(_('It\'s recommended that you perform a full backup of your existing %s database before attempting a CSV Import, just in case something goes wrong and you need to recover.'), SOFTWARE_TITLE); ?><BR>
<BR><?php echo _('For more information on how to use the CSV Importer, please see our'); ?>&nbsp;<a href="https://domainmod.org/docs/userguide/csv-importer/"><?php echo _('User Guide'); ?></a>.

<BR><BR><h3><?php echo _('Download CSV Template'); ?></h3>
<a href='../../import-template-sample.csv'><?php echo _('Click here'); ?></a> <?php echo _('to download the CSV Import Template.'); ?> <?php echo sprintf(_('You can import as little or as much domain information as you want.')); ?>&nbsp;<?php echo sprintf(_('Technically you can fill in nothing but a list of domains, and it will import just fine, but DomainMOD will need to create a bunch of generic assets for you. It\'s ok though, even if you go this route it\'s pretty easy to update the information that %s creates for you.'), SOFTWARE_TITLE); ?>
<BR><BR><?php echo $layout->highlightText('red', DomainMOD\Format::toUpper(_('Note')) . ': '); ?><?php echo _('The columns in the CSV Import Template must remain as-is. If there are not the exact number of columns, or the columns are not in the correct order, the import will fail.'); ?>

<BR><BR><h3><?php echo _('Import CSV Template'); ?></h3>
<form enctype="multipart/form-data" action="" method="post">
<?php echo $form->showCsrfField(); ?>
    <?php echo $layout->highlightText('red', DomainMOD\Format::toUpper(_('Note')) . ': '); ?><?php echo sprintf(_('This will only insert <strong>new</strong> domains into your %s database, existing domains will be skipped.'), SOFTWARE_TITLE); ?><BR><BR>
    <input type="file" name="file"><BR><BR>
    <?php echo _('Please be patient when submitting your CSV file. Depending on the amount of data, it may take a minute to process.'); ?><BR>
    <BR>
    <?php echo $layout->showButton('submit', _('Import CSV File')); ?>
</form>
<BR>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
