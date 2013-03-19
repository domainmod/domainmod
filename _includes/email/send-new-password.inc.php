<?php
// send-new-password.inc.php
// 
// Domain Manager - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// Domain Manager is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// Domain Manager is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with Domain Manager. If not, please 
// see http://www.gnu.org/licenses/
?>
<?php
session_start();

$to = $row->email_address;
$from_name = $software_title;
$from_address = $row->email_address;
$return_path = $row->email_address;

$headers = "";
$headers .= "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type: text/html; charset=iso-8859-1" . "\r\n";
$headers .= "From: \"" . $from_name . "\" <" . $from_address . ">\n";
$headers .= "Return-Path: <" . $return_path . ">\n";  // Return path for errors

$subject = "Your " . $software_title . " Password has been Reset";
$headline = "Your " . $software_title . " Password has been Reset";

$message .= "
<html>
<head>
  <title>" . $headline . "</title>
</head>
<body bgcolor=\"#FFFFFF\">
<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\">
<tr>
<td width=\"100%\" bgcolor=\"#FFFFFF\">
<table width=\"575\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\" bordercolor=\"#FFFFFF\">
	<tr>
		<td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
		<td width=\"92%\"><font color=\"#000000\" size=\"2\" face=\"Verdana, Arial, Helvetica, sans-serif\">";

			$message .= "<BR>";
			$message .= "<strong>" . $headline . "</strong><BR>";
			$message .= "<BR>";
			$message .= "Your password has been reset and you can find it below. The next ";
			$message .= "time you login you should change your password to something that ";
			$message .= "will be easier for you to remember, but still hard for someone ";
			$message .= "else to guess.<BR>";
			$message .= "<BR>";

			if ($web_root == "/") {

				$message .= "URL: <a target=\"_blank\" href=\"" . $_SERVER["HTTP_ORIGIN"] . "\">" . $_SERVER["HTTP_ORIGIN"] . "</a><BR>";
				
			} else {

				$message .= "URL: <a target=\"_blank\" href=\"" . $_SERVER["HTTP_ORIGIN"] . $web_root . "/\">" . $_SERVER["HTTP_ORIGIN"] . $web_root . "/</a><BR>";

			}

			$message .= "<BR>";
			$message .= "Your Username: $row->username<BR>";
			$message .= "Your New Password: $new_password<BR>";
			$message .= "<BR>";
			$message .= "Best Regards,<BR>";
			$message .= "<BR>";
			$message .= "AYS Media<BR>";
			$message .= "<a target=\"_blank\" href=\"http://aysmedia.com\">http://aysmedia.com</a><BR>";
			$message .= "<a target=\"_blank\" href=\"mailto:code@aysmedia.com\">code@aysmedia.com</a><BR>";
			$message .= "<BR>";
			$message .= "</font></td>

		<td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
		</tr>
		</table>
<table width=\"575\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\" bordercolor=\"#FFFFFF\">
	<tr>
		<td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
		<td width=\"92%\"><font color=\"#000000\" size=\"1\" face=\"Verdana, Arial, Helvetica, sans-serif\">";

			$message .= "<hr width=\"100%\" size=\"1\" noshade><BR>";
			$message .= "You've received this notification because someone requested a password reset for your ";
			$message .= $software_title . " account.<BR>";
			$message .= "<BR>";
			$message .= "If you did not request this yourself, it sounds like somebody might be trying to gain access ";
			$message .= "to your account. This might be a good time to reset your password again just to be safe. <BR>";
			$message .= "<a target=\"_blank\" href=\"" . $_SERVER["HTTP_REFERER"] . "\">" . $_SERVER["HTTP_REFERER"] . "</a>";
			
			$message .= "<BR></font></td>

		<td width=\"4%\" valign=\"top\" align=\"left\">&nbsp;</td>
		</tr>
		</table>
		</td>
		</tr>
		</table>
		</body></html>";

mail("$to", "$subject", "$message", "$headers");
?>