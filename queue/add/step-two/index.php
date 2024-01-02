<?php
/**
 * /queue/add/step-two/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/queue/add/step-two/index.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$domain = new DomainMOD\Domain();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/queue-add.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

$new_raid = (int) ($_REQUEST['new_raid'] ?? 0);
$raw_domain_list = isset($_POST['raw_domain_list']) ? $sanitize->text($_POST['raw_domain_list']) : '';

if ($new_raid !== 0) {

    $stmt = $pdo->prepare("
        SELECT apir.name, apir.req_account_username, apir.req_account_password, apir.req_account_id,
               apir.req_reseller_id, apir.req_api_app_name, apir.req_api_key, apir.req_api_secret, apir.req_ip_address,
               apir.lists_domains, apir.ret_expiry_date, apir.ret_dns_servers, apir.ret_privacy_status,
               apir.ret_autorenewal_status, apir.notes, ra.username, ra.password, ra.account_id, ra.reseller_id,
               ra.api_app_name, ra.api_key, ra.api_secret, ra.api_ip_id
        FROM registrar_accounts AS ra, registrars AS r, api_registrars AS apir
        WHERE ra.registrar_id = r.id
          AND r.api_registrar_id = apir.id
          AND ra.id = :new_raid");
    $stmt->bindValue('new_raid', $new_raid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $api_registrar_name = $result->name;
        $req_account_username = $result->req_account_username;
        $req_account_password = $result->req_account_password;
        $req_account_id = $result->req_account_id;
        $req_reseller_id = $result->req_reseller_id;
        $req_api_app_name = $result->req_api_app_name;
        $req_api_key = $result->req_api_key;
        $req_api_secret = $result->req_api_secret;
        $req_ip_address = $result->req_ip_address;
        $lists_domains = $result->lists_domains;
        $ret_expiry_date = $result->ret_expiry_date;
        $ret_dns_servers = $result->ret_dns_servers;
        $ret_privacy_status = $result->ret_privacy_status;
        $ret_autorenewal_status = $result->ret_autorenewal_status;
        $registrar_notes = $result->notes;
        $account_username = $result->username;
        $account_password = $result->password;
        $account_id = $result->account_id;
        $reseller_id = $result->reseller_id;
        $api_app_name = $result->api_app_name;
        $api_key = $result->api_key;
        $api_secret = $result->api_key;
        $api_ip_id = $result->api_ip_id;

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $format = new DomainMOD\Format();
    $domain_array = $format->cleanAndSplitDomains($raw_domain_list);

    // If the registrar has the ability to retrieve the list of domains
    if ($lists_domains == '1' && $raw_domain_list == '') {

        if ($new_raid === 0) {

            if ($new_raid === 0) $_SESSION['s_message_danger'] .= _('Please choose the registrar account') . '<BR>';

        } else {

            try {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    SELECT ra.owner_id, ra.registrar_id, r.api_registrar_id
                    FROM registrar_accounts AS ra, registrars AS r
                    WHERE ra.registrar_id = r.id
                      AND ra.id = :new_raid");
                $stmt->bindValue('new_raid', $new_raid, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch();
                $stmt->closeCursor();

                if ($result) {

                    $temp_owner_id = $result->owner_id;
                    $temp_registrar_id = $result->registrar_id;
                    $temp_api_registrar_id = $result->api_registrar_id;

                }

                $stmt = $pdo->prepare("
                    INSERT INTO domain_queue_list
                    (api_registrar_id, owner_id, registrar_id, account_id, created_by, insert_time)
                    VALUES
                    (:api_registrar_id, :owner_id, :registrar_id, :new_raid, :user_id, :timestamp)");
                $stmt->bindValue('api_registrar_id', $temp_api_registrar_id, PDO::PARAM_INT);
                $stmt->bindValue('owner_id', $temp_owner_id, PDO::PARAM_INT);
                $stmt->bindValue('registrar_id', $temp_registrar_id, PDO::PARAM_INT);
                $stmt->bindValue('new_raid', $new_raid, PDO::PARAM_INT);
                $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
                $timestamp = $time->stamp();
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->execute();

                $_SESSION['s_domains_in_list_queue'] = '1';

                if ($pdo->InTransaction()) $pdo->commit();

                $_SESSION['s_message_success'] .= _('Registrar Account Added To Domain List Queue') . '<BR>';

                header("Location: ./");
                exit;

            } catch (Exception $e) {

                if ($pdo->InTransaction()) $pdo->rollback();

                $log_message = 'Unable to add registrar account to domain list queue';
                $log_extra = array('Error' => $e);
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                throw $e;

            }

        }

    } else { // If the registrar's API DOES NOT have the ability to retrieve the list of domains, or if there's a
             // problem with he automatic import, use the list supplied

        // check to make sure that the registrar associated with the account has API support
        $stmt = $pdo->prepare("
            SELECT ra.id, ra.registrar_id
            FROM registrar_accounts AS ra, registrars AS r, api_registrars AS ar
            WHERE ra.registrar_id = r.id
              AND r.api_registrar_id = ar.id
              AND ra.id = :new_raid");
        $stmt->bindValue('new_raid', $new_raid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if ($result) {

            $has_api_support = '1';

        } else {

            $stmt2 = $pdo->prepare("
                SELECT registrar_id
                FROM registrar_accounts
                WHERE id = :new_raid");
            $stmt2->bindValue('new_raid', $new_raid, PDO::PARAM_INT);
            $stmt2->execute();
            $temp_registrar_id = $stmt2->fetchColumn();

            $has_api_support = '0';

        }

        if ($new_raid === 0 || $raw_domain_list == '' || $has_api_support != '1') {

            if ($has_api_support != '1' && $new_raid !== 0) {

                $_SESSION['s_message_danger'] .= _("Either the registrar associated with this account doesn't have API support, or you haven't yet updated the registrar to indicate API support.") . '<BR><BR>' .
                    sprintf(_('Please check the %sregistrar%s and try again.'), '<a href="' . $web_root . '/assets/edit/registrar.php?rid=' . $temp_registrar_id . '">', '</a>');

            } else {

                if ($new_raid === 0) $_SESSION['s_message_danger'] .= _('Please choose the registrar account') . '<BR>';
                if ($raw_domain_list == '') $_SESSION['s_message_danger'] .= _('Enter the list of domains to add to the queue') . '<BR>';

            }

        } else {

            list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) = $domain->findInvalidDomains($domain_array);

            if ($raw_domain_list == "" || $invalid_domains == 1) {

                if ($invalid_domains == 1) {

                    if ($invalid_count == 1) {

                        $_SESSION['s_message_danger'] .= sprintf(_('There is %s invalid domain on your list'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

                    } else {

                        $_SESSION['s_message_danger'] .= sprintf(_('There are %s invalid domains on your list'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

                        if (($invalid_count - $invalid_to_display) == 1) {

                            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s other'), number_format($invalid_count - $invalid_to_display)) . '<BR>';

                        } elseif (($invalid_count - $invalid_to_display) > 1) {

                            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s others'), number_format($invalid_count - $invalid_to_display)) . '<BR>';
                        }

                    }

                } else {

                    $_SESSION['s_message_danger'] .= _('Enter the list of domains to add to the queue') . '<BR>';

                }
                $submission_failed = 1;

            } else {

                $date = new DomainMOD\Date();

                // cycle through domains here
                foreach ($domain_array as $key => $new_domain) {

                    $stmt = $pdo->prepare("
                        SELECT domain
                        FROM domains
                        WHERE domain = :new_domain");
                    $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
                    $stmt->execute();
                    $result = $stmt->fetchAll();

                    if ($result) {

                        $has_existing_domains = '1';

                    }

                }

                // cycle through domains here
                foreach ($domain_array as $key => $new_domain) {

                    $stmt = $pdo->prepare("
                        SELECT domain
                        FROM domain_queue
                        WHERE domain = :new_domain");
                    $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
                    $stmt->execute();
                    $result = $stmt->fetchAll();

                    if ($result) {

                        $has_existing_domains_queue = '1';

                    }

                }

                if ($new_raid === 0 || $has_existing_domains == '1' || $has_existing_domains_queue == '1') {

                    if ($has_existing_domains == '1') $_SESSION['s_message_danger'] .= sprintf(_('At least one of the domains you entered already exists in %s. %s You should run the domain list through a Segment filter to determine which one(s).'), SOFTWARE_TITLE, '<BR><BR>') . '<BR>';
                    if ($has_existing_domains_queue == '1') $_SESSION['s_message_danger'] .= _('At least one of the domains you entered is already in the domain queue.') . '<BR>';
                    if ($new_raid === 0) $_SESSION['s_message_danger'] .= _('Please choose the registrar account') . '<BR>';

                    $submission_failed = 1;

                } else {

                    try {

                        $pdo->beginTransaction();

                        $stmt = $pdo->prepare("
                            SELECT ra.owner_id, ra.registrar_id, r.api_registrar_id
                            FROM registrar_accounts AS ra, registrars AS r
                            WHERE ra.registrar_id = r.id
                              AND ra.id = :raid");
                        $stmt->bindValue('raid', $new_raid, PDO::PARAM_INT);
                        $stmt->execute();
                        $result = $stmt->fetch();
                        $stmt->closeCursor();

                        if ($result) {

                            $temp_owner_id = $result->owner_id;
                            $temp_registrar_id = $result->registrar_id;
                            $temp_api_registrar_id = $result->api_registrar_id;

                        }

                        // cycle through domains here
                        foreach ($domain_array as $key => $new_domain) {

                            $domain_temp = new DomainMOD\Domain();
                            $new_tld = $domain_temp->getTld($new_domain);

                            $stmt = $pdo->prepare("
                                INSERT INTO domain_queue
                                (api_registrar_id, domain, owner_id, registrar_id, account_id, tld, created_by, insert_time)
                                VALUES
                                (:api_registrar_id, :new_domain, :owner_id, :registrar_id, :new_raid, :new_tld, :user_id, :timestamp)");
                            $stmt->bindValue('api_registrar_id', $temp_api_registrar_id, PDO::PARAM_INT);
                            $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
                            $stmt->bindValue('owner_id', $temp_owner_id, PDO::PARAM_INT);
                            $stmt->bindValue('registrar_id', $temp_registrar_id, PDO::PARAM_INT);
                            $stmt->bindValue('new_raid', $new_raid, PDO::PARAM_INT);
                            $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
                            $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
                            $timestamp = $time->stamp();
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->execute();

                        } // finish cycling through domains here

                        $_SESSION['s_domains_in_queue'] = '1';

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains Added To Queue') . '<BR>';

                        header('Location: ../../../queue/');
                        exit;

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to add domains to queue';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                }

            }

        }

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
if ($new_raid !== 0) { ?>

    <strong><?php echo _('API Requirements'); ?></strong><BR>
    <?php echo sprintf(_('%s requires the following credentials in order to use their API. These credentials must to be saved with the %sregistrar account asset%s.'), $api_registrar_name, '<a href="' . $web_root . '/assets/edit/registrar-account.php?raid=' . $new_raid . '">', '</a>'); ?>

    <ul><?php

    $missing_text = ' (<a href="' . $web_root . '/assets/edit/registrar-account.php?raid=' . htmlentities($new_raid, ENT_QUOTES, 'UTF-8') . '"><span style="color: #a30000"><strong>' . strtolower(_('Missing')) . ' - ' . strtolower(_('Click Here to Enter')) . '</strong></span></a>)';
    $saved_text = ' (<span style="color: darkgreen"><strong>' . strtolower(_('Saved')) . '</strong></span>)';

    if ($req_account_username == '1') {
        echo '<li>' . _('Registrar Account Username');
        if ($account_username == '') {
            echo $missing_text;
        } else {
            echo $saved_text;
        }
        echo '</li>';
    }
    if ($req_account_password == '1') {
        echo '<li>' . _('Registrar Account Password');
        if ($account_password == '') {
            echo $missing_text;
        } else {
            echo $saved_text;
        }
        echo '</li>';
    }
    if ($req_account_id == '1') {
        echo '<li>' . _('Registrar Account ID');
        if ($account_id == '') {
            echo $missing_text;
        } else {
            echo $saved_text;
        }
        echo '</li>';
    }
    if ($req_reseller_id == '1') {
        echo '<li>' . _('Reseller ID');
        if ($reseller_id == '' || $reseller_id == '0') {
            echo $missing_text;
        } else {
            echo $saved_text;
        }
        echo '</li>';
    }
    if ($req_api_app_name == '1') {
        echo '<li>' . _('API Application Name');
        if ($api_app_name == '') {
            echo $missing_text;
        } else {
            echo $saved_text;
        }
        echo '</li>';
    }
    if ($req_api_key == '1') {
        echo '<li>' . _('API Key');
        if ($api_key == '') {
            echo $missing_text;
        } else {
            echo $saved_text;
        }
        echo '</li>';
    }
    if ($req_api_secret == '1') {
        echo '<li>' . _('API Secret');
        if ($api_secret == '') {
            echo $missing_text;
        } else {
            echo $saved_text;
        }
        echo '</li>';
    }
    if ($req_ip_address == '1') {
        echo '<li>' . _('Connecting IP Address');
        if ($api_ip_id == '0') {
            echo $missing_text;
        } else {
            echo $saved_text;
        }
        echo '</li>';
    } ?>
    </ul><?php

}

$registrar_notes = $registrar_notes ?? '';
if ($registrar_notes != '') {

    echo '<strong>' . _('Registrar Notes') . '</strong><BR>';
    echo $registrar_notes . "<BR><BR>";

}
echo $form->showFormTop('');

if ($new_raid !== 0) {

    if ($lists_domains == '1') {

        echo '<strong>' . _('Domain List') . '</strong><BR>';

        echo sprintf(_("The %s API has a domain list feature, so you don't even have to supply a list of the domains you want to import, %s will retrieve them for you automatically."), htmlentities($api_registrar_name, ENT_QUOTES, 'UTF-8'), SOFTWARE_TITLE) . '<BR><BR>';

        echo $form->showInputTextarea('raw_domain_list', '[' . strtoupper(_('Optional')) . '] ' . _('Domains to add (one per line)'), '', $unsanitize->text($raw_domain_list), '', '', '');

    } else {

        echo $form->showInputTextarea('raw_domain_list', _('Domains to add (one per line)'), '', $unsanitize->text($raw_domain_list), '1', '', '');
    }

} ?>
<a href='../step-one/'><?php echo $layout->showButton('button', _('Go Back')); ?></a>&nbsp;&nbsp;
<?php
if ($new_raid !== 0) {

    echo $form->showSubmitButton(_('Add Domains'), '', '');

}
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
