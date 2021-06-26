<?php
namespace GJClasses;

class Currency
{
    public $api_key;
    public $source;

    public function __construct($source, $api_key)
    {
        $this->source = $source;
        $this->api_key = $api_key;
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

            $full_url = 'http://api.exchangeratesapi.io/v1/convert?access_key=' . $this->api_key . '&from=' . $from_currency . '&to=' . $to_currency;
            $remote = new Remote();
            $result = $remote->getFileContents($full_url);
            if ($result === false) return false;
            $json_result = json_decode($result, true);
            $conversion_rate = $json_result['rates'][$to_currency];

        } elseif ($this->source === 'er-a') {

            $full_url = 'https://v6.exchangerate-api.com/v6/' . $this->api_key . '/pair/' . $from_currency . '/' . $to_currency;
            $remote = new Remote();
            $result = $remote->getFileContents($full_url);
            if ($result === false) return false;
            $json_result = json_decode($result, true);
            $conversion_rate = $json_result['conversion_rate'];

        } elseif ($this->source === 'fixer') {

            $full_url = 'http://data.fixer.io/api/convert?access_key=' . $this->api_key . '&from=' . $from_currency . '&to=' . $to_currency;
            $remote = new Remote();
            $result = $remote->getFileContents($full_url);
            if ($result === false) return false;
            $json_result = json_decode($result, true);
            $conversion_rate = $json_result['rates'][$to_currency];

        } elseif ($this->source === 'interzoid') {

            $full_url = 'https://api.interzoid.com/convertcurrency?license=' . $this->api_key . '&from=' . $from_currency . '&to=' . $to_currency . '&amount=1';
            $remote = new Remote();
            $result = $remote->getFileContents($full_url);
            if ($result === false) return false;
            $json_result = json_decode($result, true);
            $conversion_rate = $json_result['Converted'];

        } elseif ($this->source === 'erh') {

            $full_url = 'https://api.exchangerate.host/convert?from=' . $from_currency . '&to=' . $to_currency;
            $remote = new Remote();
            $result = $remote->getFileContents($full_url);
            if ($result === false) return false;
            $json_result = json_decode($result, true);
            $conversion_rate = $json_result['info']['rate'];

        }

        return $conversion_rate;
    }
}
