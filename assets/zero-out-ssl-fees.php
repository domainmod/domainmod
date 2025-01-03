<?php
/**
 * /assets/zero-out-ssl-fees.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2025 Greg Chetcuti <greg@greg.ca>
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
$log = new DomainMOD\Log('/assets/zero-out-ssl-fees.php');
$time = new DomainMOD\Time();
$user = new DomainMOD\User();
$conversion = new DomainMOD\Conversion();
$currency = new DomainMOD\Currency();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-zero-out-ssl-fees.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

try {

    $pdo->beginTransaction();

    $result = $pdo->query("
    SELECT ssl_provider_id, type_id
    FROM ssl_certs
    WHERE fee_id = '0'")->fetchAll();

    $pid_tid_pairs = array();
    foreach ($result as $row) {
        $key = $row->ssl_provider_id . '_' . $row->type_id;  // Create a unique key
        $pid_tid_pairs[$key] = array('pid' => $row->ssl_provider_id, 'tid' => $row->type_id);
    }

    $unique_pid_tid_pairs = array_values($pid_tid_pairs);

    $stmt1 = $pdo->prepare("
    INSERT INTO ssl_fees
    (ssl_provider_id, type_id, initial_fee, renewal_fee, misc_fee, currency_id, fee_fixed, insert_time) 
    VALUES
    (:pid, :tid, '0.0', '0.0', '0.0', :currency_id, '1', :timestamp)");

    $stmt2 = $pdo->prepare("
    UPDATE ssl_certs
    SET fee_id = :new_fee_id,
        update_time = :timestamp,
        total_cost = '0.0',
        fee_fixed = '1'
    WHERE ssl_provider_id = :pid
      AND type_id = :tid");

    $default_currency = $currency->getCurrencyId($user->getDefaultCurrency($_SESSION['s_user_id']));
    $timestamp = $time->stamp();

    foreach ($unique_pid_tid_pairs as $pair) {

        $stmt1->bindValue('pid', $pair['pid'], PDO::PARAM_INT);
        $stmt1->bindValue('tid', $pair['tid'], PDO::PARAM_STR);
        $stmt1->bindValue('currency_id', $default_currency, PDO::PARAM_INT);
        $stmt1->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt1->execute();

        $new_fee_id = $pdo->lastInsertId('id');

        $stmt2->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
        $stmt2->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt2->bindValue('pid', $pair['pid'], PDO::PARAM_INT);
        $stmt2->bindValue('tid', $pair['tid'], PDO::PARAM_STR);
        $stmt2->execute();

    }

    $queryB = new DomainMOD\QueryBuild();

    $sql = $queryB->missingFees('ssl_certs');
    $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($sql);

    $conversion->updateRates($_SESSION['s_default_currency'], $_SESSION['s_user_id']);

    if ($pdo->InTransaction()) $pdo->commit();

    $_SESSION['s_message_success'] .= _('Missing fees set to 0') . '<BR>';
    header('Location: ' . $_SESSION['s_redirect']);
    exit;

} catch (Exception $e) {

    if ($pdo->InTransaction()) $pdo->rollback();

    $log_message = 'Unable to set missing fees to 0';
    $log_extra = array('Error' => $e);
    $log->critical($log_message, $log_extra);

    $_SESSION['s_message_danger'] .= $log_message . '<BR>';

    throw $e;

}
