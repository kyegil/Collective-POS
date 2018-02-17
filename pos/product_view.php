<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
if(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {
	public $template = "HTML";
//	public $title = 'Point of Sale';
	

function __construct() {
	parent::__construct();
	$product = new Product(array('id' => (int)$_GET['product']));
	$this->title = $product->name;
}

function script() {
	$tp = $this->mysqli->table_prefix;
?>
Ext.Loader.setConfig({
	enabled: true
});
Ext.Loader.setPath('Ext.ux', '<?=$this->http_host . "/" . $this->ext_library . "/examples/ux"?>');

Ext.require([
	'Ext.selection.CellModel',
	'Ext.grid.*',
	'Ext.data.*',
	'Ext.util.*',
	'Ext.state.*',
	'Ext.form.*',
	'Ext.ux.CheckColumn'
]);

Ext.onReady(function() {
	Ext.Loader.setConfig({enabled:true});
	Ext.QuickTips.init();
	
<?
	include_once("_menu.php");
?>

	var centrePanel = Ext.create('Ext.Panel', {
		region:	'center',
		title: "",
		loader: {
			url: "index.php?docu=<?=$_GET['docu']?>&product=<?=(int)$_GET['product']?>&mission=request",
			autoLoad: true
		},
		autoScroll: true,
		autoWidth: true,
		defaults: {
			border: false,
			collapsible: true,
			split: true,
			autoScroll: true,
			margins: '5 0 0 0',
			cmargins: '5 5 0 0',
			bodyStyle: 'padding: 15px'
		}
	});

	var mainPanel = Ext.create('Ext.Panel', {
		layout: 'border',
		renderTo: 'panel',
		items: [centrePanel],
		height: 500,
		autoScroll: true,
		autoWidth: true
	});

});
<?
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
			$product = new Product(array('id' => (int)$_GET['product']));
?>
	<h1><?=$product->name?></h1>
	<h2><?=$product->productCode?></h2>
	<p><?=$product->description?></p>
	<p>
	<?=$this->say('trader')?>: <?=$product->getTrader()->data->name?><br />
	<?=$this->say('product brand')?>: <?=$product->brand?><br />
	<?=$this->say('product barcode')?>: <?=$product->barcode?><br />
	<?=$this->say('product supplier')?>: <?=$product->supplier?><br />
	<?=$this->say('product sor')?>: <?=$product->sor?><br />
	</p>
	<p>
	<?=$this->say('product floating')?>: <?=$product->floating?><br />
	<?=$this->say('product unit')?>: <?=$product->unit?><br />
	<?=$this->say('product cost')?>: <?=$this->money($product->cost)?><br />
	<?=$this->say('product price')?>: <?=$this->money($product->price)?><br />
	<?=$this->say('product in stock')?>: <?=$product->inStock?><br />
	<?=$this->say('product lowest price')?>: <?=$this->money($product->lowestPrice)?><br />
	</p>
	<p>
	<?=$this->say('product categories')?>: <?=$this->summarise($product->categories)?><br />
	</p>
	<p>
	<?=$this->say('product tax')?>: <?=$product->getTax()->data->taxName?><br />
	</p>
	<p>
	<?=$this->say('product attribute sets')?>:<br />
	<?foreach($product->getAttributesets()->data as $attributeset):?>
		<?="{$attributeset->name}<br />\n"?>
	<?endforeach;?>
	</p>
	<p>
	<?foreach($product->getAttributes()->data as $attribute):?>
		<?="{$attribute->name}: {$attribute->value}<br />\n"?>
	<?endforeach;?>
	</p>
<?
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