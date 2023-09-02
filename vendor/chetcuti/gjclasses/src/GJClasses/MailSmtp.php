<?php
namespace GJClasses;

use PHPMailer\PHPMailer\PHPMailer;

class MailSmtp
{
    public function send($from_name, $from_address, $reply_name, $reply_address, $recipients, $subject, $message_html, $message_text)
    {
        $mail = new PHPMailer();

        // $mail->SMTPDebug = 3;
        $mail->isSMTP();
        $mail->isHTML(true);
        $mail->CharSet = GJC_EMAIL_ENCODING_TYPE;
        $mail->SMTPSecure = GJC_SMTP_PROTOCOL;
        $mail->Host = GJC_SMTP_MAIL_SERVER;
        $mail->Port = GJC_SMTP_PORT;
        $mail->SMTPAuth = GJC_SMTP_AUTHENTICATION_ON;
        $mail->Username = GJC_SMTP_USERNAME;
        $mail->Password = GJC_SMTP_PASSWORD;
        $mail->setFrom($from_address, $from_name);
        $mail->addReplyTo($reply_address, $reply_name);

        if ($recipients) {

            $result_message = 'Email Send Failed';

            foreach ($recipients AS $recipient) {

                $mail->Subject = $subject;
                $mail->Body = $message_html;
                $mail->AltBody = $message_text;
                $mail->clearAddresses();
                $mail->addAddress($recipient);

                if ($mail->send()) {

                    $result_message = 'Email Send Succeeded';

                } else {

                    $result_message = 'Email Send Failed';

                }

            }

        } else {

            $result_message = 'Nothing to send';

        }

        return $result_message;

    }

}
