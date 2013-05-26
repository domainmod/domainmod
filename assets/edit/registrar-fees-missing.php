<?php
// /assets/edit/registrar-fees-missing.php
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

$page_title = "Missing Domain Registrar Fees";
$software_section = "registrar-fees-missing";
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<?php
$sql = "SELECT r.id AS registrar_id, r.name AS registrar_name
		FROM registrars r, domains d
		WHERE r.id = d.registrar_id
		  AND d.fee_id = '0'
		GROUP BY r.name
		ORDER BY r.name asc";
$result = mysql_query($sql,$connection);
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
    while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <?=$row->registrar_name;?>
            </td>
            <td class="main_table_cell_active">
                <?php
                $sql_missing_tlds = "SELECT tld
									 FROM domains
									 WHERE registrar_id = '" . $row->registrar_id . "'
									   AND fee_id = '0'
									 GROUP BY tld
									 ORDER BY tld asc";
                $result_missing_tlds = mysql_query($sql_missing_tlds,$connection);
                $full_tld_list = "";
                while ($row_missing_tlds = mysql_fetch_object($result_missing_tlds)) {
                    $full_tld_list .= $row_missing_tlds->tld . ", ";
                }
                $full_tld_list_formatted = substr($full_tld_list, 0, -2); 
                ?>
                <a class="nobold" href="registrar-fees.php?rid=<?=$row->registrar_id?>"><?=$full_tld_list_formatted?></a>
            </td>
        </tr>

    <?php 
	} ?>

</table>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>