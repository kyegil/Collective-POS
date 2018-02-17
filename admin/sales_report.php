<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
if(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {
	public $template = "report";
	

function __construct() {
	parent::__construct();
	$this->title = $this->say['report sales summary']->format(array());
}

function script() {
	$tp = $this->mysqli->table_prefix;
	$trader = new Trader($_GET['trader']);
}

function design() {
	$tp = $this->mysqli->table_prefix;
	$trader = new Trader($_GET['trader']);
	$daily = @$_GET['daily'] or @$_POST['daily'];
	$taxtypes = $this->getTaxes();
	$periods = $trader->getSaleDates($this->from, $this->to);
	$taxSummaryBreakdownByPaymentMethods = $trader->getTaxSummaryBreakdownByPaymentMethods($this->from, $this->to);
	$stockValue = $trader->getStockValue(true);
	$stockValueExSor = $trader->getStockValue(false);

?>
	<div class="dataload">
	<h1><?php echo $this->say('report sales summary', array());?>
	<?php if($this->from):?><?php echo $this->shortdate($this->from);?><?php endif;?>
	<?php if($this->to and ($this->to !== $this->from)):?> - <?php echo $this->shortdate($this->to);?><?php endif;?>
	</h1>
	<h2><?php echo $trader->name;?></h2>
	<p><?php echo nl2br($trader->address);?></p>
	
	<div>
	<table>
		<tr>
			<th><?php echo $this->say('date');?></th>
			<th class="value"><?php echo $this->say('sales total');?></th>
			<?php if($trader->preferences->manage_tax):?>
				<?php foreach($taxtypes as $taxtype):?>
					<th colspan="2" class="value"><?php echo $taxtype->taxName;?></th>
				<?php endforeach;?>
			<?php endif;?>
			<th class="value"><?php echo $this->say('report head cost of sales');?></th>
		</tr>
		<?php if($trader->preferences->manage_tax):?>
			<tr>
				<th>&nbsp;</th>
				<th>&nbsp;</th>
				<?php foreach($taxtypes as $taxtype):?>
					<th class="value"><?php echo $this->say('report tax basis');?></th>
					<th class="value"><?php echo $this->say('report tax');?></th>
				<?php endforeach;?>
			</tr>
		<?php endif;?>
		
		<?php foreach($periods as $date):?>
			<tr>
				<td><?php echo $this->shortdate($date);?></td>
				<td class="value"><?php echo $this->money($trader->get_invoiced_total($date, $date));?></td>
				<?php if($trader->preferences->manage_tax):?>
					<?php foreach($taxtypes as $taxtype):?>
						<?php $tax = $trader->getTaxSummary($date, $date, $taxtype->id);?>
						<td class="value"><?php echo $this->money($tax->basis);?></td>
						<td class="value"><?php echo $this->money($tax->tax);?></td>
					<?php endforeach;?>
				<?php endif;?>
				<td class="value"><?php echo $this->money($trader->getCostOfSales($date, $date));?></td>
			</tr>
		<?php endforeach;?>

		<tr>
			<td>&nbsp;</td>
			<td class="value bold"><?php echo $this->money($trader->get_invoiced_total($this->from, $this->to));?></td>
			<?php if($trader->preferences->manage_tax):?>
				<?php foreach($taxtypes as $taxtype):?>
					<?php $tax = $trader->getTaxSummary($this->from, $this->to, $taxtype->id);?>
					<td class="value bold"><?php echo $this->money( $tax->basis );?></td>
					<td class="value bold"><?php echo $this->money( $tax->tax );?></td>
				<?php endforeach;?>
			<?php endif;?>
			<td class="value bold"><?php echo $this->money($trader->getCostOfSales($this->from, $this->to));?></td>
		</tr>
	</table>
	<br />
	</div>
	
	<?php if($trader->preferences->manage_tax):?>
	<div>
	<table>

		<tr>
			<th colspan="2"><?php echo $this->say('report tax breakdown by payment method');?></th>
			<th class="value"><?php echo $this->say('report share of payment');?></th>
			<?php foreach($taxtypes as $taxtype):?>
				<th colspan="2" class="value"><?php echo $taxtype->taxName;?></th>
			<?php endforeach;?>
		</tr>

		<tr>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
			<?php foreach($taxtypes as $taxtype):?>
				<th class="value"><?php echo $this->say('report tax basis');?></th>
				<th class="value"><?php echo $this->say('report tax');?></th>
			<?php endforeach;?>
		</tr>

		<?php $total = 0; $basistotal = $taxtotal = array();?>
		<?php foreach($taxSummaryBreakdownByPaymentMethods->payments as $paymentMethod):?>
			<?php $total = bcadd(@$total, $paymentMethod->share, 6 );?>
			<tr>
				<td><?php echo $paymentMethod->paymentMethod->paymentGroup;?></td>
				<td><?php echo $paymentMethod->paymentMethod->name;?></td>
				<td class="value"><?php echo $this->money( $paymentMethod->share );?></td>

				<?php foreach($taxtypes as $taxtype):?>
					<?php if($tax = @$paymentMethod->taxes[$taxtype->id]):?>
						<?php $basistotal[$tax->id] = bcadd(@$basistotal[$tax->id], $tax->basis, 6 );?>
						<?php $taxtotal[$tax->id] = bcadd(@$taxtotal[$tax->id], $tax->tax, 6 );?>

						<td class="value"><?php echo $this->money($tax->basis);?></td>
						<td class="value"><?php echo $this->money($tax->tax);?></td>
					<?php else:?>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
					<?php endif;?>
				<?php endforeach;?>

			</tr>
		<?php endforeach;?>

		<tr>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td class="value bold"><?php echo $this->money($total);?></td>
				<?php foreach($taxtypes as $taxtype):?>
					<td class="value bold"><?php echo $this->money( @$basistotal[$taxtype->id] );?></td>
					<td class="value bold"><?php echo $this->money( @$taxtotal[$taxtype->id] );?></td>
				<?php endforeach;?>
		</tr>

	</table>
	</div>
	
	<div>
		<?php if($taxSummaryBreakdownByPaymentMethods->incalculableInvoices):?>
		<div><strong><?php echo $this->say("report incalculable invoices", array());?></strong></div>
		<div><?php echo $this->say("report incalculable invoices where the tax can not be connected to a payment", array());?>:</div>
		<?php foreach($taxSummaryBreakdownByPaymentMethods->incalculableInvoices as $invoice):?>
			<div><?php echo $this->say("invoice number", array($invoice->id)) . " ({$this->say("sale id")}{$invoice->getSale()->data->id} {$this->shortdate($invoice->date)})";?></div>
				<?php foreach($invoice->getTaxes()->data as $tax):?>
					<div><?php echo "{$tax->tax_name}: {$this->money($tax->tax_value)} ({$this->percent($tax->tax_rate)} of {$this->money($tax->tax_basis)})";?></div>
				<?php endforeach;?>
		<?php endforeach;?>
		<?php endif;?>
	</div>

	<?php endif;?>
	
	<?php $sor = $trader->getSor($this->from, $this->to)->data;?>

		<?php if(count($sor)):?>
		<h3><?php echo $this->say('report sale or return items sold');?></h3>
		<table>

			<?php foreach($sor as $supplier):?>
			<tr class="bold">
				<td colspan = "5"><?php echo $supplier->suppliername;?>:</td>
			</tr>
			
			<?php foreach($supplier->items as $item):?>
			<tr>
				<td class="value"><?php echo $this->number($item->quantity);?></td>
				<td><?php echo $item->product->productCode;?></td>
				<td><?php echo $item->product->name;?></td>
				<td class="value"><?php echo $this->money($item->cost);?></td>
				<td class="value"><?php echo $this->money($item->total_costs);?></td>
			</tr>
			<?php endforeach;?>
			
			<tr class="bold">
				<td colspan = "4"></td>
				<td class="value"><?php echo $this->money($supplier->total_costs);?></td>
			</tr>
			<?php endforeach;?>

		</table>
		<?php endif;?>
		
		<div>
			<strong><?php echo $this->say('report stock per', array($this->long_datetime(new DateTime)));?></strong>
			<div><?php echo $this->say('report stock items count', array($this->number($trader->getStockItemsCount(true))));?></div>
			<div><?php echo $this->say('report stock products count', array($this->number($trader->getStockProductsCount(true))));?></div>
			<div><?php echo $this->say('report stock value', array($this->money($stockValue)));?></div>

			<?php if( $stockValue != $stockValueExSor ):?>
				<div><?php echo $this->say('report stock value excluding sor', array($this->money($stockValueExSor)));?></div>
			<?endif;?>

		</div>
	</span>
<?php 
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