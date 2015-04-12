<?php
/**
 * /assets/edit/registrar-fees.php
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
include("../../_includes/timestamps/current-timestamp.inc.php");
include("../../_includes/system/functions/error-reporting.inc.php");

$page_title = "Registrar Fees";
$software_section = "registrar-fees";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$rid = $_GET['rid'];
$feeid = $_GET['feeid'];
$tld = $_GET['tld'];

$new_tld = $_POST['new_tld'];
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_transfer_fee = $_POST['new_transfer_fee'];
$new_privacy_fee = $_POST['new_privacy_fee'];
$new_misc_fee = $_POST['new_misc_fee'];
$new_currency_id = $_POST['new_currency_id'];
$new_rid = $_POST['new_rid'];

$fee_id = $_POST['fee_id'];
$initial_fee = $_POST['initial_fee'];
$renewal_fee = $_POST['renewal_fee'];
$transfer_fee = $_POST['transfer_fee'];
$privacy_fee = $_POST['privacy_fee'];
$misc_fee = $_POST['misc_fee'];
$currency = $_POST['currency'];

$which_form = $_POST['which_form'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($which_form == "edit") {

        $count = 0;

        foreach ($fee_id as $value) {

            $sql = "UPDATE fees
                    SET initial_fee = '" . $initial_fee[$count] . "',
                        renewal_fee = '" . $renewal_fee[$count] . "',
                        transfer_fee = '" . $transfer_fee[$count] . "',
                        privacy_fee = '" . $privacy_fee[$count] . "',
                        misc_fee = '" . $misc_fee[$count] . "',
                        currency_id = '" . $currency[$count] . "',
                        update_time = '" . $current_timestamp . "'
                    WHERE id = '" . $fee_id[$count] . "'";
            $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

            $sql = "UPDATE domains d
                    JOIN fees f ON d.fee_id = f.id
                    SET d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                    WHERE d.privacy = '1'
                      AND d.fee_id = '" . $fee_id[$count] . "'";
            $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

            $sql = "UPDATE domains d
                    JOIN fees f ON d.fee_id = f.id
                    SET d.total_cost = f.renewal_fee + f.misc_fee
                    WHERE d.privacy = '0'
                      AND d.fee_id = '" . $fee_id[$count] . "'";
            $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

            $count++;

        }

        $_SESSION['result_message'] = "The Registrar Fees have been updated<BR>";
        include("../../_includes/system/update-conversion-rates.inc.php");

    } elseif ($which_form == "add") {

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
                    WHERE registrar_id = '" . $new_rid . "'
                      AND tld = '" . $new_tld . "'";
            $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

            if (mysqli_num_rows($result) > 0) {

                $sql = "UPDATE fees
                        SET initial_fee = '" . $new_initial_fee . "',
                            renewal_fee = '" . $new_renewal_fee . "',
                            transfer_fee = '" . $new_transfer_fee . "',
                            privacy_fee = '" . $new_privacy_fee . "',
                            misc_fee = '" . $new_misc_fee . "',
                            currency_id = '" . $new_currency_id . "',
                            update_time = '" . $current_timestamp . "'
                        WHERE registrar_id = '" . $new_rid . "'
                          AND tld = '" . $new_tld . "'";
                $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

                $sql = "SELECT id
                        FROM fees
                        WHERE registrar_id = '" . $new_rid . "'
                          AND tld = '" . $new_tld . "'
                          AND currency_id = '" . $new_currency_id . "'
                        LIMIT 1";

                $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

                while ($row = mysqli_fetch_object($result)) {
                    $new_fee_id = $row->id;
                }

                $sql = "UPDATE domains
                        SET fee_id = '" . $new_fee_id . "',
                            update_time = '" . $current_timestamp . "'
                        WHERE registrar_id = '" . $new_rid . "'
                          AND tld = '" . $new_tld . "'";
                $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

                $sql = "UPDATE domains d
                    JOIN fees f ON d.fee_id = f.id
                    SET d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                    WHERE d.privacy = '1'
                      AND d.fee_id = '" . $new_fee_id . "'";
                $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

                $sql = "UPDATE domains d
                    JOIN fees f ON d.fee_id = f.id
                    SET d.total_cost = f.renewal_fee + f.misc_fee
                    WHERE d.privacy = '0'
                      AND d.fee_id = '" . $new_fee_id . "'";
                $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

                $rid = $new_rid;

                $_SESSION['result_message'] = "The fee for <font class=\"highlight\">.$new_tld</font> has been updated<BR>";

                $temp_input_user_id = $_SESSION['user_id'];
                $temp_input_default_currency = $_SESSION['default_currency'];
                include("../../_includes/system/update-conversion-rates.inc.php");

            } else {

                $sql = "INSERT INTO fees
                        (registrar_id, tld, initial_fee, renewal_fee, transfer_fee, privacy_fee, misc_fee, currency_id, insert_time) VALUES
                        ('" . $new_rid . "', '" . mysqli_real_escape_string($connection, $new_tld) . "', '" . $new_initial_fee . "', '" . $new_renewal_fee . "', '" . $new_transfer_fee . "', '" . $new_privacy_fee . "', '" . $new_misc_fee . "', '" . $new_currency_id . "', '" . $current_timestamp . "')";
                $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

                $sql = "SELECT id
                        FROM fees
                        WHERE registrar_id = '" . $new_rid . "'
                          AND tld = '" . $new_tld . "'
                          AND currency_id = '" . $new_currency_id . "'
                        ORDER BY id DESC
                        LIMIT 1";
                $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

                while ($row = mysqli_fetch_object($result)) {
                    $new_fee_id = $row->id;
                }

                $sql = "UPDATE domains
                        SET fee_id = '" . $new_fee_id . "',
                            update_time = '" . $current_timestamp . "'
                        WHERE registrar_id = '" . $new_rid . "'
                          AND tld = '" . $new_tld . "'";
                $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

                $sql = "UPDATE domains d
                    JOIN fees f ON d.fee_id = f.id
                    SET d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                    WHERE d.privacy = '1'
                      AND d.fee_id = '" . $new_fee_id . "'";
                $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

                $sql = "UPDATE domains d
                    JOIN fees f ON d.fee_id = f.id
                    SET d.total_cost = f.renewal_fee + f.misc_fee
                    WHERE d.privacy = '0'
                      AND d.fee_id = '" . $new_fee_id . "'";
                $result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

                $_SESSION['result_message'] = "The fee for <font class=\"highlight\">.$new_tld</font> has been added<BR>";

                $temp_input_user_id = $_SESSION['user_id'];
                $temp_input_default_currency = $_SESSION['default_currency'];
                include("../../_includes/system/check-domain-fees.inc.php");
                include("../../_includes/system/update-conversion-rates.inc.php");

            }

        }

    }

}

if ($del == "1") {
	$_SESSION['result_message'] = "Are you sure you want to delete this Registrar Fee?<BR><BR><a href=\"$PHP_SELF?rid=$rid&tld=$tld&feeid=$feeid&really_del=1\">YES, REALLY DELETE THIS REGISTRAR FEE</a><BR>";
}

if ($really_del == "1") {

	$sql = "SELECT *
			FROM fees
			WHERE id = '" . $feeid . "'
			  AND registrar_id = '" . $rid . "'
			  AND tld = '" . $tld . "'";
	$result = mysqli_query($connection, $sql);
	
	if (mysqli_num_rows($result) == 0) {

		$_SESSION['result_message'] = "The fee you're trying to delete doesn't exist<BR>";

		header("Location: registrar-fees.php?rid=$rid");
		exit;

	} else {

		$sql = "DELETE FROM fees
				WHERE id = '" . $feeid . "'
				  AND registrar_id = '" . $rid . "'
				  AND tld = '" . $tld . "'";
		$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);
		
		$sql = "UPDATE domains
				SET fee_id = '0',
					update_time = '" . $current_timestamp . "'
				WHERE fee_id = '" . $feeid . "'
				  AND registrar_id = '" . $rid . "'
				  AND tld = '" . $tld . "'";
		$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);
		
		$_SESSION['result_message'] = "The fee for <font class=\"highlight\">.$tld</font> has been deleted<BR>";

		$temp_input_user_id = $_SESSION['user_id'];
		$temp_input_default_currency = $_SESSION['default_currency'];
        include("../../_includes/system/check-domain-fees.inc.php");
		include("../../_includes/system/update-conversion-rates.inc.php");

        header("Location: registrar-fees.php?rid=$rid");
		exit;

	}

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<?php
$sql = "SELECT name
		FROM registrars
		WHERE id = '" . $rid . "'";
$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);
while ($row = mysqli_fetch_object($result)) { $temp_registrar_name = $row->name; } ?>
The below fees are for the registrar <a href="registrar.php?rid=<?php echo $rid; ?>"><?php echo $temp_registrar_name; ?></a>.<BR><BR>
<?php
$sql = "SELECT tld
		FROM domains
		WHERE registrar_id = '" . $rid . "'
		  AND fee_id = '0'
		GROUP BY tld
		ORDER BY tld asc";
$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);
if (mysqli_num_rows($result) > 0) {
?>
    <BR><a name="missingfees"></a><font class="subheadline">Missing TLD Fees</font><BR><BR>
    <?php
	$count = 0;
    while ($row = mysqli_fetch_object($result)) {
        $temp_all_missing_fees = $temp_all_missing_fees .= ".$row->tld, ";
		$count++;
    }
	$all_missing_fees = substr($temp_all_missing_fees, 0, -2); 
    ?>
    <?php echo $all_missing_fees; ?><BR><BR>
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
		WHERE registrar_id = '" . $rid . "'
		  AND active not in ('0', '10')
		GROUP BY tld
		ORDER BY tld";
$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

if (mysqli_num_rows($result) != 0) {
?>
    <BR><font class="subheadline">TLDs Linked to Active Domains</font><BR><BR>
    <?php
    while ($row = mysqli_fetch_object($result)) {
        
        $sql_temp = "SELECT tld, fee_id
                     FROM domains
                     WHERE registrar_id = '" . $rid . "'
                       AND tld = '" . $row->tld . "'";
        $result_temp = mysqli_query($connection, $sql_temp) or outputOldSqlError($connection);
        while ($row_temp = mysqli_fetch_object($result_temp)) { $temp_fee_id = $row_temp->fee_id; }
        
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
<font class="subheadline">Add A New TLD Fee</font><BR>
<form name="add_registrar_fee_form" method="post" action="<?php echo $PHP_SELF; ?>">
<table class="main_table" cellpadding="0" cellspacing="0">
	<tr class="main_table_row_heading_active">
    	<td class="main_table_cell_heading_active">
        	<strong>TLD</strong><BR>
            <input name="new_tld" type="text" value="<?php echo $new_tld; ?>" size="4">
		</td>
		<td class="main_table_cell_heading_active">
        	<strong>Initial Fee</strong><BR>
            <input name="new_initial_fee" type="text" value="<?php echo $new_initial_fee; ?>" size="4">
		</td>
		<td class="main_table_cell_heading_active">
        	<strong>Renewal Fee</strong><BR>
            <input name="new_renewal_fee" type="text" value="<?php echo $new_renewal_fee; ?>" size="4">
		</td>
        <td class="main_table_cell_heading_active">
            <strong>Transfer Fee</strong><BR>
            <input name="new_transfer_fee" type="text" value="<?php echo $new_transfer_fee; ?>" size="4">
        </td>
        <td class="main_table_cell_heading_active">
            <strong>Privacy Fee</strong><BR>
            <input name="new_privacy_fee" type="text" value="<?php echo $new_privacy_fee; ?>" size="4">
        </td>
        <td class="main_table_cell_heading_active">
            <strong>Misc Fee</strong><BR>
            <input name="new_misc_fee" type="text" value="<?php echo $new_misc_fee; ?>" size="4">
        </td>
	  	<td class="main_table_cell_heading_active"><strong>Currency</strong><BR>
		  <select name="new_currency_id" id="new_currency">
		  	<?php
			$sql = "SELECT id, currency, name, symbol
					FROM currencies
					ORDER BY currency";
			$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);
			while ($row = mysqli_fetch_object($result)) {
			
			if ($row->currency == $_SESSION['default_currency']) {
			?>
			    <option value="<?php echo $row->id; ?>" selected><?php echo "$row->name ($row->currency $row->symbol)"; ?></option>
			<?php
			} else {
			?>
			    <option value="<?php echo $row->id; ?>"><?php echo "$row->name ($row->currency $row->symbol)"; ?></option>
			<?php
			}
			}
			?>
	      </select>
	    </td>
	</tr>
</table>
    <input type="hidden" name="new_rid" value="<?php echo $rid; ?>"><BR>
    <input type="hidden" name="which_form" value="add"><BR>
    <input type="submit" name="button" value="Add This TLD Fee &raquo;">
</form>
<BR><BR>
<font class="subheadline">TLD Fees</font><BR>
<form name="edit_registrar_fee_form" method="post" action="<?php echo $PHP_SELF; ?>">
<table class="main_table" cellpadding="0" cellspacing="0">
	<tr class="main_table_row_heading_active">
    	<td class="main_table_cell_heading_active"><strong>TLD</strong></td>
        <td class="main_table_cell_heading_active"><strong>Initial Fee</strong></td>
        <td class="main_table_cell_heading_active"><strong>Renewal Fee</strong></td>
        <td class="main_table_cell_heading_active"><strong>Transfer Fee</strong></td>
        <td class="main_table_cell_heading_active"><strong>Privacy Fee</strong></td>
        <td class="main_table_cell_heading_active"><strong>Misc Fee</strong></td>
        <td class="main_table_cell_heading_active"><strong>Currency</strong></td>
	</tr>
<?php
$sql = "SELECT f.id, f.tld, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, c.currency, c.symbol, c.symbol_order, c.symbol_space
		FROM fees AS f, currencies AS c
		WHERE f.currency_id = c.id
		  AND f.registrar_id = '" . $rid . "'
		ORDER BY f.tld asc";
$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);
$count = 0;
while ($row = mysqli_fetch_object($result)) {
?>
	<tr class="main_table_row_active">
    	<td class="main_table_cell_active">.<?php echo htmlentities($row->tld); ?></td>
        <td class="main_table_cell_active">
            <input type="hidden" name="fee_id[<?php echo $count; ?>]" value="<?php echo $row->id; ?>">
            <input name="initial_fee[<?php echo $count; ?>]" type="text" value="<?php echo $row->initial_fee; ?>" size="4">
        </td>
        <td class="main_table_cell_active">
            <input name="renewal_fee[<?php echo $count; ?>]" type="text" value="<?php echo $row->renewal_fee; ?>" size="4">
        </td>
        <td class="main_table_cell_active">
            <input name="transfer_fee[<?php echo $count; ?>]" type="text" value="<?php echo $row->transfer_fee; ?>" size="4">
        </td>
        <td class="main_table_cell_active">
            <input name="privacy_fee[<?php echo $count; ?>]" type="text" value="<?php echo $row->privacy_fee; ?>" size="4">
        </td>
        <td class="main_table_cell_active">
            <input name="misc_fee[<?php echo $count; ?>]" type="text" value="<?php echo $row->misc_fee; ?>" size="4">
        </td>
        <td class="main_table_cell_active">
            <select name="currency[<?php echo $count; ?>]" id="new_currency">
                <?php
                $sql_currency = "SELECT id, currency, name, symbol
                                 FROM currencies
                                 ORDER BY currency";
                $result_currency = mysqli_query($connection, $sql_currency) or outputOldSqlError($connection);
                while ($row_currency = mysqli_fetch_object($result_currency)) {

                    if ($row_currency->currency == $row->currency) {
                        ?>
                        <option value="<?php echo $row_currency->id; ?>" selected><?php echo "$row_currency->name ($row_currency->currency $row_currency->symbol)"; ?></option>
                    <?php
                    } else {
                        ?>
                        <option value="<?php echo $row_currency->id; ?>"><?php echo "$row_currency->name ($row_currency->currency $row_currency->symbol)"; ?></option>
                    <?php
                    }
                }
                ?>
            </select>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[<a class="invisiblelink" href="registrar-fees.php?rid=<?php echo $rid; ?>&tld=<?php echo $row->tld; ?>&feeid=<?php echo $row->id; ?>&del=1">delete</a>]
        </td>
	</tr>
<?php
$count++;
}
?>
</table>
    <input type="hidden" name="which_form" value="edit"><BR>
<BR><input type="submit" name="button" value="Update Registrar Fees &raquo;">
</form>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
