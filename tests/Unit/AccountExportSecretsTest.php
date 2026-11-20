<?php
/**
 * /tests/Unit/AccountExportSecretsTest.php
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * The Registrar Accounts export wrote each account's saved password, API key, and API secret into the file, and the SSL
 * Provider Accounts export wrote each saved password, although the edit forms have kept saved secrets out of the page
 * since 4.26.0. Both exports now leave those columns out, and their queries no longer read them. These tests read the
 * page source, since the pages aren't classes.
 */
class AccountExportSecretsTest extends TestCase
{
    public static function pages()
    {
        return array(
            'registrar accounts' => array('assets/registrar-accounts.php', array('password', 'api_key', 'api_secret')),
            'SSL provider accounts' => array('assets/ssl-accounts.php', array('password')),
        );
    }

    private function source($page)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $page);
        $this->assertNotFalse($source, $page);
        return $source;
    }

    #[DataProvider('pages')]
    public function testTheSecretsAreNeitherReadNorExported($page, array $columns)
    {
        $source = $this->source($page);

        foreach ($columns as $column) {
            $this->assertDoesNotMatchRegularExpression('/\b(ra|sa)\.' . $column . '\b/', $source, $page . ' reads ' . $column);
            $this->assertStringNotContainsString('$row->' . $column, $source, $page . ' exports ' . $column);
        }

        foreach (array('Password', 'API Key', 'API Secret') as $heading) {
            $this->assertStringNotContainsString("_('" . $heading . "')", $source, $page . ' has a ' . $heading . ' column');
        }
    }

    // The heading row and each account's row still line up, column for column
    #[DataProvider('pages')]
    public function testTheHeadingsAndRowsHaveTheSameColumns($page)
    {
        preg_match_all('/\$row_contents = array\(\n(.*?)\n\s*\);/s', $this->source($page), $matches);
        $this->assertCount(2, $matches[1], $page);

        list($headings, $row) = array_map(function ($list) {
            return count(preg_split('/,\n/', trim($list)));
        }, $matches[1]);
        $this->assertSame($headings, $row, $page);
    }

} //@formatter:on
