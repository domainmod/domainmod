<?php
/**
 * /assets/edit/ssl-provider-fees.php
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

$conversion = new DomainMOD\Conversion();
$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();
$timestamp = $time->time();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "SSL Provider Fees";
$software_section = "ssl-provider-fees";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslpid = $_GET['sslpid'];
$ssltid = $_GET['ssltid'];
$sslfeeid = $_GET['sslfeeid'];

$new_type_id = $_POST['new_type_id'];
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_misc_fee = $_POST['new_misc_fee'];
$new_currency_id = $_POST['new_currency_id'];
$new_sslpid = $_POST['new_sslpid'];

$fee_id = $_POST['fee_id'];
$initial_fee = $_POST['initial_fee'];
$renewal_fee = $_POST['renewal_fee'];
$misc_fee = $_POST['misc_fee'];
$currency = $_POST['currency'];

$which_form = $_POST['which_form'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($which_form == "edit") {

        $count = 0;

        foreach ($fee_id as $value) {

            $sql = "UPDATE ssl_fees
                        SET initial_fee = '" . $initial_fee[$count] . "',
                            renewal_fee = '" . $renewal_fee[$count] . "',
                            misc_fee = '" . $misc_fee[$count] . "',
                            currency_id = '" . $currency[$count] . "',
                            update_time = '" . $timestamp . "'
                        WHERE id = '" . $fee_id[$count] . "'";
            $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

            $sql = "UPDATE ssl_certs sslc
                    JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                    SET sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                    WHERE sslc.fee_id = '" . $fee_id[$count] . "'";
            $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

            $count++;

        }

        $_SESSION['s_result_message']
            .= "The SSL Provider Fees have been updated<BR>";

        $_SESSION['s_result_message']
            .= $conversion->updateRates($connection, $_SESSION['s_default_currency'], $_SESSION['s_user_id']);

    } elseif ($which_form == "add") {

        if ($new_sslpid == "" || $new_type_id == "" || $new_type_id == "0" || $new_initial_fee == "" ||
            $new_renewal_fee == "" || $new_currency_id == "" || $new_currency_id == "0"
        ) {

            if ($new_initial_fee == "") $_SESSION['s_result_message'] .= "Please enter the initial fee<BR>";
            if ($new_renewal_fee == "") $_SESSION['s_result_message'] .= "Please enter the renewal fee<BR>";
            if ($new_type_id == "" || $new_type_id == "0")
                $_SESSION['s_result_message'] .= "There was a problem with the SSL Type you chose<BR>";
            if ($new_currency_id == "" || $new_currency_id == "0")
                $_SESSION['s_result_message'] .= "There was a problem with the currency you chose<BR>";

        } else {

            $sql = "SELECT *
                    FROM ssl_fees
                    WHERE ssl_provider_id = '" . $new_sslpid . "'
                      AND type_id = '" . $new_type_id . "'";
            $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

            if (mysqli_num_rows($result) > 0) {

                $sql = "UPDATE ssl_fees
                        SET initial_fee = '" . $new_initial_fee . "',
                            renewal_fee = '" . $new_renewal_fee . "',
                            misc_fee = '" . $new_misc_fee . "',
                            currency_id = '" . $new_currency_id . "',
                            update_time = '" . $timestamp . "'
                        WHERE ssl_provider_id = '" . $new_sslpid . "'
                          AND type_id = '" . $new_type_id . "'";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                $sql = "SELECT id
                        FROM ssl_fees
                        WHERE ssl_provider_id = '" . $new_sslpid . "'
                          AND type_id = '" . $new_type_id . "'
                          AND currency_id = '" . $new_currency_id . "'
                        LIMIT 1";

                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                while ($row = mysqli_fetch_object($result)) {
                    $new_fee_id = $row->id;
                }

                $sql = "UPDATE ssl_certs
                        SET fee_id = '" . $new_fee_id . "',
                            update_time = '" . $timestamp . "'
                        WHERE ssl_provider_id = '" . $new_sslpid . "'
                          AND type_id = '" . $new_type_id . "'";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                $sql = "SELECT type
                        FROM ssl_cert_types
                        WHERE id = '" . $new_type_id . "'";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
                while ($row = mysqli_fetch_object($result)) {
                    $temp_type = $row->type;
                }

                $sql = "UPDATE ssl_certs sslc
                    JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                    SET sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                    WHERE sslc.fee_id = '" . $new_fee_id . "'";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                $sslpid = $new_sslpid;

                $_SESSION['s_result_message']
                    .= "The fee for <div class=\"highlight\">$temp_type</div> has been updated<BR>";

                $_SESSION['s_result_message']
                    .= $conversion->updateRates($connection, $_SESSION['s_default_currency'], $_SESSION['s_user_id']);

            } else {

                $sql = "INSERT INTO ssl_fees
                        (ssl_provider_id, type_id, initial_fee, renewal_fee, misc_fee, currency_id, insert_time)
                        VALUES
                        ('" . $new_sslpid . "', '" . $new_type_id . "', '" . $new_initial_fee . "',
                         '" . $new_renewal_fee . "', '" . $new_misc_fee . "', '" . $new_currency_id . "',
                         '" . $timestamp . "')";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                $sql = "SELECT id
                        FROM ssl_fees
                        WHERE ssl_provider_id = '" . $new_sslpid . "'
                          AND type_id = '" . $new_type_id . "'
                          AND currency_id = '" . $new_currency_id . "'
                        ORDER BY id DESC
                        LIMIT 1";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                while ($row = mysqli_fetch_object($result)) {
                    $new_fee_id = $row->id;
                }

                $sql = "UPDATE ssl_certs
                        SET fee_id = '" . $new_fee_id . "',
                            update_time = '" . $timestamp . "'
                        WHERE ssl_provider_id = '" . $new_sslpid . "'
                          AND type_id = '" . $new_type_id . "'";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                $sql = "SELECT type
                        FROM ssl_cert_types
                        WHERE id = '" . $new_type_id . "'";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                while ($row = mysqli_fetch_object($result)) {
                    $temp_type = $row->type;
                }

                $sql = "UPDATE ssl_certs sslc
                    JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                    SET sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                    WHERE sslc.fee_id = '" . $new_fee_id . "'";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                $_SESSION['s_result_message'] .= "The fee for <div class=\"highlight\">$temp_type</div> has been
                added<BR>";

                $queryB = new DomainMOD\QueryBuild();

                $sql = $queryB->missingFees('ssl_certs');
                $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($connection, $sql);

                $_SESSION['s_result_message']
                    .= $conversion->updateRates($connection, $_SESSION['s_default_currency'], $_SESSION['s_user_id']);

            }

        }

    }

}

if ($del == "1") {
    $_SESSION['s_result_message'] .= "Are you sure you want to delete this SSL Provider Fee?<BR><BR><a
        href=\"ssl-provider-fees.php?sslpid=$sslpid&ssltid=$ssltid&sslfeeid=$sslfeeid&really_del=1\">YES, REALLY DELETE
        THIS SSL PROVIDER FEE</a><BR>";
}
if ($really_del == "1") {

    $sql = "SELECT *
            FROM ssl_fees
            WHERE id = '" . $sslfeeid . "'
              AND ssl_provider_id = '" . $sslpid . "'
              AND type_id = '" . $ssltid . "'";
    $result = mysqli_query($connection, $sql);

    if (mysqli_num_rows($result) == 0) {

        $_SESSION['s_result_message'] .= "The fee you're trying to delete doesn't exist<BR>";

        header("Location: ssl-provider-fees.php?sslpid=$new_sslpid");
        exit;

    } else {

        $sql = "DELETE FROM ssl_fees
                WHERE id = '" . $sslfeeid . "'
                  AND ssl_provider_id = '" . $sslpid . "'
                  AND type_id = '" . $ssltid . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE ssl_certs
                SET fee_id = '0',
                    update_time = '" . $timestamp . "'
                WHERE fee_id = '" . $sslfeeid . "'
                  AND ssl_provider_id = '" . $sslpid . "'
                  AND type_id = '" . $ssltid . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "SELECT type
                FROM ssl_cert_types
                WHERE id = '" . $ssltid . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
        while ($row = mysqli_fetch_object($result)) {
            $temp_type = $row->type;
        }

        $_SESSION['s_result_message'] .= "The fee for <div class=\"highlight\">$temp_type</div> has been deleted<BR>";

        $queryB = new DomainMOD\QueryBuild();

        $sql = $queryB->missingFees('ssl_certs');
        $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($connection, $sql);

        $_SESSION['s_result_message']
            .= $conversion->updateRates($connection, $_SESSION['s_default_currency'], $_SESSION['s_user_id']);

        header("Location: ssl-provider-fees.php?sslpid=$sslpid");
        exit;

    }

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
<?php
$sql = "SELECT `name`
        FROM ssl_providers
        WHERE id = '" . $sslpid . "'";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
while ($row = mysqli_fetch_object($result)) {
    $temp_ssl_provider_name = $row->name;
} ?>
The below fees are for the SSL provider <a
    href="ssl-provider.php?sslpid=<?php echo $sslpid; ?>"><?php echo $temp_ssl_provider_name; ?></a>.<BR><BR>
<?php
$sql = "SELECT t.type
        FROM ssl_certs AS c, ssl_cert_types AS t
        WHERE c.type_id = t.id
          AND c.ssl_provider_id = '" . $sslpid . "'
          AND c.fee_id = '0'
        GROUP BY t.type
        ORDER BY t.type ASC";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
if (mysqli_num_rows($result) > 0) {
    ?>
    <BR><a name="missingfees"></a>
    <div class="subheadline">Missing SSL Type Fees</div><BR>
    <?php
    $count = 0;
    while ($row = mysqli_fetch_object($result)) {
        $temp_all_missing_fees = $temp_all_missing_fees .= "$row->type, ";
        $count++;
    }
    $all_missing_fees = substr($temp_all_missing_fees, 0, -2);
    ?>
    <?php echo $all_missing_fees; ?><BR><BR>
    <?php if ($count > 1) { ?>
        <strong>Please update the fees for these SSL Types below in order to ensure proper SSL accounting.</strong>
    <?php } else { ?>
        <strong>Please update the fees for this SSL Type below in order to ensure proper SSL accounting.</strong>
    <?php } ?>
    <BR><BR>
<?php
}
?>
<?php
$sql = "SELECT t.id, t.type
        FROM ssl_certs AS c, ssl_cert_types AS t
        WHERE c.type_id = t.id
          AND c.ssl_provider_id = '" . $sslpid . "'
          AND c.active NOT IN ('0')
        GROUP BY t.type
        ORDER BY t.type";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

if (mysqli_num_rows($result) != 0) {
    ?>

    <BR>
    <div class="subheadline">SSL Types Linked to Active SSL Certificates</div><BR>

    <?php
    while ($row = mysqli_fetch_object($result)) {

        $sql_temp = "SELECT fee_id
                     FROM ssl_certs
                     WHERE ssl_provider_id = '" . $sslpid . "'
                       AND type_id = '" . $row->id . "'";
        $result_temp = mysqli_query($connection, $sql_temp) or $error->outputOldSqlError($connection);
        while ($row_temp = mysqli_fetch_object($result_temp)) {
            $temp_fee_id = $row_temp->fee_id;
        }

        if ($temp_fee_id == "0") {
            $temp_all_types = $temp_all_types .= "<div class=\"highlight\">$row->type</div>, ";
        } else {
            $temp_all_types = $temp_all_types .= "$row->type, ";
        }

    }

    $all_types = substr($temp_all_types, 0, -2);
    echo $all_types;
    echo "<BR><BR><BR>";

}
?>
<div class="subheadline">Add SSL Type Fee</div>

<form name="add_ssl_provider_fee_form" method="post">
    <table class="main_table" cellpadding="0" cellspacing="0">
        <tr class="main_table_row_heading_active">
            <td class="main_table_cell_heading_active">
                <strong>SSL Type</strong><BR>
                <select name="new_type_id">
                    <?php
                    $sql = "SELECT id, type
                            FROM ssl_cert_types
                            ORDER BY type";
                    $result = mysqli_query($connection, $sql);
                    while ($row = mysqli_fetch_object($result)) {

                        if ($row->id == $new_type_id) { ?>
                            <option value="<?php echo $row->id; ?>" selected><?php echo "$row->type"; ?></option><?php
                        } else { ?>
                            <option value="<?php echo $row->id; ?>"><?php echo "$row->type"; ?></option><?php
                        }

                    }
                    ?>
                </select>
            </td>
            <td class="main_table_cell_heading_active">
                <strong>Initial Fee</strong><BR>
                <input name="new_initial_fee" type="text" value="<?php echo $new_initial_fee; ?>" size="4">
            </td>
            <td class="main_table_cell_heading_active">
                <strong>Renewal Fee</strong><BR>
                <input name="new_renewal_fee" type="text" value="<?php echo $new_renewal_fee; ?>" size="4">
            </td>
            <td class="main_table_cell_heading_active">
                <strong>Misc Fee</strong><BR>
                <input name="new_misc_fee" type="text" value="<?php echo $new_misc_fee; ?>" size="4">
            </td>
            <td class="main_table_cell_heading_active"><strong>Currency</strong><BR>
                <select name="new_currency_id" id="new_currency">
                    <?php
                    $sql = "SELECT id, currency, `name`, symbol
                            FROM currencies
                            ORDER BY currency";
                    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
                    while ($row = mysqli_fetch_object($result)) {

                        if ($row->currency == $_SESSION['s_default_currency']) {
                            ?>
                            <option value="<?php echo $row->id; ?>" selected>
                                <?php echo "$row->name ($row->currency $row->symbol)"; ?>
                            </option>
                        <?php
                        } else {
                            ?>
                            <option value="<?php echo $row->id; ?>">
                                <?php echo "$row->name ($row->currency $row->symbol)"; ?>
                            </option>
                        <?php
                        }
                    }
                    ?>
                </select>
            </td>
        </tr>
    </table>
    <input type="hidden" name="new_sslpid" value="<?php echo $sslpid; ?>"><BR>
    <input type="hidden" name="which_form" value="add"><BR>
    <input type="submit" name="button" value="Add This SSL Fee &raquo;">
</form>
<BR><BR>

<div class="subheadline">SSL Type Fees</div>

<form name="edit_ssl_provider_fee_form" method="post">
    <table class="main_table" cellpadding="0" cellspacing="0">
        <tr class="main_table_row_heading_active">
            <td class="main_table_cell_heading_active"><strong>SSL Type</strong></td>
            <td class="main_table_cell_heading_active"><strong>Initial Fee</strong></td>
            <td class="main_table_cell_heading_active"><strong>Renewal Fee</strong></td>
            <td class="main_table_cell_heading_active"><strong>Misc Fee</strong></td>
            <td class="main_table_cell_heading_active"><strong>Currency</strong></td>
        </tr>
        <?php
        $sql = "SELECT f.id AS sslfeeid, f.initial_fee, f.renewal_fee, f.misc_fee, c.currency, c.symbol, c.symbol_order,
                    c.symbol_space, t.id AS ssltid, t.type
                FROM ssl_fees AS f, currencies AS c, ssl_cert_types AS t
                WHERE f.currency_id = c.id
                  AND f.type_id = t.id
                  AND f.ssl_provider_id = '" . $sslpid . "'
                ORDER BY t.type ASC";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
        $count = 0;
        while ($row = mysqli_fetch_object($result)) {
            ?>
            <tr class="main_table_row_active">
                <td class="main_table_cell_active"><?php echo $row->type; ?></td>
                <td class="main_table_cell_active">
                    <input type="hidden" name="fee_id[<?php echo $count; ?>]" value="<?php echo $row->sslfeeid; ?>">
                    <input name="initial_fee[<?php echo $count; ?>]" type="text"
                           value="<?php echo $row->initial_fee; ?>" size="4">
                </td>
                <td class="main_table_cell_active">
                    <input name="renewal_fee[<?php echo $count; ?>]" type="text"
                           value="<?php echo $row->renewal_fee; ?>" size="4">
                </td>
                <td class="main_table_cell_active">
                    <input name="misc_fee[<?php echo $count; ?>]" type="text" value="<?php echo $row->misc_fee; ?>"
                           size="4">
                </td>
                <td class="main_table_cell_active">
                    <select name="currency[<?php echo $count; ?>]" id="new_currency">
                        <?php
                        $sql_currency = "SELECT id, currency, name, symbol
                                         FROM currencies
                                         ORDER BY currency";
                        $result_currency = mysqli_query($connection, $sql_currency)
                        or $error->outputOldSqlError($connection);

                        while ($row_currency = mysqli_fetch_object($result_currency)) {

                            if ($row_currency->currency == $row->currency) {
                                ?>
                                <option value="<?php echo $row_currency->id; ?>" selected>
                                    <?php echo "$row_currency->name ($row_currency->currency $row_currency->symbol)"; ?>
                                </option>
                            <?php
                            } else {
                                ?>
                                <option value="<?php echo $row_currency->id; ?>">
                                    <?php echo "$row_currency->name ($row_currency->currency $row_currency->symbol)"; ?>
                                </option>
                            <?php
                            }
                        }
                        ?>
                    </select>
                    <?php //@formatter:off ?>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[<a class="invisiblelink"
                    href="ssl-provider-fees.php?sslpid=<?php echo $sslpid; ?>&ssltid=<?php echo $row->ssltid;
                    ?>&sslfeeid=<?php echo $row->sslfeeid; ?>&del=1">delete</a>]
                    <?php //@formatter:on ?>
                </td>
            </tr>
            <?php
            $count++;
        }
        ?>
    </table>
    <input type="hidden" name="which_form" value="edit"><BR>
    <BR><input type="submit" name="button" value="Update SSL Provider Fees &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
