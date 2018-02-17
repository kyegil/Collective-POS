<?php 
include(dirname(__FILE__)."/access_user_class.php"); 

$act_password = new Access_user;

if (!empty($_GET['activate'])) { // this two variables are required for activating/updating the account/password
	if ($act_password->check_activation_password($_GET['activate'])) { // the activation/validation method 
		$_SESSION['activation'] = $_GET['activate']; // put the activation string into a session or into a hdden field
	} 
}
if (isset($_POST['Submit'])) {
	if ($act_password->activate_new_password($_POST['password'], $_POST['confirm'], $_SESSION['activation'])) { // this will change the password
		unset($_SESSION['activation']);
	}
	$act_password->user = $_POST['user']; // to hold the user name in this screen (new in version > 1.77)
} 
$error = $act_password->the_msg;
?>

<?="<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
	<meta http-equiv="Refresh" content="60; url=../">
<title>Password Activation</title>
	<link rel="stylesheet" type="text/css" href="/stylesheet.css" media="screen" />
</head>

<body style="color: rgb(0, 0, 0); background-color: rgb(210, 210, 180); text-align: center;">
<table style="height: 100%; text-align: left; margin-left: auto; margin-right: auto;" border="0" cellpadding="0" cellspacing="0">
<tbody>
	<tr>
		<td style="height: 100px"></td>
	</tr>
	<tr>
		<td style="width: 300px;">
		<?php if (isset($_SESSION['activation'])) { ?>
		<h2>Please set your password:</h2>
		<p>Enter your preferred password<br />(for login <b><?php echo $act_password->user; ?></b>).</p>
		<form name="form1" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
		  <label for="password">Preferred password:</label>
		  <input type="password" name="password" value="<?php echo (isset($_POST['password'])) ? $_POST['password'] : ""; ?>"><br />
		  <label for="confirm">Re-enter password:</label>
		  <input type="password" name="confirm" value="<?php echo (isset($_POST['confirm'])) ? $_POST['confirm'] : ""; ?>">
		  <input type="hidden" name="user" value="<?php echo $act_password->user; ?>"><br />
		  <input type="submit" name="Submit">
		</form>
		<?php } else { ?>
		<h2>Done!</h2>
		<?php } ?>
		<p style="color:#FF0000;"><b><?php echo (isset($error)) ? $error : "&nbsp;"; ?></b></p>
		<p>&nbsp;</p>
		<!-- Notice! you have to change this links here, if the files are not in the same folder -->
		<p><a href="<?php echo $act_password->login_page; ?>">Log in</a></p>
		</td>
	</tr>
</tbody>
</table>
</body>
</html>
