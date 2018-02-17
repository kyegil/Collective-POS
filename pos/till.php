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
Ext.Loader.setPath('Ext.ux', '<?="{$this->http_host}/{$this->ext_library}/examples/ux"?>');

Ext.require([
 	'Ext.data.*',
 	'Ext.form.*',
    'Ext.layout.container.Border'
]);

Ext.onReady(function(){
	Ext.form.Field.prototype.msgTarget = 'side';
	
<?
	include_once("_menu.php");
?>

	allocate = function(value) {
		allocation.setValue(value);
		if(value < 0) {
//			allocation.setMinValue(value);
//			allocation.setMaxValue(0);
		}
		else {
//			allocation.setMinValue(0);
//			allocation.setMaxValue(value);
		}
		allocWindow.show();
	}

	var load = function() {
		history.getLoader().load({
			url: "index.php?docu=till&mission=request&data=recent&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d')
		});
	}
	
	var from = Ext.create('Ext.form.field.Date', {
		format: '<?=$this->shortdate_format(true)?>',
		startDay: <?=$this->firstDayOfWeek-1?>,
		submitFormat: 'Y-m-d',
		fieldLabel: '<?=$this->say('from date')?>',
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
		fieldLabel: '<?=$this->say('to date')?>',
		name: 'to_date',
		value: new Date(),
		listeners: {
			change: load
		}
	});

	confirmCount = function(value) {
		Ext.Msg.show({
			title: '<?=$this->say('are you sure')?>',
			msg: '<?=$this->say('till count submit confirm')?>' + ': ' + value,
			buttons: Ext.Msg.OKCANCEL,
			fn: function(buttonId, text, opt){
				if(buttonId == 'ok') {
					countForm.form.submit({
						url:'index.php?docu=till&mission=receive&form=count_form',
						waitMsg:'<?=$this->say('hang on')?>'
					});
				}
			},
			animEl: 'elId',
			icon: Ext.MessageBox.QUESTION
		});
	}

	confirmClear = function(value) {
		Ext.Msg.show({
			title: '<?=$this->say('are you sure')?>',
			msg: '<?=$this->say('till clear confirm')?>',
			buttons: Ext.Msg.OKCANCEL,
			fn: function(buttonId, text, opt){
				if(buttonId == 'ok') {
					Ext.Ajax.request({
						waitMsg: '<?=$this->say('hang on')?>',
						url: "index.php?docu=till&mission=amend&data=clear",
						 success :function(response, opts){
							location.reload();
						 }
					});
				}
			},
			animEl: 'elId',
			icon: Ext.MessageBox.QUESTION
		});
	}

<?
	foreach($this->preferences->till_values as $element=>$till_value) {
		$subformula[] = "value$element.value * {$till_value->value}"
?>
	var value<?=$element?> = Ext.create('Ext.form.field.Number', {
		allowBlank: true,
		allowDecimals: false,
		hideTrigger: true,
		enableKeyEvents: true,
		selectOnFocus: true,
		decimalSeparator: '<?=$this->say('decimal_separator')?>',
		fieldLabel: '<?=$till_value->denomination?>',
		name: 'value<?=$element?>',
		width: 150,
		listeners: {
			keyup: updateTotalCount
		},
		value: '0'
	});

	
<?
		$value[] = "value$element";
	}
	$valueset = implode(", ", $value);
	$summary_formula = implode(" + ", $subformula);
	$traders = $this->mysqli->arrayData(array('source' => $this->mysqli->table_prefix . "traders"));
	$a = array();
	foreach($traders->data as $trader){
		$a[] = "{name: 'trader', boxLabel: '{$trader->name}', inputValue: '{$trader->id}'}";
	}
?>
	function updateTotalCount() {
		totalCount.setValue(<?=$summary_formula?>);
	}

	var totalCount = Ext.create('Ext.form.field.Number', {
		allowBlank: false,
		allowDecimals: true,
		hideTrigger: true,
		decimalSeparator: '<?=$this->say('decimal_separator')?>',
		fieldLabel: '<?=$this->say('total')?>',
		name: 'total_count',
		width: 150,
		value: '0'
	});

	var depositTrader = Ext.create('Ext.form.RadioGroup', {
		allowBlank: false,
		fieldLabel: '<?=$this->say('trader')?>',
		columns: 2,
		items: [
				<?=implode(", ", $a)?>
		]
	});

	var deposit = Ext.create('Ext.form.field.Number', {
		allowBlank: false,
		allowNegative: false,
		allowDecimals: true,
		hideTrigger: true,
		decimalSeparator: '<?=$this->say('decimal_separator')?>',
		selectOnFocus: true,
		fieldLabel: '<?=$this->say('sum')?>',
		name: 'deposit',
		width: 200,
		value: '0'
	});

	var depositNote = Ext.create('Ext.form.field.TextArea', {
		fieldLabel: '<?=$this->say('note')?>',
		name: 'deposit_note',
		height: 70,
		width: 200
	});

	var depositForm = Ext.create('Ext.form.FormPanel', {
		bodyStyle: 'padding: 15px',
		items:[depositTrader, deposit, depositNote],
		buttons: [{
			handler: function(){
				depositForm.form.submit({
					url:'index.php?docu=till&mission=receive&form=deposit_form',
					waitMsg:'<?=$this->say('hang on')?>'
				});
			},
			text: '<?=$this->say('submit')?>'
		}]
	});
	depositForm.on({
		actioncomplete: function(form, action){
			if(action.type == 'submit'){
				if(action.response.responseText == '') {
					Ext.MessageBox.alert('Problem', '<?=$this->say('missing confirmation from server')?>');
				} else {
					window.location = "index.php?docu=till";
				}
			}
		},
		actionfailed: function(form, action){
			if(action.type == 'submit') {
				if (action.failureType == "connect") {
					Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('lost connection to server')?>');
				}
				else if(action.response) {	
					var result = Ext.decode(action.response.responseText);
					if(result && result.msg) {			
						Ext.MessageBox.alert('<?=$this->say('received following message from DB server')?>:', result.msg);
					}
					else {
						Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('operation failed due to unknown error')?>: <?=$this->say('action type')?>=' + action.type + ', <?=$this->say('failure type')?>=' + action.failureType);
					}
				}
			}
			
		}
	});

	var depositWindow = Ext.create('Ext.window.Window', {
		title: '<?=$this->say('till put money in')?>',
		width: 600,
//		height: 300,
		closeAction: 'hide',
		modal: true,
		items: [depositForm]
	});

	var withdrawalTrader = Ext.create('Ext.form.RadioGroup', {
		allowBlank: false,
		fieldLabel: '<?=$this->say('trader')?>',
		columns: 2,
		items: [
				<?=implode(", ", $a)?>
		]
	});

	var withdrawal = Ext.create('Ext.form.field.Number', {
		allowBlank: false,
		allowNegative: false,
		allowDecimals: true,
		hideTrigger: true,
		decimalSeparator: '<?=$this->say('decimal_separator')?>',
		selectOnFocus: true,
		fieldLabel: '<?=$this->say('sum')?>',
		name: 'withdrawal',
		width: 200,
		value: '0'
	});

	var withdrawalNote = Ext.create('Ext.form.field.TextArea', {
		fieldLabel: '<?=$this->say('note')?>',
		name: 'withdrawal_note',
		height: 70,
		width: 200
	});

	var withdrawalForm = Ext.create('Ext.form.FormPanel', {
		bodyStyle: 'padding: 15px',
		items:[withdrawalTrader, withdrawal, withdrawalNote],
		buttons: [{
			handler: function(){
				withdrawalForm.form.submit({
					url:'index.php?docu=till&mission=receive&form=withdrawal_form',
					waitMsg:'<?=$this->say('hang on')?>'
				});
			},
			text: '<?=$this->say('submit')?>'
		}]
	});

	var withdrawalWindow = Ext.create('Ext.window.Window', {
		title: '<?=$this->say('till take money out')?>',
		width: 600,
//		height: 300,
		closeAction: 'hide',
		modal: true,
		items: [withdrawalForm]
	});

	withdrawalForm.on({
		actioncomplete: function(form, action){
			if(action.type == 'submit'){
				if(action.response.responseText == '') {
					Ext.MessageBox.alert('Problem', '<?=$this->say('missing confirmation from server')?>');
				} else {
					window.location = "index.php?docu=till";
				}
			}
		},
		actionfailed: function(form, action){
			if(action.type == 'submit') {
				if (action.failureType == "connect") {
					Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('lost connection to server')?>');
				}
				else if(action.response) {	
					var result = Ext.decode(action.response.responseText);
					if(result && result.msg) {			
						Ext.MessageBox.alert('<?=$this->say('received following message from DB server')?>:', result.msg);
					}
					else {
						Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('operation failed due to unknown error')?>: <?=$this->say('action type')?>=' + action.type + ', <?=$this->say('failure type')?>=' + action.failureType);
					}
				}
			}
			
		}
	});

	var allocTrader = Ext.create('Ext.form.RadioGroup', {
		allowBlank: false,
		fieldLabel: '<?=$this->say('trader')?>',
		columns: 2,
		items: [
				<?=implode(", ", $a)?>
		]
	});

	var allocation = Ext.create('Ext.form.field.Number', {
		allowBlank: false,
		allowNegative: true,
		allowDecimals: true,
		hideTrigger: true,
		decimalSeparator: '<?=$this->say('decimal_separator')?>',
		selectOnFocus: true,
		fieldLabel: '<?=$this->say('sum')?>',
		name: 'allocation',
		width: 200,
		value: '0'
	});

	var allocNote = Ext.create('Ext.form.field.TextArea', {
		fieldLabel: '<?=$this->say('note')?>',
		name: 'alloc_note',
		height: 70,
		width: 200
	});

	var allocForm = Ext.create('Ext.form.FormPanel', {
		bodyStyle: 'padding: 15px',
		items:[allocTrader, allocation, allocNote],
		buttons: [{
			handler: function(){
				allocForm.form.submit({
					url:'index.php?docu=till&mission=receive&form=alloc_form',
					waitMsg:'<?=$this->say('hang on')?>'
				});
			},
			text: '<?=$this->say('submit')?>'
		}]
	});

	var allocWindow = Ext.create('Ext.window.Window', {
		title: '<?=$this->say('till allocate discrepancy')?>',
		width: 600,
//		height: 300,
		closeAction: 'hide',
		modal: true,
		items: [allocForm]
	});

	allocForm.on({
		actioncomplete: function(form, action){
			if(action.type == 'submit'){
				if(action.response.responseText == '') {
					Ext.MessageBox.alert('Problem', '<?=$this->say('missing confirmation from server')?>');
				} else {
					window.location = "index.php?docu=till";
				}
			}
		},
		actionfailed: function(form, action){
			if(action.type == 'submit') {
				if (action.failureType == "connect") {
					Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('lost connection to server')?>');
				}
				else if(action.response) {	
					var result = Ext.decode(action.response.responseText);
					if(result && result.msg) {			
						Ext.MessageBox.alert('<?=$this->say('received following message from DB server')?>:', result.msg);
					}
					else {
						Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('operation failed due to unknown error')?>: <?=$this->say('action type')?>=' + action.type + ', <?=$this->say('failure type')?>=' + action.failureType);
					}
				}
			}
			
		}
	});

	var countForm = Ext.create('Ext.form.FormPanel', {
		region:	'east',
		autoScroll: true,
		collapsible: true,
		collapsed: true,
		width: 250,
		bodyStyle: 'padding: 15px',
		title: '<?=$this->say('till count till')?>',
		items:[<?=$valueset?>, totalCount],
		buttons: [{
			handler: function(){
				confirmCount(totalCount.value);
			},
			text: '<?=$this->say('till count submit')?>',
			width: 140,
			scale: 'large'
		}]
	});
	
	countForm.on({
		actioncomplete: function(form, action){
			if(action.type == 'submit'){
				if(action.response.responseText == '') {
					Ext.MessageBox.alert('Problem', '<?=$this->say('missing confirmation from server')?>');
				} else {
					window.location = "index.php?docu=till";
				}
			}
		},
		actionfailed: function(form, action){
			if(action.type == 'submit') {
				if (action.failureType == "connect") {
					Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('lost connection to server')?>');
				}
				else if(action.response) {	
					var result = Ext.decode(action.response.responseText);
					if(result && result.msg) {			
						Ext.MessageBox.alert('<?=$this->say('received following message from DB server')?>:', result.msg);
					}
					else {
						Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('operation failed due to unknown error')?>: <?=$this->say('action type')?>=' + action.type + ', <?=$this->say('failure type')?>=' + action.failureType);
					}
				}
			}
			
		}
	});

	var history = Ext.create('Ext.panel.Panel', {
		title: '<?=$this->say('till transactions')?>',
		tbar: [from, to],
		region:	'center',
		autoLoad: "index.php?docu=till&mission=request&data=recent&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d'),
		autoScroll: true,
		border: false
	});

	var status = Ext.create('Ext.panel.Panel', {
		autoLoad: "index.php?docu=till&mission=request&data=till_content",
		width: 250,
		collapsible: false,
		region:	'west',
		margins: '5 0 0 0'
	});

	var mainPanel = Ext.create('Ext.panel.Panel', {
		layout: 'border',
		renderTo: 'panel',
		defaults: {
			split: true,
			bodyStyle: 'padding: 15px'
		},
		items: [status, countForm, history],
		height: 500,
		autoWidth: true,
		buttons: [{
			text: '<?=$this->say('cancel')?>',
			handler: function() {
				window.location = "index.php";
			},
			width: 140,
			scale: 'large'
		}, {
			handler: function(){
				confirmClear();
			},
			text: '<?=$this->say('till clear')?>',
			width: 140,
			scale: 'large'
		}, {
			text: '<?=$this->say('till put money in')?>',
			handler: function() {
				depositWindow.show();
			},
			width: 140,
			scale: 'large'
		}, {
			text: '<?=$this->say('till take money out')?>',
			handler: function() {
				withdrawalWindow.show();
			},
			width: 140,
			scale: 'large'
		}, {
			text: '<?=$this->say('till count till')?>',
			handler: function() {
				countForm.expand();
			},
			width: 140,
			scale: 'large'
		}]

//		width: 900
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
	
	case "till_content":
		echo "<table><tr><td colspan =\"2\" style=\"font-size:24px;\">" . $this->say('till content x', array($this->money($this->till->content))). "</td></tr>";
		foreach($this->till->getTraders() as $trader) {
			echo "<tr><td>" . ($trader->id == 0 ? $this->say('prepayment deposit') : $trader->name) . ":</td><td style=\"text-align: right; padding-left: 5px;\">" . $this->money($this->till->getBalance($trader->id)) . "</td></tr>";
		}
		if($difference = $this->till->getDifference()) {
			echo "<tr><td>" . $this->say('till discrepancy') . ":</td><td style=\"text-align: right; padding-left: 5px;\">{$this->money($difference)}</td></tr>";
			echo "<tr><td colspan=\"2\"><a onclick=\"allocate({$difference})\">" . $this->say('till allocate discrepancy') . "</a></td></tr>";
		}
		echo "</table>";
		break;
		
	case "recent":
		$bg = false;
		echo "<table style=\"border-spacing: 0; border-collapse: collapse;\">\n";
		foreach($this->till->recent(false, 0, $this->from, $this->to->setTime(23, 59, 59)) as $transaction) {
			$bg = !$bg;
			echo	"\t<tr style=\"background-color: " . ($bg ? "#cccccc" : "none") . ";\">\n"
				.	"\t\t<td style=\"padding: 0px 10px;\">{$this->datetime($transaction->time)}</td>\n"
				.	"\t\t<td style=\"font-weight: bold; padding: 0px 10px;\">" . ($transaction->sale ? "<a href=\"index.php?docu=sale_record&id={$transaction->sale}\">" : "") . $this->say("till {$transaction->action}") . ($transaction->sale ? "</a>" : "") ."</td>\n"
				.	"\t\t<td style=\"font-weight: bold; padding: 0px 10px; text-align: right;\">" . ($transaction->adjustment ? $this->money($transaction->adjustment): "") . "</td>\n"
				.	"\t\t<td style=\"font-weight: bold; padding: 0px 10px; text-align: right;\">" . $this->money($transaction->content) . "</td>\n"
				.	"\t\t<td style=\"padding: 0px 10px;\">{$transaction->note}</td>\n"
				.	"\t\t<td style=\"padding: 0px 10px;\">" . ( $transaction->recorder ? $this->get_user($transaction->recorder) : "" ) . "</td>\n"
				.	"</tr>\n";
			foreach($transaction->accounts as $account) {
				echo	"\t<tr style=\"background-color: " . ($bg ? "#cccccc" : "none") . ";\">\n"
					.	"\t\t<td>&nbsp;</td>\n"
					.	"\t\t<td style=\"padding: 0px 10px;\">" . ($account->trader->id == 0 ? $this->say('prepayment deposit') : $account->trader->name) . "</td>\n"
					.	"\t\t<td style=\"padding: 0px 10px; text-align: right;\">" . ($account->adjustment ? $this->money($account->adjustment) : "") . "</td>\n"
					.	"\t\t<td style=\"padding: 0px 10px; text-align: right;\">" . $this->money($account->balance) . "</td>\n"
					.	"\t\t<td>&nbsp;</td>\n"
					.	"\t\t<td>&nbsp;</td>\n"
					.	"</tr>\n";
			}
			if($transaction->difference) {
				echo	"\t<tr style=\"background-color: " . ($bg ? "#cccccc" : "none") . ";\">\n"
					.	"\t\t<td style=\"padding: 0px 10px;\">&nbsp;</td>\n"
					.	"\t\t<td style=\"padding: 0px 10px;\">" . $this->say('till discrepancy') . "</td>\n"
					.	"\t\t<td style=\"padding: 0px 10px; text-align: right;\">&nbsp;</td>\n"
					.	"\t\t<td style=\"padding: 0px 10px; text-align: right;\">" . $this->money($transaction->difference) . "</td>\n"
					.	"\t\t<td style=\"padding: 0px 10px;\">&nbsp;</td>\n"
					.	"\t\t<td style=\"padding: 0px 10px;\">&nbsp;</td>\n"
					.	"</tr>\n";
			}
		}
		echo "</table>\n";
		break;
		
	default:
		echo json_encode($this->main_data);
	}
}

function receive($form) {
	switch ($form) {
		case "alloc_form":
			$result = $this->till->allocateDifference($this->user['id'], $_POST['trader'], $this->parse($_POST['allocation']), $_POST['alloc_note']);
			echo json_encode($result);
			break;
		case "deposit_form":
			$result = $this->till->deposit($this->user['id'], $_POST['trader'], $this->parse($_POST['deposit']), $_POST['deposit_note']);
			echo json_encode($result);
			break;
		case "withdrawal_form":
			$result = $this->till->withdraw($this->user['id'], $_POST['trader'], $this->parse($_POST['withdrawal']), $_POST['withdrawal_note']);
			echo json_encode($result);
			break;
		case "count_form":
			$result = $this->till->recount($this->user['id'], $this->parse($_POST['total_count']));
			echo json_encode($result);
			break;
		default:
//			echo json_encode($this->main_data);
	}
}

function amend($data = "") {
	switch ($data) {
		case "clear":
			$result = $this->till->clear($this->user['id']);
			echo json_encode($result);
			break;
		default:
//			echo $this->say('test');
			break;
	}
}

}
?>