<?php
/**
 * /tests/Unit/HostingRenewalTest.php
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
namespace DomainMOD\Tests\Unit;

use DomainMOD\ChangeHistoryOperation;
use DomainMOD\Hosting;
use DomainMOD\HostingRenewal;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\TestCase;

/*
 * Mark Renewed (HostingRenewal::markRenewed()), used by the hosting service page: the date it moves to, the hosting lock
 * around its transaction, the stale form check, its refusals, its Change History record, and an error rolled back. The
 * page's read-only check and the bootstrap's security token check are in HostingServicePagesTest.
 */
class HostingRenewalTest extends TestCase
{
    protected $service;

    protected function setUp(): void
    {
        if (!defined('DB_NAME')) define('DB_NAME', 'domainmod_test');
        $this->service = array('id' => 4, 'name' => 'Shared Pro', 'expiry_date' => '2026-01-31', 'billing_term' => 1,
            'renewal_day' => 31, 'revision' => 3, 'insert_time' => '2026-01-01 10:00:00', 'update_time' => '');
    }

    // A connection whose hosting_services statements work on $this->service, with $overrides added to its answers
    private function pdo(array $overrides = array())
    {
        $answers = $overrides + array(
            'GET_LOCK' => array(array(array('got' => 1))),
            'FROM hosting_services' => function ($sql, $params) {
                if ($this->service === null || (int) $params['service_id'] !== $this->service['id']) return array();
                return array(array('name' => $this->service['name'], 'expiry_date' => $this->service['expiry_date'],
                    'billing_term' => $this->service['billing_term'], 'renewal_day' => $this->service['renewal_day']));
            },
            'UPDATE hosting_services' => function ($sql, $params) {
                $service = $this->service;
                if ((int) $params['service_id'] !== $service['id'] || (int) $params['form_revision'] !== $service['revision']
                    || $params['form_insert_time'] !== $service['insert_time']) {
                    return array();
                }
                $this->service['expiry_date'] = $params['new_date'];
                $this->service['revision']++;
                $this->service['update_time'] = $params['timestamp'];
                return array(array('affected' => 1));
            },
        );
        return new RecordingPdo($answers);
    }

    private function renewal($pdo)
    {
        return new TestHostingRenewal($pdo);
    }

    private function markRenewed($pdo, $renewal = null, $revision = null, $insert_time = null)
    {
        $renewal = $renewal ?? $this->renewal($pdo);
        return $renewal->markRenewed(4, $revision ?? $this->service['revision'], $insert_time ?? $this->service['insert_time'], 9);
    }

    // The statements' SQL, in order, with their whitespace squeezed
    private function statements(RecordingPdo $pdo)
    {
        return array_map(function ($entry) { return trim(preg_replace('/\s+/', ' ', $entry['sql'])); }, $pdo->log);
    }

    // It moves the date one term from the current one, landing on "Renews On Day": January 31 with day 31 goes to
    // February 28, and from there to March 31, once per click
    public function testMovesTheDateOneTermLandingOnTheRenewalDay()
    {
        $pdo = $this->pdo();

        $result = $this->markRenewed($pdo);
        $this->assertSame(array('status' => 'renewed', 'name' => 'Shared Pro', 'date' => '2026-02-28'), $result);
        $this->assertSame('2026-02-28', $this->service['expiry_date']);
        $this->assertSame(4, $this->service['revision']);
        $this->assertSame(TestHostingRenewal::TIMESTAMP, $this->service['update_time']);

        $result = $this->markRenewed($pdo);
        $this->assertSame('2026-03-31', $result['date']);
        $this->assertSame('2026-03-31', $this->service['expiry_date']);

        // A yearly service moves a year
        $this->service['billing_term'] = 12;
        $this->service['renewal_day'] = 15;
        $this->service['expiry_date'] = '2026-07-15';
        $this->assertSame('2027-07-15', $this->markRenewed($pdo)['date']);

        $update = preg_replace('/\s+/', ' ', $pdo->statementsWith('UPDATE hosting_services')[0]['sql']);
        $this->assertStringContainsString('SET expiry_date = :new_date, revision = revision + 1, update_time = :timestamp WHERE id = :service_id AND revision = :form_revision AND insert_time = :form_insert_time', $update);
    }

    // The hosting lock is taken before the transaction and let go after it ends, and the row is read FOR UPDATE inside it
    public function testRunsInsideTheHostingLock()
    {
        $pdo = $this->pdo();

        $this->markRenewed($pdo);

        $statements = $this->statements($pdo);
        $order = array();
        foreach (array('GET_LOCK', 'START TRANSACTION', 'FOR UPDATE', 'UPDATE hosting_services', 'COMMIT', 'RELEASE_LOCK') as $text) {
            foreach ($statements as $index => $sql) {
                if (strpos($sql, $text) !== false) {
                    $order[] = $index;
                    break;
                }
            }
        }
        $this->assertCount(6, $order);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order);
        $this->assertSame(Hosting::PAGE_WAIT, $pdo->statementsWith('GET_LOCK')[0]['params']['wait']);
        $this->assertSame(Hosting::lockName(), $pdo->statementsWith('GET_LOCK')[0]['params']['lock_name']);
        $this->assertStringContainsString('WHERE id = :service_id FOR UPDATE', $statements[array_search(true, array_map(function ($sql) {
            return strpos($sql, 'FOR UPDATE') !== false;
        }, $statements), true)]);
    }

    // A busy lock changes nothing, opens no transaction, and has no lock to let go
    public function testABusyLockChangesNothing()
    {
        $pdo = $this->pdo(array('GET_LOCK' => array(array(array('got' => 0)))));

        $result = $this->markRenewed($pdo);

        $this->assertSame('busy', $result['status']);
        $this->assertSame('2026-01-31', $this->service['expiry_date']);
        $this->assertSame(array(), $pdo->statementsWith('START TRANSACTION'));
        $this->assertSame(array(), $pdo->statementsWith('RELEASE_LOCK'));
    }

    // A form drawn before another change (an older revision), a second click from the same page, or a service deleted and
    // its id given to a new one (another insert time) is rolled back and changes nothing
    public function testAStaleFormIsRolledBack()
    {
        foreach (array('older revision' => array(2, null), 'another insert time' => array(null, '2026-02-01 10:00:00')) as $case => $form) {
            $this->setUp();
            $pdo = $this->pdo();
            $renewal = $this->renewal($pdo);

            $result = $this->markRenewed($pdo, $renewal, $form[0], $form[1]);

            $this->assertSame('stale', $result['status'], $case);
            $this->assertSame('2026-01-31', $this->service['expiry_date'], $case);
            $this->assertSame(3, $this->service['revision'], $case);
            $this->assertCount(1, $pdo->statementsWith('ROLLBACK'), $case);
            $this->assertSame(array(), $pdo->statementsWith('COMMIT'), $case);
            $this->assertCount(1, $pdo->statementsWith('RELEASE_LOCK'), $case);
            $this->assertSame(0, $renewal->operation->finishes, $case);
        }

        // A second click from the page drawn before the first one went through
        $this->setUp();
        $pdo = $this->pdo();
        $this->assertSame('renewed', $this->markRenewed($pdo, null, 3)['status']);
        $this->assertSame('stale', $this->markRenewed($pdo, null, 3)['status']);
        $this->assertSame('2026-02-28', $this->service['expiry_date']);
    }

    // A service that's gone, a billing term that isn't an allowed one, and a new date past 9999-12-31 are each refused
    // and rolled back, with the lock let go
    public function testRefusesAGoneServiceAnInvalidTermAndADatePast9999()
    {
        $cases = array(
            'gone' => function () { $this->service = null; },
            'term' => function () { $this->service['billing_term'] = 2; },
            'date' => function () { $this->service['expiry_date'] = '9999-12-15'; $this->service['billing_term'] = 12; },
        );
        foreach ($cases as $status => $change) {
            $this->setUp();
            $change();
            $pdo = $this->pdo();

            $result = $this->markRenewed($pdo, null, 3, '2026-01-01 10:00:00');

            $this->assertSame($status, $result['status']);
            $this->assertSame($status === 'gone' ? '' : 'Shared Pro', $result['name']);
            $this->assertSame(array(), $pdo->statementsWith('UPDATE hosting_services'), $status);
            $this->assertCount(1, $pdo->statementsWith('ROLLBACK'), $status);
            $this->assertCount(1, $pdo->statementsWith('RELEASE_LOCK'), $status);
        }
    }

    // The renewal is recorded in the Change History as the user's own change (hosting_renew), with only the renewal date
    // captured, compared, and finished before the commit
    public function testRecordsTheRenewalInTheChangeHistory()
    {
        $pdo = $this->pdo();
        $renewal = $this->renewal($pdo);

        $this->markRenewed($pdo, $renewal);

        $this->assertSame(array('user', 9, 'hosting_renew'), $renewal->history_started);
        $this->assertSame(array(array('hosting', array(4), array('expiry_date'), null)), $renewal->operation->captures);
        $this->assertSame(1, $renewal->operation->compares);
        $this->assertSame(1, $renewal->operation->finishes);
        $this->assertCount(1, $pdo->statementsWith('COMMIT'));
    }

    // An error after the update (the Change History's own write failing) is rolled back, never committed, and thrown
    // again after the lock is let go
    public function testAnErrorAfterTheUpdateIsRolledBackAndThrown()
    {
        $pdo = $this->pdo();
        $renewal = $this->renewal($pdo);
        $renewal->fail_finish = true;

        try {
            $this->markRenewed($pdo, $renewal);
            $this->fail('The error should reach the page');
        } catch (\RuntimeException $e) {
            $this->assertSame('history failed', $e->getMessage());
        }

        $this->assertSame(array(), $pdo->statementsWith('COMMIT'));
        $this->assertCount(1, $pdo->statementsWith('ROLLBACK'));
        $statements = $this->statements($pdo);
        $this->assertStringContainsString('RELEASE_LOCK', end($statements));
    }

}

/*
 * Mark Renewed with its Change History operation recorded (without a database), a fixed update time, and a way to make
 * the history's last step fail
 */
class TestHostingRenewal extends HostingRenewal
{
    const TIMESTAMP = '2026-07-15 12:00:00';

    public $operation;
    public $history_started;
    public $fail_finish = false;

    protected function startHistory(\PDO $pdo, $user_id)
    {
        $this->history_started = array('user', $user_id, 'hosting_renew');
        $this->operation = new RecordingRenewalHistory($pdo, false, 'user', $user_id, 'hosting_renew');
        $this->operation->fail_finish = $this->fail_finish;
        return $this->operation;
    }

    protected function timestamp()
    {
        return self::TIMESTAMP;
    }
}

// A Change History operation that only records the calls made to it
class RecordingRenewalHistory extends ChangeHistoryOperation
{
    public $captures = array();
    public $compares = 0;
    public $finishes = 0;
    public $fail_finish = false;

    public function capture($type, array $ids, $only = null, $custom = null)
    {
        $this->captures[] = array($type, $ids, $only, $custom);
        return array('type' => $type, 'images' => array());
    }

    public function compare(array $before, $detail = '', $via = '')
    {
        $this->compares++;
    }

    public function finish()
    {
        if ($this->fail_finish) throw new \RuntimeException('history failed');
        $this->finishes++;
    }
} //@formatter:on
