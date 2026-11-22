<?php
/**
 * /classes/DomainMOD/HostingRenewal.php
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
//@formatter:off
namespace DomainMOD;

/*
 * Mark Renewed, on the hosting service page: moves a service's renewal date forward one billing term from its current
 * date, landing on its "Renews On Day" (Date::addMonths()), and records it in the Change History. DomainMOD can't renew
 * anything with a host, so this records a renewal the user has made or checked (see Hosting::renewalState()).
 *
 * It isn't idempotent, so the form carries the service's revision and insert time from when the page was drawn, and a
 * click sent twice (a double click, or a resend from the browser's history), from a page drawn before another change,
 * or for a service deleted since (whose id was given to a new one) finds no matching row and changes nothing. It holds
 * the hosting lock from before its transaction until after it ends, as every hosting write does. The page checks the
 * read-only setting first, and the bootstrap checks every signed-in POST's security token.
 */
class HostingRenewal
{
    protected $pdo;

    // $pdo is the shared connection (Database::getInstance()->cnxx by default), so a test can give its own
    public function __construct($pdo = null)
    {
        $this->pdo = $pdo;
    }

    /*
     * Marks the service $service_id renewed, if it still has the revision and insert time the page was drawn with.
     * Returns array('status' => ..., 'name' => the service's name, 'date' => its new renewal date), with status
     * 'renewed', 'busy' (the hosting lock stayed busy), 'gone' (no such service), 'term' (its billing term isn't an
     * allowed one), 'date' (the new date wouldn't be a valid date, such as one past 9999-12-31), or 'stale' (it changed
     * since the page was drawn). Every status but 'renewed' changes nothing. An error is rolled back and thrown again
     * after the lock is let go, for the page to report. $user_id is the user the Change History records.
     */
    public function markRenewed($service_id, $form_revision, $form_insert_time, $user_id)
    {
        $pdo = $this->pdo ?? Database::getInstance()->cnxx;
        $service_id = (int) $service_id;

        if (!$this->lock($pdo)) return $this->result('busy');

        try {

            $pdo->beginTransaction();

            // Recorded in the Change History, in the same transaction (see ChangeHistory)
            $history_op = $this->startHistory($pdo, $user_id);

            $stmt = $pdo->prepare("
                SELECT `name`, expiry_date, billing_term, renewal_day
                FROM hosting_services
                WHERE id = :service_id
                FOR UPDATE");
            $stmt->bindValue('service_id', $service_id, \PDO::PARAM_INT);
            $stmt->execute();
            $record = $stmt->fetch();
            $stmt->closeCursor();

            if (!$record) return $this->rollBack($pdo, 'gone');

            $name = (string) $record->name;
            if (!Hosting::validTerm((string) $record->billing_term)) return $this->rollBack($pdo, 'term', $name);

            $new_date = (new Date())->addMonths((string) $record->expiry_date, (int) $record->billing_term,
                (int) $record->renewal_day);
            if ($new_date === '') return $this->rollBack($pdo, 'date', $name);

            // The renewal date as it was (locked), so the change is recorded
            $history_before = $history_op->capture('hosting', array($service_id), array('expiry_date'));

            $stmt = $pdo->prepare("
                UPDATE hosting_services
                SET expiry_date = :new_date,
                    revision = revision + 1,
                    update_time = :timestamp
                WHERE id = :service_id
                  AND revision = :form_revision
                  AND insert_time = :form_insert_time");
            $stmt->bindValue('new_date', $new_date, \PDO::PARAM_STR);
            $stmt->bindValue('timestamp', $this->timestamp(), \PDO::PARAM_STR);
            $stmt->bindValue('service_id', $service_id, \PDO::PARAM_INT);
            $stmt->bindValue('form_revision', (int) $form_revision, \PDO::PARAM_INT);
            $stmt->bindValue('form_insert_time', (string) $form_insert_time, \PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() === 0) return $this->rollBack($pdo, 'stale', $name);

            $history_op->compare($history_before);
            $history_op->finish();

            $pdo->commit();

            return $this->result('renewed', $name, $new_date);

        } catch (\Throwable $e) {

            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;

        } finally {

            $this->unlock($pdo);

        }
    }

    // Rolls back a refused renewal and gives its result
    protected function rollBack(\PDO $pdo, $status, $name = '')
    {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return $this->result($status, $name);
    }

    protected function result($status, $name = '', $date = '')
    {
        return array('status' => $status, 'name' => $name, 'date' => $date);
    }

    // The Change History action a renewal is recorded in (the user's own change, from the service page)
    protected function startHistory(\PDO $pdo, $user_id)
    {
        return (new ChangeHistory($pdo))->start(ChangeHistory::SOURCE_USER, $user_id, 'hosting_renew');
    }

    protected function lock(\PDO $pdo)
    {
        return Hosting::lock($pdo, Hosting::PAGE_WAIT);
    }

    protected function unlock(\PDO $pdo)
    {
        Hosting::unlock($pdo);
    }

    // The update time written with the new date, as the other hosting writes set it
    protected function timestamp()
    {
        return (new Time())->stamp();
    }

} //@formatter:on
