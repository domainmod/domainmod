<?php
namespace GJClasses;

use donatj\Pushover\Options;
// use donatj\Pushover\Priority;
// use donatj\Pushover\Pushover;
// use donatj\Pushover\Sounds;

class Pushover
{
    public function push($api_key, $user_key, $subject, $content, $url = '', $priority = '0')
    {
        $push = new \GJClasses\Push('pushover');
        $type = $push->getPushType($url);

        if ($type == 'note') {

            $message = $this->pushNote($api_key, $user_key, $subject, $content, $priority);

        } elseif ($type == 'url') {

            $message = $this->pushUrl($api_key, $user_key, $subject, $content, $url, $priority);

        } else {

            $message = 'Push type incorrect or not specified';

        }

        return $message;
    }

    public function pushNote($api_key, $user_key, $subject, $content, $priority)
    {
        $push = new \donatj\Pushover\Pushover($api_key, $user_key);
        $push->send($content, array(
            Options::TITLE => $subject,
            Options::PRIORITY => $priority,
        )) or die('Message Failed');
        return 'Note Sent';
    }

    public function pushUrl($api_key, $user_key, $subject, $content, $url, $priority)
    {
        $push = new \donatj\Pushover\Pushover($api_key, $user_key);
        $push->send($content, array(
            Options::TITLE => $subject,
            Options::URL => $url,
            Options::PRIORITY => $priority,
        )) or die('Message Failed');
        return 'URL Sent';
    }
}
