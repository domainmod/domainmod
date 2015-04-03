<?php
/**
 * /cron/api/dw.whm.inc.php
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
$query = $row_server->protocol . "://" . $row_server->host . ":" . $row_server->port . $api_call;
$curl = curl_init();																								# Create Curl Object
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);																		# Allow certs that do not match the domain
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);																		# Allow self-signed certs
curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);																		# Return contents of transfer on curl_exec
$header[0] = "Authorization: WHM " . $row_server->username . ":" . preg_replace("'(\r|\n)'","",$row_server->hash);	# Remove newlines from the hash
curl_setopt($curl,CURLOPT_HTTPHEADER,$header);																		# Set curl header
curl_setopt($curl, CURLOPT_URL, $query);																			# Set your URL
$result = curl_exec($curl);																							# Execute Query, assign to $result
if ($result == false) {
	error_log("curl_exec error \"" . curl_error($curl) . "\" for " . $query . "");
}
curl_close($curl);
?>
