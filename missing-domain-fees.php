<?php
// missing-domain-fees.php
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

$page_title = "Missing Domain Fees";
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
$sql = "SELECT r.id AS registrar_id, r.name AS registrar_name
		FROM registrars r, domains d
		WHERE r.id = d.registrar_id
		  AND d.fee_id = '0'
		GROUP BY r.name
		ORDER BY r.name asc";
$result = mysql_query($sql,$connection);
?>
The following Registrars/TLDs are missing Domain fees. In order to ensure your Domain reporting is accurate please update these fees.
<BR><BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="20">
        <td width="250">
            <font class="subheadline">Registrar</font>
        </td>
        <td>
            <font class="subheadline">Missing TLD Fees</font>
        </td>
    </tr>

	<?php 
    while ($row = mysql_fetch_object($result)) { ?>

        <tr height="20">
            <td>
                <?=$row->registrar_name;?>
            </td>
            <td>
                <?php
                $sql2 = "SELECT tld
                         FROM domains
                         WHERE registrar_id = '$row->registrar_id'
                           AND fee_id = '0'
                         GROUP BY tld
                         ORDER BY tld asc";
                $result2 = mysql_query($sql2,$connection);
                $full_tld_list = "";
                while ($row2 = mysql_fetch_object($result2)) {
                    $full_tld_list .= $row2->tld . ", ";
                }
                $full_tld_list_formatted = substr($full_tld_list, 0, -2); 
                ?>
                <a class="nobold" href="edit/registrar.php?rid=<?=$row->registrar_id?>#missingfees"><?=$full_tld_list_formatted?></a>
            </td>
        </tr>

    <?php 
	} ?>

</table>
<BR><BR>
<a href="_includes/system/fix-domain-fees.php">Fix All Domain Fees (this may take a while, depending on how many domains you have)</a>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>