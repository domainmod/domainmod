<?php
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

include("../software.inc.php");
include("../config.inc.php");
include("../database.inc.php");
// include("../auth/auth-check.inc.php");
include("../timestamps/current-timestamp.inc.php");
include("../timestamps/current-timestamp-basic.inc.php");

$sql = "
INSERT INTO `categories` (`name`, `notes`, `test_data`, `insert_time`) VALUES
('AYS Media Domains', '$current_timestamp_date_only - Category ''AYS Media Domains'' Added', '1', '$current_timestamp'),
('Dummy Domains', '$current_timestamp_date_only - Category ''Dummy Domains'' Added', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());
$sql = "
INSERT INTO `companies` (`name`, `notes`, `test_data`, `insert_time`) VALUES
('AYS Media', '$current_timestamp_date_only - Company ''AYS Media'' Added', '1', '$current_timestamp'),
('Dummy Media', '$current_timestamp_date_only - Company ''Dummy Media'' Added', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "
INSERT INTO `dns` (`name`, `dns1`, `dns2`, `dns3`, `dns4`, `notes`, `number_of_servers`, `test_data`, `insert_time`) VALUES
('AYS Media DNS', 'ns1o.aysmedia.com', 'ns2o.aysmedia.com', '', '', '$current_timestamp_date_only - DNS Profile ''AYS Media DNS'' Added', '2', '1', '$current_timestamp'),
('Dummy DNS', 'ns1d.aysmedia.com', 'ns2d.aysmedia.com', 'ns3d.aysmedia.com', 'ns4d.aysmedia.com', '$current_timestamp_date_only - DNS Profile ''Dummy DNS'' Added', '4', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "
INSERT INTO `registrars` (`name`, `url`, `notes`, `test_data`, `insert_time`) VALUES
('AYS Media Registrar', 'http://aysmedia.com', '$current_timestamp_date_only - Registrar Account ''AYS Media Registrar'' Added', '1', '$current_timestamp'),
('Dummy Registrar', 'http://aysmedia.ca', '$current_timestamp_date_only - Registrar Account ''Dummy Registrar'' Added', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "select id
		from registrars
		where name = 'AYS Media Registrar'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $registrar_id[1] = $row->id; }

$sql = "select id
		from registrars
		where name = 'Dummy Registrar'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $registrar_id[2] = $row->id; }

$sql = "
INSERT INTO `fees` (`registrar_id`, `tld`, `initial_fee`, `renewal_fee`, `currency_id`, `test_data`, `fee_fixed`, `insert_time`) VALUES
('" . $registrar_id[1] . "', 'com', '9.95', '9.95', '20', '1', '1', '$current_timestamp'),
('" . $registrar_id[1] . "', 'ca', '9.95', '9.95', '20', '1', '1', '$current_timestamp'),
('" . $registrar_id[2] . "', 'com', '9.95', '9.95', '20', '1', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "
INSERT INTO `ssl_providers` (`name`, `url`, `notes`, `test_data`, `insert_time`) VALUES
('AYS Media SSL', 'http://aysmedia.com', '$current_timestamp_date_only - SSL Provider ''AYS Media SSL'' Added', '1', '$current_timestamp'),
('Dummy SSL', 'http://aysmedia.com', '$current_timestamp_date_only - SSL Provider ''Dummy SSL'' Added', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "select id
		from ssl_providers
		where name = 'AYS Media SSL'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_provider_id[1] = $row->id; }

$sql = "select id
		from ssl_providers
		where name = 'Dummy SSL'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_provider_id[2] = $row->id; }

$sql = "
INSERT INTO `ssl_fees` (`ssl_provider_id`, `type`, `initial_fee`, `renewal_fee`, `currency_id`, `test_data`, `fee_fixed`, `insert_time`) VALUES
('" . $ssl_provider_id[1] . "', 'Wildcard', '200', '200', '20', '1', '1', '$current_timestamp'),
('" . $ssl_provider_id[2] . "', 'Single Host', '20', '20', '20', '1', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "select id
		from companies
		where name = 'AYS Media'
		and test_data = '1'";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) { $company_id[1] = $row->id; }

$sql = "select id
		from companies
		where name = 'Dummy Media'
		and test_data = '1'";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) { $company_id[2] = $row->id; }

$sql = "
INSERT INTO `registrar_accounts` (`company_id`, `registrar_id`, `username`, `notes`, `reseller`, `test_data`, `insert_time`) VALUES
('" . $company_id[1] . "', '" . $registrar_id[1] . "', 'aysmedia', '$current_timestamp_date_only - Registrar Account ''aysmedia'' Added', '0', '1', '$current_timestamp'),
('" . $company_id[2] . "', '" . $registrar_id[2] . "', 'dummy', '$current_timestamp_date_only - Registrar Account ''dummy'' Added', '1', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "
INSERT INTO `ssl_accounts` (`company_id`, `ssl_provider_id`, `username`, `notes`, `reseller`, `test_data`, `insert_time`) VALUES
('" . $company_id[1] . "', '" . $ssl_provider_id[1] . "', 'aysmedia', '$current_timestamp_date_only - SSL Account ''aysmedia'' Added', '0', '1', '$current_timestamp'),
('" . $company_id[2] . "', '" . $ssl_provider_id[2] . "', 'dummy', '$current_timestamp_date_only - Registrar Account ''dummy'' Added', '1', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "
INSERT INTO `segments` (`name`, `description`, `segment`, `number_of_domains`, `notes`, `test_data`, `insert_time`) VALUES ('AYS Media Segment', 'This is a test segment that includes some AYS Media domains.', '''aysmedia.com'',''aysmedia.ca'',''aysprivacy.com''', '3', '$current_timestamp_date_only - Segment ''AYS Media Segment'' Added', '1', '$current_timestamp'),
('Dummy Segment', 'This is a test segment that includes some AYS Media dummy domains.', '''test1-dm.com'',''test2-dm.com'',''test3-dm.com'',''test4-dm.com'',''test5-dm.com''', '5', '$current_timestamp_date_only - Segment ''Dummy Segment'' Added', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "select id
		from registrar_accounts
		where username = 'aysmedia'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $registrar_account_id[1] = $row->id; }

$sql = "select id
		from registrar_accounts
		where username = 'dummy'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $registrar_account_id[2] = $row->id; }

$sql = "select id
		from ssl_accounts
		where username = 'aysmedia'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_account_id[1] = $row->id; }

$sql = "select id
		from ssl_accounts
		where username = 'dummy'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_account_id[2] = $row->id; }

$sql = "select id
		from categories
		where name = 'AYS Media Domains'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $category_id[1] = $row->id; }

$sql = "select id
		from categories
		where name = 'Dummy Domains'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $category_id[2] = $row->id; }

$sql = "select id
		from fees
		where registrar_id = '" . $registrar_id[1] . "'
		and tld = 'com'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $fee_id[1] = $row->id; }

$sql = "select id
		from fees
		where registrar_id = '" . $registrar_id[1] . "'
		and tld = 'ca'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $fee_id[2] = $row->id; }

$sql = "select id
		from fees
		where registrar_id = '" . $registrar_id[2] . "'
		and tld = 'com'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $fee_id[3] = $row->id; }

$sql = "select id
		from ssl_fees
		where ssl_provider_id = '" . $ssl_provider_id[1] . "'
		and type = 'Wildcard'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_fee_id[1] = $row->id; }

$sql = "select id
		from ssl_fees
		where ssl_provider_id = '" . $ssl_provider_id[2] . "'
		and type = 'Single Host'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $ssl_fee_id[2] = $row->id; }

$sql = "select id
		from dns
		where name = 'AYS Media DNS'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $dns_id[1] = $row->id; }

$sql = "select id
		from dns
		where name = 'Dummy DNS'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $dns_id[2] = $row->id; }

$sql = "insert into `domains`
	(`company_id`, `registrar_id`, `account_id`, `domain`, `tld`, `expiry_date`, `cat_id`, `fee_id`, `dns_id`, `function`, `status`, `status_notes`, `notes`, `privacy`, `active`, `test_data`, `fee_fixed`, `insert_time`)
	values 
	('" . $company_id[1] . "', '" . $registrar_id[1] . "', '" . $registrar_account_id[1] . "', 'aysmedia.com', 'com', '2011-01-23', '" . $category_id[1] . "', '" . $fee_id[1] . "', '" . $dns_id[1] . "', 'Live Site', 'Active', '$current_timestamp_date_only - ''aysmedia.com'' Went Live', '$current_timestamp_date_only - Domain ''aysmedia.com'' Added', '1', '1', '1', '1', '$current_timestamp'),
	('" . $company_id[1] . "', '" . $registrar_id[1] . "', '" . $registrar_account_id[1] . "', 'aysmedia.ca', 'ca', '2011-01-24', '" . $category_id[1] . "', '" . $fee_id[2] . "', '" . $dns_id[1] . "', 'Redirect', 'Active (aysmedia.com)', '$current_timestamp_date_only - ''aysmedia.ca'' Went Live', '$current_timestamp_date_only - Domain ''aysmedia.ca'' Added', '1', '5', '1', '1', '$current_timestamp'),
	('" . $company_id[1] . "', '" . $registrar_id[1] . "', '" . $registrar_account_id[1] . "', 'aysprivacy.com', 'com', '2011-01-25', '" . $category_id[1] . "', '" . $fee_id[1] . "', '" . $dns_id[1] . "', 'Redirect', 'Active (aysmedia.com)', '$current_timestamp_date_only - ''aysprivacy.com'' Went Live', '$current_timestamp_date_only - Domain ''aysprivacy.com'' Added', '1', '4', '1', '1', '$current_timestamp')
	";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "select id
		from domains
		where domain = 'aysmedia.com'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[1] = $row->id; }

$sql = "select id
		from domains
		where domain = 'aysmedia.ca'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[2] = $row->id; }

$sql = "select id
		from domains
		where domain = 'aysprivacy.com'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[3] = $row->id; }

$sql = "
INSERT INTO `ssl_certs` (`company_id`, `ssl_provider_id`, `account_id`, `domain_id`, `name`, `type`, `expiry_date`, `fee_id`, `notes`, `active`, `test_data`, `fee_fixed`, `insert_time`) VALUES
('" . $company_id[1] . "', '" . $ssl_provider_id[1] . "', '" . $ssl_account_id[1] . "', '" . $domain_id[1] . "', '*.aysmedia.com', 'Wildcard', '2011-01-23', '" . $ssl_fee_id[1] . "', '$current_timestamp_date_only - SSL Certificate ''*.aysmedia.com'' Added', '1', '1', '1', '$current_timestamp'),
('" . $company_id[1] . "', '" . $ssl_provider_id[1] . "', '" . $ssl_account_id[1] . "', '" . $domain_id[2] . "', '*.aysmedia.ca', 'Wildcard', '2011-01-24', '" . $ssl_fee_id[1] . "', '$current_timestamp_date_only - SSL Certificate ''*.aysmedia.ca'' Added', '5', '1', '1', '$current_timestamp'),
('" . $company_id[1] . "', '" . $ssl_provider_id[1] . "', '" . $ssl_account_id[1] . "', '" . $domain_id[3] . "', '*.aysprivacy.com', 'Wildcard', '2011-01-25', '" . $ssl_fee_id[1] . "', '$current_timestamp_date_only - SSL Certificate ''*.aysprivacy.com'' Added', '4', '1', '1', '$current_timestamp');
";
$result = mysql_query($sql,$connection) or die(mysql_error());

$count = 1;

while ($count <= $number_of_temp_domains) {
	
	$sql = "insert into domains 
			(`company_id`, `registrar_id`, `account_id`, `domain`, `tld`, `expiry_date`, `cat_id`, `fee_id`, `dns_id`, `function`, `status`, `status_notes`, `notes`, `privacy`, `test_data`, `fee_fixed`, `insert_time`)
			values 
			('" . $company_id[2] . "', '" . $registrar_id[2] . "', '" . $registrar_account_id[2] . "', 'test" . $count . "-dm.com', 'com', '2011-01-26', '" . $category_id[2] . "', '" . $fee_id[3] . "', '" . $dns_id[2] . "', 'Live Site', 'Inactive', '$current_timestamp_date_only - ''test" . $count . "-dm.com'' Went Live', '$current_timestamp_date_only - Domain ''test" . $count . "-dm.com'' Added', '0', '1', '1', '$current_timestamp')";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$count++;

}

$sql = "select id
		from domains
		where domain = 'test1-dm.com'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[4] = $row->id; }

$sql = "select id
		from domains
		where domain = 'test2-dm.com'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[5] = $row->id; }

$sql = "select id
		from domains
		where domain = 'test3-dm.com'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[6] = $row->id; }

$sql = "select id
		from domains
		where domain = 'test4-dm.com'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[7] = $row->id; }

$sql = "select id
		from domains
		where domain = 'test5-dm.com'
		and test_data = '1'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $domain_id[8] = $row->id; }

$count = 1;
$count_secondary = 4;

while ($count <= 5) {

	$sql = "INSERT INTO `ssl_certs` 
			(`company_id`, `ssl_provider_id`, `account_id`, `domain_id`, `name`, `type`, `expiry_date`, `fee_id`, `notes`, `test_data`, `fee_fixed`, `insert_time`) 
			VALUES
			('" . $company_id[2] . "', '" . $ssl_provider_id[2] . "', '" . $ssl_account_id[2] . "', '" . $domain_id[$count_secondary] . "', 'test" . $count . "-dm.com', 'Single Host', '2011-01-26', '" . $ssl_fee_id[2] . "', '$current_timestamp_date_only - SSL Certificate ''test" . $count . "-dm.com'' Added', '1', '1', '$current_timestamp');
	";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$count++;
	$count_secondary++;

}


if ($_SESSION['session_installation_mode'] == 1 || $_SESSION['session_first_login'] == 1) {
	
	$_SESSION['session_first_login'] = 0;
	header("Location: ../../index.php");
	exit;
	
} else {

	$_SESSION['session_result_message'] = "Test Data Has Been Regenerated<BR>";

	header("Location: ../../system/index.php");
	exit;

}
?>