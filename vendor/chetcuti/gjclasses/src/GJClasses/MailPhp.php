<?php
namespace GJClasses;

class MailPhp
{

    public function send($from_name, $from_address, $reply_name, $reply_address, $recipients, $subject, $message_html)
    {
        $headers = $this->getHeaders($from_name, $from_address, $reply_name, $reply_address);

        if ($recipients) {

            foreach ($recipients as $recipient) {

                mail($recipient, $subject, $message_html, $headers, "-f " . $from_address);
                sleep(2);

            }

            $result_message = 'Email Send Succeeded';

        } else {

            $result_message = 'Nothing to send';

        }

        return $result_message;
    }

    public function getHeaders($from_name, $from_address, $reply_name, $reply_address)
    {
        $headers = '';
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'From: "' . $from_name . '" <' . $from_address . ">\r\n";
        $headers .= 'Return-Path: ' . $from_address . "\r\n";
        $headers .= 'Reply-to: "' . $reply_name . '" <' . $reply_address . ">\r\n";
        $version = phpversion();
        $headers .= 'X-Mailer: PHP/' . $version . "\r\n";
        return $headers;
    }

}
