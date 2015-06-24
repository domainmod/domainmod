<?php
/**
 * /classes/DomainMOD/Domain.php
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

class Domain
{

    public function checkDomainFormat($input_domain)
    {

        /*
        if (preg_match('/^[A-Z0-9.-]+\.[A-Z0-9-]{2,50}$/i', $input_domain, $output_domain)) {

            return $output_domain;

        } else {

            return false;

        }
        */

        return $input_domain;

    }

    public function findInvalidDomains($lines)
    {

        $invalid_to_display = 5;
        $invalid_domains = 0;
        $invalid_count = 0;
        $result_message = '';

        while (list($key, $domain) = each($lines)) {

            if (!$this->checkDomainFormat($domain)) {

                if ($invalid_count < $invalid_to_display) {

                    $result_message .= "Line " . number_format($key + 1) . " contains an invalid domain<BR>";

                }

                $invalid_domains = 1;
                $invalid_count++;

            }

        }

        return array($invalid_to_display, $invalid_domains, $invalid_count, $result_message);

    }

}
