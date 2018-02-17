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
	$this->current_sale = new Sale(array(
		'id' => $_GET['sale'])
	);
	if($this->current_sale->checkIfCompleted()->data) {
		header("Location: http://pos.maya-b.com/pos/index.php?docu=sale_record&id={$_GET['sale']}");
	}
}

function script() {
	$tp = $this->mysqli->table_prefix;
?>
Ext.Loader.setConfig({
	enabled: true
});
Ext.Loader.setPath('Ext.ux', '<?=$this->http_host . "/" . $this->ext_library . "/examples/ux"?>');

// Ext.require([
// 	'Ext.data.*'
// ]);
// 
Ext.onReady(function(){

<?
	include_once("_menu.php");
?>

	Ext.define('ProductModel', {
		extend: 'Ext.data.Model',
		fields: [
			{name: 'id'},
			{name: 'product'},
			{name: 'price', type: 'float'},
			{name: 'details'}
		]
	});
	
	Ext.define('ItemModel', {
		extend: 'Ext.data.Model',
		fields: [
			{name: 'invoiceNo', 			type: 'string',	useNull: true},
			{name: 'date',					type: 'date',	useNull: true,	dateFormat: 'Y-m-d'},
			{name: 'product',				type: 'float'},
			{name: 'quantity',				type: 'float',	useNull: true},
			{name: 'quantity_formatted',	type: 'string'},
			{name: 'description',			type: 'string'},
			{name: 'price',					type: 'float'},
			{name: 'price_formatted',		type: 'string'}
		]
	});
	
	add = function(product_id, invoiceNo) {
		Ext.Ajax.request({
			waitMsg: '<?=$this->say('hang on')?>',
			params: {
				quantity: 1,
				productid: product_id,
				invoice: invoiceNo
			},
			url: "index.php?docu=returns&sale=<?=$this->current_sale->id?>&mission=amend&data=addproduct",
			 success :function(response, opts){
				returns.load({
//					callback: updateTotals()
				});
				var a = Ext.JSON.decode(response.responseText);
				if(a.sale && a.sale != <?=$this->current_sale->id?>) {
					window.location = "index.php?docu=returns&sale=" + a.sale;
				}
			 }
		});
	}

	var kwaockProductStore = Ext.create('Ext.data.Store', {
		model: 'ProductModel',
		pageSize: 50,
		remoteSort: false,
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: 'index.php?docu=_shared&mission=request&data=productCombo',
			reader: {
				type: 'json',
				root: 'data',
				totalProperty: 'totalRows'
			}
		},
		autoLoad: false
	});
	
	var quantField = Ext.create('Ext.form.field.Number', {
		allowBlank: true,
		allowDecimals: true,
		decimalSeparator: '<?=$this->say('decimal_separator')?>',
		emptyText: '<?=$this->say('quantity')?>',
		name: 'quantity',
		width: 80,
		value: '1'
	});

	var productSearchField = Ext.create('Ext.form.field.ComboBox', {
		autoSelect: true,
		queryMode: 'remote',
		store: kwaockProductStore,
		emptyText: '<?=$this->say('find product')?>',
		hideLabel: true,
		labelWidth: 0,
		minChars: 1,
		queryDelay: 1000,
		allowBlank: true,
		displayField: 'product',
		editable: true,
		forceSelection: true,
		hideTrigger: true,
		selectOnFocus: true,
		listWidth: 500,
		maxHeight: 600,
		typeAhead: false,
		valueField: 'id',
		listConfig: {
			loadingText: '<?=$this->say('searching')?>',
			emptyText: 'No matching posts found.',

			// Custom rendering template for each item
			getInnerTpl: function() {
				return '{product}<br />{details}';
			}
		},
		listeners: {
			beforequery: function(queryEvent, eOpts) {
				productQuery = queryEvent.query;
			},
			select: function(combo, record, eOpts) {
				instances.load({
					params: {productid: record[0].get('id')},
					callback: function(records, operation, success){
//		select row 1					
					}
				});
			}
		},
		pageSize: 10,
		width: '100%'
	});

	var instances = Ext.create('Ext.data.Store', {
		model: 'ItemModel',
		storeId: 'itemset',
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: 'index.php?docu=returns&mission=request&data=instances',
			reader: {
				type: 'json',
				root: 'data',
				totalProperty: 'totalRows'
			}
		},
		autoLoad: false
	});
	
	var returns = Ext.create('Ext.data.Store', {
		model: 'ItemModel',
		storeId: 'itemset',
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: 'index.php?docu=returns&mission=request&data=items&sale=<?=$this->current_sale->id?>',
			reader: {
				type: 'json',
				root: 'data',
				totalProperty: 'totalRows'
			}
		},
		autoLoad: true
	});
	
	var invoiceNo = {
		dataIndex: 'invoiceNo',
		header: '<?=$this->say('invoice')?>',
		sortable: false,
		width: 70
	};

	var date = {
		xtype: 'datecolumn',
		dataIndex: 'date',
		header: '<?=$this->say('date')?>',
		sortable: false,
		format: '<?=$this->shortdate_format(true)?>',
		width: 70
	};

	var quantity = {
		align: 'right',
		dataIndex: 'quantity',
		header: '<?=$this->say('quantity')?>',
		renderer: function(value, metadata, record, rowIndex, colIndex, store) {
			return record.get('quantity_formatted');
		},
		sortable: false,
		width: 40
	};

	var product = {
		dataIndex: 'product',
		header: '<?=$this->say('product')?>',
		sortable: false,
		width: 50
	};

	var item = {
		dataIndex: 'description',
		header: '<?=$this->say('item')?>',
		sortable: false,
		width: 300
	};

	var price = {
		align: 'right',
		dataIndex: 'price',
		header: '<?=$this->say('price')?>',
		renderer: function(value, metadata, record, rowIndex, colIndex, store) {
			return record.get('price_formatted');
		},
		sortable: false,
		width: 60
	};

	var choose = {
		dataIndex: 'product',
		renderer: function(value, metaData, record, rowIndex, colIndex, store){
			return "<a style=\"cursor: pointer\" title=\"<?=$this->say('add item to returns')?>\" onclick=\"add(" + record.data.product + ", '" + record.data.invoiceNo + "')\"><img src=\"../images/select.gif\" /></a>";
		},
		sortable: false,
		width: 30
	};

	var instancePanel = Ext.create('Ext.grid.Panel', {
		autoScroll: true,
		buttons: [],
		columns: [
			invoiceNo,
			date,
			quantity,
			product,
			item,
			price,
			choose
		],
        height: 300,
		store: instances,
		stripeRows: true,
        title:''
    });

	var returnsPanel = Ext.create('Ext.grid.Panel', {
		autoScroll: true,
		buttons: [],
		columns: [
			invoiceNo,
			date,
			quantity,
			product,
			item,
			price
		],
        height: 200,
		store: returns,
		stripeRows: true,
        title:''
    });

	var mainPanel = Ext.create('Ext.panel.Panel', {
		tbar: [productSearchField],
		renderTo: 'panel',
		items: [instancePanel, returnsPanel],
		title: '',
		height: 550,
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

function task($task = "") {
	switch ($task) {
		default:
			break;
	}
}

function request($data = "") {
	$tp = $this->mysqli->table_prefix;
	$dec = new NumberFormatter($this->say('locale'), NumberFormatter::DECIMAL);
	switch ($data) {
		case "instances":
			$instances = $this->mysqli->arrayData(array(
				'source' => $tp.'sale_items',
				'fields' => 'id, product, quantity, description, pricePer, discount * 100 AS discount',
				'where' => "product = '{$this->GET['productid']}'",
				'sql' =>	"
					SELECT NULL AS invoiceNo, NULL AS date, NULL AS id, id AS product, NULL AS quantity, name AS description, price
					FROM {$tp}products
					WHERE id = '{$this->GET['productid']}'
					UNION SELECT {$tp}invoices.invoiceNo, {$tp}invoices.date, {$tp}invoice_items.id, product, quantity, description, pricePer * (1 - discount) AS price
					FROM {$tp}invoice_items INNER JOIN {$tp}invoices ON {$tp}invoice_items.invoiceId = {$tp}invoices.id
					WHERE product = '{$this->GET['productid']}'
					ORDER BY IF(date, 1, 0) ASC, date DESC
				",
				'returnQuery' => true
			));
			foreach($instances->data as $element => $item) {
				$instances->data[$element]->quantity_formatted = ($item->quantity ? $this->number($item->quantity) : "");
				$instances->data[$element]->price_formatted = $this->money($item->price);
			}
			return json_encode($instances);
			break;
		case "items":
			$items = $this->mysqli->arrayData(array(
//				'source' => $tp.'sale_items',
//				'fields' => 'id, product, quantity, description, price',
//				'where' => "sale = '2428'" 
			));
			foreach($items->data as $element => $item) {
				$items->data[$element]->quantity_formatted = $this->number($item->quantity);
				$items->data[$element]->price_formatted = $this->money($item->price);
			}
			$items->total = ($this->current_sale->getTotal()->data);
			return json_encode($items);
			break;
		default:
			break;
	}
}

function amend($data = "") {
	$tp = $this->mysqli->table_prefix;
	switch ($data) {
		default:
//			echo $this->say('test');
			break;
	}
}

}
?>