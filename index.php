<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/

define('LEGAL',true);
require_once('config.php');
require_once('classes/index.php');

if (version_compare(PHP_VERSION, '7.0.0', '<')) {
	require_once('classes/collective_pos.class.php');
	require_once('classes/sale.class.php');
	require_once('classes/fpdf/fpdf.php');
	require_once('access_user/access_user_class.php'); 

	$permission = new Access_user;

	if (isset($_GET['mission']) && $_GET['mission'] == "exit") {
		$permission->log_out(); // the method to log off
	}
}
else {
	$mysqliConnection = new MysqliConnection;
	$docu = new CollectivePOS;

	if (isset($_GET['mission']) && $_GET['mission'] == "exit") {
		$docu->authoriser->elsalutu();
	}
}

header("Location: pos/index.php");
?>