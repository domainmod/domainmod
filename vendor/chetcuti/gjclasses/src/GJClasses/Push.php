<?php

namespace GJClasses;

class Push
{
    public $service;

    public function __construct($service)
    {
        $this->service = $service;
    }

    public function push($api_key, $user_key, $subject, $content, $url, $url_text, $priority = '0')
    {
        if ($this->service == 'join') {

            $push = new Join();
            $result_message = $push->push($api_key, $subject, $content, $url);

        } elseif ($this->service == 'pushbullet') {

            $push = new Pushbullet();
            $result_message = $push->push($api_key, $subject, $content, $url);

        } elseif ($this->service == 'pushover') {

            $push = new Pushover();
            $result_message = $push->push($api_key, $user_key, $subject, $content, $url, $priority);

        } elseif ($this->service == 'telegram') {

            $push = new Telegram();
            $result_message = $push->push($api_key, $user_key, $subject, $content, $url, $url_text);

        } else {

            $result_message = 'Invalid push service provider';

        }

        return $result_message;
    }

    public function getPushType($url)
    {
        return (isset($url) && $url != '') ? 'url' : 'note';
    }

}
