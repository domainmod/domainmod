<?php
session_start();

$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/current-timestamp.inc.php";
include("$full_include");
$full_include = "";

$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/auth/auth-check.inc.php";
include("$full_include");
$full_include = "";
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/database.inc.php";
include("$full_include");
$full_include = "";

if ($pk != "") {
	$sql = "select id
			from tags
			where public_key = '$pk'";
	$result = mysql_query($sql,$connection);
	while ($row = mysql_fetch_object($result)) { $id = $row->id; }
}

if ($id != "") {

	$sql = "select id
			from tags
			where id = '$id'
			and tagee_id = '" . $_SESSION['session_member_id_what2'] . "'
			and active = '1'";
	$result = mysql_query($sql,$connection);
	
	if (mysql_num_rows($result) == '0') {

		$_SESSION['session_result_message'] .= "The Tag you're trying to access is invalid<BR>";
		$_SESSION['session_result_message'] .= "If the problem persists, please <a href=\"/contact/\">contact support</a><BR>";
		
		header("Location: main.php");
		exit;
		
	}

	$_SESSION['session_tag_id'] = $id;

} else {

	exit;

}

$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/settings/members.inc.php";
include("$full_include");
$full_include = "";
// $full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/database.inc.php";
// include("$full_include");

// if ($submitted == "YES" && $new_alias != "") {
if ($submitted_ALIAS == "YES") {

	if ($new_alias == "") {

		$_SESSION['session_result_message'] .= "Your alias has been cleared<BR>";
//		header("Location: edit-tag-tagger.php");
//		exit;
	
	}

   $sql = "select t.id
   		   from tags as t, tag_data as td
		   where t.id = td.tag_id
		   and t.id = '" . $_SESSION['session_tag_id'] . "'
		   and t.tagee_id = '" . $_SESSION['session_member_id_what2'] . "'
		   and t.active = '1'";
   $result = mysql_query($sql,$connection) or die(mysql_error());

   if (mysql_num_rows($result) == 1) {

	   // check if the user id and password combination exist in database
	   $sql = "update tag_data 
	   			set tag_alias_tagee = '$new_alias', 
					update_time = '$current_timestamp', update_ip = '" . $_SERVER['REMOTE_ADDR'] . "'
				where tag_id = '" . $_SESSION['session_tag_id'] . "'";

	   $result = mysql_query($sql,$connection) 
	             or die('Query failed. 4. ' . mysql_error()); 

// Log in activity table
$activity_action = "Update"; // Login, Update, Insert, etc.
$activity_action_detail = "Tag Data: Tagee Alias, Update Time & IP"; // Tagger Alias, Tag Welcome Message, etc.
$activity_member_id = ""; // Defaults to $_SESSION['session_member_id_what2'] if left blank
$activity_tag_id = ""; // Defaults to $_SESSION['session_tag_id'] if left blank
$activity_message_id = "0"; // Defaults to $_SESSION['session_message_id'] if left blank
$activity_announcement_id = "0"; // Defaults to $_SESSION['session_announcement_id'] if left blank
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/activity/log-activity.inc.php";
include("$full_include");
$full_include = "";

	   $sql = "update tags 
	   			set update_time = '$current_timestamp', update_ip = '" . $_SERVER['REMOTE_ADDR'] . "'
				where id = '" . $_SESSION['session_tag_id'] . "'";

	   $result = mysql_query($sql,$connection) 
	             or die('Query failed. 4. ' . mysql_error()); 

// Log in activity table
$activity_action = "Update"; // Login, Update, Insert, etc.
$activity_action_detail = "Tag: Tag Update Time & IP"; // Tagger Alias, Tag Welcome Message, etc.
$activity_member_id = ""; // Defaults to $_SESSION['session_member_id_what2'] if left blank
$activity_tag_id = ""; // Defaults to $_SESSION['session_tag_id'] if left blank
$activity_message_id = "0"; // Defaults to $_SESSION['session_message_id'] if left blank
$activity_announcement_id = "0"; // Defaults to $_SESSION['session_announcement_id'] if left blank
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/activity/log-activity.inc.php";
include("$full_include");
$full_include = "";

		if ($new_alias != "") {
			$_SESSION['session_result_message'] .= "Your alias was saved<BR>";
		}
	
//		header("Location: edit-tag-tagger.php");
//		exit;	

	$new_alias = stripslashes($new_alias);

	} else {

		exit;

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
<body>
  <?php
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/header.inc.php";
include("$full_include");
$full_include = "";
?>
<?php
$sql = "select public_key
		from tags
		where id = '" . $_SESSION['session_tag_id'] . "'
		and tagee_id = '" . $_SESSION['session_member_id_what2'] . "'
		and active = '1'";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	$full_tag_id = $row->public_key;
}
?>
<BR><center>
  <font class="headline">Editting Tag <?php echo "#$full_tag_id"; ?></font>
</center>
<?php
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/menus/tag-menu.inc.php";
include("$full_include");
$full_include = "";
?>
  <?php 
		if (isset($_SESSION['session_result_message'])) {
		$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/result-message-table.inc.php";
		include("$full_include");
		$full_include = "";
		}
?><BR>
  <a name="alias"></a><font class="subheadline">Enter an Alias for this Tag</font><BR><BR>
<?php 
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/copy/members/edit-tag-tagee.inc.php";
include("$full_include");
?>
<?php
if ($submitted_ALIAS != "YES") {
   $sql = "select t.id
   		   from tags as t, tag_data as td
		   where t.id = td.tag_id
		   and t.id = '" . $_SESSION['session_tag_id'] . "'
		   and t.tagee_id = '" . $_SESSION['session_member_id_what2'] . "'
		   and t.active = '1'";
   $result = mysql_query($sql,$connection) or die(mysql_error());

   if (mysql_num_rows($result) == 1) {
   
   		$sql2 = "select td.tag_alias_tagee
				 from tags as t, tag_data as td
				 where t.id = td.tag_id
				 and t.id = '" . $_SESSION['session_tag_id'] . "'
				 and t.tagee_id = '" . $_SESSION['session_member_id_what2'] . "'
				 and t.active = '1'";

		$result2 = mysql_query($sql2,$connection) or die(mysql_error());
		
		while ($row2 = mysql_fetch_object($result2)) {
			$new_alias = $row2->tag_alias_tagee;
		}

   } else {
   
   exit;
   
   }

}
?>
<form id="new_alias_form" name="new_alias_form" method="post" action="<?=$_SERVER['PHP_SELF']?>">
  
    <BR><input name="new_alias" type="text" value="<?php echo $new_alias; ?>" size="55" maxlength="100">&nbsp;<i>(Maximum 100 Characters)</i><BR><BR><BR>
    <input type="image" value="submit" src="/_images/layout/buttons/save-alias.jpg">
    <input type="hidden" name="submitted_ALIAS" value="YES">
    <input type="hidden" name="pk" value="<?php echo $pk; ?>">
  
</form>

<BR>
<?php
$full_include = $_SERVER["DOCUMENT_ROOT"] . "/_includes/footer.inc.php";
include("$full_include");
$full_include = "";
?>

</body>
</html>