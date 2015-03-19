<?php
// /system/admin/dw/index.php
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
include("../../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "../../../invalid.php";
include("../../../_includes/auth/admin-user-check.inc.php");

include("../../../_includes/config.inc.php");
include("../../../_includes/database.inc.php");
include("../../../_includes/software.inc.php");
include("../../../_includes/auth/auth-check.inc.php");
include("../../../_includes/timestamps/current-timestamp.inc.php");

$id = $_GET['id'];
$action = $_GET['action'];
$view_all = $_GET['view_all'];

if ($action != "") {
	
	if ($action == "dw_accounts") {
		
		if ($view_all == "1") {
			
			$_SESSION['dw_view_all'] = 1;

		} else {
			
			$sql = "SELECT name, host
					FROM dw_servers
					WHERE id = '" . $id . "'";
			$result = mysqli_query($connection, $sql);
			
			while ($row = mysqli_fetch_object($result)) {

				$_SESSION['dw_view_all'] = "";
				$_SESSION['dw_server_id'] = $id;
				$_SESSION['dw_server_name'] = $row->name;
				$_SESSION['dw_server_host'] = $row->host;

			}

		}
		
		header("Location: list-accounts.php");
		exit;

	} elseif ($action == "dw_dns_zones") {
		
		if ($view_all == "1") {
			
			$_SESSION['dw_view_all'] = 1;

		} else {
			
			$sql = "SELECT name, host
					FROM dw_servers
					WHERE id = '" . $id . "'";
			$result = mysqli_query($connection, $sql);
			
			while ($row = mysqli_fetch_object($result)) {

				$_SESSION['dw_view_all'] = "";
				$_SESSION['dw_server_id'] = $id;
				$_SESSION['dw_server_name'] = $row->name;
				$_SESSION['dw_server_host'] = $row->host;

			}

		}
		
		header("Location: list-dns-zones.php");
		exit;

	}

}

$page_title = "Data Warehouse";
$software_section = "admin-dw-main";
?>
<?php include("../../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?></title>
<?php include("../../../_includes/layout/head-tags.inc.php"); ?>
<?php include("../../../_includes/system/functions/jumpmenu.inc.php"); ?>
</head>
<body>
<?php include("../../../_includes/layout/header.inc.php"); ?>

<?php
$sql = "SELECT id
		FROM dw_servers
		LIMIT 1";
$result = mysqli_query($connection, $sql);
if (mysqli_num_rows($result) == 0) {

	$has_servers = 0;

} else {

	$has_servers = 1;

}
?>
&raquo; <a href="servers.php">Manage Servers</a><?php if ($has_servers == 1) { ?>&nbsp;&nbsp;/&nbsp;&nbsp;<a target="_blank" href="../../../cron/dw.php?direct=1">Build DW</a><?php } ?><BR>

<?php
$sql_accounts = "SELECT id
				 FROM dw_accounts";
$result_accounts = mysqli_query($connection, $sql_accounts);
$temp_total_accounts = mysqli_num_rows($result_accounts);

$sql_dns_zones = "SELECT id
				  FROM dw_dns_records";
$result_dns_zones = mysqli_query($connection, $sql_dns_zones);
$temp_total_dns_zones = mysqli_num_rows($result_dns_zones);

$sql_build_finished = "SELECT build_status_overall
					   FROM dw_servers
					   LIMIT 1";
$result_build_finished = mysqli_query($connection, $sql_build_finished);
while ($row_build_finished = mysqli_fetch_object($result_build_finished)) {
	$is_the_build_finished = $row_build_finished->build_status_overall;
}

if ($is_the_build_finished == 1 && ($temp_total_accounts != 0 || $temp_total_dns_zones != 0)) { ?>

    <BR><font class="subheadline">View Data</font><BR><BR>
    <form name="dw_view_data_form" method="post" action="<?php echo $PHP_SELF; ?>">
    <?php
    
    if ($temp_total_accounts == 0) {
    
        echo "No Accounts exist<BR>";
    
    } else { ?>
    
        <select name="dw_accounts" onChange="MM_jumpMenu('parent',this,0)">
        <option value="<?php echo $PHP_SELF; ?>">Server Accounts</option><?php
        $sql_dw_account = "SELECT id, name, dw_accounts
						   FROM dw_servers
						   ORDER BY name, host";
        $result_dw_account = mysqli_query($connection, $sql_dw_account); ?>
    
        <option value="<?php echo $PHP_SELF; ?>?action=dw_accounts&view_all=1">VIEW ALL</option><?php
    
        while ($row_dw_account = mysqli_fetch_object($result_dw_account)) { ?>
    
            <option value="<?php echo $PHP_SELF; ?>?action=dw_accounts&id=<?php echo $row_dw_account->id; ?>"><?php echo $row_dw_account->name; ?> (<?php echo number_format($row_dw_account->dw_accounts); ?> Accounts)</option><?php
    
        } ?>
        </select>
        <BR><BR><?php
    
    }

    if ($temp_total_dns_zones == 0) {
    
        echo "No DNS Zones exist<BR>";
    
    } else { ?>
    
        <select name="dw_dns_zones" onChange="MM_jumpMenu('parent',this,0)">
        <option value="<?php echo $PHP_SELF; ?>">DNS Zones & Records</option><?php
        $sql_dw_dns_records = "SELECT id, name, dw_dns_zones, dw_dns_records
							   FROM dw_servers
							   ORDER BY name, host";
        $result_dw_dns_records = mysqli_query($connection, $sql_dw_dns_records); ?>
    
        <option value="<?php echo $PHP_SELF; ?>?action=dw_dns_zones&view_all=1">VIEW ALL</option><?php
    
        while ($row_dw_dns_records = mysqli_fetch_object($result_dw_dns_records)) { ?>
    
            <option value="<?php echo $PHP_SELF; ?>?action=dw_dns_zones&id=<?php echo $row_dw_dns_records->id; ?>"><?php echo $row_dw_dns_records->name; ?> (<?php echo number_format($row_dw_dns_records->dw_dns_zones); ?> Zones, <?php echo number_format($row_dw_dns_records->dw_dns_records); ?> Records)</option><?php
    
        } ?>
        </select>
        <BR><?php
    
    }
    ?>
    </form><?php
	
}


$sql_build_info = "SELECT build_status_overall, build_start_time_overall, build_end_time_overall, build_time_overall, has_ever_been_built_overall, build_end_time_overall, build_start_time_overall
				   FROM dw_servers
				   ORDER BY build_end_time_overall desc
				   LIMIT 1";
$result_build_info = mysqli_query($connection, $sql_build_info);
$temp_build_info = mysqli_num_rows($result_build_info);

if ($temp_build_info != 0) { ?>

    <BR><font class="subheadline">Build Information</font><BR>
    <table class="main_table" cellpadding="0" cellspacing="0"><?php
    
        if ($temp_build_info == 0) {
    
            echo "<BR>You don't currently have any servers setup in your Data Warehouse. <a href=\"add/server.php\">Click here to add one</a>.";
        
        } else {
        
            while ($row_build_info = mysqli_fetch_object($result_build_info)) { 
    
                if ($row_build_info->build_start_time_overall != "0000-00-00 00:00:00" && $row_build_info->build_end_time_overall != "0000-00-00 00:00:00") {
    
                        $temp_build_status_overall = "<font color=\"green\"><strong>Successful</strong></font>";
                        
                }
    
                if ($row_build_info->build_start_time_overall != "0000-00-00 00:00:00" && $row_build_info->has_ever_been_built_overall == 0) {
    
                        $temp_build_status_overall = "<font class=\"default_highlight\">Building...</font>";
                        
                }
    
                if ($row_build_info->build_start_time_overall != "0000-00-00 00:00:00" && $row_build_info->build_end_time_overall == "0000-00-00 00:00:00" && $row_build_info->build_status_overall == 0) {
                    
                    $sql_check_builds = "SELECT id
                                         FROM dw_servers
                                         WHERE build_status = '0'";
                    $result_check_builds = mysqli_query($connection, $sql_check_builds);
                    
                    if (mysqli_num_rows($result_check_builds) == 0) {
    
                        $temp_build_status_overall = "<font class=\"default_highlight\"><strong>Cleanup...</strong></font>";
    
                    } else {
    
                        $temp_build_status_overall = "<font class=\"default_highlight\"><strong>Building...</strong></font>";
    
                    }
                    $is_building = 1;
    
                        
                }
    
                if ($row_build_info->build_start_time_overall == "0000-00-00 00:00:00" && $row_build_info->has_ever_been_built_overall == 0) {
    
                        $temp_build_status_overall = "<font class=\"default_highlight\">Never Built</font>";
                        
                }
    
                if ($row_build_info->build_start_time_overall == "0000-00-00 00:00:00") {
                    
                    $temp_build_start_time_overall = "-";
                    
                } else {
                    
                    $temp_build_start_time_overall = date("M jS @ g:i:sa", strtotime($row_build_info->build_start_time_overall));
                    
                }
        
                if ($row_build_info->build_end_time_overall == "0000-00-00 00:00:00") {
                    
                    $temp_build_end_time_overall = "-";
                    
                } else {
        
                    $temp_build_end_time_overall = date("M jS @ g:i:sa", strtotime($row_build_info->build_end_time_overall));
                    
                }

                if ($row_build_info->build_time_overall <= 0) {
                    
                    $temp_build_time_overall = "-";
                    
                } elseif ($row_build_info->build_time_overall > 0 && $row_build_info->build_time_overall <= 60) {
    
                    $temp_build_time_overall = number_format($row_build_info->build_time_overall) . "s";
    
                } else {

                    $number_of_minutes = intval($row_build_info->build_time_overall / 60);
                    $number_of_seconds = $row_build_info->build_time_overall - ($number_of_minutes * 60);

					$temp_build_time_overall = $number_of_minutes . "m " . $number_of_seconds . "s";

                } ?>
    
                <tr class="main_table_row_heading_active">
                    <td class="main_table_cell_heading_active">
                        <font class="main_table_heading">Server</font>
                    </td>
                    <td class="main_table_cell_heading_active">
                        <font class="main_table_heading">Build Start</font>
                    </td>
                    <td class="main_table_cell_heading_active">
                        <font class="main_table_heading">Build End</font>
                    </td>
                    <td class="main_table_cell_heading_active">
                        <font class="main_table_heading">Build Time</font>
                    </td>
                    <td class="main_table_cell_heading_active">
                        <font class="main_table_heading">Build Status</font>
                    </td>
                </tr>
    
                <tr class="main_table_row_active">
                    <td class="main_table_cell_active"><em>Full Build</em></td>
                    <td class="main_table_cell_active"><?php echo $temp_build_start_time_overall; ?></td>
                    <td class="main_table_cell_active"><?php echo $temp_build_end_time_overall; ?></td>
                    <td class="main_table_cell_active"><?php echo $temp_build_time_overall; ?></td>
                    <td class="main_table_cell_active"><?php echo $temp_build_status_overall; ?></td>
                </tr><?php
                
            }
            
        } ?>

        <?php
        $sql = "SELECT name, host, build_status, build_start_time, build_end_time, build_time, has_ever_been_built
                FROM dw_servers
                ORDER BY name, host";
        $result = mysqli_query($connection, $sql);
        
        if (mysqli_num_rows($result) == 0) {
            
            echo "";
        
        } else {
        
            while ($row = mysqli_fetch_object($result)) { 
    
                if ($row->build_start_time != "0000-00-00 00:00:00" && $row->build_end_time != "0000-00-00 00:00:00") {
    
                        $temp_build_status = "<font color=\"green\"><strong>Successful</strong></font>";
                        
                }
    
                if ($row->build_start_time != "0000-00-00 00:00:00" && $row->build_end_time == "0000-00-00 00:00:00" && $row->build_status == 0) {
    
                        $temp_build_status = "<font class=\"default_highlight\"><strong>Building...</strong></font>";
                        
                }
    
                if ($row->build_start_time == "0000-00-00 00:00:00" && $row->has_ever_been_built == 0) {
    
                        if ($is_building == 1) {
    
                            $temp_build_status = "<font class=\"default_highlight\">Pending</font>";
                            
                        } else {
    
                            $temp_build_status = "<font class=\"default_highlight\">Never Built</font>";
                            
                        }
    
                }
    
                if ($row->build_start_time == "0000-00-00 00:00:00" && $row->has_ever_been_built == 1) {
    
                        $temp_build_status = "<font class=\"default_highlight\">Pending</font>";
                        
                }
    
                if ($row->build_start_time != "0000-00-00 00:00:00" && $row->has_ever_been_built == 0) {
    
                        $temp_build_status = "<font class=\"default_highlight\">Building...</font>";
                        
                }
    
                if ($row->build_start_time  == "0000-00-00 00:00:00") {
                    
                    $temp_build_start_time = "-";
                    
                } else {
                    
                    $temp_build_start_time = date("M jS @ g:i:sa", strtotime($row->build_start_time));
                    
                }
        
                if ($row->build_end_time  == "0000-00-00 00:00:00") {
                    
                    $temp_build_end_time = "-";
                    
                } else {
        
                    $temp_build_end_time = date("M jS @ g:i:sa", strtotime($row->build_end_time));
                    
                }
    
                if ($row->build_time <= 0) {
                    
                    $temp_build_time = "-";
                    
                } elseif ($row->build_time > 0 && $row->build_time <= 60) {
    
                    $temp_build_time = number_format($row->build_time) . "s";
    
                } else {

                    $number_of_minutes = intval($row->build_time / 60);
                    $number_of_seconds = $row->build_time - ($number_of_minutes * 60);

					$temp_build_time = $number_of_minutes . "m " . $number_of_seconds . "s";

                } ?>
    
                <tr class="main_table_row_active">
                    <td class="main_table_cell_active"><?php echo $row->name; ?></td>
                    <td class="main_table_cell_active"><?php echo $temp_build_start_time; ?></td>
                    <td class="main_table_cell_active"><?php echo $temp_build_end_time; ?></td>
                    <td class="main_table_cell_active"><?php echo $temp_build_time; ?></td>
                    <td class="main_table_cell_active"><?php echo $temp_build_status; ?></td>
                </tr><?php
        
            }
            
        } ?>
    </table><?php
	
}

$sql_data_check = "SELECT dw_accounts, dw_dns_zones, dw_dns_records
				   FROM dw_server_totals";
$result_data_check = mysqli_query($connection, $sql_data_check);

while ($row_data_check = mysqli_fetch_object($result_data_check)) {
	$temp_dw_accounts = $row_data_check->dw_accounts;
	$temp_dw_dns_zones = $row_data_check->dw_dns_zones;
	$temp_dw_dns_records = $row_data_check->dw_dns_records;
}

if (mysqli_num_rows($result) == 0) {
	
	// Placeholder

} else {

	if (mysqli_num_rows(mysqli_query($connection, "SHOW TABLES LIKE '" . dw_server_totals . "'")) == 1) {

		$table_exists = 1;

	} else {

		$table_exists = 0;		

	}

	if ($is_building != 1 && $table_exists != 0 && $temp_dw_accounts != 0 && $temp_dw_dns_zones != 0 && $temp_dw_dns_zones != 0) { ?>
    
        <BR><font class="subheadline">Data Warehouse Totals</font><BR>
        <table class="main_table" cellpadding="0" cellspacing="0">
            <tr class="main_table_row_heading_active">
                <td class="main_table_cell_heading_active">
                    <font class="main_table_heading">Server</font>
                </td>
                <td class="main_table_cell_heading_active">
                    <font class="main_table_heading">Accounts</font>
                </td>
                <td class="main_table_cell_heading_active">
                    <font class="main_table_heading">DNS Zones</font>
                </td>
                <td class="main_table_cell_heading_active">
                    <font class="main_table_heading">DNS Records</font>
                </td>
            </tr><?php

            $sql = "SELECT dw_servers, dw_accounts, dw_dns_zones, dw_dns_records
                    FROM dw_server_totals";
            $result = mysqli_query($connection, $sql);

            while ($row = mysqli_fetch_object($result)) { 
			
                if ($row->dw_servers > 1) { ?>
        
                    <tr class="main_table_row_active">
                        <td class="main_table_cell_active">
                            <em>All Servers</em>
                        </td>
                        <td class="main_table_cell_active">
                            <?php echo number_format($row->dw_accounts); ?>
                        </td>
                        <td class="main_table_cell_active">
                            <?php echo number_format($row->dw_dns_zones); ?>
                        </td>
                        <td class="main_table_cell_active">
                            <?php echo number_format($row->dw_dns_records); ?>
                        </td>
                    </tr><?php
    
                }
    
            }
    
            $sql = "SELECT name, dw_accounts, dw_dns_zones, dw_dns_records
                    FROM dw_servers
                    WHERE has_ever_been_built = '1'
                    ORDER BY name";
            $result = mysqli_query($connection, $sql);
    
            while ($row = mysqli_fetch_object($result)) { ?>
        
                <tr class="main_table_row_active">
                    <td class="main_table_cell_active">
                        <?php echo $row->name; ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php echo number_format($row->dw_accounts); ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php echo number_format($row->dw_dns_zones); ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php echo number_format($row->dw_dns_records); ?>
                    </td>
                </tr><?php
                
            } ?>
    
        </table><?php
		
	}

} 


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

if ($is_the_build_finished == 1 && ($temp_accounts_without_a_dns_zone != 0 || $temp_dns_zones_without_an_account != 0 || $temp_suspended_accounts != 0)) { ?>

    <BR><font class="subheadline">Potential Problems</font><?php
    if ($temp_accounts_without_a_dns_zone == 0) {
        
        $accounts_without_a_dns_zone_flag = 1;
        
    } else { ?>
    
        <BR><BR><strong>Accounts without a DNS Zone</strong><BR><?php
    
        while ($row_accounts_without_a_dns_zone = mysqli_fetch_object($result_accounts_without_a_dns_zone)) {
        
            // $account_list_raw .= "<a class=\"invisiblelink\" href=\"list-accounts.php?domain=" . $row_accounts_without_a_dns_zone->domain . "\">" . $row_accounts_without_a_dns_zone->domain . "</a>, ";
            $account_list_raw .= $row_accounts_without_a_dns_zone->domain . ", ";
        
        }
        
        $account_list = substr($account_list_raw, 0, -2);
    
        if ($account_list != "") { 
        
            echo $account_list;
        
        } else {
        
            echo "n/a";
        
        }
    
    }

    if ($temp_dns_zones_without_an_account == 0) {
        
        $dns_zones_without_an_account_flag = 1;
        
    } else { ?>
    
        <BR><BR><strong>DNS Zones without an Account</strong><BR><?php
    
        while ($row_dns_zones_without_an_account = mysqli_fetch_object($result_dns_zones_without_an_account)) {
        
            // $zone_list_raw .= "<a class=\"invisiblelink\" href=\"list-dns-zones.php?domain=" . $row_dns_zones_without_an_account->domain . "\">" . $row_dns_zones_without_an_account->domain . "</a>, ";
            $zone_list_raw .= $row_dns_zones_without_an_account->domain . ", ";
        
        }
        
        $zone_list = substr($zone_list_raw, 0, -2);
    
        if ($zone_list != "") { 
        
            echo $zone_list;
        
        } else {
        
            echo "n/a";
        
        }
    
    }

    if ($temp_suspended_accounts == 0) {
        
        $suspended_accounts_flag = 1;
        
    } else { ?>
    
        <BR><BR><strong>Suspended Accounts</strong><BR><?php
    
        while ($row_suspended_accounts = mysqli_fetch_object($result_suspended_accounts)) {
        
            // $suspended_list_raw .= "<a class=\"invisiblelink\" href=\"list-accounts.php?domain=" . $row_suspended_accounts->domain . "\">" . $row_suspended_accounts->domain . "</a>, ";
            $suspended_list_raw .= $row_suspended_accounts->domain . ", ";
        
        }
        
        $suspended_list = substr($suspended_list_raw, 0, -2);
        
        if ($suspended_list != "") { 
        
            echo $suspended_list;
        
        } else {
        
            echo "n/a";
        
        }
        
    }
	
	echo "<BR>";
	
} ?>
<?php include("../../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
