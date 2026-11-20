<?php
/**
 * /queue/ssl/index.php
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
<?php //@formatter:off
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$ssl_queue = new DomainMOD\SslQueue();
$queue = new DomainMOD\DomainQueue();
$fee_status = new DomainMOD\FeeStatus();
$unsanitize = new DomainMOD\Unsanitize();
$csrf = new DomainMOD\Csrf();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/queue-ssl.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;
$ssl_queue_available = $ssl_queue->isAvailable();
$read_only = ($_SESSION['s_read_only'] ?? '') == '1';

$id = (int) ($_GET['id'] ?? 0);
$del = (int) ($_GET['del'] ?? 0);
$really_del = (int) ($_GET['really_del'] ?? 0);
$really_clear = (int) ($_GET['really_clear'] ?? 0);
$really_retry = (int) ($_GET['really_retry'] ?? 0);
$export_data = (int) ($_GET['export_data'] ?? 0);

// The actions change the queue, so they wait for the database update like the rest of the page
if ($ssl_queue_available && $really_clear === 1) {

    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    if ($ssl_queue->clearFinished() === false) {

        $_SESSION['s_message_danger'] .= _('Unable to clear completed items from the queue') . '<BR>';

    } else {

        $_SESSION['s_message_success'] .= _('Completed items cleared from the queue') . '<BR>';

    }

    header("Location: index.php");
    exit;

}

if ($ssl_queue_available && $really_retry === 1) {

    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    $retried = $ssl_queue->retryFailed();

    if ($retried === false) {

        $_SESSION['s_message_danger'] .= _('Unable to retry the failed items') . '<BR>';

    } else {

        if ($retried['reopened'] > 0) $_SESSION['s_message_success'] .= _('Failed items put back in the queue') . '<BR>';

        if ($retried['marked'] > 0) {

            $_SESSION['s_message_info'] .= sprintf(ngettext("%s row wasn't retried, since the same hostname is already in the queue.",
                "%s rows weren't retried, since the same hostname is already in the queue.", $retried['marked']),
                number_format($retried['marked'])) . '<BR>';

        }

        if ($retried['busy'] > 0) {

            $_SESSION['s_message_warning'] .= sprintf(ngettext("%s row wasn't retried, since the same hostname is being checked now. Retry it once it finishes.",
                "%s rows weren't retried, since the same hostname is being checked now. Retry them once it finishes.", $retried['busy']),
                number_format($retried['busy'])) . '<BR>';

        }

        if ($retried['full'] > 0) {

            $_SESSION['s_message_warning'] .= sprintf(ngettext("%s row wasn't retried, since the queue is full.",
                "%s rows weren't retried, since the queue is full.", $retried['full']), number_format($retried['full'])) . '<BR>';

        }

    }

    header("Location: index.php");
    exit;

}

/*
 * A delete's second step: the question, then Yes, Delete (which carries the CSRF token) and a plain Cancel link back to
 * the queue in the alert's own colour, side by side, wrapping on narrow screens
 */
if ($ssl_queue_available && $del === 1 && $id !== 0 && !$read_only) {

    $_SESSION['s_message_danger'] .= _('Are you sure you want to delete this hostname from the queue?')
        . '<div class="d-flex flex-wrap align-items-center gap-3 mt-2">'
        . $layout->linkButton('index.php?really_del=1&id=' . $id . '&' . $csrf->query(), _('Yes, Delete'), 'primary')
        . '<a class="alert-link" href="index.php">' . _('Cancel') . '</a></div>';

}

if ($ssl_queue_available && $really_del === 1 && $id !== 0) {

    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    $deleted = $ssl_queue->deleteRow($id);

    if ($deleted === 'deleted' || $deleted === 'gone') {

        $_SESSION['s_message_success'] .= _('Hostname deleted from the queue') . '<BR>';

    } elseif ($deleted === 'claimed') {

        $_SESSION['s_message_danger'] .= _("This hostname is being checked now, so it can't be deleted. Try again once it finishes.") . '<BR>';

    } else {

        $_SESSION['s_message_danger'] .= _('Unable to delete the hostname from the queue') . '<BR>';

    }

    header("Location: index.php");
    exit;

}

$rows = array();
$ssl_queue_processing = false;

if ($ssl_queue_available) {

    // Checked before the rows are read, so a run that ends in between leaves the notice up (it then reloads the page)
    // rather than showing a finished row as Processing with no notice
    $ssl_queue_processing = $ssl_queue->isProcessing();

    // Imports happen in cron, where no session is updated, so the page's indicators are worked out again each time
    $_SESSION['s_missing_ssl_fees'] = $system->checkForRows((new DomainMOD\QueryBuild())->missingFees('ssl_certs'));
    $system->checkExistingAssets();
    $ssl_queue->checkSslQueue();

    // The certificate's details are only joined while it still exists (a deleted one leaves the row without a link)
    $rows = $pdo->query("
        SELECT q.id, q.host, q.domain_name, q.wait_domain, q.expiry_date, q.issuer, q.processing, q.finished, q.failed,
            q.already_in_ssl, q.already_in_queue, q.wait_for_domain, q.message, q.attempts, q.last_attempt, q.created_by,
            q.insert_time,
            c.id AS ssl_id, c.name, c.fee_id, f.id AS fee_row_id, p.id AS provider_id, p.name AS provider, t.id AS type_id,
            t.type, u.first_name, u.last_name
        FROM ssl_queue AS q
        LEFT JOIN ssl_certs AS c ON q.new_ssl_id > 0 AND c.id = q.new_ssl_id
        LEFT JOIN ssl_providers AS p ON c.ssl_provider_id = p.id
        LEFT JOIN ssl_cert_types AS t ON c.type_id = t.id
        LEFT JOIN ssl_fees AS f ON c.fee_id = f.id
        LEFT JOIN users AS u ON q.created_by = u.id
        ORDER BY q.insert_time DESC, q.id DESC")->fetchAll();

    // A row a run claimed after the check above keeps the notice up too, so it can't look stuck as Processing
    foreach ($rows as $row) {
        if (DomainMOD\SslQueue::freshClaim($row, time())) $ssl_queue_processing = true;
    }

}

/*
 * A row's status as plain text pieces: its label, and the detail shown under it (a reason, or what it waits for), with
 * the "Not trusted" or "Expired" flag of an imported certificate. The table and the export both use it.
 */
$status_parts = function ($row) use ($ssl_queue) {

    $state = DomainMOD\SslQueue::rowState($row);
    $flag = '';
    $detail = '';

    if ($state === 'pending') {

        $label = _('Pending');

    } elseif ($state === 'waiting') {

        $label = _('Waiting for the Domain');
        // TRANSLATORS: Shown under "Waiting for the Domain" in the SSL Queue. %s is a domain.
        $detail = sprintf(_('Waiting for %s to be added. This is checked on the next run.'), $row->wait_domain);

    } elseif ($state === 'processing') {

        $label = _('Processing');

    } elseif ($state === 'retrying') {

        $label = _('Retrying');
        // TRANSLATORS: Shown under "Retrying" in the SSL Queue, before the reason. %1$s and %2$s are numbers, such as "Attempt 1 of 3 failed."
        $detail = sprintf(_('Attempt %1$s of %2$s failed.'), (int) $row->attempts, DomainMOD\SslQueue::MAX_ATTEMPTS) . ' '
            . $ssl_queue->getMessage((string) $row->message);

    } elseif ($state === 'imported') {

        $label = _('Imported');
        if ($row->message === 'untrusted') $flag = _('Not trusted');
        if ($row->message === 'expired') $flag = _('Expired');

    } elseif ($state === 'already_in_queue') {

        $label = _('Already in the Queue');

    } elseif ($state === 'already_in_ssl') {

        $label = sprintf(_('Already in %s'), SOFTWARE_TITLE);

    } else {

        $label = _('Failed');
        $detail = $ssl_queue->getMessage((string) $row->message);

    }

    return array($state, $label, $flag, $detail);
};

// The person who queued a row, or [unknown] when they're gone
$added_by = function ($row) {
    if ($row->first_name === null) return '[' . DomainMOD\Format::toLower(_('Unknown')) . ']';
    return $row->first_name . ' ' . $row->last_name;
};

if ($ssl_queue_available && $export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('ssl_queue'), strtotime($time->stamp()));

    $row_contents = array(_('SSL Queue'));
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('Details'),
        _('Host'),
        _('Domain'),
        _('Expiry Date'),
        _('Issuer'),
        _('Added By'),
        _('Date Added')
    );
    $export->writeRow($export_file, $row_contents);

    foreach ($rows as $row) {

        list($state, $label, $flag, $detail) = $status_parts($row);
        $domain_shown = ($row->domain_name !== '') ? $row->domain_name : $row->wait_domain;

        // the table's missing-fee note, as plain text
        if ($state === 'imported' && $row->ssl_id !== null && !$fee_status->hasFee($row)) {
            $detail = _("This SSL certificate has no fee yet, so it's hidden from the SSL Certificates list until its fee is added.");
        }

        $row_contents = array(
            $ssl_queue->exportText($label . ($flag !== '' ? ' (' . $flag . ')' : '')),
            $ssl_queue->exportText($detail),
            $ssl_queue->exportText($row->host),
            $ssl_queue->exportText($domain_shown),
            ($row->expiry_date !== '1970-01-01') ? $row->expiry_date : '',
            $ssl_queue->exportText($row->issuer),
            $ssl_queue->exportText($added_by($row)),
            $time->toUserTimezone($row->insert_time)
        );
        $export->writeRow($export_file, $row_contents);

    }

    $export->closeFile($export_file);

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
if (!$ssl_queue_available) {

    echo '<p>' . _('SSL imports become available after the next database update.') . '</p>';
    echo '<div class="domainmod-back-next">' . $layout->linkButton($web_root . '/queue/', _('Go Back'), 'secondary', 'bi-arrow-left') . '</div>';

} else {

    require_once DIR_INC . '/layout/import-queue-tabs.inc.php';

    if ($ssl_queue_processing) { ?>

        <?php echo $ssl_queue->processingNotice(); ?><BR><BR><?php

    } ?>
    <p class="domainmod-intro"><?php echo sprintf(_("The SSL Queue reads the certificate of each site you add and adds it to %s as an SSL certificate, linked to its domain."), SOFTWARE_TITLE); ?>

    <?php echo sprintf(_('See the %sImport Queue information page%s for more details.'), '<a href="' . htmlspecialchars($web_root . '/queue/intro.php', ENT_QUOTES, 'UTF-8') . '">', '</a>'); ?></p>
    <?php
    // The page's actions, drawn in the page header (see Layout::renderPageActions()). Read-only users can't clear or
    // retry, so they don't get those buttons (the handlers turn them away too).
    list($completed_count, $total_count) = DomainMOD\SslQueue::progressCounts($rows);
    $failed_count = 0;
    foreach ($rows as $row) {
        // stale_choice rows ask for the hostname to be added again, so Retry Failed leaves them out
        if (DomainMOD\SslQueue::rowState($row) === 'failed' && $row->message !== 'stale_choice') $failed_count++;
    }

    // Both status pages hold every way in, so the type is chosen from one place, with Add SSL Certificates as this page's
    // main action. The domain ways in follow the RDAP availability check queue/index.php makes. Layout leaves them all
    // out for read-only users.
    $queue_rdap_available = $queue->getRdapApiRegistrarId() > 0;
    $layout->setPageAddAction(_('Add SSL Certificates'), $web_root . '/queue/add/ssl.php');
    $layout->addPageAddMenuItem($queue_rdap_available ? _('Add Domains Using an API') : _('Add Domains To Queue'), $web_root . '/queue/add/', 'bi-diagram-3');
    if ($queue_rdap_available) $layout->addPageAddMenuItem(_('Add Domains Without an API'), $web_root . '/queue/add/rdap.php', 'bi-list-check');

    if ($completed_count > 0) {
        $layout->addPageAction(_('Clear Completed'), 'index.php?really_clear=1&' . $csrf->query(), 'bi-check2-all',
            array('confirm' => _('Are you sure you want to clear completed items from the queue?') . ' '
                . _('Before clearing the queue you should review the results to make sure that everything is correct.')));
    }
    if ($failed_count > 0 && !$read_only) $layout->addPageAction(_('Retry Failed'), 'index.php?really_retry=1&' . $csrf->query(), 'bi-arrow-repeat');
    $layout->addPageAction(_('More Info'), $web_root . '/queue/intro.php', 'bi-info-circle');
    if ($rows) $layout->addPageAction(_('Export'), 'index.php?export_data=1', 'bi-download');

    if (!$rows) {

        echo $layout->emptyState('bi-inbox', _('The SSL Queue is empty'), sprintf(_('Hostnames you add wait here while %s reads their certificates.'), SOFTWARE_TITLE));

    } else {

        // Imported certificates with no fee are hidden from the SSL Certificates list, so the page says how many
        $no_fee_count = 0;
        foreach ($rows as $row) {
            if (DomainMOD\SslQueue::rowState($row) === 'imported' && $row->ssl_id !== null && !$fee_status->hasFee($row)) $no_fee_count++;
        }

        if ($no_fee_count > 0) {

            echo $system->showMessageWarning(sprintf(ngettext("%s imported SSL certificate has no fee yet, so it's hidden from the SSL Certificates list until its fee is added.",
                "%s imported SSL certificates have no fee yet, so they're hidden from the SSL Certificates list until their fees are added.", $no_fee_count),
                number_format($no_fee_count)));

        } ?>

        <p><?php
            // TRANSLATORS: The SSL Queue's progress. %1$s and %2$s are numbers, such as "12 of 40 completed".
            echo sprintf(ngettext('%1$s of %2$s completed', '%1$s of %2$s completed', $total_count), number_format($completed_count), number_format($total_count)); ?></p>

        <table id="<?php echo $slug; ?>-hosts" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th width="20px"><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
                <th><?php echo _('Status'); ?></th>
                <th><?php echo _('Host'); ?></th>
                <th><?php echo _('Domain'); ?></th>
                <th><?php echo _('Expiry Date'); ?></th>
                <th><?php echo _('Issuer'); ?></th>
                <th><?php echo _('Added By'); ?></th>
                <th><?php echo _('Date Added'); ?></th><?php
                if (!$read_only) { ?>
                    <th><?php echo _('Delete'); ?></th><?php
                } ?>
            </tr>
            </thead>
            <tbody><?php

            foreach ($rows as $row) {

                list($state, $label, $flag, $detail) = $status_parts($row);
                $domain_shown = ($row->domain_name !== '') ? $row->domain_name : $row->wait_domain;
                // Imported and Already in DomainMOD rows link to the certificate while it still exists
                $linked = ($state === 'imported' || $state === 'already_in_ssl') && $row->ssl_id !== null; ?>

                <tr>
                <td></td>
                <td><?php
                    if ($state === 'imported') {
                        echo $layout->highlightText('green', $label);
                    } elseif ($state === 'failed' || $state === 'already_in_queue' || $state === 'already_in_ssl') {
                        echo $layout->highlightText('red', $label);
                    } else {
                        echo $label;
                    }
                    if ($flag !== '') echo ' ' . $layout->highlightText('red', $flag);
                    if ($detail !== '') echo '<BR>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
                    if ($state === 'imported' && $row->ssl_id !== null && !$fee_status->hasFee($row)) {
                        echo '<BR>' . $fee_status->sslWarning($row, !$read_only);
                    } ?>
                </td>
                <td><?php
                    if ($linked) {
                        echo '<a href="' . htmlspecialchars($web_root . '/ssl/edit.php?sslcid=' . (int) $row->ssl_id, ENT_QUOTES, 'UTF-8') . '">' . $layout->ltr($row->host) . '</a>';
                    } else {
                        echo $layout->ltr($row->host);
                    } ?>
                </td>
                <td><?php echo ($domain_shown !== '') ? $layout->ltr($domain_shown) : '-'; ?></td>
                <td><?php echo ($row->expiry_date !== '1970-01-01') ? $row->expiry_date : '-'; ?></td>
                <td><?php echo ($row->issuer !== '') ? htmlspecialchars($unsanitize->text($row->issuer), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                <td><?php echo htmlspecialchars($unsanitize->text($added_by($row)), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $time->toUserTimezone($row->insert_time, 'Y-m-d H:i:s', '[' . DomainMOD\Format::toLower(_('Unknown')) . ']'); ?></td><?php
                if (!$read_only) { ?>
                    <td>
                        <a class="btn btn-sm btn-outline-danger" href="index.php?del=1&id=<?php echo (int) $row->id; ?>" title="<?php echo _('Delete'); ?>"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden"><?php
                            // TRANSLATORS: Read out by screen readers for a Delete button in the SSL Queue. %s is a hostname, such as www.example.com.
                            echo sprintf(_('Delete %s'), htmlspecialchars($row->host, ENT_QUOTES, 'UTF-8')); ?></span></a>
                    </td><?php
                } ?>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

    }

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
