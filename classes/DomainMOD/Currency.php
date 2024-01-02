<?php
/**
 * /classes/DomainMOD/Currency.php
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

class Currency
{
    public $deeb;
    public $log;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.currency');
    }

    public function format($amount, $symbol, $order, $space)
    {
        $amount = $amount ?? 0.0;
        if ($order == "1" && $space == "1") {
            $formatted_output = number_format($amount, 2, '.', ',') . " " . $symbol;
        } elseif ($order == "1" && $space == "0") {
            $formatted_output = number_format($amount, 2, '.', ',') . $symbol;
        } elseif ($order == "0" && $space == "1") {
            $formatted_output = $symbol . " " . number_format($amount, 2, '.', ',');
        } else {
            $formatted_output = $symbol . number_format($amount, 2, '.', ',');
        }

        return $formatted_output;
    }

    public function getCurrencyId($currency)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id
            FROM currencies
            WHERE currency = :currency");
        $stmt->bindValue('currency', $currency, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Currency ID';
            $log_extra = array('Currency' => $currency);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }

    }

    public function getCurrencyInfo($currency)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`, symbol, symbol_order, symbol_space
            FROM currencies
            WHERE currency = :currency");
        $stmt->bindValue('currency', $currency, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        return array($result->name, $result->symbol, $result->symbol_order, $result->symbol_space);
    }

} //@formatter:on
