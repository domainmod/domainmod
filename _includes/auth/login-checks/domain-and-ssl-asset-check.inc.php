<?php
// /_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<?php
$sql_asset_check = "SELECT id
					FROM registrars
					LIMIT 1";
$result_asset_check = mysql_query($sql_asset_check,$connection) or die(mysql_error());
if (mysql_num_rows($result_asset_check) == 0) { 
	$_SESSION['need_registrar'] = "1";
} else {
	$_SESSION['need_registrar'] = "0";
}

$sql_asset_check = "SELECT id
					FROM registrar_accounts
					LIMIT 1";
$result_asset_check = mysql_query($sql_asset_check,$connection) or die(mysql_error());
if (mysql_num_rows($result_asset_check) == 0) { 
	$_SESSION['need_registrar_account'] = "1";
} else {
	$_SESSION['need_registrar_account'] = "0";
}

$sql_asset_check = "SELECT id
					FROM domains
					LIMIT 1";
$result_asset_check = mysql_query($sql_asset_check,$connection) or die(mysql_error());
if (mysql_num_rows($result_asset_check) == 0) { 
	$_SESSION['need_domain'] = "1";
} else {
	$_SESSION['need_domain'] = "0";
}

$sql_asset_check = "SELECT id
					FROM ssl_providers
					LIMIT 1";
$result_asset_check = mysql_query($sql_asset_check,$connection) or die(mysql_error());
if (mysql_num_rows($result_asset_check) == 0) { 
	$_SESSION['need_ssl_provider'] = "1";
} else {
	$_SESSION['need_ssl_provider'] = "0";
}

$sql_asset_check = "SELECT id
					FROM ssl_accounts
					LIMIT 1";
$result_asset_check = mysql_query($sql_asset_check,$connection) or die(mysql_error());
if (mysql_num_rows($result_asset_check) == 0) { 
	$_SESSION['need_ssl_account'] = "1";
} else {
	$_SESSION['need_ssl_account'] = "0";
}

$sql_asset_check = "SELECT id
					FROM ssl_certs
					LIMIT 1";
$result_asset_check = mysql_query($sql_asset_check,$connection) or die(mysql_error());

if (mysql_num_rows($result_asset_check) == 0) { 

	$_SESSION['need_ssl_cert'] = "1";

} else {

	$_SESSION['need_ssl_cert'] = "0";

}
?>
