<?php
/**
 * /cron/expirations.php
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
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp-basic.inc.php");
include("../_includes/timestamps/current-timestamp-long.inc.php");

include("../_includes/config-demo.inc.php");

if ($demo_install != "1") {

	$sql = "SELECT expiration_email_days
			FROM settings";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		$number_of_days = $row->expiration_email_days;
	}
	
	include("../_includes/timestamps/current-timestamp-basic-plus-X-days.inc.php");
	
	$sql_settings = "SELECT full_url, email_address
					 FROM settings";
	$result_settings = mysqli_query($connection, $sql_settings);
	
	while ($row_settings = mysqli_fetch_object($result_settings)) {
		$full_url = $row_settings->full_url;
		$from_address = $row_settings->email_address;
		$return_path = $row_settings->email_address;
	}
	
	$sql_domains = "SELECT id, expiry_date, domain
					FROM domains
					WHERE active NOT IN ('0', '10')
					  AND expiry_date <= '$current_timestamp_basic_plus_x_days'
					ORDER BY expiry_date, domain";
	$result_domains = mysqli_query($connection, $sql_domains);
	
	$sql_ssl = "SELECT sslc.id, sslc.expiry_date, sslc.name, sslt.type
				FROM ssl_certs AS sslc, ssl_cert_types AS sslt
				WHERE sslc.type_id = sslt.id
				  AND sslc.active NOT IN ('0')
				  AND sslc.expiry_date <= '$current_timestamp_basic_plus_x_days'
				ORDER BY sslc.expiry_date, sslc.name";
	$result_ssl = mysqli_query($connection, $sql_ssl);
	
	$sql_recipients = "SELECT u.email_address
					   FROM users AS u, user_settings AS us
					   WHERE u.id = us.user_id
						 AND u.active = '1'
						 AND us.expiration_emails = '1'";
	$result_recipients = mysqli_query($connection, $sql_recipients);
	
	if ((mysqli_num_rows($result_domains) != 0 || mysqli_num_rows($result_ssl) != 0) && mysqli_num_rows($result_recipients) > 0) {
	
		while ($row_recipients = mysqli_fetch_object($result_recipients)) {
	
			$subject = "Upcoming Expirations - $current_timestamp_long";
			$headline = "Upcoming Expirations - $current_timestamp_long";
			
			$headers = "";
			$headers .= "MIME-Version: 1.0" . "\r\n";
			$headers .= "Content-type: text/html; charset=iso-8859-1" . "\r\n";
			$headers .= "From: \"" . $software_title . "\" <" . $from_address . ">\n";
			$headers .= "Return-Path: <" . $return_path . ">\n";
			$message .= "
			<html>
			<head><title>" . $headline . "</title></head>
			<body bgcolor=\"#FFFFFF\">
			<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\">
				<tr>
					<td width=\"100%\" bgcolor=\"#FFFFFF\">";
						$message .= "<font color=\"#000000\" size=\"2\" face=\"Verdana, Arial, Helvetica, sans-serif\">";
						$message .= "<a title=\"" . $software_title . "\" href=\"" . $full_url . "/\"><img alt=\"" . $software_title . "\" border=\"0\" src=\"" . $full_url . "/images/logo.png\"></a><BR><BR>";
						$message .= "Below is a list of all the Domains & SSL Certificates in " . $software_title . " that are expiring in the next " . $number_of_days . " days. <BR>";
						$message .= "<BR>If you would like to change the frequency of this email notification please contact your " . $software_title . " administrator. <BR>";
						$message .= "<BR>";
				
						if (mysqli_num_rows($result_domains) != 0) {
						
							$message .= "<strong><u>Domains</u></strong><BR>";
							while ($row_domains = mysqli_fetch_object($result_domains)) {
								
								if ($row_domains->expiry_date < $current_timestamp_basic) {
							
									$message .= "<font color=\"#CC0000\">" . $row_domains->expiry_date . "</font>&nbsp;&nbsp;<a href=\"" . $row_domains->domain . "\">" . $row_domains->domain . "</a> [<a href=\"" . $full_url . "/edit/domain.php?did=" . $row_domains->id . "\">edit</a>]&nbsp;&nbsp;<font color=\"#CC0000\">*EXPIRED*</font><BR>";
							
								} else {
							
									$message .= $row_domains->expiry_date . "&nbsp;&nbsp;<a href=\"" . $row_domains->domain . "\">" . $row_domains->domain . "</a> [<a href=\"" . $full_url . "/edit/domain.php?did=" . $row_domains->id . "\">edit</a>]<BR>";
							
								}
							
							}
							
						}
				
						if (mysqli_num_rows($result_ssl) != 0) {
						
							$message .= "<BR><strong><u>SSL Certificates</u></strong><BR>";
							while ($row_ssl = mysqli_fetch_object($result_ssl)) {
								
								if ($row_ssl->expiry_date < $current_timestamp_basic) {
							
									$message .= "<font color=\"#CC0000\">" . $row_ssl->expiry_date . "</font>&nbsp;&nbsp;" . $row_ssl->name . " (" . $row_ssl->type . ") [<a href=\"" . $full_url . "/edit/ssl-cert.php?sslcid=" . $row_ssl->id . "\">edit</a>]&nbsp;&nbsp;<font color=\"#CC0000\">*EXPIRED*</font><BR>";
							
								} else {
							
									$message .= "" . $row_ssl->expiry_date . "&nbsp;&nbsp;" . $row_ssl->name . " (" . $row_ssl->type . ") [<a href=\"" . $full_url . "/edit/ssl-cert.php?sslcid=" . $row_ssl->id . "\">edit</a>]<BR>";
							
								}
							
							}
							
						}

                        $message .= "<BR>";
                        $message .= "Best Regards,<BR>";
                        $message .= "<BR>";
                        $message .= "Greg Chetcuti<BR>";
                        $message .= "<a target=\"_blank\" href=\"mailto:greg@domainmod.org\">greg@domainmod.org</a><BR>";
						$message .= "</font>
					</td>
				</tr>
			</table>
			
			<table width=\"575\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" bgcolor=\"#FFFFFF\" bordercolor=\"#FFFFFF\">
				<tr>
					<td width=\"100%\"><font color=\"#000000\" size=\"1\" face=\"Verdana, Arial, Helvetica, sans-serif\">";
						$message .= "<BR><hr width=\"100%\" size=\"1\" noshade>";
						$message .= "You've received this email because you're currently subscribed to receive expiration ";
						$message .= "notifications from the $software_title installation located at: <a target=\"_blank\" href=\"" . $full_url . "/\">" . $full_url . "/</a><BR>";
						$message .= "<BR>";
						$message .= "To unsubscribe from these notifications please visit: <BR>";
						$message .= "<a target=\"_blank\" href=\"" . $full_url . "/system/email-settings.php\">" . $full_url . "/system/email-settings.php</a><BR>";
						$message .= "<BR></font>
					</td>
				</tr>
			</table>
			</body>
			</html>";
	
			mail("$row_recipients->email_address", "$subject", "$message", "$headers");
	
		}
	
	}
	
}
