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
                $_SESSION['s_message_danger'] .= _('This file can\'t be processed because PHP\'s upload folder (upload_tmp_dir) is inside your web root. Please move it outside your web root and try again.') . '<BR>';

            } elseif (!$upload->check() || !is_uploaded_file($upload->get_tmp_name())) {

                $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
                $_SESSION['s_message_danger'] .= _('Please choose a CSV template file to import.') . '<BR>';

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
                $_SESSION['s_message_success'] .= _('Your CSV Import has been completed.') . '<BR>';

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
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>

<div class="domainmod-csv-importer">
<p><?php echo _('Add domains in bulk from a spreadsheet. Fill in the template, then import it.'); ?> <?php echo sprintf(_('See %sCSV Importer%s in the User Guide.'), '<a href="https://domainmod.org/docs/userguide/csv-importer/">', '</a>'); ?></p>
<div class="callout callout-warning">
    <strong><?php echo _('Back up first.'); ?></strong> <?php
    // Only administrators can take a backup, so others are pointed to one instead of a page they can't open
    if (isset($_SESSION['s_is_admin']) && $_SESSION['s_is_admin'] === 1) {
        echo sprintf(_('An import adds many records at once, so take a %sbackup%s before you start.'), '<a href="' . WEB_ROOT . '/admin/backup/">', '</a>');
    } else {
        echo _('An import adds many records at once, so ask an administrator to take a backup before you start.');
    } ?>
</div>
<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="card card-outline card-secondary h-100 mb-0">
            <div class="card-body">
                <h2 class="h5 domainmod-csv-importer-step"><span class="domainmod-csv-importer-number" aria-hidden="true">1</span><span class="visually-hidden"><?php echo sprintf(_('Step %1$s of %2$s:'), 1, 2); ?> </span><?php echo _('Download the Template'); ?></h2>
                <ul>
                    <li><?php echo sprintf(_('Fill in as much or as little as you like. A list of domain names on its own works, and %s creates the rest for you to edit later.'), SOFTWARE_TITLE); ?></li>
                    <li><?php echo _('Keep the columns as they are, in the same order.'); ?></li>
                </ul>
                <a href="../../import-template-sample.csv" class="btn btn-outline-secondary"><i class="bi bi-download" aria-hidden="true"></i> <?php echo _('Download Template'); ?></a>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card card-outline card-secondary h-100 mb-0">
            <div class="card-body">
                <h2 class="h5 domainmod-csv-importer-step"><span class="domainmod-csv-importer-number" aria-hidden="true">2</span><span class="visually-hidden"><?php echo sprintf(_('Step %1$s of %2$s:'), 2, 2); ?> </span><?php echo _('Import Your File'); ?></h2>
                <?php // The Import button shows a spinner while the file uploads (js/csv-importer.js), and the status line
                      // below tells screen readers it has started ?>
                <div class="visually-hidden" role="status" id="domainmod-csv-importer-status"></div>
                <form enctype="multipart/form-data" action="" method="post" class="domainmod-csv-importer-form" data-busy-text="<?php echo htmlspecialchars(_('Importing…'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo $form->showCsrfField(); ?>
                    <label class="form-label" for="domainmod-csv-file"><?php echo _('CSV File'); ?></label>
                    <input type="file" class="form-control mb-2" name="file" id="domainmod-csv-file" accept=".csv,text/csv" aria-describedby="domainmod-csv-file-help">
                    <p class="small text-body-secondary" id="domainmod-csv-file-help"><?php echo sprintf(_('Only new domains are added. Domains already in %s are skipped.'), SOFTWARE_TITLE); ?> <?php echo _('A large file can take a minute.'); ?></p>
                    <?php echo $layout->showButton('submit', '<i class="bi bi-upload" aria-hidden="true"></i> ' . _('Import CSV File')); ?>
                </form>
            </div>
        </div>
    </div>
</div>
</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/csv-importer.js'); ?>"></script>
</body>
</html>
