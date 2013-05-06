<?php
// /updates.php
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

$page_title = $software_title . " Updates";
$software_section = "updates";

// Search Navigation Variables
$numBegin = $_GET['numBegin'];
$begin = $_GET['begin'];
$num = $_GET['num'];

$id = $_GET['id'];

//
// START - Code for pagination
// 
function pageBrowser($totalrows,$numLimit,$amm,$queryStr,$numBegin,$begin,$num) {
		$larrow = "&nbsp;&laquo; Prev &nbsp;";
		$rarrow = "&nbsp;Next &raquo;&nbsp;";
		$wholePiece = "<B>Page:</B> ";
		if ($totalrows > 0) {
			$numSoFar = 1;
			$cycle = ceil($totalrows/$amm);
			
			if (!isset($numBegin) || $numBegin < 1) {
				$numBegin = 1;
				$num = 1;
			}

			$minus = $numBegin-1;
			$start = $minus*$amm;

			if (!isset($begin)) {
				$begin = $start;
			}

			$preBegin = $numBegin-$numLimit;
			$preStart = $amm*$numLimit;
			$preStart = $start-$preStart;
			$preVBegin = $start-$amm;
			$preRedBegin = $numBegin-1;

			if ($start > 0 || $numBegin > 1) {
				$wholePiece .= "<a href='?num=".$preRedBegin
						."&numBegin=".$preBegin
						."&begin=".$preVBegin
						.$queryStr."'>"
						.$larrow."</a>\n";
			}

			for ($i=$numBegin;$i<=$cycle;$i++) {
				if ($numSoFar == $numLimit+1) {
					$piece = "<a href='?numBegin=".$i
						."&num=".$i
						."&begin=".$start
						.$queryStr."'>"
						.$rarrow."</a>\n";
					$wholePiece .= $piece;
					break;
				}

				$piece = "<a href='?begin=".$start
					."&num=".$i
					."&numBegin=".$numBegin
					.$queryStr
					."'>";

				if ($num == $i) {
					$piece .= "</a><b>$i</b><a>";
				} else {
					$piece .= "$i";
				}

				$piece .= "</a>\n";
				$start = $start+$amm;
				$numSoFar++;
				$wholePiece .= $piece;

			}

			$wholePiece .= "\n";
			$wheBeg = $begin+1;
			$wheEnd = $begin+$amm;
			$wheToWhe = "<b>".number_format($wheBeg)."</b>-<b>";

			if ($totalrows <= $wheEnd) {
				$wheToWhe .= $totalrows."</b>";
			} else {
				$wheToWhe .= number_format($wheEnd)."</b>";
			}

			$sqlprod = " LIMIT ".$begin.", ".$amm;

		} else {

			$wholePiece = "";
			$wheToWhe = "<b>0</b> - <b>0</b>";

		}

		return array($sqlprod,$wheToWhe,$wholePiece);
	}

//
// END - Code for pagination
// 

if ($id != "") {
	
	$sql_real_id = "SELECT u.id, ud.id AS update_id
					FROM `updates` AS u, update_data AS ud
					WHERE u.id = ud.update_id
					  AND u.id = '" . mysql_real_escape_string($id) . "'";
	$result_real_id = mysql_query($sql_real_id,$connection) or die(mysql_error());
	
	if (mysql_num_rows($result_real_id) == 0) {
		
		header("Location: updates.php");
		exit;

	} else {

/*

*/
	}

}
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/layout/header.inc.php"); ?>
<?php if ($id == "" && $_SESSION['are_there_updates'] == "1") { ?>
[<a href="_includes/system/mark-updates-read.inc.php?direct=1">mark all updates as read</a>]<BR><BR>
<?php } ?>
<?php
if ($id != "") {

	$sql = "SELECT id, name, `update`, insert_time
			FROM updates
			WHERE id = '" . mysql_real_escape_string($id) . "'
			ORDER BY insert_time desc, id desc";

} else {

	$sql = "SELECT id, name, `update`, insert_time
			FROM updates
			ORDER BY insert_time desc, id desc";

}
$result_limit = 15;
$totalrows = mysql_num_rows(mysql_query($sql));
$navigate = pageBrowser($totalrows,15,$result_limit, "",$_GET[numBegin],$_GET[begin],$_GET[num]);
$sql = $sql.$navigate[0];
$result = mysql_query($sql,$connection); ?>
<?php if ($id == "") { ?>
<?php include("_includes/layout/search-options-block-updates.inc.php"); ?><BR>
<?php } ?>

    <table class="update-block-outer"><?php

		while ($row = mysql_fetch_object($result)) {

			if ($row->update != "") { ?>

                <tr>
                    <td class="update-block-left">
                    	<strong><?=date("Y-m-d", strtotime($row->insert_time))?></strong>
					</td>
                    <td class="update-block-right"><?php 

						if ($id == "") { ?>

	                        <a href="updates.php?id=<?=$row->id?>"><font class="subheadline"><?=$row->name?></font></a>
                            <?php
							$sql_exists = "SELECT *
										   FROM update_data
										   WHERE user_id = '" . $_SESSION['user_id'] . "'
										     AND update_id = '" . $row->id . "'";
							$result_exists = mysql_query($sql_exists,$connection);
							if (mysql_num_rows($result_exists) != 0) { ?>
	                            &nbsp;&nbsp;<font class="default_highlight">*NEW*</font>&nbsp;&nbsp;[<a href="_includes/system/mark-updates-read.inc.php?direct=1&id=<?=$row->id?>">mark read</a>]<?php
							}

						} else { ?>

	                        <font class="subheadline"><?=$row->name?></font>
                            <?php
							$sql_exists = "SELECT *
										   FROM update_data
										   WHERE user_id = '" . $_SESSION['user_id'] . "'
										     AND update_id = '" . $row->id . "'";
							$result_exists = mysql_query($sql_exists,$connection);
							if (mysql_num_rows($result_exists) != 0) { ?>
	                            &nbsp;&nbsp;<font class="default_highlight">*NEW*</font>&nbsp;&nbsp;[<a href="_includes/system/mark-updates-read.inc.php?direct=1&id=<?=$row->id?>">mark read</a>]<?php
							}

						} ?>

                    </td>
                </tr>
	
				<tr>
					<td class="update-block-left">&nbsp;</td>
					<td class="update-block-right"><?=$row->update?><BR><BR></td>
				</tr><?php

			} else { ?>

                <tr>
                    <td class="update-block-left">
                    	<strong><?=date("Y-m-d", strtotime($row->insert_time))?></strong>
					</td>
                    <td class="update-block-right"><?php 

						if ($id == "") { ?>

	                        <a href="updates.php?id=<?=$row->id?>"><font class="subheadline"><?=$row->name?></font></a>
                            <?php
							$sql_exists = "SELECT *
										   FROM update_data
										   WHERE user_id = '" . $_SESSION['user_id'] . "'
										     AND update_id = '" . $row->id . "'";
							$result_exists = mysql_query($sql_exists,$connection);
							if (mysql_num_rows($result_exists) != 0) { ?>
	                            &nbsp;&nbsp;<font class="default_highlight">*NEW*</font>&nbsp;&nbsp;[<a href="_includes/system/mark-updates-read.inc.php?direct=1&id=<?=$row->id?>">mark read</a>]<?php
							} 

						} else { ?>
                        
	                        <font class="subheadline"><?=$row->name?></font>
                            <?php
							$sql_exists = "SELECT *
										   FROM update_data
										   WHERE user_id = '" . $_SESSION['user_id'] . "'
										     AND update_id = '" . $row->id . "'";
							$result_exists = mysql_query($sql_exists,$connection);
							if (mysql_num_rows($result_exists) != 0) { ?>
	                            &nbsp;&nbsp;<font class="default_highlight">*NEW*</font>&nbsp;&nbsp;[<a href="_includes/system/mark-updates-read.inc.php?direct=1&id=<?=$row->id?>">mark read</a>]<?php
							} 

						} ?>
                        <BR><BR>
                    </td>
                </tr><?php

			}

		} ?>

	</table>
    
    <?php if ($id != "") { ?><a href="updates.php">More Updates &raquo;</a><BR><?php } ?>

<?php if ($id == "") { ?>
<?php include("_includes/layout/search-options-block-updates.inc.php"); ?>
<?php } ?>
<?php include("_includes/layout/footer.inc.php"); ?>
</body>
</html>