<?php
/**
 * /tests/Unit/NoticeHostingLinksTest.php
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

use DomainMOD\Notice;
use DomainMOD\Tests\Support\FakeDatabase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/*
 * The upgrade notice's message that the 4.27.0 upgrade turns each domain's Web Hosting Provider link into a hosting
 * service (Notice): how many domains HostingLinkImport links (a real provider, and a registrar account and owner that
 * exist), read only while the column exists and the count can run, and the message without a number when the count
 * can't be worked out, so the Upgrade Database button always renders. The database is a FakeDatabase, which answers
 * the column check and the count.
 */
class NoticeHostingLinksTest extends TestCase
{
    // The column check's rows for a 4.26.0 database: everything the count needs
    const ALL_COLUMNS = array(array('domains', 'hosting_id'), array('domains', 'account_id'), array('hosting', 'id'),
        array('hosting', 'name'), array('hosting', 'creation_type_id'), array('registrar_accounts', 'id'),
        array('registrar_accounts', 'owner_id'), array('owners', 'id'), array('creation_types', 'id'),
        array('creation_types', 'name'));

    private static function database(array $columns, $count = '0')
    {
        return new FakeDatabase(array('INFORMATION_SCHEMA' => $columns, 'SELECT COUNT(*)' => $count));
    }

    public function testTheLinksToRealProvidersAreCounted()
    {
        $pdo = self::database(self::ALL_COLUMNS, '1234');

        $this->assertSame(1234, Notice::hostingLinkCount('4.26.0', $pdo));

        $count = $pdo->queriesWith('SELECT COUNT(*)');
        $this->assertCount(1, $count);
        $sql = preg_replace('/\s+/', ' ', $count[0]);
        // The same domains the import links: the provider, the registrar account, and its owner all exist
        $this->assertStringContainsString('FROM domains AS d INNER JOIN hosting AS h ON h.id = d.hosting_id INNER JOIN '
            . 'registrar_accounts AS ra ON ra.id = d.account_id INNER JOIN owners AS o ON o.id = ra.owner_id WHERE '
            . 'd.hosting_id <> 0 AND NOT ((', $sql);
        // Each placeholder list is matched by its creation type and the exact, byte for byte, name
        $this->assertSame(2, substr_count($sql, 'h.creation_type_id IN (SELECT ct.id FROM creation_types AS ct WHERE ct.name = :type'));
        $this->assertSame(2, substr_count($sql, 'BINARY h.name IN ('));
        $names = count(Notice::PLACEHOLDER_PROVIDERS['Installation']) + count(Notice::PLACEHOLDER_PROVIDERS['Queue']);
        $this->assertSame($names, substr_count($sql, ':name'));
        // Nothing is pasted into the SQL
        $this->assertStringNotContainsString('[no hosting]', $sql);
        // The column check comes first
        $this->assertStringContainsString('INFORMATION_SCHEMA', $pdo->queries[0]);
    }

    // The placeholders the update block deletes: [no hosting] in every language DomainMOD ships, made by the installer,
    // and [created by queue], always in English, made by the Domain Queue
    public function testThePlaceholderProviders()
    {
        $this->assertSame(array('Installation', 'Queue'), array_keys(Notice::PLACEHOLDER_PROVIDERS));
        $this->assertSame('[no hosting]', Notice::PLACEHOLDER_PROVIDERS['Installation'][0]);
        $this->assertContains('[kein Hosting]', Notice::PLACEHOLDER_PROVIDERS['Installation']);
        $this->assertCount(23, Notice::PLACEHOLDER_PROVIDERS['Installation']);
        $this->assertSame(array('[created by queue]'), Notice::PLACEHOLDER_PROVIDERS['Queue']);
    }

    // Nothing to say: a database at 4.27.0 or later (nothing is read), or one without the old column (the other domains
    // column the count reads is still there, so it's the old column that decides)
    public function testNoMessageWithoutTheOldColumn()
    {
        foreach (array('4.27.0', '4.27.01', '5.0.0') as $version) {
            $pdo = self::database(self::ALL_COLUMNS, '5');
            $this->assertSame(0, Notice::hostingLinkCount($version, $pdo), $version);
            $this->assertSame(array(), $pdo->queries);
        }

        $pdo = self::database(array_slice(self::ALL_COLUMNS, 1), '5');
        $this->assertSame(0, Notice::hostingLinkCount('4.26.0', $pdo));
        $this->assertSame(array(), $pdo->queriesWith('SELECT COUNT(*)'));
    }

    /*
     * The count can't be worked out when the placeholder match's columns are missing (a database older than 4.02 has
     * no creation types), or when a query fails, so the warning shows without a number
     */
    public function testACountThatCantBeWorkedOutIsNull()
    {
        foreach (array(array('hosting', 'creation_type_id'), array('creation_types', 'id'), array('creation_types', 'name'),
                     array('domains', 'account_id'), array('registrar_accounts', 'owner_id'), array('owners', 'id')) as $missing) {
            $pdo = self::database(array_values(array_filter(self::ALL_COLUMNS, function ($row) use ($missing) {
                return $row !== $missing;
            })), '5');
            $this->assertNull(Notice::hostingLinkCount('4.01.001', $pdo), implode('.', $missing));
            $this->assertSame(array(), $pdo->queriesWith('SELECT COUNT(*)'));
        }

        $this->assertNull(Notice::hostingLinkCount('4.26.0', new FakeDatabase(array('INFORMATION_SCHEMA' => new \PDOException('no access')))));
        $this->assertNull(Notice::hostingLinkCount('4.26.0', new FakeDatabase(array('INFORMATION_SCHEMA' => self::ALL_COLUMNS,
            'SELECT COUNT(*)' => new \PDOException('Unknown column')))));
        $this->assertNull(Notice::hostingLinkCount('4.26.0', self::database(self::ALL_COLUMNS, false)));
    }

    #[RunInSeparateProcess]
    public function testTheMessagesWords()
    {
        $this->assertSame('', Notice::hostingLinkMessage(0));
        $start = "This upgrade replaces each domain's Web Hosting Provider field with a Hosting Service. ";
        $end = ' Domains with the same provider and owner share one service. These services have no fees, and their renewal dates are placeholders, so check them under Hosting after the upgrade.';
        $this->assertSame($start . '1 domain will be linked to a new service named after its provider, in a new hosting account with the username "Imported".' . $end,
            Notice::hostingLinkMessage(1));
        $this->assertSame($start . '1,234 domains will each be linked to a new service named after its provider, in a new hosting account with the username "Imported".' . $end,
            Notice::hostingLinkMessage(1234));
        $this->assertSame($start . "Each domain will be linked to a new service named after its provider, in a new hosting account with the username \"Imported\", if that provider still exists and isn't one of DomainMOD's placeholders, such as [no hosting]." . $end,
            Notice::hostingLinkMessage(null));
        // Nothing is dropped any more, so there's no advice to back up the links
        $this->assertStringNotContainsString('back up', Notice::hostingLinkMessage(5));
    }

    // The message is a plain paragraph above the notice's one Critical Warning (it isn't a warning about lost data), and
    // the button is always there
    #[RunInSeparateProcess]
    public function testTheNoticeShowsTheMessageAboveTheWarning()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        define('SOFTWARE_TITLE', 'DomainMOD');

        $body = (new Notice())->upgradeBody('4.26.0', '', 'csrf_token=abc', self::database(self::ALL_COLUMNS, '3'));
        $this->assertSame(1, substr_count($body, 'alert alert-danger'));
        $message = strpos($body, '<p class="mt-4">This upgrade replaces each domain&#039;s Web Hosting Provider field with a Hosting Service. 3 domains will each be linked');
        $this->assertNotFalse($message);
        $this->assertLessThan(strpos($body, 'alert alert-danger'), $message);
        $this->assertStringContainsString('We strongly recommend that you back up', $body);
        $this->assertStringContainsString('Upgrade Database', $body);

        $fallback = (new Notice())->upgradeBody('4.26.0', '', 'csrf_token=abc', new FakeDatabase(array('INFORMATION_SCHEMA' => new \PDOException('no access'))));
        $this->assertStringContainsString('if that provider still exists and isn&#039;t one of DomainMOD&#039;s placeholders', $fallback);
        $this->assertStringContainsString('Upgrade Database', $fallback);

        $none = (new Notice())->upgradeBody('4.26.0', '', 'csrf_token=abc', self::database(self::ALL_COLUMNS, '0'));
        $this->assertStringNotContainsString('Web Hosting Provider', $none);
    }

} //@formatter:on
