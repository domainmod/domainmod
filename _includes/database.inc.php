<?php
// /_includes/database.inc.php
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
$connection = mysql_connect($dbhostname,$dbusername,$dbpassword) or die(mysql_error());
$database = mysql_select_db($dbname,$connection) or die(mysql_error());
mysql_query("SET CHARACTER SET utf8", $connection) or die(mysql_error());
mysql_query("SET COLLATION_CONNECTION = 'utf8_unicode_ci'", $connection) or die(mysql_error());
?>