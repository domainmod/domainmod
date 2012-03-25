<?php
session_start();

// is the one accessing this page logged in or not?
if ($_SESSION['is_logged_in'] == true) {

    if (isset($_SESSION['session_running_login_checks'])) {
		header("Location: /_includes/login-checks/main.inc.php");
	    exit;

	}
	
	// not logged in, move to login page
	header("Location: /members/main.php");
    exit;
}
?>