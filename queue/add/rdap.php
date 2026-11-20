<?php
/**
 * /queue/add/rdap.php
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/queue/add/rdap.php');
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$domain = new DomainMOD\Domain();
$queue = new DomainMOD\DomainQueue();
$ssl_queue = new DomainMOD\SslQueue();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/queue-add-rdap.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

// RDAP imports need the RDAP source that the database update adds
$rdap_api_registrar_id = $queue->getRdapApiRegistrarId();

$max_domains = 1000;
$raw_domain_list = isset($_POST['raw_domain_list']) ? $sanitize->text($_POST['raw_domain_list']) : '';
$new_owner_id = (int) ($_POST['new_owner_id'] ?? ($_SESSION['s_default_owner_domains'] ?? 0));
// The SSL tick box, ticked when the form is first shown, and shown once the database update adds the SSL Queue
$new_import_ssl = ($_SERVER['REQUEST_METHOD'] == 'POST') ? (int) isset($_POST['new_import_ssl']) : 1;
$ssl_queue_available = $ssl_queue->isAvailable();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $rdap_api_registrar_id) {

    $format = new DomainMOD\Format();
    $domain_array = ($raw_domain_list != '') ? $format->cleanAndSplitDomains($raw_domain_list) : array();

    list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) = $domain->findInvalidDomains($domain_array);

    $stmt = $pdo->prepare("
        SELECT id
        FROM owners
        WHERE id = :owner_id");
    $stmt->bindValue('owner_id', $new_owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $owner_exists = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($raw_domain_list == '' || !$domain_array) {

        $form->addFieldError('raw_domain_list', _('Enter the list of domains to add to the queue'));

    } elseif (count($domain_array) > $max_domains) {

        $form->addFieldError('raw_domain_list', sprintf(_('You can add up to %s domains at a time. Please split your list and add it in parts.'), number_format($max_domains)));

    } elseif ($invalid_domains == 1) {

        if ($invalid_count == 1) {

            $_SESSION['s_message_danger'] .= sprintf(_('There is %s invalid domain on your list.'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

        } else {

            $_SESSION['s_message_danger'] .= sprintf(_('There are %s invalid domains on your list.'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

            if (($invalid_count - $invalid_to_display) == 1) {

                $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s other'), number_format($invalid_count - $invalid_to_display)) . '<BR>';

            } elseif (($invalid_count - $invalid_to_display) > 1) {

                $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s others'), number_format($invalid_count - $invalid_to_display)) . '<BR>';
            }

        }

    } elseif (!$owner_exists) {

        $form->addFieldError('new_owner_id', _('Choose the owner'));

    } else {

        try {

            $pdo->beginTransaction();

            // the registrar and account are found with RDAP when each domain is processed
            foreach ($domain_array as $new_domain) {

                $queue->importToDomainQueue($rdap_api_registrar_id, $new_domain, $new_owner_id, 0, 0, $_SESSION['s_user_id']);

            }

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= _('Domains Added To Queue') . '<BR>';

            // The SSL certificate lookups, in their own transaction now the domains are committed (it never throws)
            if ($ssl_queue_available && $new_import_ssl === 1) {

                list($ssl_success, $ssl_warning) = $ssl_queue->queueDomainLookups($domain_array, $new_owner_id, $_SESSION['s_user_id']);
                $_SESSION['s_message_success'] .= $ssl_success;
                $_SESSION['s_message_warning'] .= $ssl_warning;

            }

            header('Location: ../../queue/');
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to add domains to queue';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= _('Unable to add domains to queue') . '<BR>';

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
<?php
if (!$rdap_api_registrar_id) {

    echo '<p>' . _('RDAP imports become available after the next database update.') . '</p>';
    echo '<div class="domainmod-back-next">' . $layout->linkButton('../', _('Go Back'), 'secondary', 'bi-arrow-left') . '</div>';

} else {

    echo '<p class="domainmod-intro">' . sprintf(_("Paste a list of domains, and %s will look up each one for you. It uses RDAP, the public replacement for WHOIS, or WHOIS itself for domains RDAP doesn't cover. It fills in each domain's expiry date and DNS servers and works out which registrar it's at, so you don't need to choose a registrar or enter any API details. A registrar that isn't in %s yet is added for you. Each domain goes into your account at that registrar if you have exactly one, or otherwise into a general account named \"RDAP Import\" that's created the first time and used for later imports."), SOFTWARE_TITLE, SOFTWARE_TITLE) . '</p>';

    echo $form->showFormTop('');

    echo $form->showDropdownTop('new_owner_id', _('Owner'), '', '1', '');
    $result = $pdo->query("
        SELECT id, `name`
        FROM owners
        ORDER BY `name` ASC")->fetchAll();
    foreach ($result as $row) {
        echo $form->showDropdownOption($row->id, $row->name, $new_owner_id);
    }
    echo $form->showDropdownBottom('', 'new_owner_id');

    // What the lookup can't fill in, as the domain list's help text
    $list_help = _("Neither RDAP nor WHOIS includes a domain's WHOIS privacy or auto-renewal status, so both will be set to No. You can update them afterwards using the Bulk Updater.") . ' '
        . _("Some country-code registries (such as .de, .nl, and .au) don't publish expiry dates, so those domains can't be looked up this way. If they fail in the queue, you can finish them by hand with Fix and Import.");
    echo $form->showInputTextarea('raw_domain_list', sprintf(_('Domains to add (one per line, up to %s)'), number_format($max_domains)), $list_help, $unsanitize->text($raw_domain_list), '1', '', '');
    // TRANSLATORS: %s is the app's name, DomainMOD.
    if ($ssl_queue_available) echo $form->showSwitch(_("Also import each domain's SSL certificate"), sprintf(_("Each domain's site is checked once the domain has been imported, and a new certificate is linked to it when %s has just one record of that domain. If the domain isn't imported, the site is still checked, and a new certificate is added without a domain."), SOFTWARE_TITLE), 'new_import_ssl', $new_import_ssl, '<div class="mb-3">', '</div>');

    // Go Back and the main button in one row, Back on the start side (css/base.css)
    echo '<div class="domainmod-back-next">' . $layout->linkButton('../', _('Go Back'), 'secondary', 'bi-arrow-left');
    echo $form->showSubmitButton(_('Add Domains'), '', '');
    echo '</div>';

    echo $form->showFormBottom('');

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
