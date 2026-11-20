<?php
/**
 * /tests/Support/FieldRows.php
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
 * A list's custom fields as CustomField::getFilterFields() returns them, for tests: one row for each column named in a
 * SELECT fragment such as ", dfd.note, dfd.renew_date", in that order, with ids from 1. A field the session's custom
 * field list ($settings' s_cdf_data or s_csf_data) knows takes its name and type from there, and any other is a Text
 * field named after its column.
 */
class FieldRows
{
    public static function of($sql, array $settings = array())
    {
        $known = array();
        foreach ((array) ($settings['s_cdf_data'] ?? $settings['s_csf_data'] ?? array()) as $field) {
            $known[$field['field'] ?? ''] = $field;
        }

        preg_match_all('/[a-z]+\.([A-Za-z_]+)/', (string) $sql, $matches);
        $rows = array();
        foreach ($matches[1] as $n => $name) {
            $rows[] = array('id' => $n + 1, 'name' => (string) ($known[$name]['name'] ?? $name), 'field_name' => $name,
                'type_id' => (int) ($known[$name]['type_id'] ?? 2));
        }
        return $rows;
    }

} //@formatter:on
