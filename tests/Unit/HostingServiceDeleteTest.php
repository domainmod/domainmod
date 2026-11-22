<?php
/**
 * /tests/Unit/HostingServiceDeleteTest.php
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

use DomainMOD\HostingServiceDelete;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\TestCase;

/*
 * The hosting service delete's domain side (HostingServiceDelete, used by hosting/edit.php): the domains index it
 * forces, found by its definition, the keyset pages that lock, record, and unlink the service's domains, and the
 * deadlock retry. It runs on a RecordingPdo, with stand-ins for the Domain and the Change History operation, so no
 * database is needed.
 */
class HostingServiceDeleteTest extends TestCase
{
    // An index row from INFORMATION_SCHEMA.STATISTICS, in the upper case MySQL gives its column names
    private static function indexRow($name, $seq, $column, array $flags = array())
    {
        return array('INDEX_NAME' => $name, 'SEQ_IN_INDEX' => $seq, 'COLUMN_NAME' => $column) + $flags;
    }

    private static function indexName(array $rows)
    {
        return HostingServiceDelete::serviceIndexName(new RecordingPdo(array('STATISTICS' => array($rows))));
    }

    public function testTheIndexIsFoundByItsDefinitionWhateverItsName()
    {
        $this->assertSame('service_id', self::indexName(array(self::indexRow('PRIMARY', 1, 'id'),
            self::indexRow('service_id', 1, 'service_id'))));
        // A name an older index already had
        $this->assertSame('idx_domains_service', self::indexName(array(self::indexRow('idx_domains_service', 1, 'SERVICE_ID'))));
        // One named service_id wins over another with the same column
        $this->assertSame('service_id', self::indexName(array(self::indexRow('another', 1, 'service_id'),
            self::indexRow('service_id', 1, 'service_id'))));
    }

    // An index with more columns isn't in id order within a service, and an unusable one can't be forced
    public function testNoUsableSingleColumnIndexIsNoHint()
    {
        $this->assertSame('', self::indexName(array(self::indexRow('service_expiry', 1, 'service_id'),
            self::indexRow('service_expiry', 2, 'expiry_date'))));
        $this->assertSame('', self::indexName(array(self::indexRow('service_id', 1, 'service_id', array('IGNORED' => 'YES')))));
        $this->assertSame('', self::indexName(array(self::indexRow('service_id', 1, 'service_id', array('IS_VISIBLE' => 'NO')))));
        $this->assertSame('', self::indexName(array(self::indexRow('PRIMARY', 1, 'id'))));
        $this->assertSame('', HostingServiceDelete::serviceIndexName(new RecordingPdo(array('STATISTICS' => new \PDOException('no access')))));
        // The check asks about the domains table only
        $pdo = new RecordingPdo();
        HostingServiceDelete::serviceIndexName($pdo);
        $this->assertStringContainsString("`TABLE_NAME` = 'domains'", $pdo->statementsWith('STATISTICS')[0]['sql']);
    }

    // A Domain stand-in whose clearHostingService() records its calls and changes every domain it's given
    private static function domain()
    {
        return new class {
            public $calls = array();

            public function clearHostingService(array $domain_ids, $service_id)
            {
                $this->calls[] = array($domain_ids, $service_id);
                return count($domain_ids);
            }
        };
    }

    // A ChangeHistoryOperation stand-in that records what's captured and compared
    private static function operation()
    {
        return new class {
            public $calls = array();

            public function capture($type, array $ids, $only = null, $custom = null)
            {
                $this->calls[] = array('capture', $type, $ids, $only, $custom);
                return array('ids' => $ids);
            }

            public function compare(array $before, $detail = '', $via = '')
            {
                $this->calls[] = array('compare', $before['ids'], $detail);
            }
        };
    }

    /*
     * The service's domains, 1,005 of them: two keyset pages, each locked through the index (FORCE INDEX with the name
     * found), in id order after the last id of the page before, then captured with service_id alone, unlinked, and
     * compared as a side effect, before the next page is read
     */
    public function testTheDomainsAreUnlinkedInRecordedKeysetPages()
    {
        $ids = range(10, 1014);
        $pdo = new RecordingPdo(array(
            'STATISTICS' => array(array(self::indexRow('svc', 1, 'service_id'))),
            'FROM domains' => function ($sql, $params) use ($ids) {
                $page = array_slice(array_values(array_filter($ids, function ($id) use ($params) {
                    return $id > $params['last'];
                })), 0, HostingServiceDelete::PAGE);
                return array_map(function ($id) { return array('id' => (string) $id); }, $page);
            }));
        $domain = self::domain();
        $op = self::operation();

        $this->assertSame(1005, (new HostingServiceDelete($pdo, $domain))->unlinkDomains('7', $op));

        $pages = $pdo->statementsWith('FROM domains');
        $this->assertCount(2, $pages);
        foreach ($pages as $page) {
            $sql = preg_replace('/\s+/', ' ', trim($page['sql']));
            $this->assertSame('SELECT id FROM domains FORCE INDEX (`svc`) WHERE service_id = :service_id AND id > :last '
                . 'ORDER BY id LIMIT 1000 FOR UPDATE', $sql);
            $this->assertSame(7, $page['params']['service_id']);
        }
        $this->assertSame(0, $pages[0]['params']['last']);
        $this->assertSame(1009, $pages[1]['params']['last']);

        $first = range(10, 1009);
        $second = range(1010, 1014);
        $this->assertSame(array(array($first, 7), array($second, 7)), $domain->calls);
        $this->assertSame(array(
            array('capture', 'domain', $first, array('service_id'), null), array('compare', $first, 'side_effect'),
            array('capture', 'domain', $second, array('service_id'), null), array('compare', $second, 'side_effect'),
        ), $op->calls);
        // It neither starts nor ends a transaction, and changes domains only through clearHostingService()
        $this->assertSame(array(), $pdo->statementsWith('TRANSACTION'));
        $this->assertSame(array(), $pdo->statementsWith('UPDATE domains'));
    }

    public function testWithoutTheIndexThePagesHaveNoHint()
    {
        $pdo = new RecordingPdo(array('FROM domains' => array(array(array('id' => 3), array('id' => 4)), array())));
        $domain = self::domain();

        $this->assertSame(2, (new HostingServiceDelete($pdo, $domain))->unlinkDomains(7, self::operation()));
        // A short page is the last, so it isn't read again
        $this->assertCount(1, $pdo->statementsWith('FROM domains'));
        $this->assertStringNotContainsString('FORCE INDEX', $pdo->statementsWith('FROM domains')[0]['sql']);
    }

    public function testAServiceWithNoDomainsRecordsNothing()
    {
        $pdo = new RecordingPdo();
        $domain = self::domain();
        $op = self::operation();

        $this->assertSame(0, (new HostingServiceDelete($pdo, $domain))->unlinkDomains(7, $op));
        $this->assertSame(array(), $domain->calls);
        $this->assertSame(array(), $op->calls);
        $this->assertSame(0, (new HostingServiceDelete($pdo, $domain))->unlinkDomains(0, $op));
    }

    private static function deadlock()
    {
        $e = new \PDOException('Deadlock found when trying to get lock');
        $e->errorInfo = array('40001', 1213, 'Deadlock found when trying to get lock');
        return $e;
    }

    // A deadlock rolls the attempt back and runs it once more from the start, so its count starts again
    public function testADeadlockIsRetriedOnceFromTheStart()
    {
        $pdo = new RecordingPdo();
        $attempts = array();

        $result = (new HostingServiceDelete($pdo, self::domain()))->withDeadlockRetry(function ($number) use ($pdo, &$attempts) {
            $pdo->beginTransaction();
            $counted = 0;
            $counted += 3;
            $attempts[] = array($number, $counted);
            if ($number === 1) throw self::deadlock();
            $pdo->commit();
            return $counted;
        });

        $this->assertSame(3, $result);
        $this->assertSame(array(array(1, 3), array(2, 3)), $attempts);
        $this->assertSame(array('START TRANSACTION', 'ROLLBACK', 'START TRANSACTION', 'COMMIT'), array_column($pdo->log, 'sql'));
    }

    public function testASecondDeadlockIsThrown()
    {
        $pdo = new RecordingPdo();
        $runs = 0;

        try {
            (new HostingServiceDelete($pdo, self::domain()))->withDeadlockRetry(function () use ($pdo, &$runs) {
                $runs++;
                $pdo->beginTransaction();
                throw self::deadlock();
            });
            $this->fail('No exception');
        } catch (\PDOException $e) {
            $this->assertSame(1213, $e->errorInfo[1]);
        }
        $this->assertSame(2, $runs);
        $this->assertFalse($pdo->inTransaction());
    }

    // Any other error (a lock wait timeout included) isn't retried, and is thrown after the rollback
    public function testOtherErrorsAreThrownWithoutARetry()
    {
        foreach (array(1205, 1062) as $code) {
            $pdo = new RecordingPdo();
            $runs = 0;
            $error = new \PDOException('error ' . $code);
            $error->errorInfo = array('HY000', $code, 'error');
            try {
                (new HostingServiceDelete($pdo, self::domain()))->withDeadlockRetry(function () use ($pdo, &$runs, $error) {
                    $runs++;
                    $pdo->beginTransaction();
                    throw $error;
                });
                $this->fail('No exception');
            } catch (\PDOException $e) {
                $this->assertSame($error, $e);
            }
            $this->assertSame(1, $runs, (string) $code);
            $this->assertFalse($pdo->inTransaction());
        }

        $pdo = new RecordingPdo();
        $this->expectException(\RuntimeException::class);
        (new HostingServiceDelete($pdo, self::domain()))->withDeadlockRetry(function () use ($pdo) {
            $pdo->beginTransaction();
            throw new \RuntimeException('not a database error');
        });
    }

} //@formatter:on
