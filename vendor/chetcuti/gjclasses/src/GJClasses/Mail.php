<?php
namespace GJClasses;

class Mail
{
    public function send($from_name, $from_address, $reply_name, $reply_address, $recipients, $subject, $message_html, $message_text)
    {
        if (GJC_USE_SMTP === 1) {

            $mail = new MailSmtp();
            $result_message = $mail->send($from_name, $from_address, $reply_name, $reply_address, $recipients, $subject, $message_html, $message_text);

        } else {

            $mail = new MailPhp();
            $result_message = $mail->send($from_name, $from_address, $reply_name, $reply_address, $recipients, $subject, $message_html);

        }

        return $result_message;
    }

}
