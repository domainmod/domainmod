<?php
/**
 * /tests/Support/RecordingPdo.php
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
 * A stand-in \PDO for code that runs real statements on one, without a database. Like QuotingPdo, it never calls the
 * PDO constructor, so it has no connection, and it quotes the way MySQL's PDO driver does. It keeps every statement
 * it's sent, with the values bound to it ($log, each an array('sql' => ..., 'params' => ...)). Each query is answered
 * by the first entry in $answers whose text is in the SQL, and any other query gets no rows. An answer can be a list
 * of pages, each a list of rows (arrays), given to successive queries, with the last page repeating once the others
 * are used. It can also be a callable, given the SQL and its parameters, that returns the rows, or a Throwable, thrown
 * when the query runs. Transactions and exec() are recorded as statements too.
 */
class RecordingPdo extends QuotingPdo
{
    public $log = array();
    protected $answers;
    protected $in_transaction = false;

    // $answers maps a piece of SQL to its answer
    public function __construct(array $answers = array())
    {
        $this->answers = $answers;
    }

    public function prepare(string $query, array $options = array()): \PDOStatement|false
    {
        return new RecordingStatement($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $statement = new RecordingStatement($this, $query);
        $statement->execute();
        return $statement;
    }

    public function exec(string $statement): int|false
    {
        $this->answer($statement, array());
        return 0;
    }

    public function beginTransaction(): bool
    {
        $this->answer('START TRANSACTION', array());
        $this->in_transaction = true;
        return true;
    }

    public function commit(): bool
    {
        $this->answer('COMMIT', array());
        $this->in_transaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->answer('ROLLBACK', array());
        $this->in_transaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->in_transaction;
    }

    // Records a statement and works out its rows (called by RecordingStatement::execute())
    public function answer($sql, array $params)
    {
        $this->log[] = array('sql' => $sql, 'params' => $params);
        foreach ($this->answers as $text => $answer) {
            if (strpos($sql, $text) === false) continue;
            if ($answer instanceof \Throwable) throw $answer;
            if (is_callable($answer)) return $answer($sql, $params);
            $page = count($answer) > 1 ? array_shift($answer) : reset($answer);
            $this->answers[$text] = $answer;
            return $page === false ? array() : $page;
        }
        return array();
    }

    // The statements sent so far whose SQL has $text in it
    public function statementsWith($text)
    {
        return array_values(array_filter($this->log, function ($entry) use ($text) {
            return strpos($entry['sql'], $text) !== false;
        }));
    }

} //@formatter:on
