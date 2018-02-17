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
	$this->current_sale = new Sale(array(
		'id' => @$_GET['sale'])
	);
	if($this->current_sale->checkIfCompleted()->data) {
		header("Location: http://pos.maya-b.com/pos/index.php?docu=sale_record&id={$this->current_sale->id}");
	}
}

function script() {
	$tp = $this->mysqli->table_prefix;
?>
Ext.Loader.setConfig({
	enabled: true
});
Ext.Loader.setPath('Ext.ux', '<?=$this->http_host . "/" . $this->ext_library . "/examples/ux"?>');

// Ext.require([
// 	'Ext.data.*',
// 	'Ext.util.*',
// 	'Ext.ModelManager',
// 	'Ext.draw.*',
// 	'Ext.Ajax',
// 	'Ext.direct.*',
// 	'Ext.XTemplate',
// 	'Ext.ModelManager',
// 	'Ext.dd.*',
// 	'Ext.form.*',
// 	'Ext.grid.*',
// 	'Ext.container.*',
// 	'Ext.layout.*',
// 	'Ext.selection.*'
// ]);
// 
Ext.onReady(function(){

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
	
	Ext.define('ItemModel', {
		extend: 'Ext.data.Model',
		fields: [
			{name: 'id',		type: 'float'},
			{name: 'product'},
			{name: 'productId',	type: 'float'},
			{name: 'quantity',	type: 'float'},
			{name: 'quantity_formatted'},
			{name: 'description'},
			{name: 'pricePer',	type: 'float'},
			{name: 'price_per_formatted'},
			{name: 'price',		type: 'float'},
			{name: 'price_formatted'},
			{name: 'discount',	type: 'float'},
			{name: 'discount_formatted'}
		]
	});
	
	Ext.define('ItemInstancesModel', {
		extend: 'Ext.data.Model',
		fields: [
			{name: 'id',			type: 'float'},
			{name: 'date',			type: 'date', dateFormat: 'Y-m-d'},
			{name: 'invoiceId',	type: 'float'},
			{name: 'quantity',		type: 'float'},
			{name: 'quantity_formatted'},
			{name: 'pricePer',		type: 'float'},
			{name: 'price_per_formatted'},
			{name: 'discount',		type: 'float'},
			{name: 'discount_formatted'},
			{name: 'price',			type: 'float'},
			{name: 'price_formatted'},
			{name: 'returned_id',	type: 'float'},
			{name: 'returned_invoice_id',	type: 'float'},
			{name: 'returned_quantity',	type: 'float'},
			{name: 'returned_quantity_formatted'}
		]
	});


	cancelSale = function() {
		Ext.Ajax.request({
			waitMsg: '<?=$this->say('hang on')?>',
			url: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=cancel",
			 success :function(response, opts){
				var result = Ext.JSON.decode(response.responseText);
				if(result['success'] == true) {
					window.location = "index.php";
				}
				else {
					Ext.MessageBox.alert('<?=$this->say('whoops')?>',result['msg']);
				}
			 }
		});
	}

	confirmCancel = function() {
		Ext.MessageBox.show({
			title: '<?=$this->say('are you sure')?>',
			msg: '<?=$this->say('do you really want to delete the entire sale')?>',
			buttons: Ext.MessageBox.OKCANCEL,
			fn: function(buttonId, text, opt){
				if(buttonId == 'ok') {
					cancelSale();
				}
			},
			animEl: 'elId',
			icon: Ext.MessageBox.QUESTION
		});
	}

	removeDiscount = function(discount){
		Ext.Ajax.request({
			waitMsg: '<?=$this->say('hang on')?>',
			url: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=removediscount",
			params: {
				discount: discount
			},
			success: function(response, options){
				var result = Ext.JSON.decode(response.responseText);
				if(result['success'] == true) {
					discounts.load();
					updateTotals();
				}
				else {
					Ext.MessageBox.alert('<?=$this->say('whoops')?>',result['msg']);
				}
			}
		});
	}

	removeItem = function(productId){
 		Ext.Ajax.request({
			waitMsg: '<?=$this->say('hang on')?>',
			url: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=removeproduct",
			params: {
				product: productId
			},
			success: function(response, options){
				var result = Ext.JSON.decode(response.responseText);
				if(result['success'] == true) {
					itemset.load({
						callback: updateTotals()
					});
					updateTotals();
				}
				else {
					Ext.MessageBox.alert('<?=$this->say('whoops')?>',result['msg']);
				}
			}
		});
	}

	returnItem = function(instance){
		itemInstancesWindow.hide();
		Ext.Ajax.request({
			waitMsg: '<?=$this->say('hang on')?>',
			url: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=return_item",
			params: {
				item:		instance,
				quantity:	quantField.getValue(),
				product:	productSearchField.getValue()
			},
			success: function(response, options){
				var result = Ext.JSON.decode(response.responseText);
				if(result['success'] == true) {
					itemset.load({
						callback: updateTotals()
					});
					quantField.reset();
					var a = Ext.JSON.decode(response.responseText);
					if(a.sale && a.sale != <?=$this->current_sale->id?>) {
						window.location = "index.php?docu=sales&sale=" + a.sale + (productQuery ? "&product_query=" + productQuery : "");
					}
				}
				else {
					Ext.MessageBox.alert('<?=$this->say('whoops')?>',result['msg']);
				}
			}
		});
	}

	function saveChanges(editor, edit, eOpts) {
		Ext.Ajax.request({
			waitMsg: '<?=$this->say('hang on')?>',
			url: 'index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=edit_item',
			params: {
				id: edit.record.data.id,
				field: edit.field,
				value: ((edit.field == 'discount') ? (edit.value/100) : edit.value),
				original_value: ((edit.field == 'discount') ? (edit.originalValue/100) : edit.originalValue)
			},
			failure:function(response,options){
				Ext.MessageBox.alert('<?=$this->say('unable to save due to unknown error')?>');
			},
			success:function(response,options){
					var result = Ext.JSON.decode(response.responseText);
					
					if(result['success'] == true) {
						edit.record.set('quantity',result.data.quantity);
						edit.record.set('quantity_formatted',result.data.quantity_formatted);
						edit.record.set('pricePer',result.data.pricePer);
						edit.record.set('price_per_formatted',result.data.price_per_formatted);
						edit.record.set('discount',result.data.discount);
						edit.record.set('discount_formatted',result.data.discount_formatted);
						edit.record.set('price',result.data.price);
						edit.record.set('price_formatted',result.data.price_formatted);
 						itemset.commitChanges();
 						if(result.msg) {
 							Ext.MessageBox.alert('<?=$this->say('notice')?>', result.msg);
 						}
						updateTotals();
					}
					else {
						Ext.MessageBox.alert('<?=$this->say('warning')?>',result['msg']);
					}
				}
			}
		);
	};

	showCompletMsg = function(msg){
		var print = Ext.create('Ext.form.field.Checkbox', {
			fieldLabel: '<?=$this->say('print receipt')?>',
			hideLabel: true,
			boxLabel: '<?=$this->say('print receipt')?>',
			name: 'print',
			checked: false
		});
	
		completeForm = Ext.create('Ext.form.Panel', {
			bodyStyle: 'padding: 5px',
			border: false,
			defaults: {border: false},
			labelWidth: 200,
			items: [{html: msg}, print],
			buttons: [
			{
				handler: function() {
					completeWindow.hide();
				},
				text: '<?=$this->say('cancel')?>',
			}, {
				handler: function(Button, EventObject) {
					showRefundPanel();
				},
				text: '<?=addslashes($this->say('make refund'))?>'
			}, 
			{
				handler: function() {
					completeForm.submit({
						url: 'index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=complete',
						waitMsg:'<?=$this->say('hang on')?>'
					});
				},
				text: '<?=$this->say('complete sale')?>'
			}]
		});
		completeForm.on({
			actioncomplete: function(form, action){
				if(action.type == 'submit'){
					if(action.response.responseText == '') {
						Ext.MessageBox.alert('Problem', '<?=$this->say('missing confirmation from server')?>');
					} else {
						var result = Ext.JSON.decode(action.response.responseText);
						var index;
						for (index = 0; index < result.windows.length; ++index) {
							window.open(result.windows[index]);
						}
						if(print.getValue()) {
							window.open("index.php?docu=sale_record&id=<?=$this->current_sale->id?>&mission=task&task=print");
						}
						window.location = "index.php";
					}
				}
			},
								
			actionfailed: function(form,action){
				if(action.type == 'submit') {
					if (action.failureType == "connect") {
						Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('lost connection to server')?>');
					}
					else if(action.response) {	
						var result = Ext.JSON.decode(action.response.responseText); 
						if(result && result.msg) {			
							Ext.MessageBox.alert('<?=$this->say('received following message from DB server')?>:', result.msg);
						}
						else {
							Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('operation failed due to unknown error')?>: <?=$this->say('action type')?>=' + action.type + ', <?=$this->say('failure type')?>=' + action.failureType);
						}
					}
				}
				
			} // end actionfailed listener
		}); // end skjema.on
	
		var completeWindow = Ext.create('Ext.window.Window', {
			title: '<?=$this->say('complete sale')?>',
			modal: true,
			width: 440,
			listeners: {
				close: function() {
					productSearchField.focus();
				}
			},
			height: 200,
			closeAction: 'hide',
			items: [completeForm]
		});
		completeWindow.show();
	}

	showDiscountpanel = function() {
		var trader = {
			dataIndex: 'trader',
			header: '<?=$this->say('trader')?>',
			sortable: false,
			width: 40
		};
	
		var discount_name = {
			dataIndex: 'description',
			header: '<?=$this->say('description')?>',
			sortable: false,
			width: 200
		};

		var discount_value = {
			align: 'right',
			dataIndex: 'value',
			header: '<?=$this->say('discount value')?>',
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return record.get('value_formatted');
			},
			sortable: false,
			width: 60
		};
	
		var discountRate = {
			align: 'right',
			dataIndex: 'discountRate',
			header: '<?=$this->say('discount rate')?>',
			renderer: function(value, metadata, record, rowIndex, colIndex, store) {
				return record.get('discount_rate_formatted');
			},
			sortable: false,
			width: 60
		};
	
		var remove_discount = {
			dataIndex: 'id',
			renderer: function(value, metaData, record, rowIndex, colIndex, store){
				return "<a style=\"cursor: pointer\" title=\"<?=$this->say('remove discount')?>\" onclick=\"removeDiscount(" + record.data.id + ")\"><img src=\"../images/delete.png\" /></a>";
			},
			sortable: false,
			width: 30
		};
	
		var discountGrid = Ext.create('Ext.grid.Panel', {
			autoScroll: true,
			columns: [
				trader,
				discount_name,
				discount_value,
				discountRate,
				remove_discount
			],
			height: 100,
			store: discounts,
			stripeRows: true
		});
	
		var discountValue = Ext.create('Ext.form.field.Number', {
			allowBlank: true,
			allowDecimals: true,
			allowNegative: false,
			decimalPrecision: 2,
			decimalSeparator: '<?=$this->say('decimal_separator')?>',
			fieldLabel: '<?=$this->say('discount value')?>',
			hideTrigger: true,
			labelWidth: 150,
			name: 'discount_value',
			selectOnFocus: true,
			width: 300
		});
	
		var discountRate = Ext.create('Ext.form.field.Number', {
			allowBlank: true,
			allowDecimals: true,
			allowNegative: false,
			decimalPrecision: 2,
			decimalSeparator: '<?=$this->say('decimal_separator')?>',
			fieldLabel: '<?=$this->say('discount rate')?>',
			hideTrigger: true,
			labelWidth: 150,
			maxValue: 100,
			name: 'discountRate',
			selectOnFocus: true,
			width: 300
		});
	
		var discountDescription = Ext.create('Ext.form.field.Text', {
			allowBlank: true,
			fieldLabel: '<?=$this->say('description')?>',
			labelWidth: 150,
			name: 'discount_description',
			selectOnFocus: true,
			width: 300
		});
		
		var discountCode = Ext.create('Ext.form.field.Text', {
			allowBlank: true,
			fieldLabel: '<?=$this->say('discount code')?>',
			labelWidth: 150,
			name: 'discount_code',
			selectOnFocus: true,
			width: 300
		});
	<?
	$traders = $this->activeTraders();
	$a = array();
	foreach($traders as $trader){
		$a[] = "{boxLabel: '{$trader->name}', name: 'trader_{$trader->id}'}";
	}
	?>
		var discountTraders = Ext.create('Ext.form.CheckboxGroup', {
			allowBlank: false,
			id: 'discountTraders',
			fieldLabel: '<?=$this->say('traders')?>',
			defaults: {inputValue: 1, checked: false},
			columns: 2,
			items: [
				<?=implode(", ", $a)?>
			]
		});
		
		var discountForm = Ext.create('Ext.form.Panel', {
			bodyStyle: 'padding: 15px',
			title: '<?=$this->say('add new overall discount')?>',
			items:[discountValue, discountRate, discountDescription, discountCode, discountTraders],
			buttons: [{
				handler: function(button, event) {
					discountsWindow.close();
				},
				text: '<?=$this->say('cancel')?>'
			}, {
				handler: function(){
					discountForm.submit({
						url:'index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=add_discount',
						waitMsg:'<?=$this->say('hang on')?>'
						});
				},
				text: '<?=$this->say('submit discount')?>'
			}]
		});
	
		discountForm.on({
			actioncomplete: function(form, action){
				if(action.type == 'submit'){
					if(action.response.responseText == '') {
						Ext.MessageBox.alert('Problem', '<?=$this->say('missing confirmation from server')?>');
					} else {
						discounts.load();
						updateTotals();
						discountsWindow.close();
					}
				}
			},
			actionfailed: function(form, action){
				if(action.type == 'submit') {
					if (action.failureType == "connect") {
						Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('lost connection to server')?>');
					}
					else if(action.response) {	
						var result = Ext.JSON.decode(action.response.responseText);
						if(result && result.msg) {			
							Ext.MessageBox.alert('<?=$this->say('received following message from DB server')?>:', result.msg);
						}
						else {
							Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('operation failed due to unknown error')?>: <?=$this->say('action type')?>=' + action.type + ', <?=$this->say('failure type')?>=' + action.failureType);
						}
					}
				}
				
			} // end actionfailed listener
		}); // end skjema.on
	
	var discountsWindow = Ext.create('Ext.window.Window', {
			title: '<?=$this->say('discounts overall')?>',
			width: 600,
			listeners: {
				show: function() {
					discountValue.focus(true, 500);
				},
				close: function() {
					productSearchField.focus();
				}
			},
			modal: true,
			items: [discountGrid, discountForm]
		});
	
		discountsWindow.show();
	}

	showItemInstances = function(product_id){
		itemInstances.load({
			params: {product: product_id},
			callback: function(r, options, success) {
			
				itemInstancesWindow = Ext.create('Ext.window.Window', {
					width: 440,
					height: 300,
					closeAction: 'hide',
					modal: true,
					items: [itemInstancesGrid]
				});
				
				itemInstancesWindow.show();
			}
		});
	}

	showPaymentPanel = function(method){
		paymentOptions.load({
			params: {method: method},
			callback: function(r, options, success) {
				var paymentMethod = Ext.create('Ext.form.field.ComboBox', {
					name: 'paymentMethod',
					queryMode: 'local',
					store: paymentOptions,
					valueField: 'id',
					displayField: 'name',
					hiddenName: 'paymentMethod',
					fieldLabel: '<?=$this->say('choose payment option')?>',
					allowBlank: false,
					editable: false,
					forceSelection: true,
					listWidth: 200,
					listeners: {
						select: function(combo, records, eOpts) {
							if(records[0].data.config) {
								for (var a = 0; a < paymentOptions.data.length; a++) {
									params = r[a].get('config').paymentParams;
									if(params instanceof Array) {
										for (var b = 0; b < params.length; b++) {
											if(params[b].extField) {
												parameter[params[b].name].disable();
											}
										}
									}
								}
								params = records[0].data.config.paymentParams;
								for (var b = 0; b < params.length; b++) {
									if(params[b].extField) {
										parameter[params[b].name].enable();
									}
								}
								if(records[0].data.config.calculatedValue) {
									amountPaid.disable();
								}
							}
						}
					},
					maxHeight: 200,
					typeAhead: false,
					valueField: 'id',
					width: 400
				});

				var amountPaid = Ext.create('Ext.form.field.Number', {
					allowBlank: false,
					tabIndex: 0,
					hideTrigger: true,
					selectOnFocus: true,
					fieldLabel: '<?=$this->say('amount paid')?>',
					name: 'amount',
					width: 400,
					value: '0'
				});

				var parameter = new Object();
				
//				var paymentValidator = function(param1, param2) {
//					alert(param1 + ' - ' + param2);
//				}
				
				if(paymentOptions.getTotalCount() < 2) {
					paymentMethod.setValue(r[0].get('id'));
					paymentMethod.hide();
					if(r[0].data.config.calculatedValue) {
						amountPaid.disable();
					}
				}

				for (var a = 0; a < paymentOptions.data.length; a++) {
					params = paymentOptions.getAt(a).get('config').paymentParams;
					if(params instanceof Array) {
						for (var b = 0; b < params.length; b++) {
// 							if(params[b].validator) {
//  								params[b].extField.validator = Ext.Function.bind(paymentValidator, this, [params[b].validator], true);
//  							}
							if(params[b].extField) {
								parameter[params[b].name] = Ext.create(params[b].extField);
								if(paymentOptions.getTotalCount() > 1) {
									parameter[params[b].name].disable();
								}
							}
						}
					}
				}

				paymentForm = Ext.create('Ext.form.Panel', {
					autoScroll: true,
					bodyStyle: 'padding: 5px',
					border: false,
					labelWidth: 200,
					defaults: {
						selectOnFocus: true,
						width: 400
					},
					items: [amountPaid, paymentMethod],
					buttons: [{
						handler: function() {
							paymentWindow.close();
						},
						text: '<?=$this->say('cancel')?>'
					}, {
						handler: function() {
							var params = {};
							
							for (var a in parameter) {
								if (parameter.hasOwnProperty(a)) {
									params[a] = parameter[a].getValue();
								}
							}
			
							paymentForm.submit({
								params: {
									params: Ext.JSON.encode(params)
								},
								url: 'index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=addpayment',
								waitMsg:'<?=$this->say('hang on')?>'
							});
						},
						text: '<?=$this->say('submit')?>'
					}]
				});

				for (var a in parameter) {
					if (parameter.hasOwnProperty(a)) {
						paymentForm.add(parameter[a]);
					}
				}

				paymentForm.on({
					actioncomplete: function(form, action){
						if(action.type == 'submit'){
							if(action.response.responseText == '') {
								Ext.MessageBox.alert('Problem', '<?=$this->say('missing confirmation from server')?>');
							} else {
								updateTotals();
								paymentWindow.close();
							}
						}
					},
										
					actionfailed: function(form,action){
						if(action.type == 'submit') {
							if (action.failureType == "connect") {
								Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('lost connection to server')?>');
							}
							else if(action.response) {	
								var result = Ext.JSON.decode(action.response.responseText); 
								if(result && result.msg) {			
									Ext.MessageBox.alert('<?=$this->say('received following message from DB server')?>:', result.msg);
								}
								else if(!result.errors){
									Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('operation failed due to unknown error')?>: <?=$this->say('action type')?>='+action.type+', <?=$this->say('failure type')?>='+action.failureType);
								}
							}
						}
						
					} // end actionfailed listener
				}); // end skjema.on
			
				paymentWindow = Ext.create('Ext.window.Window', {
					title: method,
					width: 440,
					height: 300,
					modal: true,
					listeners: {
						show: function() {
							amountPaid.focus(true, 500);
						},
						close: function() {
							productSearchField.focus();
						},
					},
					items: [paymentForm]
				});
				paymentWindow.show();
			}
		});
	}

	showRefundPanel = function(method){
		refundOptions.load({
			params: {method: method},
			callback: function(r, options, success) {
				var paymentMethod = Ext.create('Ext.form.field.ComboBox', {
					name: 'paymentMethod',
					queryMode: 'local',
					store: refundOptions,
					valueField: 'id',
					displayField: 'name',
					hiddenName: 'paymentMethod',
					fieldLabel: '<?=$this->say('choose refund method')?>',
					allowBlank: false,
					editable: false,
					forceSelection: true,
					listWidth: 200,
					listeners: {
						select: function(combo, records, eOpts) {
							if(records[0].data.config) {
								for (var a = 0; a < refundOptions.data.length; a++) {
									params = r[a].get('config').paymentParams;
									if(params instanceof Array) {
										for (var b = 0; b < params.length; b++) {
											if(params[b].extField) {
												refundParams[params[b].name].disable();
											}
										}
									}
								}
								params = records[0].get('config').paymentParams;
								params = records[0].data.config.paymentParams;
								for (var b = 0; b < params.length; b++) {
									if(params[b].extField) {
										refundParams[params[b].name].enable();
									}
								}
							}
						}
					},
					maxHeight: 200,
					typeAhead: false,
					valueField: 'id',
					width: 400
				});
				
				var refundParams = new Object();
				
				if(refundOptions.getTotalCount() < 2) {
					paymentMethod.setValue(r[0].get('id'));
					paymentMethod.hide();
				}
			
				for (var a = 0; a < refundOptions.data.length; a++) {
					params = refundOptions.getAt(a).get('config').paymentParams;
					if(params instanceof Array) {
						for (var b = 0; b < params.length; b++) {
							if(params[b].extField) {
								refundParams[params[b].name] = Ext.create(params[b].extField);
								if(refundOptions.getTotalCount() > 1) {
									refundParams[params[b].name].disable();
								}
							}
						}
					}
				}

				var amountPaid = Ext.create('Ext.form.field.Number', {
					allowBlank: false,
					tabIndex: 0,
					hideTrigger: true,
					selectOnFocus: true,
					fieldLabel: '<?=$this->say('amount refunded')?>',
					name: 'amount',
					width: 400,
					value: '0'
				});

// 				var note = Ext.create('Ext.form.field.TextArea', {
// 					fieldLabel: '<?=$this->say('note')?>',
// 					name: 'note',
// 					height: 70,
// 					width: 400
// 				});
// 
				paymentForm = Ext.create('Ext.form.Panel', {
					autoScroll: true,
					bodyStyle: 'padding: 5px',
					border: false,
					labelWidth: 200,
					defaults: {
						selectOnFocus: true,
						width: 400
					},
					items: [amountPaid, paymentMethod],
					buttons: [{
						handler: function() {
							paymentWindow.close();
						},
						text: '<?=$this->say('cancel')?>'
					}, {
						handler: function() {
							var params = {};
							
							for (var a in refundParams) {
								if (refundParams.hasOwnProperty(a)) {
									params[a] = refundParams[a].getValue();
								}
							}
			
							paymentForm.submit({
								params: {
									params: Ext.JSON.encode(params)
								},
								url: 'index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=addrefund',
								waitMsg:'<?=$this->say('hang on')?>'
							});
						},
						text: '<?=$this->say('submit')?>'
					}]
				});

				for (var a in refundParams) {
					if (refundParams.hasOwnProperty(a)) {
						paymentForm.add(refundParams[a]);
					}
				}

				paymentForm.on({
					actioncomplete: function(form, action){
						if(action.type == 'submit'){
							if(action.response.responseText == '') {
								Ext.MessageBox.alert('Problem', '<?=$this->say('missing confirmation from server')?>');
							} else {
								updateTotals();
								paymentWindow.close();
							}
						}
					},
										
					actionfailed: function(form,action){
						if(action.type == 'submit') {
							if (action.failureType == "connect") {
								Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('lost connection to server')?>');
							}
							else if(action.response) {	
								var result = Ext.JSON.decode(action.response.responseText); 
								if(result && result.msg) {			
									Ext.MessageBox.alert('<?=$this->say('received following message from DB server')?>:', result.msg);
								}
								else {
									Ext.MessageBox.alert('<?=$this->say('whoops')?>', '<?=$this->say('operation failed due to unknown error')?>: <?=$this->say('action type')?>='+action.type+', <?=$this->say('failure type')?>='+action.failureType);
								}
							}
						}
						
					} // end actionfailed listener
				}); // end skjema.on
			
				paymentWindow = Ext.create('Ext.window.Window', {
					title: method,
					width: 440,
					height: 300,
					modal: true,
					listeners: {
						show: function() {
							amountPaid.focus(true, 500);
						},
						close: function() {
							productSearchField.focus();
						},
					},
					items: [paymentForm]
				});
				paymentWindow.show();
			}
		});
	}

	updateTotals = function(){
		Ext.Ajax.request({
			waitMsg: '<?=$this->say('hang on')?>',
			url: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=request&data=total",
			success: function(response, options){
				var result = Ext.JSON.decode(response.responseText);
				if(result['success'] == true) {
					itemsTotal.setValue(result.data.sales_total_formatted);
					discountsTotal.setValue(result.data.discounts_formatted);
					total.setValue(result.data.total_formatted);
					paid.setValue(result.data.paid_formatted);
					change.setValue(result.data.change_formatted);
					due.setValue(result.data.due_formatted);
					changemsg = result.data.change_msg;
					
					if(result.data.due <= 0 && (itemset.getCount() || <?=count($this->current_sale->getIssuedVouchers()) ? 1 : 0?>) && <?=(bool)$this->current_sale->checkIfCompleted()->data ? 0 : 1?>) {
						completeButton.enable();
						showCompletMsg(changemsg);
					}
					if(result.data.due) {
						change.hide();
						due.show();
					}
					else {
						due.hide();
						change.show();
					}
				}
				else {
					Ext.MessageBox.alert('<?=$this->say('whoops')?>',result['msg']);
				}
			}
		});
	}

	viewProduct = function(productId){
		var window = Ext.create('Ext.window.Window', {
			title: '<span style="text-align: left;"><?=$this->say('details')?></span>',
			autoLoad: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=request&data=details&id=" + productId,
			listeners: {
				close: function() {
					productSearchField.focus();
				}
			},
			width: 600,
			height: 400,
			autoScroll: true
		});
		window.show();
	}

	window.onkeyup = function(event){
		var keyID = event.keyCode;
		if(event.keyCode == 27) { // Escape key
			confirmCancel();
		}
		if(event.keyCode == 112) { // F1 key
			showDiscountpanel();
		}
		if(event.keyCode == 112) { // F1 key
			// Open Discounts window
		}
	};

	var changemsg;
	
	var productQuery = "<?php echo addslashes(@$_GET['product_query']);?>";		
		
	var kwaockProductStore = Ext.create('Ext.data.Store', {
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
	
	var itemInstances = Ext.create('Ext.data.Store', {
		model: 'ItemInstancesModel',
		pageSize: 50,
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=request&data=item_instances",
			reader: {
				type: 'json',
				root: 'data',
				totalProperty: 'totalRows'
			}
		},
		autoload: false
	});

	var quantField = Ext.create('Ext.form.field.Number', {
		allowBlank: true,
		allowDecimals: true,
		decimalSeparator: '<?=$this->say('decimal_separator')?>',
		emptyText: '<?=$this->say('quantity')?>',
		name: 'quantity',
		width: 80,
		value: '1'
	});

	var addButton = Ext.create('Ext.button.Button', {
		width: 60,
		disabled: true,
		handler: function(Button, EventObject) {
			Ext.Ajax.request({
				waitMsg: '<?=$this->say('hang on')?>',
				params: {
					quantity: quantField.getValue(),
					productid: productSearchField.getValue()
				},
				url: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=addproduct",
				 success :function(response, opts){
					itemset.load({
						callback: updateTotals()
					});
					quantField.reset();
					var a = Ext.JSON.decode(response.responseText);
					if(a.sale && a.sale != <?=$this->current_sale->id?>) {
						window.location = "index.php?docu=sales&sale=" + a.sale + (productQuery ? "&product_query=" + productQuery : "");
					}
				 }
			});
			if(productQuery) {
				kwaockProductStore.load({
					url: 'index.php?docu=_shared&mission=request&data=productCombo&query=' + productQuery,
					callback: function() {
						productSearchField.setRawValue(productQuery);
						productSearchField.expand();
					}
				});
			}
		},
		text: '<?=$this->say('add')?>'
	});

	var viewButton = Ext.create('Ext.button.Button', {
		width: 60,
		disabled: true,
		handler: function(Button, EventObject) {
			var product = productSearchField.getValue();
			viewProduct(product);
		},
		text: '<?=$this->say('view')?>'
	});

	var returnButton = Ext.create('Ext.button.Button', {
		width: 60,
		disabled: true,
		handler: function(Button, EventObject) {
			var product = productSearchField.getValue();
			showItemInstances(product);
		},
		text: '<?=$this->say('return')?>'
	});

	var productSearchField = Ext.create('Ext.form.field.ComboBox', {
		autoSelect: true,
		queryMode: 'remote',
		store: kwaockProductStore,
		emptyText: '<?=$this->say('find product')?>',
		hideLabel: true,
		labelWidth: 0,
		minChars: 1,
		queryDelay: 1000,
		allowBlank: true,
		displayField: 'product',
		editable: true,
		forceSelection: true,
		hideTrigger: true,
		selectOnFocus: true,
		listWidth: 500,
		maxHeight: 600,
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
		listeners: {
			beforequery: function(queryEvent, eOpts) {
				productQuery = queryEvent.query;
			},
			select: function(combo, records, eOpts) {
				if(records[0].data.id) {
					addButton.enable();
					viewButton.enable();
					returnButton.enable();
					addButton.focus();
				}
				else {
					addButton.disable();
					viewButton.disable();
					returnButton.disable();
				}
			}
		},
		pageSize: 10,
		width: '100%'
	});

	if(productQuery) {
		kwaockProductStore.load({
			url: 'index.php?docu=_shared&mission=request&data=productCombo&query=' + productQuery,
			callback: function() {
				productSearchField.setRawValue(productQuery);
				productSearchField.expand();
			}
		});
	}


	var productSearch = Ext.create('Ext.panel.Panel', {
		tbar: [productSearchField],
		bbar: [quantField, addButton, viewButton, returnButton]
	});

	var kwaockCustomerCombo = Ext.create('Ext.form.field.ComboBox', {
		name: 'customerCombo',
		queryMode: 'remote',
		store: Ext.create('Ext.data.Store', {
			proxy: {
				type: 'ajax',
				simpleSortMode: true,
				url: 'index.php?docu=_shared&mission=request&data=customerCombo',
				reader: {
					type: 'json',
					root: 'data',
					totalProperty: 'totalRows'
				}
			}
		}),
		emptyText: '<?=$this->say('customer')?>',
		hideLabel: false,
		minChars: 0,
		queryDelay: 1000,
		allowBlank: true,
		displayField: 'name',
		editable: true,
		forceSelection: false,
		selectOnFocus: true,
		listWidth: 500,
		maxHeight: 600,
		typeAhead: false,
		width: 200
	});

	var itemset = Ext.create('Ext.data.Store', {
		model: 'ItemModel',
		storeId: 'itemset',
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: 'index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=request&data=items',
			reader: {
				type: 'json',
				root: 'data',
				totalProperty: 'totalRows'
			}
		},
		autoLoad: true
    });
	
	itemset.on({
		load: function(itemset, records, successful, eOpts) {
			updateTotals();
		}
	});

	var paymentOptions = Ext.create('Ext.data.Store', {
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=request&data=payment_options",
			reader: {
				type: 'json',
				root: 'data',
				totalProperty: 'totalRows'
			}
		},
		fields: [{name: 'id'},{name: 'name'},{name: 'config'}]
	});

	var refundOptions = Ext.create('Ext.data.Store', {
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=request&data=refund_options",
			reader: {
				type: 'json',
				root: 'data',
				totalProperty: 'totalRows'
			}
		},
		fields: [{name: 'id'},{name: 'name'},{name: 'config'}]
	});

	var itemInstancesGrid = Ext.create('Ext.grid.Panel', {
		autoScroll: true,
		buttons: [],
		columns: [
		{
			text: '<?=addslashes($this->say('date'))?>',
			dataIndex: 'date',
			renderer: Ext.util.Format.dateRenderer('<?=addslashes($this->shortdate_format(1))?>'),
			sortable: false,
			width: 70
		}, {
			text: '<?=addslashes($this->say('quantity sold'))?>',
			dataIndex: 'quantity',
			renderer: function(value, metaData, record, rowIndex, colIndex, store){
				return record.data.quantity_formatted;
			},
			align: 'right',
			sortable: false,
			width: 50
		}, {
			text: '<?=addslashes($this->say('price per'))?>',
			dataIndex: 'pricePer',
			renderer: function(value, metaData, record, rowIndex, colIndex, store){
				return record.data.price_per_formatted;
			},
			align: 'right',
			sortable: false,
			width: 50
		}, {
			text: '<?=addslashes($this->say('discount'))?>',
			dataIndex: 'discount',
			renderer: function(value, metaData, record, rowIndex, colIndex, store){
				return record.data.discount_formatted;
			},
			align: 'right',
			sortable: false,
			width: 50
		}, {
			text: '<?=addslashes($this->say('price'))?>',
			dataIndex: 'price',
			renderer: function(value, metaData, record, rowIndex, colIndex, store){
				return record.data.price_formatted;
			},
			align: 'right',
			sortable: false,
			width: 50
		}, {
			text: '<?=addslashes($this->say('quantity returned'))?>',
			dataIndex: 'returned_quantity',
			renderer: function(value, metaData, record, rowIndex, colIndex, store){
				return record.data.returned_quantity_formatted;
			},
			align: 'right',
			sortable: false,
			width: 50
		}, {
			text: '',
			dataIndex: 'id',
			renderer: function(value, metaData, record, rowIndex, colIndex, store){
				return "<a title=\"<?=addslashes($this->say('return this item'))?>\" onclick=\"returnItem(" + value + ")\"><img src=\"../images/select.gif\" /></a>";
			},
			sortable: false,
			width: 50
		}],
		height: 250,
		buttons: [{
			handler: function(button, event) {
				returnItem();
			},
			text: '<?=addslashes($this->say('return any item'))?>'
		}],
		store: itemInstances,
		stripeRows: true,
		title:''
	});

	var overviewSales = Ext.create('Ext.panel.Panel', {
		title: '<?=$this->say('shopping baskets')?>',
		loader: {
			url: 'index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=request&data=sales_table',
			autoLoad: true
		},
		autoScroll: true,
		region:'west',
		margins: '5 0 0 0',
		cmargins: '5 5 0 0',
		bodyStyle: 'padding: 0px',
		width: 150,
		minSize: 100,
		maxSize: 600
	});

    overviewSales.getLoader().startAutoRefresh(30000, {
        url: 'index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=request&data=sales_table'
    });

	var basketName = Ext.create('Ext.form.field.Text', {
		allowBlank: true,
		emptyText: "<?=$this->say('shopping basket description')?>",
		name: "basketName",
		listeners: {
			blur: function(field, The, eOpts) {
				Ext.Ajax.request({
					waitMsg: '<?=$this->say('hang on')?>',
					params: {
						value: field.value
					},
					url: "index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=amend&data=basketname",
					 success : function(response, opts){
					 	overviewSales.getLoader().load();
						var a = Ext.JSON.decode(response.responseText);
						if(a.id && a.id != <?=$this->current_sale->id?>) {
							window.location = "index.php?docu=sales&sale=" + a.id + (productQuery ? "&product_query=" + productQuery : "");
						}
					 }
				});
			}
		},
		width: 200,
		value: "<?=addslashes($this->current_sale->getCustomer()->data->name)?>"
	});

	var customerColumn = Ext.create('Ext.panel.Panel', {
		bodyStyle: 'padding: 2px',
		border: false,
		layout: 'form',
		items: [kwaockCustomerCombo, basketName]
	});

	var searchColumn = Ext.create('Ext.panel.Panel', {
		bodyStyle: 'padding: 2px',
		border: false,
		layout: 'form',
		items: [productSearch]
	});

	var customerSet = Ext.create('Ext.panel.Panel', {
		bodyStyle: 'padding: 2px',
		border: false,
		layout: 'column',
        defaults: {
			columnWidth: 0.5,
            anchor: '-20' // leave room for error icon
        },
        items :[customerColumn, searchColumn]
	});

	var quantity = {
		align: 'right',
		dataIndex: 'quantity',
		header: '<?=$this->say('quantity')?>',
		renderer: function(value, metadata, record, rowIndex, colIndex, store) {
			return record.get('quantity_formatted');
		},
		sortable: false,
		editor: Ext.create('Ext.form.field.Number', {
			allowDecimals: true,
			hideTrigger: true,
			decimalPrecision: 3,
			decimalSeparator: '<?=$this->say('decimal_separator')?>',
			emptyText: '<?=$this->say('this is a required field')?>',
			maskRe: null,
			name: 'quantity',
			selectOnFocus: true,
			tabIndex: 0
		}),
		width: 40
	};

	var item = {
		dataIndex: 'description',
		header: '<?=$this->say('item')?>',
		renderer: function(value, metadata, record, rowIndex, colIndex, store) {
			if(record.get('quantity') >= 0) {
				return value;
			}
			else {
				return "<span style=\"color: red; font-weight: bold;\"><?=addslashes($this->say('returned'))?>: " + record.get('quantity_formatted') + " " + value + "</span>";
			}
		},
		sortable: false,
		flex: true
	};

	var pricePerItem = {
		align: 'right',
		dataIndex: 'pricePer',
		header: '<?=$this->say('price per')?>',
		renderer: function(value, metadata, record, rowIndex, colIndex, store) {
			return record.get('price_per_formatted');
		},
		sortable: false,
		editor: Ext.create('Ext.form.field.Number', {
			allowDecimals: true,
			decimalPrecision: 2,
			decimalSeparator: '<?=$this->say('decimal_separator')?>',
			emptyText: '<?=$this->say('this is a required field')?>',
			hideTrigger: true,
			maskRe: null,
			name: 'pricePer',
			selectOnFocus: true,
			tabIndex: 1
		}),
		width: 60
	};

	var discount = {
		align: 'right',
		dataIndex: 'discount',
		header: '<?=$this->say('discount')?>',
		renderer: function(value, metadata, record, rowIndex, colIndex, store) {
			return record.get('discount_formatted');
		},
		sortable: false,
		editor: Ext.create('Ext.form.field.Number', {
			allowDecimals: true,
			decimalPrecision: 2,
			decimalSeparator: '<?=$this->say('decimal_separator')?>',
			hideTrigger: true,
			maxValue: 100,
			emptyText: '<?=$this->say('this is a required field')?>',
			maskRe: null,
			name: 'discount',
			selectOnFocus: true,
			tabIndex: 1
		}),
		width: 60
	};

	var price = {
		align: 'right',
		dataIndex: 'price',
		header: '<?=$this->say('price')?>',
		renderer: function(value, metadata, record, rowIndex, colIndex, store) {
			if(value >= 0) {
				return record.get('price_formatted');
			}
			else {
				return "<span style=\"color: red;\">" + record.get('price_formatted') + "</span>";
			}
		},
		sortable: false,
		width: 60
	};

	var remove = {
		dataIndex: 'product',
		renderer: function(value, metaData, record, rowIndex, colIndex, store){
			return "<a style=\"cursor: pointer\" title=\"<?=$this->say('remove item')?>\" onclick=\"removeItem(" + record.data.productId + ")\"><img src=\"../images/delete.png\" /></a>";
		},
		sortable: false,
		width: 30
	};

	var cancelButton = Ext.create('Ext.button.Button', {
		scale: 'medium',
		width: 140,
		handler: confirmCancel,
		text: '<?=$this->say('cancel sale')?> [Esc]'
	});

	var giftVoucherButton = Ext.create('Ext.button.Button', {
		scale: 'medium',
		width: 140,
		handler: function() {
			window.location = "index.php?docu=issue_giftvoucher_form&sale=<?php echo (int)$this->current_sale->id;?>";
		},
		text: '<?=$this->say('issue giftvoucher')?>'
	});

	var email = Ext.create('Ext.form.field.Text', {
		vtype: 'email',
		disabled: true,
		allowBlank: true,
		selectOnFocus: true,
		hideLabel: true,
		labelWidth: 0,
		emptyText: '<?=$this->say('email receipt to')?>',
		name: 'email',
		width: 140
	});
	
	var completeButton = Ext.create('Ext.button.Button', {
		scale: 'medium',
		width: 140,
		disabled: true,
		handler: function(Button, EventObject) {
			showCompletMsg(changemsg);
		},
		text: '<?=$this->say('complete sale')?>'
	});
	
	var discountsButton = Ext.create('Ext.button.Button', {
		scale: 'medium',
		width: 140,
		handler: showDiscountpanel,
		text: '<?=$this->say('discounts')?> [F1]'
	});
	
	var paymentButton = [];
<?
	$buttonset = array();
	$payments = $this->mysqli->arrayData(array(
		'source' => "{$tp}payment_methods",
		'where' => 'enabled',
		'fields' => 'MIN(id) AS id, MIN(colour) AS colour, paymentGroup',
		'groupfields' => 'paymentGroup',
		'orderfields' => 'id'
	));
	
	foreach($payments->data as $payment){
		$buttonset[] = "paymentButton[{$payment->id}]";
?>
	paymentButton[<?=$payment->id?>] = Ext.create('Ext.button.Button', {
		handler: function(Button, EventObject) {
			showPaymentPanel('<?=addslashes($payment->paymentGroup)?>');
		},
		text: '<?=addslashes($payment->paymentGroup)?>',
		width: 140,
		scale: 'large'
	});

<?
	}

?>
	var itemsTotal = Ext.create('Ext.form.field.Display', {
		fieldCls: 'totalfields',
		width: 150
	});

	var total = Ext.create('Ext.form.field.Display', {
		fieldCls: 'totalfields',
		width: 150
	});

	var discountsTotal = Ext.create('Ext.form.field.Display', {
		fieldCls: 'totalfields',
		width: 150
	});

	var paid = Ext.create('Ext.form.field.Display', {
		fieldCls: 'totalfields',
		width: 150
	});

	var due = Ext.create('Ext.form.field.Display', {
		fieldCls: 'totalfields',
		fieldStyle: "font-size: 1.2em;",
		width: 180
	});

	var change = Ext.create('Ext.form.field.Display', {
		fieldCls: 'totalfields',
		fieldStyle: "font-size: 1.2em; color: green;",
		width: 180
	});

	var totalsbar = Ext.create('Ext.toolbar.Toolbar', {
		items: [
			itemsTotal,
			'-',
			discountsTotal,
			'-',
			paid,
			'-',
			'->',
			due,
			change
		]
	});
	
	var itemPanel = Ext.create('Ext.grid.Panel', {
		autoScroll: true,
		buttons: [
			cancelButton,
			discountsButton,
			giftVoucherButton,
			email,
			completeButton
		],
		columns: [
			quantity,
			item,
			pricePerItem,
			discount,
			price,
			remove
		],
        height: 300,
        plugins: Ext.create('Ext.grid.plugin.CellEditing', {
			clicksToEdit: 1
		}),
		store: itemset,
		stripeRows: true,
        title:''
    });

	itemPanel.on('edit', saveChanges);
	


	var discounts = Ext.create('Ext.data.JsonStore', {
		proxy: {
			type: 'ajax',
			simpleSortMode: true,
			url: 'index.php?docu=sales&sale=<?=$this->current_sale->id?>&mission=request&data=discounts',
			reader: {
				type: 'json',
				root: 'data',
				totalProperty: 'totalRows'
			}
		},
		fields: [
			{name: 'id', type: 'float'},
			{name: 'trader'},
			{name: 'description'},
			{name: 'value', type: 'float'},
			{name: 'discountRate', type: 'float'},
			{name: 'value_formatted'},
			{name: 'discount_rate_formatted'}
		],
		idProperty: 'id'
    });
    discounts.load();


	var centreRegion = Ext.create('Ext.panel.Panel', {
		style: 'background-color: red;',
		title: '',
		layout: 'form',
		split: true,
		bodyStyle: 'padding: 15px',
		collapsible: false,
		region: 'center',
		margins: '5 0 0 0',
		items: [
			customerSet,
			itemPanel
		],
		bbar: totalsbar
	});

	var mainPanel = Ext.create('Ext.panel.Panel', {
		renderTo: 'panel',
		layout: 'border',
		defaults: {
			split: true,
			bodyStyle: 'padding: 15px'
		},
		items: [overviewSales, {
			title: '<?=$this->say('payment options')?>',
			region: 'east',
			border: false,
			height: 120,
			collapsed: false,
			minSize: 75,
			width: 170,
			maxSize: 250,
			cmargins: '5 0 0 0',
			items: [<?=implode(",", $buttonset)?>]
		}, centreRegion],
		title: '',
		height: 550,
		autoWidth: true
	});

	updateTotals();
	productSearchField.focus();
});
<?
}

function design() {
?>
	<div id="panel"></div>
<?
}

function task($task = "") {
	switch ($task) {
		case "print":
			$this->template = "receipt";
			$this->write_html();
			break;
		default:
			break;
	}
}

function request($data = "") {
	$tp = $this->mysqli->table_prefix;
	$dec = new NumberFormatter($this->say('locale'), NumberFormatter::DECIMAL);
	switch ($data) {

	case "details": {
		$product = $this->mysqli->arrayData(array(
			'source' => $tp.'products',
			'where' => "id = '" . (int)$_GET['id'] . "'"
		))->data[0];
		echo "<table width=\"100%\"><tbody>\n";
		if(is_object($product)){
			foreach($product as $property => $value) {
				echo "\t<tr>\n\t\t<td>{$property}:</td><td>{$value}</td>\n\t</tr>\n";
			}
		}
		echo "</tbody></table>";
		break;
	}

	case "discounts": {
		$result = new stdClass;
		$result->data = $this->current_sale->getDiscounts()->data;
		$result->success = true;
		foreach($result->data as $element => $discount) {
			$result->data[$element]->value_formatted = $this->money($discount->value);
			$result->data[$element]->discount_rate_formatted = $this->percent($discount->discountRate);
		}
		return json_encode($result);
		break;
	}

	case "item_instances": {
		$product = new Product(array('id' => $_GET['product']));
		$result = $product->getSoldItems();
		foreach($result->data as $index => $item) {
			$item->quantity_formatted = $this->number($item->quantity) . ($product->unit ? " {$product->unit}" : "");
			$item->price_per_formatted = $this->money($item->pricePer);
			$item->discount_formatted = $this->percent($item->discount);
			$item->price_formatted = $this->money($item->price);
			$item->returned_quantity_formatted = $this->number($item->returned_quantity) . ($product->unit ? " {$product->unit}" : "");
		}
		return json_encode($result);
		break;
	}

	case "items": {
		$items = $this->current_sale->getItems();
		foreach($items->data as $element => $item) {
			$items->data[$element]->productId = $item->product->id;
			$items->data[$element]->quantity_formatted = $this->number(abs($item->quantity)) . ($item->unit ? " {$item->unit}" : "");
			$items->data[$element]->description = (($image = $item->product->getAttributeValue('image')->data) ? ("<img src=\"" . $image . "\" style=\"max-height: 40px; max-width: 40px; float: right;\" />") : "") . $item->description;
			$items->data[$element]->price_per_formatted = $this->money($item->pricePer);
			$items->data[$element]->price_formatted = $this->money($item->price);
			$items->data[$element]->discount_formatted = $this->percent($item->discount/100);
		}
		$items->total = ($this->current_sale->getTotal()->data);
		return json_encode($items);
		break;
	}

	case "payment_options": {
		$result = $this->mysqli->arrayData(array(
			'class'	=>	"PaymentMethod",
			'source' => $tp.'payment_methods',
			'fields' => $tp.'payment_methods.id',
			'where' => "enabled and allowPayments and paymentGroup = '{$this->GET['method']}'" 
		));
		return json_encode($result);
		break;
	}
		
	case "refund_options": {
		$result = $this->mysqli->arrayData(array(
			'class'	=>	"PaymentMethod",
			'source' => "{$tp}payment_methods",
			'where' => "enabled and allowRefunds" 
		));

		return json_encode($result);
		break;
		}
		
	case "sales_table": {
		$sales = $this->mysqli->arrayData(array(
			'source' => $tp.'sales',
			'where' => "!completed",
			'orderfields' => "id DESC"
		));
		$completed_sales = $this->mysqli->arrayData(array(
			'source' => $tp.'sales',
			'where' => "completed > " . (time()-3600),
			'orderfields' => "completed DESC"
		));
		echo "<table width=\"100%\"><tbody>\n";
		echo "\t<tr style=\"padding: 3px;\">\n\t\t<td style=\"border: " . ((!(int)$this->current_sale->id) ? "3px solid red;" : "1px solid black;") . " padding: 5px; background-color: white;\"><a href=\"index.php?docu=sales\" style=\"display:block;\">" . $this->say('empty basket') . "</a></td>\n\t</tr>\n";
		foreach($sales->data as $sale) {
			echo "\t<tr style=\"padding: 3px;\">\n\t\t<td style=\"border: " . (($sale->id == (int)$this->current_sale->id) ? "3px solid red;" : "1px solid black;") . " padding: 5px; background-color:{$sale->colour}; color:white;\"><a href=\"index.php?docu=sales&sale={$sale->id}\" style=\"display:block;\">" . ($sale->customername ? $sale->customername : $this->say('open basket')) . "</a></td>\n\t</tr>\n";
		}
		foreach($completed_sales->data as $sale) {
			$shade = (int)(128 + (time() - $sale->completed)/30);
			echo "\t<tr style=\"padding: 3px;\">\n\t\t<td style=\"border: " . (($sale->id == (int)$this->current_sale->id) ? "3px solid red;" : "1px solid black;") . " padding: 5px; background-color:rgb($shade,$shade,$shade); color:white;\"><a href=\"index.php?docu=sale_record&id={$sale->id}\" style=\"display:block;\">" . ($sale->customername ? $sale->customername : $this->say('completed sale')) . "</a></td>\n\t</tr>\n";
		}
		echo "</tbody></table>";
		break;
	}

	case "total": {
		$result = (object) array(
			'data' => (object) array()
		);
		$data = $result->data;
		
		$data->itemsTotal = $this->current_sale->getItemsTotal()->data;
		$data->sales_total_formatted = $this->say('sales total') . ": " . $this->money($data->itemsTotal);
		
		$data->total = $this->current_sale->getTotal()->data;
		$data->total_formatted = $this->say('total') . ": " . $this->money($data->total);
		
		$data->discounts = $this->current_sale->getItemsTotal()->data - $this->current_sale->getTotal()->data;
		$data->discounts_formatted = $this->say('discounts') . ": " . $this->money($data->itemsTotal - $data->total);
		
		$data->paid = $this->current_sale->getPaymentsTotal()->paid;
		$data->paid_formatted = $this->say('paid') . ": " . $this->money($data->paid);
		
		$data->due = $this->current_sale->getPaymentsTotal()->due;
		$data->due_formatted = $this->say('to pay') . ": " . $this->money($data->due);
		
		$data->change = $this->current_sale->getPaymentsTotal()->change;
		$data->change_formatted = $this->say('change due') . ": " . $this->money($data->change);
		
		$data->change_msg = $this->say('please pay change', array($this->money($data->change)));
		
		$result->success = true;
		return json_encode($result);
		break;
		}

	default: {
		$this->current_sale->invoice();
		break;
	}
	}
}

function amend($data = "") {
	$tp = $this->mysqli->table_prefix;
	$dec = new NumberFormatter($this->say('locale'), NumberFormatter::DECIMAL);
	switch ($data) {
		case "add_discount":
			foreach($_POST as $variable => $value) {
				if($a = strstr($variable, "trader_") and $value) {
					$trader[] = substr($variable, 7);
				}
			}
			$result = $this->current_sale->discount(array(
				'description' => $_POST['discount_description'],
				'value' => $dec->parse($_POST['discount_value']),
				'discountRate' => $dec->parse($_POST['discountRate'])/100,
				'trader' => $trader
			));
			echo json_encode($result);
			break;
		case "addpayment":
			$details = (object) $_POST;
			$details->params = json_decode($_POST['params']);
			$result = $this->current_sale->addPayment($details, $this->user['name']);
			echo json_encode($result);
			break;
		case "addproduct":
			$result = $this->current_sale->addItem(array(
				'id' => @$_POST['productid'],
				'quantity' => @$_POST['quantity']
			));
			echo json_encode($result);
			break;
		case "addrefund":
			$post = (object) $_POST;
			$post->amount = -$_POST['amount'];
			$post->params = json_decode($_POST['params']);
			$result = $this->current_sale->addPayment($post, $this->user['name']);
			echo json_encode($result);
			break;
		case "basketname":
			$result = $this->current_sale->assign(array('customername' => $_POST['value']));
			echo json_encode($result);
			break;
		case "cancel":
			$result = $this->current_sale->cancel();
			echo json_encode($result);
			break;
		case "complete":
			$result = (object) array(
				'success' => true
			);
			
			$email = @$_POST['email'];
			$print = @$_POST['print'];
			$nonExchangeablePayments = $this->current_sale->getPaymentsTotal()->nonExchangeableExcessivePayment;

			foreach($nonExchangeablePayments as $nonExchangeablePayment) {

				if($nonExchangeablePayment->paymentMethod->id == -2) {
					$payments = $nonExchangeablePayment->payments;
					foreach($payments as $payment) {

						$voucher = new GiftVoucher($payment->params->giftVoucherId);						
						$allowSharedDeposit = !$voucher->prepaymentholdingTrader->id;

						$config = array(
							'code'			=> $voucher->code,
							'value'			=> bcsub($payment->amount, $this->current_sale->getSharesOfPayment($payment)->distributed, 6),
							'traders'		=> $voucher->traders,
							'prepaymentholdingTrader'	=> $voucher->prepaymentholdingTrader,
							'design'		=> $voucher->design,
							'expires'		=> $voucher->expires,
							'redeemableForCash'	=> $voucher->redeemableForCash
						);
										
						$result = $this->current_sale->issueGiftVoucher(
							$allowSharedDeposit,
							$config,
							$this->user['name']
						);
					}
				}
			}

			if($result->success) {
				$result = $this->current_sale->registerChange(
					$this->preferences->till_payment_method,
					$this->user['name'] 
				);
			}
		
			if($result->success) {
				$result = $this->current_sale->invoice();
			}
			
			$result->windows = array(
			);
			foreach($this->current_sale->getIssuedVouchers() as $voucher) {
				$result->windows[] = "index.php?docu=voucher&id={$voucher->id}";
			}
			
			if($email) {
				// email receipt
			}
			if($print) {
				// CollectivePOS::sendMail();
				// print receipt
			}
			echo json_encode($result);
			break;
		case "edit_item":
			$result = $this->current_sale->editItem(array(
				$_POST['field'] => $_POST['value'],
				'id'  => $_POST['id']
			));
			if($result->success) {
				$result = $this->mysqli->arrayData(array(
					'source' => "{$tp}sale_items",
					'where' => "id = '{$_POST['id']}'"
				));
				$result->data = $result->data[0];
				$result->data->quantity_formatted = $this->number(abs($result->data->quantity)) . ($result->data->unit ? " {$result->data->unit}" : "");
				$result->data->price_per_formatted = $this->money($result->data->pricePer);
				$result->data->discount_formatted = $this->percent($result->data->discount);
				$result->data->price_formatted = $this->money($result->data->price);
				$result->data->discount = $result->data->discount * 100;
			}
			echo json_encode($result);
			break;
		case "removediscount":
			$result = $this->current_sale->removeDiscount($_POST['discount']);
			echo json_encode($result);
			break;
		case "removeproduct":
			$result = $this->current_sale->removeItem($_POST['product']);
			echo json_encode($result);
			break;
		case "return_item":
			$result = $this->current_sale->returnItem($_POST['product'], $_POST['item'], $_POST['quantity']);
			echo json_encode($result);
			break;
		default:
//			echo $this->say('test');
			break;
	}
}

}