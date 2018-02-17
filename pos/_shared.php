<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {

function __construct() {
	parent::__construct();
	$this->template = "data";
}

function script() {
?>
	var kwaockProductCombo = new Ext.form.ComboBox({
		name: 'productCombo',
		mode: 'remote',
		store: new Ext.data.JsonStore({
			fields: [{name: 'id'},{name: 'product'},{name: 'price', type: 'float'},{name: 'details'}],
			root: 'data',
			url: 'index.php?docu=_shared&mission=request&data=productCombo'
		}),
		fieldLabel: '<?=$this->say['find product']->format(array())?>',
		hideLabel: false,
		minChars: 0,
		queryDelay: 1000,
		allowBlank: true,
		displayField: 'product',
		editable: true,
		forceSelection: false,
		selectOnFocus: true,
		listWidth: 500,
		maxHeight: 600,
		typeAhead: false,
		valueField: 'id',
		listConfig: {
			loadingText: 'Searching...',
			emptyText: 'No matching posts found.',

			// Custom rendering template for each item
			getInnerTpl: function() {
				return '{details}';
			}
		},
		pageSize: 10,
		width: 200
	});


	var kwaockCustomerCombo = new Ext.form.ComboBox({
		name: 'customerCombo',
		mode: 'remote',
		store: new Ext.data.JsonStore({
			fields: [{name: 'id'},{name: 'customer'}],
			root: 'data',
			url: 'index.php?docu=_shared&mission=request&data=customerCombo'
		}),
		fieldLabel: '<?=$this->say['find customer']->format(array())?>',
		hideLabel: false,
		minChars: 0,
		queryDelay: 1000,
		allowBlank: true,
		displayField: 'name',
		editable: true,
		forceSelection: false,
		selectOnFocus: true,
		listWidth: 500,
		maxHeight: 600,
		typeAhead: false,
		width: 200
	});


<?
}

function design() {
	$this->script();
}

function request($data = "") {
	$tp = $this->mysqli->table_prefix;
	$query = @$_POST['query'];
	
	switch ($data) {
	
	case "productCombo": {
		$searchset = array();
		$where = array("({$tp}products.enabled > 0)");
		if($_GET['query']) {
			$searchset = explode(" ", $_GET['query']);
		}
		if($query) {
			$searchset = explode(" ", $query);
		}
		foreach($searchset as $search) {
			$where[] = "({$tp}products.productCode LIKE '%{$search}%'\n"
			.	"OR {$tp}products.name LIKE '%{$search}%'\n"
			.	"OR {$tp}products.brand LIKE '%{$search}%'\n"
			.	"OR {$tp}products.barcode LIKE '%{$search}%'\n"
			.	"OR {$tp}products.description LIKE '%{$search}%'\n"
			.	"OR {$tp}suppliers.supplier LIKE '%{$search}%'\n"
			.	"OR {$tp}product_attributes.value LIKE '%{$search}%'\n)\n";
		}
		$where = implode(" AND ", $where);

		$orderfields = "IF(({$tp}products.productCode LIKE '%" . implode("%' AND {$tp}products.productCode LIKE '%", $searchset) . "%'), 0, 1),\n";
		$orderfields .= "IF(({$tp}products.name LIKE '%" . implode("%' AND {$tp}products.name LIKE '%", $searchset) . "%'), 0, 1),\n";
		$orderfields .= "IF(({$tp}products.brand LIKE '%" . implode("%' AND {$tp}products.brand LIKE '%", $searchset) . "%'), 0, 1)";
		$orderfields = "IF(({$tp}products.productCode LIKE '%" . implode("%' OR {$tp}products.productCode LIKE '%", $searchset) . "%'), 0, 1),\n";
		$orderfields .= "IF(({$tp}products.name LIKE '%" . implode("%' OR {$tp}products.name LIKE '%", $searchset) . "%'), 0, 1),\n";
		$orderfields .= "IF(({$tp}products.brand LIKE '%" . implode("%' OR {$tp}products.brand LIKE '%", $searchset) . "%'), 0, 1)";

		$result = $this->mysqli->arrayData(array(
			'class' => 'Product',
			'source' =>
				"{$tp}products
			LEFT JOIN
				{$tp}traders
				ON
				{$tp}products.trader = {$tp}traders.id
			LEFT JOIN
				{$tp}suppliers
				ON
				{$tp}products.supplier = {$tp}suppliers.id
			LEFT JOIN
				{$tp}product_attributes
				ON
				{$tp}products.id = {$tp}product_attributes.product
			LEFT JOIN
				{$tp}product_categories
				ON {$tp}products.id = {$tp}product_categories.product",
			
			'fields' =>
			"{$tp}traders.traderCode, {$tp}products.id, {$tp}products.name, {$tp}products.name, {$tp}products.productCode, {$tp}products.price, {$tp}products.description, {$tp}products.unit, {$tp}products.inStock",
			
			'where' => $where,
			'orderfields' => $orderfields,
			'returnQuery' => true
		));
		foreach($result->data as $line => $product) {
			$result->data[$line]->product = $product->traderCode . ' | ' . $product->productCode . ' | ' . $product->name . ": " . $this->money($product->price);
			$result->data[$line]->details = "{$product->productCode}<br />" . $this->say('x in stock', array($product->inStock . ($product->unit ? " {$product->unit}" : "")));
		}
		return json_encode($result);
		break;
	}

	case "customerCombo": {
		$result = $this->mysqli->arrayData(array(
			'source' => "{$tp}customers" ,
			'orderfields' => "name ASC"
		));
		return json_encode($result);
		break;
	}

	default: {
		echo "";
		break;
	}
	}
}

}
?>