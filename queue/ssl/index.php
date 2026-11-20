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

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$ssl_queue = new DomainMOD\SslQueue();
$fee_status = new DomainMOD\FeeStatus();
$csrf = new DomainMOD\Csrf();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

/*
 * The SSL Queue's actions. Its rows are listed on the Import Queue page (queue/index.php), whose SSL Queue card links
 * here for each action, and every action returns there. Anything else (such as an old link to /queue/ssl/) goes
 * straight to that page.
 */
$system->authCheck();
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

    header("Location: ../");
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

    header("Location: ../");
    exit;

}

/*
 * A delete's second step, shown on the Import Queue page: the question, then Yes, Delete (which carries the CSRF token)
 * and a plain Cancel link back to the queue in the alert's own colour, side by side, wrapping on narrow screens. The
 * links are full paths, so they work wherever the message is shown.
 */
if ($ssl_queue_available && $del === 1 && $id !== 0 && !$read_only) {

    $_SESSION['s_message_danger'] .= _('Are you sure you want to delete this hostname from the queue?')
        . '<div class="d-flex flex-wrap align-items-center gap-3 mt-2">'
        . $layout->linkButton(WEB_ROOT . '/queue/ssl/index.php?really_del=1&id=' . $id . '&' . $csrf->query(), _('Yes, Delete'), 'primary')
        . '<a class="alert-link" href="' . htmlspecialchars(WEB_ROOT . '/queue/', ENT_QUOTES, 'UTF-8') . '">' . _('Cancel') . '</a></div>';

    header("Location: ../");
    exit;

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

    header("Location: ../");
    exit;

}

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

    foreach ($ssl_queue->statusRows() as $row) {

        list($state, $label, $flag, $detail) = $ssl_queue->statusParts($row);
        $domain_shown = DomainMOD\SslQueue::domainShown($row);

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
            $ssl_queue->exportText(DomainMOD\SslQueue::addedBy($row)),
            $time->toUserTimezone($row->insert_time)
        );
        $export->writeRow($export_file, $row_contents);

    }

    $export->closeFile($export_file);

}

// No action (or one that doesn't apply): the SSL Queue is listed on the Import Queue page
header("Location: ../", true, 302);
exit; //@formatter:on
