<?php
/**
 * /domains/add.php
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
$log = new DomainMOD\Log('/domains/add.php');
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/domains-add.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

$new_domain = isset($_POST['new_domain']) ? $sanitize->text(DomainMOD\Domain::normalizeOrKeep($_POST['new_domain'])) : '';
$new_expiry_date = $_POST['datepick'] ?? '';
$new_function = isset($_POST['new_function']) ? $sanitize->text($_POST['new_function']) : '';
$new_cat_id = (int) ($_POST['new_cat_id'] ?? 0);
$new_dns_id = (int) ($_POST['new_dns_id'] ?? 0);
$new_ip_id = (int) ($_POST['new_ip_id'] ?? 0);
// The domain's optional hosting service (0 is None), once hosting is available. A malformed value is -1, refused below.
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);
$new_service_id = $hosting_available ? DomainMOD\Hosting::postedInt($_POST['new_service_id'] ?? '0', -1) : 0;
$new_account_id = (int) ($_POST['new_account_id'] ?? 0);
$new_autorenew = (int) ($_POST['new_autorenew'] ?? 0);
$new_privacy = (int) ($_POST['new_privacy'] ?? 0);
$new_active = (int) ($_POST['new_active'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

/*
 * Adding a domain from the Watch List (watch=ID, from a watched domain's "I own this now"): the entry is read by its id,
 * and its domain, known expiry date, and notes fill in the form when it's first shown. The id travels in a hidden field,
 * and an add of that entry's own domain is recorded with the Watch List as its source. Nothing is archived from the link
 * alone: the Watch List archives the entry once the domain is live here. While the Watch List is turned off in System
 * Settings, the link is ignored.
 */
$watch_id = (int) ($_POST['watch'] ?? ($_GET['watch'] ?? 0));
$watch_entry = null;
if ($watch_id > 0 && DomainMOD\WatchList::isEnabled()) {
    $watch_entry = (new DomainMOD\WatchCheck($pdo))->entry($watch_id);
    if ($watch_entry && $_SERVER['REQUEST_METHOD'] != 'POST') {
        $new_domain = $watch_entry['domain'];
        if (!DomainMOD\Time::isUnset($watch_entry['expiry_date'])) $new_expiry_date = $watch_entry['expiry_date'];
        $new_notes = $watch_entry['notes'];
    }
}

// Custom Fields. Their values are kept in their own array, keyed by field name, so a field named like one of the
// settings above (such as notes) can't overwrite it (see DomainMOD\CustomField::inputName()).
$custom_field = new DomainMOD\CustomField();
$custom_field_types = $custom_field->getFieldTypes('domain_fields');
$new_custom_fields = DomainMOD\CustomField::postedValues(array_keys($custom_field_types), $_POST);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();
    $domain = new DomainMOD\Domain();

    // A custom Date or Time Stamp that isn't a real date or time is marked under its field, and nothing is saved
    $custom_fields_valid = DomainMOD\CustomField::markInvalidValues($form, $custom_field_types, $new_custom_fields);

    if ($date->checkDateFormat($new_expiry_date) && $domain->checkFormat($new_domain) && $new_cat_id !== 0 &&
        $new_dns_id !== 0 && $new_ip_id !== 0 && $new_service_id >= 0 && $new_account_id !== 0 && $custom_fields_valid) {

        $stmt = $pdo->prepare("
            SELECT domain
            FROM domains
            WHERE domain = :new_domain
            LIMIT 1");
        $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if ($result) {

            $_SESSION['s_message_danger'] .= sprintf(_('This domain is already in %s.'), SOFTWARE_TITLE) . '<BR>';

        } elseif ($new_service_id !== 0 && !DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

            // A domain added with a hosting service holds the hosting lock, taken before the transaction, so the service
            // can't be deleted or cancelled between its check and the insert (see DomainMOD\Hosting)
            $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';

        } else {

            try {

                // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                $pdo->beginTransaction();

                // The hosting service must still exist and not be cancelled. Nothing is saved, and the form shows again
                // with the service marked, when it isn't.
                $service_error = DomainMOD\Hosting::serviceError($pdo, $new_service_id);
                if ($service_error !== '') {
                    $pdo->rollback();
                    throw new DomainMOD\HostingServiceRefused($service_error);
                }

                // The add is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory), with the
                // Watch List as its source when it's that watched domain's own name
                $from_watch = $watch_entry && (string) $watch_entry['domain'] === (string) $new_domain;
                $history_op = (new DomainMOD\ChangeHistory($pdo))->start($from_watch ? DomainMOD\ChangeHistory::SOURCE_WATCH_LIST
                    : DomainMOD\ChangeHistory::SOURCE_USER, $_SESSION['s_user_id'], $from_watch ? 'watch_move' : '');

                $tld = $domain->getTld($new_domain);

                $stmt = $pdo->prepare("
                    SELECT registrar_id, owner_id
                    FROM registrar_accounts
                    WHERE id = :new_account_id");
                $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch();
                $stmt->closeCursor();

                if (!$result) {

                    // the account was deleted while the form was open (or the form was changed), so nothing is saved
                    if ($pdo->InTransaction()) $pdo->rollback();
                    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The registrar account you chose no longer exists. Please choose another one.') . '<BR>';
                    header("Location: add.php");
                    exit;

                }

                $new_registrar_id = $result->registrar_id;
                $new_owner_id = $result->owner_id;

                // The domains whose cost updateDomainFee() recalculates with this one's, locked before the insert
                $fee_peers = $maint->captureFeePeers($history_op, $new_registrar_id, $tld);

                if ($new_privacy === 1) {

                    $fee_string = 'renewal_fee + privacy_fee + misc_fee';

                } else {

                    $fee_string = 'renewal_fee + misc_fee';

                }

                $stmt = $pdo->prepare("
                    SELECT id, (" . $fee_string . ") AS total_cost
                    FROM fees
                    WHERE registrar_id = :new_registrar_id
                      AND tld = :tld");
                $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
                $stmt->bindValue('tld', $tld, PDO::PARAM_STR);
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

                // Before the update, the old hosting_id column is still there, and is written as 0 so it doesn't take its
                // old default of 1 (a real provider on some installs). A stopped upgrade that has dropped it writes the
                // domain as the update does (see Hosting::legacyDomainColumn()).
                if (!DomainMOD\Hosting::legacyDomainColumn($pdo)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO domains
                        (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                         service_id, fee_id, total_cost, `function`, notes, autorenew, privacy, created_by,
                         active, insert_time)
                        VALUES
                        (:new_owner_id, :new_registrar_id, :new_account_id, :new_domain, :tld, :new_expiry_date, :new_cat_id,
                         :new_dns_id, :new_ip_id, :new_service_id, :new_fee_id, :new_total_cost, :new_function, :new_notes,
                         :new_autorenew, :new_privacy, :user_id, :new_active, :timestamp)");
                    $stmt->bindValue('new_service_id', $new_service_id, PDO::PARAM_INT);
                } else {
                    // Legacy: domains.hosting_id before 4.27.0
                    $stmt = $pdo->prepare("
                        INSERT INTO domains
                        (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                         hosting_id, fee_id, total_cost, `function`, notes, autorenew, privacy, created_by,
                         active, insert_time)
                        VALUES
                        (:new_owner_id, :new_registrar_id, :new_account_id, :new_domain, :tld, :new_expiry_date, :new_cat_id,
                         :new_dns_id, :new_ip_id, 0, :new_fee_id, :new_total_cost, :new_function, :new_notes,
                         :new_autorenew, :new_privacy, :user_id, :new_active, :timestamp)");
                }
                $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
                $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
                $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
                $stmt->bindValue('tld', $tld, PDO::PARAM_STR);
                $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
                $stmt->bindValue('new_cat_id', $new_cat_id, PDO::PARAM_INT);
                $stmt->bindValue('new_dns_id', $new_dns_id, PDO::PARAM_INT);
                $stmt->bindValue('new_ip_id', $new_ip_id, PDO::PARAM_INT);
                $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
                $stmt->bindValue('new_total_cost', strval($new_total_cost), PDO::PARAM_STR);
                $stmt->bindValue('new_function', $new_function, PDO::PARAM_STR);
                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                $stmt->bindValue('new_autorenew', $new_autorenew, PDO::PARAM_INT);
                $stmt->bindValue('new_privacy', $new_privacy, PDO::PARAM_INT);
                $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
                $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->execute();

                $temp_domain_id = $pdo->lastInsertId('id');
                $history_op->created('domain', $temp_domain_id);

                $stmt = $pdo->prepare("
                    INSERT INTO domain_field_data
                    (domain_id, insert_time)
                    VALUES
                    (:temp_domain_id, :timestamp)");
                $stmt->bindValue('temp_domain_id', $temp_domain_id, PDO::PARAM_INT);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->execute();

                $result = $pdo->query("
                    SELECT field_name
                    FROM domain_fields
                    ORDER BY `name`
                    LOCK IN SHARE MODE")->fetchAll();

                if ($result) {

                    $field_array = array();

                    foreach ($result as $row) {

                        $field_array[] = $row->field_name;

                    }

                    foreach ($field_array as $field) {

                        $stmt = $pdo->prepare("
                            UPDATE domain_field_data
                            SET `" . $field . "` = :full_field
                            WHERE domain_id = :temp_domain_id");
                        $stmt->bindValue('full_field', $new_custom_fields[$field] ?? '', PDO::PARAM_STR);
                        $stmt->bindValue('temp_domain_id', $temp_domain_id, PDO::PARAM_INT);
                        $stmt->execute();

                    }

                }

                $maint->updateDomainFee($temp_domain_id, $fee_peers);

                $queryB = new DomainMOD\QueryBuild();
                $sql = $queryB->missingFees('domains');
                $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

                $maint->updateSegments();

                $system->checkExistingAssets();

                $history_op->finish();

                if ($pdo->InTransaction()) $pdo->commit();

                // The domain links to its edit page, and the form stays here for the next domain
                $_SESSION['s_message_success'] .= sprintf(_('Domain %s added'), '<a href="edit.php?did=' . $temp_domain_id . '">' . $layout->ltr($unsanitize->text($new_domain)) . '</a>') . '<BR>';

                // A domain added without a fee is hidden from the list, so the add says so (DomainMOD\FeeStatus)
                $fee_status = new DomainMOD\FeeStatus();
                $fee_row = $fee_status->domainFee($temp_domain_id);
                if ($fee_row && !$fee_status->hasFee($fee_row)) $_SESSION['s_message_warning'] .= $fee_status->domainWarning($fee_row, true) . '<BR>';

            } catch (DomainMOD\HostingServiceRefused $e) {

                $form->addFieldError('new_service_id', $e->getMessage());

            } catch (Exception $e) {

                if ($pdo->InTransaction()) $pdo->rollback();

                // Another request changed the same rows at the same moment: nothing was saved, and the form shows again with
                // what was typed (see domainmod_restore_is_conflict())
                if (domainmod_restore_is_conflict($e)) {
                    $_SESSION['s_message_danger'] .= domainmod_restore_conflict_form_message() . '<BR>';
                } else {

                    $log_message = 'Unable to add domain';
                    $log_extra = array('Error' => $e);
                    $log->critical($log_message, $log_extra);

                    $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                    throw $e;

                }

            } finally {

                if ($new_service_id !== 0) DomainMOD\Hosting::unlock($pdo);

            }

        }

    } else {

        if ($new_domain === '') {
            $form->addFieldError('new_domain', _('Enter the domain'));
        } elseif (!$domain->checkFormat($new_domain)) {
            $form->addFieldError('new_domain', _('The domain format is incorrect'));
        }

        if (!$date->checkDateFormat($new_expiry_date)) {
            $form->addFieldError('datepick', _('The expiry date you entered is invalid'));
        }

        if ($new_account_id === 0) {

            $form->addFieldError('new_account_id', _('Choose the Registrar Account'));

        }

        if ($new_dns_id === 0) {

            $form->addFieldError('new_dns_id', _('Choose the DNS Profile'));

        }

        if ($new_ip_id === 0) {

            $form->addFieldError('new_ip_id', _('Choose the IP Address'));

        }

        if ($new_service_id < 0) {

            $form->addFieldError('new_service_id', DomainMOD\Hosting::serviceGoneMessage());

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
if ($watch_entry) echo $form->showInputHidden('watch', $watch_id);
// The fields in titled cards, the same way as the edit domain page. Each card's fields are built first, so a card
// whose menus all have nothing to choose from is left out rather than drawn empty. Moving a field to another card is
// a matter of moving its lines between these blocks.
ob_start();
echo $form->showInputText('new_domain', _('Domain') . ' (255)', '', $unsanitize->text($new_domain), '255', '', '1', '', '', '1');
echo $form->showInputText('new_function', _('Function') . ' (255)', '', $unsanitize->text($new_function), '255', '', '', '', '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_cat_id;

} else {

    $to_compare = $_SESSION['s_default_category_domains'];

}

$result = $pdo->query("
    SELECT id, `name`
    FROM categories
    ORDER BY name ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_cat_id', _('Category'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $to_compare);

    }

    echo $form->showDropdownBottom('', 'new_cat_id');
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

echo $layout->formSection(_('Details'), 'details', ob_get_clean());

ob_start();
if ($new_expiry_date == '') {
    $new_expiry_date = $time->userDatePlusYears(1);
}
echo $form->showInputText('datepick', _('Expiry Date') . ' (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_account_id;

} else {

    $to_compare = $_SESSION['s_default_registrar_account'];

}

$result = $pdo->query("
    SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
    FROM registrar_accounts AS ra, owners AS o, registrars AS r
    WHERE ra.owner_id = o.id
      AND ra.registrar_id = r.id
    ORDER BY r_name ASC, o_name ASC, ra.username ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_account_id', _('Registrar Account'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->r_name . ', ' . $row->o_name . ' (' . $row->username . ')', $to_compare);

    }

    echo $form->showDropdownBottom('', 'new_account_id');

}

// Each switch is one grid item, with the same spacing as the other fields
echo $form->showSwitch(_('Auto-Renewal') . '?', '', 'new_autorenew', $new_autorenew, '<div class="mb-3">', '</div>');
echo $form->showSwitch(_('Privacy Enabled') . '?', '', 'new_privacy', $new_privacy, '<div class="mb-3">', '</div>');
echo $layout->formSection(_('Registration'), 'registration', ob_get_clean());

ob_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_dns_id;

} else {

    $to_compare = $_SESSION['s_default_dns'];

}

$result = $pdo->query("
    SELECT id, `name`
    FROM dns
    ORDER BY `name` ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_dns_id', _('DNS Profile'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $to_compare);

    }

    echo $form->showDropdownBottom('', 'new_dns_id');

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_ip_id;

} else {

    $to_compare = $_SESSION['s_default_ip_address_domains'];

}

$result = $pdo->query("
    SELECT id, `name`, ip
    FROM ip_addresses
    ORDER BY `name` ASC, ip ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $to_compare);

    }

    echo $form->showDropdownBottom('', 'new_ip_id');

}

// The optional hosting service, once hosting is available (a refused choice shows again as it was posted)
if ($hosting_available) {

    $to_compare = $_SERVER['REQUEST_METHOD'] == 'POST' ? max($new_service_id, 0) : 0;
    $services = DomainMOD\Hosting::serviceMenu($pdo, $to_compare);
    // Shown again after a failed save, the IP Address shown was already chosen, so the service no longer fills it in
    echo $form->showHostingServiceMenu('new_service_id', $to_compare, $services, '', '',
        $_SERVER['REQUEST_METHOD'] == 'POST' ? '' : 'new_ip_id', DomainMOD\Hosting::serviceIps($pdo, array_keys($services)));

}

echo $layout->formSection(_('Hosting'), 'hosting', ob_get_clean());

ob_start();
echo $form->showInputTextarea('new_notes', _('Notes'), '', $unsanitize->text($new_notes), '', '<div class="domainmod-span-all">', '</div>');
echo $layout->formSection(_('Notes'), 'notes', ob_get_clean());

ob_start();
$result = $pdo->query("
    SELECT field_name
    FROM domain_fields
    ORDER BY type_id ASC, `name` ASC")->fetchAll();

if ($result) {

    $field_array = array();

    foreach ($result as $row) {

        $field_array[] = $row->field_name;

    }

    foreach ($field_array as $field) {

        $stmt = $pdo->prepare("
            SELECT df.name, df.field_name, df.type_id, df.description
            FROM domain_fields AS df, custom_field_types AS cft
            WHERE df.type_id = cft.id
              AND df.field_name = :field");
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

echo $form->showSubmitButton(_('Add Domain'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/hosting-service-ip.js'); ?>"></script>
</body>
</html>
