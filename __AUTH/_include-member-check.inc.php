<?php
session_start();

// is the one accessing this page logged in or not?
if ($_SESSION['is_logged_in'] != true) {

	$_SESSION['session_member_redirect'] = "http://anotag.com" . $_SERVER["REQUEST_URI"];

	$_SESSION['session_result_message'] .= "You must be logged in to access this area<BR>";

    // not logged in, move to login page
	header("Location: /members/index.php");
    exit;
}
?>