<?php

namespace GJClasses;

class Pushbullet
{
    public function push($api_key, $subject, $content, $url)
    {
        $push = new \GJClasses\Push('pushbullet');
        $type = $push->getPushType($url);

        if ($type == 'note') {

            $message = $this->pushNote($api_key, $subject, $content);

        } elseif ($type == 'url') {

            $message = $this->pushUrl($api_key, $subject, $content, $url);

        } else {

            $message = 'Push type incorrect or not specified';

        }

        return $message;
    }

    public function pushNote($api_key, $subject, $content)
    {
        $push = new \Pushbullet\Pushbullet($api_key);
        $push->allDevices()->pushNote($subject, $content);
        return 'Note Sent';
    }

    public function pushUrl($api_key, $subject, $content, $url)
    {
        $push = new \Pushbullet\Pushbullet($api_key);
        $push->allDevices()->pushLink($subject, $url, $content);
        return 'URL Sent';
    }
}
