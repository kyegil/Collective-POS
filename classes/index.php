<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/

if(defined('LEGAL')) {
	require_once('../config.php');

	require_once('mysqli_connection.class.php');

	require_once('collective_pos.class.php');

	require_once('DatabaseObject.class.php');

	require_once('trader.class.php');
	require_once('giftvoucher.class.php');
	require_once('invoice.class.php');
	require_once('note.class.php');
	require_once('payment.class.php');
	require_once('product.class.php');

	require_once('sale.class.php');
	require_once('supplier.class.php');
	require_once('till.class.php');
	require_once('user.class.php'); 

	if (version_compare(PHP_VERSION, '7.0.0', '<')) {
		require_once('/home/knitwith/public_html/pos/access_user/access_user_class.php'); 
		require_once('sessions_manager.class.php');
	}
	require_once('returi.class.php');
	require_once('fpdf/fpdf.php');
}

else {
	throw new Exception("Illegal access to directory");
}

?>