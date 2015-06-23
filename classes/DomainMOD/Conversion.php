<?php
/**
 * /classes/DomainMOD/Conversion.php
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

class Conversion
{

    public function updateRates($connection, $timestamp, $default_currency, $user_id)
    {

        $result = $this->getActiveCurrencies($connection);

        while ($row = mysqli_fetch_object($result)) {

            $existing_currency = $this->checkExisting($connection, $row->id, $user_id);
            $conversion_rate = $this->getConversionRate($row->currency, $default_currency);
            $conversion_rate = $this->updateForDefault($row->currency, $default_currency, $conversion_rate);
            $this->updateConversionRate($connection, $timestamp, $conversion_rate, $row->id, $user_id, $existing_currency);

        }

        return "Conversion Rates Updated<BR>";

    }

    public function getActiveCurrencies($connection)
    {

        $sql = "SELECT id, currency
                FROM
                (   SELECT c.id, c.currency
                    FROM currencies AS c, fees AS f, domains AS d
                    WHERE c.id = f.currency_id
                      AND f.id = d.fee_id
                      AND d.active NOT IN ('0', '10')
                    GROUP BY c.currency
                    UNION
                    SELECT c.id, c.currency
                    FROM currencies AS c, ssl_fees AS f, ssl_certs AS sslc
                    WHERE c.id = f.currency_id
                      AND f.id = sslc.fee_id
                      AND sslc.active NOT IN ('0')
                    GROUP BY c.currency
                ) AS temp
                GROUP BY currency";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function checkExisting($connection, $currency_id, $user_id)
    {

        $sql = "SELECT id
                FROM currency_conversions
                WHERE currency_id = '" . $currency_id . "'
                  AND user_id = '" . $user_id . "'";
        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) == 0) {

            $existing_currency = "0";

        } else {

            $existing_currency = "1";

        }

        return $existing_currency;

    }

    public function getConversionRate($from_currency, $to_currency)
    {

        $full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from_currency . $to_currency ."=X";
        $api_call = @fopen($full_url, "r");
        $api_call_result = '';

        if ($api_call) {

            $api_call_result = fgets($api_call, 4096);
            fclose($api_call);

        }

        $api_call_split = explode(",", $api_call_result);
        $conversion_rate = $api_call_split[1];

        return $conversion_rate;

    }

    public function updateForDefault($current_currency, $default_currency, $conversion_rate)
    {

        if ($current_currency == $default_currency) {

            $conversion_rate = '1';

        } else {

            $conversion_rate = $conversion_rate;

        }

        return $conversion_rate;

    }

    public function updateConversionRate($connection, $timestamp, $conversion_rate, $currency_id, $user_id,
                                         $existing_currency)
    {

        if ($existing_currency == "1") {

            $sql = "UPDATE currency_conversions
                SET conversion = '" . $conversion_rate . "',
                    update_time = '" . $timestamp . "'
                WHERE currency_id = '" . $currency_id . "'
                  AND user_id = '" . $user_id . "'";
            $result = mysqli_query($connection, $sql);

        } else {

            $sql = "INSERT INTO currency_conversions
                (currency_id, user_id, conversion, insert_time) VALUES
                ('" . $currency_id . "', '" . $user_id . "', '" . $conversion_rate . "', '" . $timestamp . "')";
            $result = mysqli_query($connection, $sql);

        }

        return $result;

    }

}
