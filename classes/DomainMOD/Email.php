<?php
/**
 * /classes/DomainMOD/Email.php
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

class Email
{
    // The logo is sent with each email and shown from there (cid:), so it shows even when the mail app can't reach the
    // installation.
    const LOGO_CID = 'domainmod-logo';

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
    // Whether the database has the hosting tables, and the hosting date (today in the hosting time zone), worked out
    // once per expiration email by checkExpiring() (see hostingExpiring())
    public $hosting_available = false;
    public $hosting_date = '';

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.email');
        $this->time = new Time();
        list($this->full_url, $this->from_address, $this->number_of_days, $this->email_signature, $this->use_smtp) = $this->getSettings();
        list($this->first_name, $this->last_name, $this->email_address) = $this->getEmailSignature();
    }

    // Returns whether the email was handed off successfully (to the SMTP server, or to PHP's mail())
    public function send($email_title, $to_address, $subject, $message_html, $message_text)
    {
        if ($this->use_smtp == '1') {

            $smtp = new Smtp();
            return $smtp->send($email_title, $to_address, $this->from_address, $subject, $message_html, $message_text);

        } else {

            return $this->intPhpMail($email_title, $to_address, $subject, $message_html, $message_text);

        }
    }

    public function intPhpMail($email_title, $to_address, $subject, $message_html, $message_text)
    {
        $mail = $this->getMailer();
        $mail->isMail();
        // PHPMailer turns down an address it can't use (and would then send with no From), so that counts as a failed send.
        $from_ok = $mail->setFrom(self::address($this->from_address), SOFTWARE_TITLE);
        // The envelope sender (the Return-Path), passed to mail() as -f unless PHP's sendmail_path already sets one.
        $mail->Sender = self::address($this->from_address);
        $mail->addReplyTo(self::address($this->from_address));
        self::setMessage($mail, $to_address, $subject, $message_html, $message_text);

        $log_extra = array(_('Method') => 'PHP mail()', _('To') => $to_address, _('From') => $this->from_address,
            _('Subject') => $subject);

        if ($from_ok && $mail->send()) {

            $log_message = $email_title . ' ' . _('Email :: SEND SUCCEEDED');
            $this->log->info($log_message, $log_extra);
            return true;

        } else {

            $log_message = $email_title . ' ' . _('Email :: SEND FAILED');
            $this->log->error($log_message, $log_extra);
            return false;

        }
    }

    // A new PHPMailer for one email (its own method, so the tests can use one that doesn't send anything).
    public function getMailer()
    {
        return new PHPMailer();
    }

    /*
     * The parts of an email that are the same however it's sent (PHP's mail() here, or SMTP in Smtp.php): the
     * recipient, subject, and HTML and plain-text versions, plus the logo as an inline image when the HTML shows it.
     */
    public static function setMessage($mail, $to_address, $subject, $message_html, $message_text)
    {
        $mail->CharSet = EMAIL_ENCODING_TYPE;
        $mail->addAddress(self::address($to_address));
        $mail->isHTML(true);  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body = $message_html;
        $mail->AltBody = $message_text;

        if (strpos($message_html, 'cid:' . self::LOGO_CID) !== false) {

            $mail->addEmbeddedImage(self::logoFile(), self::LOGO_CID, 'logo.png', PHPMailer::ENCODING_BASE64,
                'image/png');

        }
    }

    /*
     * A stored email address as it was typed. Addresses are saved HTML-escaped (Sanitize::text()), so one with & or '
     * in it would otherwise reach the mailer escaped (such as o&#039;brien@example.com), and the email wouldn't go.
     */
    public static function address($address)
    {
        return (new Unsanitize())->text((string) $address);
    }

    // The logo file that's sent with the emails.
    public static function logoFile()
    {
        return DIR_ROOT . '/images/logo.png';
    }

    /*
     * The logo at the top of an email, linked to the installation. It's on a white cell so it stays readable when a mail
     * app shows the email in dark mode. If the file can't be read, so it can't be sent with the email, the logo is shown
     * from the installation instead, as before.
     */
    public function logoHtml()
    {
        $url = htmlspecialchars($this->full_url . '/', ENT_QUOTES);
        $src = is_readable(self::logoFile()) ? 'cid:' . self::LOGO_CID : $url . 'images/logo.png';
        return '<table cellspacing="0" cellpadding="6" border="0" bgcolor="#FFFFFF" style="background-color: #FFFFFF;"><tr>'
            . '<td bgcolor="#FFFFFF" style="background-color: #FFFFFF;"><a title="' . SOFTWARE_TITLE . '" href="' . $url
            . '"><img src="' . $src . '" width="204" height="32" border="0" alt="' . SOFTWARE_TITLE
            . '" style="display: block;"></a></td></tr></table>';
    }

    /*
     * Tags for an email's head that ask mail apps not to invert it in dark mode, so the logo and colours show as designed
     * in the apps that honour them (color-scheme, plus supported-color-schemes for older versions of Apple Mail).
     */
    public static function colorSchemeTags()
    {
        return '<meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light only">';
    }

    public function sendExpirations($from_cron = false)
    {
        $timestamp_basic = $this->time->timeBasic();
        $timestamp_long = $this->time->timeLong();

        $send_to = $this->getExpEmRecip();
        $subject = _('Upcoming Expirations') . ' - ' . $timestamp_long;

        // Nothing expiring: no email (checkExpiring() has already set the message for the page)
        $expiring = $this->checkExpiring($from_cron);
        if ($expiring === false) return;
        list($result_domains, $result_ssl, $result_hosting) = $expiring;

        $message_html = '';
        $message_html .= $this->messageTopHtml($subject);
        $message_html .= $this->showDomainsHtml($result_domains, $timestamp_basic);
        $message_html .= $this->showHostingHtml($result_hosting);
        $message_html .= $this->showSslHtml($result_ssl, $timestamp_basic);
        $message_html .= $this->messageBottomHtml();

        $message_text = $subject . "\n\n";
        $message_text .= $this->messageTopText();
        $message_text .= $this->showDomainsText($result_domains, $timestamp_basic);
        $message_text .= $this->showHostingText($result_hosting);
        $message_text .= $this->showSslText($result_ssl, $timestamp_basic);
        $message_text .= $this->messageBottomText();

        foreach ($send_to as $row_recipients) {

            $this->send(_('Expiration'), $row_recipients->email_address, $subject, $message_html, $message_text);
            sleep(2);

        }

        if (isset($_SESSION['s_message_success'])) $_SESSION['s_message_success'] .= _('Expiration Email Sent') . '<BR>';
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

        $hosting_expiring = $this->hostingExpiring();

        if ($domains_expiring != '0' || $ssl_expiring != '0' || $hosting_expiring) {
            return array($domains_expiring, $ssl_expiring, $hosting_expiring);
        } else {

            if (isset($_SESSION['s_message_success'])) {

                $_SESSION['s_message_success'] .= _('No Upcoming Expirations') . '<BR>';

            }

            // Return rather than exit, so cron.php goes on to its other tasks
            return false;
        }
    }

    /*
     * The hosting services for the expiration email, once the database update has added the hosting tables (an empty
     * list before then): every service in any status except Cancelled whose renewal date is on or before the hosting
     * cut-off, the hosting date plus the email's number of days, each with its renewal state (see hostingRows()). The
     * hosting date, not the UTC date the domain and SSL sections use, is worked out once here, so just after midnight in
     * a zone ahead of UTC a one-day window still includes tomorrow there.
     */
    public function hostingExpiring()
    {
        $pdo = $this->deeb->cnxx;
        $this->hosting_available = Hosting::isAvailable($pdo);
        if (!$this->hosting_available) return array();

        $this->hosting_date = Hosting::hostingDate($pdo);
        $cutoff = Hosting::addDays($this->hosting_date, (int) $this->number_of_days);

        $stmt = $pdo->prepare("
            SELECT hs.id, hs.name, hs.expiry_date, hs.billing_term, hs.renewal_day, hs.autorenew, hs.active, ht.type
            FROM hosting_services AS hs
            LEFT JOIN hosting_types AS ht ON hs.type_id = ht.id
            WHERE hs.active <> 0
              AND hs.expiry_date <= :cutoff
            ORDER BY hs.expiry_date, hs.name");
        $stmt->bindValue('cutoff', $cutoff, \PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return self::hostingRows($rows, $this->hosting_date, $cutoff);
    }

    /*
     * Keeps the hosting service rows whose renewal date is on or before $cutoff, sorted by it (then by name), each with
     * its renewal state on $hosting_date (Hosting::renewalState()): state, and overdue and confirm saying which. Every
     * service is listed on its stored date, since DomainMOD never moves a date by itself, and one waiting for its
     * renewal to be confirmed keeps an otherwise empty email going, since it asks the reader to act.
     */
    public static function hostingRows($rows, $hosting_date, $cutoff)
    {
        $kept = array();
        foreach ($rows as $row) {
            if ((string) $row->expiry_date > $cutoff) continue;
            $row->state = Hosting::renewalState($row->active, $row->autorenew, $row->expiry_date, $row->billing_term,
                $hosting_date);
            $row->overdue = $row->state === Hosting::STATE_OVERDUE;
            $row->confirm = $row->state === Hosting::STATE_CONFIRM;
            $kept[] = $row;
        }

        usort($kept, function ($a, $b) {
            return array((string) $a->expiry_date, (string) $a->name) <=> array((string) $b->expiry_date, (string) $b->name);
        });

        return $kept;
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

    public function messageTopHtml($subject)
    {
        ob_start(); ?>
        <?php echo (new Layout())->emailHtmlTag(); ?>
        <head><?php echo self::colorSchemeTags(); ?><title><?php echo $subject; ?></title></head>
        <body bgcolor="#FFFFFF">
        <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#FFFFFF">
        <tr>
        <td width="100%" bgcolor="#FFFFFF">
        <font color="#000000" size="2" face="Verdana, Arial, Helvetica, sans-serif">
        <?php echo $this->logoHtml(); ?>
        <BR>
        <?php echo $this->introLine(); ?><BR>
        <BR>
        <?php echo sprintf(_('If you would like to change the frequency of this email notification please contact your %s administrator.'), SOFTWARE_TITLE); ?><BR>
        <BR><?php
        return ob_get_clean();
    }

    // The email's opening line, naming hosting services too once the database update has added them
    public function introLine()
    {
        if ($this->hosting_available) {
            return sprintf(ngettext('Below is a list of all the domains, hosting services, and SSL certificates in %1$s that are expiring or renewing in the next %2$s day.', 'Below is a list of all the domains, hosting services, and SSL certificates in %1$s that are expiring or renewing in the next %2$s days.', (int) $this->number_of_days), SOFTWARE_TITLE, $this->number_of_days);
        }
        return sprintf(_('Below is a list of all the Domains & SSL Certificates in %s that are expiring in the next %s days.'), SOFTWARE_TITLE, $this->number_of_days);
    }

    public function messageTopText()
    {
        $message = $this->introLine() . "\n\n";
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
                        color="#CC0000">*<?php echo Format::toUpper(_('Expired')); ?>*</font><BR><?php
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
            $message .= "[" . Format::toUpper(_('Domains')) . "]\n";
            foreach ($result_domains as $row_domains) {
                if ($row_domains->expiry_date < $timestamp_basic) {
                    $message .= $row_domains->expiry_date . " - " . $row_domains->domain . " *" . Format::toUpper(_('Expired')) . "*\n";
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
                        color="#CC0000">*<?php echo Format::toUpper(_('Expired')); ?>*</font><BR><?php
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
                    $message .= $row_ssl->expiry_date . " - " . $row_ssl->name . " (" . $row_ssl->type . ") *" . Format::toUpper(_('Expired')) . "*\n";
                } else {
                    $message .= $row_ssl->expiry_date . " - " . $row_ssl->name . " (" . $row_ssl->type . ")\n";
                }
            }
            $message .= "\n";
        }
        return $message;
    }

    // The Hosting section of the HTML email, by renewal date, with the overdue ones marked in red and those waiting for
    // their renewal to be confirmed in amber
    public function showHostingHtml($result_hosting)
    {
        ob_start();
        if ($result_hosting) { ?>
            <BR><strong><u><?php echo _('Hosting'); ?></u></strong><BR><?php
            foreach ($result_hosting as $row_hosting) {
                // Names and service types are stored HTML-escaped (as SSL certificates' are), so they're printed as is
                $hosting_name = (string) $row_hosting->name;
                if ((string) $row_hosting->type !== '') $hosting_name .= ' (' . $row_hosting->type . ')';
                if ($row_hosting->overdue) { ?>
                    <font color="#CC0000"><?php echo $row_hosting->expiry_date; ?></font>&nbsp;&nbsp;<a
                        href="<?php echo $this->full_url; ?>/hosting/edit.php?hsid=<?php echo (int) $row_hosting->id;
                        ?>"><?php echo $hosting_name; ?></a>&nbsp;&nbsp;<font
                        color="#CC0000">*<?php echo Format::toUpper(_('Overdue')); ?>*</font><BR><?php
                } elseif ($row_hosting->confirm) { ?>
                    <font color="#995C00"><?php echo $row_hosting->expiry_date; ?></font>&nbsp;&nbsp;<a
                        href="<?php echo $this->full_url; ?>/hosting/edit.php?hsid=<?php echo (int) $row_hosting->id;
                        ?>"><?php echo $hosting_name; ?></a>&nbsp;&nbsp;<font
                        color="#995C00">*<?php echo Format::toUpper(_('Confirm renewal')); ?>*</font><BR><?php
                } else { ?>
                    <?php echo $row_hosting->expiry_date; ?>&nbsp;&nbsp;<a href="<?php echo $this->full_url;
                    ?>/hosting/edit.php?hsid=<?php echo (int) $row_hosting->id; ?>"><?php echo $hosting_name; ?></a><BR><?php
                }
            }
        }
        return ob_get_clean();
    }

    // The Hosting section of the plain text email, by renewal date, with the overdue ones and those waiting for their
    // renewal to be confirmed marked
    public function showHostingText($result_hosting)
    {
        $message = '';
        if ($result_hosting) {
            $message .= "[" . _('Hosting') . "]\n";
            foreach ($result_hosting as $row_hosting) {
                // The plain text part shows the stored, HTML-escaped names as typed
                $unsanitize = new Unsanitize();
                $hosting_name = $unsanitize->text((string) $row_hosting->name);
                if ((string) $row_hosting->type !== '') $hosting_name .= " (" . $unsanitize->text((string) $row_hosting->type) . ")";
                if ($row_hosting->overdue) {
                    $message .= $row_hosting->expiry_date . " - " . $hosting_name . " *" . Format::toUpper(_('Overdue')) . "*\n";
                } elseif ($row_hosting->confirm) {
                    $message .= $row_hosting->expiry_date . " - " . $hosting_name . " *" . Format::toUpper(_('Confirm renewal')) . "*\n";
                } else {
                    $message .= $row_hosting->expiry_date . " - " . $hosting_name . "\n";
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
        <BR><hr width="100%" size="2" noshade><?php echo sprintf(_("You've received this email because you're currently subscribed to receive expiration notifications from the %s installation located at"), SOFTWARE_TITLE) . ': '; ?><a target="_blank" href="<?php echo $this->full_url; ?>/"><?php echo $this->full_url; ?>/</a><BR><BR><?php echo _('To unsubscribe from these notifications please visit') . ':'; ?><BR><a target="_blank" href="<?php echo $this->full_url; ?>/settings/profile/"><?php echo $this->full_url; ?>/settings/profile/</a><BR><BR></font>
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
        $message .= _('To unsubscribe from these notifications please visit') . ': ' . $this->full_url . "/settings/profile/";
        return $message;
    }

} //@formatter:on
