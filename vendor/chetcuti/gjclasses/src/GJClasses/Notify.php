<?php
namespace GJClasses;

class Notify
{
    public function __construct($method, $name, $address, $reply_name, $reply_address, $recipients, $email_subject,
                                $html, $text, $push_provider, $api_key, $user_key, $push_subject, $content, $url,
                                $url_text, $priority)
    {
        if ($method == 'all') {

            $mail = new Mail();
            $mail->send($name, $address, $reply_name, $reply_address, $recipients, $email_subject, $html, $text);

            $push = new Push($push_provider);
            $push->push($api_key, $user_key, $push_subject, $content, $url, $url_text, $priority);

        } elseif ($method == 'email') {

            $mail = new Mail();
            $mail->send($name, $address, $reply_name, $reply_address, $recipients, $email_subject, $html, $text);

        } elseif ($method == 'push') {

            $push = new Push($push_provider);
            $push->push($api_key, $user_key, $push_subject, $content, $url, $url_text, $priority);

        }
    }
}
