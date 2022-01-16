<?php
/**
 * /ssl/edit.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/ssl/edit.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$assets = new DomainMOD\Assets();

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/ssl-edit.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) $_GET['del'];

$sslcid = (int) $_REQUEST['sslcid'];
$new_domain_id = (int) $_POST['new_domain_id'];
$new_name = $sanitize->text($_POST['new_name']);
$new_type_id = (int) $_POST['new_type_id'];
$new_ip_id = (int) $_POST['new_ip_id'];
$new_cat_id = (int) $_POST['new_cat_id'];
$new_expiry_date = $_POST['datepick'];
$new_account_id = (int) $_POST['new_account_id'];
$new_active = (int) $_POST['new_active'];
$new_notes = $sanitize->text($_POST['new_notes']);

// Custom Fields
$result = $pdo->query("
    SELECT field_name
    FROM ssl_cert_fields
    ORDER BY `name`")->fetchAll();

if ($result) {

    $count = 0;

    foreach ($result as $row) {

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

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_domain_id !== 0 && $new_account_id !== 0 &&
        $new_type_id !== 0 && $new_ip_id !== 0 && $new_cat_id !== 0
    ) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT ssl_provider_id, owner_id
                FROM ssl_accounts
                WHERE id = :new_account_id");
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if ($result) {

                foreach ($result as $row) {

                    $new_ssl_provider_id = $result->ssl_provider_id;
                    $new_owner_id = $result->owner_id;

                }

            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM ssl_fees
                WHERE ssl_provider_id = :new_ssl_provider_id
                  AND type_id = :new_type_id");
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchColumn();

            if (!$result) {

                $temp_fee_id = "0";
                $temp_fee_fixed = "0";

            } else {

                $temp_fee_id = $result;
                $temp_fee_fixed = "1";

            }

            $stmt = $pdo->prepare("
                SELECT (renewal_fee + misc_fee) AS total_cost
                FROM ssl_fees
                WHERE ssl_provider_id = :new_ssl_provider_id
                  AND type_id = :new_type_id");
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->execute();
            $new_total_cost = $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                UPDATE ssl_certs
                SET owner_id = :new_owner_id,
                    ssl_provider_id = :new_ssl_provider_id,
                    account_id = :new_account_id,
                    domain_id = :new_domain_id,
                    `name` = :new_name,
                    type_id = :new_type_id,
                    ip_id = :new_ip_id,
                    cat_id = :new_cat_id,
                    expiry_date = :new_expiry_date,
                    fee_id = :temp_fee_id,
                    total_cost = :new_total_cost,
                    notes = :new_notes,
                    active = :new_active,
                    fee_fixed = :temp_fee_fixed,
                    update_time = :timestamp
                WHERE id = :sslcid");
            $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
            $stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
            $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->bindValue('new_ip_id', $new_ip_id, PDO::PARAM_INT);
            $stmt->bindValue('new_cat_id', $new_cat_id, PDO::PARAM_INT);
            $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
            $stmt->bindValue('temp_fee_id', $temp_fee_id, PDO::PARAM_INT);
            $stmt->bindValue('new_total_cost', strval($new_total_cost), PDO::PARAM_STR);
            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
            $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
            $stmt->bindValue('temp_fee_fixed', $temp_fee_fixed, PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
            $stmt->execute();

            $result = $pdo->query("
                SELECT field_name
                FROM ssl_cert_fields
                ORDER BY `name`")->fetchAll();

            if ($result) {

                $count = 0;

                foreach ($result as $row) {

                    $field_array[$count] = $row->field_name;
                    $count++;

                }

                foreach ($field_array as $field) {

                    $full_field = "new_" . $field;

                    $stmt = $pdo->prepare("
                        UPDATE ssl_cert_field_data
                        SET `" . $field . "` = :full_field,
                            update_time = :timestamp
                        WHERE ssl_id = :sslcid");
                    $stmt->bindValue('full_field', ${$full_field}, PDO::PARAM_STR);
                    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                    $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
                    $stmt->execute();

                }

            }

            $queryB = new DomainMOD\QueryBuild();

            $sql = $queryB->missingFees('ssl_certs');
            $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($sql);

            $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('SSL Certificate %s updated'), $new_name) . '<BR>';

            header('Location: edit.php?sslcid=' . $sslcid);
            exit;

        } catch (Exception $e) {

            $pdo->rollback();

            $log_message = 'Unable to update SSL certificate';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    } else {

        if ($new_name == "") {
            $_SESSION['s_message_danger'] .= _('Enter the SSL certificate name') . '<BR>';
        }
        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_message_danger'] .= _('The expiry date you entered is invalid') . '<BR>';
        }

        if ($new_domain_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the domain') . '<BR>';

        }

        if ($new_account_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the SSL Provider Account') . '<BR>';

        }

        if ($new_type_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the SSL Type') . '<BR>';

        }

        if ($new_ip_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the IP Address') . '<BR>';

        }

        if ($new_cat_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the Category') . '<BR>';

        }

    }

} else {

    $stmt = $pdo->prepare("
         SELECT sslc.domain_id, sslc.name, sslc.expiry_date, sslc.notes, sslc.active, sslpa.id AS account_id, sslcf.id AS type_id, ip.id AS ip_id, cat.id AS cat_id
         FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat
         WHERE sslc.account_id = sslpa.id
           AND sslc.type_id = sslcf.id
           AND sslc.ip_id = ip.id
           AND sslc.cat_id = cat.id
           AND sslc.id = :sslcid");
    $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_domain_id = $result->domain_id;
        $new_name = $result->name;
        $new_type_id = $result->type_id;
        $new_ip_id = $result->ip_id;
        $new_cat_id = $result->cat_id;
        $new_expiry_date = $result->expiry_date;
        $new_notes = $result->notes;
        $new_active = $result->active;
        $new_account_id = $result->account_id;

    }

}

if ($del === 1) {

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            DELETE FROM ssl_certs
            WHERE id = :sslcid");
        $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            DELETE FROM ssl_cert_field_data
            WHERE ssl_id = :sslcid");
        $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
        $stmt->execute();

        $temp_type = $assets->getSslType($new_type_id);

        $system->checkExistingAssets();

        $pdo->commit();

        $_SESSION['s_message_success'] .= sprintf(_('SSL Certificate %s (%s) deleted'), $new_name, $temp_type) . '<BR>';

        header("Location: index.php");
        exit;

    } catch (Exception $e) {

        $pdo->rollback();

        $log_message = 'Unable to delete SSL certificate';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-picker-head.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_name', _('Host') . ' / Label (100)', '', $unsanitize->text($new_name), '100', '', '1', '', '');
echo $form->showInputText('datepick', _('Expiry Date') . ' (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

$stmt = $pdo->prepare("
    SELECT id, domain
    FROM domains
    WHERE (active NOT IN ('0', '10') OR id = :new_domain_id)
    ORDER BY domain");
$stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_domain_id', _('Domain'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->domain, $new_domain_id);

    }

    echo $form->showDropdownBottom('');

}

$result = $pdo->query("
    SELECT sslpa.id, sslpa.username, o.name AS o_name, sslp.name AS sslp_name
    FROM ssl_accounts AS sslpa, owners AS o, ssl_providers AS sslp
    WHERE sslpa.owner_id = o.id
      AND sslpa.ssl_provider_id = sslp.id
    ORDER BY sslp_name ASC, o_name ASC, sslpa.username ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_account_id', _('SSL Provider Account'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->sslp_name . ', ' . $row->o_name . ' (' . $row->username . ')', $new_account_id);

    }

    echo $form->showDropdownBottom('');

}

$result = $pdo->query("
    SELECT id, type
    FROM ssl_cert_types
    ORDER BY type ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_type_id', _('Certificate Type'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->type, $new_type_id);

    }

    echo $form->showDropdownBottom('');
}

$result = $pdo->query("
    SELECT id, ip, `name`
    FROM ip_addresses
    ORDER BY `name`, ip")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $new_ip_id);

    }

    echo $form->showDropdownBottom('');
}

$result = $pdo->query("
    SELECT id, `name`
    FROM categories
    ORDER BY `name`")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_cat_id', _('Category'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_cat_id);

    }

    echo $form->showDropdownBottom('');

}

echo $form->showDropdownTop('new_active', _('Certificate Status'), '', '', '');
echo $form->showDropdownOption('1', _('Active'), $new_active);
echo $form->showDropdownOption('5', _('Pending (Registration)'), $new_active);
echo $form->showDropdownOption('3', _('Pending (Renewal)'), $new_active);
echo $form->showDropdownOption('4', _('Pending (Other)'), $new_active);
echo $form->showDropdownOption('0', _('Expired'), $new_active);
echo $form->showDropdownBottom('');

if ($new_notes != '') {
    $subtext = '[<a target="_blank" href="notes.php?sslcid=' . $sslcid . '">' . strtolower(_('View Full Notes')) . '</a>]';
} else {
    $subtext = '';
}
echo $form->showInputTextarea('new_notes', _('Notes'), $subtext, $unsanitize->text($new_notes), '', '', '');

$result = $pdo->query("
    SELECT field_name
    FROM ssl_cert_fields
    ORDER BY type_id, `name`")->fetchAll();

if ($result) { ?>

    <h3><?php echo _('Custom Fields'); ?></h3><?php

    $count = 0;

    foreach ($result as $row) {

        $field_array[$count] = $row->field_name;
        $count++;

    }

    foreach ($field_array as $field) {

        $result = $pdo->query("
            SELECT sf.name, sf.field_name, sf.type_id, sf.description
            FROM ssl_cert_fields AS sf, custom_field_types AS cft
            WHERE sf.type_id = cft.id
              AND sf.field_name = '" . $field . "'")->fetchAll();

        foreach ($result as $row) {

            if (${'new_' . $field}) {

                $field_data = ${'new_' . $field};

            } else {

                $stmt = $pdo->prepare("
                    SELECT " . $row->field_name . "
                    FROM ssl_cert_field_data
                    WHERE ssl_id = :sslcid");
                $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
                $stmt->execute();
                $result_data = $stmt->fetch();
                $stmt->closeCursor();
                $field_data = $result_data->{$row->field_name};

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

            } elseif ($row->type_id == "6") { // URL

                echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, $field_data, '255', '', '', '', '');

            }

        }

    }

}

echo $form->showInputHidden('sslcid', $sslcid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$layout->deleteButton(_('SSL Certificate'), $new_name, 'edit.php?sslcid=' . $sslcid . '&del=1');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
</body>
</html>
