<?php
/**
 * /watch/edit.php
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/watch/edit.php');
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/watch-edit.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

/*
 * One watched domain: its details (saved with the form), what the checks found, its Change History, and its actions
 * (Check Now, Archive or Unarchive, Delete, and the ways to move it into Domains). Read-only users can open it, but every
 * POST checks readOnlyCheck() first, and opening the page never changes anything. Every action is a POST with the
 * session's security token (checked for every signed-in POST by debug.inc.php), never a link.
 */
$wid = (int) ($_GET['wid'] ?? 0);
$watch_available = DomainMOD\WatchList::isAvailable();
// Turned off in System Settings (see WatchList::isEnabled()), the page only says so, and nothing is read or changed
$watch_off = $watch_available && !DomainMOD\WatchList::isEnabled();
if ($watch_off) $watch_available = false;
$watch_list = new DomainMOD\WatchList($pdo, (int) $_SESSION['s_user_id']);
$watch_check = new DomainMOD\WatchCheck($pdo);
$entry = $watch_available && $wid > 0 ? $watch_list->entry($wid) : null;
$read_only = ($_SESSION['s_read_only'] ?? '') == '1';
$edit_url = WEB_ROOT . '/watch/edit.php?wid=' . $wid;
$esc = function ($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
};

// The Current State section, drawn on the page and again after Check Now (an out-of-band swap, $oob)
$state_section = function ($row, $oob = false) use ($layout, $unsanitize, $esc, $read_only, $pdo) {
    $columns = new DomainMOD\WatchListColumns(DomainMOD\WatchListColumns::sessionCopy($_SESSION));
    $known = (int) $row->has_baseline === 1;
    $date = function ($value) {
        return DomainMOD\Time::isUnset($value) ? '' : (string) $value;
    };
    $sources = array('rdap' => 'RDAP', 'whois' => 'WHOIS');

    $body = '<div class="domainmod-span-all mb-2">' . $columns->stageBadge($row) . '</div>';
    $pairs = array();
    if ($known) {
        $pairs[] = array('label' => _('Stage Since'), 'value' => $columns->when($row->stage_since), 'ltr' => true);
        $pairs[] = array('label' => _('Expiry Date'), 'value' => $date($row->expiry_date) !== '' ? $row->expiry_date
            : ($row->stage === 'not_registered' ? '' : _('Expiry date not published.')));
        $pairs[] = array('label' => _('Registration Date'), 'value' => $date($row->registration_date));
        $pairs[] = array('label' => _('Deletion Date'), 'value' => $date($row->deletion_date));
        $registrar_url = (string) $unsanitize->text($row->registrar_url);
        $pairs[] = array('label' => _('Registrar'), 'value' => $columns->plain($row->registrar_name)
            . ((string) $row->registrar_iana_id !== '' ? ' ' . sprintf(_('(IANA ID %s)'), $row->registrar_iana_id) : ''),
            'url' => stripos($registrar_url, 'https://') === 0 ? $registrar_url : '', 'link_label' => _('Website'));
    }
    $pairs[] = array('label' => _('Last Checked'), 'value' => $columns->when($row->last_attempt)
        . ((string) $row->last_source !== '' && isset($sources[$row->last_source]) ? ' (' . $sources[$row->last_source] . ')' : ''),
        'ltr' => true);
    if (DomainMOD\WatchListColumns::lastAttemptUnanswered($row)) {
        $pairs[] = array('label' => _('Details Updated'), 'value' => $columns->when($row->last_success), 'ltr' => true);
    }
    if ((int) $row->archived === 0) $pairs[] = array('label' => _('Next Check'), 'value' => $columns->when(DomainMOD\WatchListColumns::nextCheck($row)), 'ltr' => true);
    $body .= '<div class="domainmod-span-all">' . $layout->factList($pairs) . '</div>';
    // A result waiting for its confirming lookup, and when that's due (a name live in Domains is archived instead, so
    // it gets no note)
    if (DomainMOD\WatchList::liveDomainId($pdo, $row->domain) === 0) {
        $confirm_note = $columns->confirmNoteHtml($row, time(), !$read_only && DEMO_INSTALLATION == false);
        if ($confirm_note !== '') $body .= '<p class="domainmod-span-all domainmod-facts-note text-body-secondary small mb-2">' . $confirm_note . '</p>';
    }

    // The registry statuses, each with its meaning
    if ($known) {
        $codes = DomainMOD\DomainStatus::fromColumn($row->statuses);
        if ($codes) {
            $body .= '<div class="domainmod-span-all mb-2"><strong>' . $esc(_('Registry Status')) . '</strong><ul class="mb-0">';
            foreach ($codes as $code) {
                $meaning = DomainMOD\DomainStatus::meaning($code);
                $body .= '<li><code>' . $esc($code) . '</code>' . ($meaning !== '' ? ': ' . $esc($meaning) : '') . '</li>';
            }
            $body .= '</ul></div>';
        }
    }

    // The estimate, or why there isn't one
    $notes = array();
    $drop = $columns->dropHtml($row);
    if ($drop !== '') {
        $body .= '<div class="domainmod-span-all mb-2"><strong>' . $esc(_('Estimated Drop')) . '</strong> ' . $drop
            . ' (' . $esc(DomainMOD\WatchLifecycle::basisLabel($row->drop_basis)) . ')</div>';
        $notes[] = _("This estimate uses the domain's statuses and the TLD's usual rules. The owner can usually renew during the grace period and restore during redemption, so the domain may never drop. When deletion is pending, it usually can't be restored. Popular names are often caught by paid backorder services the moment they drop, or never become available to register at all.");
    } elseif ($known && in_array($row->stage, array('grace', 'redemption', 'pending_delete'), true)
        && DomainMOD\WatchLifecycle::profileFor($row->domain) === null) {
        $notes[] = sprintf(_("%1\$s doesn't know .%2\$s's deletion rules, so no drop date is estimated. The stage and statuses are still checked."),
            SOFTWARE_TITLE, DomainMOD\WatchLifecycle::registryTld($row->domain));
    }
    if ($known && $row->stage === 'not_registered') {
        $notes[] = _('The registry returned no record for this domain. It may be available, or it may be reserved, blocked, or offered at a premium price. Check with a registrar.');
    }
    if (DomainMOD\WatchListColumns::lastAttemptUnanswered($row)) {
        $notes[] = sprintf(_("The last check didn't update these details, so they're from the check at %s."), $columns->when($row->last_success));
    }
    // (a confirmed "not registered" has no statuses, so an earlier incomplete read no longer matters)
    if ((string) $row->last_status_read === 'incomplete' && $row->stage !== 'not_registered') {
        $notes[] = _("The registry statuses couldn't be read in full at a recent check, so the stage may be out of date.");
    }
    foreach ($notes as $note) $body .= '<p class="domainmod-span-all text-body-secondary small mb-2">' . $esc($note) . '</p>';

    return '<div id="domainmod-watch-state"' . ($oob ? ' hx-swap-oob="true"' : '') . '>'
        . $layout->formSection(_('Current State'), 'state', $body) . '</div>';
};

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $entry) {

    $system->readOnlyCheck($edit_url);
    $action = is_string($_POST['watch_action'] ?? null) ? $_POST['watch_action'] : '';

    if (DEMO_INSTALLATION == true) {

        $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';
        if (!$system->isHtmxRequest()) {
            header('Location: ' . $edit_url);
            exit;
        }
        header('X-DomainMOD-Result: 1');
        echo '<div class="alert alert-danger py-2 mb-0" role="alert">' . $esc(_('You are not authorized to perform that action.')) . '</div>';
        exit;

    }

    // A Save that's drawn again keeps what the user typed: only the fields they changed come from the draft, so another
    // user's change shows in the rest. A "was" value that isn't a string (a crafted form) counts as empty, so that
    // field keeps what was typed
    $draft_from_post = function ($new_reason, $new_notes) {
        $draft = array();
        $reason_was = is_string($_POST['reason_was'] ?? null) ? $_POST['reason_was'] : '';
        $notes_was = is_string($_POST['notes_was'] ?? null) ? $_POST['notes_was'] : '';
        if ($new_reason !== $reason_was) $draft['reason'] = $new_reason;
        if (!hash_equals(hash('sha256', $new_notes), $notes_was)) $draft['notes'] = $new_notes;
        return $draft;
    };

    try {

        if ($action === 'check') {

            list($type, $message) = $watch_check->checkNow($wid, (int) $_SESSION['s_user_id']);
            if ($system->isHtmxRequest()) {
                // The result in place, with the Current State and Change History sections redrawn (out-of-band swaps)
                $entry = $watch_list->entry($wid);
                // An entry the check archived (its name is in Domains now) keeps the page as it is, so nothing typed into
                // it is lost, and the message says to reload it
                if ($entry && (int) $entry->archived === 1) $message .= ' ' . _('Reload the page to see its actions.');
                // A result, so the footer's htmx handler swaps it in (see System::htmxTaskResult())
                header('X-DomainMOD-Result: 1');
                echo '<div class="alert alert-' . $esc($type) . ' py-2 mb-0" role="status">' . $esc($message) . '</div>';
                if ($entry) echo $state_section($entry, true);
                echo DomainMOD\ChangeHistoryListColumns::editSection($pdo, 'watch', $wid, true);
                exit;
            }
            $_SESSION['s_message_' . $type] .= $esc($message) . '<BR>';

        } elseif ($action === 'save') {

            $new_reason = DomainMOD\WatchList::checkedReason($_POST['new_reason'] ?? '');
            $new_notes = is_string($_POST['new_notes'] ?? null) ? $sanitize->text($_POST['new_notes']) : '';
            $saved = $watch_list->saveDetails($wid, $new_reason, $new_notes, (int) $_SESSION['s_user_id'],
                is_string($_POST['loaded'] ?? null) ? $_POST['loaded'] : '');
            if ($saved === 'saved') {
                $_SESSION['s_message_success'] .= _('Watched domain saved.') . '<BR>';
            } elseif ($saved === 'changed') {
                // The page is drawn again with what the user typed (and the details as they are now behind it), so nothing
                // typed is lost, and a second Save goes through once they've looked
                $_SESSION['s_message_danger'] .= _("This watched domain's reason or notes changed after you opened the page, so your changes weren't saved yet. Check them and save again.") . '<BR>';
                $draft = $draft_from_post($new_reason, $new_notes);
            }

        } elseif ($action === 'archive') {

            $watch_list->archive(array($wid), (int) $_SESSION['s_user_id']);
            $_SESSION['s_message_success'] .= _('Watched domain archived. It isn\'t checked while it\'s archived.') . '<BR>';

        } elseif ($action === 'unarchive') {

            $result = $watch_list->unarchive(array($wid), (int) $_SESSION['s_user_id']);
            $_SESSION['s_message_' . ($result['ok'] ? 'success' : 'danger')] .= $esc(DomainMOD\WatchList::resultMessage($result)) . '<BR>';

        } elseif ($action === 'delete') {

            $watch_list->delete(array($wid), (int) $_SESSION['s_user_id']);
            $_SESSION['s_message_success'] .= _('Watched domain deleted.') . '<BR>';
            header('Location: ' . WEB_ROOT . '/watch/');
            exit;

        }

    } catch (DomainMOD\RestoreLockException $e) {

        $_SESSION['s_message_danger'] .= $esc($e->getMessage()) . '<BR>';

    } catch (Throwable $e) {

        if (domainmod_restore_is_conflict($e)) {
            // Another request changed the same rows at the same moment, so nothing was saved (see
            // domainmod_restore_is_conflict()). Check Now shows it in place, as its other results, and a Save is drawn
            // again with what the user typed, as a refused one is.
            if ($action === 'check' && $system->isHtmxRequest()) {
                header('X-DomainMOD-Result: 1');
                echo '<div class="alert alert-danger py-2 mb-0" role="alert">' . $esc(domainmod_restore_conflict_form_message()) . '</div>';
                exit;
            }
            $_SESSION['s_message_danger'] .= domainmod_restore_conflict_form_message() . '<BR>';
            if ($action === 'save' && isset($new_reason, $new_notes)) $draft = $draft_from_post($new_reason, $new_notes);
        } else {
            $log->critical('Unable to change a watched domain', array('Error' => $e->getMessage()));
            $_SESSION['s_message_danger'] .= _("That didn't work. Please try again.") . '<BR>';
        }

    }

    if (!isset($draft)) {
        header('Location: ' . $edit_url);
        exit;
    }
    $entry = $watch_list->entry($wid);

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
if ($watch_off) {

    echo DomainMOD\WatchList::offState($layout, ($_SESSION['s_is_admin'] ?? 0) === 1);

} elseif (!$watch_available) {

    echo '<p>' . _('The Watch List becomes available after the next database update.') . '</p>';

} elseif (!$entry) {

    echo '<p>' . _('That watched domain no longer exists.') . '</p>';
    echo '<div class="domainmod-back-next">' . $layout->linkButton('./', _('Go Back'), 'secondary', 'bi-arrow-left') . '</div>';

} else {

    $domain_name = (string) $unsanitize->text($entry->domain);
    $can_change = !$read_only && DEMO_INSTALLATION == false;
    $archived = (int) $entry->archived === 1;
    echo '<h2 class="h4 mb-3">' . $layout->ltr($domain_name) . ($archived ? ' <span class="badge text-bg-light border">' . $esc(_('Archived')) . '</span>' : '') . '</h2>';

    if (DEMO_INSTALLATION == true) echo '<p class="text-body-secondary small">' . _('The Watch List can\'t be changed on the demo.') . '</p>';

    // Check Now, in place with htmx (see js/watch-check.js), and a normal post without JavaScript
    if ($can_change && !$archived) { ?>
        <div class="domainmod-refresh-row d-flex flex-wrap align-items-center justify-content-end gap-2">
            <span class="text-body-secondary small"><?php echo _('Looks this domain up now with public RDAP or WHOIS data.'); ?></span>
            <form method="post" class="d-inline-flex align-items-center" hx-post="<?php echo $esc($edit_url); ?>" hx-target="#domainmod-watch-check-result" hx-swap="innerHTML" hx-indicator="#domainmod-watch-check-spinner" hx-disabled-elt="find button" hx-sync="this:drop" data-domainmod-watch-check>
                <?php echo $form->showCsrfField(); ?>
                <?php echo $form->showInputHidden('watch_action', 'check'); ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> <?php echo _('Check Now'); ?></button>
                <?php echo $layout->htmxSpinner('domainmod-watch-check-spinner'); ?>
            </form>
        </div>
        <div id="domainmod-watch-check-result" class="domainmod-refresh-result" aria-live="polite"></div><?php
    }

    // A name that's live in Domains is archived by the next run, and until then the page says so
    $domain_row = DomainMOD\WatchList::domainRow($pdo, $entry->domain);
    if ($domain_row !== null && !in_array($domain_row[1], array('0', '10'), true)) { ?>
        <p class="alert alert-info py-2"><?php echo $esc(sprintf(_('This domain is in %s now.'), SOFTWARE_TITLE)); ?>
            <a href="<?php echo $esc(WEB_ROOT . '/domains/edit.php?did=' . $domain_row[0]); ?>"><?php echo _('View the domain'); ?></a></p><?php
    }

    // Details, saved with the form
    echo $form->showFormTop('');
    echo $form->showInputHidden('watch_action', 'save');
    // What the form was drawn with, so a save never overwrites a change made since (see WatchList::saveDetails())
    echo $form->showInputHidden('loaded', DomainMOD\WatchList::detailsHash($entry->reason, $entry->notes));
    // The reason and notes as they're saved now, so a refused save can tell which ones the user changed (a draft kept
    // from an earlier refusal still differs from them, so it's kept again)
    echo $form->showInputHidden('reason_was', $entry->reason);
    // (the notes as the form sends them back: a browser drops one line break at the start of a textarea's text)
    echo $form->showInputHidden('notes_was', hash('sha256', preg_replace('/^\r?\n/', '', (string) $entry->notes)));
    ob_start();
    echo $form->showDropdownTop('new_reason', _('Reason'), '', '', '');
    foreach (DomainMOD\WatchList::reasonLabels() as $reason_code => $reason_name) {
        echo $form->showDropdownOption($reason_code, $esc($reason_name), $draft['reason'] ?? $entry->reason);
    }
    echo $form->showDropdownBottom('');
    $detail_columns = new DomainMOD\WatchListColumns(DomainMOD\WatchListColumns::sessionCopy($_SESSION));
    echo $layout->factList(array(array('label' => _('Added By'), 'value' => $detail_columns->addedBy($entry)),
        array('label' => _('Added'), 'value' => $detail_columns->when($entry->insert_time))));
    // After a refused save, the notes as they're saved now, beside the user's own
    if (isset($draft['notes']) && (string) $draft['notes'] !== (string) $entry->notes) {
        echo '<div class="domainmod-span-all mb-2"><strong>' . $esc(_('The notes as they are saved now')) . '</strong>'
            . '<div class="domainmod-history-text border rounded p-2" tabindex="0">' . $esc($unsanitize->text($entry->notes)) . '</div></div>';
    }
    echo '<div class="domainmod-span-all">' . $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($draft['notes'] ?? $entry->notes), '', '', '') . '</div>';
    if ($can_change) echo '<div class="domainmod-span-all mb-2">' . $form->showSubmitButton(_('Save'), '', '') . '</div>';
    echo $layout->formSection(_('Details'), 'details', ob_get_clean());
    echo $form->showFormBottom('');

    // The other sections, at the same width as the Details form
    echo '<div class="domainmod-form">';
    echo $state_section($entry);

    echo DomainMOD\ChangeHistoryListColumns::editSection($pdo, 'watch', $wid);

    // Links out (no lookups): the domain's public RDAP record
    $rdap_url = DomainMOD\WatchList::rdapRecordUrl($entry->domain);
    if ($rdap_url !== '') {
        echo '<p class="small"><a href="' . $esc($rdap_url) . '" rel="noopener noreferrer" target="_blank">' . _('View the public RDAP record') . '</a></p>';
    }

    // I own this now: the existing add pages with the name filled in, or the domain already in Domains
    if ($can_change) {
        ob_start();
        if ($domain_row === null) { ?>
            <p class="domainmod-span-all mb-2"><?php echo _('Bought it? Add it to your domains with the details filled in.'); ?></p>
            <div class="domainmod-span-all d-flex flex-wrap gap-2 mb-2">
                <a class="btn btn-sm btn-danger" href="<?php echo $esc(WEB_ROOT . '/queue/add/rdap.php?watch=' . $wid); ?>"><?php echo _('Import with RDAP (recommended)'); ?></a>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $esc(WEB_ROOT . '/domains/add.php?watch=' . $wid); ?>"><?php echo _('Add Manually'); ?></a>
            </div>
            <p class="domainmod-span-all text-body-secondary small mb-2"><?php echo _("Watching stops once the domain is in your domains and isn't Expired or Sold."); ?></p><?php
        } elseif (in_array($domain_row[1], array('0', '10'), true)) { ?>
            <p class="domainmod-span-all mb-2"><?php echo $esc(sprintf($domain_row[1] === '10'
                ? _('This domain is already in %s as Sold. Set Domain Status to Active and save. Use Refresh to update the domain details.')
                : _('This domain is already in %s as Expired. Set Domain Status to Active and save. Use Refresh to update the domain details.'), SOFTWARE_TITLE)); ?></p>
            <div class="domainmod-span-all"><a class="btn btn-sm btn-danger" href="<?php echo $esc(WEB_ROOT . '/domains/edit.php?did=' . $domain_row[0]); ?>"><?php echo _('Edit the domain'); ?></a></div><?php
        }
        $own_body = ob_get_clean();
        if (trim($own_body) !== '') echo $layout->formSection(_('I Own This Now'), 'own', $own_body);

        // Archive or Unarchive, and Delete (with a confirmation), each a POST form
        ob_start(); ?>
            <div class="domainmod-span-all d-flex flex-wrap gap-2">
                <form method="post" class="d-inline">
                    <?php echo $form->showCsrfField();
                    echo $form->showInputHidden('watch_action', $archived ? 'unarchive' : 'archive'); ?>
                    <button type="submit" class="btn btn-outline-secondary"><?php echo $archived ? _('Unarchive') : _('Archive'); ?></button>
                </form>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#watch-delete-confirm"><?php echo _('Delete'); ?></button>
            </div>
            <p class="domainmod-span-all text-body-secondary small mt-2 mb-2"><?php echo $archived
                ? _('Unarchiving starts watching again from scratch, with a check on the next scheduled run.')
                : _("Archiving stops the checks and keeps the entry and its history. Archived domains don't count toward the Watch List's limit."); ?></p><?php
        echo $layout->formSection(_('Actions'), 'actions', ob_get_clean()); ?>

        <div class="modal fade" id="watch-delete-confirm" tabindex="-1" aria-labelledby="watch-delete-confirm-title" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="watch-delete-confirm-title"><?php echo _('Delete Watched Domain'); ?></h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo _('Close'); ?>"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0"><?php echo sprintf(_('Are you sure you want to delete %s'), '<strong>' . $layout->ltr($domain_name) . '</strong>') . '?'; ?>
                            <?php echo _('Its earlier changes stay in the Change History.'); ?></p>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo DomainMOD\Format::toUpper(_('Cancel')); ?></button>
                        <form method="post" class="d-inline">
                            <?php echo $form->showCsrfField();
                            echo $form->showInputHidden('watch_action', 'delete'); ?>
                            <button type="submit" class="btn btn-danger"><?php echo DomainMOD\Format::toUpper(_('Yes, Delete')); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div><?php
    }

    echo '</div>';

    echo '<div class="domainmod-back-next mt-3">' . $layout->linkButton('./', _('Go Back'), 'secondary', 'bi-arrow-left') . '</div>';

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<script src="<?php echo $layout->assetUrl('js/watch-check.js'); ?>"></script>
</body>
</html>
