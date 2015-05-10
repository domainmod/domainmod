<?php
/**
 * /system/admin/add/user.php
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
include("../../../_includes/start-session.inc.php");
include("../../../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
include(DIR_INC . "auth/admin-user-check.inc.php");
require_once(DIR_INC . "functions.inc.php");
include(DIR_INC . "timestamps/current-timestamp.inc.php");

spl_autoload_register('classAutoloader');

$error = new DomainMOD\Error();

$page_title = "Adding A New User";
$software_section = "admin-user-add";

// Form Variables
$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_username = $_POST['new_username'];
$new_email_address = $_POST['new_email_address'];
$new_admin = $_POST['new_admin'];
$new_active = $_POST['new_active'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != "" && $new_last_name != "" && $new_username != ""
    && $new_email_address != "") {

    $stmt = mysqli_stmt_init($connection);
	$query = "SELECT username FROM users WHERE username = ?";

	if (mysqli_stmt_prepare($stmt, $query)) {

	    mysqli_stmt_bind_param($stmt, "s", $new_username);
	    mysqli_stmt_execute($stmt);
	    mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {

            $existing_username = 1;

        }

        mysqli_stmt_close($stmt);

	} else { $error->outputSqlError($connection, "ERROR"); }

    if ($existing_username == 1) {

		$_SESSION['result_message'] .= "You have entered an invalid username<BR>";
		
	} else {

		$new_password = substr(md5(time()), 0, 8);

        $stmt = mysqli_stmt_init($connection);
		$query = "INSERT INTO users
                  (first_name, last_name, username, email_address, `password`, admin, active, insert_time)
                  VALUES
                  (?, ?, ?, ?, password(?), ?, ?, ?)";

		if (mysqli_stmt_prepare($stmt, $query)) {

		    mysqli_stmt_bind_param($stmt, "ssssssis", $new_first_name, $new_last_name, $new_username,
                $new_email_address, $new_password, $new_admin, $new_active, $current_timestamp);
		    mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

		} else { $error->outputSqlError($connection, "ERROR"); }

        $stmt = mysqli_stmt_init($connection);
        $query = "SELECT id
                  FROM users
                  WHERE first_name = ?
                    AND last_name = ?
                    AND insert_time = ?";

        if (mysqli_stmt_prepare($stmt, $query)) {

            mysqli_stmt_bind_param($stmt, "sss", $new_first_name, $new_last_name, $current_timestamp);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            mysqli_stmt_bind_result($stmt, $id);

            while (mysqli_stmt_fetch($stmt)) {

                $temp_user_id = $id;

            }

            mysqli_stmt_close($stmt);

        } else { $error->outputSqlError($connection, "ERROR"); }

        $stmt = mysqli_stmt_init($connection);
        $query = "INSERT INTO user_settings
                  (user_id,
                   default_currency,
                   default_category_domains,
                   default_category_ssl,
                   default_dns,
                   default_host,
                   default_ip_address_domains,
                   default_ip_address_ssl,
                   default_owner_domains,
                   default_owner_ssl,
                   default_registrar,
                   default_registrar_account,
                   default_ssl_provider,
                   default_ssl_provider_account,
                   default_ssl_type,
                   insert_time)
                   VALUES
                   (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if (mysqli_stmt_prepare($stmt, $query)) {

            $temp_currency = 'CAD';

            mysqli_stmt_bind_param($stmt, "isiiiiiiiiiiiiii",
                $temp_user_id,
                $temp_currency,
                $_SESSION['system_default_category_domains'],
                $_SESSION['system_default_category_ssl'],
                $_SESSION['system_default_dns'],
                $_SESSION['system_default_host'],
                $_SESSION['system_default_ip_address_domains'],
                $_SESSION['system_default_ip_address_ssl'],
                $_SESSION['system_default_owner_domains'],
                $_SESSION['system_default_owner_ssl'],
                $_SESSION['system_default_registrar'],
                $_SESSION['system_default_registrar_account'],
                $_SESSION['system_default_ssl_provider'],
                $_SESSION['system_default_ssl_provider_account'],
                $_SESSION['system_default_ssl_type'],
                $current_timestamp);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

        } else { $error->outputSqlError($connection, "ERROR"); }

        $_SESSION['result_message'] .= "User <font class=\"highlight\">" . $new_first_name . " " . $new_last_name . " ("
            . $new_username . " / " . $new_password . ")</font> Added<BR><BR>You can either manually email the above
            credentials to the user, or you can <a href=\"reset-password.php?new_username=" . $new_username . "\">click
            here</a> to have " . $software_title . " email them for you<BR><BR>";

		$temp_input_user_id = $temp_user_id;
		$temp_input_default_currency = 'CAD';
		include(DIR_INC . "system/update-conversion-rates.inc.php");

        header("Location: ../users.php");
        exit;

    }

} else {

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_first_name == "") $_SESSION['result_message'] .= "Enter the new user's first name<BR>";
		if ($new_last_name == "") $_SESSION['result_message'] .= "Enter the new user's last name<BR>";
		if ($new_username == "") $_SESSION['result_message'] .= "Enter the new user's username<BR>";
		if ($new_email_address == "") $_SESSION['result_message'] .= "Enter the new user's email address<BR>";
		
	}

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_user_form" method="post">
<strong>First Name (50)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_first_name" type="text" size="50" maxlength="50" value="<?php echo $new_first_name; ?>"><BR><BR>
<strong>Last Name (50)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_last_name" type="text" size="50" maxlength="50" value="<?php echo $new_last_name; ?>"><BR><BR>
<strong>Username (30)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_username" type="text" size="20" maxlength="30" value="<?php echo $new_username; ?>"><BR><BR>
<strong>Email Address (100)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_email_address" type="text" size="50" maxlength="100" value="<?php echo $new_email_address; ?>"><BR><BR>
<strong>Admin Privileges?</strong>&nbsp;
<select name="new_admin">
<option value="0"<?php if ($new_admin == 0) echo " selected"; ?>>No</option>
<option value="1"<?php if ($new_admin == 1) echo " selected"; ?>>Yes</option>
</select>
<BR><BR>
<strong>Active Account?</strong>&nbsp;
<select name="new_active">
<option value="0"<?php if ($new_active == 0) echo " selected"; ?>>No</option>
<option value="1"<?php if ($new_active == 1) echo " selected"; ?>>Yes</option>
</select>
<BR><BR>
<input type="submit" name="button" value="Add New User &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
