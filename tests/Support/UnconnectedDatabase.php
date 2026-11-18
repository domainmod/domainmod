<?php
/**
 * /tests/Support/UnconnectedDatabase.php
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
namespace DomainMOD\Tests\Support;

use DomainMOD\Database;

/*
 * Puts a Database object that never connected (its connection is null) in place of the shared one, so a constructor
 * that only fetches Database::getInstance() (such as Currency's, which DomainListColumns' constructor runs) can be used
 * without a database. Anything that really queries fails at once on the null connection. Each test that installs it
 * removes it again in tearDown(), so the next test starts with no shared object.
 */
class UnconnectedDatabase
{
    public static function install()
    {
        self::set(Instance::withoutConstructor(Database::class));
    }

    public static function remove()
    {
        self::set(null);
    }

    private static function set($value)
    {
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, $value);
    }

} //@formatter:on
