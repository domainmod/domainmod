<?php
/**
 * /tests/Unit/ListExportOrderTest.php
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

use DomainMOD\DomainList;
use DomainMOD\SslList;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Export and the Raw List's order, and the keyset that starts each chunk after the last row of the one before
class ListExportOrderTest extends TestCase
{
    public static function lists()
    {
        return array(
            'domains' => array(DomainList::class, array('d.expiry_date', 'd.domain', 'd.id'),
                ' ORDER BY d.expiry_date ASC, d.domain ASC, d.id ASC'),
            'ssl certificates' => array(SslList::class, array('sslc.expiry_date', 'sslc.name', 'sslc.id'),
                ' ORDER BY sslc.expiry_date ASC, sslc.name ASC, sslc.id ASC'),
        );
    }

    #[DataProvider('lists')]
    public function testTheOrderIsUnchangedAndBuiltFromTheColumns($class, $columns, $order_sql)
    {
        $this->assertSame($columns, $class::EXPORT_ORDER);
        $this->assertSame($order_sql, $class::EXPORT_ORDER_SQL);
        $this->assertSame(' ORDER BY ' . implode(' ASC, ', $class::EXPORT_ORDER) . ' ASC', $class::EXPORT_ORDER_SQL);
    }

    #[DataProvider('lists')]
    public function testTheFirstChunkHasNoCondition($class)
    {
        $this->assertSame(array('sql' => '', 'params' => array()), $class::keysetSql(null));
    }

    #[DataProvider('lists')]
    public function testALaterChunkStartsAfterTheLastRow($class, $columns)
    {
        $keyset = $class::keysetSql(array('2027-03-04', 'example.com', '42'));
        list($expiry, $name, $id) = $columns;

        $this->assertSame(" AND (" . $expiry . " > :ks_e OR (" . $expiry . " = :ks_e2 AND (" . $name . " > :ks_d OR ("
            . $name . " = :ks_d2 AND " . $id . " > :ks_i))))", $keyset['sql']);
        $this->assertSame(array('ks_e' => '2027-03-04', 'ks_e2' => '2027-03-04', 'ks_d' => 'example.com',
            'ks_d2' => 'example.com', 'ks_i' => '42'), $keyset['params']);
    }

    public function testTheKeysetTakesTheRowsValuesInOrderWhateverTheirKeys()
    {
        $keyset = DomainList::keysetSql(array('expiry_date' => '2027-03-04', 'domain' => 'a.com', 'id' => 7));
        $this->assertSame(array('2027-03-04', 'a.com', 7), array($keyset['params']['ks_e'], $keyset['params']['ks_d'],
            $keyset['params']['ks_i']));
    }

    public static function incompleteRows()
    {
        return array('no id' => array(array('2027-03-04', 'www')), 'a null id' => array(array('2027-03-04', 'www', null)),
            'too many values' => array(array('2027-03-04', 'www', 1, 2)), 'empty' => array(array()));
    }

    #[DataProvider('incompleteRows')]
    public function testAnIncompleteRowIsNeverUsed($last)
    {
        $this->expectException(\InvalidArgumentException::class);
        SslList::keysetSql($last);
    }

    public function testEveryNameInTheSqlIsBoundOnceAndNoneClashWithTheSearchBox()
    {
        $keyset = SslList::keysetSql(array('2027-03-04', 'www', 1));
        preg_match_all('/:([a-z0-9_]+)/', $keyset['sql'], $names);
        $this->assertSame(array_keys($keyset['params']), $names[1]);

        // A search of every kind of column, with as many words as the box allows (one of them a domain in its other
        // spelling, so the IDN form is bound too), binds none of the same names
        $columns = array('lower' => array('d.domain'), 'typed' => array('d.expiry_date'), 'idn' => array('d.domain'),
            'encoded' => array('o.name'));
        $search = Instance::withoutConstructor(DomainList::class)->searchSql(DomainList::searchTokens(
            'xn--bcher-kva.de b c d e f g h i j'), $columns);
        $this->assertContains('%bücher.de%', $search['params']);
        $this->assertSame(array(), array_intersect_key($keyset['params'], $search['params']));
    }

} //@formatter:on
