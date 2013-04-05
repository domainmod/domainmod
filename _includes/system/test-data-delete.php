<?php
// test-data-delete.php
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

$generating_test_data = $_GET['generating_test_data'];

include("../config.inc.php");
include("../database.inc.php");
include("../software.inc.php");
include("../auth/auth-check.inc.php");

$sql = "DELETE FROM categories WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM currencies WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM owners WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM dns WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM domains WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM ssl_certs WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM fees WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM ssl_fees WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM registrars WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM registrar_accounts WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM ssl_providers WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM ssl_accounts WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM ssl_cert_types WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM segments WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "DELETE FROM ip_addresses WHERE test_data = '1'";
$result = mysql_query($sql,$connection);

include("../auth/login-checks/domain-and-ssl-asset-check.inc.php");

if ($generating_test_data == "1") {

	header("Location: test-data-generate.php");

} else {

	$_SESSION['session_result_message'] = "The test data has been deleted<BR>";
	header("Location: ../../system/index.php");

}
exit;
?>