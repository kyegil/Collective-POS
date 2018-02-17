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

Ext.define('note', {
    extend: 'Ext.data.Model',
    fields: [
        {name: 'title'},
        {name: 'text'},
        'author',
        {name: 'timestamp_rendered'}
    ]
});
    
Ext.onReady(function() {
	Ext.Loader.setConfig({enabled:true});
	Ext.QuickTips.init();
	
<?
	include_once("_menu.php");
?>

	var formPanel = Ext.create('Ext.form.Panel', {
		region:	'center',
		title: "",
		height: 550,
		renderTo: 'panel',
		autoScroll: true,
		autoWidth: true,
        reader: new Ext.data.JsonReader({
            model: 'note',
            record : 'data',
            successProperty: '@success'
        }),
		defaults: {
			border: false,
			collapsible: true,
			split: true,
			autoScroll: true
		},
		items: [{
			name:	'title',
			xtype:	'textfield',
			width:	'100%',
			allowBlank:	false,
			emptyText: '<?=$this->say('note title')?>'
		}, {
			name: 'text',
			xtype: 'htmleditor',
			width:	'100%',
			height: 420
		}, {
			layout: 'column',
			border: false,
			padding: '0 5 0 5',
			header: false,
			items: [{
//				columnWidth: 0.5,
				border: false,
				padding: '0 5 0 5',
				items: [{
					name:	'timestamp_rendered',
					xtype:	'displayfield',
					labelAlign: 'right',
					width:	'100',
					allowBlank:	false,
					fieldLabel: '<?=$this->say('note saved')?>'
	//				hideLabel: true
				}]
			}, {
//				columnWidth: 0.5,
				border: false,
				padding: '0 5 0 5',
				items: [{
					name:	'author',
					xtype:	'displayfield',
					labelAlign: 'right',
					width:	'100',
					allowBlank:	false,
					fieldLabel: '<?=$this->say('note saved by')?>'
				}]
			}]
		}],
		buttons: [{
			text: '<?=$this->say('cancel')?>',
			handler: function(){
				window.location = "index.php";
			}
		}, {
			text: '<?=$this->say('save')?>',
			disabled: true,
			formBind: true,
			handler: function(){
				this.up('form').getForm().submit({
					url: "index.php?docu=<?=$_GET['docu']?>&note=<?=(int)$_GET['note']?>&mission=receive",
					submitEmptyText: false,
					waitMsg: '<?=$this->say('saving')?>',
					success: function(form, action) {
						form.load({
							url: "index.php?docu=<?=$_GET['docu']?>&note=<?=(int)$_GET['note']?>&mission=request",
							waitMsg: '<?=$this->say('loading')?>'
						})
					}
				});
			}
		}]
	});

	formPanel.getForm().load({
		url: "index.php?docu=<?=$_GET['docu']?>&note=<?=(int)$_GET['note']?>&mission=request",
		waitMsg: '<?=$this->say('loading')?>'
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
			$result = new stdClass;
			$note = new Note($_GET['note']);
			$result->data = $note->load();
			$result->data->timestamp_rendered = $this->datetime($result->data->timestamp);
			echo json_encode($result);
			break;
	}
}

function receive($form) {
	switch ($form) {
		default:
			$note = new stdclass;
			$note->data = new Note($_GET['note']);
			if(isset($_POST['text'])) {
				echo json_encode($note->data->save($_POST['title'], $_POST['text'], $this->user['name']));
			}
	}
}

}
?>