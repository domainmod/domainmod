<?php
/**
 * /watch/add.php
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/watch/add.php');
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/watch-add.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

$watch_available = DomainMOD\WatchList::isAvailable();
// Turned off in System Settings (see WatchList::isEnabled()), the page only says so, and nothing is read or changed
$watch_off = $watch_available && !DomainMOD\WatchList::isEnabled();
if ($watch_off) $watch_available = false;
$raw_domain_list = is_string($_POST['raw_domain_list'] ?? null) ? $_POST['raw_domain_list'] : '';
$new_reason = DomainMOD\WatchList::checkedReason($_POST['new_reason'] ?? DomainMOD\WatchList::DEFAULT_REASON);
$new_notes = is_string($_POST['new_notes'] ?? null) ? $sanitize->text($_POST['new_notes']) : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $watch_available) {

    $watch_list = new DomainMOD\WatchList($pdo, (int) $_SESSION['s_user_id']);
    $checked = $watch_list->checkPaste($raw_domain_list);

    if (DEMO_INSTALLATION == true) {

        $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';

    } elseif ($checked['error'] !== '') {

        $form->addFieldError('raw_domain_list', $checked['error']);

    } elseif ($checked['invalid_count'] > 0) {

        // The first few invalid lines, as the Import Queue's paste page lists them
        $_SESSION['s_message_danger'] .= sprintf(ngettext('There is %s invalid domain on your list.', 'There are %s invalid domains on your list.',
            $checked['invalid_count']), number_format($checked['invalid_count'])) . '<BR><BR>';
        foreach ($checked['invalid'] as $line) {
            $_SESSION['s_message_danger'] .= sprintf(_('Line %s contains an invalid domain'), number_format($line)) . '<BR>';
        }
        $others = $checked['invalid_count'] - count($checked['invalid']);
        if ($others > 0) $_SESSION['s_message_danger'] .= '<BR>' . sprintf(ngettext('Plus %s other', 'Plus %s others', $others), number_format($others)) . '<BR>';

    } else {

        try {

            $result = $watch_list->add(array_values($checked['names']), $new_reason, $new_notes, (int) $_SESSION['s_user_id']);
            $message = htmlspecialchars(DomainMOD\WatchList::resultMessage($result), ENT_QUOTES, 'UTF-8');

            if ($result['ok']) {
                $_SESSION['s_message_success'] .= $message . '<BR>';
                header('Location: ' . WEB_ROOT . '/watch/');
                exit;
            }

            if (!empty($result['live'])) {
                $form->addFieldError('raw_domain_list', DomainMOD\WatchList::resultMessage($result));
            } else {
                $_SESSION['s_message_danger'] .= $message . '<BR>';
            }

        } catch (DomainMOD\RestoreLockException $e) {

            $_SESSION['s_message_danger'] .= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '<BR>';

        } catch (Exception $e) {

            // Another request changed the same rows at the same moment: nothing was saved, and the form shows again with
            // what was typed (see domainmod_restore_is_conflict())
            if (domainmod_restore_is_conflict($e)) {
                $_SESSION['s_message_danger'] .= domainmod_restore_conflict_form_message() . '<BR>';
            } else {

                $log_message = 'Unable to add domains to the Watch List';
                $log_extra = array('Error' => $e);
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] .= _('Unable to add domains to the Watch List') . '<BR>';

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
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
if ($watch_off) {

    echo DomainMOD\WatchList::offState($layout, ($_SESSION['s_is_admin'] ?? 0) === 1);

} elseif (!$watch_available) {

    echo '<p>' . _('The Watch List becomes available after the next database update.') . '</p>';
    echo '<div class="domainmod-back-next">' . $layout->linkButton('../', _('Go Back'), 'secondary', 'bi-arrow-left') . '</div>';

} else {

    echo '<p class="domainmod-intro">' . sprintf(_("Paste the domains you want to watch: domains you'd like to buy, try to catch when they drop, or just keep an eye on. %s checks each one in the background with public RDAP data (or WHOIS where a TLD has no RDAP), and shows its expiry date, registrar, statuses, and where it is in the expiry and deletion cycle. The registries see which domains are being checked."), SOFTWARE_TITLE) . '</p>';

    if (DEMO_INSTALLATION == true) echo '<p class="text-body-secondary small">' . _('The Watch List can\'t be changed on the demo.') . '</p>';

    echo $form->showFormTop('');

    echo $form->showInputTextarea('raw_domain_list', sprintf(_('Domains to watch (one per line, up to %s)'), number_format(DomainMOD\WatchList::MAX_PASTE)), sprintf(_("Domains you already have in %s can't be watched, unless they're Expired or Sold."), SOFTWARE_TITLE), $raw_domain_list, '1', '', '', '', 'ltr');

    echo $form->showDropdownTop('new_reason', _('Reason'), '', '', '');
    foreach (DomainMOD\WatchList::reasonLabels() as $reason_code => $reason_name) {
        echo $form->showDropdownOption($reason_code, htmlspecialchars($reason_name, ENT_QUOTES, 'UTF-8'), $new_reason);
    }
    echo $form->showDropdownBottom('');

    echo $form->showInputTextarea('new_notes', _('Notes'), _('Added to every domain on the list.'), $unsanitize->text($new_notes), '', '', '');

    echo '<div class="domainmod-back-next">' . $layout->linkButton('./', _('Go Back'), 'secondary', 'bi-arrow-left');
    echo $form->showSubmitButton(_('Add Domains'), '', '');
    echo '</div>';

    echo $form->showFormBottom('');

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
