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
			url: "index.php?docu=<?=$_GET['docu']?>&trader=<?=$trader->id?>&mission=request",
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