<?php
/**
 * /assets/add/dns.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "Adding A New DNS Profile";
$software_section = "dns-add";

// Form Variables
$new_name = $_POST['new_name'];
$new_notes = $_POST['new_notes'];
$new_dns1 = $_POST['new_dns1'];
$new_dns2 = $_POST['new_dns2'];
$new_dns3 = $_POST['new_dns3'];
$new_dns4 = $_POST['new_dns4'];
$new_dns5 = $_POST['new_dns5'];
$new_dns6 = $_POST['new_dns6'];
$new_dns7 = $_POST['new_dns7'];
$new_dns8 = $_POST['new_dns8'];
$new_dns9 = $_POST['new_dns9'];
$new_dns10 = $_POST['new_dns10'];
$new_ip1 = $_POST['new_ip1'];
$new_ip2 = $_POST['new_ip2'];
$new_ip3 = $_POST['new_ip3'];
$new_ip4 = $_POST['new_ip4'];
$new_ip5 = $_POST['new_ip5'];
$new_ip6 = $_POST['new_ip6'];
$new_ip7 = $_POST['new_ip7'];
$new_ip8 = $_POST['new_ip8'];
$new_ip9 = $_POST['new_ip9'];
$new_ip10 = $_POST['new_ip10'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_name != '' && $new_dns1 != "" && $new_dns2 != "") {

        $new_number_of_servers = 10;

        if ($new_dns10 == '') { $new_number_of_servers = '9'; }
        if ($new_dns9 == '') { $new_number_of_servers = '8'; }
        if ($new_dns8 == '') { $new_number_of_servers = '7'; }
        if ($new_dns7 == '') { $new_number_of_servers = '6'; }
        if ($new_dns6 == '') { $new_number_of_servers = '5'; }
        if ($new_dns5 == '') { $new_number_of_servers = '4'; }
        if ($new_dns4 == '') { $new_number_of_servers = '3'; }
        if ($new_dns3 == '') { $new_number_of_servers = '2'; }
        if ($new_dns2 == '') { $new_number_of_servers = '1'; }
        if ($new_dns1 == '') { $new_number_of_servers = '0'; }

        $query = "INSERT INTO dns
                  (`name`, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3, ip4, ip5, ip6,
                   ip7, ip8, ip9, ip10, notes, number_of_servers, insert_time)
                   VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('ssssssssssssssssssssssss', $new_name, $new_dns1, $new_dns2, $new_dns3, $new_dns4, $new_dns5,
                $new_dns6, $new_dns7, $new_dns8, $new_dns9, $new_dns10, $new_ip1, $new_ip2, $new_ip3, $new_ip4,
                $new_ip5, $new_ip6, $new_ip7, $new_ip8, $new_ip9, $new_ip10, $new_notes, $new_number_of_servers,
                $timestamp);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

        $_SESSION['result_message'] = "DNS Profile <font class=\"highlight\">" . $new_name . "</font> Added<BR>";

        header("Location: ../dns.php");
        exit;

    } else {

        if ($new_name == "") {
            $_SESSION['result_message'] .= "Please enter a name for the DNS profile<BR>";
        }
        if ($new_dns1 == "" || $new_dns2 == "") {
            $_SESSION['result_message'] .= "Please enter at least two DNS servers<BR>";
        }

    }

}
?>
<?php echo $system->doctype(); ?>
<html>
<head>
<title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_dns_form" method="post">
<strong>Profile Name</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a>
    <BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?php echo $new_name; ?>">
<BR><BR>
<table class="dns_table">
    <tr>
    	<td class="dns_table_left">
            <strong>DNS Server 1</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
                </font></a><BR><BR>
            <input name="new_dns1" type="text" size="28" maxlength="255" value="<?php echo $new_dns1; ?>">
        </td>
    	<td class="dns_table_right">
            <strong>IP Address 1</strong><BR><BR>
            <input name="new_ip1" type="text" size="28" maxlength="255" value="<?php echo $new_ip1; ?>">
        </td>
    </tr>
    <tr>
    	<td class="dns_table_left">
            <strong>DNS Server 2</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
                </font></a><BR><BR>
            <input name="new_dns2" type="text" size="28" maxlength="255" value="<?php echo $new_dns2; ?>">
        </td>
    	<td class="dns_table_right">
            <strong>IP Address 2</strong><BR><BR>
            <input name="new_ip2" type="text" size="28" maxlength="255" value="<?php echo $new_ip2; ?>">
        </td>
    </tr>
    <tr>
    	<td class="dns_table_left">
            <strong>DNS Server 3</strong><BR><BR>
            <input name="new_dns3" type="text" size="28" maxlength="255" value="<?php echo $new_dns3; ?>">
        </td>
    	<td class="dns_table_right">
            <strong>IP Address 3</strong><BR><BR>
            <input name="new_ip3" type="text" size="28" maxlength="255" value="<?php echo $new_ip3; ?>">
        </td>
    </tr>
    <tr>
    	<td class="dns_table_left">
            <strong>DNS Server 4</strong><BR><BR>
            <input name="new_dns4" type="text" size="28" maxlength="255" value="<?php echo $new_dns4; ?>">
        </td>
    	<td class="dns_table_right">
            <strong>IP Address 4</strong><BR><BR>
            <input name="new_ip4" type="text" size="28" maxlength="255" value="<?php echo $new_ip4; ?>">
        </td>
    </tr>
    <tr>
    	<td class="dns_table_left">
            <strong>DNS Server 5</strong><BR><BR>
            <input name="new_dns5" type="text" size="28" maxlength="255" value="<?php echo $new_dns5; ?>">
        </td>
    	<td class="dns_table_right">
            <strong>IP Address 5</strong><BR><BR>
            <input name="new_ip5" type="text" size="28" maxlength="255" value="<?php echo $new_ip5; ?>">
        </td>
    </tr>
    <tr>
    	<td class="dns_table_left">
            <strong>DNS Server 6</strong><BR><BR>
            <input name="new_dns6" type="text" size="28" maxlength="255" value="<?php echo $new_dns6; ?>">
        </td>
    	<td class="dns_table_right">
            <strong>IP Address 6</strong><BR><BR>
            <input name="new_ip6" type="text" size="28" maxlength="255" value="<?php echo $new_ip6; ?>">
        </td>
    </tr>
    <tr>
    	<td class="dns_table_left">
            <strong>DNS Server 7</strong><BR><BR>
            <input name="new_dns7" type="text" size="28" maxlength="255" value="<?php echo $new_dns7; ?>">
        </td>
    	<td class="dns_table_right">
            <strong>IP Address 7</strong><BR><BR>
            <input name="new_ip7" type="text" size="28" maxlength="255" value="<?php echo $new_ip7; ?>">
        </td>
    </tr>
    <tr>
    	<td class="dns_table_left">
            <strong>DNS Server 8</strong><BR><BR>
            <input name="new_dns8" type="text" size="28" maxlength="255" value="<?php echo $new_dns8; ?>">
        </td>
    	<td class="dns_table_right">
            <strong>IP Address 8</strong><BR><BR>
            <input name="new_ip8" type="text" size="28" maxlength="255" value="<?php echo $new_ip8; ?>">
        </td>
    </tr>
    <tr>
    	<td class="dns_table_left">
            <strong>DNS Server 9</strong><BR><BR>
            <input name="new_dns9" type="text" size="28" maxlength="255" value="<?php echo $new_dns9; ?>">
        </td>
    	<td class="dns_table_right">
            <strong>IP Address 9</strong><BR><BR>
            <input name="new_ip9" type="text" size="28" maxlength="255" value="<?php echo $new_ip9; ?>">
        </td>
    </tr>
    <tr>
    	<td class="dns_table_left">
            <strong>DNS Server 10</strong><BR><BR>
            <input name="new_dns10" type="text" size="28" maxlength="255" value="<?php echo $new_dns10; ?>">
        </td>
    	<td class="dns_table_right">
            <strong>IP Address 10</strong><BR><BR>
            <input name="new_ip10" type="text" size="28" maxlength="255" value="<?php echo $new_ip10; ?>">
        </td>
    </tr>
</table>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<BR><BR>
<input type="submit" name="button" value="Add This DNS Profile &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
