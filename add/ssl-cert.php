<?php
/**
 * /add/ssl-cert.php
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
$timestamp = $time->time();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "Adding A New SSL Certificate";
$software_section = "ssl-cert-add";

// Form Variables
$new_domain_id = $_POST['new_domain_id'];
$new_name = $_POST['new_name'];
$new_type_id = $_POST['new_type_id'];
$new_ip_id = $_POST['new_ip_id'];
$new_cat_id = $_POST['new_cat_id'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_account_id = $_POST['new_account_id'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];

// Custom Fields
$query = "SELECT field_name
          FROM ssl_cert_fields
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

} else {
    $error->outputSqlError($conn, "ERROR");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_type_id != "" && $new_ip_id != "" &&
        $new_cat_id != "" && $new_domain_id != "" && $new_account_id != "" && $new_type_id != "0" && $new_ip_id != "0"
        && $new_cat_id != "0" && $new_domain_id != "0" && $new_account_id != "0"
    ) {

        $query = "SELECT ssl_provider_id, owner_id
                  FROM ssl_accounts
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_account_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($new_ssl_provider_id, $new_owner_id);
            $q->fetch();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "SELECT id, (renewal_fee + misc_fee) AS total_cost
                  FROM ssl_fees
                  WHERE ssl_provider_id = ?
                    AND type_id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('ii', $new_ssl_provider_id, $new_type_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($new_fee_id, $new_total_cost);
            $q->fetch();
            $q->close();

            if ($new_fee_id == "") $new_fee_id = 0;
            if ($new_total_cost == "") $new_total_cost = 0;

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "INSERT INTO ssl_certs
                  (owner_id, ssl_provider_id, account_id, domain_id, `name`, type_id, ip_id, cat_id, expiry_date,
                   fee_id, total_cost, notes, active, insert_time)
                  VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('iiiisiiisidsis', $new_owner_id, $new_ssl_provider_id, $new_account_id, $new_domain_id,
                $new_name, $new_type_id, $new_ip_id, $new_cat_id, $new_expiry_date, $new_fee_id, $new_total_cost,
                $new_notes, $new_active, $timestamp);
            $q->execute();

            $temp_ssl_id = $q->insert_id;

            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "INSERT INTO ssl_cert_field_data
                  (ssl_id, insert_time)
                  VALUES
                  (?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('is', $temp_ssl_id, $timestamp);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "SELECT field_name
                  FROM ssl_cert_fields
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

                    $full_field = "new_" . $field;

                    $query_f = "UPDATE ssl_cert_field_data
                                SET `" . $field . "` = ?
                                WHERE ssl_id = ?";
                    $q_f = $conn->stmt_init();

                    if ($q_f->prepare($query_f)) {

                        $q_f->bind_param('si', ${$full_field}, $temp_ssl_id);
                        $q_f->execute();
                        $q_f->close();

                    } else {
                        $error->outputSqlError($conn, "ERROR");
                    }

                }

            }

            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $_SESSION['s_result_message'] = "SSL Certificate <div class=\"highlight\">$new_name</div> Added<BR>";

        $queryB = new DomainMOD\QueryBuild();

        $sql = $queryB->missingFees('ssl_certs');
        $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($connection, $sql);

        $system->checkExistingAssets($connection);

    } else {

        if ($new_name == "") {
            $_SESSION['s_result_message'] .= "Enter a name for the SSL certificate<BR>";
        }
        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_result_message'] .= "The expiry date you entered is
            invalid<BR>";
        }

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_ssl_cert_form" method="post">
    <strong>Host / Label (100)</strong><a title="Required Field">
        <div class="default_highlight">*</div>
    </a><BR><BR>
    <input name="new_name" type="text" size="50" maxlength="100" value="<?php echo $new_name; ?>">
    <BR><BR>
    <strong>Expiry Date (YYYY-MM-DD)</strong><a title="Required Field">
        <div
            class="default_highlight">*
        </div>
    </a><BR><BR>
    <input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") {
        echo $new_expiry_date;
    } else {
        echo $time->toUserTimezone($timestamp_basic_plus_one_year, 'Y-m-d');
    } ?>">
    <BR><BR>
    <strong>Domain</strong><BR><BR>
    <?php
    $query = "SELECT id, domain
          FROM domains
          WHERE active NOT IN ('0', '10')
          ORDER BY domain ASC";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->execute();
        $q->store_result();
        $q->bind_result($id, $domain);

        echo "<select name=\"new_domain_id\">";

        while ($q->fetch()) { ?>

            <option value="<?php echo $id; ?>"<?php if ($id == $new_domain_id) echo " selected"; ?>><?php echo
            $domain; ?></option><?php

        }

        echo "</select>";

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }
    ?>
    <BR><BR>
    <strong>SSL Provider Account</strong><BR><BR>
    <?php
    $query = "SELECT sslpa.id, sslpa.username, o.name AS o_name, sslp.name AS sslp_name
              FROM ssl_accounts AS sslpa, owners AS o, ssl_providers AS sslp
              WHERE sslpa.owner_id = o.id
                AND sslpa.ssl_provider_id = sslp.id
                ORDER BY sslp_name, o_name, sslpa.username";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->execute();
        $q->store_result();
        $q->bind_result($sslpa_id, $sslpa_username, $o_name, $sslp_name);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $to_compare = $new_account_id;

        } else {

            $to_compare = $_SESSION['s_default_ssl_provider_account'];

        }

        echo "<select name=\"new_account_id\">";

        while ($q->fetch()) { ?>

            <option value="<?php echo $sslpa_id; ?>"<?php if ($sslpa_id == $to_compare) echo " selected"; ?>><?php
            echo $sslp_name; ?>, <?php echo $o_name; ?> (<?php echo $sslpa_username; ?>)</option><?php

        }

        echo "</select>";

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }
    ?>
    <BR><BR>
    <strong>Certificate Type</strong><BR><BR>
    <?php
    $query = "SELECT id, type
          FROM ssl_cert_types
          ORDER BY type";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {
        $q->execute();
        $q->store_result();
        $q->bind_result($id, $type);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $to_compare = $new_type_id;

        } else {

            $to_compare = $_SESSION['s_default_ssl_type'];

        }

        echo "<select name=\"new_type_id\">";

        while ($q->fetch()) { ?>

            <option value="<?php echo $id; ?>"<?php if ($id == $to_compare) echo " selected"; ?>><?php echo $type; ?>
            </option><?php

        }

        echo "</select>";

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }
    ?>
    <BR><BR>
    <strong>IP Address</strong><BR><BR>
    <?php
    $query = "SELECT id, ip, `name`
          FROM ip_addresses
          ORDER BY `name` ASC, ip ASC";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->execute();
        $q->store_result();
        $q->bind_result($id, $ip, $name);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $to_compare = $new_ip_id;

        } else {

            $to_compare = $_SESSION['s_default_ip_address_ssl'];

        }

        echo "<select name=\"new_ip_id\">";

        while ($q->fetch()) { ?>

            <option value="<?php echo $id; ?>"<?php if ($id == $to_compare) echo " selected"; ?>><?php echo $name; ?>
            (<?php
            echo $ip; ?>)</option><?php

        }

        echo "</select>";

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }
    ?>
    <BR><BR>
    <strong>Category</strong><BR><BR>
    <?php
    $query = "SELECT id, `name`
          FROM categories
          ORDER BY `name`";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {
        $q->execute();
        $q->store_result();
        $q->bind_result($id, $name);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $to_compare = $new_cat_id;

        } else {

            $to_compare = $_SESSION['s_default_category_ssl'];

        }

        echo "<select name=\"new_cat_id\">";

        while ($q->fetch()) { ?>

            <option value="<?php echo $id; ?>"<?php if ($id == $to_compare) echo " selected"; ?>><?php echo $name; ?>
            </option><?php

        }

        echo "</select>";

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }
    ?>
    <BR><BR>
    <strong>Certificate Status</strong><BR><BR>
    <select name="new_active">
        <option value="1"<?php if ($new_active == "1") echo " selected"; ?>>Active</option>
        <option value="2"<?php if ($new_active == "2") echo " selected"; ?>>Pending (Registration)</option>
        <option value="3"<?php if ($new_active == "3") echo " selected"; ?>>Pending (Renewal)</option>
        <option value="4"<?php if ($new_active == "4") echo " selected"; ?>>Pending (Other)</option>
        <option value="0"<?php if ($new_active == "0") echo " selected"; ?>>Expired</option>
    </select>
    <BR><BR>
    <strong>Notes</strong><BR><BR>
    <textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
    <BR><BR>
    <?php
    $query = "SELECT field_name
          FROM ssl_cert_fields
          ORDER BY type_id, `name`";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->execute();
        $q->store_result();
        $q->bind_result($field_name);

        if ($q->num_rows() > 0) { ?>

            <BR>
            <div class="subheadline">Custom Fields</div><BR><?php

            $count = 0;

            while ($q->fetch()) {

                $field_array[$count] = $field_name;
                $count++;

            }

            foreach ($field_array as $field) {

                $query_cf = "SELECT sf.name, sf.field_name, sf.type_id, sf.description
                         FROM ssl_cert_fields AS sf, custom_field_types AS cft
                         WHERE sf.type_id = cft.id
                           AND sf.field_name = ?";
                $q_cf = $conn->stmt_init();

                if ($q_cf->prepare($query_cf)) {

                    $q_cf->bind_param('s', $field);
                    $q_cf->execute();
                    $q_cf->store_result();
                    $q_cf->bind_result($name, $field_name, $type_id, $description);

                    while ($q_cf->fetch()) {

                        if ($type_id == "1") { // Check Box ?>

                            <input type="checkbox" name="new_<?php echo $field_name; ?>" value="1"<?php
                            if (${'new_' . $field} == "1") echo " checked"; ?>>&nbsp;<strong><?php echo $name; ?>
                            </strong><BR><?php

                            if ($description != "") {

                                echo $description . "<BR><BR>";

                            } else {

                                echo "<BR>";

                            }

                        } elseif ($type_id == "2") { // Text ?>

                            <strong><?php echo $name; ?> (255)</strong><?php

                            if ($description != "") {

                                echo "<BR>" . $description . "<BR><BR>";

                            } else {

                                echo "<BR><BR>";

                            } ?>
                            <input type="text" name="new_<?php echo $field_name; ?>" size="50" maxlength="255"
                                   value="<?php
                                   echo ${'new_' . $field_name}; ?>"><BR><BR><?php

                        } elseif ($type_id == "3") { // Text Area ?>

                            <strong><?php echo $name; ?></strong><?php

                            if ($description != "") {

                                echo "<BR>" . $description . "<BR><BR>";

                            } else {

                                echo "<BR><BR>";

                            } ?>
                            <textarea name="new_<?php echo $field_name; ?>" cols="60" rows="5"><?php
                                echo ${'new_' . $field_name}; ?></textarea><BR><BR><?php

                        }

                    }

                    $q_cf->close();

                } else {
                    $error->outputSqlError($conn, "ERROR");
                }

            }

            echo "<BR>";

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }
    ?>
    <input type="submit" name="button" value="Add This SSL Certificate &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
