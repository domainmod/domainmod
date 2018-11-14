<?php
namespace GJClasses;

use donatj\Pushover\Options;
use donatj\Pushover\Priority;
// use donatj\Pushover\Pushover;
use donatj\Pushover\Sounds;

class Pushover
{
    public function push($api_key, $user_key, $subject, $content, $url)
    {
        $push = new \GJClasses\Push('pushover');
        $type = $push->getPushType($url);

        if ($type == 'note') {

            $message = $this->pushNote($api_key, $user_key, $subject, $content);

        } elseif ($type == 'url') {

            $message = $this->pushUrl($api_key, $user_key, $subject, $content, $url);

        } else {

            $message = 'Push type incorrect or not specified';

        }

        return $message;
    }

    public function pushNote($api_key, $user_key, $subject, $content)
    {
        $push = new \donatj\Pushover\Pushover($api_key, $user_key);
        $push->send($content, array(
            Options::TITLE => $subject,
            Options::PRIORITY => Priority::HIGH,
            Options::SOUND => Sounds::ALIEN,
        )) or die('Message Failed');
        return 'Note Sent';
    }

    public function pushUrl($api_key, $user_key, $subject, $content, $url)
    {
        $push = new \donatj\Pushover\Pushover($api_key, $user_key);
        $push->send($content, array(
            Options::TITLE => $subject,
            Options::URL => $url,
            Options::PRIORITY => Priority::HIGH,
            Options::SOUND => Sounds::ALIEN,
        )) or die('Message Failed');
        return 'URL Sent';
    }
}
