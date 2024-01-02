<?php
/**
 * /classes/DomainMOD/Format.php
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

class Format
{
    public $sanitize;

    public function __construct()
    {
        $this->sanitize = new Sanitize();
    }

    public function stripSpacing($input)
    {
        return trim(preg_replace("/^\n+|^[\t\s]*\n+/m", "", $input));
    }

    public function replaceBreaks($input_notes)
    {
        $output_notes = trim($input_notes);
        $output_notes = str_replace("\n\n", "<BR><BR>", $output_notes);
        return str_replace("\n", "<BR>", $output_notes);
    }

    public function cleanAndSplitDomains($raw_domain_list)
    {
        $clean_domain_list = $this->stripSpacing($raw_domain_list);
        $domain_list = explode("\r\n", $clean_domain_list);
        $new_domain_list = array();
        foreach ($domain_list as $value) {
            $new_domain_list[] = $this->sanitize->text($this->stripSpacing($value));
        }
        return array_unique($new_domain_list);
    }

    public function formatForMysql($domain_list)
    {
        $new_domain_list = array();
        foreach ($domain_list as $value) {
            $new_domain_list[] = $value;
        }
        $list_formatted = implode("\r\n", $new_domain_list);
        $list_formatted = "'" . $list_formatted;
        $list_formatted = $list_formatted . "'";
        $list_formatted = preg_replace("/\r\n/", "','", $list_formatted);
        $list_formatted = str_replace(" ", "", $list_formatted);
        return trim($list_formatted);
    }

    public function obfusc($input)
    {
        return str_repeat("*", strlen($input));
    }
} //@formatter:on
