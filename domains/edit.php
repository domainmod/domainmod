<?php
/**
 * /domains/edit.php
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
$log = new DomainMOD\Log('/domains/edit.php');
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/domains-edit.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$did = (int) $_REQUEST['did'];

$del = (int) $_GET['del'];

$new_domain = $sanitize->text($_POST['new_domain']);
$new_tld = $_POST['new_tld'];
$new_expiry_date = $_POST['datepick'];
$new_function = $sanitize->text($_POST['new_function']);
$new_cat_id = (int) $_POST['new_cat_id'];
$new_dns_id = (int) $_POST['new_dns_id'];
$new_ip_id = (int) $_POST['new_ip_id'];
$new_hosting_id = (int) $_POST['new_hosting_id'];
$new_account_id = (int) $_POST['new_account_id'];
$new_autorenew = (int) $_POST['new_autorenew'];
$new_privacy = (int) $_POST['new_privacy'];
$new_active = (int) $_POST['new_active'];
$new_notes = $sanitize->text($_POST['new_notes']);

// Custom Fields
$result = $pdo->query("
    SELECT field_name
    FROM domain_fields
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

    if ($date->checkDateFormat($new_expiry_date) && $new_cat_id !== 0 && $new_dns_id !== 0 && $new_ip_id !== 0 &&
        $new_hosting_id !== 0 && $new_account_id !== 0
    ) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT registrar_id, owner_id
                FROM registrar_accounts
                WHERE id = :new_account_id");
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

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

            if ($new_privacy === 1) {

                $fee_string = "renewal_fee + privacy_fee + misc_fee";

            } else {

                $fee_string = "renewal_fee + misc_fee";

            }

            $stmt = $pdo->prepare("
                SELECT (" . $fee_string . ")
                FROM fees
                WHERE registrar_id = :new_registrar_id
                  AND tld = :new_tld");
            $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
            $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
            $stmt->execute();
            $new_total_cost = $stmt->fetchColumn();

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

            $result = $pdo->query("
                SELECT field_name
                FROM domain_fields
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
                        UPDATE domain_field_data
                        SET `" . $field . "` = :full_field,
                            update_time = :timestamp
                        WHERE domain_id = :did");
                    $stmt->bindValue('full_field', ${$full_field}, PDO::PARAM_STR);
                    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                    $stmt->bindValue('did', $did, PDO::PARAM_INT);
                    $stmt->execute();

                }

            }

            $maint->updateSegments();

            $queryB = new DomainMOD\QueryBuild();

            $sql = $queryB->missingFees('domains');
            $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

            $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('Domain %s updated'), $new_domain);

            header('Location: edit.php?did=' . $did);
            exit;

        } catch (Exception $e) {

            $pdo->rollback();

            $log_message = 'Unable to edit domain';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    } else {

        if (!$date->checkDateFormat($new_expiry_date)) {
            $_SESSION['s_message_danger'] .= _('The expiry date you entered is invalid') . '<BR>';
        }

        if ($new_account_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the Registrar Account') . '<BR>';

        }

        if ($new_dns_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the DNS Profile') . '<BR>';

        }

        if ($new_ip_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the IP Address') . '<BR>';

        }

        if ($new_hosting_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the Web Host') . '<BR>';

        }

        if ($new_cat_id === 0) {

            $_SESSION['s_message_danger'] .= _('Choose the Category') . '<BR>';

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
    $stmt->closeCursor();

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

if ($del === 1) {

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

        $_SESSION['s_message_danger'] .= _('This Domain has SSL Certificates associated with it and cannot be deleted') . '<BR>';

    } else {

        try {

            $pdo->beginTransaction();

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

            $maint->updateSegments();

            $system->checkExistingAssets();

            $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('Domain %s deleted', $new_domain)) . '<BR>';

            header("Location: ../domains/index.php");
            exit;

        } catch (Exception $e) {

            $pdo->rollback();

            $log_message = 'Unable to delete domain';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

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
echo '<strong>' . _('Domain') . '</strong><BR>';
echo htmlentities($new_domain, ENT_QUOTES, 'UTF-8') . '<BR><BR>';
echo $form->showInputText('new_function', _('Function') . ' (255)', '', $unsanitize->text($new_function), '255', '', '', '', '');
echo $form->showInputText('datepick', _('Expiry Date') . ' (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

$result = $pdo->query("
    SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
    FROM registrar_accounts AS ra, owners AS o, registrars AS r
    WHERE ra.owner_id = o.id
      AND ra.registrar_id = r.id
    ORDER BY r_name ASC, o_name ASC, ra.username ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_account_id', _('Registrar Account'), '', '1', '');

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

    echo $form->showDropdownTop('new_dns_id', _('DNS Profile'), '', '1', '');

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

    echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '1', '');

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

    echo $form->showDropdownTop('new_hosting_id', _('Web Hosting Provider'), '', '1', '');

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

    echo $form->showDropdownTop('new_cat_id', _('Category'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_cat_id);

    }

    echo $form->showDropdownBottom('');

}

echo $form->showDropdownTop('new_active', _('Domain Status'), '', '', '');
echo $form->showDropdownOption('1', _('Active'), $new_active);
echo $form->showDropdownOption('5', _('Pending (Registration)'), $new_active);
echo $form->showDropdownOption('3', _('Pending (Renewal)'), $new_active);
echo $form->showDropdownOption('2', _('Pending (Transfer)'), $new_active);
echo $form->showDropdownOption('4', _('Pending (Other)'), $new_active);
echo $form->showDropdownOption('10', _('Sold'), $new_active);
echo $form->showDropdownOption('0', _('Expired'), $new_active);
echo $form->showDropdownBottom('');

echo $form->showSwitch(_('Auto Renewal') . '?', '', 'new_autorenew', $new_autorenew, '', '<BR><BR>');

echo $form->showSwitch(_('Privacy Enabled') . '?', '', 'new_privacy', $new_privacy, '', '<BR><BR>');

if ($new_notes != '') {
    $subtext = '[<a target="_blank" href="notes.php?did=' . $did . '">' . strtolower(_('View Full Notes')) . '</a>]';
} else {
    $subtext = '';
}
echo $form->showInputTextarea('new_notes', _('Notes'), $subtext, $unsanitize->text($new_notes), '', '', '');

$result = $pdo->query("
    SELECT field_name
    FROM domain_fields
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
            SELECT df.name, df.field_name, df.type_id, df.description
            FROM domain_fields AS df, custom_field_types AS cft
            WHERE df.type_id = cft.id
              AND df.field_name = '" . $field . "'")->fetchAll();

        foreach ($result as $row) {

            if (${'new_' . $field}) {

                $field_data = ${'new_' . $field};

            } else {

                $stmt = $pdo->prepare("
                    SELECT " . $row->field_name . "
                    FROM domain_field_data
                    WHERE domain_id = :did");
                $stmt->bindValue('did', $did, PDO::PARAM_INT);
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

echo $form->showInputHidden('did', $did);
echo $form->showInputHidden('new_domain', $new_domain);
echo $form->showInputHidden('new_tld', $new_tld);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

$dwaccounts = new DomainMOD\DwAccounts();
$dw_has_accounts = $dwaccounts->checkForAccounts($new_domain);

$dwzones = new DomainMOD\DwZones();
$dw_has_zones = $dwzones->checkForZones($new_domain);

if ($dw_has_accounts === 1 || $dw_has_zones === 1) { ?>

    <BR><BR><h3><?php echo sprintf(_('Data Warehouse Information for %s'), htmlentities($new_domain, ENT_QUOTES, 'UTF-8')); ?></h3><?php

}

if ($dw_has_accounts === 1) { ?>

    <h4><?php echo _('Accounts'); ?></h4><?php

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
        <th width="10px"></th>
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

    <h4><?php echo _('DNS Zones & Records'); ?></h4><?php

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

$layout->deleteButton(_('Domain'), $new_domain, 'edit.php?did=' . $did . '&del=1');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
</body>
</html>
