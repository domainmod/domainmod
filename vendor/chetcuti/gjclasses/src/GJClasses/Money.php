<?php
namespace GJClasses;

class Money
{
    public $log;

    public function __construct()
    {
        $this->log = new Log('class.money');
    }

    public function convertAmount($amount, $from_currency, $to_currency)
    {
        return $this->getConvRate($from_currency, $to_currency) * $amount;
    }

    public function getConvRate($from_currency, $to_currency)
    {
        list($currency_slug, $full_url) = $this->getConvUrl($from_currency, $to_currency);
        $remote = new Remote();
        $result = $remote->getFileContents('Currency Conversion', 'error', $full_url);
        $json_result = json_decode($result);
        $conversion_rate = $json_result->{$currency_slug}->val;

        if (!is_null($conversion_rate) && $conversion_rate != '') {

            return $conversion_rate;

        } else {

            $log_message = 'Unable to retrieve Currency Converter API (FREE) currency conversion';
            $log_extra = array('From Currency' => $from_currency, 'To Currency' => $to_currency,
                               'Conversion Rate Result' => $conversion_rate);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        }
    }

    public function getConvUrl($from_currency, $to_currency)
    {
        $currency_slug = $from_currency . '_' . $to_currency;
        return array($currency_slug, 'https://free.currencyconverterapi.com/api/v5/convert?q=' . $currency_slug . '&compact=y');
    }
}
