<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('No access!<br />Check your URI.');

require_once("../classes/converter.class.php");

class Docu extends CollectivePOS {
	public $title = 'Point of Sale';
	public $template = "HTML";
	

function __construct() {
	parent::__construct();
	$this->current_sale = new Sale(array(
		'id' => @$_GET['sale'])
	);
}

function script() {
?>
<?
}

function design() {

	$sales = array( new Sale(17407), new Sale(18984) );

	$payments = array(
		new Payment(20166),
		new Payment(22060)
	);
	
//	var_export($payments);
	$shares = array( $sales[0]->getShares(), $sales[1]->getShares() );
	
	var_export($shares);
	die();

?>
<div id="panel"></div>
<?
}

}

?>