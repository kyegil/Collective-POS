<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
if(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {
	public $template = "../../voucher_templates/default";
//	public $title = 'Point of Sale';
	

function __construct() {
	parent::__construct();
}

function script() {
}


function design() {
?>
<div id="panel"></div>
<?
}

function task($task = "") {
	$voucher = new GiftVoucher((int)$_GET['id']);
	switch ($task) {
		case "print":
			$this->template = "../../voucher_templates/default";
			$this->write_html();
			break;
		default:
			break;
	}
}

function request($data = "") {
}

function receive($form) {
}

}
?>