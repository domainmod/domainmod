<?php
/**
 * /classes/DomainMOD/CustomField.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
namespace DomainMOD;

class CustomField
{
    public $deeb;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
    }

    public function checkFieldFormat($input_field)
    {
        if (preg_match('/^[a-zA-Z_]+$/i', $input_field)) {

            return true;

        } else {

            return false;

        }
    }

    public function getCustomFields($table_name)
    {
        $result = $this->queryCustomFields($table_name);

        $columns_array = array();
        $count = 0;

        if ($result) {

            foreach ($result as $row) {

                $columns_array[$count++] = $row->field_name;

            }

        }

        return $columns_array;
    }

    public function queryCustomFields($table_name)
    {
        return $this->deeb->cnxx->query("
            SELECT field_name
            FROM " . $table_name . "
            ORDER BY `name` ASC")->fetchAll();
    }

    public function getCustomFieldsSql($table_name, $column_prefix)
    {
        $result = $this->queryCustomFields($table_name);

        $columns = '';

        if ($result) {

            foreach ($result as $row) {

                $columns .= ", " . $column_prefix . "." . $row->field_name;

            }

        }

        return $columns;
    }

} //@formatter:on
