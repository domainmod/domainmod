<?php
namespace GJClasses;

class Currency
{
    public $source;

    public function __construct($source)
    {
        $this->source = $source;
    }

    public function convertAmount($amount, $from_currency, $to_currency)
    {
        return $this->getConvRate($from_currency, $to_currency) * $amount;
    }

    public function getConvRate($from_currency, $to_currency)
    {
        if ($from_currency == $to_currency) return 1.0;

        $conversion_rate = 0.0;

        if ($this->source === 'era') {

            $full_url = 'https://api.exchangeratesapi.io/latest?base=' . $from_currency . '&symbols=' . $to_currency;
            $remote = new Remote();
            $result = $remote->getFileContents($full_url);
            if ($result === false) return false;
            $json_result = json_decode($result, true);
            $conversion_rate = $json_result['rates'][$to_currency];

        }

        return $conversion_rate;
    }
}
