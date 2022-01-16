<?php
/**
 * /classes/DomainMOD/Smtp.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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
    public $deeb;
    public $log;
    public $format;
    public $server;
    public $protocol;
    public $port;
    public $email_address;
    public $username;
    public $password;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.smtp');
        $this->format = new Format();
        list($this->server, $this->protocol, $this->port, $this->email_address, $this->username, $this->password)
            = $this->getSettings();
    }

    public function send($email_title, $to_address, $reply_address, $subject, $message_html, $message_text)
    {
        require_once DIR_ROOT . '/vendor/autoload.php';
        $mail = new \PHPMailer();

        // $mail->SMTPDebug = 3;  // Enable verbose debug output
        $mail->isSMTP();
        $mail->CharSet = EMAIL_ENCODING_TYPE;
        $mail->SMTPSecure = $this->protocol;
        $mail->Host = $this->server;
        $mail->Port = $this->port;
        $mail->SMTPAuth = true;
        $mail->Username = $this->username;
        $mail->Password = $this->password;
        $mail->setFrom($this->email_address, SOFTWARE_TITLE);
        $mail->addAddress($to_address);
        $mail->addReplyTo($reply_address, SOFTWARE_TITLE . ' ' . _('Admin'));
        $mail->isHTML(true);  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body = $message_html;
        $mail->AltBody = $message_text;

        $log_extra = array(_('Method') => _('SMTP'), _('To') => $to_address, _('From') => $this->email_address,
            _('Subject') => $subject, _('Server') => $this->server, _('Port') => $this->port, _('Protocol') => $this->protocol,
            _('Username') => $this->format->obfusc($this->username), _('Password') => $this->format->obfusc($this->password),
            _('CharSet') => EMAIL_ENCODING_TYPE);

        if ($mail->send()) {

            $log_message = $email_title . ' ' . _('Email :: SEND SUCCEEDED');
            $this->log->info($log_message, $log_extra);
            return true;

        } else {

            $log_message = $email_title . ' ' . _('Email :: SEND FAILED');
            $this->log->error($log_message, $log_extra);
            return false;

        }
    }

    public function getSettings()
    {
        $server = '';
        $protocol = '';
        $port = '';
        $email_address = '';
        $username = '';
        $password = '';

        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT smtp_server, smtp_protocol, smtp_port, smtp_email_address, smtp_username, smtp_password
            FROM settings");
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve SMTP settings';
            $this->log->critical($log_message);

        } else {

            $server = $result->smtp_server;
            $protocol = $result->smtp_protocol;
            $port = $result->smtp_port;
            $email_address = $result->smtp_email_address;
            $username = $result->smtp_username;
            $password = $result->smtp_password;

        }
        return array($server, $protocol, $port, $email_address, $username, $password);
    }

} //@formatter:on
