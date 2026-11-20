<?php
/**
 * /queue/add/ssl.php
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
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();
$unsanitize = new DomainMOD\Unsanitize();
$ssl_queue = new DomainMOD\SslQueue();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/queue-add-ssl.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

// SSL imports need the tables that the database update adds
$ssl_queue_available = $ssl_queue->isAvailable();

$max_hosts = DomainMOD\SslQueue::MAX_HOSTS;
// The list is checked as it was posted (an escaping round trip would empty a list with one malformed line), and kept
// escaped, as typed text is (Sanitize::text()), to show again. Bytes that aren't valid UTF-8 become U+FFFD, so the rest
// still shows, and a resubmitted line with one is still refused (unlike a "?", which could start a URL's query).
$raw_host_text = (isset($_POST['raw_host_list']) && is_string($_POST['raw_host_list'])) ? $_POST['raw_host_list'] : '';
$raw_host_list = htmlspecialchars($raw_host_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$new_account_id = (int) ($_POST['new_account_id'] ?? 0);

/*
 * The menus' rows, and each one's default: the user's SSL default, then the system's, then the install's first row (the
 * lowest id, such as type 1, "Web Server SSL/TLS Certificate"), since the defaults can be 0 or point to a row that's
 * gone. A posted value is checked against the same rows.
 */
$owners = $pdo->query("
    SELECT id, `name`
    FROM owners
    ORDER BY `name` ASC")->fetchAll();

$types = $pdo->query("
    SELECT id, type
    FROM ssl_cert_types
    ORDER BY type ASC")->fetchAll();

$categories = $pdo->query("
    SELECT id, `name`
    FROM categories
    ORDER BY `name`")->fetchAll();

$ip_addresses = $pdo->query("
    SELECT id, ip, `name`
    FROM ip_addresses
    ORDER BY `name`, ip")->fetchAll();

$accounts = $pdo->query("
    SELECT sslpa.id, sslpa.username, o.name AS o_name, sslp.name AS sslp_name
    FROM ssl_accounts AS sslpa, owners AS o, ssl_providers AS sslp
    WHERE sslpa.owner_id = o.id
      AND sslpa.ssl_provider_id = sslp.id
    ORDER BY sslp_name ASC, o_name ASC, sslpa.username ASC")->fetchAll();

$default_id = function ($rows, $candidates) {
    $ids = array();
    foreach ($rows as $row) $ids[] = (int) $row->id;
    foreach ($candidates as $candidate) {
        if (in_array((int) $candidate, $ids, true)) return (int) $candidate;
    }
    return $ids ? min($ids) : 0;
};

$row_exists = function ($rows, $id) {
    foreach ($rows as $row) {
        if ((int) $row->id === (int) $id) return true;
    }
    return false;
};

// A posted choice that no longer exists gets an empty option, selected, so the menu can't quietly fall back to its first
// option when the form is shown again. Its value fails the same check, so a resubmission without a new choice is refused.
$stale_option = function ($rows, $id, $empty_value) use ($form, $row_exists) {
    if ($_SERVER['REQUEST_METHOD'] != 'POST' || $row_exists($rows, $id)) return '';
    return $form->showDropdownOption($empty_value, '', $empty_value);
};

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $new_owner_id = (int) ($_POST['new_owner_id'] ?? 0);
    $new_type_id = (int) ($_POST['new_type_id'] ?? 0);
    $new_cat_id = (int) ($_POST['new_cat_id'] ?? 0);
    $new_ip_id = (int) ($_POST['new_ip_id'] ?? 0);

} else {

    $new_owner_id = $default_id($owners, array($_SESSION['s_default_owner_ssl'] ?? 0, $_SESSION['s_system_default_owner_ssl'] ?? 0));
    $new_type_id = $default_id($types, array($_SESSION['s_default_ssl_type'] ?? 0, $_SESSION['s_system_default_ssl_type'] ?? 0));
    $new_cat_id = $default_id($categories, array($_SESSION['s_default_category_ssl'] ?? 0, $_SESSION['s_system_default_category_ssl'] ?? 0));
    $new_ip_id = $default_id($ip_addresses, array($_SESSION['s_default_ip_address_ssl'] ?? 0, $_SESSION['s_system_default_ip_address_ssl'] ?? 0));

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $ssl_queue_available) {

    // Each line keeps its own number for the messages, and duplicates are only dropped after every line is checked
    list($host_array, $invalid_lines) = DomainMOD\SslQueue::parseHostList($raw_host_text);
    $invalid_count = count($invalid_lines);
    $invalid_to_display = 5;

    $list_valid = false;

    if (!$host_array && !$invalid_lines) {

        $form->addFieldError('raw_host_list', _('Enter the list of hostnames to add to the queue'));

    } elseif ($invalid_count > 0) {

        $invalid_message = '';
        foreach (array_slice($invalid_lines, 0, $invalid_to_display) as $line_number) {
            $invalid_message .= sprintf(_('Line %s contains an invalid hostname'), number_format($line_number)) . '<BR>';
        }

        $_SESSION['s_message_danger'] .= sprintf(ngettext('There is %s invalid hostname on your list.', 'There are %s invalid hostnames on your list.', $invalid_count), number_format($invalid_count)) . '<BR><BR>' . $invalid_message;

        if (($invalid_count - $invalid_to_display) == 1) {

            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s other'), number_format($invalid_count - $invalid_to_display)) . '<BR>';

        } elseif (($invalid_count - $invalid_to_display) > 1) {

            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s others'), number_format($invalid_count - $invalid_to_display)) . '<BR>';

        }

    } elseif (count($host_array) > $max_hosts) {

        $form->addFieldError('raw_host_list', sprintf(_('You can add up to %s hostnames at a time. Please split your list and add it in parts.'), number_format($max_hosts)));

    } else {

        $list_valid = true;

    }

    // The menus are checked whatever the list's result, so a choice deleted while the form was open is marked straight
    // away rather than shown as another option the next time. The owner is only used when matching by issuer (a chosen
    // account brings its own).
    $menus_valid = true;

    foreach (array(array($new_account_id !== 0 && !$row_exists($accounts, $new_account_id), 'new_account_id', _('Choose the SSL Provider Account')),
                 array($new_account_id === 0 && !$row_exists($owners, $new_owner_id), 'new_owner_id', _('Choose the owner')),
                 array(!$row_exists($types, $new_type_id), 'new_type_id', _('Choose the SSL Type')),
                 array(!$row_exists($categories, $new_cat_id), 'new_cat_id', _('Choose the Category')),
                 array(!$row_exists($ip_addresses, $new_ip_id), 'new_ip_id', _('Choose the IP Address'))) as $menu_check) {

        if ($menu_check[0]) {

            $form->addFieldError($menu_check[1], $menu_check[2]);
            $menus_valid = false;

        }

    }

    if ($list_valid && $menus_valid) {

        // With a chosen account, the owner is the account's (addHosts() reads it), so a certificate can't end up with one
        // owner and another owner's account
        $choices = array('owner_id' => $new_owner_id, 'account_id' => $new_account_id, 'type_id' => $new_type_id,
            'cat_id' => $new_cat_id, 'ip_id' => $new_ip_id);
        $outcome = $ssl_queue->addHosts($host_array, $choices, false, $_SESSION['s_user_id']);

        if ($outcome === 'added') {

            $_SESSION['s_message_success'] .= _('Hostnames Added To Queue') . '<BR>';

            header('Location: ' . $web_root . '/queue/');
            exit;

        } elseif ($outcome === 'full') {

            $waiting = $ssl_queue->countUnfinished();
            $_SESSION['s_message_danger'] .= sprintf(ngettext('The SSL Queue already has %s hostname waiting. Please wait for some to finish before adding more.',
                'The SSL Queue already has %s hostnames waiting. Please wait for some to finish before adding more.', $waiting), number_format($waiting)) . '<BR>';

        } else {

            $_SESSION['s_message_danger'] .= _('Unable to add hostnames to the queue') . '<BR>';

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
if (!$ssl_queue_available) {

    echo '<p>' . _('SSL imports become available after the next database update.') . '</p>';
    echo '<div class="domainmod-back-next">' . $layout->linkButton($web_root . '/queue/', _('Go Back'), 'secondary', 'bi-arrow-left') . '</div>';

} else {

    // TRANSLATORS: Both %1$s are the app's name, DomainMOD.
    echo '<p class="domainmod-intro">' . sprintf(_("Paste a list of hostnames, and %1\$s connects to each site, reads its SSL certificate, and adds it. New certificates are linked to their domain when that domain is in %1\$s, and a hostname whose domain is in the Domain Queue waits for it first. Only port 443 is checked, and only public sites can be reached."), SOFTWARE_TITLE) . '</p>';
    // TRANSLATORS: The two %s wrap the link to the SSL Queue's page in the User Guide and must stay.
    echo '<p>' . sprintf(_('See the %sSSL Queue page%s in the User Guide.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/ssl-queue/">', '</a>') . '</p>';

    echo $form->showFormTop('');

    echo $form->showDropdownTop('new_account_id', _('SSL Provider Account'), '', '', '');
    if ($new_account_id !== 0) echo $stale_option($accounts, $new_account_id, -1);
    echo $form->showDropdownOption(0, _("Match each certificate's issuer (recommended)"), $new_account_id);
    foreach ($accounts as $row) {
        echo $form->showDropdownOption($row->id, $row->sslp_name . ', ' . $row->o_name . ' (' . $row->username . ')', $new_account_id);
    }
    echo $form->showDropdownBottom('', 'new_account_id');

    echo $form->showDropdownTop('new_owner_id', _('Owner'), _("Used when each certificate's issuer is matched. A chosen account's own owner is used instead."), '1', '');
    echo $stale_option($owners, $new_owner_id, 0);
    foreach ($owners as $row) {
        echo $form->showDropdownOption($row->id, $row->name, $new_owner_id);
    }
    echo $form->showDropdownBottom('', 'new_owner_id');

    // A browser drops one line break straight after <textarea>, so a list that starts with a blank line gets another,
    // keeping its line numbers the same as the messages'
    $host_list_shown = $unsanitize->text($raw_host_list);
    if (preg_match('/^(\r\n|\r|\n)/', $host_list_shown)) $host_list_shown = "\n" . $host_list_shown;
    echo $form->showInputTextarea('raw_host_list', sprintf(_('Hostnames to add (one per line, up to %s)'), number_format($max_hosts)),
        _('Each line can be a hostname, such as example.com, or an https:// address.'), $host_list_shown, '1', '', '', '', 'ltr');

    // The rest of the defaults in a box that starts closed, so the page stays short (it opens when one has a mistake)
    $defaults_error = ($form->errorClass('new_type_id') . $form->errorClass('new_cat_id') . $form->errorClass('new_ip_id')) !== ''; ?>
    <details class="card mb-3"<?php echo $defaults_error ? ' open' : ''; ?>>
        <summary class="card-header fw-semibold"><?php echo _('Defaults for New Certificates'); ?></summary>
        <div class="card-body pb-0"><?php

        echo $form->showDropdownTop('new_type_id', _('Certificate Type'), '', '1', '');
        echo $stale_option($types, $new_type_id, 0);
        foreach ($types as $row) {
            echo $form->showDropdownOption($row->id, $row->type, $new_type_id);
        }
        echo $form->showDropdownBottom('', 'new_type_id');

        echo $form->showDropdownTop('new_cat_id', _('Category'), '', '1', '');
        echo $stale_option($categories, $new_cat_id, 0);
        foreach ($categories as $row) {
            echo $form->showDropdownOption($row->id, $row->name, $new_cat_id);
        }
        echo $form->showDropdownBottom('', 'new_cat_id');

        echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '1', '');
        echo $stale_option($ip_addresses, $new_ip_id, 0);
        foreach ($ip_addresses as $row) {
            echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $new_ip_id);
        }
        echo $form->showDropdownBottom('', 'new_ip_id'); ?>

        </div>
    </details><?php

    // Go Back and the main button in one row, Back on the start side (css/base.css)
    echo '<div class="domainmod-back-next">' . $layout->linkButton($web_root . '/queue/', _('Go Back'), 'secondary', 'bi-arrow-left');
    echo $form->showSubmitButton(_('Add SSL Certificates'), '', '');
    echo '</div>';

    echo $form->showFormBottom('');

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
