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
	$this->title = addslashes($trader->getInventoryCountName($_GET['count']));
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
			{name: 'id', type: 'int'},
			{name: 'product_id', type: 'int'},
			{name: 'productCode', type: 'string'},
			{name: 'name', type: 'string'},
			{name: 'brand', type: 'string'},
			{name: 'supplierid', type: 'int'},
			{name: 'supplier', type: 'string'},
			{name: 'calculated_quantity', type: 'float'},
			{name: 'calculated_quantity_rendered', type: 'string'},
			{name: 'actual_quantity', type: 'float'},
			{name: 'actual_quantity_rendered', type: 'string'},
			{name: 'difference', type: 'float'},
			{name: 'difference_rendered', type: 'string'},
			{name: 'value_per', type: 'float'},
			{name: 'value_per_rendered', type: 'string'},
			{name: 'value', type: 'float'},
			{name: 'value_rendered', type: 'string'}
		]
	});
	
	var excludeSorButton = Ext.create('Ext.button.Button', {
		text: '<?=addslashes($this->say("count button exclude sor items"))?>',
		enableToggle: true,
		toggleHandler: function(button, state) {
			store.load({
				params: {
					trader: <?=$trader->id?>,
					count: <?=(int)$_GET['count']?>,
					exclude_sor: Number(state)
				}
			});
		}
	});
	

	var store = Ext.create('Ext.data.Store', {
		model: 'Record',
		pageSize: 200,
		remoteSort: true,
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: 'index.php?docu=inventory_count_list&mission=request',
			extraParams: {
				trader: <?=$trader->id?>,
				count: <?=(int)$_GET['count']?>,
				exclude_sor: Number(excludeSorButton.pressed)
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
				text: '<?=$this->say('product code')?>',
				dataIndex: 'productCode',
				sortable: false,
				width: 100
			},
			{
				text: '<?=$this->say('product name')?>',
				dataIndex: 'name',
				sortable: false,
				width: 150
			},
			{
				text: '<?=$this->say('product brand')?>',
				dataIndex: 'brand',
				sortable: false,
				width: 120
			},
			{
				text: '<?=$this->say('product supplier')?>',
				dataIndex: 'supplier',
				sortable: false,
				width: 120
			},
			{
				text: '<?=$this->say('count calculated quantity')?>',
				dataIndex: 'calculated_quantity',
				align: 'right',
				sortable: false,
				width: 100,
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return record.get('calculated_quantity_rendered');
				}
			},
			{
				text: '<?=$this->say('count actual quantity')?>',
				dataIndex: 'actual_quantity',
				align: 'right',
				sortable: false,
				width: 100,
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return record.get('actual_quantity_rendered');
				}
			},
			{
				text: '<?=$this->say('count difference')?>',
				dataIndex: 'difference',
				align: 'right',
				sortable: false,
				width: 50,
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					if(value)
					return record.get('difference_rendered');
				}
			},
			{
				text: '<?=$this->say('count value per')?>',
				dataIndex: 'value_per',
				align: 'right',
				sortable: false,
				width: 80,
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return record.get('value_per_rendered');
				}
			},
			{
				text: '<?=$this->say('count value')?>',
				dataIndex: 'value',
				align: 'right',
				sortable: false,
				width: 80,
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return record.get('value_rendered');
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
		buttons: [excludeSorButton, {
			xtype: 'button',
			text: '<?=addslashes($this->say('print'))?>',
			handler: function(button, event) {
				window.open("index.php?docu=inventory_count_report&trader=<?=$_GET['trader']?>&count=<?=$_GET['count']?>&exclude_sor=" + Number(excludeSorButton.pressed));
			}
		}, {
			xtype: 'button',
			text: '<?=addslashes($this->say('export data'))?>',
			handler: function(button, event) {
				window.location = "index.php?docu=inventory_count_export&trader=<?=$_GET['trader']?>&count=<?=$_GET['count']?>&exclude_sor=" + Number(excludeSorButton.pressed);
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
		case 'file':
			$this->title = "export";
			$this->template = "file";

			break;
		default:
			$result = new stdclass;
			$stock = $trader->getInventoryCount($count, (bool)$_GET['exclude_sor']);
			$result->totalRows = count($stock);
			if($_GET['limit']) {
				$result->data = array_slice($stock, $_GET['start'], $_GET['limit']);
			}
			else {
				$result->data = $stock;
			}
			
			foreach($result->data as $index => $item) {
				$result->data[$index]->calculated_quantity_rendered = $this->number($item->calculated_quantity);
				$result->data[$index]->actual_quantity_rendered = $this->number($item->actual_quantity);
				$result->data[$index]->difference = $item->actual_quantity - $item->calculated_quantity;
				$result->data[$index]->difference_rendered = $this->relative($item->actual_quantity - $item->calculated_quantity);
				$result->data[$index]->value_per_rendered = $this->money($item->value_per);
				$result->data[$index]->value_rendered = $this->money($item->value);
			}
			echo json_encode($result);
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