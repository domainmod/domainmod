<?php
/**
 * /settings/display/index.php
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
<?php //@formatter:off
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$custom_field = new DomainMOD\CustomField();
$user = new DomainMOD\User();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/settings-display.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$new_number_of_domains = (int) ($_POST['new_number_of_domains'] ?? 0);
$new_number_of_ssl_certs = (int) ($_POST['new_number_of_ssl_certs'] ?? 0);
$domain_column_options = (array) ($_POST['domain_column_options'] ?? array(''));

$custom_domain_fields = (array) ($_POST['custom_domain_fields'] ?? array(''));
$custom_ssl_fields = (array) ($_POST['custom_ssl_fields'] ?? array(''));
$ssl_column_options = (array) ($_POST['ssl_column_options'] ?? array(''));
$new_display_inactive_assets = (int) ($_POST['new_display_inactive_assets'] ?? 0);
$new_display_dw_intro_page = (int) ($_POST['new_display_dw_intro_page'] ?? 0);
// The Getting Started checklist's setting, saved only when the form offered it (from the 4.26.0 database update on), so
// a form loaded before that update can't hide the checklist by leaving the box out
$getting_started_offered = ($_POST['getting_started_offered'] ?? '') === '1';
$new_display_getting_started = (int) ($_POST['new_display_getting_started'] ?? 0) === 1 ? 1 : 0;
// The Transfer Lock column's setting (DomainMOD\DomainStatus), saved only when the form offered it (from the 4.27.0
// database update on), so a form loaded before that update can't change it
$domain_lock_available = DomainMOD\DomainStatus::isAvailable($pdo);
$domain_lock_offered = $domain_lock_available && ($_POST['domain_lock_offered'] ?? '') === '1';
$new_display_domain_lock = '0';
// The Live DNS column (DomainMOD\LiveDns) the same way: offered and saved only once the 4.27.0 database update is done
$domain_live_dns_available = DomainMOD\LiveDns::isAvailable($pdo);
$domain_live_dns_offered = $domain_live_dns_available && ($_POST['domain_live_dns_offered'] ?? '') === '1';
$new_display_domain_live_dns = '0';
// The Hosting settings (DomainMOD\Hosting), saved only when the form offered them (from the 4.27.0 database update
// on), so a form loaded before that update can't change them
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);
$hosting_offered = $hosting_available && ($_POST['hosting_offered'] ?? '') === '1';
$new_number_of_hosting_services = (int) ($_POST['new_number_of_hosting_services'] ?? 0);
$hosting_column_options = (array) ($_POST['hosting_column_options'] ?? array(''));
$custom_hosting_fields = (array) ($_POST['custom_hosting_fields'] ?? array(''));
$hosting_columns = array('owner' => 'display_hosting_owner', 'provider' => 'display_hosting_provider',
    'account' => 'display_hosting_account', 'type' => 'display_hosting_type', 'expiry' => 'display_hosting_expiry_date',
    'ip' => 'display_hosting_ip', 'category' => 'display_hosting_category', 'fee' => 'display_hosting_fee');
$new_display_hosting = array();
foreach ($hosting_columns as $column) {
    $new_display_hosting[$column] = (string) ($_SESSION['s_' . $column] ?? '0');
}
// The colour mode: 0 Light, 1 Dark, or 2 Auto (which follows the system setting)
$new_dark_mode = (int) ($_POST['new_dark_mode'] ?? 2);
if (!in_array($new_dark_mode, array(0, 1, 2), true)) $new_dark_mode = 2;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (is_null($domain_column_options)) $domain_column_options = array('');
    if (is_null($custom_domain_fields)) $custom_domain_fields = array('');
    if (is_null($custom_ssl_fields)) $custom_ssl_fields = array('');
    if (is_null($ssl_column_options)) $ssl_column_options = array('');

    foreach ($_SESSION['s_cdf_data'] as $field) {

        if (in_array($field['display_field'], $custom_domain_fields)) {

            $pdo->query("
                UPDATE `user_settings`
                SET " . $field['display_field'] . " = '1'
                WHERE user_id = '" . $_SESSION['s_user_id'] . "'");

        } else {

            $pdo->query("
                UPDATE `user_settings`
                SET " . $field['display_field'] . " = '0'
                WHERE user_id = '" . $_SESSION['s_user_id'] . "'");

        }

        $_SESSION['s_cdf_data'] = $custom_field->getCDFData();

    }

    foreach ($_SESSION['s_csf_data'] as $field) {

        if (in_array($field['display_field'], $custom_ssl_fields)) {

            $pdo->query("
                UPDATE `user_settings`
                SET " . $field['display_field'] . " = '1'
                WHERE user_id = '" . $_SESSION['s_user_id'] . "'");

        } else {

            $pdo->query("
                UPDATE `user_settings`
                SET " . $field['display_field'] . " = '0'
                WHERE user_id = '" . $_SESSION['s_user_id'] . "'");

        }

        $_SESSION['s_csf_data'] = $custom_field->getCSFData();

    }

    if (in_array("expiry", $domain_column_options)) { $new_display_domain_expiry_date = '1'; } else { $new_display_domain_expiry_date = '0'; }
    if (in_array("fee", $domain_column_options)) { $new_display_domain_fee = '1'; } else { $new_display_domain_fee = '0'; }
    if (in_array("tld", $domain_column_options)) { $new_display_domain_tld = '1'; } else { $new_display_domain_tld = '0'; }
    if (in_array("registrar", $domain_column_options)) { $new_display_domain_registrar = '1'; } else { $new_display_domain_registrar = '0'; }
    if (in_array("account", $domain_column_options)) { $new_display_domain_account = '1'; } else { $new_display_domain_account = '0'; }
    if (in_array("dns", $domain_column_options)) { $new_display_domain_dns = '1'; } else { $new_display_domain_dns = '0'; }
    if (in_array("ip", $domain_column_options)) { $new_display_domain_ip = '1'; } else { $new_display_domain_ip = '0'; }
    if (in_array("host", $domain_column_options)) { $new_display_domain_host = '1'; } else { $new_display_domain_host = '0'; }
    if (in_array("category", $domain_column_options)) { $new_display_domain_category = '1'; } else { $new_display_domain_category = '0'; }
    if (in_array("owner", $domain_column_options)) { $new_display_domain_owner = '1'; } else { $new_display_domain_owner = '0'; }
    if (in_array("lock", $domain_column_options)) { $new_display_domain_lock = '1'; } else { $new_display_domain_lock = '0'; }
    if (in_array("live_dns", $domain_column_options)) { $new_display_domain_live_dns = '1'; } else { $new_display_domain_live_dns = '0'; }

    if (in_array("expiry", $ssl_column_options)) { $new_display_ssl_expiry_date = '1'; } else { $new_display_ssl_expiry_date = '0'; }
    if (in_array("fee", $ssl_column_options)) { $new_display_ssl_fee = '1'; } else { $new_display_ssl_fee = '0'; }
    if (in_array("domain", $ssl_column_options)) { $new_display_ssl_domain = '1'; } else { $new_display_ssl_domain = '0'; }
    if (in_array("provider", $ssl_column_options)) { $new_display_ssl_provider = '1'; } else { $new_display_ssl_provider = '0'; }
    if (in_array("account", $ssl_column_options)) { $new_display_ssl_account = '1'; } else { $new_display_ssl_account = '0'; }
    if (in_array("type", $ssl_column_options)) { $new_display_ssl_type = '1'; } else { $new_display_ssl_type = '0'; }
    if (in_array("ip", $ssl_column_options)) { $new_display_ssl_ip = '1'; } else { $new_display_ssl_ip = '0'; }
    if (in_array("category", $ssl_column_options)) { $new_display_ssl_category = '1'; } else { $new_display_ssl_category = '0'; }
    if (in_array("owner", $ssl_column_options)) { $new_display_ssl_owner = '1'; } else { $new_display_ssl_owner = '0'; }

    if ($hosting_offered) {

        foreach ($hosting_columns as $option => $column) {
            $new_display_hosting[$column] = in_array($option, $hosting_column_options, true) ? '1' : '0';
        }

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_number_of_domains !== 0 && $new_number_of_ssl_certs !== 0
    && (!$hosting_offered || $new_number_of_hosting_services !== 0)) {

    $stmt = $pdo->prepare("
        UPDATE user_settings
        SET number_of_domains = :new_number_of_domains,
            display_domain_owner = :new_display_domain_owner,
            display_domain_registrar = :new_display_domain_registrar,
            display_domain_account = :new_display_domain_account,
            display_domain_category = :new_display_domain_category,
            display_domain_expiry_date = :new_display_domain_expiry_date,
            display_domain_dns = :new_display_domain_dns,
            display_domain_host = :new_display_domain_host,
            display_domain_ip = :new_display_domain_ip,
            display_domain_tld = :new_display_domain_tld,
            display_domain_fee = :new_display_domain_fee,
            display_ssl_owner = :new_display_ssl_owner,
            display_ssl_provider = :new_display_ssl_provider,
            display_ssl_account = :new_display_ssl_account,
            display_ssl_domain = :new_display_ssl_domain,
            display_ssl_type = :new_display_ssl_type,
            display_ssl_ip = :new_display_ssl_ip,
            display_ssl_category = :new_display_ssl_category,
            display_ssl_expiry_date = :new_display_ssl_expiry_date,
            display_ssl_fee = :new_display_ssl_fee,
            display_inactive_assets = :new_display_inactive_assets,
            display_dw_intro_page = :new_display_dw_intro_page,
            dark_mode = :new_dark_mode,
            number_of_ssl_certs = :new_number_of_ssl_certs,
            update_time = :timestamp
        WHERE user_id = :user_id");
    $stmt->bindValue('new_number_of_domains', $new_number_of_domains, PDO::PARAM_INT);
    $stmt->bindValue('new_display_domain_owner', $new_display_domain_owner, PDO::PARAM_INT);
    $stmt->bindValue('new_display_domain_registrar', $new_display_domain_registrar, PDO::PARAM_INT);
    $stmt->bindValue('new_display_domain_account', $new_display_domain_account, PDO::PARAM_INT);
    $stmt->bindValue('new_display_domain_category', $new_display_domain_category, PDO::PARAM_INT);
    $stmt->bindValue('new_display_domain_expiry_date', $new_display_domain_expiry_date, PDO::PARAM_INT);
    $stmt->bindValue('new_display_domain_dns', $new_display_domain_dns, PDO::PARAM_INT);
    $stmt->bindValue('new_display_domain_host', $new_display_domain_host, PDO::PARAM_INT);
    $stmt->bindValue('new_display_domain_ip', $new_display_domain_ip, PDO::PARAM_INT);
    $stmt->bindValue('new_display_domain_tld', $new_display_domain_tld, PDO::PARAM_INT);
    $stmt->bindValue('new_display_domain_fee', $new_display_domain_fee, PDO::PARAM_INT);
    $stmt->bindValue('new_display_ssl_owner', $new_display_ssl_owner, PDO::PARAM_INT);
    $stmt->bindValue('new_display_ssl_provider', $new_display_ssl_provider, PDO::PARAM_INT);
    $stmt->bindValue('new_display_ssl_account', $new_display_ssl_account, PDO::PARAM_INT);
    $stmt->bindValue('new_display_ssl_domain', $new_display_ssl_domain, PDO::PARAM_INT);
    $stmt->bindValue('new_display_ssl_type', $new_display_ssl_type, PDO::PARAM_INT);
    $stmt->bindValue('new_display_ssl_ip', $new_display_ssl_ip, PDO::PARAM_INT);
    $stmt->bindValue('new_display_ssl_category', $new_display_ssl_category, PDO::PARAM_INT);
    $stmt->bindValue('new_display_ssl_expiry_date', $new_display_ssl_expiry_date, PDO::PARAM_INT);
    $stmt->bindValue('new_display_ssl_fee', $new_display_ssl_fee, PDO::PARAM_INT);
    $stmt->bindValue('new_display_inactive_assets', $new_display_inactive_assets, PDO::PARAM_INT);
    $stmt->bindValue('new_display_dw_intro_page', $new_display_dw_intro_page, PDO::PARAM_INT);
    $stmt->bindValue('new_dark_mode', $new_dark_mode, PDO::PARAM_INT);
    $stmt->bindValue('new_number_of_ssl_certs', $new_number_of_ssl_certs, PDO::PARAM_INT);
    $timestamp = $time->stamp();
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['s_number_of_domains'] = $new_number_of_domains;
    $_SESSION['s_number_of_ssl_certs'] = $new_number_of_ssl_certs;
    $_SESSION['s_display_domain_owner'] = $new_display_domain_owner;
    $_SESSION['s_display_domain_registrar'] = $new_display_domain_registrar;
    $_SESSION['s_display_domain_account'] = $new_display_domain_account;
    $_SESSION['s_display_domain_category'] = $new_display_domain_category;
    $_SESSION['s_display_domain_expiry_date'] = $new_display_domain_expiry_date;
    $_SESSION['s_display_domain_dns'] = $new_display_domain_dns;
    $_SESSION['s_display_domain_host'] = $new_display_domain_host;
    $_SESSION['s_display_domain_ip'] = $new_display_domain_ip;
    $_SESSION['s_display_domain_host'] = $new_display_domain_host;
    $_SESSION['s_display_domain_tld'] = $new_display_domain_tld;
    $_SESSION['s_display_domain_fee'] = $new_display_domain_fee;
    $_SESSION['s_display_ssl_owner'] = $new_display_ssl_owner;
    $_SESSION['s_display_ssl_provider'] = $new_display_ssl_provider;
    $_SESSION['s_display_ssl_account'] = $new_display_ssl_account;
    $_SESSION['s_display_ssl_domain'] = $new_display_ssl_domain;
    $_SESSION['s_display_ssl_type'] = $new_display_ssl_type;
    $_SESSION['s_display_ssl_ip'] = $new_display_ssl_ip;
    $_SESSION['s_display_ssl_category'] = $new_display_ssl_category;
    $_SESSION['s_display_ssl_expiry_date'] = $new_display_ssl_expiry_date;
    $_SESSION['s_display_ssl_fee'] = $new_display_ssl_fee;
    $_SESSION['s_display_inactive_assets'] = $new_display_inactive_assets;
    $_SESSION['s_display_dw_intro_page'] = $new_display_dw_intro_page;
    $_SESSION['s_dark_mode'] = $new_dark_mode;

    $user->setDarkMode();

    if ($domain_lock_offered) {

        $stmt = $pdo->prepare("
            UPDATE user_settings
            SET display_domain_lock = :new_display_domain_lock
            WHERE user_id = :user_id");
        $stmt->bindValue('new_display_domain_lock', $new_display_domain_lock, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_display_domain_lock'] = $new_display_domain_lock;

    }

    if ($domain_live_dns_offered) {

        $stmt = $pdo->prepare("
            UPDATE user_settings
            SET display_domain_live_dns = :new_display_domain_live_dns
            WHERE user_id = :user_id");
        $stmt->bindValue('new_display_domain_live_dns', $new_display_domain_live_dns, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_display_domain_live_dns'] = $new_display_domain_live_dns;

    }

    if ($hosting_offered) {

        $sql_columns = '';
        foreach ($hosting_columns as $column) {
            $sql_columns .= $column . " = :" . $column . ",
                ";
        }
        $stmt = $pdo->prepare("
            UPDATE user_settings
            SET " . $sql_columns . "number_of_hosting_services = :new_number_of_hosting_services
            WHERE user_id = :user_id");
        foreach ($hosting_columns as $column) {
            $stmt->bindValue($column, (int) $new_display_hosting[$column], PDO::PARAM_INT);
        }
        $stmt->bindValue('new_number_of_hosting_services', $new_number_of_hosting_services, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_number_of_hosting_services'] = $new_number_of_hosting_services;
        foreach ($hosting_columns as $column) {
            $_SESSION['s_' . $column] = $new_display_hosting[$column];
        }

        // The fields as they are now (not as cached at sign-in), so a field deleted since doesn't name a dropped column
        foreach ($custom_field->getCHFData() as $field) {

            $stmt = $pdo->prepare("
                UPDATE `user_settings`
                SET " . $field['display_field'] . " = :display
                WHERE user_id = :user_id");
            $stmt->bindValue('display', in_array($field['display_field'], $custom_hosting_fields, true) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->execute();

        }

        $_SESSION['s_chf_data'] = $custom_field->getCHFData();

    }

    if ($getting_started_offered && $user->setGettingStartedDisplay($_SESSION['s_user_id'], $new_display_getting_started)) {

        $_SESSION['s_display_getting_started'] = $new_display_getting_started;

    }

    $_SESSION['s_message_success'] .= _('Display Settings updated') . '<BR>';

    header("Location: index.php");
    exit;

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_number_of_domains === 0) $form->addFieldError('new_number_of_domains', _('Enter the default number of domains to display'));
        if ($new_number_of_ssl_certs === 0) $form->addFieldError('new_number_of_ssl_certs', _('Enter the default number of SSL certificates to display'));
        if ($hosting_offered && $new_number_of_hosting_services === 0) $form->addFieldError('new_number_of_hosting_services', _('Enter the default number of hosting services to display'));

    } else {

        $stmt = $pdo->prepare("
            SELECT number_of_domains, number_of_ssl_certs, display_domain_owner, display_domain_registrar,
                display_domain_account, display_domain_category, display_domain_expiry_date, display_domain_dns,
                display_domain_host, display_domain_ip, display_domain_tld, display_domain_fee, display_ssl_owner,
                display_ssl_provider, display_ssl_account, display_ssl_domain, display_ssl_type, display_ssl_ip,
                display_ssl_category, display_ssl_expiry_date, display_ssl_fee, display_inactive_assets,
                display_dw_intro_page
            FROM user_settings
            WHERE user_id = :user_id");
        $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result) {

            $new_number_of_domains = $result->number_of_domains;
            $new_number_of_ssl_certs = $result->number_of_ssl_certs;
            $new_display_domain_owner = $result->display_domain_owner;
            $new_display_domain_registrar = $result->display_domain_registrar;
            $new_display_domain_account = $result->display_domain_account;
            $new_display_domain_category = $result->display_domain_category;
            $new_display_domain_expiry_date = $result->display_domain_expiry_date;
            $new_display_domain_dns = $result->display_domain_dns;
            $new_display_domain_host = $result->display_domain_host;
            $new_display_domain_ip = $result->display_domain_ip;
            $new_display_domain_tld = $result->display_domain_tld;
            $new_display_domain_fee = $result->display_domain_fee;
            $new_display_ssl_owner = $result->display_ssl_owner;
            $new_display_ssl_provider = $result->display_ssl_provider;
            $new_display_ssl_account = $result->display_ssl_account;
            $new_display_ssl_domain = $result->display_ssl_domain;
            $new_display_ssl_type = $result->display_ssl_type;
            $new_display_ssl_ip = $result->display_ssl_ip;
            $new_display_ssl_category = $result->display_ssl_category;
            $new_display_ssl_expiry_date = $result->display_ssl_expiry_date;
            $new_display_ssl_fee = $result->display_ssl_fee;
            $new_display_inactive_assets = $result->display_inactive_assets;
            $new_display_dw_intro_page = $result->display_dw_intro_page;

        }

        $new_display_getting_started = $user->getGettingStartedDisplay($_SESSION['s_user_id']) ?? 1;

        if ($domain_lock_available) {

            $stmt = $pdo->prepare("
                SELECT display_domain_lock
                FROM user_settings
                WHERE user_id = :user_id");
            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $new_display_domain_lock = (string) $stmt->fetchColumn();
            $stmt->closeCursor();

        }

        if ($domain_live_dns_available) {

            $stmt = $pdo->prepare("
                SELECT display_domain_live_dns
                FROM user_settings
                WHERE user_id = :user_id");
            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $new_display_domain_live_dns = (string) $stmt->fetchColumn();
            $stmt->closeCursor();

        }

        if ($hosting_available) {

            $stmt = $pdo->prepare("
                SELECT number_of_hosting_services, " . implode(', ', $hosting_columns) . "
                FROM user_settings
                WHERE user_id = :user_id");
            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if ($result) {

                $new_number_of_hosting_services = (int) $result->number_of_hosting_services;
                foreach ($hosting_columns as $column) {
                    $new_display_hosting[$column] = (string) $result->$column;
                }

            }

        }

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>

<?php echo $form->showFormTop(''); ?>

<h2 class="h3"><?php echo _('Main Domain Page'); ?></h2><?php

if ($new_number_of_domains !== 0) {
    $temp_number_of_domains = $new_number_of_domains;
} else {
    $temp_number_of_domains = '';
}
echo $form->showInputText('new_number_of_domains', _('Number of domains per page'), '', $temp_number_of_domains, '5', '', '1', '', '');

echo $form->showMultipleSelectTop('domain_column_options', _('Columns to Display'), '');
echo $form->showMultipleSelectOption(_('Expiry Date'), 'expiry', $new_display_domain_expiry_date);
echo $form->showMultipleSelectOption(_('Fee'), 'fee', $new_display_domain_fee);
echo $form->showMultipleSelectOption(_('TLD'), 'tld', $new_display_domain_tld);
echo $form->showMultipleSelectOption(_('Registrar'), 'registrar', $new_display_domain_registrar);
echo $form->showMultipleSelectOption(_('Account'), 'account', $new_display_domain_account);
echo $form->showMultipleSelectOption(_('DNS'), 'dns', $new_display_domain_dns);
echo $form->showMultipleSelectOption(_('IP Address'), 'ip', $new_display_domain_ip);
// The setting keeps its shipped name (display_domain_host), and now shows each domain's hosting service
echo $form->showMultipleSelectOption(_('Hosting Service'), 'host', $new_display_domain_host);
echo $form->showMultipleSelectOption(_('Category'), 'category', $new_display_domain_category);
echo $form->showMultipleSelectOption(_('Owner'), 'owner', $new_display_domain_owner);
if ($domain_lock_available) echo $form->showMultipleSelectOption(_('Transfer Lock'), 'lock', $new_display_domain_lock);
if ($domain_live_dns_available) echo $form->showMultipleSelectOption(_('Live DNS'), 'live_dns', $new_display_domain_live_dns);
echo $form->showMultipleSelectBottom('');
if ($domain_lock_available) echo $form->showInputHidden('domain_lock_offered', '1');
if ($domain_live_dns_available) echo $form->showInputHidden('domain_live_dns_offered', '1');

echo $form->showMultipleSelectTop('custom_domain_fields', _('Custom Domain Fields to Display'), '');

foreach ($_SESSION['s_cdf_data'] as $field) {

    if ($field['type_id'] != '3') { // Don't show Text Areas

        echo $form->showMultipleSelectOption($field['name'], $field['display_field'], $field['value']);

    }

}

echo $form->showMultipleSelectBottom('<BR>');
?>

<h2 class="h3"><?php echo _('Main SSL Certificate Page'); ?></h2><?php

if ($new_number_of_ssl_certs !== 0) {
    $temp_number_of_ssl_certs = $new_number_of_ssl_certs;
} else {
    $temp_number_of_ssl_certs = '';
}
echo $form->showInputText('new_number_of_ssl_certs', _('Number of SSL certificates per page'), '', $temp_number_of_ssl_certs, '5', '', '1', '', '');

echo $form->showMultipleSelectTop('ssl_column_options', _('Columns to Display'), '');
echo $form->showMultipleSelectOption(_('Expiry Date'), 'expiry', $new_display_ssl_expiry_date);
echo $form->showMultipleSelectOption(_('Fee'), 'fee', $new_display_ssl_fee);
echo $form->showMultipleSelectOption(_('Domain'), 'domain', $new_display_ssl_domain);
echo $form->showMultipleSelectOption(_('SSL Provider'), 'provider', $new_display_ssl_provider);
echo $form->showMultipleSelectOption(_('Account'), 'account', $new_display_ssl_account);
echo $form->showMultipleSelectOption(_('SSL Type'), 'type', $new_display_ssl_type);
echo $form->showMultipleSelectOption(_('IP Address'), 'ip', $new_display_ssl_ip);
echo $form->showMultipleSelectOption(_('Category'), 'category', $new_display_ssl_category);
echo $form->showMultipleSelectOption(_('Owner'), 'owner', $new_display_ssl_owner);
echo $form->showMultipleSelectBottom('');

echo $form->showMultipleSelectTop('custom_ssl_fields', _('Custom SSL Fields to Display'), '');

foreach ($_SESSION['s_csf_data'] as $field) {

    if ($field['type_id'] != '3') { // Don't show Text Areas

        echo $form->showMultipleSelectOption($field['name'], $field['display_field'], $field['value']);

    }

}

echo $form->showMultipleSelectBottom('<BR>');

if ($hosting_available) { ?>

<h2 class="h3"><?php echo _('Main Hosting Page'); ?></h2><?php

    echo $form->showInputHidden('hosting_offered', '1');

    if ($new_number_of_hosting_services !== 0) {
        $temp_number_of_hosting_services = $new_number_of_hosting_services;
    } else {
        $temp_number_of_hosting_services = '';
    }
    echo $form->showInputText('new_number_of_hosting_services', _('Number of hosting services per page'), '', $temp_number_of_hosting_services, '5', '', '1', '', '');

    echo $form->showMultipleSelectTop('hosting_column_options', _('Columns to Display'), '');
    echo $form->showMultipleSelectOption(_('Renewal Date'), 'expiry', $new_display_hosting['display_hosting_expiry_date']);
    echo $form->showMultipleSelectOption(_('Fee'), 'fee', $new_display_hosting['display_hosting_fee']);
    echo $form->showMultipleSelectOption(_('Provider'), 'provider', $new_display_hosting['display_hosting_provider']);
    echo $form->showMultipleSelectOption(_('Account'), 'account', $new_display_hosting['display_hosting_account']);
    echo $form->showMultipleSelectOption(_('Type'), 'type', $new_display_hosting['display_hosting_type']);
    echo $form->showMultipleSelectOption(_('IP Address'), 'ip', $new_display_hosting['display_hosting_ip']);
    echo $form->showMultipleSelectOption(_('Category'), 'category', $new_display_hosting['display_hosting_category']);
    echo $form->showMultipleSelectOption(_('Owner'), 'owner', $new_display_hosting['display_hosting_owner']);
    echo $form->showMultipleSelectBottom('');

    echo $form->showMultipleSelectTop('custom_hosting_fields', _('Custom Hosting Fields to Display'), '');

    foreach ($_SESSION['s_chf_data'] ?? array() as $field) {

        if ($field['type_id'] != '3') { // Don't show Text Areas

            echo $form->showMultipleSelectOption($field['name'], $field['display_field'], $field['value']);

        }

    }

    echo $form->showMultipleSelectBottom('<BR>');

}
?>

<h2 class="h3"><?php echo _('Asset Management Pages'); ?></h2>
<?php echo $form->showCheckbox('new_display_inactive_assets', '1', _('Display inactive assets'), '', $new_display_inactive_assets, '', '<BR>'); ?>

<h2 class="h3"><?php echo _('Data Warehouse'); ?></h2>
<?php echo $form->showCheckbox('new_display_dw_intro_page', '1', _('Display intro page'), '', $new_display_dw_intro_page, '', '<BR>'); ?>
<?php if ($user->canHideGettingStarted()) { ?>

<h2 class="h3"><?php echo _('Dashboard'); ?></h2>
<?php echo $form->showInputHidden('getting_started_offered', '1'); ?>
<?php echo $form->showCheckbox('new_display_getting_started', '1', _('Show the Getting Started checklist on the Dashboard'), _('It appears only until the first domain is added.'), $new_display_getting_started, '', '<BR>'); ?>
<?php } ?>

<h2 class="h3"><?php echo _('Miscellaneous'); ?></h2>

<?php
echo $form->showDropdownTop('new_dark_mode', _('Color Mode'), '', '', '');
echo $form->showDropdownOption('0', _('Light'), (string) ($_SESSION['s_dark_mode'] ?? 2));
echo $form->showDropdownOption('1', _('Dark'), (string) ($_SESSION['s_dark_mode'] ?? 2));
echo $form->showDropdownOption('2', _('Auto (follows your system setting)'), (string) ($_SESSION['s_dark_mode'] ?? 2));
echo $form->showDropdownBottom('<BR>');
?>

<?php echo $form->showSubmitButton(_('Update Display Settings'), '', ''); ?>

<?php echo $form->showFormBottom(''); ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
