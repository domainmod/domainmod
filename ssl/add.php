<?php
/**
 * /ssl/add.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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
$log = new DomainMOD\Log('/ssl/add.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/ssl-add.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

$new_domain_id = (int) ($_POST['new_domain_id'] ?? 0);
$new_name = is_string($_POST['new_name'] ?? null) ? $sanitize->text(trim($_POST['new_name'])) : '';
$new_type_id = (int) ($_POST['new_type_id'] ?? 0);
$new_ip_id = (int) ($_POST['new_ip_id'] ?? 0);
$new_cat_id = (int) ($_POST['new_cat_id'] ?? 0);
$new_expiry_date = $_POST['datepick'] ?? '';
$new_account_id = (int) ($_POST['new_account_id'] ?? 0);
$new_active = (int) ($_POST['new_active'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

// Custom Fields. Their values are kept in their own array, keyed by field name, so a field named like one of the
// settings above (such as notes) can't overwrite it (see DomainMOD\CustomField::inputName()).
$custom_field = new DomainMOD\CustomField();
$custom_field_types = $custom_field->getFieldTypes('ssl_cert_fields');
$new_custom_fields = DomainMOD\CustomField::postedValues(array_keys($custom_field_types), $_POST);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    // A custom Date or Time Stamp that isn't a real date or time is marked under its field, and nothing is saved
    $custom_fields_valid = DomainMOD\CustomField::markInvalidValues($form, $custom_field_types, $new_custom_fields);

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_type_id !== 0 && $new_ip_id !== 0 &&
        $new_cat_id !== 0 && $new_account_id !== 0 && $custom_fields_valid
    ) {

        try {

            $pdo->beginTransaction();

            // The add is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_USER, $_SESSION['s_user_id']);

            // The domain is optional, and 0 is None. A chosen domain may have been deleted while the form was open, and
            // then nothing is saved. The read locks the domain row, so a domain delete running at the same moment waits
            // for this save, and its unlink then sees the new certificate. A negative id, which only a changed form can
            // send, is refused the same way.
            if ($new_domain_id !== 0) {

                $domain_exists = false;

                if ($new_domain_id > 0) {

                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM domains
                        WHERE id = :new_domain_id
                        FOR UPDATE");
                    $stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $domain_exists = $stmt->fetchColumn();
                    $stmt->closeCursor();

                }

                if (!$domain_exists) {

                    if ($pdo->InTransaction()) $pdo->rollback();
                    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The domain you chose no longer exists. Please choose another one.') . '<BR>';
                    header("Location: add.php");
                    exit;

                }

            }

            $stmt = $pdo->prepare("
                SELECT ssl_provider_id, owner_id
                FROM ssl_accounts
                WHERE id = :new_account_id");
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if (!$result) {

                // the account was deleted while the form was open (or the form was changed), so nothing is saved
                if ($pdo->InTransaction()) $pdo->rollback();
                $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The SSL provider account you chose no longer exists. Please choose another one.') . '<BR>';
                header("Location: add.php");
                exit;

            }

            $new_ssl_provider_id = $result->ssl_provider_id;
            $new_owner_id = $result->owner_id;

            $stmt = $pdo->prepare("
                SELECT id, (renewal_fee + misc_fee) AS total_cost
                FROM ssl_fees
                WHERE ssl_provider_id = :new_ssl_provider_id
                  AND type_id = :new_type_id");
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if ($result) {

                $new_fee_id = $result->id;
                $new_total_cost = $result->total_cost;

            } else {

                $new_fee_id = 0;
                $new_total_cost = 0;

            }

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
            $history_op->created('ssl', $temp_ssl_id);

            $stmt = $pdo->prepare("
                INSERT INTO ssl_cert_field_data
                (ssl_id, insert_time)
                VALUES
                (:temp_ssl_id, :timestamp)");
            $stmt->bindValue('temp_ssl_id', $temp_ssl_id, PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->execute();

            $result = $pdo->query("
                SELECT field_name
                FROM ssl_cert_fields
                ORDER BY `name`
                LOCK IN SHARE MODE")->fetchAll();

            if ($result) {

                $field_array = array();

                foreach ($result as $row) {

                    $field_array[] = $row->field_name;

                }

                foreach ($field_array as $field) {

                    $stmt = $pdo->prepare("
                        UPDATE ssl_cert_field_data
                        SET `" . $field . "` = :full_field
                        WHERE ssl_id = :temp_ssl_id");
                    $stmt->bindValue('full_field', $new_custom_fields[$field] ?? '', PDO::PARAM_STR);
                    $stmt->bindValue('temp_ssl_id', $temp_ssl_id, PDO::PARAM_INT);
                    $stmt->execute();

                }

            }

            $queryB = new DomainMOD\QueryBuild();
            $sql = $queryB->missingFees('ssl_certs');
            $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($sql);

            $system->checkExistingAssets();

            $history_op->finish();

            if ($pdo->InTransaction()) $pdo->commit();

            // The certificate links to its edit page, and the form stays here for the next one
            $_SESSION['s_message_success'] .= sprintf(_('SSL Certificate %s added'), '<a href="edit.php?sslcid=' . $temp_ssl_id . '">' . $layout->ltr($unsanitize->text($new_name)) . '</a>') . '<BR>';

            // A certificate added without a fee is hidden from the list, so the add says so (DomainMOD\FeeStatus)
            $fee_status = new DomainMOD\FeeStatus();
            $fee_row = $fee_status->sslFee($temp_ssl_id);
            if ($fee_row && !$fee_status->hasFee($fee_row)) $_SESSION['s_message_warning'] .= $fee_status->sslWarning($fee_row, true) . '<BR>';

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            // Another request changed the same rows at the same moment: nothing was saved, and the form shows again with
            // what was typed (see domainmod_restore_is_conflict())
            if (domainmod_restore_is_conflict($e)) {
                $_SESSION['s_message_danger'] .= domainmod_restore_conflict_form_message() . '<BR>';
            } else {

                $log_message = 'Unable to add SSL certificate';
                $log_extra = array('Error' => $e);
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                throw $e;

            }

        }

    } else {

        if ($new_name == "") {
            $form->addFieldError('new_name', _('Enter the SSL certificate\'s Host / Label'));
        }
        if (!$date->checkDateFormat($new_expiry_date)) {
            $form->addFieldError('datepick', _('The expiry date you entered is invalid'));
        }

        // A chosen domain that was deleted while the form was open isn't in the menu any more, so it's marked here
        // rather than shown again as None
        if ($new_domain_id !== 0) {

            $domain_exists = false;

            if ($new_domain_id > 0) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM domains
                    WHERE id = :new_domain_id");
                $stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
                $stmt->execute();
                $domain_exists = $stmt->fetchColumn();
                $stmt->closeCursor();

            }

            if (!$domain_exists) {

                $form->addFieldError('new_domain_id', _('The domain you chose no longer exists. Please choose another one.'));

            }

        }

        if ($new_account_id === 0) {

            $form->addFieldError('new_account_id', _('Choose the SSL Provider Account'));

        }

        if ($new_type_id === 0) {

            $form->addFieldError('new_type_id', _('Choose the SSL Type'));

        }

        if ($new_ip_id === 0) {

            $form->addFieldError('new_ip_id', _('Choose the IP Address'));

        }

        if ($new_cat_id === 0) {

            $form->addFieldError('new_cat_id', _('Choose the Category'));

        }

    }

} else {

    // Casting $new_active as int sets it to 0 on first load, which sets the default status to 'Expired'. The below
    // line sets the default to 'Active' instead.
    $new_active = 1;

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-picker-head.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
// The fields in titled cards, the same way as the edit SSL certificate page. Each card's fields are built first, so a
// card with nothing in it is left out rather than drawn empty. Moving a field to another card is a matter of moving its
// lines between these blocks.
ob_start();
echo $form->showInputText('new_name', _('Host') . ' / ' . _('Label') . ' (100)', '', $unsanitize->text($new_name), '100', '', '1', '', '', '1');

$stmt = $pdo->prepare("
    SELECT id, domain
    FROM domains
    WHERE (active NOT IN ('0', '10') OR id = :new_domain_id)
    ORDER BY domain");
$stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

echo $form->showDropdownTop('new_domain_id', _('Domain'), '', '', '');
// TRANSLATORS: The Domain menu's choice for an SSL certificate that isn't linked to a domain.
echo $form->showDropdownOption('0', _('None'), $new_domain_id);

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->domain, $new_domain_id);

}

echo $form->showDropdownBottom('', 'new_domain_id');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_cat_id;

} else {

    $to_compare = $_SESSION['s_system_default_category_ssl'];

}

$result = $pdo->query("
    SELECT id, `name`
    FROM categories
    ORDER BY `name`")->fetchAll();

echo $form->showDropdownTop('new_cat_id', _('Category'), '', '1', '');

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->name, $to_compare);

}
echo $form->showDropdownBottom('', 'new_cat_id');

echo $form->showDropdownTop('new_active', _('Certificate Status'), '', '', '');
echo $form->showDropdownOption('1', _('Active'), $new_active);
echo $form->showDropdownOption('5', _('Pending (Registration)'), $new_active);
echo $form->showDropdownOption('3', _('Pending (Renewal)'), $new_active);
echo $form->showDropdownOption('4', _('Pending (Other)'), $new_active);
echo $form->showDropdownOption('0', _('Expired'), $new_active);
echo $form->showDropdownBottom('');

echo $layout->formSection(_('Details'), 'details', ob_get_clean());

ob_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_account_id;

} else {

    $to_compare = $_SESSION['s_default_ssl_provider_account'];

}

$result = $pdo->query("
    SELECT sslpa.id, sslpa.username, o.name AS o_name, sslp.name AS sslp_name
    FROM ssl_accounts AS sslpa, owners AS o, ssl_providers AS sslp
    WHERE sslpa.owner_id = o.id
      AND sslpa.ssl_provider_id = sslp.id
    ORDER BY sslp_name ASC, o_name ASC, sslpa.username ASC")->fetchAll();

echo $form->showDropdownTop('new_account_id', _('SSL Provider Account'), '', '1', '');

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->sslp_name . ', ' . $row->o_name . ' (' . $row->username . ')', $to_compare);

}

echo $form->showDropdownBottom('', 'new_account_id');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_type_id;

} else {

    $to_compare = $_SESSION['s_default_ssl_type'];

}

$result = $pdo->query("
    SELECT id, type
    FROM ssl_cert_types
    ORDER BY type ASC")->fetchAll();

echo $form->showDropdownTop('new_type_id', _('Certificate Type'), '', '1', '');

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->type, $to_compare);

}

echo $form->showDropdownBottom('', 'new_type_id');

if ($new_expiry_date == '') {
    $new_expiry_date = $time->userDatePlusYears(1);
}
echo $form->showInputText('datepick', _('Expiry Date') . ' (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');
echo $layout->formSection(_('Certificate'), 'certificate', ob_get_clean());

ob_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_ip_id;

} else {

    $to_compare = $_SESSION['s_default_ip_address_ssl'];

}

$result = $pdo->query("
    SELECT id, ip, `name`
    FROM ip_addresses
    ORDER BY `name`, ip")->fetchAll();

echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '1', '');

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $to_compare);

}
echo $form->showDropdownBottom('', 'new_ip_id');

echo $layout->formSection(_('Hosting'), 'hosting', ob_get_clean());

ob_start();
$subtext = $subtext ?? '';
echo $form->showInputTextarea('new_notes', _('Notes'), $subtext, $unsanitize->text($new_notes), '', '<div class="domainmod-span-all">', '</div>');
echo $layout->formSection(_('Notes'), 'notes', ob_get_clean());

ob_start();
$result = $pdo->query("
    SELECT field_name
    FROM ssl_cert_fields
    ORDER BY type_id ASC, `name`")->fetchAll();

if ($result) {

    $field_array = array();

    foreach ($result as $row) {

        $field_array[] = $row->field_name;

    }

    foreach ($field_array as $field) {

        $stmt = $pdo->prepare("
            SELECT sf.name, sf.field_name, sf.type_id, sf.description
            FROM ssl_cert_fields AS sf, custom_field_types AS cft
            WHERE sf.type_id = cft.id
              AND sf.field_name = :field");
        $stmt->bindValue('field', $field, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                if ($row->type_id == "1") { // Check Box

                    echo $form->showCheckbox(DomainMOD\CustomField::inputName($row->field_name), '1', $row->name, $row->description, $new_custom_fields[$field] ?? '', '', '');

                } elseif ($row->type_id == "2") { // Text

                    echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$field] ?? '', '255', '', '', '', '');

                } elseif ($row->type_id == "3") { // Text Area

                    echo $form->showInputTextarea(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$field] ?? '', '', '<div class="domainmod-span-all">', '</div>');

                } elseif ($row->type_id == "4") { // Date

                    echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$field] ?? '', '10', '', '', '', '', '', 'ltr');

                } elseif ($row->type_id == "5") { // Time Stamp

                    echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$field] ?? '', '19', '', '', '', '', '', 'ltr');

                } elseif ($row->type_id == "6") { // URL

                    echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$field] ?? '', '255', '', '', '', '', '', 'ltr');

                }

            }

        }

    }

}

echo $layout->formSection(_('Custom Fields'), 'custom-fields', ob_get_clean());

echo $form->showSubmitButton(_('Add SSL Certificate'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
</body>
</html>
