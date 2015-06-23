<?php
/**
 * /classes/DomainMOD/CustomField.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
?>
<?php
namespace DomainMOD;

class CustomField
{

    public function checkFieldFormat($input_field)
    {

        if (preg_match('/^[a-zA-Z_]+$/i', $input_field, $output_field)) {

            return $output_field;

        } else {

            return false;

        }

    }

    public function queryCustomFields($connection, $table_name)
    {

        $sql = "SELECT field_name FROM " . $table_name . " ORDER BY `name` ASC";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function getCustomFields($connection, $table_name)
    {

        $result = $this->queryCustomFields($connection, $table_name);

        $columns_array = array();

        $count = 0;

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $columns_array[$count++] = $row->field_name;

            }

        }

        return $columns_array;

    }

    public function getCustomFieldsSql($connection, $table_name, $column_prefix)
    {

        $result = $this->queryCustomFields($connection, $table_name);

        $columns = '';

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $columns .= ", " . $column_prefix . "." . $row->field_name;

            }

        }

        return $columns;

    }

}
