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

        $result = $this->getActiveDomainCurrencies($connection);
        $exclude_string = $this->cycleDomainCurrencies($connection, $timestamp, $default_currency, $result, $user_id);
        $result = $this->getActiveSslCurrencies($connection, $exclude_string);
        $result_message = $this->cycleSslCurrencies($connection, $timestamp, $default_currency, $result, $user_id);

        return $result_message;

    }

    public function getActiveDomainCurrencies($connection)
    {

        $sql = "SELECT c.id, c.currency
                FROM currencies AS c, fees AS f, domains AS d
                WHERE c.id = f.currency_id
                  AND f.id = d.fee_id
                  AND d.active NOT IN ('0', '10')
                GROUP BY c.currency";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function getActiveSslCurrencies($connection, $exclude_string)
    {

        $sql = "SELECT c.id, c.currency
                FROM currencies AS c, ssl_fees AS f, ssl_certs AS sslc
                WHERE c.id = f.currency_id
                  AND f.id = sslc.fee_id
                  AND sslc.active NOT IN ('0')
                  AND c.currency NOT IN (" . $exclude_string . ")
                GROUP BY c.currency";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function cycleDomainCurrencies($connection, $timestamp, $default_currency, $result, $user_id)
    {

        $exclude_string = '';

        while ($row = mysqli_fetch_object($result)) {

            $existing_currency = $this->checkExisting($connection, $row->id, $user_id);
            $exclude_string = $this->buildExcludeString($exclude_string, $row->currency);

            if ($existing_currency == "1") {

                if ($row->currency == $default_currency) {

                    $this->updateConversionRate($connection, $timestamp, '1', $row->id, $user_id);

                } else {

                    $conversion_rate = $this->getConversionRate($row->currency, $default_currency);
                    $this->updateConversionRate($connection, $timestamp, $conversion_rate, $row->id, $user_id);

                }

            } else {

                if ($row->currency == $default_currency) {

                    $this->insertConversionRate($connection, $timestamp, '1', $row->id, $user_id);

                } else {

                    $conversion_rate = $this->getConversionRate($row->currency, $default_currency);
                    $this->insertConversionRate($connection, $timestamp, $conversion_rate, $row->id, $user_id);

                }

            }

        }

        return $exclude_string;

    }

    public function cycleSslCurrencies($connection, $timestamp, $default_currency, $result, $user_id)
    {

        while ($row = mysqli_fetch_object($result)) {

            $existing_currency = $this->checkExisting($connection, $row->id, $user_id);

            if ($existing_currency == "1") {

                if ($row->currency == $default_currency) {

                    $this->updateConversionRate($connection, $timestamp, '1', $row->id, $user_id);

                } else {

                    $conversion_rate = $this->getConversionRate($row->currency, $default_currency);
                    $this->updateConversionRate($connection, $timestamp, $conversion_rate, $row->id, $user_id);

                }

            } else {

                if ($row->currency == $default_currency) {

                    $this->insertConversionRate($connection, $timestamp, '1', $row->id, $user_id);

                } else {

                    $conversion_rate = $this->getConversionRate($row->currency, $default_currency);
                    $this->insertConversionRate($connection, $timestamp, $conversion_rate, $row->id, $user_id);

                }

            }

        }

        return "Conversion Rates Updated<BR>";

    }

    public function checkExisting($connection, $currency_id, $user_id)
    {

        $sql = "SELECT id
                FROM currency_conversions
                WHERE currency_id = '" . $currency_id . "'
                  AND user_id = '" . $user_id . "'";
        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) == 0) {

            $existing_currency = "";

        } else {

            $existing_currency = "1";

        }

        return $existing_currency;

    }

    public function buildExcludeString($exclude_string, $currency)
    {

        $exclude_string .= "'" . $currency . "', ";

        return $exclude_string;

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

    public function updateConversionRate($connection, $timestamp, $conversion_rate, $current_currency_id, $current_user_id)
    {

        $sql = "UPDATE currency_conversions
                SET conversion = '" . $conversion_rate . "',
                    update_time = '" . $timestamp . "'
                WHERE currency_id = '" . $current_currency_id . "'
                  AND user_id = '" . $current_user_id . "'";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function insertConversionRate($connection, $timestamp, $conversion_rate, $currency_id, $user_id)
    {

        $sql = "INSERT INTO currency_conversions
                (currency_id, user_id, conversion, insert_time) VALUES
                ('" . $currency_id . "', '" . $user_id . "', '" . $conversion_rate . "', '" . $timestamp . "')";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

}
