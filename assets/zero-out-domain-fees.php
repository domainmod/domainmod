<?php
/**
 * /assets/zero-out-domain-fees.php
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
$log = new DomainMOD\Log('/assets/zero-out-domain-fees.php');
$time = new DomainMOD\Time();
$user = new DomainMOD\User();
$conversion = new DomainMOD\Conversion();
$currency = new DomainMOD\Currency();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-zero-out-domain-fees.inc.php';

$system->authCheck();
$csrf = new DomainMOD\Csrf();
$csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data
$pdo = $deeb->cnxx;

try {

    // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
    $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
    $pdo->beginTransaction();

    /*
     * The change is one Change History action: every item's cost and fee currency is captured first (into a temporary
     * table, see DomainMOD\ChangeHistoryOperation::captureCosts()), since a group's items are all given its new fee,
     * and an old item can still point at a deleted fee whose id a new fee row reuses (it then had no prior currency)
     */
    $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET, $_SESSION['s_user_id'], 'zero_out_domain_fees');
    // One fee change at a time, so two fee pages never deadlock over items in each other's groups
    $history_op->holdNamedLock('domainmod_fee_changes_' . substr(md5(DB_NAME), 0, 12));
    $history_costs = $history_op->captureCosts('domain');


    $result = $pdo->query("
    SELECT domain, registrar_id
    FROM domains
    WHERE fee_id = '0'")->fetchAll();

    $rid_tld_pairs = array();
    foreach ($result as $row) {
        $tld = DomainMOD\Domain::tldOf($row->domain);
        $key = $row->registrar_id . '_' . $tld;  // Create a unique key
        $rid_tld_pairs[$key] = array('rid' => $row->registrar_id, 'tld' => $tld);
    }

    $unique_rid_tld_pairs = array_values($rid_tld_pairs);

    $stmt1 = $pdo->prepare("
    INSERT INTO fees
    (registrar_id, tld, initial_fee, renewal_fee, transfer_fee, privacy_fee, misc_fee, currency_id, fee_fixed, insert_time)
    VALUES
    (:rid, :new_tld, '0.0', '0.0', '0.0', '0.0', '0.0', :currency_id, '1', :timestamp)");

    $stmt2 = $pdo->prepare("
    UPDATE domains
    SET fee_id = :new_fee_id,
        update_time = :timestamp,
        total_cost = '0.0',
        fee_fixed = '1'
    WHERE registrar_id = :rid
      AND tld = :new_tld");

    $default_currency = $currency->getCurrencyId($user->getDefaultCurrency($_SESSION['s_user_id']));
    $timestamp = $time->stamp();

    foreach ($unique_rid_tld_pairs as $pair) {

        $stmt1->bindValue('rid', $pair['rid'], PDO::PARAM_INT);
        $stmt1->bindValue('new_tld', $pair['tld'], PDO::PARAM_STR);
        $stmt1->bindValue('currency_id', $default_currency, PDO::PARAM_INT);
        $stmt1->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt1->execute();

        $new_fee_id = $pdo->lastInsertId('id');

        $stmt2->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
        $stmt2->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt2->bindValue('rid', $pair['rid'], PDO::PARAM_INT);
        $stmt2->bindValue('new_tld', $pair['tld'], PDO::PARAM_STR);
        $stmt2->execute();

    }

    $queryB = new DomainMOD\QueryBuild();

    $sql = $queryB->missingFees('domains');
    $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

    $conversion->updateRates($_SESSION['s_default_currency'], $_SESSION['s_user_id']);


    $history_op->compareCosts($history_costs);
    $history_op->finish();

    if ($pdo->InTransaction()) $pdo->commit();

    $_SESSION['s_message_success'] .= _('Missing fees set to 0') . '<BR>';
    header('Location: ' . $_SESSION['s_redirect']);
    exit;

} catch (Exception $e) {

    if ($pdo->InTransaction()) $pdo->rollback();

    $log_message = 'Unable to set missing fees to 0';
    $log_extra = array('Error' => $e);
    $log->critical($log_message, $log_extra);

    // No message for a conflict, whose own page explains it (see domainmod_restore_is_conflict())
    if (!domainmod_restore_is_conflict($e)) $_SESSION['s_message_danger'] .= $log_message . '<BR>';

    throw $e;

}
