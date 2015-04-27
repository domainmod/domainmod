<?php
/**
 * /reporting/domains/registrar-fees.php
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
include("../../_includes/classes/Error.class.php");

$error = new DomainMOD\Error();

$page_title = $reporting_section_title;
$page_subtitle = "Domain Registrar Fee Report";
$software_section = "reporting-domain-registrar-fee-report";
$report_name = "domain-registrar-fee-report";

// Form Variables
$export = $_GET['export'];
$all = (integer) urlencode($_GET['all']);

if ($all == "1") {

	$sql = "SELECT r.id, r.name AS registrar, f.id AS fee_id, f.tld, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order, c.symbol_space, count(*) AS number_of_fees_total
			FROM registrars AS r, fees AS f, currencies AS c
			WHERE r.id = f.registrar_id
			  AND f.currency_id = c.id
			GROUP BY r.name, f.tld
			ORDER BY r.name, f.tld";
	
} else {

	$sql = "SELECT r.id, r.name AS registrar, d.tld, f.id AS fee_id, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order, c.symbol_space, count(*) AS number_of_fees_total
			FROM registrars AS r, domains AS d, fees AS f, currencies AS c
			WHERE r.id = d.registrar_id
			  AND d.fee_id = f.id
			  AND f.currency_id = c.id
			  AND d.active NOT IN ('0', '10')
			GROUP BY r.name, d.tld
			ORDER BY r.name, d.tld";

}

$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
$total_rows = mysqli_num_rows($result);

if ($total_rows > 0) {

	if ($export == "1") {

		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
	
		$current_timestamp_unix = strtotime($current_timestamp);
		if ($all == "1") {
			$export_filename = "registrar_fee_report_all_" . $current_timestamp_unix . ".csv";
		} else {
			$export_filename = "registrar_fee_report_active_" . $current_timestamp_unix . ".csv";
		}
		include("../../_includes/system/export/header.inc.php");
	
		$row_content[$count++] = $page_subtitle;
		include("../../_includes/system/export/write-row.inc.php");
	
		fputcsv($file_content, $blank_line);

		if ($all == "1") {
			$row_content[$count++] = "All Registrar Fees";
		} else {
			$row_content[$count++] = "Active Registrar Fees";
		}
		include("../../_includes/system/export/write-row.inc.php");

		fputcsv($file_content, $blank_line);

		$row_content[$count++] = "Registrar";
		$row_content[$count++] = "TLD";
		$row_content[$count++] = "Initial Fee";
		$row_content[$count++] = "Renewal Fee";
        $row_content[$count++] = "Transfer Fee";
        $row_content[$count++] = "Privacy Fee";
        $row_content[$count++] = "Misc Fee";
		$row_content[$count++] = "Currency";
		$row_content[$count++] = "Domains";
		$row_content[$count++] = "Inserted";
		$row_content[$count++] = "Updated";
		include("../../_includes/system/export/write-row.inc.php");
	
		$new_registrar = "";
		$last_registrar = "";
		$new_tld = "";
		$last_tld = "";

		if (mysqli_num_rows($result) > 0) {
	
			while ($row = mysqli_fetch_object($result)) {
				
				$new_registrar = $row->registrar;
				$new_tld = $row->tld;

				$temp_input_amount = $row->initial_fee;
				$temp_input_conversion = "";
				$temp_input_currency_symbol = $row->symbol;
				$temp_input_currency_symbol_order = $row->symbol_order;
				$temp_input_currency_symbol_space = $row->symbol_space;
				include("../../_includes/system/convert-and-format-currency.inc.php");
				$row->initial_fee = $temp_output_amount;
	
				$temp_input_amount = $row->renewal_fee;
				$temp_input_conversion = "";
				$temp_input_currency_symbol = $row->symbol;
				$temp_input_currency_symbol_order = $row->symbol_order;
				$temp_input_currency_symbol_space = $row->symbol_space;
				include("../../_includes/system/convert-and-format-currency.inc.php");
				$row->renewal_fee = $temp_output_amount;

                $temp_input_amount = $row->transfer_fee;
                $temp_input_conversion = "";
                $temp_input_currency_symbol = $row->symbol;
                $temp_input_currency_symbol_order = $row->symbol_order;
                $temp_input_currency_symbol_space = $row->symbol_space;
                include("../../_includes/system/convert-and-format-currency.inc.php");
                $row->transfer_fee = $temp_output_amount;

                $temp_input_amount = $row->privacy_fee;
                $temp_input_conversion = "";
                $temp_input_currency_symbol = $row->symbol;
                $temp_input_currency_symbol_order = $row->symbol_order;
                $temp_input_currency_symbol_space = $row->symbol_space;
                include("../../_includes/system/convert-and-format-currency.inc.php");
                $row->privacy_fee = $temp_output_amount;

                $temp_input_amount = $row->misc_fee;
                $temp_input_conversion = "";
                $temp_input_currency_symbol = $row->symbol;
                $temp_input_currency_symbol_order = $row->symbol_order;
                $temp_input_currency_symbol_space = $row->symbol_space;
                include("../../_includes/system/convert-and-format-currency.inc.php");
                $row->misc_fee = $temp_output_amount;

                $row_content[$count++] = $row->registrar;
				$row_content[$count++] = '.' . $row->tld;
				$row_content[$count++] = $row->initial_fee;
				$row_content[$count++] = $row->renewal_fee;
                $row_content[$count++] = $row->transfer_fee;
                $row_content[$count++] = $row->privacy_fee;
                $row_content[$count++] = $row->misc_fee;
				$row_content[$count++] = $row->currency;
				
				$sql_domain_count = "SELECT count(*) AS total_domain_count
									 FROM domains
									 WHERE registrar_id = '" . $row->id . "'
									   AND fee_id = '" . $row->fee_id . "'
									   AND active NOT IN ('0', '10')";
				$result_domain_count = mysqli_query($connection, $sql_domain_count);

				while ($row_domain_count = mysqli_fetch_object($result_domain_count)) {

					$row_content[$count++] = $row_domain_count->total_domain_count;

				}

				$row_content[$count++] = $row->insert_time;
				$row_content[$count++] = $row->update_time;
				include("../../_includes/system/export/write-row.inc.php");

				$last_registrar = $row->registrar;
	
			}
	
		}
	
		include("../../_includes/system/export/footer.inc.php");

	}

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
    <a href="<?php echo $PHP_SELF; ?>?all=1">View All</a> or <a href="<?php echo $PHP_SELF; ?>?all=0">Active Only</a>
    <?php if ($total_rows > 0) { ?>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="<?php echo $PHP_SELF; ?>?export=1&all=<?php echo $all; ?>">EXPORT REPORT</a>]</strong>
    <?php } ?>
<?php include("../../_includes/layout/table-export-bottom.inc.php"); ?>

<BR><font class="subheadline"><?php echo $page_subtitle; ?></font><BR>
<BR>
<?php if ($all == "1") { ?>
    <strong>All Registrar Fees</strong><BR>
<?php } else { ?>
    <strong>Active Registrar Fees</strong><BR>
<?php }

if ($total_rows > 0) { ?>

    <table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Registrar</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">TLD</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Initial Fee</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Renewal Fee</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Transfer Fee</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Privacy Fee</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Misc Fee</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Currency</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Domains</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Last Updated</font>
        </td>
    </tr>
    <?php

    $new_registrar = "";
    $last_registrar = "";
    $new_tld = "";
    $last_tld = "";

    while ($row = mysqli_fetch_object($result)) {

        $new_registrar = $row->registrar;
        $new_tld = $row->tld;

        if ($row->update_time == "0000-00-00 00:00:00") {
            $row->update_time = $row->insert_time;
        }
        $last_updated = date('Y-m-d', strtotime($row->update_time));

        if ($new_registrar != $last_registrar || $new_registrar == "") { ?>

            <tr class="main_table_row_active">
                <td class="main_table_cell_active"><a class="invisiblelink" href="../../assets/edit/registrar-fees.php?rid=<?php echo $row->id; ?>"><?php echo $row->registrar; ?></a></td>
                <td class="main_table_cell_active"><a class="invisiblelink" href="../../assets/edit/registrar-fees.php?rid=<?php echo $row->id; ?>">.<?php echo $row->tld; ?></a></td>
                <td class="main_table_cell_active">
                    <?php
                    $temp_input_amount = $row->initial_fee;
                    $temp_input_conversion = "";
                    $temp_input_currency_symbol = $row->symbol;
                    $temp_input_currency_symbol_order = $row->symbol_order;
                    $temp_input_currency_symbol_space = $row->symbol_space;
                    include("../../_includes/system/convert-and-format-currency.inc.php");
                    $row->initial_fee = $temp_output_amount;
                    ?>
                    <?php echo $row->initial_fee; ?>
                </td>
                <td class="main_table_cell_active">
                    <?php
                    $temp_input_amount = $row->renewal_fee;
                    $temp_input_conversion = "";
                    $temp_input_currency_symbol = $row->symbol;
                    $temp_input_currency_symbol_order = $row->symbol_order;
                    $temp_input_currency_symbol_space = $row->symbol_space;
                    include("../../_includes/system/convert-and-format-currency.inc.php");
                    $row->renewal_fee = $temp_output_amount;
                    ?>
                    <?php echo $row->renewal_fee; ?>
                </td>
                <td class="main_table_cell_active">
                    <?php
                    $temp_input_amount = $row->transfer_fee;
                    $temp_input_conversion = "";
                    $temp_input_currency_symbol = $row->symbol;
                    $temp_input_currency_symbol_order = $row->symbol_order;
                    $temp_input_currency_symbol_space = $row->symbol_space;
                    include("../../_includes/system/convert-and-format-currency.inc.php");
                    $row->transfer_fee = $temp_output_amount;
                    ?>
                    <?php echo $row->transfer_fee; ?>
                </td>
                <td class="main_table_cell_active">
                    <?php
                    $temp_input_amount = $row->privacy_fee;
                    $temp_input_conversion = "";
                    $temp_input_currency_symbol = $row->symbol;
                    $temp_input_currency_symbol_order = $row->symbol_order;
                    $temp_input_currency_symbol_space = $row->symbol_space;
                    include("../../_includes/system/convert-and-format-currency.inc.php");
                    $row->privacy_fee = $temp_output_amount;
                    ?>
                    <?php echo $row->privacy_fee; ?>
                </td>
                <td class="main_table_cell_active">
                    <?php
                    $temp_input_amount = $row->misc_fee;
                    $temp_input_conversion = "";
                    $temp_input_currency_symbol = $row->symbol;
                    $temp_input_currency_symbol_order = $row->symbol_order;
                    $temp_input_currency_symbol_space = $row->symbol_space;
                    include("../../_includes/system/convert-and-format-currency.inc.php");
                    $row->misc_fee = $temp_output_amount;
                    ?>
                    <?php echo $row->misc_fee; ?>
                </td>
                <td class="main_table_cell_active"><?php echo $row->currency; ?></td>
                <td class="main_table_cell_active">
                    <?php
                    $sql_domain_count = "SELECT count(*) AS total_domain_count
                                         FROM domains
                                         WHERE registrar_id = '" . $row->id . "'
                                           AND fee_id = '" . $row->fee_id . "'
                                           AND active NOT IN ('0', '10')";
                    $result_domain_count = mysqli_query($connection, $sql_domain_count);
                    while ($row_domain_count = mysqli_fetch_object($result_domain_count)) {

                        if ($row_domain_count->total_domain_count == 0) {

                            echo "-";

                        } else {

                            echo "<a class=\"invisiblelink\" href=\"../../domains.php?rid=" . $row->id . "&tld=" . $row->tld . "\">" . $row_domain_count->total_domain_count . "</a>";

                        }

                    } ?>
                </td>
                <td class="main_table_cell_active"><?php echo $last_updated; ?></td>
            </tr>

            <?php
            $last_registrar = $row->registrar;
            $last_tld = $row->tld;

        } else { ?>

            <tr class="main_table_row_active">
                <td class="main_table_cell_active">&nbsp;</td>
                <td class="main_table_cell_active"><a class="invisiblelink" href="../../assets/edit/registrar-fees.php?rid=<?php echo $row->id; ?>">.<?php echo $row->tld; ?></a></td>
                <td class="main_table_cell_active">
                    <?php
                    $temp_input_amount = $row->initial_fee;
                    $temp_input_conversion = "";
                    $temp_input_currency_symbol = $row->symbol;
                    $temp_input_currency_symbol_order = $row->symbol_order;
                    $temp_input_currency_symbol_space = $row->symbol_space;
                    include("../../_includes/system/convert-and-format-currency.inc.php");
                    $row->initial_fee = $temp_output_amount;
                    ?>
                    <?php echo $row->initial_fee; ?>
                </td>
                <td class="main_table_cell_active">
                    <?php
                    $temp_input_amount = $row->renewal_fee;
                    $temp_input_conversion = "";
                    $temp_input_currency_symbol = $row->symbol;
                    $temp_input_currency_symbol_order = $row->symbol_order;
                    $temp_input_currency_symbol_space = $row->symbol_space;
                    include("../../_includes/system/convert-and-format-currency.inc.php");
                    $row->renewal_fee = $temp_output_amount;
                    ?>
                    <?php echo $row->renewal_fee; ?>
                </td>
                <td class="main_table_cell_active">
                    <?php
                    $temp_input_amount = $row->transfer_fee;
                    $temp_input_conversion = "";
                    $temp_input_currency_symbol = $row->symbol;
                    $temp_input_currency_symbol_order = $row->symbol_order;
                    $temp_input_currency_symbol_space = $row->symbol_space;
                    include("../../_includes/system/convert-and-format-currency.inc.php");
                    $row->transfer_fee = $temp_output_amount;
                    ?>
                    <?php echo $row->transfer_fee; ?>
                </td>
                <td class="main_table_cell_active">
                    <?php
                    $temp_input_amount = $row->privacy_fee;
                    $temp_input_conversion = "";
                    $temp_input_currency_symbol = $row->symbol;
                    $temp_input_currency_symbol_order = $row->symbol_order;
                    $temp_input_currency_symbol_space = $row->symbol_space;
                    include("../../_includes/system/convert-and-format-currency.inc.php");
                    $row->privacy_fee = $temp_output_amount;
                    ?>
                    <?php echo $row->privacy_fee; ?>
                </td>
                <td class="main_table_cell_active">
                    <?php
                    $temp_input_amount = $row->misc_fee;
                    $temp_input_conversion = "";
                    $temp_input_currency_symbol = $row->symbol;
                    $temp_input_currency_symbol_order = $row->symbol_order;
                    $temp_input_currency_symbol_space = $row->symbol_space;
                    include("../../_includes/system/convert-and-format-currency.inc.php");
                    $row->misc_fee = $temp_output_amount;
                    ?>
                    <?php echo $row->misc_fee; ?>
                </td>
                <td class="main_table_cell_active"><?php echo $row->currency; ?></td>
                <td class="main_table_cell_active">
                    <?php
                    $sql_domain_count = "SELECT count(*) AS total_domain_count
                                         FROM domains
                                         WHERE registrar_id = '" . $row->id . "'
                                           AND fee_id = '" . $row->fee_id . "'
                                           AND active NOT IN ('0', '10')";
                    $result_domain_count = mysqli_query($connection, $sql_domain_count);
                    while ($row_domain_count = mysqli_fetch_object($result_domain_count)) {

                        if ($row_domain_count->total_domain_count == 0) {

                            echo "-";

                        } else {

                            echo "<a class=\"invisiblelink\" href=\"../../domains.php?rid=" . $row->id . "&tld=" . $row->tld . "\">" . $row_domain_count->total_domain_count . "</a>";

                        }

                    } ?>
                </td>
                <td class="main_table_cell_active"><?php echo $last_updated; ?></td>
            </tr>

            <?php
            $last_registrar = $row->registrar;
            $last_tld = $row->tld;

        }

    }
    ?>
    </table>

    <?php
}
?>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
