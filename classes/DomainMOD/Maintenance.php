<?php
/**
 * /classes/DomainMOD/Maintenance.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
//@formatter:off
namespace DomainMOD;

class Maintenance
{
    public $deeb;
    public $log;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.maintenance');
        $this->time = new Time();
    }

    public function performCleanup()
    {
        $this->lowercaseDomains();
        $this->lowercaseTlds();
        $this->updateTlds();
        $this->updateSegments();
        $this->updateAllFees();
        $this->deleteUnusedFees('fees', 'domains');
        $this->deleteUnusedFees('ssl_fees', 'ssl_certs');
        $this->zeroInvalidIpIds();

        $result_message = _('Maintenance Completed') . '<BR>';

        return $result_message;
    }

    public function lowercaseDomains()
    {
        $this->deeb->cnxx->query("UPDATE domains SET domain = LOWER(domain)");
    }

    public function lowercaseTlds()
    {
        $this->deeb->cnxx->query("UPDATE fees SET tld = LOWER(tld)");
    }

    public function updateTlds()
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("SELECT id, domain FROM domains")->fetchAll();

        if ($result) {

            $pdo = $this->deeb->cnxx;
            $stmt = $pdo->prepare("
                UPDATE domains
                SET tld = :tld
                WHERE id = :id");
            $stmt->bindParam('tld', $bind_tld, \PDO::PARAM_STR);
            $stmt->bindParam('id', $bind_id, \PDO::PARAM_INT);

            foreach ($result as $row) {

                $bind_tld = $this->getTld($row->domain);
                $bind_id = $row->id;
                $stmt->execute();

            }

        }
    }

    public function getTld($domain)
    {
        return preg_replace("/^((.*?)\.)(.*)$/", "\\3", $domain);
    }

    public function updateSegments()
    {
        $pdo = $this->deeb->cnxx;
        $pdo->query("
            UPDATE segment_data
            SET active = '0',
                inactive = '0',
                missing = '0',
                filtered = '0'");

        $pdo->query("
            UPDATE segment_data
            SET active = '1'
            WHERE domain IN (SELECT domain FROM domains WHERE active NOT IN ('0', '10'))");

        $pdo->query("
            UPDATE segment_data
            SET inactive = '1'
            WHERE domain IN (SELECT domain FROM domains WHERE active IN ('0', '10'))");

        $pdo->query("
            UPDATE segment_data
            SET missing = '1'
            WHERE domain NOT IN (SELECT domain FROM domains)");
    }

    public function updateAllFees()
    {
        $this->updateDomainFees();
        $this->updateSslFees();
    }

    public function updateDomainFees()
    {
        $pdo = $this->deeb->cnxx;

        $pdo->query("UPDATE domains SET fee_fixed = '0'");

        $stmt = $pdo->prepare("
            UPDATE fees
            SET fee_fixed = '0',
                update_time = :update_time");
        $timestamp = $this->time->stamp();
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $pdo->query("
            SELECT id, registrar_id, tld
            FROM fees
            WHERE fee_fixed = '0'")->fetchAll();

        if ($result) {

            $stmt = $pdo->prepare("
                UPDATE domains
                SET fee_id = :fee_id
                WHERE registrar_id = :registrar_id
                  AND tld = :tld
                  AND fee_fixed = '0'");
            $stmt->bindParam('fee_id', $bind_fee_id, \PDO::PARAM_INT);
            $stmt->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt2 = $pdo->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '1'");
            $stmt2->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt2->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt3 = $pdo->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '0'");
            $stmt3->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt3->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt4 = $pdo->prepare("
                UPDATE fees
                SET fee_fixed = '1',
                    update_time = :update_time
                WHERE registrar_id = :registrar_id
                  AND tld = :tld");
            $timestamp = $this->time->stamp();
            $stmt4->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
            $stmt4->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt4->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            foreach ($result as $row) {

                $bind_fee_id = $row->id;
                $bind_registrar_id = $row->registrar_id;
                $bind_tld = $row->tld;

                $stmt->execute();

                $stmt2->execute();

                $stmt3->execute();

                $stmt4->execute();

            }

        }
    }

    public function updateDomainFee($domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT registrar_id, tld
            FROM domains
            WHERE id = :domain_id");
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result) {

            $registrar_id = $result->registrar_id;
            $tld = $result->tld;

        }

        $stmt = $pdo->prepare("
            UPDATE domains
            SET fee_fixed = '0'
            WHERE id = :domain_id");
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            UPDATE fees
            SET fee_fixed = '0',
                update_time = :update_time
            WHERE registrar_id = :registrar_id
              AND tld = :tld");
        $timestamp = $this->time->stamp();
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
        $stmt->execute();

        $stmt = $pdo->prepare("
            SELECT id, registrar_id, tld
            FROM fees
            WHERE fee_fixed = '0'
              AND registrar_id = :registrar_id
              AND tld = :tld");
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchAll();

        if ($result) {

            $stmt = $pdo->prepare("
                UPDATE domains
                SET fee_id = :fee_id
                WHERE registrar_id = :registrar_id
                  AND tld = :tld
                  AND fee_fixed = '0'");
            $stmt->bindParam('fee_id', $bind_fee_id, \PDO::PARAM_INT);
            $stmt->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt2 = $pdo->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '1'");
            $stmt2->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt2->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt3 = $pdo->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '0'");
            $stmt3->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt3->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt4 = $pdo->prepare("
                UPDATE fees
                SET fee_fixed = '1',
                    update_time = :update_time
                WHERE registrar_id = :registrar_id
                  AND tld = :tld");
            $timestamp = $this->time->stamp();
            $stmt4->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
            $stmt4->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt4->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            foreach ($result as $row) {

                $bind_fee_id = $row->id;
                $bind_registrar_id = $row->registrar_id;
                $bind_tld = $row->tld;

                $stmt->execute();

                $stmt2->execute();

                $stmt3->execute();

                $stmt4->execute();

            }

        }
    }

    public function updateSslFees()
    {
        $pdo = $this->deeb->cnxx;

        $pdo->query("UPDATE ssl_certs SET fee_fixed = '0'");

        $stmt = $pdo->prepare("
            UPDATE ssl_fees
            SET fee_fixed = '0',
                update_time = :update_time");
        $timestamp = $this->time->stamp();
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $pdo->query("
            SELECT id, ssl_provider_id, type_id
            FROM ssl_fees
            WHERE fee_fixed = '0'")->fetchAll();

        if ($result) {

            $stmt = $pdo->prepare("
                UPDATE ssl_certs
                SET fee_id = :fee_id
                WHERE ssl_provider_id = :ssl_provider_id
                  AND type_id = :type_id
                  AND fee_fixed = '0'");
            $stmt->bindParam('fee_id', $bind_fee_id, \PDO::PARAM_INT);
            $stmt->bindParam('ssl_provider_id', $bind_ssl_provider_id, \PDO::PARAM_INT);
            $stmt->bindParam('type_id', $bind_type_id, \PDO::PARAM_INT);

            $stmt2 = $pdo->prepare("
                UPDATE ssl_certs sslc
                JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                SET sslc.fee_fixed = '1',
                    sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                WHERE sslc.ssl_provider_id = :ssl_provider_id
                  AND sslc.type_id = :type_id");
            $stmt2->bindParam('ssl_provider_id', $bind_ssl_provider_id, \PDO::PARAM_INT);
            $stmt2->bindParam('type_id', $bind_type_id, \PDO::PARAM_INT);

            $stmt3 = $pdo->prepare("
                UPDATE ssl_fees
                SET fee_fixed = '1',
                    update_time = :update_time
                WHERE ssl_provider_id = :ssl_provider_id
                  AND type_id = :type_id");
            $timestamp = $this->time->stamp();
            $stmt3->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
            $stmt3->bindParam('ssl_provider_id', $bind_ssl_provider_id, \PDO::PARAM_INT);
            $stmt3->bindParam('type_id', $bind_type_id, \PDO::PARAM_INT);

            foreach ($result as $row) {

                $bind_fee_id = $row->id;
                $bind_ssl_provider_id = $row->ssl_provider_id;
                $bind_type_id = $row->type_id;

                $stmt->execute();

                $stmt2->execute();

                $stmt3->execute();

            }

        }
    }

    public function deleteUnusedFees($fee_table, $compare_table)
    {
        $this->deeb->cnxx->query("
            DELETE FROM " . $fee_table . "
            WHERE id NOT IN (
                             SELECT fee_id
                             FROM " . $compare_table . "
                            )");
    }

    public function zeroInvalidIpIds()
    { // This zeroes out API IP address IDs in the registrar_account table that are no longer valid. For example, if an
      // IP has been deleted.
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT id
            FROM ip_addresses")->fetchAll();

        if ($result) {

            $id_array = array();

            foreach ($result as $row) {

                $id_array[] = $row->id;

            }

            $in_list = str_repeat('?, ', count($id_array) - 1) . '?';
            $sql = "UPDATE registrar_accounts
                    SET api_ip_id = '0'
                    WHERE api_ip_id NOT IN (" . $in_list . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($id_array);

        }

    }

} //@formatter:on
