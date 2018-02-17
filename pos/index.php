<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/

//error_reporting(E_ALL | E_STRICT);
define('LEGAL',true);
require_once('../config.php');
require_once('../classes/index.php');

if(!@$_GET['docu'] or !file_exists($_GET['docu'].".php")) {
	include_once("sales.php");
}	
else {
	include_once($_GET['docu'].".php");
}


$mysqliConnection = new MysqliConnection;
$docu = new docu;

if(!$docu->permission(array('area' => $docu->folder(__FILE__)))) {
	die("{$docu->user['name']} does not have permission to enter area '" . $docu->folder(__FILE__) . "'.\n");
}

if(isset($_GET['mission'])) {
	$docu->mission($_GET['mission']);
}
else {
	$docu->write_html();
}
?>