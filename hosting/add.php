<?php
/**
 * /hosting/add.php
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
$log = new DomainMOD\Log('/hosting/add.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/hosting-add.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

// Before the database update there are no hosting tables, so nothing can be added yet
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);

$new_name = is_string($_POST['new_name'] ?? null) ? $sanitize->text(trim($_POST['new_name'])) : '';
$new_account_id = DomainMOD\Hosting::postedInt($_POST['new_account_id'] ?? null);
$new_type_id = DomainMOD\Hosting::postedInt($_POST['new_type_id'] ?? null);
$new_billing_term = is_scalar($_POST['new_billing_term'] ?? null) ? (string) $_POST['new_billing_term'] : '';
$new_active = DomainMOD\Hosting::postedInt($_POST['new_active'] ?? null, -1);
$new_expiry_date = is_string($_POST['datepick'] ?? null) ? trim($_POST['datepick']) : '';
$new_renewal_day = DomainMOD\Hosting::postedInt($_POST['new_renewal_day'] ?? null, -1);
$new_autorenew = (int) ($_POST['new_autorenew'] ?? 0) === 1 ? 1 : 0;
$new_hostname = is_string($_POST['new_hostname'] ?? null) ? $sanitize->text(trim($_POST['new_hostname'])) : '';
// The IP address is optional, and 0 is None, so a malformed value (-1) is told apart from None and refused
$new_ip_id = DomainMOD\Hosting::postedInt($_POST['new_ip_id'] ?? null, -1);
$new_cat_id = DomainMOD\Hosting::postedInt($_POST['new_cat_id'] ?? null);
$new_notes = isset($_POST['new_notes']) && is_string($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

// Custom Fields. Their values are kept in their own array, keyed by field name, so a field named like one of the
// settings above (such as notes) can't overwrite it (see DomainMOD\CustomField::inputName()).
$custom_field = new DomainMOD\CustomField();
$custom_field_types = $hosting_available ? $custom_field->getFieldTypes('hosting_service_fields') : array();
$new_custom_fields = DomainMOD\CustomField::postedValues(array_keys($custom_field_types), $_POST);

if ($hosting_available && $_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    // A custom Date or Time Stamp that isn't a real date or time is marked under its field, and nothing is saved
    $custom_fields_valid = DomainMOD\CustomField::markInvalidValues($form, $custom_field_types, $new_custom_fields);

    // The day the service renews on: the one chosen, or the renewal date's own day for "Same as the Renewal Date" (0)
    $save_renewal_day = $new_renewal_day;
    if ($save_renewal_day === 0 && $date->checkDateFormat($new_expiry_date)) $save_renewal_day = (int) substr($new_expiry_date, 8, 2);

    $term_valid = DomainMOD\Hosting::validTerm($new_billing_term);
    $day_valid = $save_renewal_day >= 1 && $save_renewal_day <= 31;
    $status_valid = DomainMOD\Hosting::statusName($new_active) !== '';

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_account_id !== 0 && $new_type_id !== 0 &&
        $new_ip_id >= 0 && $new_cat_id !== 0 && $term_valid && $day_valid && $status_valid &&
        $custom_fields_valid
    ) {

        // Every write to hosting services holds the hosting lock, taken before the transaction (see DomainMOD\Hosting)
        if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

            $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';

        } else {

            try {

                $pdo->beginTransaction();

                // The add is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_USER, $_SESSION['s_user_id']);

                // The custom field list first, read in share mode, so a custom field can't be added or deleted until this
                // add ends (the Change History's lock order: the field definitions, then the hosting services)
                $field_array = array();
                foreach ($pdo->query("
                    SELECT field_name
                    FROM hosting_service_fields
                    ORDER BY `name`
                    LOCK IN SHARE MODE")->fetchAll() as $row) {

                    $field_array[] = $row->field_name;

                }

                // Each asset the form chose must still exist. The asset deletes hold the hosting lock too, so nothing
                // can be deleted between these checks and the insert. A missing one is marked on its field.
                $asset_errors = array();

                // The hosting account, with its owner and provider, which the service takes from it
                $stmt = $pdo->prepare("
                    SELECT hpa.hosting_id, hpa.owner_id
                    FROM hosting_provider_accounts AS hpa
                    JOIN hosting AS h ON h.id = hpa.hosting_id
                    JOIN owners AS o ON o.id = hpa.owner_id
                    WHERE hpa.id = :new_account_id");
                $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
                $stmt->execute();
                $provider_account = $stmt->fetch();
                $stmt->closeCursor();
                if (!$provider_account) $asset_errors['new_account_id'] = _('The hosting account you chose no longer exists. Please choose another one.');

                $asset_checks = array(
                    'new_type_id' => array('hosting_types', $new_type_id, _('The service type you chose no longer exists. Please choose another one.')),
                    'new_ip_id' => array('ip_addresses', $new_ip_id, _('The IP address you chose no longer exists. Please choose another one.')),
                    'new_cat_id' => array('categories', $new_cat_id, _('The category you chose no longer exists. Please choose another one.')));
                // An IP address of 0 is None, so there's nothing to check
                if ($new_ip_id === 0) unset($asset_checks['new_ip_id']);
                foreach ($asset_checks as $asset_field => $asset_check) {

                    $stmt = $pdo->prepare("SELECT id FROM " . $asset_check[0] . " WHERE id = :id");
                    $stmt->bindValue('id', $asset_check[1], PDO::PARAM_INT);
                    $stmt->execute();
                    if (!$stmt->fetchColumn()) $asset_errors[$asset_field] = $asset_check[2];
                    $stmt->closeCursor();

                }

                if ($asset_errors) {

                    // Nothing is saved, and the form shows again with what was typed and the missing assets marked
                    if ($pdo->InTransaction()) $pdo->rollback();
                    foreach ($asset_errors as $asset_field => $asset_error) $form->addFieldError($asset_field, $asset_error);

                } else {

                    $new_hosting_id = (int) $provider_account->hosting_id;
                    $new_owner_id = (int) $provider_account->owner_id;

                    // The fee for the provider, service type, and billing term, or none (the service is then saved
                    // with no cost, and the list shows "No fee" until one is added)
                    $stmt = $pdo->prepare("
                        SELECT id, (renewal_fee + misc_fee) AS total_cost
                        FROM hosting_fees
                        WHERE hosting_id = :new_hosting_id
                          AND type_id = :new_type_id
                          AND billing_term = :new_billing_term");
                    $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_billing_term', (int) $new_billing_term, PDO::PARAM_INT);
                    $stmt->execute();
                    $fee = $stmt->fetch();
                    $stmt->closeCursor();

                    $new_fee_id = $fee ? (int) $fee->id : 0;
                    $new_total_cost = $fee ? (string) $fee->total_cost : '0';
                    $new_fee_fixed = $fee ? 1 : 0;

                    $stmt = $pdo->prepare("
                        INSERT INTO hosting_services
                        (owner_id, hosting_id, account_id, type_id, ip_id, cat_id, `name`, hostname, billing_term,
                         renewal_day, expiry_date, autorenew, fee_id, total_cost, fee_fixed, notes, active, created_by,
                         insert_time)
                        VALUES
                        (:new_owner_id, :new_hosting_id, :new_account_id, :new_type_id, :new_ip_id, :new_cat_id, :new_name,
                         :new_hostname, :new_billing_term, :new_renewal_day, :new_expiry_date, :new_autorenew, :new_fee_id,
                         :new_total_cost, :new_fee_fixed, :new_notes, :new_active, :user_id, :timestamp)");
                    $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_ip_id', $new_ip_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_cat_id', $new_cat_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
                    $stmt->bindValue('new_hostname', $new_hostname, PDO::PARAM_STR);
                    $stmt->bindValue('new_billing_term', (int) $new_billing_term, PDO::PARAM_INT);
                    $stmt->bindValue('new_renewal_day', $save_renewal_day, PDO::PARAM_INT);
                    $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
                    $stmt->bindValue('new_autorenew', $new_autorenew, PDO::PARAM_INT);
                    $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_total_cost', $new_total_cost, PDO::PARAM_STR);
                    $stmt->bindValue('new_fee_fixed', $new_fee_fixed, PDO::PARAM_INT);
                    $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                    $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
                    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
                    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                    $stmt->execute();

                    $temp_service_id = (int) $pdo->lastInsertId('id');
                    $history_op->created('hosting', $temp_service_id);

                    // Every hosting service has one custom field data row, which the list joins, whether or not any
                    // custom fields exist
                    $stmt = $pdo->prepare("
                        INSERT INTO hosting_service_field_data
                        (service_id, insert_time)
                        VALUES
                        (:temp_service_id, :timestamp)");
                    $stmt->bindValue('temp_service_id', $temp_service_id, PDO::PARAM_INT);
                    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                    $stmt->execute();

                    foreach ($field_array as $field) {

                        $stmt = $pdo->prepare("
                            UPDATE hosting_service_field_data
                            SET `" . $field . "` = :full_field
                            WHERE service_id = :temp_service_id");
                        $stmt->bindValue('full_field', $new_custom_fields[$field] ?? '', PDO::PARAM_STR);
                        $stmt->bindValue('temp_service_id', $temp_service_id, PDO::PARAM_INT);
                        $stmt->execute();

                    }

                    $_SESSION['s_missing_hosting_fees'] = $system->checkForRows((new DomainMOD\QueryBuild())->missingFees('hosting_services'));

                    $system->checkExistingAssets();

                    $history_op->finish();

                    if ($pdo->InTransaction()) $pdo->commit();

                    // The hosting service links to its edit page, and the form stays here for the next one
                    $_SESSION['s_message_success'] .= sprintf(_('Hosting Service %s added'), '<a href="edit.php?hsid=' . $temp_service_id . '">' . $layout->ltr($unsanitize->text($new_name)) . '</a>') . '<BR>';

                    // A hosting service added without a fee has no cost until its fee is added, so the add says so
                    // (DomainMOD\FeeStatus)
                    $fee_status = new DomainMOD\FeeStatus();
                    $fee_row = $fee_status->hostingFee($temp_service_id);
                    if ($fee_row && !$fee_status->hasFee($fee_row)) $_SESSION['s_message_warning'] .= $fee_status->hostingWarning($fee_row, ($_SESSION['s_read_only'] ?? '') != '1') . '<BR>';

                }

            } catch (Exception $e) {

                if ($pdo->InTransaction()) $pdo->rollback();

                // Another request changed the same rows at the same moment: nothing was saved, and the form shows again
                // with what was typed (see domainmod_restore_is_conflict())
                if (domainmod_restore_is_conflict($e)) {
                    $_SESSION['s_message_danger'] .= domainmod_restore_conflict_form_message() . '<BR>';
                } else {

                    $log_message = 'Unable to add hosting service';
                    $log_extra = array('Error' => $e);
                    $log->critical($log_message, $log_extra);

                    $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                    throw $e;

                }

            } finally {

                DomainMOD\Hosting::unlock($pdo);

            }

        }

    } else {

        if ($new_name == "") {
            $form->addFieldError('new_name', _("Enter the hosting service's name"));
        }
        if (!$date->checkDateFormat($new_expiry_date)) {
            $form->addFieldError('datepick', _('The renewal date you entered is invalid'));
        }
        if ($new_account_id === 0) {
            $form->addFieldError('new_account_id', _('Choose the Hosting Account'));
        }
        if ($new_type_id === 0) {
            $form->addFieldError('new_type_id', _('Choose the Service Type'));
        }
        if (!$term_valid) {
            $form->addFieldError('new_billing_term', _('Choose the Billing Term'));
        }
        if (!$day_valid && $date->checkDateFormat($new_expiry_date)) {
            $form->addFieldError('new_renewal_day', _('Choose the day of the month the service renews on'));
        }
        if (!$status_valid) {
            $form->addFieldError('new_active', _('Choose the Status'));
        }
        if ($new_ip_id < 0) {
            $form->addFieldError('new_ip_id', _('The IP address you chose no longer exists. Please choose another one.'));
        }
        if ($new_cat_id === 0) {
            $form->addFieldError('new_cat_id', _('Choose the Category'));
        }

    }

} else {

    // The form starts as Active, renewing yearly
    $new_active = DomainMOD\Hosting::ACTIVE;
    $new_billing_term = '12';
    $new_renewal_day = 0;

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
if (!$hosting_available) {

    echo '<p>' . htmlspecialchars(DomainMOD\Hosting::unavailableMessage(), ENT_QUOTES, 'UTF-8') . '</p>';

} else {

    $is_post = $_SERVER['REQUEST_METHOD'] == 'POST';

    if ($new_expiry_date == '') $new_expiry_date = $time->userDatePlusYears(1);

    /*
     * The hosting account the form starts on: the default hosting account when it still exists, whichever provider it's
     * at, otherwise the first hosting account at the default hosting provider (default_host), and otherwise none,
     * leaving the user to pick
     */
    $provider_accounts = $pdo->query("
        SELECT hpa.id, hpa.hosting_id, hpa.username, o.name AS o_name, h.name AS h_name
        FROM hosting_provider_accounts AS hpa, owners AS o, hosting AS h
        WHERE hpa.owner_id = o.id
          AND hpa.hosting_id = h.id
        ORDER BY h_name ASC, o_name ASC, hpa.username ASC")->fetchAll();

    if ($is_post) {

        $account_to_compare = $new_account_id;

    } else {

        $account_to_compare = 0;
        $default_host = (int) ($_SESSION['s_default_host'] ?? 0);
        $default_account = (int) ($_SESSION['s_default_hosting_provider_account'] ?? 0);

        foreach ($provider_accounts as $row) {

            if ($default_account !== 0 && (int) $row->id === $default_account) {
                $account_to_compare = $default_account;
                break;
            }
            if ($account_to_compare === 0 && (int) $row->hosting_id === $default_host) $account_to_compare = (int) $row->id;

        }

    }

    echo $form->showFormTop('');
    // The fields in titled cards, the same way as the edit hosting service page. Each card's fields are built first, so
    // a card with nothing in it is left out rather than drawn empty.
    ob_start();
    echo $form->showInputText('new_name', _('Name') . ' (100)', '', $unsanitize->text($new_name), '100', '', '1', '', '', '1');

    // The service's owner and provider come from its account (there's no owner menu of its own)
    echo $form->showDropdownTop('new_account_id', _('Hosting Account'), _('The owner comes from the account.'), '1', '');
    echo $form->showDropdownOption('0', _('Choose the Hosting Account'), $account_to_compare);
    foreach ($provider_accounts as $row) {

        echo $form->showDropdownOption($row->id, $row->h_name . ', ' . $row->o_name . ' (' . $row->username . ')', $account_to_compare);

    }
    echo $form->showDropdownBottom('', 'new_account_id');

    $type_to_compare = $is_post ? $new_type_id : ($_SESSION['s_default_hosting_type'] ?? 0);
    $result = $pdo->query("
        SELECT id, type
        FROM hosting_types
        ORDER BY type ASC")->fetchAll();

    echo $form->showDropdownTop('new_type_id', _('Service Type'), '', '1', '');
    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->type, $type_to_compare);

    }
    echo $form->showDropdownBottom('', 'new_type_id');

    echo $form->showDropdownTop('new_billing_term', _('Billing Term'), '', '1', '');
    foreach (DomainMOD\Hosting::termOptions() as $term => $term_label) {

        echo $form->showDropdownOption($term, $term_label, $new_billing_term);

    }
    echo $form->showDropdownBottom('', 'new_billing_term');

    echo $form->showDropdownTop('new_active', _('Status'), '', '', '');
    foreach (DomainMOD\Hosting::statusOptions() as $status => $status_label) {

        echo $form->showDropdownOption($status, $status_label, $new_active);

    }
    echo $form->showDropdownBottom('', 'new_active');

    echo $form->showInputText('datepick', _('Renewal Date') . ' (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

    echo $form->showDropdownTop('new_renewal_day', _('Renews On Day'), _('If a shorter month moves the renewal date earlier, the next renewal goes back to the day chosen here.'), '1', '');
    // TRANSLATORS: The Renews On Day menu's first choice, which takes the day from the renewal date.
    echo $form->showDropdownOption('0', _('Same as the Renewal Date'), $new_renewal_day);
    for ($day = 1; $day <= 31; $day++) {

        echo $form->showDropdownOption($day, $day, $new_renewal_day);

    }
    echo $form->showDropdownBottom('', 'new_renewal_day');

    // The help is fixed text, so it's drawn whatever the switch's value, and is there when someone turns it on
    // TRANSLATORS: %s is the number of days an auto-renewing hosting service waits for its renewal to be confirmed (14).
    $autorenew_help = sprintf(ngettext('With auto-renew on, an active service whose renewal date has passed waits %s day for you to confirm the renewal before it shows as overdue.', 'With auto-renew on, an active service whose renewal date has passed waits %s days for you to confirm the renewal before it shows as overdue.', DomainMOD\Hosting::CONFIRM_DAYS), DomainMOD\Hosting::CONFIRM_DAYS);
    echo $form->showSwitch(_('Auto-Renewal') . '?', $autorenew_help, 'new_autorenew', $new_autorenew, '<div class="mb-3">', '</div>');

    echo $layout->formSection(_('Details'), 'details', ob_get_clean());

    ob_start();
    echo $form->showInputText('new_hostname', _('Hostname') . ' (255)', _('Such as web-01.example.com'), $unsanitize->text($new_hostname), '255', '', '', '', '', '', 'ltr');

    // The IP address is optional: None first, and the menu is always drawn, even with no IP addresses at all
    $to_compare = $is_post ? $new_ip_id : ($_SESSION['s_default_ip_address_hosting'] ?? 0);
    $result = $pdo->query("
        SELECT id, ip, `name`
        FROM ip_addresses
        ORDER BY `name`, ip")->fetchAll();

    echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '', '');
    // TRANSLATORS: The IP Address menu's choice for a hosting service with no IP address.
    echo $form->showDropdownOption('0', _('None'), $to_compare);
    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $to_compare);

    }
    echo $form->showDropdownBottom('', 'new_ip_id');

    $to_compare = $is_post ? $new_cat_id : ($_SESSION['s_default_category_hosting'] ?? 0);
    $result = $pdo->query("
        SELECT id, `name`
        FROM categories
        ORDER BY `name`")->fetchAll();

    echo $form->showDropdownTop('new_cat_id', _('Category'), '', '1', '');
    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $to_compare);

    }
    echo $form->showDropdownBottom('', 'new_cat_id');

    echo $layout->formSection(_('Where It Runs'), 'where-it-runs', ob_get_clean());

    /*
     * The fee comes from the account's provider, the type, and the billing term when the service is saved. A service
     * with no matching fee is still added, with no cost until its fee is added, so the card says so before saving, with
     * a link to add the fee for the choices the form starts on (once an account is chosen).
     */
    ob_start();
    echo '<p class="domainmod-span-all">' . _('The fee comes from the provider, type, and billing term. If no fee matches them yet, the service is saved without a cost until you add one.') . '</p>';
    $fee_provider_id = 0;
    foreach ($provider_accounts as $row) {

        if ((int) $row->id === (int) $account_to_compare) $fee_provider_id = (int) $row->hosting_id;

    }
    if ($fee_provider_id !== 0) {

        $fee_link = (object) array('provider_id' => $fee_provider_id, 'type_id' => (int) $type_to_compare, 'billing_term' => (int) $new_billing_term);
        echo '<div class="domainmod-span-all mb-3">' . $layout->linkButton(WEB_ROOT . (new DomainMOD\FeeStatus())->addHostingFeeUrl($fee_link), _('Add Fee'), 'secondary', 'bi-plus-lg') . '</div>';

    }
    echo $layout->formSection(_('Fee'), 'fee', ob_get_clean());

    ob_start();
    echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '<div class="domainmod-span-all">', '</div>');
    echo $layout->formSection(_('Notes'), 'notes', ob_get_clean());

    ob_start();
    $result = $pdo->query("
        SELECT field_name, `name`, type_id, description
        FROM hosting_service_fields
        ORDER BY type_id ASC, `name`")->fetchAll();

    foreach ($result as $row) {

        $field = $row->field_name;
        $input_name = DomainMOD\CustomField::inputName($row->field_name);

        if ($row->type_id == "1") { // Check Box

            echo $form->showCheckbox($input_name, '1', $row->name, $row->description, $new_custom_fields[$field] ?? '', '', '');

        } elseif ($row->type_id == "2") { // Text

            echo $form->showInputText($input_name, $row->name, $row->description, $new_custom_fields[$field] ?? '', '255', '', '', '', '');

        } elseif ($row->type_id == "3") { // Text Area

            echo $form->showInputTextarea($input_name, $row->name, $row->description, $new_custom_fields[$field] ?? '', '', '<div class="domainmod-span-all">', '</div>');

        } elseif ($row->type_id == "4") { // Date

            echo $form->showInputText($input_name, $row->name, $row->description, $new_custom_fields[$field] ?? '', '10', '', '', '', '', '', 'ltr');

        } elseif ($row->type_id == "5") { // Time Stamp

            echo $form->showInputText($input_name, $row->name, $row->description, $new_custom_fields[$field] ?? '', '19', '', '', '', '', '', 'ltr');

        } elseif ($row->type_id == "6") { // URL

            echo $form->showInputText($input_name, $row->name, $row->description, $new_custom_fields[$field] ?? '', '255', '', '', '', '', '', 'ltr');

        }

    }

    echo $layout->formSection(_('Custom Fields'), 'custom-fields', ob_get_clean());

    echo $form->showSubmitButton(_('Add Hosting Service'), '', '');
    echo $form->showFormBottom('');

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
</body>
</html>
