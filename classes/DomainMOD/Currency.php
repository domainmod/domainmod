<?php
/**
 * /_includes/classes/DomainMOD/Currency.php
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

class Currency
{

    public function convertAndFormat($amount, $conversion, $symbol, $order, $space)
    {

        if ($conversion == "") {

            if ($order == "0") {

                if ($space == "0") {

                    $output = $symbol . number_format($amount, 2, '.', ',');

                } else {

                    $output = $symbol . " " . number_format($amount, 2, '.', ',');

                }

            } else {

                if ($space == "0") {

                    $output = number_format($amount, 2, '.', ',') . $symbol;

                } else {

                    $output = number_format($amount, 2, '.', ',') . " " . $symbol;

                }

            }

        } else {

            if ($order == "0") {

                if ($space == "0") {

                    $temp_converted_fee = $amount * $conversion;
                    $output = $symbol . number_format($temp_converted_fee, 2, '.', ',');

                } else {

                    $temp_converted_fee = $amount * $conversion;
                    $output = $symbol . " " . number_format($temp_converted_fee, 2, '.', ',');

                }

            } else {

                if ($space == "0") {

                    $temp_converted_fee = $amount * $conversion;
                    $output = number_format($temp_converted_fee, 2, '.', ',') . $symbol;

                } else {

                    $temp_converted_fee = $amount * $conversion;
                    $output = number_format($temp_converted_fee, 2, '.', ',') . " " . $symbol;

                }

            }

        }
        
        return $output;

    }

}
