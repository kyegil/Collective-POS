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
	$tp = $this->mysqli->table_prefix;
	$query = array(
		'source' => "{$tp}"
	);
	if($_GET['limit']) {
		$query['limit'] = "{$_GET['start']}, {$_GET['limit']}";
	}
	if($_GET['sort']) {
		$query['orderfields'] = "{$_GET['sort']}" . ($_GET['dir'] == "DESC" ? " DESC" : " ASC");
	}
	$this->main_data = $this->mysqli->arrayData($query);
}

function script() {
	$tp = $this->mysqli->table_prefix;
	$trader = new Trader($_GET['trader']);
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

	Ext.define('Entry', {
		extend: 'Ext.data.Model',
		idProperty: 'id',
		fields: [
<?
	$fields = array_keys($this->main_data['data'][0]);
	echo "\t\t\t\t{name: '" . implode("'},\n\t\t\t\t{name: '", $fields) . "'}\n";
	foreach($this->main_data['data'][0] as $field => $value) {
//		echo "\t\t\t\t{name: '$field'},\n";
	}
?>
//				{name: 'xx', type: 'int'},
//				{name: 'xx', type: 'date', dateFormat: 'Y-m-d H:i:s'},
//				{name: 'xx', type: 'float'},
		]
	});
	
	var load = function() {
		store.load({
			url: "index.php?docu=<?=$_GET['docu']?>&mission=request&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d')
		});
	}

	var from = Ext.create('Ext.form.field.Date', {
		format: '<?=$this->shortdate_format(true)?>',
		submitFormat: 'Y-m-d',
		fieldLabel: '<?=$this->say['from date']->format(array())?>',
		name: 'from_date',
		maxValue: new Date(),
		value: new Date(),
		listeners: {
			change: load
		}
	});
	
	var to = Ext.create('Ext.form.field.Date', {
		format: '<?=$this->shortdate_format(true)?>',
		submitFormat: 'Y-m-d',
		fieldLabel: '<?=$this->say['to date']->format(array())?>',
		name: 'to_date',
		value: new Date(),
		listeners: {
			change: load
		}
	});

	var store = Ext.create('Ext.data.Store', {
		model: 'Entry',
		pageSize: 50,
		remoteSort: true,
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: "index.php?docu=<?=$_GET['docu']?>&mission=request&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d'),
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
			property: '<?=array_shift($fields)?>',
			direction: 'DESC'
		}],
		groupField: '<?=array_shift($fields)?>',
		autoLoad: true
	});

	var columns = [
<?
	$fields = array_keys($this->main_data['data'][0]);
	echo "\t\t\t\t{dataIndex: '" . implode("'},\n\t\t\t\t{dataIndex: '", $fields) . "'}\n";
?>
// 		{
// 			dataIndex: 'id',
// 			text: '<?=$this->say['payment id']->format(array())?>',
// 			width: 40,
// 			hidden: true,
// 			align: 'right',
// 			sortable: true
// 		},		
	];
	
    var showSummary = true;
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
		tbar: [from, to]
// 		bbar: Ext.create('Ext.PagingToolbar', {
// 			store: store,
// 			displayInfo: true,
// 			displayMsg: '<?=$this->say['displaying']->format(array())?> {0} - {1} <?=$this->say['of']->format(array())?> {2} <?=$this->say['payments']->format(array())?>',
// 			emptyMsg: "<?=$this->say['no products to display']->format(array())?>"
// 		})
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
	$trader = new Trader($_GET['trader']);
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