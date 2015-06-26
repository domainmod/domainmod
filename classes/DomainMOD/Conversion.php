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

    public function updateRates($connection, $default_currency, $user_id)
    {

        $result = $this->getActiveCurrencies($connection);

        while ($row = mysqli_fetch_object($result)) {

            $conversion_rate = $this->getConversionRate($row->currency, $default_currency);

            $system = new System();

            $sql = $system->buildSqlCurrencyExist($row->id, $user_id);
            $is_existing = $system->checkForRows($connection, $sql);

            $this->updateConversionRate($connection, $conversion_rate, $is_existing, $row->id, $user_id);

        }

        $result_message = 'Conversion Rates Updated<BR>';

        return $result_message;

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

    public function getConversionRate($from_currency, $to_currency)
    {

        $full_url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=" . $from_currency . $to_currency . "=X";
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

    public function updateConversionRate($connection, $conversion_rate, $is_existing, $currency_id, $user_id)
    {

        $time = new Timestamp();
        $timestamp = $time->time();

        if ($is_existing == '1') {

            $sql = "UPDATE currency_conversions
                    SET conversion = '" . $conversion_rate . "',
                        update_time = '" . $timestamp . "'
                    WHERE currency_id = '" . $currency_id . "'
                      AND user_id = '" . $user_id . "'";

        } else {

            $sql = "INSERT INTO currency_conversions
                    (currency_id, user_id, conversion, insert_time) VALUES
                    ('" . $currency_id . "', '" . $user_id . "', '" . $conversion_rate . "', '" . $timestamp . "')";

        }

        $result = mysqli_query($connection, $sql);

        return $result;

    }

}
