<?php
/**
 * /classes/DomainMOD/Email.php
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

class Email
{
    public $deeb;
    public $log;
    public $time;
    public $full_url;
    public $from_address;
    public $number_of_days;
    public $email_signature;
    public $first_name;
    public $last_name;
    public $email_address;
    public $use_smtp;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.email');
        $this->time = new Time();
        list($this->full_url, $this->from_address, $this->number_of_days, $this->email_signature, $this->use_smtp) = $this->getSettings();
        list($this->first_name, $this->last_name, $this->email_address) = $this->getEmailSignature();
    }

    public function send($email_title, $to_address, $subject, $message_html, $message_text)
    {
        if ($this->use_smtp == '1') {

            $smtp = new Smtp();
            $smtp->send($email_title, $to_address, $this->from_address, $subject, $message_html, $message_text);

        } else {

            $this->intPhpMail($email_title, $to_address, $subject, $message_html);

        }
    }

    public function intPhpMail($email_title, $to_address, $subject, $message)
    {
        $headers = $this->getHeaders();
        $log_extra = array(_('Method') => 'PHP mail()', _('To') => $to_address, _('From') => $this->from_address,
            _('Subject') => $subject);

        if (mail($to_address, $subject, $message, $headers, '-f' . $this->from_address)) {

            $log_message = $email_title . ' ' . _('Email :: SEND SUCCEEDED');
            $this->log->info($log_message, $log_extra);
            return true;

        } else {

            $log_message = $email_title . ' ' . _('Email :: SEND FAILED');
            $this->log->error($log_message, $log_extra);
            return false;

        }
    }

    public function sendExpirations($from_cron = false)
    {
        $timestamp_basic = $this->time->timeBasic();
        $timestamp_long = $this->time->timeLong();

        $send_to = $this->getExpEmRecip();
        $subject = _('Upcoming Expirations') . ' - ' . $timestamp_long;

        list($result_domains, $result_ssl) = $this->checkExpiring($from_cron);
        $message_html = '';
        $message_html .= $this->messageTopHtml($subject);
        $message_html .= $this->showDomainsHtml($result_domains, $timestamp_basic);
        $message_html .= $this->showSslHtml($result_ssl, $timestamp_basic);
        $message_html .= $this->messageBottomHtml();

        list($result_domains, $result_ssl) = $this->checkExpiring($from_cron);
        $message_text = $subject . "\n\n";
        $message_text .= $this->messageTopText();
        $message_text .= $this->showDomainsText($result_domains, $timestamp_basic);
        $message_text .= $this->showSslText($result_ssl, $timestamp_basic);
        $message_text .= $this->messageBottomText();

        foreach ($send_to as $row_recipients) {

            $this->send(_('Expiration'), $row_recipients->email_address, $subject, $message_html, $message_text);
            sleep(2);

        }

        $_SESSION['s_message_success'] .= _('Expiration Email Sent') . '<BR>';
    }

    public function getSettings()
    {
        $url = '';
        $email = '';
        $days = '';
        $signature = '';
        $use_smtp = '';

        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT full_url, email_address, expiration_days, email_signature, use_smtp
            FROM settings");
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve email settings';
            $this->log->critical($log_message);

        } else {

            $url = $result->full_url;
            $email = $result->email_address;
            $days = $result->expiration_days;
            $signature = $result->email_signature;
            $use_smtp = $result->use_smtp;

        }
        return array($url, $email, $days, $signature, $use_smtp);
    }

    public function getEmailSignature()
    {
        $first_name = '';
        $last_name = '';
        $email_address = '';

        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT first_name, last_name, email_address
            FROM users
            WHERE id = :user_id");
        $stmt->bindValue('user_id', $this->email_signature, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve email signature user ID';
            $this->log->critical($log_message);

        } else {

            $first_name = $result->first_name;
            $last_name = $result->last_name;
            $email_address = $result->email_address;

        }
        return array($first_name, $last_name, $email_address);
    }

    public function checkExpiring($from_cron = false)
    {
        $date = $this->time->timeBasicPlusDays($this->number_of_days);
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id, expiry_date, domain
            FROM domains
            WHERE active NOT IN ('0', '10')
              AND expiry_date <= :date
            ORDER BY expiry_date, domain");
        $stmt->bindValue('date', $date, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if (!$result) {

            $domains_expiring = '0';

        } else {

            $domains_expiring = $result;

        }

        $stmt = $pdo->prepare("
            SELECT sslc.id, sslc.expiry_date, sslc.name, sslt.type
            FROM ssl_certs AS sslc, ssl_cert_types AS sslt
            WHERE sslc.type_id = sslt.id
              AND sslc.active NOT IN ('0')
              AND sslc.expiry_date <= :date
            ORDER BY sslc.expiry_date, sslc.name");
        $stmt->bindValue('date', $date, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if (!$result) {

            $ssl_expiring = '0';

        } else {

            $ssl_expiring = $result;

        }

        if ($domains_expiring != '0' || $ssl_expiring != '0') {
            return array($domains_expiring, $ssl_expiring);
        } else {
            $_SESSION['s_message_success'] .= _('No Upcoming Expirations') . '<BR>';
            if ($from_cron === true) exit;
            return false;
        }
    }

    public function getExpEmRecip()
    {
        $result = $this->deeb->cnxx->query("
            SELECT u.email_address
            FROM users AS u, user_settings AS us
            WHERE u.id = us.user_id
              AND u.active = '1'
              AND us.expiration_emails = '1'")->fetchAll();

        if (!$result) {

            $_SESSION['s_message_danger'] .= _('No Users Are Subscribed') . '<BR>';
            return false;

        } else {

            return $result;

        }
    }

    public function getHeaders()
    {
        $headers = '';
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=' . EMAIL_ENCODING_TYPE . "\r\n";
        $headers .= 'From: "' . SOFTWARE_TITLE . '" <' . $this->from_address . ">\r\n";
        $headers .= 'Return-Path: ' . $this->from_address . "\r\n";
        $headers .= 'Reply-to: ' . $this->from_address . "\r\n";
        $version = phpversion();
        $headers .= 'X-Mailer: PHP/' . $version . "\r\n";
        return $headers;
    }

    public function messageTopHtml($subject)
    {
        ob_start(); ?>
        <html>
        <head><title><?php echo $subject; ?></title></head>
        <body bgcolor="#FFFFFF">
        <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#FFFFFF">
        <tr>
        <td width="100%" bgcolor="#FFFFFF">
        <font color="#000000" size="2" face="Verdana, Arial, Helvetica, sans-serif">
        <a title="<?php echo SOFTWARE_TITLE; ?>" href="<?php echo $this->full_url; ?>/"><img border="0" alt="<?php echo SOFTWARE_TITLE; ?>" src="<?php echo $this->full_url; ?>/images/logo.png"></a><BR>
        <BR>
        <?php echo sprintf(_('Below is a list of all the Domains & SSL Certificates in %s that are expiring in the next %s days.'), SOFTWARE_TITLE, $this->number_of_days); ?><BR>
        <BR>
        <?php echo sprintf(_('If you would like to change the frequency of this email notification please contact your %s administrator.'), SOFTWARE_TITLE); ?><BR>
        <BR><?php
        return ob_get_clean();
    }

    public function messageTopText()
    {
        $message = sprintf(_('Below is a list of all the Domains & SSL Certificates in %s that are expiring in the next %s days.'), SOFTWARE_TITLE, $this->number_of_days) . "\n\n";
        $message .= sprintf(_('If you would like to change the frequency of this email notification please contact your %s administrator.'), SOFTWARE_TITLE) . "\n\n";
        return $message;
    }

    public function showDomainsHtml($result_domains, $timestamp_basic)
    {
        ob_start();
        if ($result_domains) { ?>
            <strong><u><?php echo _('Domains'); ?></u></strong><BR><?php
            foreach ($result_domains as $row_domains) {
                if ($row_domains->expiry_date < $timestamp_basic) { ?>

                    <font color="#CC0000"><?php echo $row_domains->expiry_date; ?></font>&nbsp;&nbsp;<a
                        href="<?php echo $this->full_url; ?>/domains/edit.php?did=<?php echo $row_domains->id;
                        ?>"><?php echo $row_domains->domain; ?></a>&nbsp;&nbsp;<font
                        color="#CC0000">*<?php echo strtoupper(_('Expired')); ?>*</font><BR><?php
                } else { ?>

                    <?php echo $row_domains->expiry_date; ?>&nbsp;&nbsp;<a href="<?php echo $this->full_url;
                    ?>/domains/edit.php?did=<?php echo $row_domains->id; ?>"><?php echo $row_domains->domain;
                        ?></a><BR><?php
                }
            }
        }
        return ob_get_clean();
    }

    public function showDomainsText($result_domains, $timestamp_basic)
    {
        $message = '';
        if ($result_domains) {
            $message .= "[" . strtoupper(_('Domains')) . "]\n";
            foreach ($result_domains as $row_domains) {
                if ($row_domains->expiry_date < $timestamp_basic) {
                    $message .= $row_domains->expiry_date . " - " . $row_domains->domain . " *" . strtoupper(_('Expired')) . "*\n";
                } else {
                    $message .= $row_domains->expiry_date . " - " . $row_domains->domain . "\n";
                }
            }
            $message .= "\n";
        }
        return $message;
    }

    public function showSslHtml($result_ssl, $timestamp_basic)
    {
        ob_start();
        if ($result_ssl) { ?>
            <BR><strong><u><?php echo _('SSL Certificates'); ?></u></strong><BR><?php
            foreach ($result_ssl as $row_ssl) {
                if ($row_ssl->expiry_date < $timestamp_basic) { ?>
                    <font color="#CC0000"><?php echo $row_ssl->expiry_date; ?></font>&nbsp;&nbsp;<a
                        href="<?php echo $this->full_url; ?>/ssl/edit.php?sslcid=<?php echo $row_ssl->id;
                        ?>"><?php echo $row_ssl->name; ?> (<?php echo $row_ssl->type; ?>)</a>&nbsp;&nbsp;<font
                        color="#CC0000">*<?php echo strtoupper(_('Expired')); ?>*</font><BR><?php
                } else { ?>
                    <?php echo $row_ssl->expiry_date; ?>&nbsp;&nbsp;<a href="<?php echo $this->full_url;
                    ?>/ssl/edit.php?sslcid=<?php echo $row_ssl->id; ?>"><?php echo $row_ssl->name; ?>
                        (<?php echo $row_ssl->type; ?>)</a><BR><?php
                }
            }
        }
        return ob_get_clean();
    }

    public function showSslText($result_ssl, $timestamp_basic)
    {
        $message = '';
        if ($result_ssl) {
            $message .= "[" . _('SSL Certificates') . "]\n";
            foreach ($result_ssl as $row_ssl) {
                if ($row_ssl->expiry_date < $timestamp_basic) {
                    $message .= $row_ssl->expiry_date . " - " . $row_ssl->name . " (" . $row_ssl->type . ") *" . strtoupper(_('Expired')) . "*\n";
                } else {
                    $message .= $row_ssl->expiry_date . " - " . $row_ssl->name . " (" . $row_ssl->type . ")\n";
                }
            }
            $message .= "\n";
        }
        return $message;
    }

    public function messageBottomHtml()
    {
        ob_start(); ?>
        <BR><?php echo _('Best Regards'); ?>,<BR><BR><?php echo $this->first_name . ' ' . $this->last_name; ?><BR><a
            target="_blank" href="mailto:<?php echo $this->email_address; ?>"><?php echo $this->email_address; ?></a><BR>
        </font>
        </td></tr>
        </table>
        <table width="575" cellspacing="0" cellpadding="0" border="0" bgcolor="#FFFFFF"><tr>
        <td width="100%"><font color="#000000" size="2" face="Verdana, Arial, Helvetica, sans-serif">
        <BR><hr width="100%" size="2" noshade><?php echo sprintf(_("You've received this email because you're currently subscribed to receive expiration notifications from the %s installation located at"), SOFTWARE_TITLE) . ':'; ?><a target="_blank" href="<?php echo $this->full_url; ?>/"><?php echo $this->full_url; ?>/</a><BR><BR><?php echo _('To unsubscribe from these notifications please visit') . ':'; ?><BR><a target="_blank" href="<?php echo $this->full_url; ?>/settings/profile/"><?php echo $this->full_url; ?>/settings/profile/</a><BR><BR></font>
        </td></tr>
        </table>
        </body>
        </html><?php
        return ob_get_clean();
    }

    public function messageBottomText()
    {
        $message = '';
        $message .= _('Best Regards') . ",\n";
        $message .= "\n";
        $message .= $this->first_name . ' ' . $this->last_name . "\n";
        $message .= $this->email_address . "\n\n";
        $message .= "---\n\n";
        $message .= sprintf(_("You've received this email because you're currently subscribed to receive expiration notifications from the %s installation located at"), SOFTWARE_TITLE) . ': ' . $this->full_url . "\n\n";
        $message .= _('To unsubscribe from these notifications please visit') . ':'  . $this->full_url . "/settings/profile/";
        return $message;
    }

} //@formatter:on
