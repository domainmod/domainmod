<?php
/**
 * /ssl/add.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';

require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$timestamp = $time->stamp();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/ssl-add.inc.php';
require_once DIR_INC . '/database.inc.php';

$pdo = $system->db();
$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);

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
$q = $dbcon->stmt_init();

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
    $error->outputSqlError($dbcon, '1', 'ERROR');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_type_id != "" && $new_ip_id != "" &&
        $new_cat_id != "" && $new_domain_id != "" && $new_account_id != "" && $new_type_id != "0" && $new_ip_id != "0"
        && $new_cat_id != "0" && $new_domain_id != "0" && $new_account_id != "0" && $new_active != '') {

        $stmt = $pdo->prepare("
            SELECT ssl_provider_id, owner_id
            FROM ssl_accounts
            WHERE id = :new_account_id");
        $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result) {

            $new_ssl_provider_id = $result->ssl_provider_id;
            $new_owner_id = $result->owner_id;

        }

        $stmt = $pdo->prepare("
            SELECT id, (renewal_fee + misc_fee) AS total_cost
            FROM ssl_fees
            WHERE ssl_provider_id = :new_ssl_provider_id
              AND type_id = :new_type_id");
        $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
        $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result) {

            $new_fee_id = $result->id;
            $new_total_cost = $result->total_cost;

        }

        if ($new_fee_id == "") $new_fee_id = 0;
        if ($new_total_cost == "") $new_total_cost = 0;

        $stmt = $pdo->prepare("
            INSERT INTO ssl_certs
            (owner_id, ssl_provider_id, account_id, domain_id, `name`, type_id, ip_id, cat_id, expiry_date,
             fee_id, total_cost, notes, active, created_by, insert_time)
            VALUES
            (:new_owner_id, :new_ssl_provider_id, :new_account_id, :new_domain_id, :new_name, :new_type_id, :new_ip_id,
             :new_cat_id, :new_expiry_date, :new_fee_id, :new_total_cost, :new_notes, :new_active, :user_id,
             :timestamp)");
        $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
        $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
        $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
        $stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
        $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
        $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
        $stmt->bindValue('new_ip_id', $new_ip_id, PDO::PARAM_INT);
        $stmt->bindValue('new_cat_id', $new_cat_id, PDO::PARAM_INT);
        $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
        $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
        $stmt->bindValue('new_total_cost', strval($new_total_cost), PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->execute();

        $temp_ssl_id = $pdo->lastInsertId('id');

        $stmt = $pdo->prepare("
            INSERT INTO ssl_cert_field_data
            (ssl_id, insert_time)
            VALUES
            (:temp_ssl_id, :timestamp)");
        $stmt->bindValue('temp_ssl_id', $temp_ssl_id, PDO::PARAM_INT);
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->execute();

        $query = "SELECT field_name
                  FROM ssl_cert_fields
                  ORDER BY `name`";
        $q = $dbcon->stmt_init();

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
                    $q_f = $dbcon->stmt_init();

                    if ($q_f->prepare($query_f)) {

                        $q_f->bind_param('si', ${$full_field}, $temp_ssl_id);
                        $q_f->execute();
                        $q_f->close();

                    } else {
                        $error->outputSqlError($dbcon, '1', 'ERROR');
                    }

                }

            }

            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

        $queryB = new DomainMOD\QueryBuild();
        $sql = $queryB->missingFees('ssl_certs');
        $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($sql);

        $system->checkExistingAssets();

        $_SESSION['s_message_success'] .= 'SSL Certificate ' . $new_name . ' Added<BR>';

    } else {

        if ($new_name == "") {
            $_SESSION['s_message_danger'] .= "Enter a name for the SSL certificate<BR>";
        }
        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_message_danger'] .= "The expiry date you entered is invalid<BR>";
        }

        if ($new_domain_id == '' || $new_domain_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the domain<BR>";

        }

        if ($new_account_id == '' || $new_account_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the SSL Provider Account<BR>";

        }

        if ($new_type_id == '' || $new_type_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the SSL Type<BR>";

        }

        if ($new_ip_id == '' || $new_ip_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the IP Address<BR>";

        }

        if ($new_cat_id == '' || $new_cat_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Category<BR>";

        }

        if ($new_cat_id == '' || $new_cat_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Category<BR>";

        }

        if ($new_active == '') {

            $_SESSION['s_message_danger'] .= "Choose the Status<BR>";

        }

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_name', 'Host / Label (100)', '', $new_name, '100', '', '1', '', '');
if ($new_expiry_date == '') {
    $new_expiry_date = $time->toUserTimezone($timestamp_basic_plus_one_year, 'Y-m-d');
}
echo $form->showInputText('new_expiry_date', 'Expiry Date (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

echo $form->showDropdownTop('new_domain_id', 'Domain', '', '1', '');
$query = "SELECT id, domain
          FROM domains
          WHERE (active NOT IN ('0', '10') OR id = ?)
          ORDER BY domain";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('i', $new_domain_id);
    $q->execute();
    $q->store_result();
    $q->bind_result($id, $domain);

    while ($q->fetch()) {

        echo $form->showDropdownOption($id, $domain, '');

    }

    $q->close();

} else $error->outputSqlError($dbcon, '1', 'ERROR');
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_account_id;

} else {

    $to_compare = $_SESSION['s_default_ssl_provider_account'];

}
$sql_account = "SELECT sslpa.id, sslpa.username, o.name AS o_name, sslp.name AS sslp_name
                FROM ssl_accounts AS sslpa, owners AS o, ssl_providers AS sslp
                WHERE sslpa.owner_id = o.id
                  AND sslpa.ssl_provider_id = sslp.id
                ORDER BY sslp_name ASC, o_name ASC, sslpa.username ASC";
$result_account = mysqli_query($dbcon, $sql_account) or $error->outputSqlError($dbcon, '1', 'ERROR');
echo $form->showDropdownTop('new_account_id', 'SSL Provider Account', '', '1', '');
while ($row_account = mysqli_fetch_object($result_account)) {

    echo $form->showDropdownOption($row_account->id, $row_account->sslp_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $to_compare);

}
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_type_id;

} else {

    $to_compare = $_SESSION['s_default_ssl_type'];

}
$sql_type = "SELECT id, type
             FROM ssl_cert_types
             ORDER BY type ASC";
$result_type = mysqli_query($dbcon, $sql_type) or $error->outputSqlError($dbcon, '1', 'ERROR');
echo $form->showDropdownTop('new_type_id', 'Certificate Type', '', '1', '');
while ($row_type = mysqli_fetch_object($result_type)) {

    echo $form->showDropdownOption($row_type->id, $row_type->type, $to_compare);

}
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_ip_id;

} else {

    $to_compare = $_SESSION['s_default_ip_address_ssl'];

}
$sql_ip = "SELECT id, ip, `name`
           FROM ip_addresses
           ORDER BY `name`, ip";
$result_ip = mysqli_query($dbcon, $sql_ip) or $error->outputSqlError($dbcon, '1', 'ERROR');
echo $form->showDropdownTop('new_ip_id', 'IP Address', '', '1', '');
while ($row_ip = mysqli_fetch_object($result_ip)) {

    echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ')', $to_compare);

}
echo $form->showDropdownBottom('');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_cat_id;

} else {

    $to_compare = $_SESSION['s_system_default_category_ssl'];

}
$sql_cat = "SELECT id, `name`
            FROM categories
            ORDER BY `name`";
$result_cat = mysqli_query($dbcon, $sql_cat) or $error->outputSqlError($dbcon, '1', 'ERROR');
echo $form->showDropdownTop('new_cat_id', 'Category', '', '1', '');
while ($row_cat = mysqli_fetch_object($result_cat)) {

    echo $form->showDropdownOption($row_cat->id, $row_cat->name, $to_compare);

}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_active', 'Certificate Status', '', '', '');
echo $form->showDropdownOption('1', 'Active', $new_active);
echo $form->showDropdownOption('5', 'Pending (Registration)', $new_active);
echo $form->showDropdownOption('3', 'Pending (Renewal)', $new_active);
echo $form->showDropdownOption('4', 'Pending (Other)', $new_active);
echo $form->showDropdownOption('0', 'Expired', $new_active);
echo $form->showDropdownBottom('');

echo $form->showInputTextarea('new_notes', 'Notes', $subtext, $new_notes, '', '', '');

$query = "SELECT field_name
          FROM ssl_cert_fields
          ORDER BY type_id, `name`";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($field_name);

    if ($q->num_rows() > 0) { ?>

        <h3>Custom Fields</h3><?php

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
            $q_cf = $dbcon->stmt_init();

            if ($q_cf->prepare($query_cf)) {

                $q_cf->bind_param('s', $field);
                $q_cf->execute();
                $q_cf->store_result();
                $q_cf->bind_result($name, $field_name, $type_id, $description);

                while ($q_cf->fetch()) {

                    if ($type_id == "1") { // Check Box

                        echo $form->showCheckbox('new_' . $field_name, '1', $name, $description, ${'new_' . $field}, '', '');

                    } elseif ($type_id == "2") { // Text

                        echo $form->showInputText('new_' . $field_name, $name, $description, ${'new_' . $field}, '255', '', '', '', '');

                    } elseif ($type_id == "3") { // Text Area

                        echo $form->showInputTextarea('new_' . $field_name, $name, $description, ${'new_' . $field}, '', '', '');

                    } elseif ($type_id == "4") { // Date

                        echo $form->showInputText('new_' . $field_name, $name, $description, ${'new_' . $field}, '10', '', '', '', '');

                    } elseif ($type_id == "5") { // Time Stamp

                        echo $form->showInputText('new_' . $field_name, $name, $description, ${'new_' . $field}, '19', '', '', '', '');

                    }

                }

                $q_cf->close();

            } else {
                $error->outputSqlError($dbcon, '1', 'ERROR');
            }

        }

        echo "<BR>";

    }

    $q->close();

} else {
    $error->outputSqlError($dbcon, '1', 'ERROR');
}

echo $form->showSubmitButton('Add SSL Certificate', '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
