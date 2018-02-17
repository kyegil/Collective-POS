<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
if(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {
	public $template = "report";
//	public $title = 'Point of Sale';
	

function __construct() {
	parent::__construct();
	if(!$_GET['trader']) die("Illegal docu: No trader ID given");
	$this->title = $this->say('report inventory count report');
}

function script() {
}

function design() {
	$trader	= new Trader($_GET['trader']);
	$count	= (int)$_GET['count'];
	$excludeSor	= (int)$_GET['exclude_sor'];
	
	$stock = $trader->getInventoryCount($count, $excludeSor);
?>
	<span class="dataload">
	<h1><?=addslashes($trader->getInventoryCountName($count))?></h1>
	<h2><?=$trader->name?></h2>
	<h3><?=$this->say('report printed x', array($this->long_datetime(date_create())))?></h3>
	<table>
		<tr>
			<th><?=$this->say('product code')?></th>
			<th><?=$this->say('product name')?></th>
			<th class="value"><?=$this->say('count calculated quantity')?></th>
			<th colspan="2" class="value"><?=$this->say('count actual quantity')?></th>
			<th class="value"><?=$this->say('product value')?></th>
		</tr>
		
		<?$total = 0;?>
		
		<?foreach($stock as $product):?>
		<?if($product->supplierid != $supplier or $product->brand != $brand):?>
			<tr>
				<td class="value bold" colspan="6"><?=$this->say('report brand total x', array($this->money($brandtotal)));?></td>
			</tr>
			<tr>
				<td class="bold" colspan="6"><?="{$product->brand} ({$product->supplier})"?></td>
			</tr>
		<?$brandtotal = 0;?>
		<?endif?>
		<?$brandtotal += $product->actual_quantity * $product->value_per;?>
		<?$total += $product->actual_quantity * $product->value_per;?>
			<tr>
				<td><?=$product->productCode?></td>
				<td><?=$product->name?></td>
				<td class="value"><?=$this->number($product->calculated_quantity)?></td>
				<td class="value"><?=$this->number($product->actual_quantity)?></td>
				<td><?=($product->actual_quantity - $product->calculated_quantity ?  ("(" . $this->relative($product->actual_quantity - $product->calculated_quantity) . ")") : "")?></td>
				<td class="value"><?=$this->money($product->actual_quantity * $product->value_per)?></td>
			</tr>
			<?$supplier = $product->supplierid?>
			<?$brand = $product->brand?>
		<?endforeach?>
			<tr>
				<td class="value bold" colspan="6"><?=$this->say('report brand total x', array($this->money($brandtotal)));?></td>
			</tr>
			<tr>
				<td class="value bold" colspan="6"><?=$this->say('report total x', array($this->money($total)));?></td>
			</tr>
	</table>
	<script type="text/javascript">
		window.print();
	</script>
<?
}

function request($data = "") {
	$tp = $this->mysqli->table_prefix;
	switch ($data) {
		default:
			echo json_encode();
	}
}

function receive($form) {
	switch ($form) {
		default:
			echo json_encode($result);
	}
}

function amend($data = "") {
	$tp = $this->mysqli->table_prefix;
	$dec = new NumberFormatter($this->say['locale'], NumberFormatter::DECIMAL);
	switch ($data) {
		default:
//			echo $this->say['test']->format(array());
			break;
	}
}

}
?>