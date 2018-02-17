<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
if(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {
	public $template = "HTML";
	public $title = 'Point of Sale';
	

function __construct() {
	parent::__construct();
}

function script() {
	$trader = new Trader(1);
	$first = time();
	var_dump($trader->getPayments(new DateTime));
	die(time() - $first);
}

function design() {
?>
<div id="panel"></div>
<?
}

function request($data = "") {
	$tp = $this->mysqli->table_prefix;
	switch ($data) {
		default:
			echo json_encode($this->main_data);
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