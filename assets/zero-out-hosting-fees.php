<?php
/**
 * /assets/zero-out-hosting-fees.php
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
$log = new DomainMOD\Log('/assets/zero-out-hosting-fees.php');
$time = new DomainMOD\Time();
$user = new DomainMOD\User();
$conversion = new DomainMOD\Conversion();
$currency = new DomainMOD\Currency();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-zero-out-hosting-fees.inc.php';

$system->authCheck();
$csrf = new DomainMOD\Csrf();
$csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data
$pdo = $deeb->cnxx;

// Before the database update there are no hosting fees to set
if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';
    header('Location: ' . $_SESSION['s_redirect']);
    exit;

}

/*
 * The change holds the hosting lock (see DomainMOD\Hosting::lock()), taken before its transaction, so no fee or service
 * can change between finding the services without a fee and giving them one
 */
if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';
    header('Location: ' . $_SESSION['s_redirect']);
    exit;

}

try {

    // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
    $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
    $pdo->beginTransaction();

    /*
     * The change is one Change History action: every service's cost and fee currency is captured first (into a
     * temporary table, see DomainMOD\ChangeHistoryOperation::captureCosts()), since a service can still point at a
     * deleted fee whose id a new fee row reuses (it then had no prior currency)
     */
    $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET, $_SESSION['s_user_id'], 'zero_out_hosting_fees');
    $history_costs = $history_op->captureCosts('hosting');

    // Each provider, service type, and billing term that has services without a fee (an invalid stored term is left alone,
    // since no fee can be added for it)
    $result = $pdo->query("
        SELECT hosting_id, type_id, billing_term
        FROM hosting_services
        WHERE fee_id = '0'
        GROUP BY hosting_id, type_id, billing_term
        ORDER BY hosting_id, type_id, billing_term")->fetchAll();

    $stmt1 = $pdo->prepare("
        INSERT INTO hosting_fees
        (hosting_id, type_id, billing_term, initial_fee, renewal_fee, misc_fee, currency_id, fee_fixed, insert_time)
        VALUES
        (:hosting_id, :type_id, :billing_term, '0.00', '0.00', '0.00', :currency_id, '1', :timestamp)");

    // The services without a fee get the group's fee, with their cost (one term's renewal fee plus misc fee) and
    // fee_fixed set together from it
    $stmt2 = $pdo->prepare("
        UPDATE hosting_services AS hs
        JOIN hosting_fees AS hf ON hf.id = :fee_id
        SET hs.fee_id = hf.id,
            hs.total_cost = hf.renewal_fee + hf.misc_fee,
            hs.fee_fixed = '1',
            hs.revision = hs.revision + 1,
            hs.update_time = :timestamp
        WHERE hs.hosting_id = :hosting_id
          AND hs.type_id = :type_id
          AND hs.billing_term = :billing_term
          AND hs.fee_id = '0'");

    $default_currency = $currency->getCurrencyId($user->getDefaultCurrency($_SESSION['s_user_id']));
    $timestamp = $time->stamp();

    foreach ($result as $row) {

        if (!DomainMOD\Hosting::validTerm($row->billing_term)) continue;

        // A fee that already exists for the group is used rather than adding a second one
        $fee_id = DomainMOD\HostingFee::findId($pdo, $row->hosting_id, $row->type_id, $row->billing_term, 'FOR UPDATE');

        if ($fee_id === 0) {

            $stmt1->bindValue('hosting_id', $row->hosting_id, PDO::PARAM_INT);
            $stmt1->bindValue('type_id', $row->type_id, PDO::PARAM_INT);
            $stmt1->bindValue('billing_term', $row->billing_term, PDO::PARAM_INT);
            $stmt1->bindValue('currency_id', $default_currency, PDO::PARAM_INT);
            $stmt1->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt1->execute();

            $fee_id = (int) $pdo->lastInsertId('id');

        }

        $stmt2->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt2->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt2->bindValue('hosting_id', $row->hosting_id, PDO::PARAM_INT);
        $stmt2->bindValue('type_id', $row->type_id, PDO::PARAM_INT);
        $stmt2->bindValue('billing_term', $row->billing_term, PDO::PARAM_INT);
        $stmt2->execute();

    }

    $history_op->compareCosts($history_costs);
    $history_op->finish();

    if ($pdo->InTransaction()) $pdo->commit();

} catch (Exception $e) {

    if ($pdo->InTransaction()) $pdo->rollback();

    $log_message = 'Unable to set missing fees to 0';
    $log_extra = array('Error' => $e);
    $log->critical($log_message, $log_extra);

    $_SESSION['s_message_danger'] .= $log_message . '<BR>';

    throw $e;

} finally {

    // The action goes (and lets go of anything it holds) before the hosting lock does
    $history_op = null;
    DomainMOD\Hosting::unlock($pdo);

}

$queryB = new DomainMOD\QueryBuild();

$sql = $queryB->missingFees('hosting_services');
$_SESSION['s_missing_hosting_fees'] = $system->checkForRows($sql);

/*
 * A rate for the default currency if no fee used it before, added only now that the change is saved and the hosting
 * lock is let go of, since it can be a network lookup. A failure here leaves the fees saved, and the rate is added on a
 * later page load.
 */
try {
    $conversion->ensureRates($_SESSION['s_user_id'], $_SESSION['s_default_currency']);
} catch (Throwable $e) {
    $log->warning('Unable to add a conversion rate after setting missing hosting fees to 0', array('Error' => $e->getMessage()));
}

$_SESSION['s_message_success'] .= _('Missing fees set to 0') . '<BR>';
header('Location: ' . $_SESSION['s_redirect']);
exit;
