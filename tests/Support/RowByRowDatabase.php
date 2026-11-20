<?php
/**
 * /tests/Support/RowByRowDatabase.php
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
 * FakeDatabase, with fetch() giving the answer's rows one at a time (then false), as a statement does, so code that
 * reads its rows in a loop can be tested. For a query whose SQL has one of the $row_by_row texts in it, fetchAll()
 * gives nothing, so code that goes back to reading all of that query's rows at once fails its tests.
 */
class RowByRowDatabase extends FakeDatabase
{
    private $rows = array();
    private $row_by_row;
    private $current_row_by_row = false;

    public function __construct(array $answers = array(), array $row_by_row = array())
    {
        parent::__construct($answers);
        $this->row_by_row = $row_by_row;
    }

    public function prepare($sql)
    {
        parent::prepare($sql);
        $this->rows = is_array($this->answer) ? array_values($this->answer) : array();
        $this->current_row_by_row = false;
        foreach ($this->row_by_row as $text) {
            if (strpos($sql, $text) !== false) $this->current_row_by_row = true;
        }
        return $this;
    }

    public function fetch($mode = null)
    {
        return $this->rows ? array_shift($this->rows) : false;
    }

    public function fetchAll($mode = null)
    {
        return $this->current_row_by_row ? array() : parent::fetchAll($mode);
    }

} //@formatter:on
