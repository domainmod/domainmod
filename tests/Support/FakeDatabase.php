<?php
/**
 * /tests/Support/FakeDatabase.php
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
 * A stand-in for a PDO connection and its statements, for code that only runs queries and reads their results. It
 * keeps every query's SQL, and answers each one with the first entry in $answers whose text is in the SQL (a value, or
 * a Throwable to throw), or null. It's set on an object's PDO property with Instance::withoutConstructor(), so no
 * database is needed.
 */
class FakeDatabase
{
    public $queries = array();
    protected $answers;
    protected $answer;

    // $answers maps a piece of SQL to its answer
    public function __construct(array $answers = array())
    {
        $this->answers = $answers;
    }

    public function query($sql)
    {
        return $this->prepare($sql);
    }

    public function prepare($sql)
    {
        $this->queries[] = $sql;
        $this->answer = null;
        foreach ($this->answers as $text => $answer) {
            if (strpos($sql, $text) === false) continue;
            if ($answer instanceof \Throwable) throw $answer;
            $this->answer = $answer;
            break;
        }
        return $this;
    }

    // The queries run so far whose SQL has $text in it
    public function queriesWith($text)
    {
        return array_values(array_filter($this->queries, function ($sql) use ($text) {
            return strpos($sql, $text) !== false;
        }));
    }

    public function bindValue($name, $value, $type = null)
    {
        return true;
    }

    public function execute($params = null)
    {
        return true;
    }

    public function fetchColumn()
    {
        return $this->answer;
    }

    public function fetch($mode = null)
    {
        return $this->answer;
    }

    public function fetchAll($mode = null)
    {
        return is_array($this->answer) ? $this->answer : array();
    }

    public function closeCursor()
    {
        return true;
    }

} //@formatter:on
