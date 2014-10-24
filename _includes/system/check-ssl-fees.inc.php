<?php
// /_includes/system/check-ssl-fees.inc.php
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
$sql_find_missing_ssl_cert_fees = "SELECT count(id) AS total_count
                                   FROM ssl_certs
                                   WHERE fee_id = '0'
                                     AND active NOT IN ('0', '10')";
$result_find_missing_ssl_cert_fees = mysql_query($sql_find_missing_ssl_cert_fees,$connection);

while ($row_find_missing_ssl_cert_fees = mysql_fetch_object($result_find_missing_ssl_cert_fees)) { $total_results_find_missing_ssl_cert_fees = $row_find_missing_ssl_cert_fees->total_count; }

if ($total_results_find_missing_ssl_cert_fees != 0) {
    $_SESSION['missing_ssl_fees'] = 1;
} else {
    $_SESSION['missing_ssl_fees'] = 0;
}
?>
