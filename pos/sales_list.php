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
	$tp = $this->mysqli->table_prefix;
?>
Ext.Loader.setConfig({
	enabled: true
});
Ext.Loader.setPath('Ext.ux', '<?=$this->http_host . "/" . $this->ext_library . "/examples/ux"?>');

Ext.require([
	'Ext.data.*',
	'Ext.form.*',
    'Ext.form.field.Date',
    'Ext.form.field.Number',
	'Ext.grid.*',
	'Ext.selection.CellModel',
	'Ext.state.*',
    'Ext.tip.QuickTipManager',
	'Ext.util.*',
	'Ext.ux.CheckColumn',
    'Ext.ux.RowExpander'
]);

Ext.onReady(function() {
	Ext.Loader.setConfig({enabled:true});
	Ext.QuickTips.init();
	
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
	
	Ext.define('Sale', {
		extend: 'Ext.data.Model',
		idProperty: 'id',
		fields: [
			{name: 'id', type: 'int'},
			{name: 'completed', type: 'date', dateFormat: 'Y-m-d H:i:s'},
			{name: 'completed_formatted'},
			{name: 'customerid'},
			{name: 'customername'},
			{name: 'colour'},
			{name: 'html'},
			{name: 'total', type: 'float'},
			{name: 'total_formatted'}
		]
	});
	
	var load = function() {
		store.getProxy().setExtraParam('from', Ext.Date.format(from.getValue(), 'Y-m-d'));
		store.getProxy().setExtraParam('to', Ext.Date.format(to.getValue(), 'Y-m-d'));
		store.currentPage = 1;
		store.load({
			params: {
				start: 0,
				limit: 200,
				product: productSearchField.getValue(),
				search: productSearchField.getRawValue()
			}
		});
	}

	var from = Ext.create('Ext.form.field.Date', {
		format: '<?=$this->shortdate_format(true)?>',
		startDay: <?=$this->firstDayOfWeek-1?>,
		submitFormat: 'Y-m-d',
		fieldLabel: '<?=$this->say['from date']->format(array())?>',
		name: 'from_date',
		maxValue: new Date(),
		value: new Date(<?=$this->lastSalesdate()->format('Y, m-1, d')?>),
		listeners: {
			change: load
		}
	});
	
	var to = Ext.create('Ext.form.field.Date', {
		format: '<?=$this->shortdate_format(true)?>',
		startDay: <?=$this->firstDayOfWeek-1?>,
		submitFormat: 'Y-m-d',
		fieldLabel: '<?=$this->say['to date']->format(array())?>',
		name: 'to_date',
		value: new Date(),
		listeners: {
			change: load
		}
	});

	var productStore = Ext.create('Ext.data.Store', {
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
	
	var productSearchField = Ext.create('Ext.form.field.ComboBox', {
		queryMode: 'remote',
		store: productStore,
		emptyText: '<?=$this->say('search for sold product')?>',
		hideLabel: true,
		labelWidth: 0,
		minChars: 1,
		queryDelay: 1000,
		allowBlank: true,
		displayField: 'product',
		editable: true,
		forceSelection: false,
		hideTrigger: true,
		selectOnFocus: true,
		matchFieldWidth: false,
		listWidth: 500,
		typeAhead: false,
		valueField: 'id',
		listConfig: {
			loadingText: '<?=$this->say('searching')?>',
			emptyText: 'No matching posts found.',
			maxHeight: 600,
			getInnerTpl: function() {
				return '{product}<br />{details}';
			},
			width: 500
		},
		pageSize: 10,
		width: 200,
		listeners: {
			change: load
		}
	});

	var store = Ext.create('Ext.data.JsonStore', {
		model: 'Sale',
		pageSize: 200,
		remoteSort: true,
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: "index.php?docu=sales_list&mission=request",
			extraParams: {
				from: Ext.Date.format(from.getValue(), 'Y-m-d'),
				to: Ext.Date.format(to.getValue(), 'Y-m-d')
			},
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
			property: 'created',
			direction: 'DESC'
		}],
		autoLoad: true
	});

	var columns = [
		{
			dataIndex: 'id',
			text: '#',
			width: 60,
			align: 'right',
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return "<a href=index.php?docu=sale_record&id=" + value + ">" + value + "</a>";
			},
			sortable: true
		},		
		{
			dataIndex: 'completed',
			text: '<?=$this->say['time']->format(array())?>',
			width: 250,
			align: 'right',
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return record.get('completed_formatted');
			},
			sortable: true
		},		
		{
			dataIndex: 'total',
			text: '<?=$this->say['total']->format(array())?>',
			width: 120,
			align: 'right',
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return record.get('total_formatted');
			},
			sortable: true
		}
	];
	
	var mainPanel = Ext.create('Ext.grid.Panel', {
		layout: 'border',
		store: store,
		title: '',
		iconCls: 'icon-grid',
		columns: columns,
		renderTo: 'panel',
		dockedItems: [{
			dock: 'top',
			xtype: 'toolbar',
			items: []
		}],
		plugins: [{
			ptype: 'rowexpander',
			rowBodyTpl : ['{html}']
		}],
		features: [{
			id: 'group',
			ftype: 'groupingsummary',
			groupHeaderTpl: '{name}',
			hideGroupedHeader: true,
			startCollapsed: true,
			enableGroupingMenu: false
		}],
		height: 500,
		autoScroll: true,
		autoWidth: true,
		tbar: [from, to, productSearchField],
		bbar: Ext.create('Ext.PagingToolbar', {
			store: store,
			displayInfo: true,
			displayMsg: '<?=$this->say['displaying x of x sales']->format(array("\{0\}", "\{1\}", "\{2\}"))?>',
			emptyMsg: "No <?=$this->say['no sales to display']->format(array())?>"
		})
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
			$tp = $this->mysqli->table_prefix;
			$query = array(
				'class' => "Sale",
				'distinct' => true,
				'returnQuery' => true,
				'source' => "{$tp}sales INNER JOIN {$tp}invoices ON {$tp}sales.id = {$tp}invoices.sale INNER JOIN {$tp}invoice_items ON {$tp}invoices.id = {$tp}invoice_items.invoiceId",
				'fields' => "{$tp}sales.id",
				'where' => "{$tp}sales.completed"
			);
			if($this->from) {
				$query['where'] .= " AND {$tp}sales.completed >= '{$this->from->format('U')}'";
			}
			if($this->to) {
				$query['where'] .= " AND {$tp}sales.completed <= '{$this->to->format('U')}'";
			}
			if(@$_GET['product'] and @$_GET['product'] != @$_GET['search']) {
				$query['where'] .= " AND {$tp}invoice_items.product = '{$_GET['product']}'";
			}
			if(@$_GET['search'] and @$_GET['product'] == @$_GET['search']) {
				$query['where'] .= " AND ({$tp}invoice_items.productCode LIKE '%{$_GET['search']}%' OR {$tp}invoice_items.description LIKE '%{$_GET['search']}%')";
			}
			if(@$_GET['limit']) {
				$query['limit'] = "{$_GET['start']}, {$_GET['limit']}";
			}
			if(@$_GET['sort']) {
				$query['orderfields'] = "{$tp}sales.{$_GET['sort']}" . ($_GET['dir'] == "DESC" ? " DESC" : " ASC") . ", ";
			}
			$query['orderfields'] .= "{$tp}sales.completed DESC";
			$result = $this->mysqli->arrayData($query);

			foreach($result->data as $sale) {
				$completed = $sale->checkIfCompleted()->data;
				$sale->completed_formatted = $this->long_datetime($completed);
				$invoices = $this->mysqli->arrayData(array(
					'source' => "{$tp}invoices",
					'where' => "sale = '{$sale->id}'"
				));
				foreach($invoices->data as $invoice) {
					$html = $this->say['invoice']->format(array()) . " {$invoice->invoiceNo}: " . $this->money($invoice->total) . "<br />";
				}
				$sale->html = $html;
				$sale->total_formatted = $this->money($sale->getTotal()->data);
			}
			echo json_encode($result);
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