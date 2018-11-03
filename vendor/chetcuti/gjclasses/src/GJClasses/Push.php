<?php
namespace GJClasses;

class Push
{
    public $service;

    public function __construct($service)
    {
        $this->service = $service;
    }

    public function push($api_key, $type, $subject, $content, $url)
    {
        if ($this->service == 'pushbullet') {

            $push = new Pushbullet();
            $result_message = $push->push($api_key, $type, $subject, $content, $url);

        } else {

            $result_message = 'Invalid push service provider';

        }

        return $result_message;
    }

}
