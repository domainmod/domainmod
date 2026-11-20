<?php
/**
 * /tests/Support/RecordingStatement.php
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
 * RecordingPdo's statements: bound values are kept until the statement runs, and its rows come from RecordingPdo's
 * answers. Rows are arrays, given as objects by default, as the project's connection does (PDO::FETCH_OBJ).
 */
class RecordingStatement extends \PDOStatement
{
    protected $recording_pdo;
    protected $sql;
    protected $bound = array();
    protected $rows = array();
    protected $row_count = 0;

    public function __construct(RecordingPdo $pdo, $sql)
    {
        $this->recording_pdo = $pdo;
        $this->sql = $sql;
    }

    public function bindValue(string|int $param, mixed $value, int $type = \PDO::PARAM_STR): bool
    {
        $this->bound[ltrim((string) $param, ':')] = $value;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        // As in PDO, values passed here replace any bound before, for this run and the ones after it
        if ($params !== null) $this->bound = $params;
        $this->rows = array_values($this->recording_pdo->answer($this->sql, $this->bound));
        $this->row_count = count($this->rows);
        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
                          int $cursorOffset = 0): mixed
    {
        if (!$this->rows) return false;
        return $this->shaped(array_shift($this->rows), $mode);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if (!$this->rows) return false;
        $row = array_values(array_shift($this->rows));
        // A NULL value is null, as in PDO, which keeps false for "no more rows"
        if (!array_key_exists($column, $row)) throw new \ValueError('Invalid column index');
        return $row[$column];
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = $this->rows;
        $this->rows = array();
        if ($mode === \PDO::FETCH_KEY_PAIR) {
            $pairs = array();
            foreach ($rows as $row) {
                // As in PDO, which refuses any other number of columns
                if (count($row) !== 2) throw new \PDOException('PDO::FETCH_KEY_PAIR needs exactly two columns');
                $row = array_values($row);
                $pairs[$row[0]] = $row[1];
            }
            return $pairs;
        }
        if ($mode === \PDO::FETCH_COLUMN) {
            $column = $args[0] ?? 0;
            return array_map(function ($row) use ($column) {
                return array_values($row)[$column];
            }, $rows);
        }
        $shaped = array();
        foreach ($rows as $row) {
            $shaped[] = $this->shaped($row, $mode);
        }
        return $shaped;
    }

    public function closeCursor(): bool
    {
        $this->rows = array();
        return true;
    }

    // The rows the query returned, read or not, as with a buffered MySQL query
    public function rowCount(): int
    {
        return $this->row_count;
    }

    // A row as the fetch mode gives it: an array for PDO::FETCH_ASSOC, otherwise an object
    protected function shaped(array $row, $mode)
    {
        return $mode === \PDO::FETCH_ASSOC ? $row : (object) $row;
    }

} //@formatter:on
