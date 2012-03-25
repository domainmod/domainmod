<?php
session_start();

// $full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/auth/auth-check.inc.php";
// include("$full_include");

$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/auth/auth-check.inc.php";
include("$full_include");
$full_include = "";


$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/settings/members.inc.php";
include("$full_include");
$full_include = "";
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/database.inc.php";
include("$full_include");
$full_include = "";
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/top.inc.php";
include("$full_include");
$full_include = "";

if ($submitted == "YES" && $new_email_address != "" && $new_password != "") {

   // check if the user id and password combination exist in database
   $sql = "SELECT a.email_address 
           FROM auth as a, members as m
           WHERE a.id = m.auth_id
		   		 AND a.email_address = '$new_email_address' 
                 AND a.password = PASSWORD('$new_password')
				 AND m.active = '1'";

  // right here
  // if don't match, give error message and send to login page again
   
   $result = mysql_query($sql,$connection) 
             or die('Query failed. 1. ' . mysql_error()); 

   if (mysql_num_rows($result) == 1) {

	   $sql2 = "SELECT a.member_id, a.email_address, a.admin, md.account_type_id, md.bulk_account_type_id, md.first_name, md.last_name, md.dob, md.referral_type, md.bulk_tags_remaining, md.help_mode, md.getting_started_mode, at.number_of_tags, at.expiry_date_length
           FROM auth as a, members as m, member_data as md, account_types as at
           WHERE a.id = m.auth_id 
		   and m.id = md.member_id 
		   and md.account_type_id = at.account_type_id
		   and a.email_address = '$new_email_address' 
		   AND a.password = PASSWORD('$new_password')
		   and m.active = '1'";

	   $result2 = mysql_query($sql2,$connection) 
             or die('Query failed. 2. ' . mysql_error()); 
			 
		while ($row2 = mysql_fetch_object($result2)) {
		
			if ($row2->help_mode == "1") { $temp_help_mode = "on"; } else { $temp_help_mode = "off"; }
			if ($row2->getting_started_mode == "1") { $temp_getting_started_mode = "on"; } else { $temp_getting_started_mode = "off"; }

			$_SESSION['session_member_id_what2'] = "$row2->member_id";
			$_SESSION['session_is_admin'] = "$row2->admin";
			$_SESSION['session_account_type_id'] = "$row2->account_type_id";

			$_SESSION['session_number_of_monthly_tags'] = "$row2->number_of_tags";
			$_SESSION['session_bulk_account_type_id'] = "$row2->bulk_account_type_id";
			$_SESSION['session_number_of_bulk_tags_remaining'] = "$row2->bulk_tags_remaining";
			$_SESSION['session_help_mode'] = "$temp_help_mode";
			$_SESSION['session_getting_started_mode'] = "$temp_getting_started_mode";
			$_SESSION['session_expiry_date_length_monthly'] = "$row2->expiry_date_length";
			$_SESSION['session_email_address'] = "$row2->email_address";
			$_SESSION['session_first_name'] = "$row2->first_name";
			$_SESSION['session_last_name'] = "$row2->last_name";
			$_SESSION['session_referral_type'] = "$row2->referral_type";

			function birthday ($birthday)
			  {
				list($year,$month,$day) = explode("-",$birthday);
				$year_diff  = date("Y") - $year;
				$month_diff = date("m") - $month;
				$day_diff   = date("d") - $day;
				if ($month_diff < 0) $year_diff--;
				elseif (($month_diff==0) && ($day_diff < 0)) $year_diff--;
				return $year_diff;
			  }
			
			if ($row2->dob != '0000-00-00') {

				$_SESSION['session_age'] = birthday("$row2->dob");
			
			} else {

				$_SESSION['session_age'] = "0";
			
			}
			 
			$sql = "select expiry_date_length
					from account_types
					where account_type_id = '$row2->bulk_account_type_id'
					and type = 'bulk'";
			$result = mysql_query($sql,$connection);

			while ($row = mysql_fetch_object($result)) {

				$temp_expiry_date_length = $row->expiry_date_length;

			}


			$_SESSION['session_expiry_date_length_bulk'] = $temp_expiry_date_length;

			
			if ($row2->bulk_account_type_id != "0" && $row2->bulk_account_type_id != "") {
			
				$_SESSION['session_has_bulk_tags'] = "true";
			
			}
			
		}

	      // the user id and password match, 
	      // set the session
	      $_SESSION['is_logged_in'] = "true";

	      if ($_SESSION['session_account_type_id'] > 1 && $_SESSION['session_account_type_id'] < 20) {
		  	$_SESSION['is_paid_account'] = "true";
		  	$_SESSION['is_free_account'] = "false";
		  } else {
		  	$_SESSION['is_free_account'] = "true";
		  	$_SESSION['is_paid_account'] = "false";
		  }

// 			echo "member id: " . $_SESSION['session_member_id_what2'] . "<BR><BR>";
// 			echo "email address: " . $_SESSION['session_email_address'] . "<BR><BR>";
// 			echo "first name: " . $_SESSION['session_first_name'] . "<BR><BR>";
// 			echo "free account: " . $_SESSION['is_free_account'] . "<BR><BR>";
// 			echo "paid account: " . $_SESSION['is_paid_account'] . "<BR><BR>";

// Log in activity table
$activity_action = "Login"; // Login, Update, Insert, etc.
$activity_action_detail = "User Has Logged In"; // Tagger Alias, Tag Welcome Message, etc.
$activity_member_id = ""; // Defaults to $_SESSION['session_member_id_what2'] if left blank
$activity_tag_id = "0"; // Defaults to $_SESSION['session_tag_id'] if left blank
$activity_message_id = "0"; // Defaults to $_SESSION['session_message_id'] if left blank
$activity_announcement_id = "0"; // Defaults to $_SESSION['session_announcement_id'] if left blank
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/activity/log-activity.inc.php";
include("$full_include");
$full_include = "";
      // after login we move to the main page

      header("Location: /_includes/login-checks/main.inc.php");
	  exit;

   } else {

		$_SESSION['session_result_message'] .= "Your login information is incorrect.<BR>";
//		$_SESSION['session_result_message'] .= "If you created your account before Saturday, January the 10th, 2009, you will need<BR>to <a href=\"https://anotag.com/members/reset-password.php\">reset your password</a> in order to take advantage of enhanced security features.<BR>";
		
   }


} else {

	if ($submitted == "YES") {
		if ($new_email_address == "" || $new_password == "") {
			if ($new_email_address == "") $_SESSION['session_result_message'] .= "Enter your email address<BR>";
			$new_password = "";
			if ($new_password == "") { 
			$_SESSION['session_result_message'] .= "Enter your password<BR>";
			}
		}
	}
}
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/top.inc.php";
include("$full_include");
$full_include = "";
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/head-tags.inc.php";
include("$full_include");
$full_include = "";
?></head>
<?php if ($new_email_address != "") { ?>
<body onLoad="document.forms[0].elements[1].focus()";>
<?php } else { ?>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php } ?>
  <?php
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/header.inc.php";
include("$full_include");
$full_include = "";
?>
<BR>
<center><font class="headline">Members Area</font></center><BR>
<?php 
		if (isset($_SESSION['session_result_message'])) {
		$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/result-message-table.inc.php";
		include("$full_include");
		$full_include = "";
		}
?><BR>
<center>
<?php 
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/copy/members/index.inc.php";
include("$full_include");
?>
</center>
<BR><BR>
<center><form id="login_form" name="login_form" method="post" action="<?=$_SERVER['PHP_SELF']?>">
<table width="60%" cellspacing="0" cellpadding="0" border="0">
<tr>
<td align="center" valign="top" width="50%">
<p>Email Address<BR>
    <input type="text" name="new_email_address" value="<?php echo $new_email_address; ?>" size="20">
</p>
</td>
<td align="center" valign="top" width="50%">
<p>Password<BR>
    <input type="password" id="new_password" name="new_password" size="20"><br>
<font size="1"><i>(<a href="reset-password.php"><i>Forgot your Password?</i></a>)</i></font>
</p>
</td>
</tr>
</table><BR>
<p>
  <input type="image" value="submit" src="/_images/layout/buttons/login.jpg">
  <input type="hidden" name="submitted" value="YES">
</p>
</form></center><BR>
<?php
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/footer.inc.php";
include("$full_include");
$full_include = "";
?>
</body>
</html>