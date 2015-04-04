<?php
/**
 * /reporting/ssl/provider-fees.php
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

$page_title = $reporting_section_title;
$page_subtitle = "SSL Provider Fee Report";
$software_section = "reporting-ssl-provider-fee-report";
$report_name = "ssl-provider-fee-report";

// Form Variables
$export = $_GET['export'];
$all = $_GET['all'];

if ($all == "1") {

	$sql = "SELECT sslp.id, sslp.name AS ssl_provider, sslt.id AS type_id, sslt.type, f.id AS fee_id, f.initial_fee, f.renewal_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order, c.symbol_space
			FROM ssl_providers AS sslp, ssl_fees AS f, currencies AS c, ssl_cert_types AS sslt
			WHERE sslp.id = f.ssl_provider_id
			  AND f.currency_id = c.id
			  AND f.type_id = sslt.id
			GROUP BY sslp.name, sslt.type
			ORDER BY sslp.name, sslt.type";
	
} else {

	$sql = "SELECT sslp.id, sslp.name AS ssl_provider, sslt.id AS type_id, sslt.type, f.id AS fee_id, f.initial_fee, f.renewal_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order, c.symbol_space
			FROM ssl_providers AS sslp, ssl_certs AS sslc, ssl_fees AS f, currencies AS c, ssl_cert_types AS sslt
			WHERE sslp.id = sslc.ssl_provider_id
			  AND sslc.fee_id = f.id
			  AND f.currency_id = c.id
			  AND sslc.type_id = sslt.id
			  AND sslc.active NOT IN ('0')
			GROUP BY sslp.name, sslt.type
			ORDER BY sslp.name, sslt.type";

}

$result = mysqli_query($connection, $sql) or die(mysqli_error());
$total_rows = mysqli_num_rows($result);

if ($total_rows > 0) {

	if ($export == "1") {

		$result = mysqli_query($connection, $sql) or die(mysqli_error());
	
		$current_timestamp_unix = strtotime($current_timestamp);
		if ($all == "1") {
			$export_filename = "ssl_provider_fee_report_all_" . $current_timestamp_unix . ".csv";
		} else {
			$export_filename = "ssl_provider_fee_report_active_" . $current_timestamp_unix . ".csv";
		}
		include("../../_includes/system/export/header.inc.php");
	
		$row_content[$count++] = $page_subtitle;
		include("../../_includes/system/export/write-row.inc.php");
	
		fputcsv($file_content, $blank_line);
	
		if ($all == "1") {

			$row_content[$count++] = "All SSL Provider Fees";

		} else {

			$row_content[$count++] = "Active SSL Provider Fees";

		}
		include("../../_includes/system/export/write-row.inc.php");

		fputcsv($file_content, $blank_line);

		$row_content[$count++] = "SSL Provider";
		$row_content[$count++] = "Certificate Type";
		$row_content[$count++] = "Initial Fee";
        $row_content[$count++] = "Renewal Fee";
        $row_content[$count++] = "Misc Fee";
		$row_content[$count++] = "Currency";
		$row_content[$count++] = "Certs";
		$row_content[$count++] = "Inserted";
		$row_content[$count++] = "Updated";
		include("../../_includes/system/export/write-row.inc.php");
	
		$new_ssl_provider = "";
		$last_ssl_provider = "";
		$new_type = "";
		$last_type = "";
	
		if (mysqli_num_rows($result) > 0) {
	
			while ($row = mysqli_fetch_object($result)) {
				
				$new_ssl_provider = $row->ssl_provider;
				$new_type = $row->type;

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

                $temp_input_amount = $row->misc_fee;
                $temp_input_conversion = "";
                $temp_input_currency_symbol = $row->symbol;
                $temp_input_currency_symbol_order = $row->symbol_order;
                $temp_input_currency_symbol_space = $row->symbol_space;
                include("../../_includes/system/convert-and-format-currency.inc.php");
                $row->misc_fee = $temp_output_amount;

                $row_content[$count++] = $row->ssl_provider;
				$row_content[$count++] = $row->type;
				$row_content[$count++] = $row->initial_fee;
                $row_content[$count++] = $row->renewal_fee;
                $row_content[$count++] = $row->misc_fee;
				$row_content[$count++] = $row->currency;

				$sql_ssl_count = "SELECT count(*) AS total_ssl_count
								  FROM ssl_certs
								  WHERE ssl_provider_id = '" . $row->id . "'
								    AND fee_id = '" . $row->fee_id . "'
									AND active NOT IN ('0')";
				$result_ssl_count = mysqli_query($connection, $sql_ssl_count);

				while ($row_ssl_count = mysqli_fetch_object($result_ssl_count)) {

					$row_content[$count++] = $row_ssl_count->total_ssl_count;

				}

				$row_content[$count++] = $row->insert_time;
				$row_content[$count++] = $row->update_time;
				include("../../_includes/system/export/write-row.inc.php");

				$last_ssl_provider = $row->ssl_provider;
				$last_type = $row->type;
	
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
    <?php if ($total_rows > 0 && $all != "") { ?>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="<?php echo $PHP_SELF; ?>?export=1&all=<?php echo $all; ?>">EXPORT REPORT</a>]</strong>
    <?php } ?>
<?php include("../../_includes/layout/table-export-bottom.inc.php"); ?>
<?php if ($all != "") { ?>
    <BR><font class="subheadline"><?php echo $page_subtitle; ?></font><BR>
    <BR>
    <?php if ($all == "1") { ?>
        <strong>All SSL Provider Fees</strong><BR>
    <?php } else { ?>
        <strong>Active SSL Provider Fees</strong><BR>
    <?php } ?>
    <?php
    if ($total_rows > 0) { ?>
    
        <table class="main_table" cellpadding="0" cellspacing="0">
        <tr class="main_table_row_heading_active">
            <td class="main_table_cell_heading_active">
            	<font class="main_table_heading">SSL Provider</font>
			</td>
            <td class="main_table_cell_heading_active">
            	<font class="main_table_heading">Certificate Type</font>
			</td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Initial Fee</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Renewal Fee</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Misc Fee</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Currency</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Certs</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Last Updated</font>
            </td>
        </tr>
        <?php
    
        $new_ssl_provider = "";
        $last_ssl_provider = "";
        $new_type = "";
        $last_type = "";
    
        while ($row = mysqli_fetch_object($result)) {
            
            $new_ssl_provider = $row->ssl_provider;
            $new_type = $row->type;
    
            if ($row->update_time == "0000-00-00 00:00:00") {
                $row->update_time = $row->insert_time;	
            }
            $last_updated = date('Y-m-d', strtotime($row->update_time));
    
            if ($new_ssl_provider != $last_ssl_provider || $new_ssl_provider == "") { ?>
    
                <tr class="main_table_row_active">
                    <td class="main_table_cell_active"><a class="invisiblelink" href="../../assets/edit/ssl-provider-fees.php?sslpid=<?php echo $row->id; ?>"><?php echo $row->ssl_provider; ?></a></td>
                    <td class="main_table_cell_active"><a class="invisiblelink" href="../../assets/edit/ssl-provider-fees.php?sslpid=<?php echo $row->id; ?>"><?php echo $row->type; ?></a></td>
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
						$sql_ssl_count = "SELECT count(*) AS total_ssl_count
										  FROM ssl_certs
										  WHERE ssl_provider_id = '" . $row->id . "'
										    AND fee_id = '" . $row->fee_id . "'
											AND active NOT IN ('0')";
						$result_ssl_count = mysqli_query($connection, $sql_ssl_count);
						while ($row_ssl_count = mysqli_fetch_object($result_ssl_count)) {
							
							if ($row_ssl_count->total_ssl_count == 0) {

								echo "-";
								
							} else {

								echo "<a class=\"invisiblelink\" href=\"../../ssl-certs.php?sslpid=" . $row->id . "&ssltid=" . $row->type_id . "\">" . $row_ssl_count->total_ssl_count . "</a>";
								
							}

						} ?>
                    </td>
                    <td class="main_table_cell_active"><?php echo $last_updated; ?></td>
                </tr>

                <?php
                $last_ssl_provider = $row->ssl_provider;
                $last_type = $row->type;
                
            } else { ?>
            
                <tr class="main_table_row_active">
                    <td class="main_table_cell_active">&nbsp;</td>
                    <td class="main_table_cell_active"><a class="invisiblelink" href="../../assets/edit/ssl-provider-fees.php?sslpid=<?php echo $row->id; ?>"><?php echo $row->type; ?></a></td>
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
						$sql_ssl_count = "SELECT count(*) AS total_ssl_count
										  FROM ssl_certs
										  WHERE ssl_provider_id = '" . $row->id . "'
										    AND fee_id = '" . $row->fee_id . "'
											AND active NOT IN ('0')";
						$result_ssl_count = mysqli_query($connection, $sql_ssl_count);
						while ($row_ssl_count = mysqli_fetch_object($result_ssl_count)) {
							
							if ($row_ssl_count->total_ssl_count == 0) {

								echo "-";
								
							} else {

								echo "<a class=\"invisiblelink\" href=\"../../ssl-certs.php?sslpid=" . $row->id . "&ssltid=" . $row->type_id . "\">" . $row_ssl_count->total_ssl_count . "</a>";
								
							}

						} ?>
                    </td>
                    <td class="main_table_cell_active"><?php echo $last_updated; ?></td>
                </tr>
    
                <?php
                $last_ssl_provider = $row->ssl_provider;
                $last_type = $row->type;
    
            }
    
        }
        ?>
        </table>
        
        <?php
    } 
    ?>
<?php } ?>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
