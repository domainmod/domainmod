<?php
session_start();

$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/auth/auth-check.inc.php";
include("$full_include");
$full_include = "";

header("Location: process/logout.php");
exit;
?>