<?php
/**
 * /domains/edit.php
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
$maint = new DomainMOD\Maintenance();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/domains-edit.inc.php';
require_once DIR_INC . '/database.inc.php';

$pdo = $system->db();
$system->authCheck();

$did = (integer) $_REQUEST['did'];

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$new_domain = $_POST['new_domain'];
$new_tld = $_POST['new_tld'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_function = $_POST['new_function'];
$new_cat_id = $_POST['new_cat_id'];
$new_dns_id = $_POST['new_dns_id'];
$new_ip_id = $_POST['new_ip_id'];
$new_hosting_id = $_POST['new_hosting_id'];
$new_account_id = $_POST['new_account_id'];
$new_autorenew = $_POST['new_autorenew'];
$new_privacy = $_POST['new_privacy'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];

// Custom Fields
$sql = "SELECT field_name
        FROM domain_fields
        ORDER BY `name`";
$result = mysqli_query($dbcon, $sql);

if (mysqli_num_rows($result) > 0) {

    $count = 0;

    while ($row = mysqli_fetch_object($result)) {

        $field_array[$count] = $row->field_name;
        $count++;

    }

    foreach ($field_array as $field) {

        $full_field = "new_" . $field . "";
        ${'new_' . $field} = $_POST[$full_field];

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    $date = new DomainMOD\Date();

    if ($date->checkDateFormat($new_expiry_date) && $new_cat_id != "" && $new_dns_id != "" && $new_ip_id != "" &&
        $new_hosting_id != "" && $new_account_id != "" && $new_cat_id != "0" && $new_dns_id != "0" &&
        $new_ip_id != "0" && $new_hosting_id != "0" && $new_account_id != "0" && $new_active != '') {

        $stmt = $pdo->prepare("
            SELECT registrar_id, owner_id
            FROM registrar_accounts
            WHERE id = :new_account_id");
        $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result) {

            $new_registrar_id = $result->registrar_id;
            $new_owner_id = $result->owner_id;

        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM fees
            WHERE registrar_id = :new_registrar_id
              AND tld = :new_tld");
        $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
        $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $temp_fee_id = "0";
            $temp_fee_fixed = "0";

        } else {

            $temp_fee_id = $result;
            $temp_fee_fixed = "1";

        }

        if ($new_privacy == "1") {

            $fee_string = "renewal_fee + privacy_fee + misc_fee";

        } else {

            $fee_string = "renewal_fee + misc_fee";

        }

        $sql = "SELECT (" . $fee_string . ") AS total_cost
                FROM fees
                WHERE registrar_id = '" . $new_registrar_id . "'
                  AND tld = '" . mysqli_real_escape_string($dbcon, $new_tld) . "'";
        $result = mysqli_query($dbcon, $sql);

        while ($row = mysqli_fetch_object($result)) {
            $new_total_cost = $row->total_cost;
        }

        $stmt = $pdo->prepare("
            UPDATE domains
            SET owner_id = :new_owner_id,
                registrar_id = :new_registrar_id,
                account_id = :new_account_id,
                tld = :new_tld,
                expiry_date = :new_expiry_date,
                cat_id = :new_cat_id,
                dns_id = :new_dns_id,
                ip_id = :new_ip_id,
                hosting_id = :new_hosting_id,
                fee_id = :temp_fee_id,
                total_cost = :new_total_cost,
                `function` = :new_function,
                notes = :new_notes,
                autorenew = :new_autorenew,
                privacy = :new_privacy,
                active = :new_active,
                fee_fixed = :temp_fee_fixed,
                update_time = :timestamp
            WHERE id = :did");
        $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
        $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
        $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
        $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
        $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
        $stmt->bindValue('new_cat_id', $new_cat_id, PDO::PARAM_INT);
        $stmt->bindValue('new_dns_id', $new_dns_id, PDO::PARAM_INT);
        $stmt->bindValue('new_ip_id', $new_ip_id, PDO::PARAM_INT);
        $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
        $stmt->bindValue('temp_fee_id', $temp_fee_id, PDO::PARAM_INT);
        $stmt->bindValue('new_total_cost', strval($new_total_cost), PDO::PARAM_STR);
        $stmt->bindValue('new_function', $new_function, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $stmt->bindValue('new_autorenew', $new_autorenew, PDO::PARAM_INT);
        $stmt->bindValue('new_privacy', $new_privacy, PDO::PARAM_INT);
        $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
        $stmt->bindValue('temp_fee_fixed', $temp_fee_fixed, PDO::PARAM_INT);
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('did', $did, PDO::PARAM_INT);
        $stmt->execute();

        $sql = "SELECT field_name
                FROM domain_fields
                ORDER BY `name`";
        $result = mysqli_query($dbcon, $sql);

        if (mysqli_num_rows($result) > 0) {

            $count = 0;

            while ($row = mysqli_fetch_object($result)) {

                $field_array[$count] = $row->field_name;
                $count++;

            }

            foreach ($field_array as $field) {

                $full_field = "new_" . $field;

                $sql = "UPDATE domain_field_data
                        SET `" . $field . "` = '" . mysqli_real_escape_string($dbcon, ${$full_field}) . "',
                            update_time = '" . $timestamp . "'
                        WHERE domain_id = '" . mysqli_real_escape_string($dbcon, $did) . "'";
                $result = mysqli_query($dbcon, $sql);

            }

        }

        $_SESSION['s_message_success'] .= "Domain " . $new_domain . " Updated<BR>";

        $maint->updateSegments();

        $queryB = new DomainMOD\QueryBuild();

        $sql = $queryB->missingFees('domains');
        $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

        header('Location: edit.php?did=' . $did);
        exit;

    } else {

        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_message_danger'] .= "The expiry date you entered is invalid<BR>";
        }

        if ($new_account_id == '' || $new_account_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Registrar Account<BR>";

        }

        if ($new_dns_id == '' || $new_dns_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the DNS Profile<BR>";

        }

        if ($new_ip_id == '' || $new_ip_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the IP Address<BR>";

        }

        if ($new_hosting_id == '' || $new_hosting_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Web Host<BR>";

        }

        if ($new_cat_id == '' || $new_cat_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Category<BR>";

        }

        if ($new_active == '') {

            $_SESSION['s_message_danger'] .= "Choose the Status<BR>";

        }

    }

} else {

    $stmt = $pdo->prepare("
        SELECT d.domain, d.tld, d.expiry_date, d.cat_id, d.dns_id, d.ip_id, d.hosting_id, d.function, d.notes, d.autorenew, d.privacy, d.active, ra.id AS account_id
        FROM domains AS d, registrar_accounts AS ra
        WHERE d.account_id = ra.id
          AND d.id = :did");
    $stmt->bindValue('did', $did, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result) {

        $new_domain = $result->domain;
        $new_tld = $result->tld;
        $new_expiry_date = $result->expiry_date;
        $new_cat_id = $result->cat_id;
        $new_dns_id = $result->dns_id;
        $new_ip_id = $result->ip_id;
        $new_hosting_id = $result->hosting_id;
        $new_function = $result->function;
        $new_notes = $result->notes;
        $new_autorenew = $result->autorenew;
        $new_privacy = $result->privacy;
        $new_active = $result->active;
        $new_account_id = $result->account_id;

    }

}

if ($del == "1") {

    $stmt = $pdo->prepare("
        SELECT domain_id
        FROM ssl_certs
        WHERE domain_id = :did
        LIMIT 1");
    $stmt->bindValue('did', $did, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if ($result) {

        $existing_ssl_certs = 1;

    }

    if ($existing_ssl_certs > 0) {

        $_SESSION['s_message_danger'] .= "This Domain has SSL Certificates associated with it and cannot be deleted<BR>";

    } else {

        $_SESSION['s_message_danger'] .= "Are you sure you want to delete this Domain?<BR><BR><a href=\"edit.php?did=" . $did . "&really_del=1\">YES, REALLY DELETE THIS DOMAIN</a><BR>";

    }

}

if ($really_del == "1") {

    $stmt = $pdo->prepare("
        DELETE FROM domains
        WHERE id = :did");
    $stmt->bindValue('did', $did, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare("
        DELETE FROM domain_field_data
        WHERE domain_id = :did");
    $stmt->bindValue('did', $did, PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['s_message_success'] .= "Domain " . $new_domain . " Deleted<BR>";

    $maint->updateSegments();

    $system->checkExistingAssets();

    header("Location: ../domains/index.php");
    exit;

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
echo '<strong>Domain</strong><BR>';
echo htmlentities($new_domain, ENT_QUOTES, 'UTF-8'). '<BR><BR>';
echo $form->showInputText('new_function', 'Function (255)', '', $new_function, '255', '', '', '', '');
echo $form->showInputText('new_expiry_date', 'Expiry Date (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

$result = $pdo->query("
    SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
    FROM registrar_accounts AS ra, owners AS o, registrars AS r
    WHERE ra.owner_id = o.id
      AND ra.registrar_id = r.id
    ORDER BY r_name ASC, o_name ASC, ra.username ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_account_id', 'Registrar Account', '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->r_name . ', ' . $row->o_name . ' (' . $row->username . ')', $new_account_id);

    }

    echo $form->showDropdownBottom('');

}

$result = $pdo->query("
    SELECT id, `name`
    FROM dns
    ORDER BY name ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_dns_id', 'DNS Profile', '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_dns_id);

    }

    echo $form->showDropdownBottom('');

}

$result = $pdo->query("
    SELECT id, `name`, ip
    FROM ip_addresses
    ORDER BY `name` ASC, ip ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_ip_id', 'IP Address', '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $new_ip_id);

    }

    echo $form->showDropdownBottom('');
}

$result = $pdo->query("
    SELECT id, `name`
    FROM hosting
    ORDER BY name ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_hosting_id', 'Web Hosting Provider', '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_hosting_id);

    }

    echo $form->showDropdownBottom('');

}

$result = $pdo->query("
    SELECT id, `name`
    FROM categories
    ORDER BY name ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_cat_id', 'Category', '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_cat_id);

    }

    echo $form->showDropdownBottom('');

}

echo $form->showDropdownTop('new_active', 'Domain Status', '', '', '');
echo $form->showDropdownOption('1', 'Active', $new_active);
echo $form->showDropdownOption('5', 'Pending (Registration)', $new_active);
echo $form->showDropdownOption('3', 'Pending (Renewal)', $new_active);
echo $form->showDropdownOption('2', 'Pending (Transfer)', $new_active);
echo $form->showDropdownOption('4', 'Pending (Other)', $new_active);
echo $form->showDropdownOption('10', 'Sold', $new_active);
echo $form->showDropdownOption('0', 'Expired', $new_active);
echo $form->showDropdownBottom('');

echo $form->showRadioTop('Auto Renewal?', '', '');
echo $form->showRadioOption('new_autorenew', '1', 'Yes', $new_autorenew, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_autorenew', '0', 'No', $new_autorenew, '', '');
echo $form->showRadioBottom('');

echo $form->showRadioTop('Privacy Enabled?', '', '');
echo $form->showRadioOption('new_privacy', '1', 'Yes', $new_privacy, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_privacy', '0', 'No', $new_privacy, '', '');
echo $form->showRadioBottom('');

if ($new_notes != '') {
    $subtext = '[<a target="_blank" href="notes.php?did=' . htmlentities($did, ENT_QUOTES, 'UTF-8') . '">view full notes</a>]';
} else {
    $subtext = '';
}
echo $form->showInputTextarea('new_notes', 'Notes', $subtext, $new_notes, '', '', '');

$sql = "SELECT field_name
        FROM domain_fields
        ORDER BY type_id, `name`";
$result = mysqli_query($dbcon, $sql);

if (mysqli_num_rows($result) > 0) { ?>

    <h3>Custom Fields</h3><?php

    $count = 0;

    while ($row = mysqli_fetch_object($result)) {

        $field_array[$count] = $row->field_name;
        $count++;

    }

    foreach ($field_array as $field) {

        $sql = "SELECT df.name, df.field_name, df.type_id, df.description
                FROM domain_fields AS df, custom_field_types AS cft
                WHERE df.type_id = cft.id
                  AND df.field_name = '" . $field . "'";
        $result = mysqli_query($dbcon, $sql);

        while ($row = mysqli_fetch_object($result)) {

            if (${'new_' . $field}) {

                $field_data = ${'new_' . $field};

            } else {

                $sql_data = "SELECT " . $row->field_name . "
                             FROM domain_field_data
                             WHERE domain_id = '" . mysqli_real_escape_string($dbcon, $did) . "'";
                $result_data = mysqli_query($dbcon, $sql_data);

                while ($row_data = mysqli_fetch_object($result_data)) {

                    $field_data = $row_data->{$row->field_name};

                }

            }

            if ($row->type_id == "1") { // Check Box

                echo $form->showCheckbox('new_' . $row->field_name, '1', $row->name, $row->description, $field_data, '', '');

            } elseif ($row->type_id == "2") { // Text

                echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, $field_data, '255', '', '', '', '');

            } elseif ($row->type_id == "3") { // Text Area

                echo $form->showInputTextarea('new_' . $row->field_name, $row->name, $row->description, $field_data, '', '', '');

            } elseif ($row->type_id == "4") { // Date

                echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, $field_data, '10', '', '', '', '');

            } elseif ($row->type_id == "5") { // Time Stamp

                echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, $field_data, '19', '', '', '', '');

            }

        }

    }

}

echo $form->showInputHidden('did', $did);
echo $form->showInputHidden('new_domain', $new_domain);
echo $form->showInputHidden('new_tld', $new_tld);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');

$dwaccounts = new DomainMOD\DwAccounts();
$dw_has_accounts = $dwaccounts->checkForAccounts($new_domain);

$dwzones = new DomainMOD\DwZones();
$dw_has_zones = $dwzones->checkForZones($new_domain);

if ($dw_has_accounts === 1 || $dw_has_zones === 1) { ?>

    <BR><BR><h3>Data Warehouse Information for <?php echo htmlentities($new_domain, ENT_QUOTES, 'UTF-8'); ?></h3><?php

}

if ($dw_has_accounts === 1) { ?>

    <h4>Accounts</h4><?php

    $stmt = $pdo->prepare("
        SELECT s.id, s.name
        FROM dw_accounts AS a, dw_servers AS s
        WHERE a.server_id = s.id
          AND a.domain = :new_domain
        ORDER BY s.name ASC, a.unix_startdate DESC");
    $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

    $dwdisplay = new DomainMOD\DwDisplay(); ?>

    <table id="<?php echo $slug; ?>-account" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
        </tr>
        </thead>
        <tbody><?php

            foreach ($result as $row) { ?>

                <tr>
                    <td></td>
                    <td><?php echo $row->name; ?></td>

                    <?php echo $dwdisplay->account($row->id, $new_domain); ?>

                </tr><?php

            } ?>

        </tbody>
    </table><?php

}

if ($dw_has_zones === 1) { ?>

    <h4>DNS Zones & Records</h4><?php

    $stmt = $pdo->prepare("
        SELECT s.id AS dw_server_id, s.name
        FROM dw_dns_zones AS z, dw_servers AS s
        WHERE z.server_id = s.id
          AND z.domain = :new_domain
        ORDER BY s.name, z.zonefile, z.domain");
    $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

    $dwdisplay = new DomainMOD\DwDisplay(); ?>

    <table id="<?php echo $slug; ?>-zone" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th></th>
            <th></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) { ?>

            <tr>
                <td></td>
                <td valign="top"><?php echo $row->name; ?></td>
                <td>
                    <?php echo $dwdisplay->zone($row->dw_server_id, $new_domain); ?>
                </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php
}
?>
<BR><a href="edit.php?did=<?php echo urlencode($did); ?>&del=1">DELETE THIS DOMAIN</a>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
