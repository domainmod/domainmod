<?php
/**
 * /domains/csv-importer/index.php
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
$log = new DomainMOD\Log('/domains/csv-importer/index.php');
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/csv-importer-main.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

use iamdual\Uploader;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    try {

        if (isset($_FILES["file"])) {

            if (file_exists(DIR_TEMP . '/import.csv')) {

                unlink(DIR_TEMP . '/import.csv');

            }

            $upload = new Uploader($_FILES["file"]);
            $upload->allowed_extensions(array("csv"));
            $upload->max_size(50); // in MB
            $upload->path(DIR_TEMP);
            $upload->name("import");

            if (!$upload->upload()) {

                $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
                $_SESSION['s_message_danger'] .= _('Please choose a CSV template file to import');

            } else {

                $csv = new DomainMOD\Csv();
                $filename = DIR_TEMP . '/import.csv';
                $csv->parse($filename, $_SESSION['s_user_id']);

                $_SESSION['s_message_success'] .= _('Your CSV Import has been completed');

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
echo '&nbsp;'; echo _('This is a very new feature (ie. experimental), but all testing so far has gone incredibly well, and I\'ve personally imported many test CSV files with 100% accuracy.'); ?>&nbsp;<?php echo sprintf(_('It\'s recommended that you perform a full backup of your existing %s database before attempting a CSV Import, just in case something goes wrong and you need to recover.'), SOFTWARE_TITLE); ?><BR>
<BR><?php echo $layout->highlightText('red', strtoupper(_('Note')) . ': '); ?><?php echo _('Before you can use the CSV Importer you must update the permissions on the'); ?> <strong><?php echo DIR_TEMP; ?></strong> <?php echo strtolower(_('Folder')); ?> ("<em>chmod 777 <?php  echo DIR_TEMP; ?></em>"). <?php echo _("If you're unsure how to do this your web hosting provider should be able to assist you."); ?><BR>
<BR><?php echo _('For more information on how to use the CSV Importer, please see our'); ?>&nbsp;<a href="https://domainmod.org/docs/userguide/csv-importer/"><?php echo _('User Guide'); ?></a>.

<BR><BR><h3><?php echo _('Download CSV Template'); ?></h3>
<a href='../../import-template-sample.csv'><?php echo _('Click here'); ?></a> <?php echo _('to download the CSV Import Template.'); ?> <?php echo sprintf(_('You can import as little or as much domain information as you want.')); ?>&nbsp;<?php echo sprintf(_('Technically you can fill in nothing but a list of domains, and it will import just fine, but DomainMOD will need to create a bunch of generic assets for you. It\'s ok though, even if you go this route it\'s pretty easy to update the information that %s creates for you.'), SOFTWARE_TITLE); ?>
<BR><BR><?php echo $layout->highlightText('red', strtoupper(_('Note')) . ': '); ?><?php echo _('The columns in the CSV Import Template must remain as-is. If there are not the exact number of columns, or the columns are not in the correct order, the import will fail.'); ?>

<BR><BR><h3><?php echo _('Import CSV Template'); ?></h3>
<form enctype="multipart/form-data" action="" method="post">
    <?php echo $layout->highlightText('red', strtoupper(_('Note')) . ': '); ?><?php echo sprintf(_('This will only insert <strong>new</strong> domains into your %s database, existing domains will be skipped.'), SOFTWARE_TITLE); ?><BR><BR>
    <input type="file" name="file"><BR><BR>
    <?php echo _('Please be patient when submitting your CSV file. Depending on the amount of data, it may take a minute to process.'); ?><BR>
    <BR>
    <?php echo $layout->showButton('submit', _('Import CSV File')); ?>
</form>
<BR>

<h3><?php echo _('Cleanup'); ?></h3>
<?php echo _('Click here to delete any and all past CSV Import files that may be saved on your server.'); ?><BR>
<a href="cleanup/"><?php echo $layout->showButton('button', _('Perform Cleanup')); ?></a>
<BR><BR>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
