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

$generating_test_data = $_GET['generating_test_data'];

include("../config.inc.php");
include("../database.inc.php");
include("../software.inc.php");
include("../auth/auth-check.inc.php");

$sql = "delete from categories where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from companies where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from currencies where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from dns where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from domains where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from ssl_certs where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from fees where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from ssl_fees where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from registrars where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from registrar_accounts where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from ssl_providers where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from ssl_accounts where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from segments where test_data = '1'";
$result = mysql_query($sql,$connection);

$sql = "delete from ip_addresses where test_data = '1'";
$result = mysql_query($sql,$connection);

if ($generating_test_data == "1") {

	header("Location: test-data-generate.php");

} else {

	$_SESSION['session_result_message'] = "Test Data Has Been Deleted<BR>";
	header("Location: ../../system/index.php");

}
exit;
?>