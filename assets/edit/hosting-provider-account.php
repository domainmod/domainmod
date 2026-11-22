<?php
/**
 * /assets/edit/hosting-provider-account.php
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
$log = new DomainMOD\Log('/assets/edit/hosting-provider-account.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-hosting-account.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Before the database update there are no hosting tables to edit
if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';
    header("Location: ../index.php");
    exit;

}

$del = (int) ($_GET['del'] ?? 0);

$hpaid = (int) ($_GET['hpaid'] ?? 0);
$new_owner_id = (int) ($_POST['new_owner_id'] ?? 0);
$new_hosting_id = (int) ($_POST['new_hosting_id'] ?? 0);
$new_email_address = isset($_POST['new_email_address']) ? $sanitize->text($_POST['new_email_address']) : '';
$new_username = isset($_POST['new_username']) ? $sanitize->text($_POST['new_username']) : '';
$new_reseller = (int) ($_POST['new_reseller'] ?? 0);
$new_reseller_id = isset($_POST['new_reseller_id']) ? $sanitize->text($_POST['new_reseller_id']) : '';
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';
$new_hpaid = (int) ($_POST['new_hpaid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
    $hpaid = $new_hpaid;

    if ($validate->text($new_username) && $new_owner_id !== 0 && $new_hosting_id !== 0) {

        // The save holds the hosting lock (see DomainMOD\Hosting::lock()), so the owner and provider it checks, and the
        // hosting services it moves, can't change before it commits
        if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

            $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';

        } else {

            // '' while the form is shown again, 'saved' once it's saved, or 'gone' when the account was deleted meanwhile
            $outcome = '';
            $moved = false;

            try {

                $stmt = $pdo->prepare("
                    SELECT (SELECT COUNT(*) FROM owners WHERE id = :new_owner_id) AS owner_found,
                           (SELECT COUNT(*) FROM hosting WHERE id = :new_hosting_id) AS provider_found");
                $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                $stmt->execute();
                $found = $stmt->fetch();
                $stmt->closeCursor();

                if ((int) $found->owner_found === 0) $form->addFieldError('new_owner_id', _('Choose the Owner'));
                if ((int) $found->provider_found === 0) $form->addFieldError('new_hosting_id', _('Choose the Hosting Provider'));

                // The account as it's saved now (a plain read is enough, since the hosting lock keeps it from changing),
                // which decides whether this is a move, never the form's own idea of the old values
                $stmt = $pdo->prepare("
                    SELECT owner_id, hosting_id
                    FROM hosting_provider_accounts
                    WHERE id = :record_id");
                $stmt->bindValue('record_id', $new_hpaid, PDO::PARAM_INT);
                $stmt->execute();
                $saved = $stmt->fetch();
                $stmt->closeCursor();

                if (!$saved) {

                    $outcome = 'gone';

                } elseif ((int) $found->owner_found > 0 && (int) $found->provider_found > 0) {

                    $moved = (int) $saved->owner_id !== $new_owner_id || (int) $saved->hosting_id !== $new_hosting_id;

                    // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                    $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                    $pdo->beginTransaction();

                    // One asset action, started before any row is locked, so its wait for the custom field definitions
                    // (see DomainMOD\ChangeHistory::start()) never holds a row
                    $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET, $_SESSION['s_user_id'], 'hosting_account_edit');

                    $timestamp = $time->stamp();

                    if ($moved) {

                        /*
                         * The account's hosting services follow it to its new owner and provider, a keyset page of 1,000 at a
                         * time: locked (through the account_id key, before the account's own row, keeping the lock
                         * order of items before assets), captured, moved, and compared, so PHP holds one page at once. Each
                         * one's fee is looked up again by its new provider and its own service type and billing term, so its fee,
                         * cost, and fee_fixed are set together: from the matching fee, or all three 0 when there's none (which
                         * the missing fees warning then shows).
                         */
                        $history_last = 0;
                        do {
                            $stmt = $pdo->prepare("
                                SELECT id
                                FROM hosting_services
                                WHERE account_id = :account_id
                                  AND id > :last_id
                                ORDER BY id
                                LIMIT 1000
                                FOR UPDATE");
                            $stmt->bindValue('account_id', $new_hpaid, PDO::PARAM_INT);
                            $stmt->bindValue('last_id', $history_last, PDO::PARAM_INT);
                            $stmt->execute();
                            $history_chunk = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                            $stmt->closeCursor();
                            if (!$history_chunk) break;
                            $history_last = end($history_chunk);

                            $history_before = $history_op->capture('hosting', $history_chunk, array('account_id', 'owner_id', 'hosting_id', 'total_cost'), false);
                            $stmt = $pdo->prepare("
                                UPDATE hosting_services AS hs
                                LEFT JOIN hosting_fees AS hf
                                    ON hf.hosting_id = :fee_hosting_id
                                   AND hf.type_id = hs.type_id
                                   AND hf.billing_term = hs.billing_term
                                SET hs.owner_id = :new_owner_id,
                                    hs.hosting_id = :new_hosting_id,
                                    hs.fee_id = COALESCE(hf.id, 0),
                                    hs.total_cost = COALESCE(hf.renewal_fee + hf.misc_fee, 0),
                                    hs.fee_fixed = IF(hf.id IS NULL, 0, 1),
                                    hs.revision = hs.revision + 1,
                                    hs.update_time = :timestamp
                                WHERE hs.account_id = :new_hpaid
                                  AND hs.id IN (" . implode(',', $history_chunk) . ")");
                            $stmt->bindValue('fee_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                            $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                            $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindValue('new_hpaid', $new_hpaid, PDO::PARAM_INT);
                            $stmt->execute();
                            $history_op->compare($history_before);
                        } while (count($history_chunk) === 1000);

                    }

                    // Then the account's own row, locked after its hosting services
                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM hosting_provider_accounts
                        WHERE id = :record_id
                        FOR UPDATE");
                    $stmt->bindValue('record_id', $new_hpaid, PDO::PARAM_INT);
                    $stmt->execute();
                    $stmt->closeCursor();

                    $stmt = $pdo->prepare("
                        UPDATE hosting_provider_accounts
                        SET owner_id = :new_owner_id,
                            hosting_id = :new_hosting_id,
                            email_address = :new_email_address,
                            username = :new_username,
                            reseller = :new_reseller,
                            reseller_id = :new_reseller_id,
                            notes = :new_notes,
                            update_time = :timestamp
                        WHERE id = :new_hpaid");
                    $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_email_address', $new_email_address, PDO::PARAM_STR);
                    $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
                    $stmt->bindValue('new_reseller', $new_reseller, PDO::PARAM_INT);
                    $stmt->bindValue('new_reseller_id', $new_reseller_id, PDO::PARAM_STR);
                    $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                    $stmt->bindValue('new_hpaid', $new_hpaid, PDO::PARAM_INT);
                    $stmt->execute();

                    $history_op->finish();

                    if ($pdo->InTransaction()) $pdo->commit();

                    $outcome = 'saved';

                }

            } catch (Exception $e) {

                if ($pdo->InTransaction()) $pdo->rollback();

                $log_message = 'Unable to update hosting account';
                $log_extra = array('Error' => $e);
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                throw $e;

            } finally {

                // The action goes (and lets go of anything it holds) before the hosting lock does
                $history_op = null;
                DomainMOD\Hosting::unlock($pdo);

            }

            if ($outcome === 'gone') {

                $_SESSION['s_message_danger'] .= _('That hosting account no longer exists.') . '<BR>';
                header("Location: ../hosting-accounts.php");
                exit;

            }

            if ($outcome === 'saved') {

                // A move can leave hosting services without a fee at their new provider, or give one to a service that
                // had none
                if ($moved) {

                    $queryB = new DomainMOD\QueryBuild();

                    $sql = $queryB->missingFees('hosting_services');
                    $_SESSION['s_missing_hosting_fees'] = $system->checkForRows($sql);

                }

                $_SESSION['s_message_success'] .= sprintf(_('Hosting Account %s (%s, %s) updated'), $new_username,
                    $assets->getHost($new_hosting_id), $assets->getOwner($new_owner_id)) . '<BR>';

                header("Location: ../hosting-accounts.php");
                exit;

            }

        }

    } else {

        if ($new_owner_id === 0) {

            $form->addFieldError('new_owner_id', _('Choose the Owner'));

        }

        if ($new_hosting_id === 0) {

            $form->addFieldError('new_hosting_id', _('Choose the Hosting Provider'));

        }

        if (!$validate->text($new_username)) {
            $form->addFieldError('new_username', _('Enter a username'));
        }

    }

} else {

    $stmt = $pdo->prepare("
        SELECT owner_id, hosting_id, email_address, username, reseller, reseller_id, notes
        FROM hosting_provider_accounts
        WHERE id = :hpaid");
    $stmt->bindValue('hpaid', $hpaid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_owner_id = $result->owner_id;
        $new_hosting_id = $result->hosting_id;
        $new_email_address = $result->email_address;
        $new_username = $result->username;
        $new_reseller = $result->reseller;
        $new_reseller_id = $result->reseller_id;
        $new_notes = $result->notes;

    }

}

if ($del === 1) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    // The check and the delete hold the hosting lock, so a hosting service can't be given this account between
    // the two
    if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

        $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';
        header("Location: hosting-provider-account.php?hpaid=" . $hpaid);
        exit;

    }

    // '' when it's refused, 'deleted' once it's deleted, or 'gone' when it was already deleted
    $outcome = '';

    try {

        $stmt = $pdo->prepare("
            SELECT account_id
            FROM hosting_services
            WHERE account_id = :hpaid
            LIMIT 1");
        $stmt->bindValue('hpaid', $hpaid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($result) {

            $_SESSION['s_message_danger'] .= _('This Hosting Account has hosting services associated with it and cannot be deleted.') . '<BR>';

        } else {

            $stmt = $pdo->prepare("
                SELECT a.username AS username, o.name AS owner_name, h.name AS hosting_name
                FROM hosting_provider_accounts AS a, owners AS o, hosting AS h
                WHERE a.owner_id = o.id
                  AND a.hosting_id = h.id
                  AND a.id = :hpaid");
            $stmt->bindValue('hpaid', $hpaid, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if (!$result) {

                // already deleted, or the link was changed
                $outcome = 'gone';

            } else {

                $temp_username = $result->username;
                $temp_owner_name = $result->owner_name;
                $temp_hosting_name = $result->hosting_name;

                $stmt = $pdo->prepare("
                    DELETE FROM hosting_provider_accounts
                    WHERE id = :hpaid");
                $stmt->bindValue('hpaid', $hpaid, PDO::PARAM_INT);
                $stmt->execute();

                $outcome = 'deleted';

            }

        }

    } finally {

        DomainMOD\Hosting::unlock($pdo);

    }

    if ($outcome === 'gone') {

        $_SESSION['s_message_danger'] .= _('That hosting account no longer exists.') . '<BR>';
        header("Location: ../hosting-accounts.php");
        exit;

    }

    if ($outcome === 'deleted') {

        $_SESSION['s_message_success'] .= sprintf(_('Hosting Account %s (%s, %s) deleted'), $temp_username, $temp_hosting_name, $temp_owner_name) . '<BR>';

        $system->checkExistingAssets();

        header("Location: ../hosting-accounts.php");
        exit;

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
echo $form->showFormTop('');
// The fields in titled cards, the same way as the edit domain page (Layout::formSection()): the account, then the notes
ob_start();
$result = $pdo->query("
    SELECT id, `name`
    FROM hosting
    ORDER BY `name` ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_hosting_id', _('Hosting Provider'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_hosting_id);

    }

    echo $form->showDropdownBottom('', 'new_hosting_id');

}

$result = $pdo->query("
    SELECT id, `name`
    FROM owners
    ORDER BY `name` ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_owner_id', _('Account Owner'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_owner_id);

    }

    echo $form->showDropdownBottom('', 'new_owner_id');

}

echo $form->showInputText('new_email_address', _('Email Address') . ' (100)', '', $unsanitize->text($new_email_address), '100', '', '', '', '');
echo $form->showInputText('new_username', _('Username') . ' (100)', '', $unsanitize->text($new_username), '100', '', '1', '', '');
// The switch is one grid item, with the same spacing as the other fields
echo $form->showSwitch(_('Reseller Account') . '?', '', 'new_reseller', $new_reseller, '<div class="mb-3">', '</div>');
echo $form->showInputText('new_reseller_id', _('Reseller ID') . ' (100)', '', $unsanitize->text($new_reseller_id), '100', '', '', '', '');
echo $layout->formSection(_('Account'), 'account', ob_get_clean());

ob_start();
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '<div class="domainmod-span-all">', '</div>');
echo $layout->formSection(_('Notes'), 'notes', ob_get_clean());

echo $form->showInputHidden('new_hpaid', $hpaid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->dangerZone(_('Hosting Account'), $new_username, 'hosting-provider-account.php?hpaid=' . $hpaid . '&del=1', _('Delete this hosting account'), sprintf(_('This removes %s. It can only be deleted when no hosting services use it. It can\'t be undone.'), '<strong>' . htmlentities(html_entity_decode($new_username, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>'), 2);
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
