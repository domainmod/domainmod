<?php
// /cron/fixfees.php
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
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

include("../_includes/config-demo.inc.php");

if ($demo_install != "1") {

    $sql_domain_fee_fix1 = "UPDATE domains
                            SET fee_fixed = '0',
                                fee_id = '0'";
    $result_domain_fee_fix1 = mysql_query($sql_domain_fee_fix1,$connection) or die(mysql_error());

    $sql_domain_fee_fix2 = "UPDATE fees
                            SET fee_fixed = '0',
                                update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
    $result_domain_fee_fix2 = mysql_query($sql_domain_fee_fix2,$connection) or die(mysql_error());

    $sql_domain_fee_fix3 = "SELECT id, registrar_id, tld
                            FROM fees
                            WHERE fee_fixed = '0'";
    $result_domain_fee_fix3 = mysql_query($sql_domain_fee_fix3,$connection) or die(mysql_error());

    while ($row_domain_fee_fix3 = mysql_fetch_object($result_domain_fee_fix3)) {

        $sql_domain_fee_fix4 = "UPDATE domains
                                SET fee_id = '" . $row_domain_fee_fix3->id . "',
                                    fee_fixed = '1'
                                WHERE registrar_id = '" . $row_domain_fee_fix3->registrar_id . "'
                                  AND tld = '" . $row_domain_fee_fix3->tld . "'
                                  AND fee_fixed = '0'";
        $result_domain_fee_fix4 = mysql_query($sql_domain_fee_fix4, $connection);

        $sql_domain_fee_fix5 = "UPDATE fees
                                SET fee_fixed = '1',
                                    update_time = '" . mysql_real_escape_string($current_timestamp) . "'
                                WHERE registrar_id = '" . $row_domain_fee_fix3->registrar_id . "'
                                  AND tld = '" . $row_domain_fee_fix3->tld . "'";
        $result_domain_fee_fix5 = mysql_query($sql_domain_fee_fix5, $connection);

    }

}
?>
