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
	$docu = @$_GET['docu'];
?>
Ext.Loader.setConfig({
	enabled: true
});
Ext.Loader.setPath('Ext.ux', '<?php echo $this->http_host . "/" . $this->ext_library . "/examples/ux";?>');

Ext.require([
 	'Ext.data.*',
 	'Ext.form.field.*',
    'Ext.layout.container.Border',
 	'Ext.grid.*'
]);

Ext.onReady(function() {

    Ext.tip.QuickTipManager.init();
    
	Ext.Loader.setConfig({enabled:true});
	
<?php 
	include_once("_menu.php");
?>

	Ext.define('Payment', {
		extend: 'Ext.data.Model',
		idProperty: 'id',
		fields: [
			{name: 'trader0_amount', type: 'float'},
			{name: 'trader0_amount_rendered'},
			{name: 'trader0_cost', type: 'float'},
			{name: 'trader0_cost_rendered'},
<?php 
	$traders = $this->mysqli->arrayData(array(
		'source' => "{$tp}traders"
	))->data;
	foreach($traders as $b) {
		echo "\t\t\t{name: 'trader{$b->id}_amount', type: 'float'},\n";
		echo "\t\t\t{name: 'trader{$b->id}_amount_rendered'},\n";
		echo "\t\t\t{name: 'trader{$b->id}_cost', type: 'float'},\n";
		echo "\t\t\t{name: 'trader{$b->id}_cost_rendered'},\n";
	}
?>
			{name: 'id', type: 'int'},
			{name: 'payment_method_id', type: 'int'},
			{name: 'name'},
			{name: 'sale'},
			{name: 'html'},
			{name: 'timestamp', type: 'date', dateFormat: 'Y-m-d H:i:s'},
			{name: 'timestamp_rendered'},
			{name: 'amount', type: 'float'},
			{name: 'amount_rendered'},
			{name: 'cost', type: 'float'},
			{name: 'cost_rendered'},
			{name: 'note'},
			{name: 'registerer'}
		]
	});


	function saveChanges(editor, edit, eOpts) {
		Ext.Ajax.request({
			waitMsg: '<?php echo $this->say('hang on', array());?>',
			url: 'index.php?docu=payments_list&mission=amend',
			params: {
				payment: edit.record.data.id,
				sale: edit.record.data.sale,
				new_value: edit.value,
				original_value: edit.originalValue
			},
			callback: function(options, success, response){
					if(!response.responseText) {
						Ext.MessageBox.alert('<?php echo $this->say('unable to save due to unknown error', array());?>');
						return false;
					}
					var result = Ext.JSON.decode(response.responseText);
					
					if(result['success'] == true) {
						load();
					}
					else {
						Ext.MessageBox.alert('<?php echo $this->say('warning', array());?>', result['msg']);
					}
				}
			}
		);
	};


	var load = function() {
		store.getProxy().setExtraParam('from', Ext.Date.format(from.getValue(), 'Y-m-d'));
		store.getProxy().setExtraParam('to', Ext.Date.format(to.getValue(), 'Y-m-d'));
		store.currentPage = 1;
		store.load();
	}

	var from = Ext.create('Ext.form.field.Date', {
		format: '<?php echo $this->shortdate_format(true);?>',
		startDay: <?php echo $this->firstDayOfWeek-1;?>,
		submitFormat: 'Y-m-d',
		fieldLabel: '<?php echo $this->say('from date');?>',
		name: 'from_date',
		maxValue: new Date(),
		value: new Date(<?php echo $this->lastSalesdate()->format('Y, m-1, d');?>),
		enableKeyEvents: true,
		listeners: {
			keypress: function(field, event, opts) {
				var key = event.getKey();
				if(key == 13) {
					load();
				}
			}
		}
	});
	
	var to = Ext.create('Ext.form.field.Date', {
		format: '<?php echo $this->shortdate_format(true);?>',
		startDay: <?php echo $this->firstDayOfWeek-1;?>,
		submitFormat: 'Y-m-d',
		fieldLabel: '<?php echo $this->say('to date');?>',
		name: 'to_date',
		value: new Date(),
		enableKeyEvents: true,
		listeners: {
			keypress: function(field, event, opts) {
				var key = event.getKey();
				if(key == 13) {
					load();
				}
			}
		}
	});

	var paymentOptions = Ext.create('Ext.data.Store', {
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: "index.php?docu=<?php echo $docu;?>&mission=request&data=payment_options",
			reader: {
				type: 'json',
				root: 'data',
				totalProperty: 'totalRows'
			}
		},
		fields: [{name: 'id'},{name: 'name'}]
	});

	var store = Ext.create('Ext.data.Store', {
		model: 'Payment',
		pageSize: 200,
		remoteSort: true,
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: "index.php?docu=payments_list&mission=request&data=payments",
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
			property: 'timestamp',
			direction: 'DESC'
		}],
        groupField: 'name',
		autoLoad: true
	});

	store.on({
		'load': function() {
			summary.collapseAll(1);
		}
	});

    var cellEditing = Ext.create('Ext.grid.plugin.CellEditing', {
        clicksToEdit: 1
    });

	var columns = [
		{
			dataIndex: 'id',
			text: '<?php echo $this->say('payment id');?>',
			width: 40,
			hidden: true,
			align: 'right',
			sortable: true
		},
		{
			dataIndex: 'timestamp',
			text: '<?php echo $this->say('payment time');?>',
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return record.get('timestamp_rendered');
			},
			width: 120,
			sortable: true
		},
		{
			dataIndex: 'sale',
			text: '<?php echo $this->say('payment sale');?>',
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return '<a href="index.php?docu=sale_record&id=' + value + '">' + value + '</a>';
			},
			width: 50
		},
		{
			dataIndex: 'note',
			text: '<?php echo $this->say('payment note');?>',
			summaryType: 'count',
			summaryRenderer: function(value, summaryData, dataIndex) {
				return value + (value == 1 ? ' <?php echo $this->say('payment');?>' : ' <?php echo $this->say('payments');?></b>');
			},
			width: 200
		},
		{
			dataIndex: 'registerer',
			text: '<?php echo $this->say('payment recorder');?>',
			width: 120
		},
		{
			dataIndex: 'payment_method_id',
			text: '<?php echo $this->say('payment method');?>',
			editor: Ext.create('Ext.form.field.ComboBox', {
				allowBlank: false,
				typeAhead: false,
				forceSelection: true,
				triggerAction: 'all',
				width: 120,
				matchFieldWidth: false,
				listConfig: {
					width: 200
				},
				selectOnFocus: true,
				store: paymentOptions,
				valueField: 'id',
				displayField: 'name'
			}),
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return record.get('name');
			},
			width: 120
		},
<?php 
	if(
	$this->preferences->vouchers_use_shared_prepayment_deposit 
	or $this->checkIfSharedDepositIsActive()
	) {
?>
		{
			header: '<?php echo $this->say('prepayment deposit');?>',
			columns: [{
				dataIndex: 'trader0_amount',
				text: '<?php echo $this->say('payment amount');?>',
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return record.get('trader0_amount_rendered');
				},
				summaryType: 'sum',
				summaryRenderer: function(value, summaryData, dataIndex) {
					return value.moneyFormat('<?php echo addslashes($this->money_pattern());?>', '<?php echo addslashes($this->say['decimal_separator']);?>', '<?php echo addslashes($this->groupingSymbol());?>', '<?php echo addslashes($this->getCurrencySymbol());?>');
				},
				align: 'right',
				width: 70
			},
			{
				dataIndex: 'trader0_cost',
				text: '<?php echo $this->say('payment cost');?>',
				width: 70,
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return record.get('trader0_cost_rendered');
				},
				summaryType: 'sum',
				summaryRenderer: function(value, summaryData, dataIndex) {
					return value.moneyFormat('<?php echo addslashes($this->money_pattern());?>', '<?php echo addslashes($this->say['decimal_separator']);?>', '<?php echo addslashes($this->groupingSymbol());?>', '<?php echo addslashes($this->getCurrencySymbol());?>');
				},
				align: 'right',
				sortable: false
			}]
		},
<?php 
	}
	foreach($traders as $b) {
?>
		{
			header: '<?php echo $b->name;?>',
			columns: [{
				dataIndex: 'trader<?php echo $b->id;?>_amount',
				text: '<?php echo $this->say('payment amount');?>',
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return record.get('trader<?php echo $b->id;?>_amount_rendered');
				},
				summaryType: 'sum',
				summaryRenderer: function(value, summaryData, dataIndex) {
					return value.moneyFormat('<?php echo addslashes($this->money_pattern());?>', '<?php echo addslashes($this->say['decimal_separator']);?>', '<?php echo addslashes($this->groupingSymbol());?>', '<?php echo addslashes($this->getCurrencySymbol());?>');
				},
				align: 'right',
				width: 70
			},
			{
				dataIndex: 'trader<?php echo $b->id;?>_cost',
				text: '<?php echo $this->say('payment cost');?>',
				width: 70,
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return record.get('trader<?php echo $b->id;?>_cost_rendered');
				},
				summaryType: 'sum',
				summaryRenderer: function(value, summaryData, dataIndex) {
					return value.moneyFormat('<?php echo addslashes($this->money_pattern());?>', '<?php echo addslashes($this->say['decimal_separator']);?>', '<?php echo addslashes($this->groupingSymbol());?>', '<?php echo addslashes($this->getCurrencySymbol());?>');
				},
				align: 'right',
				sortable: false
			}]
		},
<?php 
	}
?>
		{
			header: '<?php echo $this->say('total all traders');?>',
			columns: [{
				dataIndex: 'amount',
				text: '<?php echo $this->say('payment amount');?>',
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return record.get('amount_rendered');
				},
				summaryType: 'sum',
	//			summaryType: function(records) {},
				summaryRenderer: function(value, summaryData, dataIndex) {
					pattern = "<?php echo $this->money_pattern();?>";
					symbol = "<?php echo $this->getCurrencySymbol();?>";
					return '<b>' + value.moneyFormat('<?php echo addslashes($this->money_pattern());?>', '<?php echo addslashes($this->say['decimal_separator']);?>', '<?php echo addslashes($this->groupingSymbol());?>', '<?php echo addslashes($this->getCurrencySymbol());?>') + '</b>';
				},
				align: 'right',
				width: 70
			},
			{
				dataIndex: 'cost',
				text: '<?php echo $this->say('payment cost');?>',
				width: 70,
				renderer: function(value, metadata, record, rowIndex, colIndex, store) {
					return record.get('cost_rendered');
				},
				summaryType: 'sum',
				summaryRenderer: function(value, summaryData, dataIndex) {
					return '<b>' + value.moneyFormat('<?php echo addslashes($this->money_pattern());?>', '<?php echo addslashes($this->say['decimal_separator']);?>', '<?php echo addslashes($this->groupingSymbol());?>', '<?php echo addslashes($this->getCurrencySymbol());?>') + '</b>';
				},
				align: 'right',
				sortable: false
			}]
		}
	];
	
	var summary = Ext.create('Ext.grid.feature.GroupingSummary', {
		id: 'group',
		groupHeaderTpl: '{name}',
		hideGroupedHeader: false,
		enableGroupingMenu: false,
		startCollapsed: true
	});

    var showSummary = true;


	var mainPanel = Ext.create('Ext.grid.Panel', {
		layout: 'border',
        frame: true,
		store: store,
		title: '',
		iconCls: 'icon-grid',
		columns: columns,
		renderTo: 'panel',
        plugins: [cellEditing],
        features: [summary],
		height: 500,
		autoScroll: true,
		autoWidth: true,
		tbar: [from, to],
		buttons: [{
			menu: Ext.create('Ext.menu.Menu', {
				defaultAlign: 'bl-tl',
				id: 'print',
				items: [
					{
						text: '<?php echo addslashes($this->say('report payments daily breakdown'));?>',
						handler: function(){
							window.open("index.php?docu=payment_fees_report&summary=1&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d') + "&daily=1");
						}
				}, {
					text: '<?php echo addslashes($this->say('report payments summary'));?>',
					handler: function(){
						window.open("index.php?docu=payment_fees_report&summary=1&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d'));
					}
				}
			]
		}),
			text: '<?php echo $this->say('print');?>'
		}]
	});
	cellEditing.on('edit', saveChanges);

});
<?php 
}


function design() {
?>
<div id="panel"></div>
<?php 
}

function request($data = "") {
	$tp = $this->mysqli->table_prefix;

	switch ($data) {

	case "payment_options": {
		$result = $this->mysqli->arrayData(array(
			'source' => "{$tp}payment_methods",
			'where' => "enabled",
			'orderfields' => "{$tp}payment_methods.sortorder"
		));
		return json_encode($result);
		break;
	}
	
	case "payments": {

		$query['class'] = "Payment";
		
		$query['source'] = "{$tp}payments INNER JOIN {$tp}sales ON {$tp}payments.sale = {$tp}sales.id LEFT JOIN {$tp}payment_methods ON {$tp}payments.paymentMethod = {$tp}payment_methods.id";
		$query['where'] = "1";
		$query['where'] .= ($this->from ? (" AND DATE(timestamp) >= '" . $this->from->format('Y-m-d H:i:s') . "'") : "");
		$query['where'] .= ($this->to ? (" AND DATE(timestamp) <= '" . $this->to->format('Y-m-d H:i:s') . "'") : "");
		$query['fields'] = "{$tp}payments.id";
		
		if($_GET['sort'] == 'id') {
			$query['orderfields'] = "{$tp}payment_methods.sortorder ASC, {$tp}payments.id" . ($_GET['dir'] == "DESC" ? " DESC" : " ASC");
		}
		else if($_GET['sort'] == 'name') {
			$query['orderfields'] = "{$tp}payment_methods.sortorder" . ($_GET['dir'] == "DESC" ? " DESC" : " ASC");
		}
		else if($_GET['sort']) {
			$query['orderfields'] = "{$tp}payment_methods.sortorder ASC, {$_GET['sort']}" . ($_GET['dir'] == "DESC" ? " DESC" : " ASC") . ", {$tp}payments.timestamp DESC";
		}
		else {
			$query['orderfields'] = "{$tp}payment_methods.sortorder ASC, {$tp}payments.timestamp DESC";
		}
		
		$result = new stdclass;
		$payments = $this->mysqli->arrayData($query);
		$total_amount = array();
		$total_costs = array();
		
		foreach($payments->data as $payment) {
			settype( $total_amount[	$payment->id ], 'array' );
			settype( $total_costs[	$payment->id ], 'array' );
		
			$data = (object)array(
				'id'					=>	$payment->id,
				'timestamp'				=>	($payment->timestamp ? $payment->timestamp->format('Y-m-d H:i:s') : null),
				'timestamp_rendered'	=>	$this->short_datetime($payment->timestamp),
				'amount'				=>	$payment->amount,
				'amount_rendered'		=>	$this->money($payment->amount),
				'sale'					=>	$payment->getSale()->data->id,
				'note'					=>	$payment->note,
				'registerer'			=>	$payment->registerer,
				'payment_method_id'		=>	$payment->paymentMethod->id,
				'name'					=>	$payment->paymentMethod->name,
				'sortorder'				=>	$payment->paymentMethod->sortorder,
				'transactionChargeFixed'=>	$payment->paymentMethod->transactionChargeFixed,
				'transactionChargeRate'	=>	$payment->paymentMethod->transactionChargeRate
			);
			
			$cost = $payment->getCost();
			if(!$cost->success) {
				return $cost;
			}
			$data->cost = $cost->data;
			$data->cost_rendered = $this->money($cost->data);
			
			$shares = $payment->getShares();
			if(!$shares->success) return $shares;
			
			foreach($shares->shares as $share) {
				$trader	= $share->trader;
				$traders_share = $trader->getShareOfPayment($payment);
				if(!$traders_share->success) return $traders_share;

				settype( $total_amount[	$payment->id ][$trader->id], 'string' );
				settype( $total_costs[	$payment->id ][$trader->id], 'string' );

				$share_of_payment	= $traders_share->amount;
				$share_of_cost		= $traders_share->cost;
				
				$total_amount[$payment->id][$trader->id] = bcadd(
					$total_amount[$payment->id][$trader->id],
					$share_of_payment,
					6
				);
				$total_costs[$payment->id][$trader->id] = bcadd(
					$total_costs[$payment->id][$trader->id],
					$share_of_cost,
					6
				);
				$data->{"trader{$trader->id}_amount"} = $share_of_payment;
				$data->{"trader{$trader->id}_amount_rendered"} = $this->money($share_of_payment);
				$data->{"trader{$trader->id}_cost"} = $share_of_cost;
				$data->{"trader{$trader->id}_cost_rendered"} = $this->money($share_of_cost);
			}
			$result->data[] = $data;
		}
		$result->total_amount = $total_amount;
		$result->total_costs = $total_costs;
		$result->success = true;
		return json_encode($result);
		break;
	}
		
	default: {
		echo json_encode($this->main_data);
	}
	}
}

function amend($data = "") {
	$tp = $this->mysqli->table_prefix;
	$dec = new NumberFormatter($this->say['locale'], NumberFormatter::DECIMAL);

	switch ($data) {

	default: {
		$paymentId = (int)$_POST['payment'];

		// check if the original payment method affects the till
		if((int)$_POST['original_value'] == $this->till->id) {
			$payment = $this->mysqli->arrayData(array(
				'source' => "{$tp}payments",
				'where' => "id = '$paymentId'"
			))->data[0];
			$sale = new Sale(array(
				'id' => $payment->sale
			));
			foreach($sale->getInvoices()->data as $invoice) {
				$portions[$invoice->getTrader()->data->id]['trader'] = $invoice->getTrader()->data->id;
				if($sale->getTotal()->data){
					$portions[$invoice->getTrader()->data->id]['sum'] = $this->roundSum(-$payment->amount * $invoice->total/$sale->getTotal()->data);
				}
				else {
					$portions[$invoice->getTrader()->data->id]['sum'] = 0;
				}
			}
			$result = $this->till->sale($this->user['id'], $sale->id, $portions, "", "correction");
		}
		
		echo json_encode($this->mysqli->saveToDb(array(
			'returnQuery' => true,
			'update' =>  true,
			'where' => "id = '$paymentId'",
			'table' => "{$tp}payments",
			'fields' => array(
				'paymentMethod' => $_POST['new_value']
			)
		)));

		// check if the new payment method affects the till
		if((int)$_POST['new_value'] == $this->till->id) {
			$payment = $this->mysqli->arrayData(array(
				'source' => "{$tp}payments",
				'where' => "id = '$paymentId'"
			))->data[0];
			$sale = new Sale(array(
				'id' => $payment->sale
			));
			foreach($sale->getInvoices()->data as $invoice) {
				$portions[$invoice->getTrader()->data->id]['trader'] = $invoice->getTrader()->data->id;
				if($sale->getTotal()->data){
					$portions[$invoice->getTrader()->data->id]['sum'] = $this->roundSum($payment->amount * $invoice->total/$sale->getTotal()->data);
				}
				else {
					$portions[$invoice->getTrader()->data->id]['sum'] = 0;
				}
			}
			$result = $this->till->sale($this->user['id'], $sale->id, $portions, "", "correction");
		}
		
		break;
	}
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