<?php
/**
 * /assets/edit/registrar-fees-missing.php
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

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "Missing Domain Registrar Fees";
$software_section = "registrar-fees-missing";
?>
<?php echo $system->doctype(); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php
$sql = "SELECT r.id AS registrar_id, r.name AS registrar_name
		FROM registrars r, domains d
		WHERE r.id = d.registrar_id
		  AND d.fee_id = '0'
		GROUP BY r.name
		ORDER BY r.name asc";
$result = mysqli_query($connection, $sql);
?>
The following Registrars/TLDs are missing Domain fees. In order to ensure your domain reporting is accurate please update these fees.<BR>
<table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Registrar</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Missing TLD Fees</font>
        </td>
    </tr>

    <?php
    while ($row = mysqli_fetch_object($result)) { ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <?php echo $row->registrar_name; ?>
            </td>
            <td class="main_table_cell_active">
                <?php
                $sql_missing_tlds = "SELECT tld
									 FROM domains
									 WHERE registrar_id = '" . $row->registrar_id . "'
									   AND fee_id = '0'
									 GROUP BY tld
									 ORDER BY tld asc";
                $result_missing_tlds = mysqli_query($connection, $sql_missing_tlds);
                $full_tld_list = "";

                while ($row_missing_tlds = mysqli_fetch_object($result_missing_tlds)) {

                    $full_tld_list .= $row_missing_tlds->tld . ", ";

                }
                $full_tld_list_formatted = substr($full_tld_list, 0, -2);
                ?>
                <a class="nobold" href="registrar-fees.php?rid=<?php echo $row->registrar_id . "\">" . $full_tld_list_formatted; ?></a>
            </td>
        </tr>

    <?php 
	} ?>

</table>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
