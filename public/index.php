<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/

define('LEGAL',true);
require_once('../config.php');
require_once('../classes/index.php');

if(!isset($_GET['docu']) or !file_exists($_GET['docu'].".php")) {
	class Docu extends CollectivePOS {
		public $template = "HTML";
	

	function __construct() {
		parent::__construct();
	}

	function script() {}


	function design() {
?>
	<div id="panel"></div>
<?php
	}

	}
}	
else {
	include_once($_GET['docu'].".php");
}
$mysqliConnection = new MysqliConnection;
$docu = new docu;

if(isset($_GET['mission'])) $docu->mission($_GET['mission']);
else $docu->write_html();
?>