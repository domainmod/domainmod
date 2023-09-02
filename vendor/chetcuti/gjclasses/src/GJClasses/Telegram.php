<?php

namespace GJClasses;

use Telegram\Bot\Api;

class Telegram
{
    public function push($api_key, $chat_id, $subject, $content, $url, $url_text)
    {
        $push = new \GJClasses\Push('telegram');
        $type = $push->getPushType($url);

        if ($type == 'note') {

            $message = $this->pushNote($api_key, $chat_id, $subject, $content);

        } elseif ($type == 'url') {

            $message = $this->pushUrl($api_key, $chat_id, $subject, $content, $url, $url_text);

        } else {

            $message = 'Push type incorrect or not specified';

        }

        return $message;

    }

    public function pushNote($api_key, $chat_id, $subject, $content)
    {
        $telegram = new Api($api_key);

        if ($subject != '') $subject = '*' . $subject . '*' . "\n\n";
        $content = $subject . $content;

        $telegram->sendMessage([
            "chat_id" => $chat_id,
            "text" => $content,
            "parse_mode" => 'Markdown'
        ]);

        return 'Note Sent';
    }

    public function pushUrl($api_key, $chat_id, $subject, $content, $url, $url_text)
    {
        $telegram = new Api($api_key);

        if ($subject != '') $subject = '*' . $subject . '*' . "\n\n";
        if ($url_text == '') $url_text = $url;
        $content = $subject . $content . "\n\n[" . $url_text . '](' . $url . ')';

        $telegram->sendMessage([
            "chat_id" => $chat_id,
            "text" => $content,
            "parse_mode" => 'Markdown'
        ]);

        return 'URL Sent';
    }

}
