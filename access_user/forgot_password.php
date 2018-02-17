<?php 
include(dirname(__FILE__)."/access_user_class.php"); 

$renew_password = new Access_user;

if (isset($_POST['Submit'])) {
	$renew_password->forgot_password($_POST['email']);
} 
$error = $renew_password->the_msg;
?>
<?="<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
	<meta http-equiv="Refresh" content="60; url=../">
	<title>Opprett passord</title>
	<link rel="stylesheet" type="text/css" href="/stylesheet.css" media="screen" />
</head>

<body style="color: rgb(0, 0, 0); background-color: rgb(210, 180, 210); text-align: center;">
<table style="height: 100%; text-align: left; margin-left: auto; margin-right: auto;" border="0" cellpadding="0" cellspacing="0">
<tbody>
	<tr>
		<td style="height: 100px"></td>
	</tr>
	<tr>
		<td style="width: 300px;">
			<h2>Set or reset your password</h2>
			<p><b><?php echo (isset($error)) ? $error : "&nbsp;"; ?></b></p>
			<p>Please provide your email address for further instructions on how to set your password.</p>
			<form name="form1" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
				<label for="email">Email:</label>
				<input type="text" name="email" value="<?php echo (isset($_POST['email'])) ? $_POST['email'] : ""; ?>">
				<input type="submit" name="Submit" value="Send">
			</form>
			<p style="text-align: center;"><a href="./login.php"><< Back to login</a></p>
		</td>
	</tr>
</tbody>
</table>
</body>
</html>
