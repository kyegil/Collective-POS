<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
if(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {
	public $template = "file";
	

function __construct() {
	parent::__construct();
	if(!$_GET['trader']) die("Illegal docu: No trader ID given");
	$tp = $this->mysqli->table_prefix;
	$trader	= new Trader($_GET['trader']);
	$this->title = addslashes(str_replace(" ", "_", $trader->getInventoryCountName($_GET['count']))) . ".csv";
}

function script() {
//
}

function design() {
	$trader	= new Trader($_GET['trader']);
	$count	= (int)$_GET['count'];

	$stock = $trader->getInventoryCount($count, $_GET['exclude_sor']);
	
	echo $this->toCsv($stock);
}

function request($data = "") {
	$tp = $this->mysqli->table_prefix;
	switch ($data) {
		case 'file':
			break;
		default:
			echo json_encode($result);
			break;
	}
}

function receive($form) {
	switch ($form) {
		default:
			echo json_encode($result);
	}
}

}
?>