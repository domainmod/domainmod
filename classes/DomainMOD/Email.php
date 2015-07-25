<?php
/**
 * /classes/DomainMOD/Email.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
?>
<?php
namespace DomainMOD;

class Email
{

    public function sendExpirations($connection, $software_title, $from_cron)
    {
        $time = new Timestamp();
        $timestamp_basic = $time->timeBasic();
        $timestamp_long = $time->timeLong();
        list($full_url, $from_address, $number_of_days) = $this->getSettings($connection);
        list($result_domains, $result_ssl) = $this->checkExpiring($connection, $number_of_days, $from_cron);
        $send_to = $this->getRecipients($connection);
        $subject = "Upcoming Expirations - " . $timestamp_long;
        $headers = $this->getHeaders($software_title, $from_address);

        while ($row_recipients = mysqli_fetch_object($send_to)) {
            $message = '';
            $message .= $this->messageTop($software_title, $full_url, $subject, $number_of_days);
            $message .= $this->showDomains($result_domains, $full_url, $timestamp_basic);
            $message .= $this->showSsl($result_ssl, $full_url, $timestamp_basic);
            $message .= $this->messageBottom($software_title, $full_url);
            $full_to = "\"$row_recipients->first_name $row_recipients->last_name\" <$row_recipients->email_address>";
            mail($full_to, $subject, $message, $headers, "-f $from_address");
            sleep(2);
        }
    }

    public function getSettings($connection)
    {
        $sql = "SELECT full_url, email_address, expiration_email_days FROM settings";
        $result = mysqli_query($connection, $sql);
        $url = '';
        $email = '';
        $days = '';
        while ($row = mysqli_fetch_object($result)) {
            $url = $row->full_url;
            $email = $row->email_address;
            $days = $row->expiration_email_days;
        }
        return array($url, $email, $days);
    }

    public function checkExpiring($connection, $days, $from_cron)
    {
        $system = new System();
        $time = new Timestamp();
        $date = $time->timeBasicPlusDays($days);

        $sql_domains = "SELECT id, expiry_date, domain
                        FROM domains
                        WHERE active NOT IN ('0', '10')
                          AND expiry_date <= '" . $date . "'
                        ORDER BY expiry_date, domain";
        $domains_expiring = $system->checkForRowsResult($connection, $sql_domains);

        $sql_ssl = "SELECT sslc.id, sslc.expiry_date, sslc.name, sslt.type
                    FROM ssl_certs AS sslc, ssl_cert_types AS sslt
                    WHERE sslc.type_id = sslt.id
                      AND sslc.active NOT IN ('0')
                      AND sslc.expiry_date <= '" . $date . "'
                    ORDER BY sslc.expiry_date, sslc.name";
        $ssl_expiring = $system->checkForRowsResult($connection, $sql_ssl);

        if ($domains_expiring != '0' || $ssl_expiring != '0') {
            $_SESSION['result_message'] .= 'Expiration Email Sent<BR>';
            return array($domains_expiring, $ssl_expiring);
        } else {
            $_SESSION['result_message'] .= 'No Upcoming Expirations<BR>';
            if ($from_cron == '1') exit;
            return false;
        }
    }

    public function getRecipients($connection)
    {
        $sql_recipients = "SELECT u.email_address, u.first_name, u.last_name
                           FROM users AS u, user_settings AS us
                           WHERE u.id = us.user_id
                             AND u.active = '1'
                             AND us.expiration_emails = '1'";
        $result_recipients = mysqli_query($connection, $sql_recipients);

        if (mysqli_num_rows($result_recipients) <= 0) {
            $_SESSION['result_message'] .= 'No Users Are Subscribed<BR>';
            return false;
        }
        return $result_recipients;
    }

    public function getHeaders($software_title, $from_address)
    {
        $headers = '';
        $version = phpversion();
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'From: "' . $software_title . '" <' . $from_address . ">\r\n";
        $headers .= 'Return-Path: ' . $from_address . "\r\n";
        $headers .= 'Reply-to: ' . $from_address . "\r\n";
        $headers .= 'X-Mailer: PHP/' . $version . "\r\n";
        return $headers;
    }

    public function messageTop($software_title, $full_url, $subject, $number_of_days)
    {
        ob_start(); //@formatter:off ?>
        <html>
        <head><title><?php echo $subject; ?></title></head>
        <body bgcolor="#FFFFFF">
        <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#FFFFFF">
        <tr>
        <td width="100%" bgcolor="#FFFFFF">
        <font color="#000000" size="2" face="Verdana, Arial, Helvetica, sans-serif">
        <a title="<?php echo $software_title; ?>" href="<?php echo $full_url; ?>/"><img border="0" alt="<?php
            echo $software_title; ?>" src="<?php echo $full_url; ?>/images/logo.png"></a><BR><BR>Below is a
        list of all the Domains & SSL Certificates in <?php echo $software_title; ?> that are expiring in the next
        <?php echo $number_of_days; ?> days.<BR> <BR>If you would like to change the frequency of this email
        notification please contact your <?php echo $software_title; ?> administrator.<BR><BR><?php
        return ob_get_clean(); //@formatter:on
    }

    public function showDomains($result_domains, $full_url, $timestamp_basic)
    {
        ob_start();
        if ($result_domains != '0') { ?>
            <strong><u>Domains</u></strong><BR><?php
            while ($row_domains = mysqli_fetch_object($result_domains)) {
                if ($row_domains->expiry_date < $timestamp_basic) { ?>
                    <font color="#CC0000"><?php echo $row_domains->expiry_date; ?></font>&nbsp;&nbsp;<a
                        href="<?php echo $full_url; ?>/edit/domain.php?did=<?php echo $row_domains->id;
                        ?>"><?php echo $row_domains->domain; ?></a>&nbsp;&nbsp;<font
                        color="#CC0000">*EXPIRED*</font><BR><?php
                } else { ?>
                    <?php echo $row_domains->expiry_date; ?>&nbsp;&nbsp;<a href="<?php echo $full_url;
                    ?>/edit/domain.php?did=<?php echo $row_domains->id; ?>"><?php echo $row_domains->domain;
                        ?></a><BR><?php
                }
            }
        }
        return ob_get_clean();
    }

    public function showSsl($result_ssl, $full_url, $timestamp_basic)
    {
        ob_start();
        if ($result_ssl != '0') { ?>
            <BR><strong><u>SSL Certificates</u></strong><BR><?php
            while ($row_ssl = mysqli_fetch_object($result_ssl)) {
                if ($row_ssl->expiry_date < $timestamp_basic) { ?>
                    <font color="#CC0000"><?php echo $row_ssl->expiry_date; ?></font>&nbsp;&nbsp;<a
                        href="<?php echo $full_url; ?>/edit/ssl-cert.php?sslcid=<?php echo $row_ssl->id;
                        ?>"><?php echo $row_ssl->name; ?> (<?php echo $row_ssl->type; ?>)</a>&nbsp;&nbsp;<font
                        color="#CC0000">*EXPIRED*</font><BR><?php
                } else { ?>
                    <?php echo $row_ssl->expiry_date; ?>&nbsp;&nbsp;<a href="<?php echo $full_url;
                    ?>/edit/ssl-cert.php?sslcid=<?php echo $row_ssl->id; ?>"><?php echo $row_ssl->name; ?>
                        (<?php echo $row_ssl->type; ?>)</a><BR><?php
                }
            }
        }
        return ob_get_clean();
    }

    public function messageBottom($software_title, $full_url)
    {
        ob_start(); //@formatter:off ?>
        <BR>Best Regards,<BR><BR>Greg Chetcuti<BR><a
            target="_blank" href="mailto:greg@domainmod.org">greg@domainmod.org</a><BR>
        </font>
        </td></tr>
        </table>
        <table width="575" cellspacing="0" cellpadding="0" border="0" bgcolor="#FFFFFF"><tr>
        <td width="100%"><font color="#000000" size="1" face="Verdana, Arial, Helvetica, sans-serif">
        <BR><hr width="100%" size="1" noshade>You've received this email because you're currently subscribed to receive
        expiration notifications from the <?php echo $software_title; ?> installation located at: <a target="_blank"
        href="<?php echo $full_url; ?>/"><?php echo $full_url; ?>/</a><BR><BR>To unsubscribe from these notifications
        please visit: <BR><a target="_blank" href="<?php echo $full_url; ?>/settings/email/"><?php echo $full_url;
        ?>/settings/email/</a><BR><BR></font>
        </td></tr>
        </table>
        </body>
        </html><?php
        return ob_get_clean();
    }

}
