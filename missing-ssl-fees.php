<?php
// /missing-ssl-fees.php
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
include("_includes/start-session.inc.php");
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");

$page_title = "Missing SSL Fees";
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
<?php
$sql = "SELECT sp.id AS ssl_provider_id, sp.name AS ssl_provider_name
		FROM ssl_providers sp, ssl_certs sc
		WHERE sp.id = sc.ssl_provider_id
		  AND sc.fee_id = '0'
		GROUP BY sp.name
		ORDER BY sp.name asc";
$result = mysql_query($sql,$connection);
?>
The following SSL Certificates are missing fees. In order to ensure your SSL reporting is accurate please update these fees.
<BR><BR>
<table class="main_table">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">SSL Provider</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Missing Fees</font>
        </td>
    </tr>

	<?php 
    while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <?=$row->ssl_provider_name;?>
            </td>
            <td class="main_table_cell_active">
                <?php
				$sql_missing_types = "SELECT sslcf.type
									  FROM ssl_certs AS sslc, ssl_cert_types AS sslcf
									  WHERE sslc.type_id = sslcf.id
									    AND sslc.ssl_provider_id = '$row->ssl_provider_id'
										AND sslc.fee_id = '0'
									  GROUP BY sslcf.type
									  ORDER BY sslcf.type asc";
                $result_missing_types = mysql_query($sql_missing_types,$connection);
                $full_type_list = "";

                while ($row_missing_types = mysql_fetch_object($result_missing_types)) {
                    $full_type_list .= $row_missing_types->type . " / ";
                }

                $full_type_list_formatted = substr($full_type_list, 0, -2); 
                ?>
                <a class="nobold" href="edit/ssl-provider-fees.php?sslpid=<?=$row->ssl_provider_id?>"><?=$full_type_list_formatted?></a>
            </td>
        </tr>
    <?php 
	} ?>
</table>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>