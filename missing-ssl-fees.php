<?php
// missing-ssl-fees.php
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
session_start();

include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");

$page_title = "Missing SSL Fees";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
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
<table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="20">
        <td width="250">
            <font class="subheadline">SSL Provider</font>
        </td>
        <td>
            <font class="subheadline">Missing Fees</font>
        </td>
    </tr>

	<?php 
    while ($row = mysql_fetch_object($result)) { ?>

        <tr height="20">
            <td>
                <?=$row->ssl_provider_name;?>
            </td>
            <td>
                <?php
				$sql2 = "SELECT sslcf.function AS full_tf_string
						FROM ssl_certs AS sslc, ssl_cert_functions AS sslcf
						WHERE sslc.function_id = sslcf.id
						  AND sslc.ssl_provider_id = '$row->ssl_provider_id'
						  AND sslc.fee_id = '0'
						GROUP BY full_tf_string
						ORDER BY full_tf_string asc";
                $result2 = mysql_query($sql2,$connection);
                $full_type_list = "";

                while ($row2 = mysql_fetch_object($result2)) {
                    $full_type_list .= $row2->full_tf_string . " / ";
                }

                $full_type_list_formatted = substr($full_type_list, 0, -2); 
                ?>
                <a class="nobold" href="edit/ssl-provider.php?sslpid=<?=$row->ssl_provider_id?>#missingfees"><?=$full_type_list_formatted?></a>
            </td>
        </tr>
    <?php 
	} ?>
</table>
<BR><BR>
<a href="_includes/system/fix-ssl-fees.php">Fix All SSL Fees (this may take a while, depending on how many SSL Certificates you have)</a>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>