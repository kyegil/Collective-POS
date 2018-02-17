<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
if(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {
	public $template = "HTML";
	public $title = 'Point of Sale';
	

public function __construct() {
	parent::__construct();
	if(!$_GET['trader']) die("Illegal docu: No trader ID given");
}

public function script() {
	$pageSize = 50;
	$trader = new Trader($_GET['trader']);
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

	Ext.define('Product', {
		extend: 'Ext.data.Model',
		fields: [
<?
	$a = $this->mysqli->arrayData(array(
		'source' => $this->mysqli->table_prefix."attributes",
		'flat' => false
	));
	foreach($a->data as $b) {
		echo "\t\t\t{name: '{$b->code}'" . ($b->type ? ", type: '{$b->type}'" : "") . ", useNull: true},\n";
	}
?>
			{name: 'id', type: 'int', useNull: true},
			{name: 'productCode', type: 'string'},
			{name: 'enabled', type: 'boolean'},
			{name: 'name', type: 'string'},
			{name: 'brand', type: 'string'},
			{name: 'barcode', type: 'string'},
			{name: 'floating', type: 'boolean'},
			{name: 'unit', type: 'string'},
			{name: 'description', type: 'string'},
			{name: 'cost', type: 'float'},
			{name: 'cost_rendered', type: 'string'},
			{name: 'price', type: 'float'},
			{name: 'price_rendered', type: 'string'},
			{name: 'supplier', type: 'int', useNull: true},
			{name: 'inStock', type: 'float'},
			{name: 'in_stock_rendered', type: 'string'},
			{name: 'lowestPrice', type: 'float'},
			{name: 'lowest_price_rendered', type: 'string'},
			{name: 'sor', type: 'boolean'},
			{name: 'tax', type: 'int', useNull: true},
			{name: 'tax_rendered', type: 'string'}
		]
	});
	
	var currentField;
	var currentProduct;

	var store = Ext.create('Ext.data.Store', {
		model: 'Product',
		pageSize: <?=$pageSize?>,
		remoteSort: true,
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: 'index.php?docu=stock&trader=<?=$trader->id?>&mission=request',
			reader: {
				type: 'json',
				root: 'data',
				actionMethods: {
					read: 'POST'
				},
				totalProperty: 'totalRows'
			}
		},
		sorters: [{
			property: 'id',
			direction: 'DESC'
		}],
		autoLoad: true
	});
	
	Ext.define('Brand', {
		extend: 'Ext.data.Model',
		fields: [
			{name: 'brand', type: 'string'}
		]
	});
	
	var brandstore = Ext.create('Ext.data.Store', {
		autoDestroy: true,
		model: 'Brand',
		data: 
<?
	echo json_encode($this->mysqli->arrayData(array(
		'source' => $this->mysqli->table_prefix."products",
		'fields' => "brand",
		'where' => "trader = '{$trader->id}'",
		'orderfields' => "brand",
		'groupfields' => "brand",
		'flat' => false
	))->data);
?>

	});
	
	Ext.define('Supplier', {
		extend: 'Ext.data.Model',
		fields: [
			{name: 'id', type: 'int', useNull: true},
			{name: 'supplier', type: 'string'}
		]
	});
	
	var supplierstore = Ext.create('Ext.data.Store', {
		autoDestroy: true,
		model: 'Supplier',
		data: 
<?
	echo json_encode($this->mysqli->arrayData(array(
		'source' => $this->mysqli->table_prefix."suppliers",
		'fields' => "id, supplier",
		'where' => "trader = '{$trader->id}'",
		'orderfields' => "supplier",
		'flat' => false
	))->data);
?>

	});
	
	Ext.define('Tax', {
		extend: 'Ext.data.Model',
		fields: [
			{name: 'id', type: 'int', useNull: true},
			{name: 'taxName', type: 'string'}
		]
	});
	
	var taxstore = Ext.create('Ext.data.Store', {
		autoDestroy: true,
		model: 'Tax',
		data: 
<?
	echo json_encode($this->mysqli->arrayData(array(
		'source' => $this->mysqli->table_prefix."tax",
		'fields' => "id, taxName",
		'flat' => false
	))->data);
?>

	});
	
	Ext.define('Attributsets', {
		extend: 'Ext.data.Model',
		fields: [
			{name: 'id', type: 'int'},
			{name: 'name', type: 'string'}
		]
	});
	
	var attributesets = Ext.create('Ext.data.Store', {
		autoDestroy: true,
		model: 'Attributsets',
		data: 
<?
	echo json_encode($this->mysqli->arrayData(array(
		'source' => $this->mysqli->table_prefix."attributesets",
		'where' => "!trader or trader = '{$this->GET['trader']}'",
		'fields' => "id, name",
		'flat' => false
	))->data);
?>

	});
	
	var customiseGrid = function(a, b, c) {
		Ext.Ajax.request({
			waitMsg: '<?=addslashes($this->say('hang on', array()))?>',
			url: 'index.php?docu=stock&trader=<?=$trader->id?>&mission=request&data=columns',
			params: {
				attributesets: String(b)
			},
			success:function(response, options){
				var feedback = Ext.decode(response.responseText);
				var columns = feedback.data;
				var gridView = Ext.ComponentQuery.query("gridpanel")[0];
				gridView.headerCt.removeAll();
				gridView.headerCt.add(defaultColumns);
				for(var i in columns) {
					var config = Ext.decode("{}");
					if(columns[i].config) {
						var config = Ext.decode(columns[i].config);
					}
					var xtype = 'textfield';
					var width = 60;
					var align = 'left';
					var editor = {
						xtype: 'textfield',
						name: columns[i].code,
						selectOnFocus: true,
						tabIndex: 1
					};

					if(columns[i].type == 'boolean' || columns[i].type == 'bool') {
						var xtype = 'checkcolumn';
						var width = 30;
					}
					if(columns[i].type == 'float' || columns[i].type == 'integer' || columns[i].type == 'integer') {
						var align = 'right';
						var editor = {
							xtype: 'numberfield',
							allowBlank: true,
							allowDecimals: (columns[i].type == 'float' ? true : false),
							decimalSeparator: '<?=$this->say('decimal_separator')?>',
							hideTrigger: true,
							keyNavEnabled: false,
							mouseWheelEnabled: false,
							name: columns[i].code,
							renderer: function(v) {
								return v.replace(".","<?=$this->say('decimal_separator')?>");
							},
							selectOnFocus: true,
							tabIndex: 1
						};
					}
					var column = Ext.create('Ext.grid.column.Column', {
						allowBlank: false,
						text: columns[i].code.charAt(0).toUpperCase() + columns[i].code.slice(1),
						dataIndex: columns[i].code,
						width: width,
						xtype: xtype,
						align: align,
						editor: editor,
						stopSelection: false
					});

					gridView.headerCt.insert(gridView.columns.length, column);
					gridView.getView().refresh();
				}
			}
		});
	}
	
	function saveChanges(editor, edit, eOpts) {
		if(!gridEditable) {
				Ext.MessageBox.alert('<?=addslashes($this->say('stock locked cannot edit', array()))?>');
				return false;
		}
		Ext.Ajax.request({
			waitMsg: '<?=addslashes($this->say('hang on', array()))?>',
			url: 'index.php?docu=stock&trader=<?=$trader->id?>&mission=amend&data=edit_item',
			params: {
				id: edit.record.data.id,
				field: edit.field,
				editing_allowed: gridEditable,
				value: edit.value,
				original_value: edit.originalValue
			},
			callback: function(options, success, response){
					if(!response.responseText) {
						Ext.MessageBox.alert('<?=addslashes($this->say('unable to save due to unknown error', array()))?>');
						return false;
					}
					var result = Ext.JSON.decode(response.responseText);
					
					if(result['success'] == true) {
<?
	$a = $this->mysqli->arrayData(array(
		'source' => $this->mysqli->table_prefix."attributes",
		'flat' => false
	));
	foreach($a->data as $b) {
		echo "\t\t\t\t\t\tedit.record.set('attribute{$b->id}', result.data.attribute{$b->id});\n";
	}
?>
						edit.record.set('id', result.data.id);
						edit.record.set('productCode', result.data.productCode);
						edit.record.set('enabled', result.data.enabled);
						edit.record.set('name', result.data.name);
						edit.record.set('brand', result.data.brand);
						edit.record.set('barcode', result.data.barcode);
						edit.record.set('floating', result.data.floating);
						edit.record.set('unit', result.data.unit);
						edit.record.set('description', result.data.description);
						edit.record.set('cost', result.data.cost);
						edit.record.set('cost_rendered', result.data.cost_rendered);
						edit.record.set('price', result.data.price);
						edit.record.set('price_rendered', result.data.price_rendered);
						edit.record.set('supplier', result.data.supplier);
						edit.record.set('supplier_rendered', result.data.supplier_rendered);
						edit.record.set('inStock', result.data.inStock);
						edit.record.set('in_stock_rendered', result.data.in_stock_rendered);
						edit.record.set('lowestPrice', result.data.lowestPrice);
						edit.record.set('lowest_price_rendered', result.data.lowest_price_rendered);
						edit.record.set('sor', result.data.sor);
						edit.record.set('tax', result.data.tax);
						edit.record.set('tax_rendered', result.data.tax_rendered);

 						store.commitChanges();
 						if(result.msg) {
 							Ext.MessageBox.alert('<?=addslashes($this->say('notice', array()))?>', result.msg);
 						}
					}
					else {
						Ext.MessageBox.alert('<?=addslashes($this->say('warning', array()))?>', result['msg']);
					}
				}
			}
		);
	};

	function deleteProduct(record) {
		if(!gridEditable) {
			Ext.MessageBox.alert('<?=addslashes($this->say('stock locked cannot edit', array()))?>');
			return false;
		}
		Ext.Msg.show({
			title: '<?=addslashes($this->say('are you sure', array()))?>:<br />',
			msg: '<?=addslashes($this->say('product confirm deletion', array()))?>:<br />' + record.data.productCode + '?<br /><br />',
			buttons: Ext.Msg.OKCANCEL,
			fn: function(buttonId, text, opt){
				if(buttonId == 'ok') {
					Ext.Ajax.request({
						waitMsg: 'hang on',
						url: "index.php?docu=<?=$_GET['docu']?>&trader=<?=$_GET['trader']?>&mission=amend&data=delete",
						params: {
							product: record.data.id
						},
						success: function(response, options){
							var result = Ext.JSON.decode(response.responseText);
							if(result['success'] == true) {
								Ext.MessageBox.alert('<?=addslashes($this->say('success', array()))?>', result.msg, function(){
									store.load();
								});
							}
							else {
								Ext.MessageBox.alert('<?=addslashes($this->say('failure', array()))?>',result['msg']);
							}
						}
					});
				}
			},
			animEl: 'elId',
			icon: Ext.MessageBox.QUESTION
		});
	}


	var gridEditable = false;

	var cellEditing = Ext.create('Ext.grid.plugin.CellEditing', {
		clicksToEdit: 1
	});
	
	var rowEditing = Ext.create('Ext.grid.plugin.RowEditing', {
		clicksToMoveEditor: 1,
		autoCancel: false
	});
	
	var attributesetChooser = Ext.create('Ext.form.field.ComboBox', {
		name: 'attributeset_chooser',
		hiddenName: 'attributeset_chooser',
		displayField: 'name',
		emptyText: '<?=addslashes($this->say('select attributesets for more columns', array()))?>',
		editable: false,
		fieldLabel: '',
		forceSelection: true,
		labelWidth: 130,
		listWidth: 500,
		multiSelect: true,
		queryMode: 'local',
		store: attributesets,
		valueField: 'id',
		width: 500
	});
	attributesetChooser.on('change', customiseGrid);
	
	var searchfield = Ext.create('Ext.form.field.Text', {
		emptyText: '<?=addslashes($this->say('search product', array()))?>',
		name: 'query',
		width: 200,
		listeners: {
			'specialkey': function() {
				store.getProxy().setExtraParam('query', searchfield.getValue());
				store.load({params: {start: 0, limit: <?=$pageSize?>}});
			}
		}
	});

	var deleteButtons = Ext.create('Ext.grid.column.Action', {
		dataIndex: 'id',
		hidden: true,
		text: '<?=addslashes($this->say('delete', array()))?>',
		width: 25,
		align: 'center',
		items : [
			{
				icon: '../images/delete.png',
				tooltip : '<?=addslashes($this->say('delete', array()))?>',
				handler : function (grid, rowIndex, colIndex, item, e, record) {
					deleteProduct(record);
				}
			}
		],
		sortable: false
	});

	var defaultColumns = [
		deleteButtons,
		{
			dataIndex: 'productCode',
			text: '<?=addslashes($this->say('product code', array()))?>',
			width: 100,
			align: 'left',
			editor: {
				allowBlank: false,
				blankText: '<?=addslashes($this->say('this is a required field', array()))?>',
				xtype: 'textfield',
				name: 'productCode',
				selectOnFocus: true,
				tabIndex: 1
			},
			listeners: {
				beforeedit: function(e, editor){
					if (!gridEditable) {
						return false;
					}
				}
			},
			sortable: true,
			validator: function(value) {
			}
		},
		{
			xtype: 'checkcolumn',
			text: '<?=addslashes($this->say('product enabled', array()))?>',
			dataIndex: 'enabled',
			width: 50,
			listeners : {
				checkchange : function(column, recordIndex, checked) {
					e= {
						record: store.getAt(recordIndex),
						field: column.dataIndex,
						value: checked,
						originalValue: !checked
					};
					saveChanges(this, e);
				} 
			}
		},
		{
			dataIndex: 'name',
			text: '<?=addslashes($this->say('product name', array()))?>',
			width: 200,
			align: 'left',
			editor: {
				xtype: 'textfield',
				name: 'name',
				selectOnFocus: true,
				tabIndex: 1
			},
			sortable: true
		},
		{
			dataIndex: 'description',
			text: '<?=addslashes($this->say('product description', array()))?>',
			width: 200,
			align: 'left',
			editor: {
				xtype: 'textfield',
				name: 'description',
				selectOnFocus: true,
				tabIndex: 1
			},
			sortable: true
		},
		{
			text: '<?=addslashes($this->say('product brand', array()))?>',
			dataIndex: 'brand',
			width: 100,
			editor: new Ext.form.field.ComboBox({
				displayField: 'brand',
				listClass: 'x-combo-list-small',
				listConfig: {
					width: 200
				},
				matchFieldWidth: false,
				queryMode: 'local',
				triggerAction: 'all',
				typeAhead: true,
				selectOnFocus: true,
				selectOnTab: true,
				store: brandstore
			})
		},
		{
			dataIndex: 'barcode',
			text: '<?=addslashes($this->say('product barcode', array()))?>',
			width: 90,
			align: 'right',
			editor: {
				xtype: 'textfield',
				name: 'barcode',
				selectOnFocus: true,
				tabIndex: 1
			},
			sortable: true
		},
		{
			xtype: 'checkcolumn',
			text: '<?=addslashes($this->say('product floating', array()))?>',
			dataIndex: 'floating',
			width: 25,
			listeners : {
				checkchange: function(column, recordIndex, checked) {
					e= {
						record: store.getAt(recordIndex),
						field: column.dataIndex,
						value: checked,
						originalValue: !checked
					};
					saveChanges(this, e);
				} 
			}
		},
		{
			dataIndex: 'inStock',
			text: '<?=addslashes($this->say('product in stock', array()))?>',
			width: 40,
			align: 'right',
			editor: {
				xtype: 'numberfield',
				name: 'inStock',
				decimalSeparator: '<?=$this->say('decimal_separator')?>',
				hideTrigger: true,
				keyNavEnabled: false,
				mouseWheelEnabled: false,
				selectOnFocus: true,
				tabIndex: 1
			},
			sortable: true
		},
		{
			dataIndex: 'unit',
			text: '<?=addslashes($this->say('product unit', array()))?>',
			width: 40,
			align: 'left',
			editor: {
				xtype: 'textfield',
				name: 'unit',
				selectOnFocus: true,
				tabIndex: 1
			},
			sortable: true
		},
		{
			dataIndex: 'cost',
			text: '<?=addslashes($this->say('product cost', array()))?>',
			width: 70,
			align: 'right',
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return record.get('cost_rendered');
			},
			editor: {
				xtype: 'numberfield',
				name: 'cost',
				allowDecimals: true,
				decimalSeparator: '<?=$this->say('decimal_separator')?>',
				hideTrigger: true,
				keyNavEnabled: false,
				mouseWheelEnabled: false,
				selectOnFocus: true,
				tabIndex: 1
			},
			sortable: true
		},
		{
			dataIndex: 'price',
			text: '<?=addslashes($this->say('product price', array()))?>',
			width: 70,
			align: 'right',
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return record.get('price_rendered');
			},
			editor: {
				xtype: 'numberfield',
				name: 'price',
				allowDecimals: true,
				decimalSeparator: '<?=$this->say('decimal_separator')?>',
				hideTrigger: true,
				keyNavEnabled: false,
				mouseWheelEnabled: false,
				selectOnFocus: true,
				tabIndex: 1
			},
			sortable: true
		},
		{
			text: '<?=addslashes($this->say('product supplier', array()))?>',
			dataIndex: 'supplier',
			width: 100,
 			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
 				if(!value) return "";
				var idx = supplierstore.findExact('id', value);
				var rec = supplierstore.getAt(idx);
				return rec.get('supplier');
 			},
			editor: new Ext.form.field.ComboBox({
				displayField: 'supplier',
				listClass: 'x-combo-list-small',
				listConfig: {
					width: 200
				},
				matchFieldWidth: false,
				queryMode: 'local',
				selectOnTab: true,
				store: supplierstore,
				typeAhead: true,
				valueField: 'id'
			})
		},
		{
			xtype: 'checkcolumn',
			text: '<?=addslashes($this->say('sor', array()))?>',
			dataIndex: 'sor',
			width: 30,
			listeners : {
				checkchange : function(column, recordIndex, checked) {
					e= {
						record: store.getAt(recordIndex),
						field: column.dataIndex,
						value: checked,
						originalValue: !checked
					};
					saveChanges(this, e);
				} 
			}
		},
		{
			text: '<?=addslashes($this->say('product vat', array()))?>',
			dataIndex: 'tax',
			width: 50,
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return record.get('tax_rendered');
			},
			editor: new Ext.form.field.ComboBox({
				allowBlank: false,
				displayField: 'taxName',
				listClass: 'x-combo-list-small',
				listConfig: {
					width: 200
				},
				matchFieldWidth: false,
				queryMode: 'local',
				selectOnFocus: true,
				selectOnTab: true,
				store: taxstore,
				typeAhead: true,
				valueField: 'id'
			})
		}
	];
	
	// create the Grid
	var grid = Ext.create('Ext.grid.Panel', {
		defaults: {
			listeners: {
				blur: function(component, The, eOpts) {
					alert(component);
				}
			}
		},
		id: 'gridpanel',
		store: store,
		columns: defaultColumns,
		height: 500,
//		width: 900,
		title: "<?=addslashes($this->say('traders products', array($trader->name)))?>",
//		plugins: [rowEditing],
		plugins: [cellEditing],
		selModel: {
			selType: 'cellmodel'
		},
		renderTo: 'panel',
		bbar: Ext.create('Ext.PagingToolbar', {
			store: store,
			displayInfo: true,
			displayMsg: '<?=addslashes($this->say('displaying', array()))?> {0} - {1} <?=addslashes($this->say('of', array()))?> {2} <?=addslashes($this->say('products', array()))?>',
			emptyMsg: "<?=addslashes($this->say('no products to display', array()))?>"
		}),
		tbar: [
			attributesetChooser,
			searchfield,
			{
				text: (gridEditable ? "<?=addslashes($this->say('stock unlocked grid', array()))?>" : "<?=addslashes($this->say('stock unlock grid', array()))?>"),
				enableToggle: true,
				toggleHandler: function(button, state) {
					gridEditable = state;
					button.setText((gridEditable ? "<?=addslashes($this->say('stock unlocked grid', array()))?>" : "<?=addslashes($this->say('stock unlock grid', array()))?>"));
					if(state) {
						deleteButtons.show();
						cellEditing.startEditByPosition({row: 0, column: 1});
					}
					else {
						deleteButtons.hide();
					}
				}
			}, {
				text: '<?=addslashes($this->say('insert new row', array()))?>',
				handler : function(button, event){
					if(gridEditable) {
						var pr = Ext.create('Product', {
							tax: 2
						});
						store.insert(0, pr);
						cellEditing.startEditByPosition({row: 0, column: 0});
					}
					else {
						button.toggle(false);
					}
				}
			}
		],
		viewConfig: {
			stripeRows: true
		},
		buttons: [{
			text: '<?=$this->say('print', array())?>',
			handler: function(){
				window.open('index.php?docu=stock_report&trader=<?=$_GET['trader']?>');
			}
		}]
	});
	grid.on('edit', saveChanges);
});
<?
}

public function design() {
?>
<div id="panel"></div>
<?
}

public function request($data = "") {
	$tp = $this->mysqli->table_prefix;

	switch ($data) {
	case "columns":
	{
		echo json_encode($this->mysqli->arrayData(array(
			'source' => "{$tp}attributesets_attributes INNER JOIN {$tp}attributes ON {$tp}attributesets_attributes.attribute = {$tp}attributes.id",
			'where' => "{$tp}attributesets_attributes.attributeset = '" . str_replace(",", "' OR {$tp}attributesets_attributes.attributeset = '", $this->POST['attributesets']) . "'",
			'fields' => "{$tp}attributes.*"
		)));
		break;
	}

	default:
	{
		$tp = $this->mysqli->table_prefix;
		$where = "";
		
		if(@$_GET['query']) {
			$searchset = explode(" ", $_GET['query']);
			$where = array();
			foreach($searchset as $search) {
				$where[] = "({$tp}products.productCode LIKE '%{$search}%'\n"
				.	"OR {$tp}products.name LIKE '%{$search}%'\n"
				.	"OR {$tp}products.brand LIKE '%{$search}%'\n"
				.	"OR {$tp}products.barcode LIKE '%{$search}%'\n"
				.	"OR {$tp}products.description LIKE '%{$search}%'\n"
				.	"OR {$tp}suppliers.supplier LIKE '%{$search}%'\n"
				.	"OR {$tp}product_attributes.value LIKE '%{$search}%'\n)\n";
			}
			$where = " AND " . implode(" AND ", $where);
		}
		
		$query = array(
			'class' => "Product",
			'distinct' => true,
			'source' => "{$tp}products LEFT JOIN {$tp}suppliers ON {$tp}products.supplier = {$tp}suppliers.id LEFT JOIN {$tp}product_attributes ON {$tp}products.id = {$tp}product_attributes.product LEFT JOIN {$tp}attributes ON {$tp}product_attributes.attribute = {$tp}attributes.id
",
			'fields' => "{$tp}products.id",
			'returnQuery' => true,
			'where' => "{$tp}products.trader = '" . (int)$_GET['trader']. "'" . $where
		);
		if($_GET['limit']) {
			$query['limit'] = "{$_GET['start']}, {$_GET['limit']}";
		}
		if($_GET['sort'] and isset($this->mysqli->arrayData(array('source' => "{$tp}products"))->data[0]->{$_GET['sort']})) {
			$query['orderfields'] = "{$_GET['sort']}" . ($_GET['dir'] == "DESC" ? " DESC" : " ASC") . ", ";
		}
		if($_GET['sort']) {
			$query['orderfields'] .= "IF({$tp}attributes.code = '{$_GET['sort']}', 0, 1) ASC, IF(({$tp}attributes.type = 'float' or {$tp}attributes.type = 'int'), CONVERT({$tp}product_attributes.value, DECIMAL), {$tp}product_attributes.value)" . ($_GET['dir'] == "DESC" ? " DESC" : " ASC");
		}
		$result = $this->mysqli->arrayData($query);
		foreach($result->data as $element => $product) {
			$result->data[$element] = $product->output()->data;		
			$tax = $product->getTax()->data;
			$result->data[$element]->cost_rendered = $this->money($product->cost) . ($product->unit ? "/{$product->unit}" : "");
			$result->data[$element]->price_rendered = $this->money($product->price) . ($product->unit ? "/{$product->unit}" : "");
			$result->data[$element]->in_stock_rendered = $this->number($product->inStock);
			$result->data[$element]->lowest_price_rendered = $this->money($product->lowestPrice) . ($product->unit ? "/{$product->unit}" : "");
			if($tax) {
                $result->data[$element]->tax = $tax->id;
                $result->data[$element]->tax_rendered = $tax->taxName;
			}
		}
		echo json_encode($result);
	}
	}
}

public function receive($form) {
	switch ($form) {
		default:
			echo json_encode($result);
	}
}

public function amend($data = "") {
	$tp = $this->mysqli->table_prefix;
	$dec = new NumberFormatter($this->say('locale'), NumberFormatter::DECIMAL);
	switch ($data) {
		case "edit_item":
		{
			$field = $_POST['field'];
			$value = $_POST['value'];
			$product = new Product(array(
				'id' => (int)$_POST['id'],
				'trader' => (int)$_GET['trader']
			));
			
			$attributes = $product->getAttributes();
			
			if(isset($attributes->data->$field) and $attributes->data->$field->type == "float") {
				$value = $this->parse($value);
			}

			switch($field) {
				case "cost":
				case "price":
				case "inStock":
				case "lowestPrice":
					$value = $this->parse($value);
					break;
			}
			
			$result = $product->setAttribute($field, $value);
			
			if($result->success) {
				$product->load();
				$tax = $product->getTax()->data;
				$result = $product->output();
				$result->data->cost_rendered = $this->money($result->data->cost) . ($result->data->unit ? "/{$result->data->unit}" : "");
				$result->data->price_rendered = $this->money($result->data->price) . ($result->data->unit ? "/{$result->data->unit}" : "");
				$result->data->in_stock_rendered = $this->number($result->data->inStock);
				$result->data->lowest_price_rendered = $this->money($result->data->lowestPrice) . ($result->data->unit ? "/{$result->data->unit}" : "");
				
				$result->data->tax = null;
				$result->data->tax_rendered = '';
				if($tax) {
                    $result->data->tax = $tax->id;
                    $result->data->tax_rendered = $tax->taxName;
				}
			}
			echo json_encode($result);
			break;
		}
		case "delete":
		{
			$product = new Product( (int)$_POST['product'] );
			$result = $product->delete();
			if ( !$result->success and !$result->msg ) {
				$result->msg = $this->say('failed to delete', array()) . " " . $product->name;
			}
			else if ( !$result->msg ) {
				$result->msg = $this->say('product deleted successfully');
			}
			echo json_encode($result);
			break;
		}
		default:
		{
			break;
		}
	}
}

}
?>