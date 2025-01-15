<?php
/**
 * /_includes/email/send-test-email.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2025 Greg Chetcuti <greg@greg.ca>
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
?>
<?php
$email = new DomainMOD\Email();

list($full_url, $null_variable1, $null_variable2, $null_variable3, $null_variable4) = $email->getSettings();
list($first_name_sig, $last_name_sig, $email_address_sig) = $email->getEmailSignature();

$to_address = $email_address;
$from_name = SOFTWARE_TITLE;

$subject = _('Test Email');
$headline = $subject;

$message_html = $message_html ?? '';
$message_html .= "
<html>
<head><title>" . $headline . "</title></head>
<body bgcolor=\"#FFFFFF\">
<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\">
    <tr>
        <td width=\"100%\" bgcolor=\"#FFFFFF\">

            <table width=\"575\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\"
                bordercolor=\"#FFFFFF\">
                <tr>
                    <td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
                    <td width=\"92%\"><font color=\"#000000\" size=\"2\" face=\"Verdana, Arial, Helvetica,
                        sans-serif\"><BR>";
$message_html .= "<a title=\"" . SOFTWARE_TITLE . "\" href=\"" . $full_url . "/\"><img alt=\"" .
    SOFTWARE_TITLE . "\" border=\"0\" src=\"" . $full_url . "/images/logo.png\"></a><BR><BR>";
$message_html .= sprintf(_("This is a test email to ensure that your %s installation is able to send email successfully. "), SOFTWARE_TITLE);
$message_html .= sprintf(_("If you did not request this from your account, you should report it to your %s Administrator."), SOFTWARE_TITLE) . "<BR>";
$message_html .= "<BR>";
$message_html .= "Installation URL: <a href=\"" . $full_url . "\">" . $full_url . "</a><BR>";
$message_html .= "<BR>";
$message_html .= _('Best Regards') . ",<BR>";
$message_html .= "<BR>";
$message_html .= $first_name_sig . ' ' . $last_name_sig . "<BR>";
$message_html .= "<a target=\"_blank\"
                            href=\"mailto:" . $email_address_sig . "\">" . $email_address_sig . "</a><BR>";
$message_html .= "<BR>";
$message_html .= "</font>
                    </td>
                    <td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
                </tr>
            </table>

            <table width=\"575\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\"
                bordercolor=\"#FFFFFF\">
                <tr>
                    <td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
                    <td width=\"92%\"><font color=\"#000000\" size=\"2\" face=\"Verdana, Arial, Helvetica,
                        sans-serif\">";
$message_html .= "<hr width=\"100%\" size=\"2\" noshade>";
$message_html .= _("You've received this notification because someone requested a test email be sent from your account.");
$message_html .= "</font>
                    </td>
                    <td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
</body>
</html>";

$message_text = $message_text ?? '';
$message_text .= sprintf(_("This is a test email to ensure that your %s installation is able to send email successfully. "), SOFTWARE_TITLE);
$message_text .= sprintf(_("If you did not request this from your account, you should report it to your %s Administrator."), SOFTWARE_TITLE) . "\n\n";
$message_text .= "Installation URL: " . $full_url . "\n\n";
$message_text .= _('Best Regards') . ",\n";
$message_text .= "\n";
$message_text .= $first_name_sig . ' ' . $last_name_sig . "\n";
$message_text .= $email_address_sig . "\n";
$message_text .= "\n---\n\n";
$message_text .= _("You've received this notification because someone requested a test email be sent from your account.") . "\n\n";

$email->send(_('DomainMOD Test Email'), $to_address, $subject, $message_html, $message_text);