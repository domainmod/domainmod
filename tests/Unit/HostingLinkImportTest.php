<?php
/**
 * /tests/Unit/HostingLinkImportTest.php
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

use DomainMOD\HostingLinkImport;
use DomainMOD\Notice;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\TestCase;

/*
 * The 4.27.0 upgrade's import of each domain's old Web Hosting Provider into hosting services (HostingLinkImport): the
 * imported services' renewal values, the category they get, the statements import() sends (with RecordingPdo), the
 * column drop after the commit, the placeholder match that the notice's count, the update block's delete, and the
 * import share, and where the update block calls it all.
 */
class HostingLinkImportTest extends TestCase
{
    const UPDATE_FILE = '_includes/updates/4.24.02-current.inc.php';

    // One year ahead, on the same day of the month, which is also the Renews On Day (a short month takes its last day)
    public function testTheRenewalValues()
    {
        $this->assertSame(array('expiry_date' => '2027-11-22', 'renewal_day' => 22), HostingLinkImport::renewalValues('2026-11-22'));
        $this->assertSame(array('expiry_date' => '2028-01-31', 'renewal_day' => 31), HostingLinkImport::renewalValues('2027-01-31'));
        $this->assertSame(array('expiry_date' => '2029-02-28', 'renewal_day' => 29), HostingLinkImport::renewalValues('2028-02-29'));
        $this->assertSame(array('expiry_date' => '2027-12-31', 'renewal_day' => 31), HostingLinkImport::renewalValues('2026-12-31'));
    }

    // A connection whose lastInsertId() gives $id, for the category insert
    private static function pdoWithInsertId(array $answers, $id)
    {
        return new class($answers, $id) extends RecordingPdo {
            protected $insert_id;

            public function __construct(array $answers, $id)
            {
                parent::__construct($answers);
                $this->insert_id = $id;
            }

            public function lastInsertId(?string $name = null): string|false
            {
                return (string) $this->insert_id;
            }
        };
    }

    // The default domain category when its row exists, then the installer's category, then the first, then a new one
    public function testTheCategoryChoice()
    {
        $default = 'INNER JOIN categories AS c ON c.id = s.default_category_domains';
        $installation = 'WHERE creation_type_id = :creation_type_id';
        $first = "FROM categories\n                ORDER BY id";

        $pdo = new RecordingPdo(array($default => array(array(array('id' => 7))), $installation => array(array(array('id' => 2)))));
        $this->assertSame(7, HostingLinkImport::categoryId($pdo, 3, '2026-11-22 10:00:00'));
        $this->assertCount(1, $pdo->log);

        $pdo = new RecordingPdo(array($installation => array(array(array('id' => 2))), $first => array(array(array('id' => 1)))));
        $this->assertSame(2, HostingLinkImport::categoryId($pdo, 3, '2026-11-22 10:00:00'));
        $this->assertSame(array('creation_type_id' => 3), $pdo->statementsWith($installation)[0]['params']);

        $pdo = new RecordingPdo(array($first => array(array(array('id' => 5)))));
        $this->assertSame(5, HostingLinkImport::categoryId($pdo, 3, '2026-11-22 10:00:00'));
        $this->assertSame(array(), $pdo->statementsWith('INSERT INTO categories'));

        $pdo = self::pdoWithInsertId(array(), 9);
        $this->assertSame(9, HostingLinkImport::categoryId($pdo, 3, '2026-11-22 10:00:00'));
        $insert = $pdo->statementsWith('INSERT INTO categories');
        $this->assertCount(1, $insert);
        $this->assertSame(array('name' => '[no category]', 'stakeholder' => '[no stakeholder]', 'creation_type_id' => 3,
            'insert_time' => '2026-11-22 10:00:00'), $insert[0]['params']);
    }

    // A connection for import(), with the old column there or not, and $accounts imported accounts
    private static function importPdo($column, $accounts)
    {
        return new RecordingPdo(array(
            'INFORMATION_SCHEMA' => array(array(array('found' => $column ? 1 : 0))),
            'INSERT INTO hosting_provider_accounts' => array(array_fill(0, $accounts, array('row' => 1))),
            'FROM hosting_types' => array(array(array('id' => 8))),
            'INNER JOIN categories AS c' => array(array(array('id' => 4)))));
    }

    private static function flat($sql)
    {
        return preg_replace('/\s+/', ' ', $sql);
    }

    public function testNothingHappensWithoutTheOldColumn()
    {
        $pdo = self::importPdo(false, 2);
        $this->assertSame(0, HostingLinkImport::import($pdo, 3, '2026-11-22 10:00:00', '2026-11-22'));
        $this->assertCount(1, $pdo->log);
        $this->assertStringContainsString("`COLUMN_NAME` = 'hosting_id'", $pdo->log[0]['sql']);
    }

    public function testTheImportsStatements()
    {
        $pdo = self::importPdo(true, 3);
        $this->assertSame(3, HostingLinkImport::import($pdo, 3, '2026-11-22 10:00:00', '2028-02-29'));

        $placeholder = HostingLinkImport::placeholderMatch('h');
        $joins = self::flat(HostingLinkImport::ELIGIBLE_JOINS);
        $this->assertSame('INNER JOIN hosting AS h ON h.id = d.hosting_id INNER JOIN registrar_accounts AS ra ON ra.id = '
            . 'd.account_id INNER JOIN owners AS o ON o.id = ra.owner_id', $joins);
        $eligible = $joins . ' WHERE d.hosting_id <> 0 AND NOT ' . $placeholder['sql'];

        // The accounts: one per provider and owner pair of the eligible domains, in a fixed order
        $accounts = $pdo->statementsWith('INSERT INTO hosting_provider_accounts');
        $this->assertCount(1, $accounts);
        $sql = self::flat($accounts[0]['sql']);
        $this->assertStringContainsString('SELECT DISTINCT ra.owner_id, d.hosting_id, ', $sql);
        $this->assertStringContainsString('FROM domains AS d ' . $eligible . ' ORDER BY d.hosting_id, ra.owner_id', $sql);
        $this->assertSame('Imported', $accounts[0]['params']['username']);
        $this->assertSame(HostingLinkImport::ACCOUNT_NOTES, $accounts[0]['params']['notes']);
        $this->assertSame(3, $accounts[0]['params']['creation_type_id']);
        foreach ($placeholder['params'] as $key => $value) $this->assertSame($value, $accounts[0]['params'][$key]);
        // The note doesn't ask for a username the account already has
        $this->assertStringNotContainsString("Add the account's username", HostingLinkImport::ACCOUNT_NOTES);

        // The services: one per imported account, with the placeholder renewal values
        $services = $pdo->statementsWith('INSERT INTO hosting_services');
        $this->assertCount(1, $services);
        $sql = self::flat($services[0]['sql']);
        $this->assertStringContainsString('FROM hosting_provider_accounts AS hpa INNER JOIN hosting AS h ON h.id = hpa.hosting_id ORDER BY hpa.id', $sql);
        $this->assertStringContainsString('SELECT hpa.owner_id, hpa.hosting_id, hpa.id, :type_id, 0, :cat_id, h.name, ', $sql);
        $params = $services[0]['params'];
        $this->assertSame(array(8, 4, 12, 29, '2029-02-28', 1, 3), array($params['type_id'], $params['cat_id'],
            $params['billing_term'], $params['renewal_day'], $params['expiry_date'], $params['active'],
            $params['creation_type_id']));
        $this->assertSame(HostingLinkImport::SERVICE_NOTES, $params['notes']);
        // Auto-renew off, no fee, and no IP address
        $this->assertStringContainsString(':renewal_day, 0, :expiry_date, 0, 0, 0, 0, :notes', $sql);

        $this->assertCount(1, $pdo->statementsWith('INSERT INTO hosting_service_field_data'));

        // The links: the same eligible domains, each joined to the service for both its provider and its owner
        $links = $pdo->statementsWith('SET d.service_id = hs.service_id');
        $this->assertCount(1, $links);
        $sql = self::flat($links[0]['sql']);
        $this->assertStringContainsString('UPDATE domains AS d ' . $joins . ' INNER JOIN hosting_link_import AS hs ON '
            . 'hs.hosting_id = d.hosting_id AND hs.owner_id = ra.owner_id SET d.service_id = hs.service_id WHERE '
            . 'd.hosting_id <> 0 AND NOT ' . $placeholder['sql'], $sql);
        $this->assertSame($placeholder['params'], $links[0]['params']);
        $this->assertStringNotContainsString('update_time', $sql);
        // Each service is found through a temporary table keyed on its provider and owner, made from every service
        // and dropped after the links
        $create = $pdo->statementsWith('CREATE TEMPORARY TABLE `hosting_link_import`');
        $this->assertCount(1, $create);
        $this->assertStringContainsString('PRIMARY KEY  (`hosting_id`, `owner_id`)', $create[0]['sql']);
        $this->assertStringContainsString('SELECT hosting_id, owner_id, id FROM hosting_services ORDER BY id',
            self::flat($pdo->statementsWith('INSERT INTO `hosting_link_import`')[0]['sql']));
        $this->assertCount(2, $pdo->statementsWith('DROP TEMPORARY TABLE IF EXISTS `hosting_link_import`'));

        // In this order, ending with every old link set to 0
        $order = array();
        foreach ($pdo->log as $entry) $order[] = self::flat($entry['sql']);
        $positions = array();
        foreach (array('INSERT INTO hosting_provider_accounts', 'FROM hosting_types', 'INNER JOIN categories AS c',
                     'INSERT INTO hosting_services', 'INSERT INTO hosting_service_field_data', 'CREATE TEMPORARY TABLE',
                     'SET d.service_id = hs.service_id') as $text) {
            foreach ($order as $index => $sql) {
                if (strpos($sql, $text) !== false) {
                    $positions[] = $index;
                    break;
                }
            }
        }
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $this->assertCount(7, $positions);
        $this->assertSame('UPDATE domains SET hosting_id = 0 WHERE hosting_id <> 0', trim(end($order)));
    }

    // Without the privilege to make a temporary table, the links join hosting_services directly, with the same result
    public function testTheLinksWorkWithoutATemporaryTable()
    {
        $pdo = self::importPdo(true, 1);
        $answers = new \ReflectionProperty($pdo, 'answers');
        $denied = new \PDOException('Access denied for user to database');
        $denied->errorInfo = array('42000', 1044, 'Access denied for user to database');
        $answers->setValue($pdo, array('CREATE TEMPORARY TABLE' => $denied) + $answers->getValue($pdo));
        $this->assertSame(1, HostingLinkImport::import($pdo, 3, '2026-11-22 10:00:00', '2026-11-22'));
        $links = $pdo->statementsWith('SET d.service_id');
        $this->assertCount(1, $links);
        $this->assertStringContainsString('INNER JOIN hosting_services AS hs ON hs.hosting_id = d.hosting_id AND '
            . 'hs.owner_id = ra.owner_id SET d.service_id = hs.id WHERE', self::flat($links[0]['sql']));
        $this->assertSame(array(), $pdo->statementsWith('INSERT INTO `hosting_link_import`'));
    }

    // GTID consistency with statement-based binary logging refuses the temporary table (1787) without ending the
    // transaction, so that falls back to the direct join too
    public function testTheLinksFallBackWhenGtidRefusesTheTemporaryTable()
    {
        $pdo = self::importPdo(true, 1);
        $answers = new \ReflectionProperty($pdo, 'answers');
        $refused = new \PDOException('Statement violates GTID consistency');
        foreach (array(1787, 3748) as $code) {
            $pdo = self::importPdo(true, 1);
            $refused->errorInfo = array('HY000', $code, 'Statement violates GTID consistency');
            $answers->setValue($pdo, array('CREATE TEMPORARY TABLE' => $refused) + $answers->getValue($pdo));
            $this->assertSame(1, HostingLinkImport::import($pdo, 3, '2026-11-22 10:00:00', '2026-11-22'), (string) $code);
            $this->assertStringContainsString('INNER JOIN hosting_services AS hs ON hs.hosting_id = d.hosting_id',
                self::flat($pdo->statementsWith('SET d.service_id')[0]['sql']), (string) $code);
        }
    }

    // Any other error while making the lookup table is thrown, since it can have rolled back the upgrade's transaction
    // (a deadlock does), and carrying on would clear the old links with no services left to link them to
    public function testOtherLookupTableErrorsAreThrown()
    {
        $pdo = self::importPdo(true, 1);
        $answers = new \ReflectionProperty($pdo, 'answers');
        $deadlock = new \PDOException('Deadlock found when trying to get lock');
        $deadlock->errorInfo = array('40001', 1213, 'Deadlock found when trying to get lock');
        $answers->setValue($pdo, array('INSERT INTO `hosting_link_import`' => $deadlock) + $answers->getValue($pdo));
        try {
            HostingLinkImport::import($pdo, 3, '2026-11-22 10:00:00', '2026-11-22');
            $this->fail('The deadlock should have been thrown');
        } catch (\PDOException $e) {
            $this->assertSame(1213, $e->errorInfo[1]);
        }
        $this->assertSame(array(), $pdo->statementsWith('SET d.service_id'));
        $this->assertSame(array(), $pdo->statementsWith('SET hosting_id = 0'));
    }

    // With no eligible domain, no service or category is made, and the old links are still cleared
    public function testNoAccountsMeansNoServices()
    {
        $pdo = self::importPdo(true, 0);
        $this->assertSame(0, HostingLinkImport::import($pdo, 3, '2026-11-22 10:00:00', '2026-11-22'));
        $this->assertSame(array(), $pdo->statementsWith('INSERT INTO hosting_services'));
        $this->assertSame(array(), $pdo->statementsWith('categories'));
        $this->assertSame(array(), $pdo->statementsWith('SET d.service_id'));
        $last = end($pdo->log);
        $this->assertSame('UPDATE domains SET hosting_id = 0 WHERE hosting_id <> 0', trim(self::flat($last['sql'])));
    }

    // A missing Other type stops the upgrade (the transaction rolls back) rather than making services with no type
    public function testAMissingOtherTypeThrows()
    {
        $pdo = new RecordingPdo(array('INFORMATION_SCHEMA' => array(array(array('found' => 1))),
            'INSERT INTO hosting_provider_accounts' => array(array(array('row' => 1)))));
        $this->expectException(\RuntimeException::class);
        HostingLinkImport::import($pdo, 3, '2026-11-22 10:00:00', '2026-11-22');
    }

    // The drop after the commit runs only while the column is there, and never throws
    public function testTheColumnDropNeverThrows()
    {
        $pdo = new RecordingPdo(array('INFORMATION_SCHEMA' => array(array(array('found' => 1))),
            '@@SESSION.lock_wait_timeout' => array(array(array('wait' => 31536000)))));
        HostingLinkImport::dropLegacyColumn($pdo);
        $this->assertCount(1, $pdo->statementsWith('ALTER TABLE `domains` DROP `hosting_id`'));
        // It waits a few seconds for another connection's lock on domains, not a year, then puts the timeout back
        $sets = $pdo->statementsWith('SET SESSION lock_wait_timeout');
        $this->assertCount(2, $sets);
        $this->assertStringContainsString('= ' . HostingLinkImport::DROP_LOCK_WAIT, $sets[0]['sql']);
        $this->assertStringContainsString('= 31536000', $sets[1]['sql']);

        $pdo = new RecordingPdo(array('INFORMATION_SCHEMA' => array(array(array('found' => 0)))));
        HostingLinkImport::dropLegacyColumn($pdo);
        $this->assertSame(array(), $pdo->statementsWith('ALTER TABLE'));
        // The short wait is set before the column check, which can wait for a lock too
        $this->assertNotEmpty($pdo->statementsWith('SET SESSION lock_wait_timeout'));

        $pdo = new RecordingPdo(array('INFORMATION_SCHEMA' => array(array(array('found' => 1))),
            'ALTER TABLE' => new \PDOException('Lock wait timeout exceeded')));
        HostingLinkImport::dropLegacyColumn($pdo);
        $this->assertCount(1, $pdo->statementsWith('ALTER TABLE'));

        $pdo = new RecordingPdo(array('INFORMATION_SCHEMA' => new \PDOException('gone away')));
        HostingLinkImport::dropLegacyColumn($pdo);
        $this->assertSame(array(), $pdo->statementsWith('ALTER TABLE'));
    }

    // Each placeholder list matches any creation type row with its name, and every name exactly, all bound
    public function testThePlaceholderMatch()
    {
        $match = HostingLinkImport::placeholderMatch('h');
        $this->assertSame(2, substr_count($match['sql'], 'h.creation_type_id IN (SELECT ct.id FROM creation_types AS ct WHERE ct.name = :type'));
        $this->assertSame(2, substr_count($match['sql'], 'BINARY h.name IN ('));
        $this->assertSame('Installation', $match['params']['type0']);
        $this->assertSame('Queue', $match['params']['type1']);
        $names = count(Notice::PLACEHOLDER_PROVIDERS['Installation']) + count(Notice::PLACEHOLDER_PROVIDERS['Queue']);
        $this->assertCount($names + 2, $match['params']);
        $this->assertStringNotContainsString('[no hosting]', $match['sql']);
        $this->assertStringStartsWith('((', $match['sql']);
        $this->assertStringEndsWith('))', $match['sql']);
    }

    // The notice's count, the update block's delete, and the import use the same match, so they can't drift
    public function testAllThreeCallersShareTheMatch()
    {
        $columns = array(array('domains', 'hosting_id'), array('domains', 'account_id'), array('hosting', 'id'),
            array('hosting', 'name'), array('hosting', 'creation_type_id'), array('registrar_accounts', 'id'),
            array('registrar_accounts', 'owner_id'), array('owners', 'id'), array('creation_types', 'id'),
            array('creation_types', 'name'));
        $fake = new FakeDatabase(array('INFORMATION_SCHEMA' => $columns, 'SELECT COUNT(*)' => '2'));
        Notice::hostingLinkCount('4.26.0', $fake);
        $count = self::flat($fake->queriesWith('SELECT COUNT(*)')[0]);
        $match = HostingLinkImport::placeholderMatch('h')['sql'];
        $this->assertStringContainsString('FROM domains AS d ' . self::flat(HostingLinkImport::ELIGIBLE_JOINS)
            . ' WHERE d.hosting_id <> 0 AND NOT ' . $match, $count);

        $pdo = self::importPdo(true, 1);
        HostingLinkImport::import($pdo, 3, '2026-11-22 10:00:00', '2026-11-22');
        $this->assertStringContainsString('AND NOT ' . $match, self::flat($pdo->statementsWith('INSERT INTO hosting_provider_accounts')[0]['sql']));
        $this->assertStringContainsString('AND NOT ' . $match, self::flat($pdo->statementsWith('SET d.service_id')[0]['sql']));

        $block = self::upgradeBlock();
        $this->assertStringContainsString("\$placeholder_match = DomainMOD\\HostingLinkImport::placeholderMatch('h');", $block);
        $this->assertStringContainsString("WHERE \" . \$placeholder_match['sql'] . \"", $block);
        // The old one id per name lookup is gone
        $this->assertStringNotContainsString('getCreationTypeId($placeholder_type_name)', $block);
    }

    // The 4.26.0 to 4.27.0 block, from its version check to the end of the file
    private static function upgradeBlock()
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::UPDATE_FILE);
        $start = strpos($source, "if (\$current_db_version === '4.26.0') {");
        return substr($source, $start);
    }

    /*
     * The block keeps domains.hosting_id through the transaction (its default set to 0, not dropped), imports the links
     * after the service_id reset and the placeholder delete and before the Change History's start marker, and drops the
     * column after the commit
     */
    public function testTheUpdateBlocksOrder()
    {
        $block = self::upgradeBlock();
        $this->assertStringNotContainsString("\$domain_changes[] = \"DROP `hosting_id`\"", $block);
        $this->assertStringContainsString("\$domain_changes[] = \"ALTER `hosting_id` SET DEFAULT '0'\"", $block);

        $steps = array('$pdo->beginTransaction();', 'UPDATE domains SET service_id = 0 WHERE service_id <> 0',
            'DELETE FROM hosting', 'DomainMOD\HostingLinkImport::import($pdo,', 'ChangeHistory::writeStartMarker($pdo);',
            '$upgrade->database($new_version);', 'if ($pdo->InTransaction()) $pdo->commit();', 'DomainMOD\HostingLinkImport::dropLegacyColumn($pdo);');
        $positions = array();
        foreach ($steps as $step) {
            $this->assertSame(1, substr_count($block, $step), $step);
            $positions[] = strpos($block, $step);
        }
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        // The ALTER that sets the default comes before the transaction
        $this->assertLessThan($positions[0], strpos($block, "ALTER `hosting_id` SET DEFAULT '0'"));
    }

} //@formatter:on
