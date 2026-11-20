<?php
/**
 * /tests/Support/FakeWriteDatabase.php
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
 * FakeDatabase for code that writes: each executed statement is recorded with its SQL and bound values, rowCount()
 * answers from $row_counts (the first entry whose text is in the SQL, otherwise 1), and transactions are recorded as
 * 'BEGIN', 'COMMIT', and 'ROLLBACK' entries in $log, in order with the statements. It shows how code reacts to "no row
 * changed", not real database concurrency.
 */
class FakeWriteDatabase extends FakeDatabase
{
    public $log = array();
    public $row_counts;
    protected $sql = '';
    protected $bound = array();
    protected $in_transaction = false;

    public function __construct(array $answers = array(), array $row_counts = array())
    {
        parent::__construct($answers);
        $this->row_counts = $row_counts;
    }

    // A query runs straight away, so it's recorded like an executed statement
    public function query($sql)
    {
        $statement = $this->prepare($sql);
        $this->execute();
        return $statement;
    }

    public function prepare($sql)
    {
        $this->sql = $sql;
        $this->bound = array();
        return parent::prepare($sql);
    }

    public function bindValue($name, $value, $type = null)
    {
        $this->bound[$name] = $value;
        return true;
    }

    public function execute($params = null)
    {
        $this->log[] = array('sql' => $this->sql, 'values' => $this->bound);
        return true;
    }

    public function rowCount()
    {
        foreach ($this->row_counts as $text => $count) {
            if (strpos($this->sql, $text) !== false) return $count;
        }
        return 1;
    }

    public function beginTransaction()
    {
        $this->in_transaction = true;
        $this->log[] = 'BEGIN';
        return true;
    }

    public function commit()
    {
        $this->in_transaction = false;
        $this->log[] = 'COMMIT';
        return true;
    }

    public function rollBack()
    {
        $this->in_transaction = false;
        $this->log[] = 'ROLLBACK';
        return true;
    }

    public function inTransaction()
    {
        return $this->in_transaction;
    }

    // The executed statements whose SQL has $text in it, each as array('sql' => ..., 'values' => ...)
    public function executedWith($text)
    {
        return array_values(array_filter($this->log, function ($entry) use ($text) {
            return is_array($entry) && strpos($entry['sql'], $text) !== false;
        }));
    }

    // The transaction markers in order
    public function transactions()
    {
        return array_values(array_filter($this->log, 'is_string'));
    }

} //@formatter:on
