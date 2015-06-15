<?php
/**
 * /reporting/domains/cost-by-registrar.php
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
include("../../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$time = new DomainMOD\Timestamp();

$page_title = $reporting_section_title;
$page_subtitle = "Domain Cost by Registrar Report";
$software_section = "reporting-domain-cost-by-registrar-report";
$report_name = "domain-cost-by-registrar-report";

// Form Variables
$export_data = $_GET['export_data'];
$all = $_GET['all'];
$new_start_date = $_REQUEST['new_start_date'];
$new_end_date = $_REQUEST['new_end_date'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ((!$date->checkDateFormat($new_start_date) || !$date->checkDateFormat($new_end_date)) || $new_start_date > $new_end_date) {

        if (!$date->checkDateFormat($new_start_date)) $_SESSION['result_message'] .= "The start date is invalid<BR>";
        if (!$date->checkDateFormat($new_end_date)) $_SESSION['result_message'] .= "The end date is invalid<BR>";
        if ($new_start_date > $new_end_date) $_SESSION['result_message'] .= "The end date proceeds the start date<BR>";

        $submission_failed = "1";

    }

    $all = "0";

}

if ($all == "1") {

	$range_string = "";
	
} else {

	$range_string = " AND d.expiry_date between '" . $new_start_date . "' AND '" . $new_end_date . "' ";
	
}

$sql = "SELECT r.id, r.name AS registrar_name, o.name AS owner_name, ra.id AS registrar_account_id, ra.username, SUM(d.total_cost * cc.conversion) as total_cost, count(*) AS number_of_domains
		FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
		WHERE d.fee_id = f.id
		  AND f.currency_id = c.id
		  AND c.id = cc.currency_id
		  AND d.registrar_id = r.id
		  AND d.account_id = ra.id
		  AND d.owner_id = o.id
		  AND d.active NOT IN ('0', '10')
		  AND cc.user_id = '" . $_SESSION['user_id'] . "'
		  " . $range_string . "
		GROUP BY r.name, o.name, ra.username
		ORDER BY r.name, o.name, ra.username";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
$total_rows = mysqli_num_rows($result);

$sql_grand_total = "SELECT SUM(d.total_cost * cc.conversion) AS grand_total, count(*) AS number_of_domains_total
					FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
					WHERE d.fee_id = f.id
					  AND f.currency_id = c.id
					  AND c.id = cc.currency_id
					  AND d.registrar_id = r.id
					  AND d.account_id = ra.id
					  AND d.owner_id = o.id
					  AND d.active NOT IN ('0', '10')
					  AND cc.user_id = '" . $_SESSION['user_id'] . "'
					  " . $range_string . "";
$result_grand_total = mysqli_query($connection, $sql_grand_total) or $error->outputOldSqlError($connection);
while ($row_grand_total = mysqli_fetch_object($result_grand_total)) {
	$grand_total = $row_grand_total->grand_total;
	$number_of_domains_total = $row_grand_total->number_of_domains_total;
}

$grand_total = $currency->convertAndFormat($grand_total, '', $_SESSION['default_currency_symbol'],
    $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']);

if ($submission_failed != "1" && $total_rows > 0) {

	if ($export_data == "1") {

		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $export = new DomainMOD\Export();

        if ($all == "1") {

            $export_file = $export->openFile('domain_cost_by_registrar_report_all', strtotime($time->time()));

        } else {

            $export_file = $export->openFile(
                'domain_cost_by_registrar_report',
                $new_start_date . '--' . $new_end_date
            );

        }

        $row_contents = array($page_subtitle);
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        if ($all != "1") {

            $row_contents = array('Date Range:', $new_start_date, $new_end_date);

        } else {

            $row_contents = array('Date Range:', 'ALL');

        }
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            'Total Cost:',
            $grand_total,
            $_SESSION['default_currency']
        );
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            'Number of Domains:',
            $number_of_domains_total
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            'Registrar',
            'Domains',
            'Cost',
            'Per Domain',
            'Registrar Account',
            'Domains',
            'Cost',
            'Per Domain'
        );
        $export->writeRow($export_file, $row_contents);

        $new_registrar = "";
		$last_registrar = "";

		if (mysqli_num_rows($result) > 0) {

			while ($row = mysqli_fetch_object($result)) {
	
				$new_registrar = $row->registrar_name;
	
				$sql_registrar_total = "SELECT SUM(d.total_cost * cc.conversion) as registrar_total, count(*) AS number_of_domains_registrar
										FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
										WHERE d.fee_id = f.id
										  AND f.currency_id = c.id
										  AND c.id = cc.currency_id
										  AND d.registrar_id = r.id
										  AND d.account_id = ra.id
										  AND d.owner_id = o.id
										  AND d.active NOT IN ('0', '10')
										  AND cc.user_id = '" . $_SESSION['user_id'] . "'
										  AND r.id = '" . $row->id . "'
										  " . $range_string . "";
				$result_registrar_total = mysqli_query($connection, $sql_registrar_total) or $error->outputOldSqlError($connection);
				while ($row_registrar_total = mysqli_fetch_object($result_registrar_total)) { 
					$temp_registrar_total = $row_registrar_total->registrar_total; 
					$number_of_domains_registrar = $row_registrar_total->number_of_domains_registrar; 
				}
	
				$per_domain_account = $row->total_cost / $row->number_of_domains;

                $row->total_cost = $currency->convertAndFormat($row->total_cost, '',
                    $_SESSION['default_currency_symbol'], $_SESSION['default_currency_symbol_order'],
                    $_SESSION['default_currency_symbol_space']);

                $per_domain_account = $currency->convertAndFormat($per_domain_account, '',
                    $_SESSION['default_currency_symbol'], $_SESSION['default_currency_symbol_order'],
                    $_SESSION['default_currency_symbol_space']);

                $per_domain_registrar = $temp_registrar_total / $number_of_domains_registrar;

                $temp_registrar_total = $currency->convertAndFormat($temp_registrar_total, '',
                    $_SESSION['default_currency_symbol'], $_SESSION['default_currency_symbol_order'],
                    $_SESSION['default_currency_symbol_space']);

                $per_domain_registrar = $currency->convertAndFormat($per_domain_registrar, '',
                    $_SESSION['default_currency_symbol'], $_SESSION['default_currency_symbol_order'],
                    $_SESSION['default_currency_symbol_space']);

                $row_contents = array(
                    $row->registrar_name,
                    $number_of_domains_registrar,
                    $temp_registrar_total,
                    $per_domain_registrar,
                    $row->owner_name . ' (' . $row->username . ')',
                    $row->number_of_domains,
                    $row->total_cost,
                    $per_domain_account
                );
                $export->writeRow($export_file, $row_contents);

                $last_registrar = $row->registrar_name;

			}

		}

        $export->closeFile($export_file);

    }

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?> :: <?php echo $page_subtitle; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php include(DIR_INC . "layout/reporting-block.inc.php"); ?>
<?php include(DIR_INC . "layout/table-export-top.inc.php"); ?>
    <form name="export_domains_form" method="post">
        <a href="cost-by-registrar.php?all=1">View All</a> or Expiring Between
        <input name="new_start_date" type="text" size="10" maxlength="10" <?php if ($new_start_date == "") { echo "value=\"" . $time->timeBasic() . "\""; } else { echo "value=\"$new_start_date\""; } ?>>
        and 
        <input name="new_end_date" type="text" size="10" maxlength="10" <?php if ($new_end_date == "") { echo "value=\"" . $time->timeBasic() . "\""; } else { echo "value=\"$new_end_date\""; } ?>>
        &nbsp;&nbsp;<input type="submit" name="button" value="Generate Report &raquo;"> 
        <?php if ($total_rows > 0) { ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="cost-by-registrar.php?export_data=1&new_start_date=<?php echo $new_start_date; ?>&new_end_date=<?php echo $new_end_date; ?>&all=<?php echo $all; ?>">EXPORT REPORT</a>]</strong>
        <?php } ?>
    </form>
<?php include(DIR_INC . "layout/table-export-bottom.inc.php"); ?>
<?php
if ($submission_failed != "1" && $total_rows > 0) { ?>

	<BR><font class="subheadline"><?php echo $page_subtitle; ?></font><BR>
	<BR>
    <?php if ($all != "1") { ?>
	    <strong>Date Range:</strong> <?php echo $new_start_date; ?> - <?php echo $new_end_date; ?><BR><BR>
    <?php } else { ?>
	    <strong>Date Range:</strong> ALL<BR><BR>
    <?php } ?>

    <strong>Total Cost:</strong> <?php echo $grand_total; ?> <?php echo $_SESSION['default_currency']; ?><BR><BR>
    <strong>Number of Domains:</strong> <?php echo $number_of_domains_total; ?><BR>
    <table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Registrar</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Domains</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Cost</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Per Domain</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Registrar Account</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Domains</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Cost</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Per Domain</font></td>
    </tr>

	<?php
	$new_registrar = "";
	$last_registrar = "";

	while ($row = mysqli_fetch_object($result)) {

		$new_registrar = $row->registrar_name;

		$sql_registrar_total = "SELECT SUM(d.total_cost * cc.conversion) as registrar_total, count(*) AS number_of_domains_registrar
								FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
								WHERE d.fee_id = f.id
								  AND f.currency_id = c.id
								  AND c.id = cc.currency_id
								  AND d.registrar_id = r.id
								  AND d.account_id = ra.id
								  AND d.owner_id = o.id
								  AND d.active NOT IN ('0', '10')
								  AND cc.user_id = '" . $_SESSION['user_id'] . "'
								  AND r.id = '" . $row->id . "'
								  " . $range_string . "";
		$result_registrar_total = mysqli_query($connection, $sql_registrar_total) or $error->outputOldSqlError($connection);
		while ($row_registrar_total = mysqli_fetch_object($result_registrar_total)) { 
			$temp_registrar_total = $row_registrar_total->registrar_total; 
			$number_of_domains_registrar = $row_registrar_total->number_of_domains_registrar; 
		}

		$per_domain_account = $row->total_cost / $row->number_of_domains;

        $row->total_cost = $currency->convertAndFormat($row->total_cost, '', $_SESSION['default_currency_symbol'],
            $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']);

        $per_domain_account = $currency->convertAndFormat($per_domain_account, '', $_SESSION['default_currency_symbol'],
            $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']);

        $per_domain_registrar = $temp_registrar_total / $number_of_domains_registrar;

        $temp_registrar_total = $currency->convertAndFormat($temp_registrar_total, '',
            $_SESSION['default_currency_symbol'], $_SESSION['default_currency_symbol_order'],
            $_SESSION['default_currency_symbol_space']);

        $per_domain_registrar = $currency->convertAndFormat($per_domain_registrar, '',
            $_SESSION['default_currency_symbol'], $_SESSION['default_currency_symbol_order'],
            $_SESSION['default_currency_symbol_space']);

        if ($new_registrar != $last_registrar || $new_registrar == "") { ?>
	
            <tr class="main_table_row_active">
                <td class="main_table_cell_active"><a class="invisiblelink" href="../../domains.php?rid=<?php echo $row->id; ?>"><?php echo $row->registrar_name; ?></a></td>
                <td class="main_table_cell_active"><a class="invisiblelink" href="../../domains.php?rid=<?php echo $row->id; ?>"><?php echo $number_of_domains_registrar; ?></a></td>
                <td class="main_table_cell_active"><?php echo $temp_registrar_total; ?></td>
                <td class="main_table_cell_active"><?php echo $per_domain_registrar; ?></td>
                <td class="main_table_cell_active"><a class="invisiblelink" href="../../domains.php?raid=<?php echo $row->registrar_account_id; ?>"><?php echo $row->owner_name; ?> (<?php echo $row->username; ?>)</a></td>
                <td class="main_table_cell_active"><a class="invisiblelink" href="../../domains.php?raid=<?php echo $row->registrar_account_id; ?>"><?php echo $row->number_of_domains; ?></a></td>
                <td class="main_table_cell_active"><?php echo $row->total_cost; ?></td>
                <td class="main_table_cell_active"><?php echo $per_domain_account; ?></td>
            </tr><?php

			$last_registrar = $row->registrar_name;

		} else { ?>

            <tr class="main_table_row_active">
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"><a class="invisiblelink" href="../../domains.php?raid=<?php echo $row->registrar_account_id; ?>"><?php echo $row->owner_name; ?> (<?php echo $row->username; ?>)</a></td>
                <td class="main_table_cell_active"><a class="invisiblelink" href="../../domains.php?raid=<?php echo $row->registrar_account_id; ?>"><?php echo $row->number_of_domains; ?></a></td>
                <td class="main_table_cell_active"><?php echo $row->total_cost; ?></td>
                <td class="main_table_cell_active"><?php echo $per_domain_account; ?></td>
            </tr><?php

			$last_registrar = $row->registrar_name;

		}

	}
		?>
    </table><?php

} 
?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
