<?php
/**
 * /reporting/dw/potential-problems.php
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
include("../../_includes/start-session.inc.php");
include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/classes/Export.class.php");

$page_title = $reporting_section_title;
$page_subtitle = "Data Warehouse Potential Problems Report";
$software_section = "reporting-dw-potential-problems-report";
$report_name = "dw-potential-problems-report";

$generate = $_GET['generate'];
$export_data = $_GET['export_data'];

$sql_accounts_without_a_dns_zone = "SELECT domain
									FROM dw_accounts
									WHERE domain NOT IN (SELECT domain 
														 FROM dw_dns_zones)
									ORDER BY domain";
$result_accounts_without_a_dns_zone = mysqli_query($connection, $sql_accounts_without_a_dns_zone);
$temp_accounts_without_a_dns_zone = mysqli_num_rows($result_accounts_without_a_dns_zone);

$sql_dns_zones_without_an_account = "SELECT domain
									 FROM dw_dns_zones
									 WHERE domain NOT IN (SELECT domain 
									 					  FROM dw_accounts)
									ORDER BY domain";
$result_dns_zones_without_an_account = mysqli_query($connection, $sql_dns_zones_without_an_account);
$temp_dns_zones_without_an_account = mysqli_num_rows($result_dns_zones_without_an_account);

$sql_suspended_accounts = "SELECT domain
						   FROM dw_accounts
						   WHERE suspended = '1'
						   ORDER BY domain";
$result_suspended_accounts = mysqli_query($connection, $sql_suspended_accounts);
$temp_suspended_accounts = mysqli_num_rows($result_suspended_accounts);

if ($export_data == "1") {

    $export = new DomainMOD\Export();

    $export_file = $export->openFile('dw_potential_problems_report');

    $row_contents = array($page_subtitle);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    if ($temp_accounts_without_a_dns_zone == 0) {
        
        $accounts_without_a_dns_zone_flag = 1;
        
    } else {

        $row_contents = array('Accounts without a DNS Zone (' . $temp_accounts_without_a_dns_zone . ')');
        $export->writeRow($export_file, $row_contents);

        unset($row_contents);
        $count = 0;

        while ($row_accounts_without_a_dns_zone = mysqli_fetch_object($result_accounts_without_a_dns_zone)) {

			$row_contents[$count++] = $row_accounts_without_a_dns_zone->domain;

        }
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    }

    if ($temp_dns_zones_without_an_account == 0) {
        
        $dns_zones_without_an_account_flag = 1;
        
    } else {

        $row_contents = array('DNS Zones without an Account (' . $temp_dns_zones_without_an_account . ')');
        $export->writeRow($export_file, $row_contents);

        unset($row_contents);
        $count = 0;

        while ($row_dns_zones_without_an_account = mysqli_fetch_object($result_dns_zones_without_an_account)) {

			$row_contents[$count++] = $row_dns_zones_without_an_account->domain;

        }
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    }

    if ($temp_suspended_accounts == 0) {
        
        $suspended_accounts_flag = 1;
        
    } else {

        $row_contents = array('Suspended Accounts (' . $temp_suspended_accounts . ')');
        $export->writeRow($export_file, $row_contents);

        unset($row_contents);
        $count = 0;

        while ($row_suspended_accounts = mysqli_fetch_object($result_suspended_accounts)) {

			$row_contents[$count++] = $row_suspended_accounts->domain;

        }
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    }

    $export->closeFile($export_file);

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?> :: <?php echo $page_subtitle; ?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<?php include("../../_includes/layout/reporting-block.inc.php"); ?>
<?php include("../../_includes/layout/table-export-top.inc.php"); ?>
    <form name="export_dw_form" method="post" action="<?php echo $PHP_SELF; ?>">
        <a href="<?php echo $PHP_SELF; ?>?generate=1">Generate</a>
        <?php if ($generate == 1) { ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="<?php echo $PHP_SELF; ?>?export_data=1&new_start_date=<?php echo $new_start_date; ?>&new_end_date=<?php echo $new_end_date; ?>&all=<?php echo $all; ?>">EXPORT REPORT</a>]</strong>
        <?php } ?>
    </form>
<?php include("../../_includes/layout/table-export-bottom.inc.php"); ?>
<?php if ($generate == 1) { ?>
<BR><font class="subheadline"><?php echo $page_subtitle; ?></font><BR>
<BR>
<?php } ?>
<?php
if ($generate == 1) {
	
	if ($temp_accounts_without_a_dns_zone == 0) {
        
        $accounts_without_a_dns_zone_flag = 1;
        
    } else { ?>
    
        <strong>Accounts without a DNS Zone (<?php echo $temp_accounts_without_a_dns_zone; ?>)</strong><BR><?php
    
        while ($row_accounts_without_a_dns_zone = mysqli_fetch_object($result_accounts_without_a_dns_zone)) {
        
            $account_list_raw .= $row_accounts_without_a_dns_zone->domain . ", ";
        
        }
        
        $account_list = substr($account_list_raw, 0, -2);
    
        if ($account_list != "") { 
        
            echo $account_list;
        
        } else {
        
            echo "n/a";
        
        }
		
		echo "<BR><BR>";
    
    }

    if ($temp_dns_zones_without_an_account == 0) {
        
        $dns_zones_without_an_account_flag = 1;
        
    } else { ?>
    
        <strong>DNS Zones without an Account (<?php echo $temp_dns_zones_without_an_account; ?>)</strong><BR><?php
    
        while ($row_dns_zones_without_an_account = mysqli_fetch_object($result_dns_zones_without_an_account)) {
        
            $zone_list_raw .= $row_dns_zones_without_an_account->domain . ", ";
        
        }
        
        $zone_list = substr($zone_list_raw, 0, -2);
    
        if ($zone_list != "") { 
        
            echo $zone_list;
        
        } else {
        
            echo "n/a";
        
        }

		echo "<BR><BR>";
    
    }

    if ($temp_suspended_accounts == 0) {
        
        $suspended_accounts_flag = 1;
        
    } else { ?>
    
        <strong>Suspended Accounts (<?php echo $temp_suspended_accounts; ?>)</strong><BR><?php
    
        while ($row_suspended_accounts = mysqli_fetch_object($result_suspended_accounts)) {
        
            $suspended_list_raw .= $row_suspended_accounts->domain . ", ";
        
        }
        
        $suspended_list = substr($suspended_list_raw, 0, -2);
        
        if ($suspended_list != "") { 
        
            echo $suspended_list;
        
        } else {
        
            echo "n/a";
        
        }
        
    }

} ?>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
