<?php
namespace GJClasses;

class Money
{
    public function convertAmount($amount, $from_currency, $to_currency)
    {
        return $this->getConvRate($from_currency, $to_currency) * $amount;
    }

    public function getConvRate($from_currency, $to_currency)
    {
        list($currency_slug, $full_url) = $this->getConvUrl($from_currency, $to_currency);
        $remote = new Remote();
        $result = $remote->getFileContents($full_url);
        if ($result === false) return false;
        $json_result = json_decode($result);
        $conversion_rate = $json_result->{$currency_slug}->val;

        if (!is_null($conversion_rate) && $conversion_rate != '') {

            return $conversion_rate;

        } else {

            return false;

        }
    }

    public function getConvUrl($from_currency, $to_currency)
    {
        $currency_slug = $from_currency . '_' . $to_currency;
        return array($currency_slug, 'https://free.currencyconverterapi.com/api/v5/convert?q=' . $currency_slug . '&compact=y');
    }
}
