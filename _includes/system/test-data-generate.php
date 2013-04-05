<?php
// test-data-generate.php
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

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "index.php";
include("../auth/admin-user-check.inc.php");

include("../software.inc.php");
include("../config.inc.php");
include("../database.inc.php");
include("../auth/auth-check.inc.php");
include("../timestamps/current-timestamp-basic.inc.php");
include("../timestamps/current-timestamp.inc.php");

$sql = "INSERT INTO `categories` 
		(`name`, `stakeholder`, `notes`, `test_data`, `insert_time`) VALUES
		('AYS Media Domains', 'AYS Media Domain Admin', '$current_timestamp_basic - Category ''AYS Media Domains'' Added', '1', '$current_timestamp'),
		('Dummy Domains', 'Dummy Domain Admin', '$current_timestamp_basic - Category ''Dummy Domains'' Added', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "INSERT INTO `owners` 
		(`name`, `notes`, `test_data`, `insert_time`) VALUES
		('AYS Media', '$current_timestamp_basic - Owner ''AYS Media'' Added', '1', '$current_timestamp'),
		('Greg Chetcuti', '$current_timestamp_basic - Owner ''Greg Chetcuti'' Added', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "INSERT INTO `dns` 
		(`name`, `dns1`, `dns2`, `dns3`, `dns4`, `notes`, `number_of_servers`, `test_data`, `insert_time`) VALUES
		('AYS Media DNS', 'ns1.aysmedia.com', 'ns2.aysmedia.com', 'ns3.aysmedia.com', 'ns4.aysmedia.com', '$current_timestamp_basic - DNS Profile ''AYS Media DNS'' Added', '4', '1', '$current_timestamp'),
		('Parked DNS', 'ns1p.aysmedia.com', 'ns2p.aysmedia.com', '', '', '$current_timestamp_basic - DNS Profile ''Parked DNS'' Added', '2', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "INSERT INTO `registrars` 
		(`name`, `url`, `notes`, `test_data`, `insert_time`) VALUES
		('Dynadot', 'http://aysmedia.com', '$current_timestamp_basic - Registrar Account ''Dynadot'' Added', '1', '$current_timestamp'),
		('Moniker', 'http://aysmedia.ca', '$current_timestamp_basic - Registrar Account ''Moniker'' Added', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "SELECT id
		FROM registrars
		WHERE name = 'Dynadot'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $registrar_id[1] = $row->id; }

$sql = "SELECT id
		FROM registrars
		WHERE name = 'Moniker'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $registrar_id[2] = $row->id; }

$sql = "INSERT INTO `fees` 
		(`registrar_id`, `tld`, `initial_fee`, `renewal_fee`, `currency_id`, `test_data`, `fee_fixed`, `insert_time`) VALUES
		('" . $registrar_id[1] . "', 'com', '9.95', '9.95', '2', '1', '1', '$current_timestamp'),
		('" . $registrar_id[1] . "', 'ca', '9.95', '9.95', '2', '1', '1', '$current_timestamp'),
		('" . $registrar_id[2] . "', 'com', '9.95', '9.95', '2', '1', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "INSERT INTO `ssl_providers` 
		(`name`, `url`, `notes`, `test_data`, `insert_time`) VALUES
		('StartSSL', 'http://aysmedia.com', '$current_timestamp_basic - SSL Provider ''StartSSL'' Added', '1', '$current_timestamp'),
		('Verisign', 'http://aysmedia.com', '$current_timestamp_basic - SSL Provider ''Verisign'' Added', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "SELECT id
		FROM ssl_providers
		WHERE name = 'StartSSL'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_provider_id[1] = $row->id; }

$sql = "SELECT id
		FROM ssl_providers
		WHERE name = 'Verisign'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_provider_id[2] = $row->id; }

$sql = "INSERT INTO `ssl_fees` 
		(`ssl_provider_id`, `type_id`, `initial_fee`, `renewal_fee`, `currency_id`, `test_data`, `fee_fixed`, `insert_time`) VALUES
		('" . $ssl_provider_id[1] . "', '1', '200', '200', '2', '1', '1', '$current_timestamp'),
		('" . $ssl_provider_id[1] . "', '3', '50', '50', '2', '1', '1', '$current_timestamp'),
		('" . $ssl_provider_id[2] . "', '1', '20', '20', '2', '1', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "SELECT id
		FROM owners
		WHERE name = 'AYS Media'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) { $owner_id[1] = $row->id; }

$sql = "SELECT id
		FROM owners
		WHERE name = 'Greg Chetcuti'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) { $owner_id[2] = $row->id; }

$sql = "INSERT INTO `registrar_accounts` 
		(`owner_id`, `registrar_id`, `username`, `notes`, `reseller`, `test_data`, `insert_time`) VALUES
		('" . $owner_id[1] . "', '" . $registrar_id[1] . "', 'aysmedia', '$current_timestamp_basic - Registrar Account ''aysmedia'' Added', '0', '1', '$current_timestamp'),
		('" . $owner_id[2] . "', '" . $registrar_id[2] . "', 'chetcuti', '$current_timestamp_basic - Registrar Account ''chetcuti'' Added', '1', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "INSERT INTO `ssl_accounts` 
		(`owner_id`, `ssl_provider_id`, `username`, `notes`, `reseller`, `test_data`, `insert_time`) VALUES
		('" . $owner_id[1] . "', '" . $ssl_provider_id[1] . "', 'aysmedia', '$current_timestamp_basic - SSL Account ''aysmedia'' Added', '0', '1', '$current_timestamp'),
		('" . $owner_id[2] . "', '" . $ssl_provider_id[2] . "', 'chetcuti', '$current_timestamp_basic - Registrar Account ''chetcuti'' Added', '1', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "INSERT INTO `ssl_cert_types` 
		(`type`, `test_data`, `insert_time`) VALUES
		('Website Certificate', 1, '$current_timestamp'),
		('Email Certificate', 1, '$current_timestamp')";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "INSERT INTO `segments` 
		(`name`, `description`, `segment`, `number_of_domains`, `notes`, `test_data`, `insert_time`) VALUES 
		('AYS Media Segment', 'This is a test segment that includes some AYS Media domains.', '''aysmedia.com'',''aysmedia.ca'',''aysprivacy.com''', '3', '$current_timestamp_basic - Segment ''AYS Media Segment'' Added', '1', '$current_timestamp'),
		('Dummy Segment', 'This is a test segment that includes some AYS Media test domains.', '''test1-dm.com'',''test2-dm.com'',''test3-dm.com'',''test4-dm.com'',''test5-dm.com''', '5', '$current_timestamp_basic - Segment ''Dummy Segment'' Added', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "SELECT id
		FROM registrar_accounts
		WHERE username = 'aysmedia'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $registrar_account_id[1] = $row->id; }

$sql = "SELECT id
		FROM registrar_accounts
		WHERE username = 'chetcuti'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $registrar_account_id[2] = $row->id; }

$sql = "SELECT id
		FROM ssl_accounts
		WHERE username = 'aysmedia'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_account_id[1] = $row->id; }

$sql = "SELECT id
		FROM ssl_accounts
		WHERE username = 'chetcuti'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_account_id[2] = $row->id; }

$sql = "SELECT id
		FROM categories
		WHERE name = 'AYS Media Domains'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $category_id[1] = $row->id; }

$sql = "SELECT id
		FROM categories
		WHERE name = 'Dummy Domains'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $category_id[2] = $row->id; }

$sql = "SELECT id
		FROM fees
		WHERE registrar_id = '" . $registrar_id[1] . "'
		  AND tld = 'com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $fee_id[1] = $row->id; }

$sql = "SELECT id
		FROM fees
		WHERE registrar_id = '" . $registrar_id[1] . "'
		  AND tld = 'ca'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $fee_id[2] = $row->id; }

$sql = "SELECT id
		FROM fees
		WHERE registrar_id = '" . $registrar_id[2] . "'
		  AND tld = 'com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $fee_id[3] = $row->id; }

$sql = "SELECT id
		FROM ssl_fees
		WHERE ssl_provider_id = '" . $ssl_provider_id[1] . "'
		  AND type_id = '1'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_fee_id[1] = $row->id; }

$sql = "SELECT id
		FROM ssl_fees
		WHERE ssl_provider_id = '" . $ssl_provider_id[1] . "'
		  AND type_id = '3'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_fee_id[2] = $row->id; }

$sql = "SELECT id
		FROM ssl_fees
		WHERE ssl_provider_id = '" . $ssl_provider_id[2] . "'
		  AND type_id = '1'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_fee_id[3] = $row->id; }

$sql = "SELECT id
		FROM dns
		WHERE name = 'AYS Media DNS'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $dns_id[1] = $row->id; }

$sql = "SELECT id
		FROM dns
		WHERE name = 'Parked DNS'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $dns_id[2] = $row->id; }

$sql = "SELECT id
		FROM ip_addresses
		WHERE name = 'Primary Shared IP'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $temp_ip_id = $row->id; }

$sql = "INSERT INTO `domains`
		(`owner_id`, `registrar_id`, `account_id`, `domain`, `tld`, `expiry_date`, `cat_id`, `fee_id`, `dns_id`, `ip_id`, `function`, `status`, `status_notes`, `notes`, `privacy`, `active`, `test_data`, `fee_fixed`, `insert_time`) VALUES 
		('" . $owner_id[1] . "', '" . $registrar_id[1] . "', '" . $registrar_account_id[1] . "', 'aysmedia.com', 'com', '2011-01-23', '" . $category_id[1] . "', '" . $fee_id[1] . "', '" . $dns_id[1] . "', '$temp_ip_id', 'Live Site', 'Active', '$current_timestamp_basic - ''aysmedia.com'' Went Live', '$current_timestamp_basic - Domain ''aysmedia.com'' Added', '1', '1', '1', '1', '$current_timestamp'),
		('" . $owner_id[1] . "', '" . $registrar_id[1] . "', '" . $registrar_account_id[1] . "', 'aysmedia.ca', 'ca', '2011-01-24', '" . $category_id[1] . "', '" . $fee_id[2] . "', '" . $dns_id[1] . "', '$temp_ip_id', 'Redirect', 'Active (aysmedia.com)', '$current_timestamp_basic - ''aysmedia.ca'' Went Live', '$current_timestamp_basic - Domain ''aysmedia.ca'' Added', '1', '5', '1', '1', '$current_timestamp'),
		('" . $owner_id[1] . "', '" . $registrar_id[1] . "', '" . $registrar_account_id[1] . "', 'aysprivacy.com', 'com', '2011-01-25', '" . $category_id[1] . "', '" . $fee_id[1] . "', '" . $dns_id[1] . "', '$temp_ip_id', 'Redirect', 'Active (aysmedia.com)', '$current_timestamp_basic - ''aysprivacy.com'' Went Live', '$current_timestamp_basic - Domain ''aysprivacy.com'' Added', '1', '4', '1', '1', '$current_timestamp')";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "SELECT id
		FROM domains
		WHERE domain = 'aysmedia.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[1] = $row->id; }

$sql = "SELECT id
		FROM domains
		WHERE domain = 'aysmedia.ca'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[2] = $row->id; }

$sql = "SELECT id
		FROM domains
		WHERE domain = 'aysprivacy.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[3] = $row->id; }

$sql = "SELECT id
		FROM ssl_cert_types
		WHERE type = 'Website Certificate'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_type_id[1] = $row->id; }

$sql = "SELECT id
		FROM ssl_cert_types
		WHERE type = 'Email Certificate'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_type_id[2] = $row->id; }

$sql = "INSERT INTO `ssl_certs` 
		(`owner_id`, `ssl_provider_id`, `account_id`, `domain_id`, `type_id`, `name`, `expiry_date`, `fee_id`, `notes`, `active`, `test_data`, `fee_fixed`, `insert_time`) VALUES
		('" . $owner_id[1] . "', '" . $ssl_provider_id[1] . "', '" . $ssl_account_id[1] . "', '" . $domain_id[1] . "', '" . $ssl_type_id[1] . "', '*.aysmedia.com', '2011-01-23', '" . $ssl_fee_id[1] . "', '$current_timestamp_basic - SSL Certificate ''*.aysmedia.com'' Added', '1', '1', '1', '$current_timestamp'),
		('" . $owner_id[1] . "', '" . $ssl_provider_id[1] . "', '" . $ssl_account_id[1] . "', '" . $domain_id[2] . "', '" . $ssl_type_id[2] . "', 'AYS Media', '2011-01-24', '" . $ssl_fee_id[2] . "', '$current_timestamp_basic - Code Signing Certificate ''AYS Media'' Added', '5', '1', '1', '$current_timestamp'),
		('" . $owner_id[1] . "', '" . $ssl_provider_id[1] . "', '" . $ssl_account_id[1] . "', '" . $domain_id[3] . "', '" . $ssl_type_id[1] . "', '*.aysprivacy.com', '2011-01-25', '" . $ssl_fee_id[1] . "', '$current_timestamp_basic - SSL Certificate ''*.aysprivacy.com'' Added', '4', '1', '1', '$current_timestamp');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "INSERT INTO `ip_addresses` 
		(`name`, `ip`, `rdns`, `notes`, `test_data`, `insert_time`, `update_time`) VALUES
		('Primary Shared IP', '69.167.168.205', 'box.aysmedia.com', '', 1, '2013-03-26 03:07:48', '0000-00-00 00:00:00'),
		('SITE - aysmedia.ca', '69.167.168.206', 'aysmedia.ca', '', 1, '2013-03-26 03:07:48', '0000-00-00 00:00:00'),
		('SITE - aysprivacy.com', '69.167.168.207', 'aysprivacy.com', '', 1, '2013-03-26 03:07:48', '0000-00-00 00:00:00'),
		('UNASSIGNED', '69.167.168.208', '-', '', 1, '2013-03-26 03:07:48', '0000-00-00 00:00:00'),
		('UNASSIGNED', '69.167.168.209', '-', '', 1, '2013-03-26 03:07:48', '0000-00-00 00:00:00');";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "SELECT id 
		FROM ip_addresses 
		WHERE name = 'Primary Shared IP' 
		  AND insert_time = '2013-03-26 03:07:48'
		  AND test_data = '1';";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	$temp_ip_id = $row->id;
}

$sql = "UPDATE domains
		SET ip_id = '$temp_ip_id'
		WHERE domain = 'aysmedia.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "SELECT id 
		FROM ip_addresses 
		WHERE name = 'SITE - aysmedia.ca' 
		  AND insert_time = '2013-03-26 03:07:48'
		  AND test_data = '1';";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	$temp_ip_id = $row->id;
}

$sql = "UPDATE domains
		SET ip_id = '$temp_ip_id'
		WHERE domain = 'aysmedia.ca'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "SELECT id 
		FROM ip_addresses 
		WHERE name = 'SITE - aysprivacy.com' 
		  AND insert_time = '2013-03-26 03:07:48'
		  AND test_data = '1';";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	$temp_ip_id = $row->id;
}

$sql = "UPDATE domains
		SET ip_id = '$temp_ip_id'
		WHERE domain = 'aysprivacy.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());

$count = 1;

$sql = "SELECT id
		FROM ip_addresses
		WHERE name = 'Primary Shared IP'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $temp_ip_id = $row->id; }

while ($count <= $number_of_temp_domains) {
	
	$sql = "INSERT INTO domains 
			(`owner_id`, `registrar_id`, `account_id`, `domain`, `tld`, `expiry_date`, `cat_id`, `fee_id`, `dns_id`, `ip_id`, `function`, `status`, `status_notes`, `notes`, `privacy`, `test_data`, `fee_fixed`, `insert_time`) VALUES 
			('" . $owner_id[2] . "', '" . $registrar_id[2] . "', '" . $registrar_account_id[2] . "', 'test" . $count . "-dm.com', 'com', '2011-01-26', '" . $category_id[2] . "', '" . $fee_id[3] . "', '" . $dns_id[2] . "', '$temp_ip_id', 'Live Site', 'Inactive', '$current_timestamp_basic - ''test" . $count . "-dm.com'' Went Live', '$current_timestamp_basic - Domain ''test" . $count . "-dm.com'' Added', '0', '1', '1', '$current_timestamp')";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$count++;

}

$sql = "SELECT id
		FROM domains
		WHERE domain = 'test1-dm.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[4] = $row->id; }

$sql = "SELECT id
		FROM domains
		WHERE domain = 'test2-dm.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[5] = $row->id; }

$sql = "SELECT id
		FROM domains
		WHERE domain = 'test3-dm.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[6] = $row->id; }

$sql = "SELECT id
		FROM domains
		WHERE domain = 'test4-dm.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[7] = $row->id; }

$sql = "SELECT id
		FROM domains
		WHERE domain = 'test5-dm.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[8] = $row->id; }

$sql = "SELECT id
		FROM domains
		WHERE domain = 'test6-dm.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[9] = $row->id; }

$sql = "SELECT id
		FROM domains
		WHERE domain = 'test7-dm.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[10] = $row->id; }

$sql = "SELECT id
		FROM domains
		WHERE domain = 'test8-dm.com'
		  AND test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[11] = $row->id; }

$count = 1;
$count_secondary = 4;

while ($count <= 8) {

	$sql = "INSERT INTO `ssl_certs` 
			(`owner_id`, `ssl_provider_id`, `account_id`, `domain_id`, `type_id`, `name`, `expiry_date`, `fee_id`, `notes`, `test_data`, `fee_fixed`, `insert_time`) VALUES 
			('" . $owner_id[2] . "', '" . $ssl_provider_id[2] . "', '" . $ssl_account_id[2] . "', '" . $domain_id[$count_secondary] . "', '" . $ssl_type_id[1] . "', 'test" . $count . "-dm.com', '2011-01-26', '" . $ssl_fee_id[3] . "', '$current_timestamp_basic - SSL Certificate ''test" . $count . "-dm.com'' Added', '1', '1', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$count++;
	$count_secondary++;

}

include("../auth/login-checks/domain-and-ssl-asset-check.inc.php");

if ($_SESSION['session_installation_mode'] == 1 || $_SESSION['session_first_login'] == 1) {
	
	$_SESSION['session_first_login'] = 0;
	header("Location: ../../index.php");
	exit;
	
} else {

	$_SESSION['session_result_message'] = "The test data has been generated<BR>";

	header("Location: ../../system/index.php");
	exit;

}
?>