<?php
/**
 * /_includes/email/send-two-factor-notice.inc.php
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
?>
<?php
/*
 * Tells a user about a change to their two-factor sign-in, so they can act if it wasn't them. The page sets
 * $two_factor_event ('on', 'off', 'reset', or 'locked'), $two_factor_required (whether it's required for all users),
 * $first_name, $last_name, $username, and $email_address.
 */
$email = new DomainMOD\Email();

list($full_url, $null_variable1, $null_variable2, $null_variable3, $null_variable4) = $email->getSettings();
list($first_name_sig, $last_name_sig, $email_address_sig) = $email->getEmailSignature();

$to_address = $email_address;
$from_name = SOFTWARE_TITLE;

if ($two_factor_event === 'on') {

    $subject = sprintf(_('MFA Was Turned On for Your %s Account'), SOFTWARE_TITLE);
    $notice_body = _('A new authenticator app was set up for MFA on your account, with a new set of recovery codes.');

} elseif ($two_factor_event === 'off') {

    $subject = sprintf(_('MFA Was Turned Off for Your %s Account'), SOFTWARE_TITLE);
    $notice_body = _('MFA was turned off for your account, so it now signs in with the password alone.');

} elseif ($two_factor_event === 'reset') {

    $subject = sprintf(_('MFA Was Reset for Your %s Account'), SOFTWARE_TITLE);
    $notice_body = $two_factor_required
        ? _("An administrator reset MFA for your account. You'll set it up again with a new authenticator the next time you sign in, since it's required for all users.")
        : _('An administrator reset MFA for your account, so it now signs in with the password alone. You can set it up again under Settings.');

} else {

    $subject = sprintf(_('MFA Was Locked for Your %s Account'), SOFTWARE_TITLE);
    $notice_body = sprintf(_('%d wrong MFA codes were entered for your account, so codes won\'t be checked for the next %d minutes.'),
        DomainMOD\TwoFactor::MAX_FAILURES, DomainMOD\TwoFactor::LOCK_SECONDS / 60);

}

$notice_warning = _("If this wasn't you, someone may know your password, so change it as soon as you can and contact your administrator.");
$headline = $subject;

$message_html = "
" . (new DomainMOD\Layout())->emailHtmlTag() . "
<head>" . DomainMOD\Email::colorSchemeTags() . "<title>" . $headline . "</title></head>
<body bgcolor=\"#FFFFFF\">
<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\">
    <tr>
        <td width=\"100%\" bgcolor=\"#FFFFFF\">

            <table width=\"575\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\"
                bordercolor=\"#FFFFFF\">
                <tr>
                    <td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
                    <td width=\"92%\"><font color=\"#000000\" size=\"2\" face=\"Verdana, Arial, Helvetica,
                        sans-serif\">";
$message_html .= $email->logoHtml() . "<BR>";
$message_html .= $notice_body . '<BR>';
$message_html .= "<BR>";
$message_html .= _('URL') . ": <a title=\"" . SOFTWARE_TITLE . "\" target=\"_blank\" href=\"" . $full_url . "/\">" .
    $full_url . "/</a><BR>";
$message_html .= _('Your Username') . ': ' . $username . '<BR>';
$message_html .= "<BR>";
$message_html .= $notice_warning . '<BR>';
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

        </td>
    </tr>
</table>
</body>
</html>";

$message_text = $headline . "\n\n";
$message_text .= $notice_body . "\n\n";
$message_text .= "URL: " . $full_url . "\n";
$message_text .= _('Your Username') . ": " . $username . "\n";
$message_text .= "\n";
$message_text .= $notice_warning . "\n";
$message_text .= "\n";
$message_text .= _('Best Regards') . ",\n";
$message_text .= "\n";
$message_text .= $first_name_sig . ' ' . $last_name_sig . "\n";
$message_text .= $email_address_sig . "\n";

$email->send(_('MFA'), $to_address, $subject, $message_html, $message_text);
