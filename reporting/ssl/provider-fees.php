<?php
// /reporting/ssl/provider-fees.php
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
include("../../_includes/start-session.inc.php");
include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");

$page_title = $reporting_section_title;
$page_subtitle = "SSL Provider Fee Report";
$software_section = "reporting";
$report_name = "ssl-provider-fee-report";

// Form Variables
$export = $_GET['export'];
$all = $_GET['all'];

if ($all == "1") {

	$range_string = "";
	
} else {

	$range_string = " AND sslc.active NOT IN ('0') ";
	
}

$sql = "SELECT sslp.name AS ssl_provider, sslt.type, f.initial_fee, f.renewal_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order, c.symbol_space
		FROM ssl_providers AS sslp, ssl_certs AS sslc, ssl_fees AS f, currencies AS c, ssl_cert_types AS sslt
		WHERE sslp.id = sslc.ssl_provider_id
		  AND sslc.fee_id = f.id
		  AND f.currency_id = c.id
		  AND sslc.type_id = sslt.id
		  " . $range_string . "
		GROUP BY sslp.name, sslt.type
		ORDER BY sslp.name, sslt.type";
$result = mysql_query($sql,$connection) or die(mysql_error());
$total_rows = mysql_num_rows($result);

if ($total_rows > 0) {

	if ($export == "1") {

		$full_export = "";

		$full_export .= "\"" . $page_subtitle . "\"\n\n";

		if ($all == "1") {
			$full_export .= "\"All SSL Provider Fees\"\n\n";
		} else {
			$full_export .= "\"Active SSL Provider Fees\"\n\n";
		}

		$full_export .= "\"SSL Provider\",\"Certificate Type\",\"Initial Fee\",\"Renewal Fee\",\"Currency\",\"Last Updated\"\n";
	
		$new_ssl_provider = "";
		$last_ssl_provider = "";
		$new_type = "";
		$last_type = "";
	
		while ($row = mysql_fetch_object($result)) {
			
			$new_ssl_provider = $row->ssl_provider;
			$new_type = $row->type;

			if ($row->update_time == "0000-00-00 00:00:00") {
				$row->update_time = $row->insert_time;	
			}
			$last_updated = date('Y-m-d', strtotime($row->update_time));

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

			if ($new_ssl_provider != $last_ssl_provider || $new_ssl_provider == "") {
	
				$full_export .= "\"" . $row->ssl_provider . "\",\"" . $row->type . "\",\"" . $row->initial_fee . "\",\"" . $row->renewal_fee . "\",\"" . $row->currency . "\",\"" . $last_updated . "\"\n";
				$last_ssl_provider = $row->ssl_provider;
				$last_type = $row->type;
				
			} else {
	
				$full_export .= "\"\",\"" . $row->type . "\",\"" . $row->initial_fee . "\",\"" . $row->renewal_fee . "\",\"" . $row->currency . "\",\"" . $last_updated . "\"\n";
				$last_ssl_provider = $row->ssl_provider;
				$last_type = $row->type;
	
			}
	
		}
	
		$full_export .= "\n";

		$current_timestamp_unix = strtotime($current_timestamp);
		if ($all == "1") {
			$export_filename = "ssl_provider_fee_report_all_" . $current_timestamp_unix . ".csv";
		} else {
			$export_filename = "ssl_provider_fee_report_active_" . $current_timestamp_unix . ".csv";
		}
		include("../../_includes/system/export-to-csv.inc.php");
		exit;
	}

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?> :: <?=$page_subtitle?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<?php include("../../_includes/layout/reporting-block.inc.php"); ?>
<?php include("../../_includes/layout/table-export-top.inc.php"); ?>
    <a href="<?=$PHP_SELF?>?all=1">View All</a> or <a href="<?=$PHP_SELF?>?all=0">Active Only</a>
    <?php if ($total_rows > 0 && $all != "") { ?>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="<?=$PHP_SELF?>?export=1&all=<?=$all?>">EXPORT REPORT</a>]</strong>
    <?php } ?>
<?php include("../../_includes/layout/table-export-bottom.inc.php"); ?>
<?php if ($all != "") { ?>
    <BR><font class="subheadline"><?=$page_subtitle?></font><BR>
    <BR>
    <?php if ($all == "1") { ?>
        <strong>All SSL Provider Fees</strong><BR><BR>
    <?php } else { ?>
        <strong>Active SSL Provider Fees</strong><BR><BR>
    <?php } ?>
    <?php
    if ($total_rows > 0) { ?>
    
        <table class="main_table">
        <tr class="main_table_row_heading_active">
            <td class="main_table_cell_heading_active">
            <font class="main_table_heading">SSL Provider</font></td>
            <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Certificate Type</font></td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Initial Fee</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Renewal Fee</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Currency</font>
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
    
        while ($row = mysql_fetch_object($result)) {
            
            $new_ssl_provider = $row->ssl_provider;
            $new_type = $row->type;
    
            if ($row->update_time == "0000-00-00 00:00:00") {
                $row->update_time = $row->insert_time;	
            }
            $last_updated = date('Y-m-d', strtotime($row->update_time));
    
            if ($new_ssl_provider != $last_ssl_provider || $new_ssl_provider == "") { ?>
    
                <tr class="main_table_row_active">
                    <td class="main_table_cell_active"><?=$row->ssl_provider?></td>
                    <td class="main_table_cell_active"><?=$row->type?></td>
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
                        <?=$row->initial_fee?>
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
                        <?=$row->renewal_fee?>
                    </td>
                    <td class="main_table_cell_active"><?=$row->currency?></td>
                    <td class="main_table_cell_active"><?=$last_updated?></td>
                </tr>
    
                <?php
                $last_ssl_provider = $row->ssl_provider;
                $last_type = $row->type;
                
            } else { ?>
            
                <tr class="main_table_row_active">
                    <td class="main_table_cell_active">&nbsp;</td>
                    <td class="main_table_cell_active"><?=$row->type?></td>
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
                        <?=$row->initial_fee?>
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
                        <?=$row->renewal_fee?>
                    </td>
                    <td class="main_table_cell_active"><?=$row->currency?></td>
                    <td class="main_table_cell_active"><?=$last_updated?></td>
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