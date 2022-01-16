<?php
/**
 * /settings/defaults/index.php
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

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/settings-defaults.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$new_default_category_domains = $_POST['new_default_category_domains'];
$new_default_category_ssl = $_POST['new_default_category_ssl'];
$new_default_dns = $_POST['new_default_dns'];
$new_default_host = $_POST['new_default_host'];
$new_default_ip_address_domains = $_POST['new_default_ip_address_domains'];
$new_default_ip_address_ssl = $_POST['new_default_ip_address_ssl'];
$new_default_owner_domains = $_POST['new_default_owner_domains'];
$new_default_owner_ssl = $_POST['new_default_owner_ssl'];
$new_default_registrar = $_POST['new_default_registrar'];
$new_default_registrar_account = $_POST['new_default_registrar_account'];
$new_default_ssl_provider_account = $_POST['new_default_ssl_provider_account'];
$new_default_ssl_type = $_POST['new_default_ssl_type'];
$new_default_ssl_provider = $_POST['new_default_ssl_provider'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $_SESSION['s_message_success'] .= _('Your Defaults were updated') . '<BR>';

    $stmt = $pdo->prepare("
        SELECT *
        FROM user_settings
        WHERE user_id = :user_id");
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $saved_default_category_domains = $result->default_category_domains;
        $saved_default_category_ssl = $result->default_category_ssl;
        $saved_default_dns = $result->default_dns;
        $saved_default_host = $result->default_host;
        $saved_default_ip_address_domains = $result->default_ip_address_domains;
        $saved_default_ip_address_ssl = $result->default_ip_address_ssl;
        $saved_default_owner_domains = $result->default_owner_domains;
        $saved_default_owner_ssl = $result->default_owner_ssl;
        $saved_default_registrar = $result->default_registrar;
        $saved_default_registrar_account = $result->default_registrar_account;
        $saved_default_ssl_provider_account = $result->default_ssl_provider_account;
        $saved_default_ssl_type = $result->default_ssl_type;
        $saved_default_ssl_provider = $result->default_ssl_provider;

    }

    $stmt = $pdo->prepare("
        UPDATE user_settings
        SET default_category_domains = :new_default_category_domains,
            default_category_ssl = :new_default_category_ssl,
            default_dns = :new_default_dns,
            default_host = :new_default_host,
            default_ip_address_domains = :new_default_ip_address_domains,
            default_ip_address_ssl = :new_default_ip_address_ssl,
            default_owner_domains = :new_default_owner_domains,
            default_owner_ssl = :new_default_owner_ssl,
            default_registrar = :new_default_registrar,
            default_registrar_account = :new_default_registrar_account,
            default_ssl_provider_account = :new_default_ssl_provider_account,
            default_ssl_type = :new_default_ssl_type,
            default_ssl_provider = :new_default_ssl_provider,
            update_time = :timestamp
        WHERE user_id = :user_id");
    $stmt->bindValue('new_default_category_domains', $new_default_category_domains, PDO::PARAM_INT);
    $stmt->bindValue('new_default_category_ssl', $new_default_category_ssl, PDO::PARAM_INT);
    $stmt->bindValue('new_default_dns', $new_default_dns, PDO::PARAM_INT);
    $stmt->bindValue('new_default_host', $new_default_host, PDO::PARAM_INT);
    $stmt->bindValue('new_default_ip_address_domains', $new_default_ip_address_domains, PDO::PARAM_INT);
    $stmt->bindValue('new_default_ip_address_ssl', $new_default_ip_address_ssl, PDO::PARAM_INT);
    $stmt->bindValue('new_default_owner_domains', $new_default_owner_domains, PDO::PARAM_INT);
    $stmt->bindValue('new_default_owner_ssl', $new_default_owner_ssl, PDO::PARAM_INT);
    $stmt->bindValue('new_default_registrar', $new_default_registrar, PDO::PARAM_INT);
    $stmt->bindValue('new_default_registrar_account', $new_default_registrar_account, PDO::PARAM_INT);
    $stmt->bindValue('new_default_ssl_provider_account', $new_default_ssl_provider_account, PDO::PARAM_INT);
    $stmt->bindValue('new_default_ssl_type', $new_default_ssl_type, PDO::PARAM_INT);
    $stmt->bindValue('new_default_ssl_provider', $new_default_ssl_provider, PDO::PARAM_INT);
    $timestamp = $time->stamp();
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['s_default_category_domains'] = $new_default_category_domains;
    $_SESSION['s_default_category_ssl'] = $new_default_category_ssl;
    $_SESSION['s_default_dns'] = $new_default_dns;
    $_SESSION['s_default_host'] = $new_default_host;
    $_SESSION['s_default_ip_address_domains'] = $new_default_ip_address_domains;
    $_SESSION['s_default_ip_address_ssl'] = $new_default_ip_address_ssl;
    $_SESSION['s_default_owner_domains'] = $new_default_owner_domains;
    $_SESSION['s_default_owner_ssl'] = $new_default_owner_ssl;
    $_SESSION['s_default_registrar'] = $new_default_registrar;
    $_SESSION['s_default_registrar_account'] = $new_default_registrar_account;
    $_SESSION['s_default_ssl_provider_account'] = $new_default_ssl_provider_account;
    $_SESSION['s_default_ssl_type'] = $new_default_ssl_type;
    $_SESSION['s_default_ssl_provider'] = $new_default_ssl_provider;

    header("Location: index.php");
    exit;

} else {

    $stmt = $pdo->prepare("
        SELECT *
        FROM user_settings
        WHERE user_id = :user_id");
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll();

    foreach ($result as $row) {

        $new_default_category_domains = $row->default_category_domains;
        $new_default_category_ssl = $row->default_category_ssl;
        $new_default_dns = $row->default_dns;
        $new_default_host = $row->default_host;
        $new_default_ip_address_domains = $row->default_ip_address_domains;
        $new_default_ip_address_ssl = $row->default_ip_address_ssl;
        $new_default_owner_domains = $row->default_owner_domains;
        $new_default_owner_ssl = $row->default_owner_ssl;
        $new_default_registrar = $row->default_registrar;
        $new_default_registrar_account = $row->default_registrar_account;
        $new_default_ssl_provider_account = $row->default_ssl_provider_account;
        $new_default_ssl_type = $row->default_ssl_type;
        $new_default_ssl_provider = $row->default_ssl_provider;

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

<h3><?php echo _('Domain Defaults'); ?></h3><?php

echo $form->showFormTop('');

echo $form->showDropdownTop('new_default_registrar', _('Default Domain Registrar'), '', '', '');
$result = $pdo->query("
    SELECT id, `name`
    FROM registrars
    ORDER BY name")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_default_registrar']);

}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_registrar_account', _('Default Domain Registrar Account'), '', '', '');
$result = $pdo->query("
    SELECT ra.id, ra.username, r.name AS r_name, o.name AS o_name
    FROM registrars AS r, registrar_accounts AS ra, owners AS o
    WHERE r.id = ra.registrar_id
      AND ra.owner_id = o.id
    ORDER BY r.name, o.name, ra.username")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->r_name . ' :: ' . $row->o_name . ' :: ' . $row->username, $_SESSION['s_default_registrar_account']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_dns', _('Default DNS Profile'), '', '', '');
$result = $pdo->query("
    SELECT id, `name`
    FROM dns
    ORDER BY name")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_default_dns']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_host', _('Default Web Hosting Provider'), '', '', '');
$result = $pdo->query("
    SELECT id, `name`
    FROM hosting
    ORDER BY name")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_default_host']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_ip_address_domains', _('Default IP Address'), '', '', '');
$result = $pdo->query("
    SELECT id, ip, `name`
    FROM ip_addresses
    ORDER BY name")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $_SESSION['s_default_ip_address_domains']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_category_domains', _('Default Category'), '', '', '');
$result = $pdo->query("
    SELECT id, `name`
    FROM categories
    ORDER BY name")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_default_category_domains']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_owner_domains', _('Default Account Owner'), '', '', '');
$result = $pdo->query("
    SELECT id, `name`
    FROM owners
    ORDER BY name")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_default_owner_domains']);
}
echo $form->showDropdownBottom('<BR>'); ?>


<h3>SSL Defaults</h3><?php

echo $form->showDropdownTop('new_default_ssl_provider', _('Default SSL Provider'), '', '', '');
$result = $pdo->query("
    SELECT id, `name`
    FROM ssl_providers
    ORDER BY name")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_default_ssl_provider']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_ssl_provider_account', _('Default SSL Provider Account'), '', '', '');
$result = $pdo->query("
    SELECT sslpa.id, sslpa.username, sslp.name AS p_name, o.name AS o_name
    FROM ssl_providers AS sslp, ssl_accounts AS sslpa, owners AS o
    WHERE sslp.id = sslpa.ssl_provider_id
      AND sslpa.owner_id = o.id
    ORDER BY sslp.name, o.name, sslpa.username")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->p_name . ' :: ' . $row->o_name . ' :: ' . $row->username, $_SESSION['s_default_ssl_provider_account']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_ssl_type', _('Default SSL Type'), '', '', '');
$result = $pdo->query("
    SELECT id, type
    FROM ssl_cert_types
    ORDER BY type")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->type, $_SESSION['s_default_ssl_type']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_ip_address_ssl', _('Default IP Address'), '', '', '');
$result = $pdo->query("
    SELECT id, ip, `name`
    FROM ip_addresses
    ORDER BY name")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $_SESSION['s_default_ip_address_ssl']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_category_ssl', _('Default Category'), '', '', '');
$result = $pdo->query("
    SELECT id, `name`
    FROM categories
    ORDER BY name")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_default_category_ssl']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_owner_ssl', _('Default Account Owner'), '', '', '');
$result = $pdo->query("
    SELECT id, `name`
    FROM owners
    ORDER BY name")->fetchAll();
foreach ($result as $row) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_default_owner_ssl']);
}
echo $form->showDropdownBottom('');

echo $form->showSubmitButton(_('Update User Defaults'), '<BR>', '');

echo $form->showFormBottom('');
?>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
