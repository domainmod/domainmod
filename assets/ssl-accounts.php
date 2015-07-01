<?php
/**
 * /assets/ssl-accounts.php
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
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "SSL Provider Accounts";
$software_section = "ssl-provider-accounts";

$sslpid = $_GET['sslpid'];
$sslpaid = $_GET['sslpaid'];
$oid = $_GET['oid'];
$export_data = $_GET['export_data'];

if ($sslpid != "") {
    $sslpid_string = " AND sa.ssl_provider_id = '$sslpid' ";
} else {
    $sslpid_string = "";
}
if ($sslpaid != "") {
    $sslpaid_string = " AND sa.id = '$sslpaid' ";
} else {
    $sslpaid_string = "";
}
if ($oid != "") {
    $oid_string = " AND sa.owner_id = '$oid' ";
} else {
    $oid_string = "";
}

$sql = "SELECT sa.id AS sslpaid, sa.username, sa.password, sa.owner_id, sa.ssl_provider_id, sa.reseller, o.id AS oid,
            o.name AS oname, sslp.id AS sslpid, sslp.name AS sslpname, sa.notes, sa.insert_time, sa.update_time
        FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp, ssl_certs as sslc
        WHERE sa.owner_id = o.id
          AND sa.ssl_provider_id = sslp.id
          AND sa.id = sslc.account_id
          AND sslc.active not in ('0')
          $sslpid_string
          $sslpaid_string
          $oid_string
          AND (SELECT count(*)
                 FROM ssl_certs
               WHERE account_id = sa.id
                 AND active NOT IN ('0'))
                 > 0
        GROUP BY sa.username, oname, sslpname
        ORDER BY sslpname, username, oname";

if ($export_data == "1") {

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('ssl_provider_account_list', strtotime($time->time()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Status',
        'SSL Provider',
        'Username',
        'Password',
        'Owner',
        'SSL Certs',
        'Default Account?',
        'Reseller Account?',
        'Notes',
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result) > 0) {

        $has_active = 1;

        while ($row = mysqli_fetch_object($result)) {

            $new_sslpaid = $row->sslpaid;

            if ($current_sslpaid != $new_sslpaid) {
                $exclude_account_string_raw .= "'" . $row->sslpaid . "', ";
            }

            $sql_total_count = "SELECT count(*) AS total_cert_count
                                FROM ssl_certs
                                WHERE account_id = '$row->sslpaid'
                                  AND active NOT IN ('0')";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_cert_count = mysqli_fetch_object($result_total_count)) {
                $total_cert_count = $row_cert_count->total_cert_count;
            }

            if ($row->sslpaid == $_SESSION['default_ssl_provider_account']) {

                $is_default = "1";

            } else {

                $is_default = "";

            }

            if ($row->reseller == "0") {

                $is_reseller = "";

            } else {

                $is_reseller = "1";

            }

            $row_contents = array(
                'Active',
                $row->sslpname,
                $row->username,
                $row->password,
                $row->oname,
                $total_cert_count,
                $is_default,
                $is_reseller,
                $row->notes,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

            $current_sslpaid = $row->sslpaid;

        }

    }

    $exclude_account_string = substr($exclude_account_string_raw, 0, -2);

    if ($exclude_account_string != "") {

        $sslpaid_string = " AND sa.id not in (" . $exclude_account_string . ") ";

    } else {

        $sslpaid_string = "";

    }

    $sql = "SELECT sa.id AS sslpaid, sa.username, sa.password, sa.owner_id, sa.ssl_provider_id, sa.reseller,
                o.id AS oid, o.name AS oname, sslp.id AS sslpid, sslp.name AS sslpname, sa.notes,
                sa.insert_time, sa.update_time
            FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp
            WHERE sa.owner_id = o.id
              AND sa.ssl_provider_id = sslp.id
              " . $sslpid_string . "
              " . $sslpaid_string . "
              " . $oid_string . "
            GROUP BY sa.username, oname, sslpname
            ORDER BY sslpname, username, oname";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) > 0) {

        $has_inactive = "1";

        while ($row = mysqli_fetch_object($result)) {

            if ($row->sslpaid == $_SESSION['default_ssl_provider_account']) {

                $is_default = "1";

            } else {

                $is_default = "";

            }

            if ($row->reseller == "0") {

                $is_reseller = "";

            } else {

                $is_reseller = "1";

            }

            $row_contents = array(
                'Inactive',
                $row->sslpname,
                $row->username,
                $row->password,
                $row->oname,
                '0',
                $is_default,
                $is_reseller,
                $row->notes,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    $export->closeFile($export_file);

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
Below is a list of all the SSL Provider Accounts that are stored in <?php echo $software_title; ?>.<BR><BR>
[<a href="ssl-accounts.php?export_data=1&sslpid=<?php echo $sslpid; ?>&sslpaid=<?php echo $sslpaid; ?>&oid=<?php echo
$oid; ?>">EXPORT</a>]<?php

$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

if (mysqli_num_rows($result) > 0) {

$has_active = 1; ?>
<table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <div class="main_table_heading">SSL Provider</div>
        </td>
        <td class="main_table_cell_heading_active">
            <div class="main_table_heading">Active Accounts (<?php echo mysqli_num_rows($result); ?>)</div>
        </td>
        <td class="main_table_cell_heading_active">
            <div class="main_table_heading">Owner</div>
        </td>
        <td class="main_table_cell_heading_active">
            <div class="main_table_heading">SSL Certs</div>
        </td>
    </tr><?php

    while ($row = mysqli_fetch_object($result)) {

        $new_sslpaid = $row->sslpaid;

        if ($current_sslpaid != $new_sslpaid) {
            $exclude_account_string_raw .= "'" . $row->sslpaid . "', ";
        } ?>

        <tr class="main_table_row_active">
        <td class="main_table_cell_active">
            <a class="invisiblelink" href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid; ?>"><?php
                echo $row->sslpname; ?></a>
        </td>
        <td class="main_table_cell_active">
            <?php //@formatter:off ?>
            <a class="invisiblelink" href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid; ?>"><?php
                echo $row->username; ?></a><?php

            if ($_SESSION['default_ssl_provider_account'] == $row->sslpaid) { ?>

                <a title="Default Account"><div class=\"default_highlight\">*</div></a><?php

            }

            if ($row->reseller == "1") { ?>

                <a title="Reseller Account"><div class="reseller_highlight">*</div></a><?php

            } ?>
            <?php //@formatter:on ?>
        </td>
        <td class="main_table_cell_active">
            <a class="invisiblelink"
               href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid; ?>"><?php echo $row->oname; ?></a>
        </td>
        <td class="main_table_cell_active"><?php
            $sql_total_count = "SELECT count(*) AS total_cert_count
                                    FROM ssl_certs
                                    WHERE account_id = '$row->sslpaid'
                                      AND active NOT IN ('0')";
            $result_total_count = mysqli_query($connection, $sql_total_count);

            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                echo "<a class=\"nobold\" href=\"../ssl-certs
                .php?oid=$row->oid&sslpid=$row->sslpid&sslpaid=$row->sslpaid\">" . number_format
                    ($row_total_count->total_cert_count) . "</a>";
            } ?>
        </td>
        </tr><?php

        $current_sslpaid = $row->sslpaid;

    }

    }

    if ($_SESSION['display_inactive_assets'] == "1") {

        $exclude_account_string = substr($exclude_account_string_raw, 0, -2);

        if ($exclude_account_string != "") {

            $sslpaid_string = " AND sa.id not in ($exclude_account_string) ";

        } else {

            $sslpaid_string = "";

        }

        $sql = "SELECT sa.id AS sslpaid, sa.username, sa.owner_id, sa.ssl_provider_id, sa.reseller, o.id AS oid,
                    o.name AS oname, sslp.id AS sslpid, sslp.name AS sslpname
                FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp
                WHERE sa.owner_id = o.id
                  AND sa.ssl_provider_id = sslp.id
                  " . $sslpid_string . "
                  " . $sslpaid_string . "
                  " . $oid_string . "
                GROUP BY sa.username, oname, sslpname
                ORDER BY sslpname, username, oname";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) > 0) {

            $has_inactive = "1";
            if ($has_active == "1") echo "<BR>";
            if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\"
            cellspacing=\"0\">"; ?>

            <tr class="main_table_row_heading_inactive">
            <td class="main_table_cell_heading_inactive">
                <div class="main_table_heading">SSL Provider</div>
            </td>
            <td class="main_table_cell_heading_inactive">
                <div class="main_table_heading">Inactive Accounts (<?php echo mysqli_num_rows($result); ?>)</div>
            </td>
            <td class="main_table_cell_heading_inactive">
                <div class="main_table_heading">Owner</div>
            </td>
            <td class="main_table_cell_heading_inactive">&nbsp;

            </td>
            </tr><?php

            while ($row = mysqli_fetch_object($result)) { ?>

                <tr class="main_table_row_inactive">
                <td class="main_table_cell_inactive">
                    <a class="invisiblelink" href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid;
                    ?>"><?php echo $row->sslpname; ?></a>
                </td>
                <td class="main_table_cell_inactive">
                    <?php //@formatter:off ?>
                    <a class="invisiblelink" href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid;
                        ?>"><?php echo $row->username; ?></a><?php

                    if ($_SESSION['default_ssl_provider_account'] == $row->sslpaid) { ?>

                        <a title="Default Account"><div class=\"default_highlight\">*</div></a><?php

                    }

                    if ($row->reseller == "1") { ?>

                        <a title="Reseller Account"><div class="reseller_highlight">*</div></a><?php

                    } ?>
                    <?php //@formatter:on ?>
                </td>
                <td class="main_table_cell_inactive">
                    <a class="invisiblelink" href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid;
                    ?>"><?php echo $row->oname; ?></a>
                </td>
                <td class="main_table_cell_inactive">&nbsp;

                </td>
                </tr><?php

            }

        }

    }

    if ($has_active == "1" || $has_inactive == "1") echo "</table>";

    if ($_SESSION['display_inactive_assets'] != "1") { ?>
        <BR><em>Inactive Accounts are currently not displayed. <a class="invisiblelink"
                                                                  href="../settings/display-settings.php">Click here to
                display them</a>.</em><BR><?php
    }

    if ($has_active || $has_inactive) { ?>
        <BR>
        <div class="default_highlight">*</div> = Default Account&nbsp;&nbsp;
        <div class="reseller_highlight">*</div> = Reseller Account<?php
    }

    if (!$has_active && !$has_inactive) {

        $sql = "SELECT id
            FROM ssl_providers
            LIMIT 1";
        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) == 0) { ?>

            <BR>Before adding an SSL Provider Account you must add at least one SSL Provider. <a
                href="add/ssl-provider.php">Click here to add an SSL Provider</a>.<BR><?php

        } else { ?>

            <BR>You don't currently have any SSL Provider Accounts. <a href="add/ssl-provider-account.php">Click here to
                add one</a>.<BR><?php

        }

    } ?>
    <?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
