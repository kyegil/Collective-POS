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
}

function script() {
	$tp		= $this->mysqli->table_prefix;
	$trader	= new Trader($_GET['trader']);
	$count	= (int)$_GET['count'];
	$start	= (int)$_GET['start'];
	
	$products = $this->mysqli->arrayData(array(
		'source'	=> "{$tp}products LEFT JOIN {$tp}suppliers ON {$tp}products.supplier = {$tp}suppliers.id LEFT JOIN (SELECT DISTINCT product_id FROM {$tp}inventory_count WHERE count_no = '$count') AS counted ON {$tp}products.id = counted.product_id",
		'where'		=> "{$tp}products.trader = '{$trader->id}' AND counted.product_id IS NULL",
		'distinct'	=> true,
		'limit'		=> "{$start}, 50",
		'fields'	=> "{$tp}products.*",
		'orderfields' => "{$tp}suppliers.supplier, brand, productCode"
	))->data;
?>
Ext.Loader.setConfig({
	enabled: true
});
Ext.Loader.setPath('Ext.ux', '<?=$this->http_host . "/" . $this->ext_library . "/examples/ux"?>');

Ext.require([
	'Ext.form.*'
]);

Ext.onReady(function() {
	Ext.Loader.setConfig({enabled:true});
	Ext.QuickTips.init();
	
<?
	include_once("_menu.php");
?>

<?
	foreach($products as $index => $product) {
?>
	var skip<?=$product->id?> = Ext.create('Ext.form.field.Checkbox', {
		hideLabel	: true,
		xtype		: 'checkboxfield',
		fieldLabel	: 'Skip',
		boxLabel	: 'Skip',
		name		: 'skip<?=$product->id?>',
		width		: 100,
		inputValue	: '1',
		checked		: true,
		id			: 'skip<?=$product->id?>'
	});

	var quantity<?=$product->id?> = Ext.create('Ext.form.field.Number', {
		hideLabel	: true,
		xtype		: 'numberfield',
		tabIndex	: <?=1 + $index?>,
		anchor		: '100%',
		name		: 'quantity<?=$product->id?>',
		width		: 50,
		fieldLabel	: '<?=$this->say('product quantity')?>',
		value		: <?=$product->inStock?>,
		decimalSeparator: '<?=$this->say['decimal_separator']?>',
		listeners	: {
			focus: function(field, eventObj, eOpts) {
				skip<?=$product->id?>.setValue(false);
			}
		},
		hideTrigger	: true,
		keyNavEnabled	: false,
		mouseWheelEnabled	: false
	});

<?
	}
?>
	var mainPanel = Ext.create('Ext.form.Panel', {
		layout: 'form',
		anchor: '85%',
//		bodyPadding: '5 5 0',
		renderTo: 'panel',
		buttons: [{
			text: '<?=$this->say('cancel')?>',
			handler: function() {
				this.up('form').getForm().reset();
			}
		}, {
			text: '<?=$this->say('save')?>',
			handler: function() {
				this.up('form').getForm().isValid();
				this.up('form').getForm().submit({
					timeout: 20, // seconds
					url:'index.php?docu=inventory_count_form&trader=<?=$trader->id?>&count=<?=(int)$_GET['count']?>&mission=receive&form=count',
					waitMsg:'<?=$this->say('hang on')?>',
					failure: function(form, action) {
						var result = Ext.JSON.decode(action.response.responseText);

						if(result.msg) {
							Ext.MessageBox.alert('<?=$this->say('warning')?>', result.msg);
						}
						else {
							Ext.MessageBox.alert('<?=$this->say('unable to save due to unknown error')?>');
						}
					},
					success: function(form, action) {
						var result = Ext.JSON.decode(action.response.responseText);
						
						if(result['success'] == true) {
							if(result.msg) {
								Ext.MessageBox.alert('<?=$this->say('notice')?>', result.msg);
							}
							window.location = "index.php?docu=inventory_count_form&trader=<?=$_GET['trader']?>&count=" + <?=((int)$_GET['count'] ? $_GET['count'] : "result['count']")?>;
						}
						else {
							Ext.MessageBox.alert('<?=$this->say('warning')?>',result['msg']);
						}
					}

				});
			}
		}, {
			text: '<?=$this->say('previous')?>',
			handler: function() {
				window.location = "index.php?docu=inventory_count_form&trader=<?=$trader->id?>&count=<?=(int)$_GET['count']?>&start=<?=$_GET['start'] - 50?>";
			}
		}, {
			text: '<?=$this->say('next')?>',
			handler: function() {
				window.location = "index.php?docu=inventory_count_form&trader=<?=$trader->id?>&count=<?=(int)$_GET['count']?>&start=<?=$_GET['start'] + 50?>";
			}
		}],
		autoScroll: true,
		items: [{
			xtype: 'textfield',
			allowBlank: false,
			width: 250,
			name: 'count_name',
			value: '<?=addslashes($this->getCountName($_GET['count']))?>'
		}
<?
	foreach($products as $index => $product) {
?>
			, {
            xtype: 'container',
            anchor: '85%',
            layout: 'hbox',
			items:[{
					xtype: 'container',
					flex: 1,
					layout: 'anchor',
					items: [{
						xtype		: 'displayfield',
						hideLabel	: true,
						name		: 'productCode<?=$product->id?>',
						value		: '<?=addslashes($product->productCode)?>'
					}]
				},{
					xtype: 'container',
					flex: 1,
					layout: 'anchor',
					items: [{
						xtype		: 'displayfield',
						name		: 'name<?=$product->id?>',
						value		: '<?=addslashes($product->name)?>'
					}]
			   },{
					xtype: 'container',
					layout: 'anchor',
					items: [{
						xtype		: 'displayfield',
						name		: 'calculated<?=$product->id?>',
						width		: 100,
						value		: '<?=$product->inStock?>'
					}]
			   },{
					xtype: 'container',
					layout: 'anchor',
					items: [skip<?=$product->id?>]
				}, {
					xtype: 'container',
					layout: 'anchor',
					items: [quantity<?=$product->id?>]
				}]
			}
<?
	}
?>
		],
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
	$tp = $this->mysqli->table_prefix;
	switch ($form) {
		default:
			$result = new stdclass;
			$result->success = false;
			$count = $_GET['count'];
			foreach($_POST as $id => $value) {
				if(substr($id, 0, 8) == "quantity" and !$_POST["skip" . substr($id, 8)]) {
					$product = new Product(array(
						'id' => substr($id, 8)
					));
					if($product->getTrader()->data->id == $_GET['trader']) {
						$result = $product->count($value, $count, $_POST['count_name']);
						if($result->success) {
							$count = $result->count;
						}
					}
				}
			}
			echo json_encode($result);
	}
}

}
?>