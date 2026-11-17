<?php
/**
 * /classes/DomainMOD/CustomField.php
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

    /*
     * The name a custom field's input is posted under. Custom fields are posted in their own array, keyed by field name,
     * so a field named like one of the page's own settings (such as notes) can't take its place.
     */
    public static function inputName($field_name)
    {
        return 'custom_fields[' . $field_name . ']';
    }

    // The posted custom field values, keyed by field name. A value that's missing or isn't text is left empty.
    public static function postedValues($field_names, $post)
    {
        $posted = $post['custom_fields'] ?? array();
        if (!is_array($posted)) $posted = array();

        $values = array();

        foreach ($field_names as $field_name) {

            $value = $posted[$field_name] ?? '';
            $values[$field_name] = is_string($value) ? $value : '';

        }

        return $values;
    }

    /*
     * Why a custom field's value can't be saved, or an empty string when it can. A Date (type 4) takes a real date
     * (YYYY-MM-DD) and a Time Stamp (type 5) a real date and time (YYYY-MM-DD HH:MM:SS), and both can be left blank.
     * Other types take any text.
     */
    public static function valueError($type_id, $value)
    {
        $type_id = (int) $type_id;
        $value = (string) $value;

        if (($type_id !== 4 && $type_id !== 5) || $value === '') return '';

        if ($type_id === 4) {

            // checkDateFormat()'s pattern lets a final line break through, so the length is checked too
            $date = new Date();
            return strlen($value) === 10 && $date->checkDateFormat($value) ? '' : _('Enter a date in the format YYYY-MM-DD, or leave it blank');

        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})\z/', $value, $parts) &&
            checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) && (int) $parts[4] < 24 && (int) $parts[5] < 60 &&
            (int) $parts[6] < 60) {

            return '';

        }

        return _('Enter a time stamp in the format YYYY-MM-DD HH:MM:SS, or leave it blank');
    }

    /*
     * Marks each custom field whose value can't be saved, with the reason under the field ($field_types is the field
     * names and their type ids, $values the values from postedValues()), and returns whether they can all be saved.
     */
    public static function markInvalidValues($form, $field_types, $values)
    {
        $valid = true;

        foreach ($field_types as $field_name => $type_id) {

            $error = self::valueError($type_id, $values[$field_name] ?? '');

            if ($error !== '') {

                $form->addFieldError(self::inputName($field_name), $error);
                $valid = false;

            }

        }

        return $valid;
    }

    // The custom fields of a table (domain_fields or ssl_cert_fields) and their type ids, keyed by field name
    public function getFieldTypes($table_name)
    {
        $field_types = array();

        $result = $this->deeb->cnxx->query("
            SELECT field_name, type_id
            FROM " . $table_name . "
            ORDER BY `name` ASC")->fetchAll();

        foreach ($result as $row) {

            $field_types[$row->field_name] = (int) $row->type_id;

        }

        return $field_types;
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

    // The database field name of a custom field, by its id, or false when there's no such field
    public function getFieldName($table_name, $field_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT field_name
            FROM " . $table_name . "
            WHERE id = :field_id");
        $stmt->bindValue('field_id', (int) $field_id, \PDO::PARAM_INT);
        $stmt->execute();
        $field_name = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $field_name;
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
