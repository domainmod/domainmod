<?php
/**
 * /add/domain.php
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
$maint = new DomainMOD\Maintenance();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();
$timestamp = $time->time();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

include(DIR_INC . "head.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "Adding A New Domain";
$software_section = "domain-add";

// Form Variables
$new_domain = $_POST['new_domain'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_function = $_POST['new_function'];
$new_cat_id = $_POST['new_cat_id'];
$new_dns_id = $_POST['new_dns_id'];
$new_ip_id = $_POST['new_ip_id'];
$new_hosting_id = $_POST['new_hosting_id'];
$new_account_id = $_POST['new_account_id'];
$new_privacy = $_POST['new_privacy'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];

// Custom Fields
$query = "SELECT field_name
          FROM domain_fields
          ORDER BY `name`";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($field_name);

    if ($q->num_rows() > 0) {

        $count = 0;

        while ($q->fetch()) {

            $field_array[$count] = $field_name;
            $count++;

        }

        foreach ($field_array as $field) {

            $full_field = "new_" . $field . "";
            ${'new_' . $field} = $_POST[$full_field];

        }

    }

    $q->close();

} else $error->outputSqlError($conn, "ERROR");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();
    $domain = new DomainMOD\Domain();

    if ($date->checkDateFormat($new_expiry_date) && $domain->checkDomainFormat($new_domain) && $new_cat_id != "" &&
        $new_dns_id != "" && $new_ip_id != "" && $new_hosting_id != "" && $new_account_id != "" && $new_cat_id != "0" &&
        $new_dns_id != "0" && $new_ip_id != "0" && $new_hosting_id != "0" && $new_account_id != "0") {

        $query = "SELECT domain
                  FROM domains
                  WHERE domain = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('s', $new_domain);
            $q->execute();
            $q->store_result();

            if ($q->num_rows() === 0) {

                $tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);

                $query_ra = "SELECT registrar_id, owner_id
                             FROM registrar_accounts
                             WHERE id = ?";
                $q_ra = $conn->stmt_init();

                if ($q_ra->prepare($query_ra)) {

                    $q_ra->bind_param('i', $new_account_id);
                    $q_ra->execute();
                    $q_ra->store_result();
                    $q_ra->bind_result($new_registrar_id, $new_owner_id);
                    $q_ra->fetch();
                    $q_ra->close();

                } else $error->outputSqlError($conn, "ERROR");

                if ($new_privacy == "1") {

                    $query_f = "SELECT id, (renewal_fee + privacy_fee + misc_fee) AS total_cost
                                FROM fees
                                WHERE registrar_id = ?
                                  AND tld = ?";

                } else {

                    $query_f = "SELECT id, (renewal_fee + misc_fee) AS total_cost
                                FROM fees
                                WHERE registrar_id = ?
                                  AND tld = ?";

                }

                $q_f = $conn->stmt_init();

                if ($q_f->prepare($query_f)) {

                    $q_f->bind_param('is', $new_registrar_id, $tld);
                    $q_f->execute();
                    $q_f->store_result();
                    $q_f->bind_result($new_fee_id, $new_total_cost);
                    $q_f->fetch();
                    $q_f->close();

                    if ($new_fee_id == "") $new_fee_id = 0;
                    if ($new_total_cost == "") $new_total_cost = 0;

                } else $error->outputSqlError($conn, "ERROR");

                $query_d = "INSERT INTO domains
                            (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                             hosting_id, fee_id, total_cost, `function`, notes, privacy, active, insert_time)
                            VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $q_d = $conn->stmt_init();

                if ($q_d->prepare($query_d)) {

                    $q_d->bind_param('iiisssiiiiidssiis', $new_owner_id, $new_registrar_id, $new_account_id,
                        $new_domain, $tld, $new_expiry_date, $new_cat_id, $new_dns_id, $new_ip_id, $new_hosting_id,
                        $new_fee_id, $new_total_cost, $new_function, $new_notes, $new_privacy, $new_active, $timestamp);
                    $q_d->execute();

                    $temp_domain_id = $q_d->insert_id;

                    $q_d->close();

                } else $error->outputSqlError($conn, "ERROR");

                $query_df = "INSERT INTO domain_field_data
                             (domain_id, insert_time)
                             VALUES
                             (?, ?)";
                $q_df = $conn->stmt_init();

                if ($q_df->prepare($query_df)) {

                    $q_df->bind_param('is', $temp_domain_id, $timestamp);
                    $q_df->execute();
                    $q_df->close();

                } else $error->outputSqlError($conn, "ERROR");

                $query_df = "SELECT field_name
                             FROM domain_fields
                             ORDER BY name";
                $q_df = $conn->stmt_init();

                if ($q_df->prepare($query_df)) {

                    $q_df->execute();
                    $q_df->store_result();
                    $q_df->bind_result($field_name);

                    if ($q_df->num_rows() > 0) {

                        $count = 0;

                        while ($q_df->fetch()) {

                            $field_array[$count] = $field_name;
                            $count++;

                        }

                        foreach ($field_array as $field) {

                            $full_field = "new_" . $field;

                            $query_dfd = "UPDATE domain_field_data
                                          SET `" . $field . "` = ?
                                          WHERE domain_id = ?";
                            $q_dfd = $conn->stmt_init();

                            if ($q_dfd->prepare($query_dfd)) {

                                $q_dfd->bind_param('si', ${$full_field}, $temp_domain_id);
                                $q_dfd->execute();
                                $q_dfd->close();

                            } else $error->outputSqlError($conn, "ERROR");

                        }

                    }

                    $q_df->close();

                    $_SESSION['result_message'] = "Domain <font class=\"highlight\">$new_domain</font> Added<BR>";

                    $_SESSION['result_message'] .= $maint->updateSegments($connection);

                    $queryB = new DomainMOD\QueryBuild();

                    $sql = $queryB->missingFees('domains');
                    $_SESSION['missing_domain_fees'] = $system->checkForRows($connection, $sql);

                    $system->checkExistingAssets($connection);

                } else $error->outputSqlError($conn, "ERROR");

            } else {

                $_SESSION['result_message'] .= "The domain you entered is already in $software_title<BR>";

            }

            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

    } else {

        if (!$domain->checkDomainFormat($new_domain)) { $_SESSION['result_message'] .= "The domain format is
            incorrect<BR>"; }
        if (!$date->checkDateFormat($new_expiry_date)) { $_SESSION['result_message'] .= "The expiry date you entered is
            invalid<BR>"; }

    }

}
?>
<?php echo $system->doctype(); ?>
<html>
<head>
<title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_domain_form" method="post">
    <strong>Domain (255)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
    <input name="new_domain" type="text" size="50" maxlength="255" value="<?php echo $new_domain; ?>">
    <BR><BR>
    <strong>Function (255)</strong><BR><BR>
    <input name="new_function" type="text" size="50" maxlength="255" value="<?php echo $new_function; ?>">
    <BR><BR>
    <strong>Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR>
    <BR>
    <input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") {
        echo $new_expiry_date; } else { echo $timestamp_basic_plus_one_year; } ?>">
    <BR><BR>
    <strong>Registrar Account</strong><BR><BR>
    <?php
    $query = "SELECT ra.id, ra.username, o.name, r.name
              FROM registrar_accounts AS ra, owners AS o, registrars AS r
              WHERE ra.owner_id = o.id
                AND ra.registrar_id = r.id
              ORDER BY r.name, o.name, ra.username";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {
        $q->execute();
        $q->store_result();
        $q->bind_result($ra_id, $ra_username, $o_name, $r_name);
        $q->fetch();

        echo "<select name=\"new_account_id\">";

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $to_compare = $new_account_id;

        } else {

            $to_compare = $_SESSION['default_registrar_account'];

        }

        while ($q->fetch()) { ?>

        <option value="<?php echo $ra_id; ?>"<?php if ($ra_id == $to_compare) echo " selected"; ?>>
            <?php echo $r_name; ?>, <?php echo $o_name; ?> (<?php echo $ra_username;
            ?>)</option><?php

        }

        echo "</select>";

        $q->close();

    } else $error->outputSqlError($conn, "ERROR");
    ?>
    <BR><BR>
    <strong>DNS Profile</strong><BR><BR>
    <?php
    $query = "SELECT id, `name`
              FROM dns
              ORDER BY `name` ASC";
    $q = $conn->stmt_init();

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $to_compare = $new_dns_id;

    } else {

        $to_compare = $_SESSION['default_dns'];

    }

    if ($q->prepare($query)) {

        $q->execute();
        $q->store_result();
        $q->bind_result($dns_id, $dns_name);

        echo "<select name=\"new_dns_id\">";

        while ($q->fetch()) { ?>

            <option value="<?php echo $dns_id; ?>"<?php if ($dns_id == $to_compare) echo " selected";?>><?php
            echo $dns_name; ?></option><?php

        }

        echo "</select>";

        $q->close();

    } else $error->outputSqlError($conn, "ERROR");
    ?>
    <BR><BR>
    <strong>IP Address</strong><BR><BR>
    <?php
    $query = "SELECT id, `name`, ip
              FROM ip_addresses
              ORDER BY `name` ASC, ip ASC";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {
        $q->execute();
        $q->store_result();
        $q->bind_result($ip_id, $ip_name, $ip);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $to_compare = $new_ip_id;

        } else {

            $to_compare = $_SESSION['default_ip_address_domains'];

        }

        echo "<select name=\"new_ip_id\">";

        while ($q->fetch()) { ?>

            <option value="<?php echo $ip_id; ?>"<?php if ($ip_id == $to_compare) echo " selected";?>><?php
            echo $ip_name; ?> (<?php echo $ip; ?>)</option><?php

        }

        echo "</select>";

        $q->close();

    } else $error->outputSqlError($conn, "ERROR");
    ?>
    <BR><BR>
    <strong>Web Hosting Provider</strong><BR><BR>
    <?php
    $query = "SELECT id, `name`
              FROM hosting
              ORDER BY `name` ASC";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {
        $q->execute();
        $q->store_result();
        $q->bind_result($h_id, $h_name);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $to_compare = $new_hosting_id;

        } else {

            $to_compare = $_SESSION['default_host'];

        }

        echo "<select name=\"new_hosting_id\">";

        while ($q->fetch()) { ?>

            <option value="<?php echo $h_id; ?>"<?php if ($h_id == $to_compare) echo " selected";
            ?>><?php echo $h_name; ?></option><?php

        }

        echo "</select>";

        $q->close();

    } else $error->outputSqlError($conn, "ERROR");
    ?>
    <BR><BR>
    <strong>Category</strong><BR><BR>
    <?php
    $query = "SELECT id, `name`
              FROM categories
              ORDER BY `name` ASC";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->execute();
        $q->store_result();
        $q->bind_result($cat_id, $cat_name);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $to_compare = $new_cat_id;

        } else {

            $to_compare = $_SESSION['default_category_domains'];

        }

        echo "<select name=\"new_cat_id\">";

        while ($q->fetch()) { ?>

            <option value="<?php echo $cat_id; ?>"<?php if ($cat_id == $to_compare) echo " selected";?>><?php
            echo $cat_name; ?></option><?php

        }

        echo "</select>";

        $q->close();

    } else $error->outputSqlError($conn, "ERROR");
    ?>
    <BR><BR>
    <strong>Domain Status</strong><BR><BR>
    <select name="new_active">
        <option value="1"<?php if ($new_active == "1") echo " selected"; ?>>Active</option>
        <option value="2"<?php if ($new_active == "2") echo " selected"; ?>>In Transfer</option>
        <option value="5"<?php if ($new_active == "5") echo " selected"; ?>>Pending (Registration)</option>
        <option value="3"<?php if ($new_active == "3") echo " selected"; ?>>Pending (Renewal)</option>
        <option value="4"<?php if ($new_active == "4") echo " selected"; ?>>Pending (Other)</option>
        <option value="0"<?php if ($new_active == "0") echo " selected"; ?>>Expired</option>
        <option value="10"<?php if ($new_active == "10") echo " selected"; ?>>Sold</option>
    </select>
    <BR><BR>
    <strong>Privacy Enabled?</strong><BR><BR>
    <select name="new_privacy">
        <option value="0""<?php if ($new_privacy == "0") echo " selected"; ?>>No</option>
        <option value="1""<?php if ($new_privacy == "1") echo " selected"; ?>>Yes</option>
    </select>
    <BR><BR>
    <strong>Notes</strong><BR><BR>
    <textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
    <BR><BR>
    <?php
    $query = "SELECT field_name
              FROM domain_fields
              ORDER BY type_id ASC, `name` ASC";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {
        $q->execute();
        $q->store_result();
        $q->bind_result($field_name);

        if ($q->num_rows() > 0) { ?>

            <BR><font class="subheadline">Custom Fields</font><BR><BR><?php

            $count = 0;

            while ($q->fetch()) {

                $field_array[$count] = $field_name;
                $count++;

            }

            foreach($field_array as $field) {

                $query_df = "SELECT df.name, df.field_name, df.type_id, df.description
                             FROM domain_fields AS df, custom_field_types AS cft
                             WHERE df.type_id = cft.id
                               AND df.field_name = ?";
                $q_df = $conn->stmt_init();

                if ($q_df->prepare($query_df)) {

                    $q_df->bind_param('s', $field);
                    $q_df->execute();
                    $q_df->store_result();
                    $q_df->bind_result($df_name, $df_field_name, $df_type_id, $df_description);

                    while ($q_df->fetch()) {

                        if ($df_type_id == "1") { // Check Box ?>

                            <input type="checkbox" name="new_<?php echo $df_field_name; ?>" value="1"<?php
                            if (${'new_' . $field} == "1") echo " checked"; ?>>
                            &nbsp;<strong><?php echo $df_name; ?></strong><BR><?php

                            if ($df_description != "") {

                                echo $df_description . "<BR><BR>";

                            } else {

                                echo "<BR>";

                            }

                        } elseif ($df_type_id == "2") { // Text ?>

                            <strong><?php echo $df_name; ?> (255)</strong><?php

                            if ($df_description != "") {

                                echo "<BR>" . $df_description . "<BR><BR>";

                            } else {

                                echo "<BR><BR>";

                            } ?>
                            <input type="text" name="new_<?php echo $df_field_name; ?>" size="50" maxlength="255"
                                   value="<?php echo ${'new_' . $df_field_name}; ?>"><BR><BR><?php

                        } elseif ($df_type_id == "3") { // Text Area ?>

                            <strong><?php echo $df_name; ?></strong><?php

                            if ($df_description != "") {

                                echo "<BR>" . $df_description . "<BR><BR>";

                            } else {

                                echo "<BR><BR>";

                            } ?>
                            <textarea name="new_<?php echo $df_field_name; ?>" cols="60" rows="5"><?php
                                echo ${'new_' . $df_field_name}; ?></textarea><BR><BR><?php

                        }

                    }

                    $q_df->close();

                } else $error->outputSqlError($conn, "ERROR");

            }

            echo "<BR>";

        }

        $q->close();

    } else $error->outputSqlError($conn, "ERROR");
    ?>
    <input type="submit" name="button" value="Add This Domain &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
