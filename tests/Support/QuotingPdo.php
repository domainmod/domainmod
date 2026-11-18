<?php
/**
 * /tests/Support/QuotingPdo.php
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

/*
 * A stand-in \PDO for SQL builders that take one but only call quote() on it. It overrides quote() to quote the way
 * MySQL's PDO driver does for these values (single quotes, with quotes and backslashes escaped), so the fragments read
 * as they would on a real connection, and it never calls the parent constructor, so it has no connection, and
 * anything that needs one fails.
 */
class QuotingPdo extends \PDO
{
    public function __construct()
    {
    }

    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        return "'" . addcslashes($string, "'\\") . "'";
    }

} //@formatter:on
