<?php
/**
 * /classes/DomainMOD/Smtp.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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

use PHPMailer\PHPMailer\PHPMailer;

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
        $mail = new PHPMailer();
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

            $log_extra[_('Error')] = $this->getSafeError($mail->ErrorInfo);
            $log_message = $email_title . ' ' . _('Email :: SEND FAILED');
            $this->log->error($log_message, $log_extra);
            return false;

        }
    }

    /*
     * PHPMailer's reason for a failed send, for the log. Its messages don't include the login, but they can quote the
     * mail server's reply, so anything that could be the username or password is hidden in case a server ever echoes
     * it back: any encoded word (as SMTP logins send them) that decodes to the login, then the values themselves and
     * any long enough piece of the password. Angle brackets become round ones, since the Debug Log shows the text as
     * it is.
     */
    public function getSafeError($error)
    {
        $error = trim((string) $error);
        $username = (string) $this->username;
        $password = (string) $this->password;

        // Encoded words first, such as the AUTH LOGIN, AUTH PLAIN, and CRAM-MD5 responses, before anything else can
        // break one up.
        $error = preg_replace_callback('/(?<![A-Za-z0-9+\/])[A-Za-z0-9+\/]{2,}={0,2}(?![A-Za-z0-9+\/=])/', function ($match) use ($username, $password) {

            $word = rtrim($match[0], '=');
            $decoded = base64_decode($word . str_repeat('=', (4 - strlen($word) % 4) % 4), true);
            if ($decoded === false || $decoded === '') return $match[0];

            $hide = strpos($decoded, "\0") !== false
                || ($username !== '' && stripos($decoded, $username) !== false)
                || $this->hasPasswordPiece($decoded, $password);

            return $hide ? '[hidden]' : $match[0];

        }, $error);

        foreach (array($password, $username) as $value) {

            if ($value !== '') $error = str_ireplace($value, '[hidden]', $error);

        }

        foreach ($this->getPasswordPieces($password) as $piece) $error = str_ireplace($piece, '[hidden]', $error);

        $error = str_replace(array('<', '>'), array('(', ')'), $error);

        if ($error === '') return _('No reason given');

        // Keep the log entry a sensible size without cutting a character in half (the byte cut is only for text that
        // isn't valid UTF-8 to begin with).
        return preg_match('/^.{0,1000}/su', $error, $match) ? $match[0] : substr($error, 0, 1000);
    }

    // The pieces of a password worth hiding, longest first: every piece at least six characters long, or the whole
    // password if it's shorter than that.
    protected function getPasswordPieces($password)
    {
        $length = strlen((string) $password);
        $pieces = array();

        for ($size = $length; $size >= min(6, $length) && $size > 0; $size--) {

            for ($start = 0; $start + $size <= $length; $start++) $pieces[] = substr($password, $start, $size);

        }

        return array_values(array_unique($pieces));
    }

    // Whether some text holds a piece of the password worth hiding. Checking the shortest pieces is enough, since any
    // longer piece contains one of them.
    protected function hasPasswordPiece($text, $password)
    {
        $password = (string) $password;
        $size = min(6, strlen($password));

        for ($start = 0; $size > 0 && $start + $size <= strlen($password); $start++) {

            if (stripos($text, substr($password, $start, $size)) !== false) return true;

        }

        return false;
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
