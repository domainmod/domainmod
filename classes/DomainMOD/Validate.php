<?php
/**
 * /classes/DomainMOD/Validate.php
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

class Validate
{

   public function tld($tld)
    {
        if(preg_match('/^[a-z\.\-]+$/i', $tld)) {

            return true;

        } else {

            return false;

        }

    }

   public function text($text)
    {
        /*
         * Strip whitespace from beginning and end of value
         * Make sure there's a value
         * Make sure the value isn't all whitespace
         * Make sure the value is 2 or more characters
         */

        $clean_text = trim($text);

        if($clean_text != '' && !ctype_space($clean_text) && strlen($clean_text) >= 2) {

            return true;

        } else {

            return false;

        }

    }

} //@formatter:on
