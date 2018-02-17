<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
if(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {
	public $template = "HTML";
	

function __construct() {
	parent::__construct();
	if(!$_GET['trader']) die("Illegal docu: No trader ID given");
	$tp = $this->mysqli->table_prefix;
	$trader	= new Trader($_GET['trader']);
	$this->title = addslashes($this->say('count list of counts'));
}

function script() {
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

	Ext.define('Record', {
		extend: 'Ext.data.Model',
		fields: [
			{name: 'count_no', type: 'int'},
			{name: 'count_name', type: 'string'},
			{name: 'complete', type: 'bool'}
		]
	});
	

	var store = Ext.create('Ext.data.Store', {
		model: 'Record',
		pageSize: 200,
		remoteSort: false,
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: 'index.php?docu=inventory_counts_list&mission=request',
			extraParams: {
				trader: <?=$trader->id?>
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
			property: 'id',
			direction: 'DESC'
		}],
		autoLoad: true
	});
	

	// create the Grid
	var grid = Ext.create('Ext.grid.Panel', {
		id: 'gridpanel',
		store: store,
		columns: [
			{
				text: '<?=$this->say('count name')?>',
				dataIndex: 'count_name',
				flex: 1,
				sortable: false,
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return '<a href="index.php?docu=inventory_count_list&trader=<?=$trader->id?>&count=' + record.data.count_no + '">' + value + '</a>';
				}
			}, {
				dataIndex: 'complete',
				flex: 1,
				sortable: false,
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					if(!value) {
						return '<a href="index.php?docu=inventory_count_form&trader=<?=$trader->id?>&count=' + record.data.count_no + '"><img src="../images/edit.png" /></a>';
					}
				}
			}
		],
		height: 500,
//		width: 900,
		title: "<?=$this->title?>",
		selModel: {
			selType: 'cellmodel'
		},
		renderTo: 'panel',
		bbar: Ext.create('Ext.PagingToolbar', {
			store: store,
			displayInfo: true,
			displayMsg: '<?=$this->say['displaying']->format(array())?> {0} - {1} <?=$this->say['of']->format(array())?> {2} <?=$this->say['products']->format(array())?>',
			emptyMsg: "<?=$this->say['no products to display']->format(array())?>"
		}),
		buttons: [{
			text: '<?=$this->say('print current stock')?>',
			handler: function(){
				window.open('index.php?docu=stock_report&trader=<?=$_GET['trader']?>');
			}
		}, {
			text: '<?=$this->say('new inventory count')?>',
			handler: function(){
				window.location = 'index.php?docu=inventory_count_form&trader=<?=$_GET['trader']?>&count=*';
			}
		}],
		viewConfig: {
			stripeRows: true
		}
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
	$trader	= new Trader($_GET['trader']);
	$count	= (int)$_GET['count'];
	$tp = $this->mysqli->table_prefix;
	switch ($data) {
		default:
			$result = new stdclass;
			$result->data = $trader->getInventoryCounts();
			$result->totalRows = count($result->data);
			
			echo json_encode($trader->getInventoryCounts());
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