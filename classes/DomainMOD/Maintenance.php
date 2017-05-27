<?php
/**
 * /classes/DomainMOD/Maintenance.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
    public $log;
    public $system;
    public $time;

    public function __construct()
    {
        $this->log = new Log('maintenance.class');
        $this->system = new System();
        $this->time = new Time();
    }

    public function performCleanup()
    {
        $this->lowercaseDomains();
        $this->updateTlds();
        $this->updateSegments();
        $this->updateAllFees();
        $this->deleteUnusedFees('fees', 'domains');
        $this->deleteUnusedFees('ssl_fees', 'ssl_certs');

        $result_message = 'Maintenance Completed<BR>';

        return $result_message;
    }

    public function lowercaseDomains()
    {
        $this->system->db()->query("UPDATE domains SET domain = LOWER(domain)");
    }

    public function updateTlds()
    {
        $tmpq = $this->system->db()->query("SELECT id, domain FROM domains");
        $result = $tmpq->fetchAll();

        if ($result) {

            $tmpq = $this->system->db()->prepare("
                UPDATE domains
                SET tld = :tld
                WHERE id = :id");

            foreach ($result as $row) {

                $tld = $this->getTld($row->domain);

                $tmpq->execute(['tld' => $tld,
                                'id' => $row->id]);

            }

        }
    }

    public function getTld($domain)
    {
        return preg_replace("/^((.*?)\.)(.*)$/", "\\3", $domain);
    }

    public function updateSegments()
    {
        $this->system->db()->query("
            UPDATE segment_data
            SET active = '0',
                inactive = '0',
                missing = '0',
                filtered = '0'");

        $this->system->db()->query("
            UPDATE segment_data
            SET active = '1'
            WHERE domain IN (SELECT domain FROM domains WHERE active NOT IN ('0', '10'))");

        $this->system->db()->query("
            UPDATE segment_data
            SET inactive = '1'
            WHERE domain IN (SELECT domain FROM domains WHERE active IN ('0', '10'))");

        $this->system->db()->query("
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
        $this->system->db()->query("UPDATE domains SET fee_fixed = '0'");

        $tmpq = $this->system->db()->prepare("
            UPDATE fees
            SET fee_fixed = '0',
                update_time = :update_time");
        $tmpq->execute(['update_time' => $this->time->stamp()]);

        $tmpq = $this->system->db()->query("
            SELECT id, registrar_id, tld
            FROM fees
            WHERE fee_fixed = '0'");
        $result = $tmpq->fetchAll();

        if ($result) {

            $tmpq = $this->system->db()->prepare("
                UPDATE domains
                SET fee_id = :id
                WHERE registrar_id = :registrar_id
                  AND tld = :tld
                  AND fee_fixed = '0'");

            $tmpq2 = $this->system->db()->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '1'");

            $tmpq3 = $this->system->db()->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '0'");

            $tmpq4 = $this->system->db()->prepare("
                UPDATE fees
                SET fee_fixed = '1',
                    update_time = :update_time
                WHERE registrar_id = :registrar_id
                  AND tld = :tld");

            foreach ($result as $row) {

                $tmpq->execute(['id' => $row->id,
                                'registrar_id' => $row->registrar_id,
                                'tld' => $row->tld]);

                $tmpq2->execute(['registrar_id' => $row->registrar_id,
                                 'tld' => $row->tld]);

                $tmpq3->execute(['registrar_id' => $row->registrar_id,
                                 'tld' => $row->tld]);

                $tmpq4->execute(['update_time' => $this->time->stamp(),
                                 'registrar_id' => $row->registrar_id,
                                 'tld' => $row->tld]);

            }

        }
    }

    public function updateDomainFee($domain_id)
    {
        $tmpq = $this->system->db()->prepare("
            SELECT registrar_id, tld
            FROM domains
            WHERE id = :domain_id");
        $tmpq->execute(['domain_id' => $domain_id]);
        $result = $tmpq->fetch();

        if ($result) {

            $registrar_id = $result->registrar_id;
            $tld = $result->tld;

        }

        $tmpq = $this->system->db()->prepare("
            UPDATE domains
            SET fee_fixed = '0'
            WHERE id = :domain_id");
        $tmpq->execute(['domain_id' => $domain_id]);

        $tmpq = $this->system->db()->prepare("
            UPDATE fees
            SET fee_fixed = '0',
                update_time = :update_time
            WHERE registrar_id = :registrar_id
              AND tld = :tld");
        $tmpq->execute(['update_time' => $this->time->stamp(),
                        'registrar_id' => $registrar_id,
                        'tld' => $tld]);

        $tmpq = $this->system->db()->prepare("
            SELECT id, registrar_id, tld
            FROM fees
            WHERE fee_fixed = '0'
              AND registrar_id = :registrar_id
              AND tld = :tld");
        $tmpq->execute(['registrar_id' => $registrar_id,
                        'tld' => $tld]);
        $result = $tmpq->fetchAll();

        if ($result) {

            $tmpq = $this->system->db()->prepare("
                UPDATE domains
                SET fee_id = :fee_id
                WHERE registrar_id = :registrar_id
                  AND tld = :tld
                  AND fee_fixed = '0'");

            $tmpq2 = $this->system->db()->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '1'");

            $tmpq3 = $this->system->db()->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '0'");

            $tmpq4 = $this->system->db()->prepare("
                UPDATE fees
                SET fee_fixed = '1',
                    update_time = :update_time
                WHERE registrar_id = :registrar_id
                  AND tld = :tld");

            foreach ($result as $row) {

                $tmpq->execute(['fee_id' => $row->id,
                                'registrar_id' => $row->registrar_id,
                                'tld' => $row->tld]);

                $tmpq2->execute(['registrar_id' => $row->registrar_id,
                                'tld' => $row->tld]);

                $tmpq3->execute(['registrar_id' => $row->registrar_id,
                                'tld' => $row->tld]);

                $tmpq4->execute(['update_time' => $this->time->stamp(),
                                'registrar_id' => $row->registrar_id,
                                'tld' => $row->tld]);

            }

        }
    }

    public function updateSslFees()
    {
        $this->system->db()->query("UPDATE ssl_certs SET fee_fixed = '0'");

        $tmpq = $this->system->db()->prepare("
            UPDATE ssl_fees
            SET fee_fixed = '0',
                update_time = :update_time");
        $tmpq->execute(['update_time' => $this->time->stamp()]);

        $tmpq = $this->system->db()->query("
            SELECT id, ssl_provider_id, type_id
            FROM ssl_fees
            WHERE fee_fixed = '0'");
        $result = $tmpq->fetchAll();

        if ($result) {

            $tmpq = $this->system->db()->prepare("
                UPDATE ssl_certs
                SET fee_id = :id
                WHERE ssl_provider_id = :ssl_provider_id
                  AND type_id = :type_id
                  AND fee_fixed = '0'");

            $tmpq2 = $this->system->db()->prepare("
                UPDATE ssl_certs sslc
                JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                SET sslc.fee_fixed = '1',
                    sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                WHERE sslc.ssl_provider_id = :ssl_provider_id
                  AND sslc.type_id = :type_id");

            $tmpq3 = $this->system->db()->prepare("
                UPDATE ssl_fees
                SET fee_fixed = '1',
                    update_time = :update_time
                WHERE ssl_provider_id = :ssl_provider_id
                  AND type_id = :type_id");

            foreach ($result as $row) {

                $tmpq->execute(['id' => $row->id,
                                'ssl_provider_id' => $row->ssl_provider_id,
                                'type_id' => $row->type_id]);

                $tmpq2->execute(['ssl_provider_id' => $row->ssl_provider_id,
                                 'type_id' => $row->type_id]);

                $tmpq3->execute(['update_time' => $this->time->stamp(),
                                 'ssl_provider_id' => $row->ssl_provider_id,
                                 'type_id' => $row->type_id]);

            }

        }
    }

    public function deleteUnusedFees($fee_table, $compare_table)
    {
        $this->system->db()->query("
            DELETE FROM " . $fee_table . "
            WHERE id NOT IN (
                             SELECT fee_id
                             FROM " . $compare_table . "
                            )");
    }

} //@formatter:on
