<?php
/**
 * /admin/defaults/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once('../../_includes/start-session.inc.php');
require_once('../../_includes/init.inc.php');

require_once(DIR_ROOT . '/classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$form = new DomainMOD\Form();
$time = new DomainMOD\Time();

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/settings/admin-defaults.inc.php');
require_once(DIR_INC . '/database.inc.php');

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);

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

    $_SESSION['s_message_success'] .= "The System Defaults were updated<BR>";

    $query = "UPDATE settings
              SET default_category_domains = ?,
                  default_category_ssl = ?,
                  default_dns = ?,
                  default_host = ?,
                  default_ip_address_domains = ?,
                  default_ip_address_ssl = ?,
                  default_owner_domains = ?,
                  default_owner_ssl = ?,
                  default_registrar = ?,
                  default_registrar_account = ?,
                  default_ssl_provider_account = ?,
                  default_ssl_type = ?,
                  default_ssl_provider = ?,
                  update_time = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $timestamp = $time->stamp();

        $q->bind_param('iiiiiiiiiiiiis', $new_default_category_domains, $new_default_category_ssl, $new_default_dns,
            $new_default_host, $new_default_ip_address_domains, $new_default_ip_address_ssl,
            $new_default_owner_domains, $new_default_owner_ssl, $new_default_registrar, $new_default_registrar_account,
            $new_default_ssl_provider_account, $new_default_ssl_type, $new_default_ssl_provider, $timestamp);
        $q->execute();
        $q->close();

    } else $error->outputSqlError($dbcon, '1', 'ERROR');

    $_SESSION['s_system_default_category_domains'] = $new_default_category_domains;
    $_SESSION['s_system_default_category_ssl'] = $new_default_category_ssl;
    $_SESSION['s_system_default_dns'] = $new_default_dns;
    $_SESSION['s_system_default_host'] = $new_default_host;
    $_SESSION['s_system_default_ip_address_domains'] = $new_default_ip_address_domains;
    $_SESSION['s_system_default_ip_address_ssl'] = $new_default_ip_address_ssl;
    $_SESSION['s_system_default_owner_domains'] = $new_default_owner_domains;
    $_SESSION['s_system_default_owner_ssl'] = $new_default_owner_ssl;
    $_SESSION['s_system_default_registrar'] = $new_default_registrar;
    $_SESSION['s_system_default_registrar_account'] = $new_default_registrar_account;
    $_SESSION['s_system_default_ssl_provider_account'] = $new_default_ssl_provider_account;
    $_SESSION['s_system_default_ssl_type'] = $new_default_ssl_type;
    $_SESSION['s_system_default_ssl_provider'] = $new_default_ssl_provider;

    header("Location: ../index.php");
    exit;

}
?>
<?php require_once(DIR_INC . '/doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once(DIR_INC . '/layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . '/layout/header.inc.php'); ?>

<h3>Domain Defaults</h3><?php

echo $form->showFormTop('');

echo $form->showDropdownTop('new_default_registrar', 'Default Domain Registrar', '', '', '');
$sql = "SELECT id, `name`
        FROM registrars
        ORDER BY name";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_system_default_registrar']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_registrar_account', 'Default Domain Registrar Account', '', '', '');
$sql = "SELECT ra.id, ra.username, r.name AS r_name, o.name AS o_name
        FROM registrars AS r, registrar_accounts AS ra, owners AS o
        WHERE r.id = ra.registrar_id
          AND ra.owner_id = o.id
        ORDER BY r.name, o.name, ra.username";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->r_name . ' :: ' . $row->o_name . ' :: ' . $row->username, $_SESSION['s_system_default_registrar_account']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_dns', 'Default DNS Profile', '', '', '');
$sql = "SELECT id, `name`
        FROM dns
        ORDER BY name";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_system_default_dns']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_host', 'Default Web Hosting Provider', '', '', '');
$sql = "SELECT id, `name`
        FROM hosting
        ORDER BY name";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_system_default_host']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_ip_address_domains', 'Default IP Address', '', '', '');
$sql = "SELECT id, ip, `name`
        FROM ip_addresses
        ORDER BY name";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $_SESSION['s_system_default_ip_address_domains']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_category_domains', 'Default Category', '', '', '');
$sql = "SELECT id, `name`
        FROM categories
        ORDER BY name";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_system_default_category_domains']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_owner_domains', 'Default Account Owner', '', '', '');
$sql = "SELECT id, `name`
        FROM owners
        ORDER BY name";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_system_default_owner_domains']);
}
echo $form->showDropdownBottom('<BR>'); ?>


<h3>SSL Defaults</h3><?php

echo $form->showDropdownTop('new_default_ssl_provider', 'Default SSL Provider', '', '', '');
$sql = "SELECT id, `name`
        FROM ssl_providers
        ORDER BY name";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_system_default_ssl_provider']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_ssl_provider_account', 'Default SSL Provider Account', '', '', '');
$sql = "SELECT sslpa.id, sslpa.username, sslp.name AS p_name, o.name AS o_name
        FROM ssl_providers AS sslp, ssl_accounts AS sslpa, owners AS o
        WHERE sslp.id = sslpa.ssl_provider_id
          AND sslpa.owner_id = o.id
        ORDER BY sslp.name, o.name, sslpa.username";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->p_name . ' :: ' . $row->o_name . ' :: ' . $row->username, $_SESSION['s_system_default_ssl_provider_account']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_ssl_type', 'Default SSL Type', '', '', '');
$sql = "SELECT id, type
        FROM ssl_cert_types
        ORDER BY type";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->type, $_SESSION['s_system_default_ssl_type']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_ip_address_ssl', 'Default IP Address', '', '', '');
$sql = "SELECT id, ip, `name`
        FROM ip_addresses
        ORDER BY name";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $_SESSION['s_system_default_ip_address_ssl']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_category_ssl', 'Default Category', '', '', '');
$sql = "SELECT id, `name`
        FROM categories
        ORDER BY name";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_system_default_category_ssl']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_default_owner_ssl', 'Default Account Owner', '', '', '');
$sql = "SELECT id, `name`
        FROM owners
        ORDER BY name";
$result = mysqli_query($dbcon, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->id, $row->name, $_SESSION['s_system_default_owner_ssl']);
}
echo $form->showDropdownBottom('');

echo $form->showSubmitButton('Update System Defaults', '<BR>', '');

echo $form->showFormBottom('');
?>

<?php require_once(DIR_INC . '/layout/footer.inc.php'); ?>
</body>
</html>
