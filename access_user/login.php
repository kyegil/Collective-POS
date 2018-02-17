<?php 
include($_SERVER['DOCUMENT_ROOT']."/access_user/access_user_class.php"); 
include($_SERVER['DOCUMENT_ROOT']."/classes/sessions_manager.class.php"); 

$my_access = new SessionsManager(false);


// $my_access->language = "de"; // use this selector to get messages in other languages
if (isset($_GET['activate']) && isset($_GET['ident'])) { // this two variables are required for activating/updating the account/password
	$my_access->auto_activation = true; // use this (true/false) to stop the automatic activation
	$my_access->activate_account($_GET['activate'], $_GET['ident']); // the activation method 
}
if (isset($_GET['validate']) && isset($_GET['id'])) { // this two variables are required for activating/updating the new e-mail address
	$my_access->validate_email($_GET['validate'], $_GET['id']); // the validation method 
}
if (isset($_POST['Submit'])) {
	$my_access->save_login = "no"; // use a cookie to remember the login
	$my_access->count_visit = false; // if this is true then the last visitdate is saved in the database (field extra info)
	$my_access->login_user($_POST['login'], $_POST['password']); // call the login method
} 
$error = $my_access->the_msg; 
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
	<meta http-equiv="Refresh" content="60; url=http://www.maya-b.com/" />
	<title>Log in</title>
	<link rel="stylesheet" type="text/css" href="/stylesheet.css" media="screen" />
</head>
<body style="color: rgb(0, 0, 0); background-color: rgb(180, 210, 210); text-align: center;">
<table style="height: 100%; text-align: left; margin-left: auto; margin-right: auto;" border="0" cellpadding="0" cellspacing="0">
<tbody>
	<tr>
		<td style="height: 100px"></td>
	</tr>
	<tr>
		<td style="width: 300px;">
			<h2>Log in:</h2>
			<form name="form1" method="post" action="/access_user/login.php">
				<table>
				<tbody>
					<tr>
						<td><label for="login">Login:</label></td>
						<td><input name="login" id="login" size="20" value="" type="text" /></td>
					</tr>
					<tr>
						<td><label for="password">Password:</label></td>
						<td><input name="password" id="password" size="20" value="" type="password" /></td>
					</tr>
				</tbody>
				</table>
				<p style="text-align: right"><input name="Submit" value="Enter" type="submit" /></p>
			</form>
			<p style="text-align: center;"><a href="./forgot_password.php">(Re)Set Password</a></p>
			<p style="text-align: center;">You have to set a password before logging in for the first time.</p>
		</td>
	</tr>
</tbody>
</table>
</body>
</html>