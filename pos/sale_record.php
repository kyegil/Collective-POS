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
		'id' => $_GET['id'])
	);
}

function script() {
	$tp = $this->mysqli->table_prefix;
?>
Ext.Loader.setConfig({
	enabled: true
});
Ext.Loader.setPath('Ext.ux', '<?="{$this->http_host}/{$this->ext_library}/examples/ux"?>');

Ext.require([
	'Ext.layout.*',
	'Ext.resizer.*'
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
			url: "index.php?docu=<?=$_GET['docu']?>&id=<?=$_GET['id']?>&mission=request",
			autoLoad: true
		},
		autoScroll: true,
		autoWidth: true
	});

	var mainPanel = Ext.create('Ext.Panel', {
		layout: 'border',
		renderTo: 'panel',
		items: [centrePanel],
		height: 500,
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
		},
		buttons: [{
			handler: function(button, event) {
				window.open("index.php?docu=sale_record&id=<?=$this->current_sale->id?>&mission=task&task=print");
			},
			text: '<?=$this->say('print receipt')?>'
		}]
	});

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

	switch ($data) {

	default:
		if($this->current_sale->id) {
			echo "<span class=\"dataload\">\n";
			echo "<h2>" . $this->say('x sale on x', array($this->money($this->current_sale->getTotal()->data), $this->long_datetime($this->current_sale->checkIfCompleted()->data))) . "<br /></h2>";
			echo "<p>{$this->say('sale id')}: {$this->current_sale->id}<br />&nbsp;</p>\n";
			echo "<table><tbody>\n";
			echo "\t<tr>\n";
			echo "\t\t<th>{$this->say('trader')}</th>\n";
			echo "\t\t<th>{$this->say('invoice')}</th>\n";
			echo "\t\t<th>{$this->say('quantity')}</th>\n";
			echo "\t\t<th>{$this->say('product code')}</th>\n";
			echo "\t\t<th>{$this->say('description')}</th>\n";
			echo "\t\t<th>{$this->say('price per')}</th>\n";
			echo "\t\t<th>{$this->say('price')}</th>\n";
			echo "\t</tr>\n";
			foreach($this->current_sale->getInvoices()->data as $invoice) {
				foreach($invoice->getItems()->data as $item) {
					echo "\t<tr>\n";
					echo "\t\t<td>{$invoice->getTrader()->data->traderCode}</td>\n";
					echo "\t\t<td>{$invoice->number}</td>\n";
					echo "\t\t<td>{$item->quantity}</td>\n";
					echo "\t\t<td>{$item->productCode}</td>\n";
					echo "\t\t<td>{$item->description}</td>\n";
					echo "\t\t<td class=\"value\">{$this->money($item->pricePer)}</td>\n";
					echo "\t\t<td class=\"value\">" . $this->money($item->price) . "</td>\n";
					echo "\t</tr>\n";
					if((float)$item->reduction) {
						echo "\t<tr>\n";
						echo "\t\t<td>&nbsp;</td>\n";
						echo "\t\t<td>&nbsp;</td>\n";
						echo "\t\t<td>&nbsp;</td>\n";
						echo "\t\t<td colspan=\"2\">{$this->say('original price x', array($this->money($item->originalPrice)))}</td>\n";
						echo "\t\t<td>&nbsp;</td>\n";
						echo "\t\t<td>&nbsp;</td>\n";
						echo "\t</tr>\n";
					}
					if((float)$item->discount) {
						echo "\t<tr>\n";
						echo "\t\t<td>&nbsp;</td>\n";
						echo "\t\t<td>&nbsp;</td>\n";
						echo "\t\t<td>&nbsp;</td>\n";
						echo "\t\t<td colspan=\"2\">{$this->say('at x discount', array($this->percent($item->discount)))}</td>\n";
						echo "\t\t<td>&nbsp;</td>\n";
						echo "\t\t<td>&nbsp;</td>\n";
						echo "\t</tr>\n";
					}
				}
			}
			echo "\t<tr>\n";
			echo "\t\t<td>{$this->say('total for items')}</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td class=\"value\">{$this->money($this->current_sale->getItemsTotal()->data)}</td>\n";
			echo "\t</tr>\n";
			echo "\t<tr>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t</tr>\n";
			if(count($discounts = $this->current_sale->getDiscounts()->data)) {
				echo "\t<tr>\n";
				echo "\t\t<th>{$this->say('discounts overall')}</th>\n";
				echo "\t\t<th>&nbsp;</th>\n";
				echo "\t\t<th>&nbsp;</th>\n";
				echo "\t\t<th>&nbsp;</th>\n";
				echo "\t\t<th>&nbsp;</th>\n";
				echo "\t\t<th>&nbsp;</th>\n";
				echo "\t\t<th>&nbsp;</th>\n";
				echo "\t</tr>\n";
			}
			foreach($discounts as $discount) {
				unset($tradernames);
				foreach($discount->traders as $disctrader) {
					$tradernames[] = $disctrader->traderCode;
				}
				$discountnom = (float)$discount->discountRate ? $this->percent($discount->discountRate) : "";
				$discountnom .= ((float)$discount->discountRate and (float)$discount->value) ? " + " : "";
				$discountnom .= (float)$discount->value ? $this->money($discount->value) : "";
				echo "\t<tr>\n";
				echo "\t\t<td>" . implode(" + ", $tradernames) . "</td>\n";
				echo "\t\t<td colspan=\"2\">" . $this->say('x discount', array($discountnom)) . "</td>\n";
				echo "\t\t<td colspan=\"4\">{$discount->description}</td>\n";
				echo "\t</tr>\n";
			}
			echo "\t<tr>\n";
			echo "\t\t<td class=\"bold\">{$this->say('total')}</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td class=\"value bold\">{$this->money($this->current_sale->getTotal()->data)}</td>\n";
			echo "\t</tr>\n";
			echo "\t<tr>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t\t<td>&nbsp;</td>\n";
			echo "\t</tr>\n";
			echo "\t<tr>\n";
			echo "\t\t<th>{$this->say('payments')}</th>\n";
			echo "\t\t<th>&nbsp;</th>\n";
			echo "\t\t<th>&nbsp;</th>\n";
			echo "\t\t<th>&nbsp;</th>\n";
			echo "\t\t<th>&nbsp;</th>\n";
			echo "\t\t<th>&nbsp;</th>\n";
			echo "\t\t<th>&nbsp;</th>\n";
			echo "\t</tr>\n";
			foreach($this->current_sale->getPayments()->data as $payment) {
				if($payment->amount < 0) {
					echo "\t<tr>\n";
					echo "\t\t<td colspan=\"3\">{$payment->name}</td>\n";
					echo "\t\t<td></td>\n";
					echo "\t\t<td>{$payment->note}</td>\n";
					echo "\t\t<td>&nbsp;</td>\n";
					echo "\t\t<td class=\"value\">{$this->money($payment->amount)}</td>\n";
					echo "\t</tr>\n";
				}
				else {
					echo "\t<tr>\n";
					echo "\t\t<td colspan=\"3\">{$this->say('x payment received by x at x', array($payment->name, $payment->registerer, $this->shorttime($payment->timestamp)))}</td>\n";
					echo "\t\t<td></td>\n";
					echo "\t\t<td>{$payment->note}</td>\n";
					echo "\t\t<td>&nbsp;</td>\n";
					echo "\t\t<td class=\"value\">{$this->money($payment->amount)}</td>\n";
					echo "\t</tr>\n";
				}
			}
			echo "</tbody></table>\n";
			echo "</span>\n";
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