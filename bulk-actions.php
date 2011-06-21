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
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");
include("_includes/timestamps/current-timestamp.inc.php");
include("_includes/timestamps/current-timestamp-basic.inc.php");
include("_includes/timestamps/current-timestamp-plus-one-year-date-only.inc.php");
$software_section == "bulkactions";

// Form Variables
$jumpMenu = $_GET['jumpMenu'];
$action = $_REQUEST['action'];
$new_notes = $_POST['new_notes'];
$new_data = $_POST['new_data'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_function = $_POST['new_function'];
$new_status = $_POST['new_status'];
$new_status_notes = $_POST['new_status_notes'];
$new_pcid = $_POST['new_pcid'];
$new_dnsid = $_POST['new_dnsid'];
$new_raid = $_POST['new_raid'];
$new_privacy = $_POST['new_privacy'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];
$new_renewal_years = $_POST['new_renewal_years'];

$choose_text = "Click Here To Choose A New";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_data != "") {

		$lines = explode("\r\n", $new_data);
		$number_of_domains = count($lines);

		$new_data_formatted = "'" . $new_data;
		$new_data_formatted = $new_data_formatted . "'";
		$new_data_formatted = preg_replace("/\r\n/", "','", $new_data_formatted);
		$new_data_formatted = str_replace (" ", "", $new_data_formatted);
		$new_data_formatted = trim($new_data_formatted);

		if ($action == "R") { 
		
			$sql = "select domain, expiry_date
					from domains
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			while ($row = mysql_fetch_object($result)) {
			
				$lines = explode("-", $row->expiry_date);
				$old_expiry = $lines[0] . "-" . $lines[1] . "-" . $lines[2];
				$new_expiry = $lines[0]+$new_renewal_years . "-" . $lines[1] . "-" . $lines[2];
				
				$sql2 = "update domains
						 set expiry_date = '$new_expiry',
						 update_time = '$current_timestamp'
						 where domain = '$row->domain'";
				$result2 = mysql_query($sql2,$connection);
			
			}
			
			$_SESSION['session_result_message'] = "Domains Renewed<BR>";

		} elseif ($action == "AD") { 
		
			$sql = "select company_id, registrar_id
					from registrar_accounts
					where id = '$new_raid'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) {
				$temp_company_id = $row->company_id;
				$temp_registrar_id = $row->registrar_id;
			}

			$lines = explode("\r\n", $new_data);
			$number_of_domains = count($lines);
	
			reset($lines);
	
			// cycle through domains here
			while (list($key, $new_domain) = each($lines)) {
			
				$new_tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);

				$sql = "select id
						from fees
						where registrar_id = '$temp_registrar_id'
						and tld = '$new_tld'";
				$result = mysql_query($sql,$connection);
				while ($row = mysql_fetch_object($result)) {
					$temp_fee_id = $row->id;
				}

				if ($temp_fee_id == '0' || $temp_fee_id == "") { $temp_fee_fixed = 0; $temp_fee_id = 0; } else { $temp_fee_fixed = 1; }
	
				$sql = "insert into domains
						(company_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, fee_id, dns_id, function, status, status_notes, notes, privacy, active, fee_fixed, insert_time)
						values
						('$temp_company_id', '$temp_registrar_id', '$new_raid',  '$new_domain', '$new_tld', '$new_expiry_date', '$new_pcid', '$temp_fee_id', '$new_dnsid', '$new_function', '$new_status', '$new_status_notes', '$new_notes', '$new_privacy', '$new_active', '$temp_fee_fixed', '$current_timestamp')";
				$result = mysql_query($sql,$connection) or die(mysql_error());
				$temp_fee_id = 0;
			

			// finish cycling through domains here
			}

			$_SESSION['session_result_message'] = "Domains Added<BR>";
			
		} elseif ($action == "FR") { 
		
			$sql = "select domain, expiry_date
					from domains
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			while ($row = mysql_fetch_object($result)) {
			
				$lines = explode("-", $row->expiry_date);
				$old_expiry = $lines[0] . "-" . $lines[1] . "-" . $lines[2];
				$new_expiry = $lines[0]+$new_renewal_years . "-" . $lines[1] . "-" . $lines[2];
				
				if ($new_renewal_years == "1") {
					$renewal_years_string = "$new_renewal_years Year";
				} else {
					$renewal_years_string = "$new_renewal_years Years";
				}
				
				$new_notes = "$current_timestamp_date_only - Domain Renewed For $renewal_years_string";
				
				$sql2 = "update domains
						 set expiry_date = '$new_expiry',
						 active = '1',
						 notes = concat(notes, '\r\n\r\n$new_notes'),
						 update_time = '$current_timestamp'
						 where domain = '$row->domain'";

				$result2 = mysql_query($sql2,$connection);
			
			}
			
			$_SESSION['session_result_message'] = "Domains Fully Renewed<BR>";
			
		} elseif ($action == "CPC") { 

			$sql = "update domains
					set cat_id = '$new_pcid',
				    update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$sql = "select name
					from categories
					where id = '$new_pcid'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) {
				$new_primary_category_string = $row->name;
			}

			$_SESSION['session_result_message'] = "Primary Categories Updated<BR>";

		} elseif ($action == "CDNS") { 

			$sql = "update domains
					set dns_id = '$new_dnsid',
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$sql = "select name
					from dns
					where id = '$new_dnsid'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) {
				$new_dns_string = $row->name;
			}

			$_SESSION['session_result_message'] = "DNS Profile Updated<BR>";

		} elseif ($action == "AN") { 
		
			$sql2 = "update domains
					set notes = concat(notes, '\r\n\r\n$new_notes'),
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";

			$result2 = mysql_query($sql2,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Note Added<BR>";

		} elseif ($action == "CRA") { 


			$sql = "select ra.id as ra_id, ra.username, r.id as r_id, r.name as r_name, c.id as c_id, c.name as c_name
  				    from registrar_accounts as ra, registrars as r, companies as c, domains as d
				    where ra.registrar_id = r.id
				    and ra.company_id = c.id
				    and ra.id = d.account_id
					and ra.id = '$new_raid'
				    group by r.name, c.name, ra.username
				    order by r.name asc, c.name asc, ra.username asc";
			$result = mysql_query($sql,$connection);

			while ($row = mysql_fetch_object($result)) {
				$new_company_id = $row->c_id;
				$new_registrar_id = $row->r_id;
				$new_registrar_account_id = $row->ra_id;
				$new_company_name = $row->c_name;
				$new_registrar_name = $row->r_name;
				$new_username = $row->username;
			}
			
			$sql = "update domains
					set company_id = '$new_company_id', 
					registrar_id = '$new_registrar_id', 
					account_id = '$new_registrar_account_id',
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$new_account_string = "$new_registrar_name :: $new_company_name ($new_username)";
			$_SESSION['session_result_message'] = "Registrar Account Changed<BR>";

		} elseif ($action == "E") { 
		
			$sql = "update domains
					set active = '0',
				    update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Domains Marked As Expired<BR>";

		} elseif ($action == "S") { 
		
			$sql = "update domains
					set active = '10',
				    update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Domains Marked As Sold<BR>";

		} elseif ($action == "A") { 
		
			$sql = "update domains
					set active = '1',
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Domains Marked As Active<BR>";

		} elseif ($action == "T") { 
		
			$sql = "update domains
					set active = '2',
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Domains Marked As 'In Transfer'<BR>";

		} elseif ($action == "PRg") { 
		
			$sql = "update domains
					set active = '5',
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Domains Marked As 'Pending (Registration)'<BR>";

		} elseif ($action == "PRn") { 
		
			$sql = "update domains
					set active = '3',
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Domains Marked As 'Pending (Renewal)'<BR>";

		} elseif ($action == "PO") { 
		
			$sql = "update domains
					set active = '4',
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Domains Marked As 'Pending (Other)'<BR>";

		} elseif ($action == "PRVE") { 
		
			$sql = "update domains
					set privacy = '1',
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Domains Marked As 'Private WHOIS'<BR>";

		} elseif ($action == "PRVD") { 
		
			$sql = "update domains
					set privacy = '0',
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Domains Marked As 'Public WHOIS'<BR>";

		} elseif ($action == "CED") { 
		
			$sql = "update domains
					set expiry_date = '$new_expiry_date',
					update_time = '$current_timestamp'
					where domain in ($new_data_formatted)";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$_SESSION['session_result_message'] = "Expiry Date Updated ($new_expiry_date)<BR>";

		}

		$done = "1";
		$new_data_unformatted = strtolower(preg_replace("/\r\n/", ", ", $new_data));

}
$page_title = "Bulk Actions";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
<script type="text/javascript">
<!--
function MM_jumpMenu(targ,selObj,restore){ //v3.0
  eval(targ+".location='"+selObj.options[selObj.selectedIndex].value+"'");
  if (restore) selObj.selectedIndex=0;
}
//-->
</script>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
<?php if ($done == "1") { ?>

    <?php if ($action == "CPC") { echo "<BR><strong>New Primary Category:</strong> " . $new_primary_category_string . "<BR><BR>"; } ?>
    <?php if ($action == "CDNS") { echo "<BR><strong>New DNS Profile:</strong> " . $new_dns_string . "<BR><BR>"; } ?>
    <?php if ($action == "CRA") { echo "<BR><strong>New Account:</strong> " . $new_account_string . "<BR><BR>"; } ?>
    <?php if ($action == "AD") { ?>
    	<BR><strong>The Following Domains Were Added:</strong><BR>
    <?php } elseif ($action == "R") { ?>
    	<BR><strong>The Following Domains Were Renewed For <?=$new_renewal_years?> Year<?php if ($new_renewal_years > 1) { echo "s"; } ?>:</strong><BR>
    <?php } elseif ($action == "FR") { ?>
    	<BR><strong>The Following Domains Were Fully Renewed For <?=$new_renewal_years?> Year<?php if ($new_renewal_years > 1) { echo "s"; } ?>:</strong><BR>
    <?php } elseif ($action == "E") { ?>
    	<BR><strong>The Following Domains Were Marked As Expired:</strong><BR>
    <?php } elseif ($action == "S") { ?>
    	<BR><strong>The Following Domains Were Marked As Sold:</strong><BR>
    <?php } elseif ($action == "A") { ?>
    	<BR><strong>The Following Domains Were Marked As Active:</strong><BR>
    <?php } elseif ($action == "T") { ?>
    	<BR><strong>The Following Domains Were Marked As 'In Transfer':</strong><BR>
    <?php } elseif ($action == "PRg") { ?>
    	<BR><strong>The Following Domains Were Marked As 'Pending (Registration)':</strong><BR>
    <?php } elseif ($action == "PRn") { ?>
    	<BR><strong>The Following Domains Were Marked As 'Pending (Renewal)':</strong><BR>
    <?php } elseif ($action == "PO") { ?>
    	<BR><strong>The Following Domains Were Marked As 'Pending (Other)':</strong><BR>
    <?php } elseif ($action == "PRVE") { ?>
    	<BR><strong>The Following Domains Were Marked As 'Private WHOIS':</strong><BR>
    <?php } elseif ($action == "PRVD") { ?>
    	<BR><strong>The Following Domains Were Marked As 'Public WHOIS':</strong><BR>
    <?php } elseif ($action == "CED") { ?>
    	<BR><strong>The Expiry Date Was UpdateD For The Following Domains</strong><BR>
    <?php } elseif ($action == "CPC") { ?>
    	<BR><strong>The Following Domains Had Their Primary Category Changed:</strong><BR>
    <?php } elseif ($action == "CDNS") { ?>
    	<BR><strong>The Following Domains Had Their DNS Profile Changed:</strong><BR>
    <?php } elseif ($action == "CRA") { ?>
    	<BR><strong>The Following Domains Had Their Account Changed:</strong><BR>
    <?php } elseif ($action == "AN") { ?>
    	<BR><strong>The Following Domains Had The Note Appended</strong><BR>
    <?php } ?>
	<BR><?=$new_data_unformatted?><BR><BR><BR>
<?php } ?>
Instead of having to waste time editting domains one-by-one, you can use the below form to execute actions on multiple domains.<BR><BR>
<form name="form1" method="post" action="<?=$PHP_SELF?>">
  <select name="jumpMenu" id="jumpMenu" onChange="MM_jumpMenu('parent',this,0)">
    <option value="bulk-actions.php"<?php if ($action == "") { echo " selected"; } ?>>Click To Choose Action</option>
    <option value="bulk-actions.php?action=AD"<?php if ($action == "AD") { echo " selected"; } ?>>Add Domains</option>
    <option value="bulk-actions.php?action=FR"<?php if ($action == "FR") { echo " selected"; } ?>>Renew Domains (Update Expiry Date, Active, Notes)</option>
    <option value="bulk-actions.php?action=R"<?php if ($action == "R") { echo " selected"; } ?>>Renew Domains (Update Expiry Date Only)</option>
    <option value="bulk-actions.php?action=A"<?php if ($action == "A") { echo " selected"; } ?>>Mark As 'Active'</option>
    <option value="bulk-actions.php?action=T"<?php if ($action == "T") { echo " selected"; } ?>>Mark As 'In Transfer'</option>
	<option value="bulk-actions.php?action=PRg"<?php if ($action == "PRg") { echo " selected"; } ?>>Mark As 'Pending (Registration)'</option>
	<option value="bulk-actions.php?action=PRn"<?php if ($action == "PRn") { echo " selected"; } ?>>Mark As 'Pending (Renewal)'</option>
	<option value="bulk-actions.php?action=PO"<?php if ($action == "PO") { echo " selected"; } ?>>Mark As 'Pending (Other)'</option>
    <option value="bulk-actions.php?action=E"<?php if ($action == "E") { echo " selected"; } ?>>Mark As 'Expired'</option>
    <option value="bulk-actions.php?action=S"<?php if ($action == "S") { echo " selected"; } ?>>Mark As 'Sold'</option>
    <option value="bulk-actions.php?action=CPC"<?php if ($action == "CPC") { echo " selected"; } ?>>Change Primary Category</option>
    <option value="bulk-actions.php?action=CDNS"<?php if ($action == "CDNS") { echo " selected"; } ?>>Change DNS Profile</option>
    <option value="bulk-actions.php?action=CRA"<?php if ($action == "CRA") { echo " selected"; } ?>>Change Registrar Account</option>
    <option value="bulk-actions.php?action=PRVE"<?php if ($action == "PRVE") { echo " selected"; } ?>>Change To Private WHOIS</option>
    <option value="bulk-actions.php?action=PRVD"<?php if ($action == "PRVD") { echo " selected"; } ?>>Change To Public WHOIS</option>
    <option value="bulk-actions.php?action=CED"<?php if ($action == "CED") { echo " selected"; } ?>>Change Expiry Date</option>
    <option value="bulk-actions.php?action=AN"<?php if ($action == "AN") { echo " selected"; } ?>>Add A Note</option>
  </select>
  <BR><BR>

<?php if ($action != "") { ?>
Enter the domains one per line.
<BR><BR>
<textarea name="new_data" cols="60" rows="5"><?php if ($new_data != "") { echo stripslashes($new_data); } else { echo "List of Domains"; } ?></textarea>
<BR><BR>
<?php } ?>

<?php if ($action == "R" || $action == "FR") { ?>
    Renew For: 
    <select name="new_renewal_years">
      <option value="1"<?php if ($new_renewal_years == "1") { echo " selected"; } ?>>1 Year</option>
      <option value="2"<?php if ($new_renewal_years == "2") { echo " selected"; } ?>>2 Years</option>
      <option value="3"<?php if ($new_renewal_years == "3") { echo " selected"; } ?>>3 Years</option>
      <option value="4"<?php if ($new_renewal_years == "4") { echo " selected"; } ?>>4 Years</option>
      <option value="5"<?php if ($new_renewal_years == "5") { echo " selected"; } ?>>5 Years</option>
      <option value="6"<?php if ($new_renewal_years == "6") { echo " selected"; } ?>>6 Years</option>
      <option value="7"<?php if ($new_renewal_years == "7") { echo " selected"; } ?>>7 Years</option>
      <option value="8"<?php if ($new_renewal_years == "8") { echo " selected"; } ?>>8 Years</option>
      <option value="9"<?php if ($new_renewal_years == "9") { echo " selected"; } ?>>9 Years</option>
      <option value="10"<?php if ($renewal_years == "10") { echo " selected"; } ?>>10 Years</option>
    </select>
    <BR><BR>
<?php } elseif ($action == "AD") { ?>
    <strong>Expiry Date (YYYY-MM-DD):</strong><BR><BR>
    <input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $current_timestamp_plus_one_year_date_only; } ?>">
    <BR><BR>
    <strong>Function:</strong><BR><BR>
    <input name="new_function" type="text" size="50" maxlength="255" value="<?=stripslashes($new_function)?>">
    <BR><BR>
    <strong>Status:</strong><BR><BR>
    <input name="new_status" type="text" size="50" maxlength="255" value="<?=stripslashes($new_status)?>">
    <BR><BR>
    <strong>Status Notes:</strong><BR><BR>
    <textarea name="new_status_notes" cols="60" rows="5"><?=stripslashes($new_status_notes)?>
    </textarea>
    <BR><BR>
    <strong>Primary Category:</strong><BR><BR>
    <?php
    $sql_cat = "select id, name
                    from categories
                    where active = '1'
                    order by default_category desc, name asc";
    $result_cat = mysql_query($sql_cat,$connection) or die(mysql_error());
    echo "<select name=\"new_pcid\">";
    while ($row_cat = mysql_fetch_object($result_cat)) {
    
        if ($row_cat->id == $new_pcid) {
    
            echo "<option value=\"$row_cat->id\" selected>[ $row_cat->name ]</option>";
        
        } else {
    
            echo "<option value=\"$row_cat->id\">$row_cat->name</option>";
        
        }
    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>DNS Profile:</strong><BR><BR>
    <?php
    $sql_dns = "select id, name
                    from dns
                    where active = '1'
                    order by name asc";
    $result_dns = mysql_query($sql_dns,$connection) or die(mysql_error());
    echo "<select name=\"new_dnsid\">";
    while ($row_dns = mysql_fetch_object($result_dns)) {
    
        if ($row_dns->id == $new_dnsid) {
    
            echo "<option value=\"$row_dns->id\" selected>[ $row_dns->name ]</option>";
        
        } else {
    
            echo "<option value=\"$row_dns->id\">$row_dns->name</option>";
        
        }
    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>Registrar Account:</strong><BR><BR>
    <?php
    $sql_account = "select ra.id, ra.username, c.name as c_name, r.name as r_name
                    from registrar_accounts as ra, companies as c, registrars as r
                    where ra.company_id = c.id
                    and ra.registrar_id = r.id
                    and ra.active = '1'
                    order by r_name asc, c_name asc, ra.username asc";
    $result_account = mysql_query($sql_account,$connection) or die(mysql_error());
    echo "<select name=\"new_raid\">";
    while ($row_account = mysql_fetch_object($result_account)) {
    
        if ($row_account->id == $new_raid) {
    
            echo "<option value=\"$row_account->id\" selected>[ $row_account->r_name :: $row_account->c_name :: $row_account->username ]</option>";
        
        } else {
    
            echo "<option value=\"$row_account->id\">$row_account->r_name :: $row_account->c_name :: $row_account->username</option>";
        
        }
    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>Privacy Enabled?</strong><BR><BR>
    <?php
    echo "<select name=\"new_privacy\">";
    echo "<option value=\"0\""; if ($new_privacy == "0") echo " selected"; echo ">No</option>";
    echo "<option value=\"1\""; if ($new_privacy == "1") echo " selected"; echo ">Yes</option>";
    echo "</select>";
    ?>
    <BR><BR>
    <strong>Domain Status:</strong><BR><BR>
    <?php
    echo "<select name=\"new_active\">";
    echo "<option value=\"1\""; if ($new_active == "1") echo " selected"; echo ">Active</option>";
    echo "<option value=\"2\""; if ($new_active == "2") echo " selected"; echo ">In Transfer</option>";
    echo "<option value=\"5\""; if ($new_active == "5") echo " selected"; echo ">Pending (Registration)</option>";
    echo "<option value=\"3\""; if ($new_active == "3") echo " selected"; echo ">Pending (Renewal)</option>";
    echo "<option value=\"4\""; if ($new_active == "4") echo " selected"; echo ">Pending (Other)</option>";
    echo "<option value=\"0\""; if ($new_active == "0") echo " selected"; echo ">Expired</option>";
    echo "<option value=\"10\""; if ($new_active == "10") echo " selected"; echo ">Sold</option>";
    echo "</select>";
    ?>
    <BR><BR>
    <strong>Notes:</strong><BR><BR>
    <textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
    <BR><BR>

<?php } elseif ($action == "CPC") { ?>

	<?php
    $sql_cat = "select id, name
				from categories
				where active = '1'
				order by default_category desc, name asc";
    $result_cat = mysql_query($sql_cat,$connection);
    echo "<select name=\"new_pcid\">";
    echo "<option value=\"\""; if ($new_pcid == "") echo " selected"; echo ">"; echo "$choose_text Primary Category</option>";
	while ($row_cat = mysql_fetch_object($result_cat)) { 
    echo "<option value=\"$row_cat->id\""; if ($row_cat->id == $new_pcid) echo " selected"; echo ">"; echo "$row_cat->name</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "CDNS") { ?>

	<?php
    $sql_dns = "select id, name
				from dns
				where active = '1'
				order by name asc";
    $result_dns = mysql_query($sql_dns,$connection);
    echo "<select name=\"new_dnsid\">";
    echo "<option value=\"\""; if ($new_dnsid == "") echo " selected"; echo ">"; echo "$choose_text DNS Profile</option>";
    while ($row_dns = mysql_fetch_object($result_dns)) { 
    echo "<option value=\"$row_dns->id\""; if ($row_dns->id == $new_dnsid) echo " selected"; echo ">"; echo "$row_dns->name</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "CRA") { ?>
	<?php
    $sql_account = "select ra.id as ra_id, ra.username, r.name as r_name, c.name as c_name
                      from registrar_accounts as ra, registrars as r, companies as c, domains as d
                      where ra.registrar_id = r.id
                      and ra.company_id = c.id
                      and ra.id = d.account_id
                      and ra.active = '1'
                      and r.active = '1'
                      and c.active = '1'
                      $is_active_string
                      $cid_string
                      $rid_string
                      $tld_string
                      group by r.name, c.name, ra.username
                      order by r.name asc, c.name asc, ra.username asc";
    $result_account = mysql_query($sql_account,$connection);
    echo "<select name=\"new_raid\">";
    echo "<option value=\"\""; if ($new_raid == "") echo " selected"; echo ">"; echo "$choose_text Registrar Account</option>";
	while ($row_account = mysql_fetch_object($result_account)) { 
    echo "<option value=\"$row_account->ra_id\""; if ($row_account->ra_id == $new_raid) echo " selected"; echo ">"; echo "$row_account->r_name :: $row_account->c_name ($row_account->username)</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "AN") { ?>
<textarea name="new_notes" cols="60" rows="5"><?php if ($new_notes != "") { echo stripslashes($new_notes); } else { echo "New Note"; } ?></textarea>
  <BR><BR>
<?php } elseif ($action == "CED") { ?>
<input name="new_expiry_date" type="text" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $current_timestamp_date_only; } ?>" size="10" maxlength="10">
    <BR><BR>
<?php } ?>

<BR>
<input type="hidden" name="action" value="<?=$action?>">
<?php if ($action == "CDNS") { ?>
<input type="hidden" name="dnsid" value="<?=$new_dnsid?>">
<?php } ?>
<?php if ($action == "CRA") { ?>
<input type="hidden" name="raid" value="<?=$new_raid?>">
<?php } ?>
<input type="submit" name="button" value="Perform Bulk Action &raquo;">
</form>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>