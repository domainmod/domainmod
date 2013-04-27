<?php
// /edit/registrar-fees.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Editting A Registrar's Fees";
$software_section = "registrars";

// 'Delete Registrar Fee' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$rid = $_GET['rid'];
$feeid = $_GET['feeid'];
$tld = $_GET['tld'];

// Form Variables
$new_tld = $_POST['new_tld'];
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_transfer_fee = $_POST['new_transfer_fee'];
$new_currency_id = $_POST['new_currency_id'];
$new_rid = $_POST['new_rid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_rid == "" || $new_tld == "" || $new_initial_fee == "" || $new_renewal_fee == "" || $new_transfer_fee == "" || $new_currency_id == "" || $new_currency_id == "0") {
		
		if ($new_tld == "") $_SESSION['result_message'] .= "Please enter the TLD<BR>";
		if ($new_initial_fee == "") $_SESSION['result_message'] .= "Please enter the initial fee<BR>";
		if ($new_renewal_fee == "") $_SESSION['result_message'] .= "Please enter the renewal fee<BR>";
		if ($new_transfer_fee == "") $_SESSION['result_message'] .= "Please enter the transfer fee<BR>";
		if ($new_currency_id == "" || $new_currency_id == "0") $_SESSION['result_message'] .= "There was a problem with the currency you chose<BR>";

	} else {
		
		$new_tld = trim($new_tld, ". \t\n\r\0\x0B");

		$sql = "SELECT *
				FROM fees
				WHERE registrar_id = '$new_rid'
				  AND tld = '$new_tld'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		if (mysql_num_rows($result) > 0) {
			
			$sql = "UPDATE fees
					SET initial_fee = '$new_initial_fee',
						renewal_fee = '$new_renewal_fee',
						transfer_fee = '$new_transfer_fee',
						currency_id = '$new_currency_id',
						update_time = '$current_timestamp'
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'";
			$result = mysql_query($sql,$connection) or die(mysql_error());

			$sql = "SELECT id
					FROM fees
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'
					  AND currency_id = '$new_currency_id'
					LIMIT 1";
	
			$result = mysql_query($sql,$connection) or die(mysql_error());

			while ($row = mysql_fetch_object($result)) {
				$new_fee_id = $row->id;
			}

			$sql = "UPDATE domains
					SET fee_id = '$new_fee_id',
						update_time = '$current_timestamp'
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
	
			$rid = $new_rid;

			$_SESSION['result_message'] = "The fee for <font class=\"highlight\">.$new_tld</font> has been updated<BR>";
			
			header("Location: registrar-fees.php?rid=$new_rid");
			exit;

		} else {
			
			$sql = "INSERT INTO fees 
					(registrar_id, tld, initial_fee, renewal_fee, transfer_fee, currency_id, insert_time) VALUES 
					('$new_rid', '" . mysql_real_escape_string($new_tld) . "', '$new_initial_fee', '$new_renewal_fee', '$new_transfer_fee', '$new_currency_id', '$current_timestamp')";
			$result = mysql_query($sql,$connection) or die(mysql_error());

			$sql = "SELECT id
					FROM fees
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'
					  AND currency_id = '$new_currency_id'
					ORDER BY id desc
					LIMIT 1";
	
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			while ($row = mysql_fetch_object($result)) {
				$new_fee_id = $row->id;
			}

			$sql = "UPDATE domains
					SET fee_id = '$new_fee_id',
						update_time = '$current_timestamp'
					WHERE registrar_id = '$new_rid'
					  AND tld = '$new_tld'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
	
			$_SESSION['result_message'] = "The fee for <font class=\"highlight\">.$new_tld</font> has been added<BR>";
			
			header("Location: registrar-fees.php?rid=$new_rid");
			exit;

		}

	}

} else {

	include("../_includes/system/update-domain-fees.inc.php");
	
}
if ($del == "1") {
	$_SESSION['result_message'] = "Are you sure you want to delete this Registrar Fee?<BR><BR><a href=\"$PHP_SELF?rid=$rid&tld=$tld&feeid=$feeid&really_del=1\">YES, REALLY DELETE THIS REGISTRAR FEE</a><BR>";
}

if ($really_del == "1") {

	$sql = "SELECT *
			FROM fees
			WHERE id = '$feeid'
			  AND registrar_id = '$rid'
			  AND tld = '$tld'";
	$result = mysql_query($sql,$connection);
	
	if (mysql_num_rows($result) == 0) {

		$_SESSION['result_message'] = "The fee you're trying to delete doesn't exist<BR>";

		header("Location: registrar-fees.php?rid=$rid");
		exit;

	} else {

		$sql = "DELETE FROM fees
				WHERE id = '$feeid'
				  AND registrar_id = '$rid'
				  AND tld = '$tld'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE domains
				SET fee_id = '0',
					update_time = '$current_timestamp'
				WHERE fee_id = '$feeid'
				  AND registrar_id = '$rid'
				  AND tld = '$tld'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['result_message'] = "The fee for <font class=\"highlight\">.$tld</font> has been deleted<BR>";

		header("Location: registrar-fees.php?rid=$rid");
		exit;

	}

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<?php
$sql = "SELECT name
		FROM registrars
		WHERE id = '$rid'";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) { $temp_registrar_name = $row->name; } ?>
The below fees are for the registrar <a href="registrar.php?rid=<?=$rid?>"><?=$temp_registrar_name?></a>.<BR><BR>
<?php
$sql = "SELECT tld
		FROM domains
		WHERE registrar_id = '$rid'
		  AND fee_id = '0'
		GROUP BY tld
		ORDER BY tld asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
if (mysql_num_rows($result) > 0) {
?>
    <BR><a name="missingfees"></a><font class="subheadline">Missing TLD Fees</font><BR><BR>
    <?php
	$count = 0;
    while ($row = mysql_fetch_object($result)) {
        $temp_all_missing_fees = $temp_all_missing_fees .= ".$row->tld, ";
		$count++;
    }
	$all_missing_fees = substr($temp_all_missing_fees, 0, -2); 
    ?>
    <?=$all_missing_fees?><BR><BR>
    <?php if ($count > 1) { ?>
	    <strong>Please update the fees for these TLDs below in order to ensure proper domain accounting.</strong>
	<?php } else { ?>
	    <strong>Please update the fees for this TLD below in order to ensure proper domain accounting.</strong>
    <?php } ?>
    <BR><BR>
<?php
}
?>
<?php
$sql = "SELECT tld 
		FROM domains
		WHERE registrar_id = '$rid'
		  AND active not in ('0', '10')
		GROUP BY tld
		ORDER BY tld";
$result = mysql_query($sql,$connection) or die(mysql_error());

if (mysql_num_rows($result) != 0) {
?>
    <BR><BR><font class="subheadline">TLDs Linked to Active Domains</font><BR><BR>
    <?php
    while ($row = mysql_fetch_object($result)) {
        
        $sql_temp = "SELECT tld, fee_id
                     FROM domains
                     WHERE registrar_id = '$rid'
                       AND tld = '$row->tld'";
        $result_temp = mysql_query($sql_temp,$connection) or die(mysql_error());
        while ($row_temp = mysql_fetch_object($result_temp)) { $temp_fee_id = $row_temp->fee_id; }
        
        if ($temp_fee_id == "0") {
            $temp_all_tlds = $temp_all_tlds .= "<font class=\"highlight\">.$row->tld</font>, ";
        } else {
            $temp_all_tlds = $temp_all_tlds .= ".$row->tld, ";
        }
    
    }

	$all_tlds = substr($temp_all_tlds, 0, -2); 
	echo $all_tlds;
	echo "<BR><BR><BR>";

}
?>
<BR>
<font class="subheadline">Add/Update TLD Fee</font><BR><BR>
<form name="edit_registrar_fee_form" method="post" action="<?=$PHP_SELF?>">
<table class="main_table">
	<tr class="main_table_row_heading_active">
    	<td class="main_table_cell_heading_active">
        	<strong>TLD</strong><BR>
            <input name="new_tld" type="text" value="<?=$new_tld?>" size="10">
		</td>
		<td class="main_table_cell_heading_active">
        	<strong>Initial Fee</strong><BR>
            <input name="new_initial_fee" type="text" value="<?=$new_initial_fee?>" size="10">
		</td>
		<td class="main_table_cell_heading_active">
        	<strong>Renewal Fee</strong><BR>
            <input name="new_renewal_fee" type="text" value="<?=$new_renewal_fee?>" size="10">
		</td>
		<td class="main_table_cell_heading_active">
        	<strong>Transfer Fee</strong><BR>
            <input name="new_transfer_fee" type="text" value="<?=$new_transfer_fee?>" size="10">
		</td>
	  	<td class="main_table_cell_heading_active"><strong>Currency</strong><BR>
		  <select name="new_currency_id" id="new_currency">
		  	<?php
			$sql = "SELECT id, currency, name, symbol
					FROM currencies
					ORDER BY currency";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			while ($row = mysql_fetch_object($result)) {
			
			if ($row->currency == $_SESSION['default_currency']) {
			?>
			    <option value="<?=$row->id?>" selected><?php echo "$row->name ($row->currency $row->symbol)"; ?></option>
			<?php
			} else {
			?>
			    <option value="<?=$row->id?>"><?php echo "$row->name ($row->currency $row->symbol)"; ?></option>
			<?php
			}
			}
			?>
	      </select>
	    </td>
	</tr>
</table>
<input type="hidden" name="new_rid" value="<?=$rid?>">
<BR><input type="submit" name="button" value="Add/Update This TLD Fee &raquo;">
</form>
<BR><BR>
<font class="subheadline">TLD Fees</font><BR><BR>
<table class="main_table">
	<tr class="main_table_row_heading_active">
    	<td class="main_table_cell_heading_active"><strong>TLD</strong></td>
        <td class="main_table_cell_heading_active"><strong>Initial Fee</strong></td>
        <td class="main_table_cell_heading_active"><strong>Renewal Fee</strong></td>
        <td class="main_table_cell_heading_active"><strong>Transfer Fee</strong></td>
        <td class="main_table_cell_heading_active"><strong>Currency</strong></td>
	</tr>
<?php
$sql = "SELECT f.id, f.tld, f.initial_fee, f.renewal_fee, f.transfer_fee, c.currency 
		FROM fees AS f, currencies AS c
		WHERE f.currency_id = c.id
		  AND f.registrar_id = '$rid'
		ORDER BY f.tld asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
while ($row = mysql_fetch_object($result)) {
?>
	<tr class="main_table_row_active">
    	<td class="main_table_cell_active">.<?=$row->tld?></td>
        <td class="main_table_cell_active">
			<?php
			$temp_input_amount = $row->initial_fee;
			$temp_input_conversion = "";
			include("../_includes/system/convert-and-format-currency.inc.php");
			echo $temp_output_amount;
            ?>
		</td>
        <td class="main_table_cell_active">
			<?php
			$temp_input_amount = $row->renewal_fee;
			$temp_input_conversion = "";
			include("../_includes/system/convert-and-format-currency.inc.php");
			echo $temp_output_amount;
            ?>
		</td>
        <td class="main_table_cell_active">
			<?php
			$temp_input_amount = $row->transfer_fee;
			$temp_input_conversion = "";
			include("../_includes/system/convert-and-format-currency.inc.php");
			echo $temp_output_amount;
            ?>
		</td>
        <td class="main_table_cell_active"><?=$row->currency?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[<a class="invisiblelink" href="registrar-fees.php?rid=<?=$rid?>&tld=<?=$row->tld?>&feeid=<?=$row->id?>&del=1">delete</a>]
        </td>
	</tr>
<?php
}
?>
</table>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>