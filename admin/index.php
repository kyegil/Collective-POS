<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/

define('LEGAL',true);
require_once('../config.php');
require_once('../classes/index.php');

if(!$_GET['docu'] or !file_exists($_GET['docu'].".php")) {
	include_once("main.php");
}	
else {
	include_once($_GET['docu'].".php");
}
$mysqliConnection = new MysqliConnection;
$docu = new docu;

if( !$docu->permission(array(
	'area' => $docu->folder(__FILE__),
	'trader'=>(int)$_GET['trader']
)) ) {
	die("You do not have permission to enter this area.\n" . $docu->folder(__FILE__));
}

if(isset($_GET['mission'])) $docu->mission($_GET['mission']);
else $docu->write_html();
?>