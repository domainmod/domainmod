<?php
/**
 * /classes/DomainMOD/CustomFieldFilter.php
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

/*
 * A list's custom field filters (the Domains list's and the SSL Certificates list's), given the list's custom fields
 * (CustomField::getFilterFields()) and its custom field data table's alias (dfd or sslfd). Each field has a value key,
 * cf_<field_name>, and every field but a check box an empty key, cfe_<field_name> (empty or filled):
 * - Check Box: 1 (Yes) or 0 (No).
 * - Text, Text Area, and URL: text the value contains, on one line, up to MAX_TEXT_LENGTH characters.
 * - Date and Time Stamp: a range of days, YYYY-MM-DD - YYYY-MM-DD, which never matches the unset placeholder dates.
 * A value the list can use wins over its field's empty key, so a field has one filter at most.
 *
 * Column names only ever come from the fields given, checked again here, and values are always bound parameters. The
 * class reads no session and prints nothing on its own, so the lists' data endpoints (and later an API) can use it.
 */
class CustomFieldFilter
{
    // The longest text a "contains" filter takes, the Keyword Search box's own maxlength
    const MAX_TEXT_LENGTH = 100;

    // The most bytes the filters' keys and values take once URL-encoded, so every address the list makes stays short
    const MAX_ENCODED_BYTES = 2000;

    // The unset placeholders of Date and Time Stamp fields (see Time::isUnset())
    const UNSET_DATES = array('1970-01-01', '0000-00-00');
    const UNSET_STAMPS = array('1970-01-01 00:00:00', '0000-00-00 00:00:00');

    protected $fields = array();
    protected $alias;

    // $fields are CustomField::getFilterFields()'s rows, and $alias the list's custom field data table's alias
    public function __construct(array $fields, $alias)
    {
        foreach ($fields as $field) {
            if (!preg_match('/^[a-zA-Z_]+$/', (string) ($field['field_name'] ?? ''))) continue;
            if (!in_array((int) ($field['type_id'] ?? 0), array(1, 2, 3, 4, 5, 6), true)) continue;
            $this->fields[$field['field_name']] = array('id' => (int) ($field['id'] ?? 0), 'name' => (string) ($field['name'] ?? ''),
                'field_name' => $field['field_name'], 'type_id' => (int) $field['type_id']);
        }
        $this->alias = (string) $alias;
    }

    // Whether a request key is a custom field filter's key (cf_ or cfe_)
    public static function isFilterKey($key)
    {
        return is_string($key) && (strpos($key, 'cf_') === 0 || strpos($key, 'cfe_') === 0);
    }

    // Whether the list has any custom fields to filter on
    public function hasFields()
    {
        return $this->fields !== array();
    }

    /*
     * The checked filters in a request ($_GET or $_POST), keyed by field name, in field order, each as array('key' =>
     * its request key, 'value' => its value). Bad values are dropped (see the class comment), and a value drops its
     * field's empty key. Once the filters' keys and values together take more than MAX_ENCODED_BYTES URL-encoded, that
     * filter and every one after it are dropped, and $dropped is set to true.
     */
    public function normalize(array $request, &$dropped = null)
    {
        $dropped = false;
        $date = new Date();
        $state = array();
        $query = array();

        foreach ($this->fields as $name => $field) {

            $value = $request['cf_' . $name] ?? null;
            $value = is_string($value) ? $value : '';
            $empty = $request['cfe_' . $name] ?? null;
            $empty = is_string($empty) ? $empty : '';
            $filter = null;

            switch ($field['type_id']) {
                case 1:
                    if ($value === '0' || $value === '1') $filter = array('key' => 'cf_' . $name, 'value' => $value);
                    break;
                case 2:
                case 3:
                case 6:
                    // Valid UTF-8 with no control characters (such as a line break), as the panel's text box keeps it, so
                    // applying the panel again can't change it
                    if (trim($value) !== '' && !preg_match('/[\x00-\x1F\x7F]/', $value) && mb_check_encoding($value, 'UTF-8')) {
                        if (mb_strlen($value, 'UTF-8') <= self::MAX_TEXT_LENGTH) {
                            $filter = array('key' => 'cf_' . $name, 'value' => $value);
                        }
                    }
                    break;
                case 4:
                case 5:
                    if (preg_match('/^\d{4}-\d{2}-\d{2} - \d{4}-\d{2}-\d{2}$/', $value)) {
                        list($start, $end) = $date->splitAndCheckRange($value);
                        if ($start !== '' && $start <= $end) $filter = array('key' => 'cf_' . $name, 'value' => $value);
                    }
                    break;
            }

            // An empty key counts only when there's no value the list can use (a blank one, or one it drops, such as a
            // half-typed date range), as the panel's script treats it, and never for a check box (one is never empty)
            if ($filter === null && $field['type_id'] !== 1 && ($empty === 'empty' || $empty === 'filled')) {
                $filter = array('key' => 'cfe_' . $name, 'value' => $empty);
            }

            if ($filter === null) continue;

            $query[$filter['key']] = $filter['value'];
            if (strlen(http_build_query($query)) > self::MAX_ENCODED_BYTES) {
                $dropped = true;
                break;
            }
            $state[$name] = $filter;

        }

        return $state;
    }

    // The request keys and values for a state (see normalize()), for an address or a form
    public static function requestKeys(array $state)
    {
        $keys = array();
        foreach ($state as $filter) {
            $keys[$filter['key']] = $filter['value'];
        }
        return $keys;
    }

    // Every key in a request that starts with cf_ or cfe_, current field or not, plus cff and cfk when they're there
    public static function incomingKeys(array $request)
    {
        $keys = array();
        foreach (array_keys($request) as $key) {
            if (self::isFilterKey($key) || $key === 'cff' || $key === 'cfk') $keys[] = (string) $key;
        }
        return $keys;
    }

    // Whether a request has any custom field filter key
    public static function hasFilterKeys(array $request)
    {
        foreach (array_keys($request) as $key) {
            if (self::isFilterKey($key)) return true;
        }
        return false;
    }

    // A request without any custom field filter key
    public static function withoutFilterKeys(array $request)
    {
        foreach (array_keys($request) as $key) {
            if (self::isFilterKey($key)) unset($request[$key]);
        }
        return $request;
    }

    /*
     * A short hash of every current field's id and type (cff), sorted by id, so it changes when a field is added,
     * deleted, or replaced, but not when one is renamed
     */
    public function fieldsKey()
    {
        $ids = array();
        foreach ($this->fields as $field) {
            $ids[$field['id']] = $field['id'] . ':' . $field['type_id'];
        }
        ksort($ids);
        return substr(sha1(implode(',', $ids)), 0, 12);
    }

    /*
     * A short hash of a list of request key names (cfk): the ones that start with cf_ or cfe_, sorted, each with its
     * field's current id and type, or "missing" when no current field has that name. It reads no values, so a filter
     * of 0 counts as much as any other.
     */
    public function filterKey(array $keys)
    {
        $parts = array();
        foreach ($keys as $key) {
            if (!self::isFilterKey($key)) continue;
            $name = strpos($key, 'cfe_') === 0 ? substr($key, 4) : substr($key, 3);
            $field = $this->fields[$name] ?? null;
            $parts[$key] = $key . '=' . ($field === null ? 'missing' : $field['id'] . ':' . $field['type_id']);
        }
        ksort($parts, SORT_STRING);
        return substr(sha1(implode(',', $parts)), 0, 12);
    }

    /*
     * Whether a list page's request ($_GET, or $_POST for the panel's form) may use its custom field filter keys: one
     * with cfk (an export, a pager link, or a Reload link) is checked by cfk alone, one with only cff (the form, a chip,
     * or the expand=1 link) by cff, and one with neither (a hand-written link) is taken as it is. A request without any
     * custom field filter key always may.
     */
    public function requestOk(array $request)
    {
        if (!self::hasFilterKeys($request)) return true;
        if (isset($request['cfk'])) {
            return is_string($request['cfk']) && hash_equals($this->filterKey(array_keys($request)), $request['cfk']);
        }
        if (isset($request['cff'])) {
            return is_string($request['cff']) && hash_equals($this->fieldsKey(), $request['cff']);
        }
        return true;
    }

    /*
     * Whether a data endpoint's request ($_GET) matches the fields: one with neither a custom field filter key nor cfk
     * does, one with filter keys but no cfk doesn't, and otherwise cfk must match filterKey() of its keys
     */
    public function endpointOk(array $request)
    {
        $has_keys = self::hasFilterKeys($request);
        if (!$has_keys && !isset($request['cfk'])) return true;
        if (!isset($request['cfk']) || !is_string($request['cfk'])) return false;
        return hash_equals($this->filterKey(array_keys($request)), $request['cfk']);
    }

    /*
     * A data endpoint's checks of its request against a read of the list's custom fields, in order, for the 409 it
     * answers with: 'filter' when the custom field filters' key doesn't match (see endpointOk()), so a filtered field
     * deleted or replaced since the page loaded is never filtered on, then 'layout' when the table's column layout key
     * ($layout, from its request) isn't $layout_now (the list's columns built from the same read), or null when both
     * pass. $alias is the list's custom field data alias.
     */
    public static function endpointConflict(array $request, array $fields, $alias, $layout_now)
    {
        if (!(new self($fields, $alias))->endpointOk($request)) return 'filter';
        if (!is_string($request['layout'] ?? null) || $request['layout'] !== $layout_now) return 'layout';
        return null;
    }

    // The value " AND ..." SQL and its parameters for one filter, with parameter names from $prefix and $n
    protected function condition(array $field, array $filter, $column, $prefix, &$n)
    {
        $params = array();
        $name = function ($value) use ($prefix, &$n, &$params) {
            $param = $prefix . $n++;
            $params[$param] = $value;
            return ':' . $param;
        };
        $type = $field['type_id'];
        $value = $filter['value'];
        $is_date = $type === 4 || $type === 5;
        $unset = "'" . implode("', '", $type === 5 ? self::UNSET_STAMPS : self::UNSET_DATES) . "'";

        if ($filter['key'] === 'cfe_' . $field['field_name']) {
            if ($is_date) {
                $sql = $value === 'empty' ? $column . " IN (" . $unset . ")" : $column . " NOT IN (" . $unset . ")";
            } else {
                $sql = $value === 'empty' ? $column . " = ''" : $column . " != ''";
            }
        } elseif ($type === 1) {
            $sql = $column . " = " . $name((int) $value);
        } elseif ($is_date) {
            list($start, $end) = (new Date())->splitAndCheckRange($value);
            if ($type === 5) {
                $start .= ' 00:00:00';
                $end .= ' 23:59:59';
            }
            $sql = $column . " >= " . $name($start) . " AND " . $column . " <= " . $name($end) . " AND " . $column
                . " NOT IN (" . $unset . ")";
        } else {
            $sql = $column . " LIKE " . $name(ListQuery::likeValue($value)) . " ESCAPE '\\\\'";
        }

        return array('sql' => " AND " . $sql, 'params' => $params);
    }

    /*
     * The filters in a state (see normalize()) as " AND ..." SQL on the list's custom field data row and its bound
     * parameters (cff0, cff1, and so on, each used once), as array('sql' => ..., 'params' => ...)
     */
    public function conditions(array $state)
    {
        return $this->conditionsOn($state, $this->alias, 'cff');
    }

    protected function conditionsOn(array $state, $alias, $prefix)
    {
        $sql = '';
        $params = array();
        $n = 0;
        foreach ($state as $name => $filter) {
            $field = $this->fields[$name] ?? null;
            if ($field === null) continue;
            $part = $this->condition($field, $filter, $alias . '.`' . $field['field_name'] . '`', $prefix, $n);
            $sql .= $part['sql'];
            $params += $part['params'];
        }
        return array('sql' => $sql, 'params' => $params);
    }

    /*
     * The same filters for a query that doesn't join the custom field data (the filter menus), as " AND <outer_id> IN
     * (SELECT ...)". $data_table and $id_column are the list's FIELD_DATA_TABLE and FIELD_DATA_ID, and $outer_id is the
     * asset's id column as the menu's query names it, all written in the code, never taken from a request.
     */
    public function subquery(array $state, $data_table, $id_column, $outer_id)
    {
        $inner = $this->conditionsOn($state, 'cfd', 'cfs');
        if ($inner['sql'] === '') return array('sql' => '', 'params' => array());

        return array('sql' => " AND " . $outer_id . " IN (SELECT cfd." . $id_column . " FROM " . $data_table . " AS cfd WHERE 1 = 1"
            . $inner['sql'] . ")", 'params' => $inner['params']);
    }

    // A filter's plain-text label, as its chip and the export show it
    protected function label(array $field, array $filter)
    {
        if ($filter['key'] === 'cfe_' . $field['field_name']) {
            return $filter['value'] === 'empty' ? _('Empty') : _('Filled in');
        }
        if ($field['type_id'] === 1) return $filter['value'] === '1' ? _('Yes') : _('No');
        if ($field['type_id'] === 4 || $field['type_id'] === 5) {
            list($start, $end) = (new Date())->splitAndCheckRange($filter['value']);
            return sprintf(_('%1$s to %2$s'), $start, $end);
        }
        return '"' . $filter['value'] . '"';
    }

    // Each filter in a state as array('name' => the field's plain-text name, 'label' => its plain-text label)
    public function describe(array $state)
    {
        $filters = array();
        foreach ($state as $name => $filter) {
            $field = $this->fields[$name] ?? null;
            if ($field === null) continue;
            $filters[$filter['key']] = array('name' => FilterToolbar::decode($field['name']),
                'label' => $this->label($field, $filter));
        }
        return $filters;
    }

    // One chip for each filter in a state
    public function addChips(FilterToolbar $toolbar, array $state)
    {
        foreach ($this->describe($state) as $key => $filter) {
            $toolbar->addFilter($key, $state[substr($key, strpos($key, '_') + 1)]['value'], '', $filter['label'],
                $filter['name']);
        }
    }

    /*
     * The panel's Custom Fields section: a disclosure, open when any custom field filter is on, with one group of
     * controls for each field, and the field set's key (cff) for the page's check. Empty when the list has no custom
     * fields.
     */
    public function panelHtml(Form $form, array $state)
    {
        if (!$this->fields) return '';

        $esc = function ($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); };
        $active = count($state);
        $summary = _('Custom Fields');
        if ($active > 0) $summary .= ' (' . sprintf(ngettext('%s active', '%s active', $active), number_format($active)) . ')';

        ob_start(); ?>
        <details class="domainmod-filter-custom-fields mb-3"<?php if ($active > 0) echo ' open'; ?>>
            <summary><?php echo $esc($summary); ?></summary>
            <input type="hidden" name="cff" value="<?php echo $esc($this->fieldsKey()); ?>"><?php
            foreach ($this->fields as $name => $field) {
                $filter = $state[$name] ?? null;
                $value = $filter !== null && $filter['key'] === 'cf_' . $name ? $filter['value'] : '';
                $empty = $filter !== null && $filter['key'] === 'cfe_' . $name ? $filter['value'] : ''; ?>
            <fieldset class="domainmod-filter-custom-field">
                <legend class="form-label"><?php echo $esc(FilterToolbar::decode($field['name'])); ?></legend><?php
                if ($field['type_id'] === 1) {
                    echo $form->showDropdownTop('cf_' . $name, _('Value'), '', '', '');
                    echo $form->showDropdownOption('', _('Any'), $value);
                    echo $form->showDropdownOption('1', _('Yes'), $value);
                    echo $form->showDropdownOption('0', _('No'), $value);
                    echo $form->showDropdownBottom('');
                } else {
                    if ($field['type_id'] === 4 || $field['type_id'] === 5) {
                        // Left to right on every page, as Expiring Between is (see Form::dirAttribute()), and marked as a
                        // range, so js/filter-auto-apply.js counts only a whole range as a value, even without the picker
                        echo $form->showInputText('cf_' . $name, _('Between'), '', $value, '23', '', '',
                            '<div class="domainmod-filter-range">', '</div>', '', 'ltr');
                    } else {
                        echo $form->showInputText('cf_' . $name, _('Contains'), '', $value, (string) self::MAX_TEXT_LENGTH, '', '', '', '');
                    }
                    echo $form->showDropdownTop('cfe_' . $name, _('Filled In'), '', '', '');
                    echo $form->showDropdownOption('', _('Any'), $empty);
                    echo $form->showDropdownOption('filled', _('Filled in'), $empty);
                    echo $form->showDropdownOption('empty', _('Empty'), $empty);
                    echo $form->showDropdownBottom('');
                } ?>
            </fieldset><?php
            } ?>
        </details><?php
        return ob_get_clean();
    }

    // The request keys of the list's Date and Time Stamp fields, for the date range picker (see $date_range_fields)
    public function dateKeys()
    {
        $keys = array();
        foreach ($this->fields as $name => $field) {
            if ($field['type_id'] === 4 || $field['type_id'] === 5) $keys[] = 'cf_' . $name;
        }
        return $keys;
    }

    /*
     * Whether the fields as read now (inside a snapshot or a transaction) are still the ones this filter was built
     * from, by their fieldsKey()
     */
    public function sameFields(array $fields_now)
    {
        return hash_equals($this->fieldsKey(), (new self($fields_now, $this->alias))->fieldsKey());
    }

    // Whether an error is the database's "unknown column" (SQLSTATE 42S22) or "table definition has changed" (1412)
    public static function fieldsChangedError(\Throwable $e)
    {
        if (!$e instanceof \PDOException) return false;
        $info = $e->errorInfo ?? null;
        return $e->getCode() === '42S22' || (is_array($info) && (($info[0] ?? '') === '42S22' || (int) ($info[1] ?? 0) === 1412));
    }

} //@formatter:on
