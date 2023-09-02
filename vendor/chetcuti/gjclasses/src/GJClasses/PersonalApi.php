<?php

namespace GJClasses;

class PersonalApi
{
    public $api_key;
    public $api_url;

    public function __construct($api_key, $api_url)
    {
        $this->api_key = $api_key;
        $this->api_url = $api_url;
    }

    public function notify($subject, $message, $url = '', $priority = '0')
    {
        if ($priority != '1') $priority = '0';

        $payload = '{"api_key": "' . $this->api_key . '",
                     "subject": "' . trim($subject) . '",
                     "message": "' . trim($message) . '",
                     "url": "' . trim($url) . '",
                     "priority": "' . $priority . '"}';

        $handle = curl_init($this->api_url);
        curl_setopt($handle, CURLOPT_ENCODING, '');
        curl_setopt($handle, CURLOPT_MAXREDIRS, 10);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
        ));
        curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
        curl_exec($handle);
        curl_close($handle);

        return 'Notification Sent';
    }

}
