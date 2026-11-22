<?php
/**
 * /tests/Unit/SslQueueSameAsDomainTest.php
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

use DomainMOD\Database;
use DomainMOD\SslQueue;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The owner, category, and IP address an SSL Queue certificate gets. "Same as the Domain" (SslQueue::SAME_AS_DOMAIN,
 * stored as 0) takes the domain's own when the certificate is saved with a domain, and the SSL defaults of the user who
 * queued it when it has none. A specific choice is used for every host, as before. The issuer's account is matched
 * under the owner the certificate ends up with. The tick box on the domain paste pages always uses the domain's.
 */
class SslQueueSameAsDomainTest extends TestCase
{

    private function root()
    {
        return __DIR__ . '/../..';
    }

    private function queue($pdo)
    {
        $database = Instance::withoutConstructor(Database::class, array('cnxx' => $pdo));

        return Instance::withoutConstructor(SslQueue::class, array('deeb' => $database));
    }

    private function call($queue, $method, ...$arguments)
    {
        return (new \ReflectionMethod(SslQueue::class, $method))->invoke($queue, ...$arguments);
    }

    /*
     * A database whose single-id checks (defaultId()'s "WHERE id = :id") find an id only when it's in $rows[table], whose
     * MIN(id) for a table is the lowest of its $rows (null for none), and whose other queries are answered as
     * FakeDatabase answers them
     */
    private function database(array $answers, array $rows)
    {
        return new class($answers, $rows) extends FakeDatabase {

            public $user_ids = array();
            private $rows;
            private $sql = '';
            private $id = null;

            public function __construct(array $answers, array $rows)
            {
                parent::__construct($answers);
                $this->rows = $rows;
            }

            public function prepare($sql)
            {
                $this->sql = $sql;
                $this->id = null;
                return parent::prepare($sql);
            }

            public function bindValue($name, $value, $type = null)
            {
                if ($name === 'id') $this->id = $value;
                if ($name === 'user_id') $this->user_ids[] = $value;
                return true;
            }

            public function fetchColumn()
            {
                foreach ($this->rows as $table => $ids) {
                    if (strpos($this->sql, 'FROM `' . $table . '`') === false) continue;
                    if (strpos($this->sql, 'MIN(id)') !== false) return $ids ? (string) min($ids) : null;
                    return in_array((int) $this->id, $ids, true) ? (string) $this->id : false;
                }
                return parent::fetchColumn();
            }

        };
    }

    // Greg's case (2026-11-21): greg.ca's certificate takes the domain's owner, category, and IP address
    public function testSameAsTheDomainTakesTheDomainsOwn()
    {
        $row = (object) array('owner_id' => 0, 'cat_id' => 0, 'ip_id' => 0);
        $domain = (object) array('domain' => 'greg.ca', 'owner_id' => 7, 'cat_id' => 12, 'ip_id' => 5);
        $fallback = array('owner_id' => 2, 'cat_id' => 3, 'ip_id' => 4);

        $this->assertSame(array('owner_id' => 7, 'cat_id' => 12, 'ip_id' => 5), SslQueue::savedChoices($row, $domain, $fallback));
    }

    public function testACertificateWithNoDomainTakesTheSslDefaults()
    {
        $row = (object) array('owner_id' => 0, 'cat_id' => 0, 'ip_id' => 0);
        $fallback = array('owner_id' => 2, 'cat_id' => 3, 'ip_id' => 4);

        $this->assertSame($fallback, SslQueue::savedChoices($row, null, $fallback));
    }

    public function testAChosenValueIsUsedWhateverTheDomain()
    {
        $domain = (object) array('domain' => 'greg.ca', 'owner_id' => 7, 'cat_id' => 12, 'ip_id' => 5);
        $fallback = array('owner_id' => 2, 'cat_id' => 3, 'ip_id' => 4);

        $this->assertSame(array('owner_id' => 9, 'cat_id' => 10, 'ip_id' => 11),
            SslQueue::savedChoices((object) array('owner_id' => 9, 'cat_id' => 10, 'ip_id' => 11), $domain, $fallback));
        // each menu on its own
        $this->assertSame(array('owner_id' => 7, 'cat_id' => 10, 'ip_id' => 5),
            SslQueue::savedChoices((object) array('owner_id' => 0, 'cat_id' => 10, 'ip_id' => 0), $domain, $fallback));
        $this->assertSame(array('owner_id' => 9, 'cat_id' => 3, 'ip_id' => 11),
            SslQueue::savedChoices((object) array('owner_id' => 9, 'cat_id' => 0, 'ip_id' => 11), null, $fallback));
    }

    public function testTheSslDefaultsAreTheUsersThenTheSystemsThenTheFirstRow()
    {
        $rows = array('owners' => array(2, 5, 8), 'categories' => array(3, 6), 'ip_addresses' => array(4, 9));

        // the user's owner exists, their category is gone (so the system's is used), and neither IP address exists
        $pdo = $this->database(array(
            'FROM user_settings' => (object) array('default_owner_ssl' => '8', 'default_category_ssl' => '99', 'default_ip_address_ssl' => '0'),
            'FROM settings' => (object) array('default_owner_ssl' => '5', 'default_category_ssl' => '6', 'default_ip_address_ssl' => '77')), $rows);

        $this->assertSame(array('owner_id' => 8, 'cat_id' => 6, 'ip_id' => 4), $this->call($this->queue($pdo), 'fallbackChoices', 1));
        $this->assertStringContainsString('WHERE user_id = :user_id', $pdo->queriesWith('FROM user_settings')[0]);
        $this->assertSame(array(1), $pdo->user_ids);

        // a user who's gone
        $pdo = $this->database(array('FROM user_settings' => false,
            'FROM settings' => (object) array('default_owner_ssl' => '5', 'default_category_ssl' => '0', 'default_ip_address_ssl' => '9')), $rows);

        $this->assertSame(array('owner_id' => 5, 'cat_id' => 3, 'ip_id' => 9), $this->call($this->queue($pdo), 'fallbackChoices', 44));
        $this->assertSame(array(44), $pdo->user_ids);
    }

    public function testTheTickBoxUsesTheDomainsOwnAndTheDefaultType()
    {
        $rows = array('ssl_cert_types' => array(1, 2), 'owners' => array(1), 'categories' => array(1), 'ip_addresses' => array(1));
        $pdo = $this->database(array(), $rows);
        $session = $_SESSION ?? null;
        $_SESSION['s_default_ssl_type'] = 2;

        try {
            $choices = $this->call($this->queue($pdo), 'defaultChoices');
        } finally {
            if ($session === null) unset($_SESSION); else $_SESSION = $session;
        }

        $this->assertSame(array('owner_id' => SslQueue::SAME_AS_DOMAIN, 'account_id' => 0, 'type_id' => 2,
            'cat_id' => SslQueue::SAME_AS_DOMAIN, 'ip_id' => SslQueue::SAME_AS_DOMAIN), $choices);
        $this->assertSame(0, SslQueue::SAME_AS_DOMAIN);
    }

    public function testTheTickBoxQueuesNothingWhenATableItNeedsIsEmpty()
    {
        foreach (array('owners', 'categories', 'ip_addresses') as $empty) {

            $rows = array('ssl_cert_types' => array(1), 'owners' => array(1), 'categories' => array(1), 'ip_addresses' => array(1));
            $rows[$empty] = array();

            $this->assertNull($this->call($this->queue($this->database(array(), $rows)), 'defaultChoices'), $empty);

        }
    }

    // The save reads the domain's values under its lock, checks and uses the saved values, and matches the issuer's
    // account under the saved owner
    public function testTheSaveUsesTheSavedChoices()
    {
        $source = file_get_contents($this->root() . '/classes/DomainMOD/SslQueue.php');

        $this->assertStringContainsString("\$domain = \$this->lockRow('domains', 'id', (int) \$row->domain_id, 'domain, owner_id, cat_id, ip_id');", $source);
        $this->assertStringContainsString("\$saved = self::savedChoices(\$row, \$domain, \$fallback);", $source);
        $this->assertStringContainsString("array('categories', \$saved['cat_id'], \$row->cat_id), array('ip_addresses', \$saved['ip_id'], \$row->ip_id)) as \$check) {", $source);
        $this->assertStringContainsString("\$ssl_assets->resolve(\$details['issuer'], \$saved['owner_id'], (int) \$row->created_by);", $source);
        $this->assertStringContainsString("\$stmt->bindValue('ip_id', \$saved['ip_id'], \\PDO::PARAM_INT);", $source);
        $this->assertStringContainsString("\$stmt->bindValue('cat_id', \$saved['cat_id'], \\PDO::PARAM_INT);", $source);
        $this->assertStringContainsString("? \$this->fallbackChoices((int) \$row->created_by) : array();", $source);
        $this->assertStringNotContainsString("(int) \$row->owner_id, (int) \$row->created_by)", $source);
    }

    public function testTheAddFormStartsOnSameAsTheDomain()
    {
        $source = file_get_contents($this->root() . '/queue/add/ssl.php');

        foreach (array('owner', 'cat', 'ip') as $menu) {
            $this->assertStringContainsString("\$new_" . $menu . "_id = DomainMOD\\SslQueue::SAME_AS_DOMAIN;", $source);
        }
        foreach (array('owner' => 'owners', 'cat' => 'categories', 'ip' => 'ip_addresses') as $menu => $rows) {
            $this->assertStringContainsString("echo \$form->showDropdownOption(DomainMOD\\SslQueue::SAME_AS_DOMAIN, _('Same as the Domain'), \$same_selected(\$" . $rows . ", \$new_" . $menu . "_id));", $source);
        }
        $this->assertStringContainsString("!\$choice_exists(\$owners, \$new_owner_id)", $source);
        $this->assertStringContainsString("!\$choice_exists(\$categories, \$new_cat_id)", $source);
        $this->assertStringContainsString("!\$choice_exists(\$ip_addresses, \$new_ip_id)", $source);
        // the type has no "Same as the Domain"
        $this->assertStringContainsString("!\$row_exists(\$types, \$new_type_id)", $source);
    }

    // A pasted row the tick box upgrades becomes the upgrading user's, so their SSL defaults fill in its choices
    public function testAnUpgradedRowBecomesTheUpgradingUsers()
    {
        $pdo = new class extends FakeDatabase {

            public $bound = array();

            public function bindValue($name, $value, $type = null)
            {
                $this->bound[$name] = $value;
                return true;
            }

            public function rowCount()
            {
                return 1;
            }

        };
        $choices = array('owner_id' => 0, 'account_id' => 0, 'provider_id' => 0, 'type_id' => 2, 'cat_id' => 0, 'ip_id' => 0);

        $this->assertTrue($this->call($this->queue($pdo), 'upgradeRow', 14, 'example.com', $choices, 6, '2026-11-21 10:00:00'));
        $this->assertStringContainsString('created_by = :created_by', $pdo->queries[0]);
        $this->assertSame(6, $pdo->bound['created_by']);
        $this->assertSame(14, $pdo->bound['id']);

        // Retry Failed's upgrade hands the row to the user of the failed tick-box row whose choices it copies
        $source = file_get_contents($this->root() . '/classes/DomainMOD/SslQueue.php');
        $this->assertStringContainsString("\$this->upgradeRow(\$action[1], \$wait_domain, \$choices, (int) \$row->created_by, \$now)", $source);
        $this->assertStringContainsString("\$this->upgradeRow(\$action[1], \$host, \$choices, (int) \$created_by, \$now)", $source);
    }

    // A value the user didn't choose (the domain's, or a default) that's gone by the save is tried again, not failed
    public function testAGoneValueThatWasntChosenIsTriedAgain()
    {
        $source = file_get_contents($this->root() . '/classes/DomainMOD/SslQueue.php');

        $this->assertStringContainsString("array(array('owners', \$saved['owner_id'], \$row->owner_id), array('ssl_cert_types', \$row->type_id, \$row->type_id),", $source);
        $this->assertStringContainsString("return ((int) \$check[2] === self::SAME_AS_DOMAIN) ? 'save_failed' : 'stale_choice';", $source);
        // save_failed is tried again, and stale_choice fails the row
        $this->assertContains('save_failed', SslQueue::$retry_codes);
        $this->assertContains('stale_choice', SslQueue::$failure_codes);
        $this->assertSame(array('processing' => 0, 'attempts' => 1, 'message' => 'save_failed'),
            SslQueue::afterResult((object) array('attempts' => 0), 'save_failed'));
        $this->assertSame(1, SslQueue::afterResult((object) array('attempts' => 0), 'stale_choice')['failed']);
    }

    // A missing or malformed menu value is refused, not taken for "Same as the Domain"
    public function testTheAddFormRefusesAMissingOrMalformedChoice()
    {
        $source = file_get_contents($this->root() . '/queue/add/ssl.php');
        $this->assertSame(1, preg_match('/\$posted_choice = (function \(\$name\) \{.*?\n    \});/s', $source, $match));
        $posted_choice = eval('return ' . $match[1] . ';');

        $saved_post = $_POST;

        try {
            $_POST = array('new_owner_id' => '0', 'new_cat_id' => '12', 'new_ip_id' => 'x', 'new_bad' => array('3'));
            $this->assertSame(0, $posted_choice('new_owner_id'));
            $this->assertSame(12, $posted_choice('new_cat_id'));
            $this->assertSame(-1, $posted_choice('new_ip_id'));
            $this->assertSame(-1, $posted_choice('new_bad'));
            $this->assertSame(-1, $posted_choice('new_missing'));
        } finally {
            $_POST = $saved_post;
        }

        foreach (array('owner', 'cat', 'ip') as $menu) {
            $this->assertStringContainsString("\$new_" . $menu . "_id = \$posted_choice('new_" . $menu . "_id');", $source);
        }
    }

    // "Same as the Domain" is accepted only while the menu has rows, since a certificate with no domain takes one
    public function testTheAddFormAcceptsSameAsTheDomainOnlyWithRows()
    {
        $source = file_get_contents($this->root() . '/queue/add/ssl.php');
        $this->assertSame(1, preg_match('/\$row_exists = (function \(\$rows, \$id\) \{.*?\n\});/s', $source, $row_match));
        $this->assertSame(1, preg_match('/\$choice_exists = (function \(\$rows, \$id\) use \(\$row_exists\) \{.*?\n\});/s', $source, $choice_match));
        $row_exists = eval('return ' . $row_match[1] . ';');
        $choice_exists = eval('return ' . $choice_match[1] . ';');

        $rows = array((object) array('id' => 3), (object) array('id' => 8));
        $this->assertTrue($choice_exists($rows, 0));
        $this->assertTrue($choice_exists($rows, 8));
        $this->assertFalse($choice_exists($rows, 5));
        $this->assertFalse($choice_exists($rows, -1));
        $this->assertFalse($choice_exists(array(), 0));

        // once it's refused for an empty menu, only the empty option is selected
        $this->assertSame(1, preg_match('/\$same_selected = (function \(\$rows, \$id\) use \(\$choice_exists\) \{.*?\n\});/s', $source, $same_match));
        $same_selected = eval('return ' . $same_match[1] . ';');
        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $this->assertSame(-1, $same_selected(array(), 0));
            $this->assertSame(0, $same_selected($rows, 0));
            $this->assertSame(8, $same_selected($rows, 8));
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $this->assertSame(0, $same_selected(array(), 0));
        } finally {
            if ($method === null) unset($_SERVER['REQUEST_METHOD']); else $_SERVER['REQUEST_METHOD'] = $method;
        }
    }

    public function testTheTickBoxPagesPassNoOwner()
    {
        foreach (array('queue/add/rdap.php', 'queue/add/step-two/index.php') as $page) {
            $this->assertStringContainsString("\$ssl_queue->queueDomainLookups(\$domain_array, \$_SESSION['s_user_id']);",
                file_get_contents($this->root() . '/' . $page), $page);
        }
    }

} //@formatter:on
