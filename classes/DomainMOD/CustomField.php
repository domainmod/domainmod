<?php
/**
 * /classes/DomainMOD/CustomField.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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

    public function getTypeId($table_name, $field_id)
    {
        return $this->deeb->cnxx->query("
            SELECT type_id
            FROM " . $table_name . "
            WHERE ID = " . $field_id)->fetchColumn();
    }

    public function getName($table_name, $field_id)
    {
        return $this->deeb->cnxx->query("
            SELECT `name`
            FROM " . $table_name . "
            WHERE ID = " . $field_id)->fetchColumn();
    }

    public function getType($type_id)
    {
        return $this->deeb->cnxx->query("
            SELECT `name`
            FROM custom_field_types
            WHERE ID = " . $type_id)->fetchColumn();
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

    public function getCDFData()
    {
        $custom_domain_field_data = array();

        $result = $this->deeb->cnxx->query("
            SELECT name, field_name, type_id
            FROM domain_fields
            ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);

        if ($result) {

            $count = 0;

            foreach ($result as $field_values) {

                $custom_domain_field_data[$count]['name'] = $field_values['name'];
                $custom_domain_field_data[$count]['field'] = $field_values['field_name'];
                $custom_domain_field_data[$count]['type_id'] = $field_values['type_id'];
                $custom_domain_field_data[$count]['display_field'] = 'dispcdf_' . $field_values['field_name'];

                $stmt = $this->deeb->cnxx->prepare("
                    SELECT  dispcdf_" . $field_values['field_name'] . "
                    FROM user_settings
                    WHERE user_id = :user_id");
                $stmt->bindValue('user_id', $_SESSION['s_user_id'], \PDO::PARAM_INT);
                $stmt->execute();
                $custom_domain_field_data[$count]['value'] = $stmt->fetchColumn();

                $count++;

            }

        }

        return $custom_domain_field_data;

    }

    public function getCSFData()
    {
        $custom_ssl_field_data = array();

        $result = $this->deeb->cnxx->query("
            SELECT name, field_name, type_id
            FROM ssl_cert_fields
            ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);

        if ($result) {

            $count = 0;

            foreach ($result as $field_values) {

                $custom_ssl_field_data[$count]['name'] = $field_values['name'];
                $custom_ssl_field_data[$count]['field'] = $field_values['field_name'];
                $custom_ssl_field_data[$count]['type_id'] = $field_values['type_id'];
                $custom_ssl_field_data[$count]['display_field'] = 'dispcsf_' . $field_values['field_name'];

                $stmt = $this->deeb->cnxx->prepare("
                    SELECT  dispcsf_" . $field_values['field_name'] . "
                    FROM user_settings
                    WHERE user_id = :user_id");
                $stmt->bindValue('user_id', $_SESSION['s_user_id'], \PDO::PARAM_INT);
                $stmt->execute();
                $custom_ssl_field_data[$count]['value'] = $stmt->fetchColumn();

                $count++;

            }

        }

        return $custom_ssl_field_data;

    }

} //@formatter:on
