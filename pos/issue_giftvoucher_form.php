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
	$this->current_sale = new Sale(array(
		'id' => $_GET['sale'])
	);
}

function script() {
	$tp = $this->mysqli->table_prefix;
	$activeTraders = $this->activeTraders();
	if( $this->preferences->vouchers_use_shared_prepayment_deposit ) {
		$deposit = new Trader(0);
		$b = array("{id: 0, name: '" . addslashes($deposit->name ? $deposit->name : $this->say('prepayment deposit')) . "'}");
	}
	foreach($activeTraders as $trader) {
		if($trader->preferences->vouchers) {
			$voucherTraders[] = $trader;
			$a[] = "{boxLabel: '" . addslashes($trader->name) . "', name: 'trader_" . addslashes($trader->id) . "'}";
			$b[] = "{id: {$trader->id}, name: '" . addslashes($trader->name) . "'}";
		}
	}
	$voucher = new GiftVoucher;
	
	foreach(scandir('../voucher_templates') as $file) {
		if(file_exists("../voucher_templates/" . ($d = (strstr($file, ".", true))) . ".php")) {
			$default_template = (@$default_template ? $default_template : $d);
			$design[] = "{name: '" . ($d) . "'}";
		}
	}
		
	$today = new DateTime(null, $this->timezone);
	$expires = clone $today;
	$expires->add(new DateInterval($this->preferences->vouchers_expiry_interval));
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

Ext.define('TraderModel', {
    extend: 'Ext.data.Model',
    fields: [
        {name: 'id'},
        {name: 'name'}
    ]
});
    
Ext.define('DesignModel', {
    extend: 'Ext.data.Model',
    fields: [
        {name: 'name', type: 'string'}
    ]
});
    
Ext.onReady(function() {
	Ext.Loader.setConfig({enabled:true});
	Ext.QuickTips.init();
	
<?
	include_once("_menu.php");
?>

	function autoSelectDeposit() {
		if(traders.getChecked().length == 1) {
			holdingTrader.setValue(parseInt(traders.getChecked()[0].name.substring(7)));
			holdingTrader.setReadOnly(true);
		}
<?if($this->preferences->vouchers_use_shared_prepayment_deposit):?>
		else if(traders.getChecked().length > 1) {
			holdingTrader.setValue(0);
			holdingTrader.setReadOnly(true);
		}
<?endif;?>
		else {
			holdingTrader.setReadOnly(false);
		}
	}


	function suggestCode() {
		Ext.Ajax.request({
			waitMsg: '<?=$this->say('hang on')?>',
			url: "index.php?docu=issue_giftvoucher_form&sale=<?=$this->current_sale->id?>&mission=request&data=code",
			success: function(response, opts){
				var result = Ext.JSON.decode(response.responseText);
				code.setValue(result.data);
			 }
		});
	}
	
	
	var traderStore = Ext.create('Ext.data.Store', {
		autoLoad: true,
		model: 'TraderModel',
		data: [
			<?=implode(", ", $b)?>
		]
	});


	var designStore = Ext.create('Ext.data.Store', {
		model: 'DesignModel',
		data: [<?=implode(",", $design)?>]
	});


	var code = Ext.create('Ext.form.field.Text', {
		allowBlank:	false,
		fieldLabel:	'<?=$this->say('giftvoucher code')?>',
		name: 'code',
		tabIndex: 0,
		width: 400,
		value: '<?=$voucher->generateCode()->data?>'
	});

	var codeButton = Ext.create('Ext.button.Button', {
		width: 200,
		handler: function(Button, EventObject) {
			suggestCode();
		},
		text: '<?=$this->say('giftvoucher suggest code')?>'
	});


	var traders = Ext.create('Ext.form.CheckboxGroup', {
		allowBlank: false,
		columns:		2,
		defaults: {
			inputValue: 1, checked: true
		},
		fieldLabel:		'<?=$this->say('traders')?>',
		items: [
			<?=implode(", ", $a)?>
		],
		listeners: {
			change:	autoSelectDeposit
		},
		name:			'traders',
		tabIndex:		3
	});

	var holdingTrader = Ext.create('Ext.form.field.ComboBox', {
		allowBlank:		false,
		displayField:	'name',
		fieldLabel:		'<?=$this->say('giftvoucher payment is held by')?>',
		labelWidth:		200,
		name:			'holdingTrader',
		queryMode:		'local',
		selectOnFocus:	true,
		store:			traderStore,
		tabIndex:		5,
		valueField:		'id'
	});

	var design = Ext.create('Ext.form.field.ComboBox', {
		allowBlank:	false,
		fieldLabel:	'<?=$this->say('giftvoucher design')?>',
		forceSelection: true,
		mode: 'local',
		name: 'design',
		queryMode: 'local',
		store: designStore,
		tabIndex: 6,
		valueField: 'name',
		displayField: 'name',
		value: '<?=$default_template?>'
	});
	
	var formPanel = Ext.create('Ext.form.Panel', {
		region:	'center',
		title: "",
		height: 550,
		renderTo: 'panel',
		autoScroll: true,
		autoWidth: true,
		defaults: {
			labelWidth: 200,
			border: false,
			margin: 10,
			collapsible: true,
			split: true,
			autoScroll: true,
			width: 400
		},
		items: [
			code,
			codeButton,
			{
				allowBlank:		false,
				decimalPrecision: <?=$this->monetaryPrecision?>,
				decimalSeparator: '<?=$this->decimalSeparator?>',
				fieldLabel:		'<?=$this->say('giftvoucher value')?>',
				hideTrigger:	true,
				minValue: 0.01,
				name: 			'value',
				selectOnFocus:	true,
				tabIndex:		1,
				value:			'0',
				width:			400,
				xtype:			'numberfield'
			}, {
				allowBlank:		true,
				fieldLabel:		'<?=$this->say('giftvoucher expires')?>',
				name:			'expires',
				selectOnFocus:	true,
				startDay:		<?=$this->firstDayOfWeek-1?>,
				submitFormat: 'Y-m-d',
				tabIndex:		2,
				value:			'<?=$expires->format('Y-m-d')?>',
				width:			400,
				xtype:			'datefield'
			},
			traders,
			holdingTrader,
			{
				allowBlank:		false,
				boxLabel:		'<?=$this->say('giftvoucher redeemable for cash')?>',
				checked:		false,
				fieldLabel:		'<?=$this->say('giftvoucher redeemable for cash')?>',
				hideLabel:		true,
				hideTrigger:	true,
				name:			'redeemable_for_cash',
				selectOnFocus:	true,
				tabIndex:		4,
				value:			'0',
				xtype:			'checkbox'
			}, design
		],
		buttons: [{
			text: '<?=$this->say('cancel')?>',
			handler: function(){
				window.location = 'index.php?docu=sales&sale=<?=$_GET['sale']?>';
			}
		}, {
			text: '<?=$this->say('save')?>',
			disabled: true,
			formBind: true,
			handler: function(){
				this.up('form').getForm().submit({
					url: "index.php?docu=<?=$_GET['docu']?>&sale=<?=(int)$_GET['sale']?>&mission=receive",
					submitEmptyText: false,
					waitMsg: '<?=$this->say('saving')?>',
					timeout: 20, // seconds
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
							window.location = "index.php?docu=sales&sale=" + result['id'];
						}
						else {
							Ext.MessageBox.alert('<?=$this->say('warning')?>',result['msg']);
						}
					}
				});
			}
		}]
	});

	autoSelectDeposit();


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
		case "code":
			$result = new stdClass;
			$voucher = new GiftVoucher;
			return json_encode($voucher->generateCode());
			break;
		default:
			break;
	}
}

function receive($form) {
	switch ($form) {
		default:
			foreach($_POST as $variable => $value) {
				if($a = strstr($variable, "trader_") and $value) {
					$traders[] = new Trader(substr($variable, 7));
				}
			}
			$result = $this->current_sale->issueGiftVoucher(
				$this->preferences->vouchers_use_shared_prepayment_deposit,
				array(
					'code'						=> $_POST['code'],
					'value'						=> $this->parse($_POST['value']),
					'traders'					=> $traders,
					'prepaymentholdingTrader'	=> new Trader($_POST['holdingTrader']),
					'expires'					=> new DateTime($_POST['expires'], $this->timezone),
					'redeemableForCash'			=> (bool)@$_POST['redeemable_for_cash'],
					'design'					=> $this->POST['design']
				),
				$this->user['name']
			);
			$result->id = $this->current_sale->id;
			if($result->msg) {
				$result->msg = $this->say($result->msg);
			}
			echo json_encode($result);
			break;
	}
}

}
?>