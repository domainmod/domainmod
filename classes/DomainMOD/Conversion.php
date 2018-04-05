<?php
/**
 * /classes/DomainMOD/Conversion.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
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

class Conversion
{
    public $deeb;
    public $log;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.conversion');
        $this->time = new Time();
    }

    public function updateRates($default_currency, $user_id)
    {
        $pdo = $this->deeb->cnxx;
        $result = $this->getActiveCurrencies();

        $stmt = $pdo->prepare("
            SELECT id
            FROM currency_conversions
            WHERE currency_id = :currency_id
              AND user_id = :user_id");
        $stmt->bindParam('currency_id', $bind_currency_id, \PDO::PARAM_INT);
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);

        foreach ($result as $row) {

            $conversion_rate = $row->currency == $default_currency ? 1 : $this->getConversionRate($row->currency, $default_currency);

            $bind_currency_id = $row->id;
            $stmt->execute();

            $result_conversion = $stmt->fetchColumn();

            if (!$result_conversion) {

                $is_existing = '0';
                $log_message = 'Unable to retrieve user currency';
                $log_extra = array('User ID' => $user_id, 'Currency ID' => $row->id, 'Default Currency' =>
                    $default_currency, 'Conversion Rate' => $conversion_rate);
                $this->log->critical($log_message, $log_extra);

            } else {

                $is_existing = '1';

            }

            $this->updateConversionRate($conversion_rate, $is_existing, $row->id, $user_id);

        }

        $result_message = 'Conversion Rates updated<BR>';

        return $result_message;
    }

    public function getActiveCurrencies()
    {
        $result = $this->deeb->cnxx->query("
            SELECT id, currency
            FROM
            (  SELECT c.id, c.currency
               FROM currencies AS c, fees AS f, domains AS d
               WHERE c.id = f.currency_id
                 AND f.id = d.fee_id
               GROUP BY c.currency
               UNION
               SELECT c.id, c.currency
               FROM currencies AS c, ssl_fees AS f, ssl_certs AS sslc
               WHERE c.id = f.currency_id
                 AND f.id = sslc.fee_id
               GROUP BY c.currency
            ) AS temp
            GROUP BY currency")->fetchAll();

        if (!$result) {

            $log_message = 'Unable to retrieve active currencies';
            $this->log->critical($log_message);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getConversionRate($from_currency, $to_currency)
    {
        $currency_slug = $from_currency . '_' . $to_currency;
        $full_url = 'https://free.currencyconverterapi.com/api/v5/convert?q=' . $currency_slug . '&compact=y';
        $handle = curl_init($full_url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($handle);
        curl_close($handle);
        $json_result = json_decode($result);
        $conversion_rate = $json_result->{$currency_slug}->val;

        if ($conversion_rate != '' && $conversion_rate != 'N/A' && $conversion_rate != 'n/a') {

            return $conversion_rate;

        } else {

            $log_message = 'Unable to retrieve Currency Converter API (FREE) currency conversion';
            $log_extra = array('From Currency' => $from_currency, 'To Currency' => $to_currency,
                               'Conversion Rate Result' => $conversion_rate);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        }
    }

    public function updateConversionRate($conversion_rate, $is_existing, $currency_id, $user_id)
    {
        $pdo = $this->deeb->cnxx;

        if ($is_existing == '1') {

            $stmt = $pdo->prepare("
                UPDATE currency_conversions
                SET conversion = :conversion_rate,
                    update_time = :update_time
                WHERE currency_id = :currency_id
                  AND user_id = :user_id");
            $stmt->bindValue('conversion_rate', strval($conversion_rate), \PDO::PARAM_STR);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->bindValue('currency_id', $currency_id, \PDO::PARAM_INT);
            $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
            $stmt->execute();

            $log_message = 'Conversion rate updated';
            $log_extra = array('User ID' => $user_id, 'Currency ID' => $currency_id, 'Conversion Rate' => $conversion_rate, 'Update Time' => $this->time->stamp());
            $this->log->info($log_message, $log_extra);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO currency_conversions
                (currency_id, user_id, conversion, insert_time)
                VALUES
                (:currency_id, :user_id, :conversion_rate, :update_time)");
            $stmt->bindValue('currency_id', $currency_id, \PDO::PARAM_INT);
            $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
            $stmt->bindValue('conversion_rate', strval($conversion_rate), \PDO::PARAM_STR);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->execute();

            $log_message = 'Conversion rate inserted';
            $log_extra = array('User ID' => $user_id, 'Currency ID' => $currency_id, 'Conversion Rate' => $conversion_rate, 'Update Time' => $this->time->stamp());
            $this->log->info($log_message, $log_extra);

        }
    }

} //@formatter:on
