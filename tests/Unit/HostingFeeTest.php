<?php
/**
 * /tests/Unit/HostingFeeTest.php
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

use DomainMOD\HostingFee;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Hosting fees: the amounts as whole cents (rounded as the database rounds a decimal string into DECIMAL(10,2)), the
 * total check that keeps a service's cost inside DECIMAL(10,2), the lookup by provider, service type, and billing
 * term, and the fee edit page's delete refusal
 */
class HostingFeeTest extends TestCase
{
    public static function amounts()
    {
        return array(
            'blank' => array('', 0, '0.00'),
            'zero' => array('0', 0, '0.00'),
            'negative zero' => array('-0.00', 0, '0.00'),
            'whole' => array('12', 1200, '12.00'),
            'one decimal' => array('12.5', 1250, '12.50'),
            'two decimals' => array('0.29', 29, '0.29'),
            'no whole part' => array('.5', 50, '0.50'),
            'trailing period' => array('7.', 700, '7.00'),
            'rounds down' => array('12.344', 1234, '12.34'),
            'rounds half up' => array('12.345', 1235, '12.35'),
            'rounds up into the next whole' => array('0.995', 100, '1.00'),
            'leading zeros' => array('007.10', 710, '7.10'),
            'surrounding spaces' => array(' 3.20 ', 320, '3.20'),
            'the largest 32-bit int' => array('21474836.47', 2147483647, '21474836.47'),
            // A float on 32-bit PHP (the armhf Docker image), where these literals are floats too
            'past the 32-bit int range' => array('21474836.48', 2147483648, '21474836.48'),
            'rounds up past the 32-bit int range' => array('21474836.475', 2147483648, '21474836.48'),
            'the largest amount' => array('99999999.99', 9999999999, '99999999.99'),
            'an array' => array(array('5'), 0, '0.00'),
        );
    }

    #[DataProvider('amounts')]
    public function testAmountsAreWholeCents($typed, $cents, $decimal)
    {
        $this->assertSame($cents, HostingFee::toCents($typed));
        $this->assertSame($decimal, HostingFee::fromCents($cents));
    }

    public function testATotalThatFitsHasNoErrors()
    {
        $this->assertSame(array(), HostingFee::totalErrors(0, 9999999998, 1));
        $this->assertSame(array(), HostingFee::totalErrors(9999999999, 0, 0));
    }

    public function testATotalOverTheLargestAmountIsRefusedInCents()
    {
        // 99,999,999.99 plus 0.01 is one cent over, a sum a float can't be trusted to see
        $errors = HostingFee::totalErrors(0, 9999999999, 1);
        $this->assertSame(array('new_renewal_fee'), array_keys($errors));

        $errors = HostingFee::totalErrors(9999999999, 0, 1);
        $this->assertSame(array('new_initial_fee'), array_keys($errors));

        $errors = HostingFee::totalErrors(5000000000, 5000000000, 5000000000);
        $this->assertSame(array('new_renewal_fee', 'new_initial_fee'), array_keys($errors));
        $this->assertStringContainsString('99999999.99', $errors['new_renewal_fee']);
    }

    // The pages add two amounts' cents before fromCents(), which on 32-bit PHP gives a float past its int range
    public function testCentsPastThe32BitIntRangeAreExactInEveryForm()
    {
        $this->assertSame('99999999.99', HostingFee::fromCents(9999999999.0));
        $this->assertSame('99999999.99', HostingFee::fromCents('9999999999'));
        $this->assertSame('21474836.48', HostingFee::fromCents(HostingFee::toCents('21474836.47') + HostingFee::toCents('0.01')));
        $this->assertSame('99999999.99', HostingFee::fromCents(HostingFee::toCents('50000000.00') + HostingFee::toCents('49999999.99')));
        $this->assertSame(array(), HostingFee::totalErrors('0', '9999999998', '1'));
        $this->assertSame(array('new_renewal_fee'), array_keys(HostingFee::totalErrors('0', '9999999999', '1')));
        $this->assertSame(array('new_initial_fee'), array_keys(HostingFee::totalErrors(9999999999.0, 0, 1.0)));
    }

    public function testTheCheckedCentsAreTheStoredValues()
    {
        // 12.345 + 0.005 as floats is 12.35, but each is stored rounded (12.35 and 0.01), so the cost is 12.36
        $this->assertSame('12.36', HostingFee::fromCents(HostingFee::toCents('12.345') + HostingFee::toCents('0.005')));
    }

    public function testADeleteIsRefusedWhileAnyServiceUsesTheFee()
    {
        $this->assertSame('', HostingFee::deleteRefusal(0));
        $this->assertSame('', HostingFee::deleteRefusal('0'));
        $this->assertSame("1 hosting service uses this fee, so it can't be deleted.", HostingFee::deleteRefusal(1));
        $this->assertSame("1,204 hosting services use this fee, so it can't be deleted.", HostingFee::deleteRefusal('1204'));
    }

    public function testAFeeIsFoundByAllThreeKeys()
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite is not loaded');

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE hosting_fees (id INTEGER PRIMARY KEY, hosting_id INTEGER, type_id INTEGER, billing_term INTEGER)");
        $pdo->exec("INSERT INTO hosting_fees (id, hosting_id, type_id, billing_term) VALUES
            (4, 1, 2, 12), (5, 1, 2, 1), (6, 1, 3, 12), (7, 2, 2, 12)");

        $this->assertSame(4, HostingFee::findId($pdo, 1, 2, 12));
        $this->assertSame(5, HostingFee::findId($pdo, 1, 2, 1));
        $this->assertSame(6, HostingFee::findId($pdo, 1, 3, 12));
        $this->assertSame(7, HostingFee::findId($pdo, '2', '2', '12'));
        // Any key that doesn't match finds nothing, so a monthly service never takes the yearly fee
        $this->assertSame(0, HostingFee::findId($pdo, 1, 2, 3));
        $this->assertSame(0, HostingFee::findId($pdo, 2, 3, 12));
        $this->assertSame(0, HostingFee::findId($pdo, 3, 2, 12));
    }

    public function testAnUnknownLockIsIgnored()
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite is not loaded');

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE hosting_fees (id INTEGER PRIMARY KEY, hosting_id INTEGER, type_id INTEGER, billing_term INTEGER)");
        $pdo->exec("INSERT INTO hosting_fees (id, hosting_id, type_id, billing_term) VALUES (9, 1, 1, 12)");

        // Only the two locking reads are added to the query, so anything else can't reach the SQL
        $this->assertSame(9, HostingFee::findId($pdo, 1, 1, 12, '; DROP TABLE hosting_fees'));
    }

} //@formatter:on
