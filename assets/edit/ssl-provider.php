<?php
/**
 * /assets/edit/ssl-provider.php
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
$log = new DomainMOD\Log('/assets/edit/ssl-provider.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-ssl-provider.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$sslpid = (int) ($_GET['sslpid'] ?? 0);
$new_ssl_provider = isset($_POST['new_ssl_provider']) ? $sanitize->text($_POST['new_ssl_provider']) : '';
$new_url = isset($_POST['new_url']) ? $sanitize->text($_POST['new_url']) : '';
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';
$new_sslpid = (int) ($_POST['new_sslpid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    if ($validate->text($new_ssl_provider)) {

        $stmt = $pdo->prepare("
            UPDATE ssl_providers
            SET `name` = :new_ssl_provider,
                url = :new_url,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :new_sslpid");
        $stmt->bindValue('new_ssl_provider', $new_ssl_provider, PDO::PARAM_STR);
        $stmt->bindValue('new_url', $new_url, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('new_sslpid', $new_sslpid, PDO::PARAM_INT);
        $stmt->execute();

        $sslpid = $new_sslpid;

        $_SESSION['s_message_success'] .= sprintf(_('SSL Provider %s updated'), $new_ssl_provider) . '<BR>';

        header("Location: ../ssl-providers.php");
        exit;

    } else {

        if (!$validate->text($new_ssl_provider)) $form->addFieldError('new_ssl_provider', _("Enter the SSL provider's name"));

    }

} else {

    $stmt = $pdo->prepare("
        SELECT `name`, url, notes
        FROM ssl_providers
        WHERE id = :sslpid");
    $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_ssl_provider = $result->name;
        $new_url = $result->url;
        $new_notes = $result->notes;

    }

}

if ($del === 1) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    $existing_ssl_provider_accounts = 0;
    $existing_ssl_certs = 0;

    $stmt = $pdo->prepare("
        SELECT ssl_provider_id
        FROM ssl_accounts
        WHERE ssl_provider_id = :sslpid
        LIMIT 1");
    $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $existing_ssl_provider_accounts = 1;

    }

    $stmt = $pdo->prepare("
        SELECT ssl_provider_id
        FROM ssl_certs
        WHERE ssl_provider_id = :sslpid
        LIMIT 1");
    $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $existing_ssl_certs = 1;

    }

    if ($existing_ssl_provider_accounts > 0 || $existing_ssl_certs > 0) {

        if ($existing_ssl_provider_accounts > 0) $_SESSION['s_message_danger'] .= _('This SSL Provider has Accounts associated with it and cannot be deleted.') . '<BR>';
        if ($existing_ssl_certs > 0) $_SESSION['s_message_danger'] .= _('This SSL Provider has SSL Certificates associated with it and cannot be deleted.') . '<BR>';

    } else {

        try {

            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $pdo->beginTransaction();

            /*
             * The checks above run again inside the transaction, as locking reads, in the lock order every writer uses:
             * the items first, then the items still pointing at this SSL provider's fees (captured for the Change History,
             * whose costs lose their currency when those fees go), and only then the assets. Each check that finds
             * something refuses with the same message as above.
             */
            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET, $_SESSION['s_user_id'], 'ssl_provider_delete');
            // One fee change at a time (the fee pages take the same lock first), so they never deadlock
            $history_op->holdNamedLock('domainmod_fee_changes_' . substr(md5(DB_NAME), 0, 12));
            $history_refused = '';
            $stmt = $pdo->prepare("
                SELECT id
                FROM ssl_certs
                WHERE ssl_provider_id = :sslpid
                LIMIT 1
                FOR UPDATE");
            $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn()) $history_refused = _('This SSL Provider has SSL Certificates associated with it and cannot be deleted.');
            $stmt->closeCursor();
            $history_costs = $history_refused === '' ? $history_op->captureCosts('ssl', " AND i.fee_id IN (SELECT id FROM ssl_fees WHERE ssl_provider_id = :history_asset_id)", array('history_asset_id' => $sslpid)) : null;

            $stmt = $pdo->prepare("
                SELECT id
                FROM ssl_accounts
                WHERE ssl_provider_id = :sslpid
                LIMIT 1
                FOR UPDATE");
            $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
            $stmt->execute();
            $history_refused = $stmt->fetchColumn() ? _('This SSL Provider has Accounts associated with it and cannot be deleted.') : $history_refused;
            $stmt->closeCursor();

            if ($history_refused !== '') {

                if ($pdo->InTransaction()) $pdo->rollback();
                $_SESSION['s_message_danger'] .= $history_refused . '<BR>';
                header("Location: ssl-provider.php?sslpid=" . $sslpid);
                exit;

            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM ssl_fees
                WHERE ssl_provider_id = :sslpid
                FOR UPDATE");
            $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            $stmt = $pdo->prepare("
                DELETE FROM ssl_fees
                WHERE ssl_provider_id = :sslpid");
            $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("
                DELETE FROM ssl_accounts
                WHERE ssl_provider_id = :sslpid");
            $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("
                DELETE FROM ssl_providers
                WHERE id = :sslpid");
            $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
            $stmt->execute();

            $system->checkExistingAssets();

            $history_op->compareCosts($history_costs);
            $history_op->finish();

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('SSL Provider %s deleted'), $new_ssl_provider) . '<BR>';

            header("Location: ../ssl-providers.php");
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to delete SSL provider';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

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
// The page's actions, drawn in the page header (see Layout::renderPageActions())
$layout->addPageAction(_('Fees'), '../ssl-provider-fees.php?sslpid=' . $sslpid, 'bi-cash-coin');

echo $form->showFormTop('');
echo $form->showInputText('new_ssl_provider', _('SSL Provider Name') . ' (100)', '', $unsanitize->text($new_ssl_provider), '100', '', '1', '', '');
echo $form->showInputText('new_url', _("SSL Provider's URL"), '', $unsanitize->text($new_url), '100', '', '', '', '');
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '', '');
echo $form->showInputHidden('new_sslpid', $sslpid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->dangerZone(_('SSL Provider'), $new_ssl_provider, 'ssl-provider.php?sslpid=' . $sslpid . '&del=1', _('Delete this SSL provider'), sprintf(_('This removes %s and its fees. It can only be deleted when it has no accounts or SSL certificates. It can\'t be undone.'), '<strong>' . htmlentities(html_entity_decode($new_ssl_provider, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>'), 2);
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
