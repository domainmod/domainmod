<?php
/**
 * /_includes/email/send-new-password.inc.php
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
$sql_settings = "SELECT full_url, email_address
				 FROM settings";
$result_settings = mysqli_query($connection, $sql_settings) or die(mysqli_error());

while ($row_settings = mysqli_fetch_object($result_settings)) {

    $full_url = $row_settings->full_url;
    $from_address = $row_settings->email_address;
    $return_path = $row_settings->email_address;

}

$to = $row->email_address;
$from_name = $software_title;

$subject = "Your " . $software_title . " Password has been Reset";
$headline = "Your " . $software_title . " Password has been Reset";

$headers = "";
$headers .= "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type: text/html; charset=iso-8859-1" . "\r\n";
$headers .= "From: \"" . $from_name . "\" <" . $from_address . ">\n";
$headers .= "Return-Path: <" . $return_path . ">\n";  // Return path for errors

$message .= "
<html>
<head><title>" . $headline . "</title></head>
<body bgcolor=\"#FFFFFF\">
<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\">
	<tr>
		<td width=\"100%\" bgcolor=\"#FFFFFF\">

			<table width=\"575\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\" bordercolor=\"#FFFFFF\">
				<tr>
					<td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
					<td width=\"92%\"><font color=\"#000000\" size=\"2\" face=\"Verdana, Arial, Helvetica, sans-serif\">";
						$message .= "<BR>";
						$message .= "<a title=\"" . $software_title . "\" href=\"" . $full_url . "/\"><img alt=\"" . $software_title . "\" border=\"0\" src=\"" . $full_url . "/images/logo.png\"></a><BR><BR>";
						$message .= "Your password has been reset and you can find it below. The next ";
						$message .= "time you login you should change your password to something that ";
						$message .= "will be easier for you to remember, but still hard for someone ";
						$message .= "else to guess.<BR>";
						$message .= "<BR>";
						$message .= "URL: <a title=\"DomainMOD\" target=\"_blank\" href=\"" . $full_url . "/\">" . $full_url . "/</a><BR>";
						$message .= "<BR>";
						$message .= "Your Username: $row->username<BR>";
						$message .= "Your New Password: $new_password<BR>";
						$message .= "<BR>";
						$message .= "Best Regards,<BR>";
						$message .= "<BR>";
						$message .= "Greg Chetcuti<BR>";
						$message .= "<a target=\"_blank\" href=\"mailto:greg@domainmod.org\">greg@domainmod.org</a><BR>";
						$message .= "<BR>";
						$message .= "</font>
					</td>
					<td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
				</tr>
			</table>

			<table width=\"575\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\" bordercolor=\"#FFFFFF\">
				<tr>
					<td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
					<td width=\"92%\"><font color=\"#000000\" size=\"1\" face=\"Verdana, Arial, Helvetica, sans-serif\">";
						$message .= "<hr width=\"100%\" size=\"1\" noshade>";
						$message .= "You've received this notification because someone requested a password reset for your ";
						$message .= $software_title . " account.<BR>";
						$message .= "<BR>";
						$message .= "If you did not request this yourself, it sounds like somebody might be trying to gain access ";
						$message .= "to your account. This might be a good time to reset your password again just to be safe. <BR>";
						$message .= "<a target=\"_blank\" href=\"" . $full_url . "/reset-password.php\">" . $full_url . "/reset-password.php</a>";
						$message .= "<BR></font>
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

mail("$to", "$subject", "$message", "$headers");
