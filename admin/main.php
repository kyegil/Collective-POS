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
	$tp = $this->mysqli->table_prefix;
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

	var load = function() {
		cashPanel.getLoader().load({
			url: "index.php?docu=main&trader=<?=$trader->id?>&mission=request&data=cash&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d') + "&daily=" + (daily.getValue() ? 1 : 0)
		});
		salesPanel.getLoader().load({
			url: "index.php?docu=main&trader=<?=$trader->id?>&mission=request&data=sales&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d') + "&daily=" + (daily.getValue() ? 1 : 0)
		});
		sorPanel.getLoader().load({
			url: "index.php?docu=main&trader=<?=$trader->id?>&mission=request&data=sor&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d') + "&daily=" + (daily.getValue() ? 1 : 0)
		});
	}

	var from = Ext.create('Ext.form.field.Date', {
		enableKeyEvents: true,
		format: '<?=$this->shortdate_format(true)?>',
		startDay: <?=$this->firstDayOfWeek-1?>,
		submitFormat: 'Y-m-d',
		fieldLabel: '<?=$this->say('from date', array())?>',
		maxValue: new Date(),
		value: new Date(<?=$this->lastSalesdate()->format('Y, m-1, d')?>),
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
		enableKeyEvents: true,
		format: '<?=$this->shortdate_format(true)?>',
		startDay: <?=$this->firstDayOfWeek-1?>,
		submitFormat: 'Y-m-d',
		fieldLabel: '<?=$this->say('to date', array())?>',
		value: new Date(),
		listeners: {
			keypress: function(field, event, opts) {
				var key = event.getKey();
				if(key == 13) {
					load();
				}
			}
		}
	});

	var daily = Ext.create('Ext.form.field.Checkbox', {
		boxLabel: '<?=$this->say('daily breakdown', array())?>',
		inputValue: '1',
		uncheckedValue: '0',
		value: '0',
		listeners: {
			change: function(box, newValue, oldValue, eOpts ) {
				load();
			}
		}
	});

	var cashPanel = Ext.create('Ext.Panel', {
		title: "<?=$this->say('cash', array())?>",
		region:	'west',
		loader: {
			url: "index.php?docu=main&trader=<?=$trader->id?>&mission=request&data=cash&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d'),
			autoLoad: true
		},
		autoScroll: true,
		buttons: [{
			text: '<?=$this->say('print cash report', array())?>',
			handler: function() {
				window.open("index.php?docu=cash_report&trader=<?=$_GET['trader']?>&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d'));
			}
		}]
	});

	var salesPanel = Ext.create('Ext.Panel', {
		title: "<?=$this->say('traders sales summary', array($this->possessive($trader->name)))?>",
		region:	'center',
		loader: {
			url: "index.php?docu=main&trader=<?=$trader->id?>&mission=request&data=sales&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d'),
			autoLoad: true
		},
		autoScroll: true,
		buttons: [{
			text: '<?=$this->say('print cash report', array())?>',
			handler: function() {
				window.open("index.php?docu=sales_report&trader=<?=$_GET['trader']?>&from=" + Ext.Date.format(from.getValue(), 'Y-m-d') + "&to=" + Ext.Date.format(to.getValue(), 'Y-m-d'));
			}
		}]

	});

	var sorPanel = Ext.create('Ext.Panel', {
		region:	'east',
		loader: {
			url: "index.php?docu=main&trader=<?=$trader->id?>&mission=request&data=sor",
			autoLoad: true
		},
		width: 400,
		autoScroll: true
	});

	var mainPanel = Ext.create('Ext.Panel', {
		layout: 'border',
		renderTo: 'panel',
		items: [cashPanel, salesPanel, sorPanel],
		height: 500,
		autoScroll: true,
		autoWidth: true,
		tbar: [from, to, daily]
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
	$stockValue = $trader->getStockValue(true);
	$stockValueExSor = $trader->getStockValue(false);
	switch ($data) {
		case "sales":
			$daily = @$_GET['daily'] or @$_POST['daily'];

			echo "<h3>" . $this->say('traders sales summary', array($this->possessive($trader->name))) . " ";
			if($this->from){
				echo $this->shortdate($this->from);
			}
			if($this->to and ($this->shortdate($this->to) != $this->shortdate($this->from))){
				echo " - " . $this->shortdate($this->to);
			}
			echo "</h3>\n";
			if($daily) {
				$periods = $trader->getSaleDates($this->from, $this->to);
			}
			else {
				$periods = array($this->from);
			}
			foreach($periods as $date) {
				$from = ($daily ? $date : $this->from);
				$to = ($daily ? $date : $this->to);
		
				if($daily) {
					echo "<h3>" . $this->shortdate($date) . "</h3>\n";
				}
				echo "<h3>" . $this->say('report total sales', array($this->money($trader->get_invoiced_total($from, $to)))) . "</h3>\n";
		
				echo "<div>" . $this->say('report cost of sales', array($this->money($trader->getCostOfSales($from, $to)))) . "</div>\n";
		
				if($trader->preferences->manage_tax) {
					$tax = $trader->getTaxSummary($from, $to);
					echo "<div strong>" . $this->say('report total tax received', array()) . "</strong>\n";
					foreach($tax as $taxgroup) {
						echo "<p>{$taxgroup->tax_name}: " . $this->say('report tax percent of value', array($this->percent($taxgroup->tax_rate), $this->money($taxgroup->basis), $this->money($taxgroup->tax))) . "</p>";
					}
					echo "</div>\n";
				}
			}
			
			echo "<div><strong>{$this->say('report stock per', array($this->long_datetime(new DateTime)))}</strong>";
				echo "<div>" . $this->say('report stock items count', array($this->number($trader->getStockItemsCount(true)))) . "</div>\n";
				echo "<div>" . $this->say('report stock products count', array($this->number($trader->getStockProductsCount(true)))) . "</div>\n";
		
				echo "<div>" . $this->say('report stock value', array($this->money($stockValue))) . "</div>\n";
				if( $stockValue != $stockValueExSor ) {
				echo "<div>" . $this->say('report stock value excluding sor', array($this->money($stockValueExSor))) . "</div>\n";
				}
		
			echo "</div>";
			break;
		case "sor":
			$sor = $trader->getSor($this->from, $this->to)->data;
			if(count($sor)) {
				echo "<h3>" . $this->say('report sale or return items sold', array()) . ":</h3>\n";
			}
			echo "<div class = \"dataload\"><table style=\"border-bottom: 1px solid grey;\">\n";
			foreach($sor as $supplier) {
				echo "\t<tr id=\"bold\"><td colspan = \"5\">{$supplier->suppliername}:</td></tr>\n";
				foreach($supplier->items as $item) {
					echo "\t<tr>\n\t\t<td class=\"value\">" . $this->number($item->quantity) . "</td>\n\t\t<td>" . $item->product->productCode . "</td>\n\t\t<td>" . $item->product->name . "</td>\n\t\t<td class=\"value\">" . $this->money($item->cost) . "</td>\n\t\t<td class=\"value\">" . $this->money($item->total_costs) . "</td>\n\t</tr>\n";
				}
				echo "\t<tr id=\"bold\"><td colspan = \"4\"></td><td class=\"value\">" . $this->money($supplier->total_costs) . "</td></tr>\n";
			}
			echo "</table>\n";
			echo "</div>\n";
			break;
		case "cash":
			$tp = $this->mysqli->table_prefix;
			$trader = new Trader($_GET['trader']);
			$statement = $trader->getTillStatement($this->till->id, $this->from, $this->to->setTime(23, 59, 59), false);
?>
			<span class="dataload">
	<p>
		<?=$this->say('report total sum deposits x', array($this->money($statement->deposits)))?>
	<br />
		<?=$this->say('report total sum withdrawals x', array($this->money($statement->withdrawals)))?>
	<br />
		<?=$this->say('report total sum cash payments x', array($this->money($statement->cash_payments)))?>
	</p>
			<table>
				<tr id="bold">
					<th><?=$this->say('time')?></th>
					<th>&nbsp;</th>
					<th class="value"><?=$this->say('sum')?></th>
					<th class="value"><?=$this->say('balance')?></th>
				</tr>
			<?foreach($statement->transactions as $entry):?>
				<tr id="bold">
					<td><?=$this->short_datetime($entry->time)?></td>
					<td><?=($entry->note ? addslashes($entry->note) : $this->say("till {$entry->action}"))?></td>
					<td class="value"><?=$this->money($entry->adjustment)?></td>
					<td class="value"><?=$this->money($entry->balance)?></td>
				</tr>
			<?endforeach?>
			</table>
			</span>
<?
			break;
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