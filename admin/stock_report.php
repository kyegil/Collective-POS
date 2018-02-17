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
	$this->title = $this->say('report stock report');
	parent::__construct();
	if(!$_GET['trader']) die("Illegal docu: No trader ID given");
	$tp = $this->mysqli->table_prefix;
}

function script() {
}

function design() {
	$trader = new Trader($_GET['trader']);
	$tp = $this->mysqli->table_prefix;
	$stock = $this->mysqli->arrayData(array(
		'source' => "{$tp}products LEFT JOIN {$tp}suppliers ON {$tp}products.supplier = {$tp}suppliers.id",
		'where' => "{$tp}products.trader = '{$trader->id}' and {$tp}products.enabled > 0",
		'fields' => "{$tp}products.supplier AS supplierid, {$tp}suppliers.supplier, brand, productCode, {$tp}products.name, inStock, cost, price, cost * inStock AS value",
		'orderfields' => "{$tp}suppliers.supplier, brand, productCode"
	))->data;
	$count = $sales_value = $value = 0;
?>
	<span class="dataload">
	<h1><?=$this->say('report stock report')?></h1>
	<h2><?=$trader->name?></h2>
	<h3><?=$this->say('report printed x', array($this->long_datetime(date_create())))?></h3>
	<table>
		<tr>
			<th><?=$this->say('product code')?></th>
			<th><?=$this->say('product name')?></th>
			<th class="value"><?=$this->say('product price')?></th>
			<th class="value"><?=$this->say('product in stock')?></th>
			<th class="value"><?=$this->say('product cost')?></th>
			<th class="value"><?=$this->say('product value')?></th>
		</tr>
		<?foreach($stock as $product):?>
		<?if($product->supplierid != $supplier or $product->brand != $brand):?>
			<tr>
				<td class="bold" colspan="6">&nbsp;</td>
			</tr>
			<tr>
				<td class="bold" colspan="6"><?="{$product->brand} ({$product->supplier})"?></td>
			</tr>
		<?endif?>
			<tr>
				<td><?=$product->productCode?></td>
				<td><?=$product->name?></td>
				<td class="value"><?=$this->money($product->price)?></td>
				<td class="value"><?=$this->number($product->inStock)?></td>
				<td class="value"><?=$this->money($product->cost)?></td>
				<td class="value"><?=$this->money($product->value)?></td>
			</tr>
			<?$supplier = $product->supplierid?>
			<?$brand = $product->brand?>
			<?php $count += $product->inStock;?>
			<?php $sales_value += ($product->inStock * $product->price);?>
			<?php $value += $product->value;?>
		<?endforeach?>
		<tr>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td class="bold value"><?php echo $this->money($sales_value)?></td>
			<td class="bold value"><?php echo $this->number($count)?></td>
			<td class="bold value">&nbsp;</td>
			<td class="bold value"><?=$this->money($value)?></td>
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
			break;
	}
}

}
?>