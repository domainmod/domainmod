<?php
/**
 * /classes/DomainMOD/Smtp.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
//@formatter:off
namespace DomainMOD;

class Smtp
{

    public function send($connection, $reply_address, $to_address, $to_name, $subject, $message_html, $message_text)
    {
        require DIR_ROOT . 'vendor/autoload.php';
        $mail = new \PHPMailer();

        list($server, $protocol, $port, $email_address, $username, $password) = $this->getSettings($connection);

        // $mail->SMTPDebug = 3;  // Enable verbose debug output
        $mail->isSMTP();
        $mail->SMTPSecure = $protocol;
        $mail->Host = $server;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->setFrom($email_address, 'DomainMOD');
        $mail->addAddress($to_address, $to_name);
        $mail->addReplyTo($reply_address, 'DomainMOD System Admin');
        $mail->isHTML(true);  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body = $message_html;
        $mail->AltBody = $message_text;

        if(!$mail->send()) {
            echo 'Message could not be sent.<BR><BR>Please check your SMTP server and account information and try again.';
            exit;
        }
        return;
    }

    public function getSettings($connection)
    {
        $sql = "SELECT smtp_server, smtp_protocol, smtp_port, smtp_email_address, smtp_username, smtp_password FROM settings";
        $result = mysqli_query($connection, $sql);
        $server = '';
        $protocol = '';
        $port = '';
        $email_address = '';
        $username = '';
        $password = '';
        while ($row = mysqli_fetch_object($result)) {
            $server = $row->smtp_server;
            $protocol = $row->smtp_protocol;
            $port = $row->smtp_port;
            $email_address = $row->smtp_email_address;
            $username = $row->smtp_username;
            $password = $row->smtp_password;
        }
        return array($server, $protocol, $port, $email_address, $username, $password);
    }

} //@formatter:on
