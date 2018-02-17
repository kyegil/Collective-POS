<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('No access!<br />Check your URI.');
$this->setLocale($this->preferences->invoice_language);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:ext="http://www.extjs.com" xml:lang="<?=$this->say['locale']?>" lang="<?=$this->say['locale']?>">

<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
	<title><?=$this->say['receipt']->format(array())?></title>
	<style>
body {font-family: monospace; font-size: 10px; text-align: center; width: 80mm;}
h1 {font-size: 1.5em;}
h2 {font-size: 1.3em;}
table {width: 100%; margin-bottom: 40px;}
p {text-align: left;}
td {text-align: left; vertical-align: top;}
td.value {text-align: right; vertical-align: bottom;}
#bold {font-weight: bold;}
	</style>
</head>

<body>
	<h1><?=$this->preferences->pos_name?></h1>
	<h2><?=$this->say('your receipt', array())?></h2>

	<h3>* * *</h3>
	<?foreach($this->current_sale->getInvoices()->data as $invoice):?>
		<p><?=$invoice->getTrader()->data->name?><br />
		<?=nl2br($invoice->getTrader()->data->address)?></p>
		<?if($invoice->getTrader()->data->legal_identifier):?>
			<p><?=$invoice->getTrader()->data->legal_identifier?></p>
		<?endif?>
		
		<?if($invoice->total < 0):?>
			<h3><?=$this->say('credit note number', array($invoice->number))?>&nbsp;<?=$this->say('date x', array($this->shortdate($invoice->date)))?></h3>
		<?else:?>
			<h3><?=$this->say('invoice number', array($invoice->number))?>&nbsp;<?=$this->say('date x', array($this->shortdate($invoice->date)))?></h3>
		<?endif?>
		
		<table><tbody>
		<?foreach($invoice->getItems()->data as $item):?>
			<tr>
				<td><?=$item->quantity . ($item->unit ? " {$item->unit}" : "")?></td>
				<td><?=$item->description . ($invoice->taxInvoice ? $this->footnote($item->tax) : "")?></td>
				<td class="value"><?=$this->money($item->pricePer)?></td>
				<td class="value"><?=$this->money($item->quantity * $item->pricePer)?></td>
			</tr>

			<?if((float)$item->discount):?><tr>
				<td>&nbsp;</td>
				<td><?=$this->say('discount percent', array($this->percent((float)$item->discount)))?></td>
				<td>&nbsp;</td>
				<td class="value"><?=$this->money((-1) * $item->discount * $item->quantity * $item->pricePer)?></td>
			</tr><?endif?>
		<?endforeach?>

		<?if(count($invoice->getDiscounts()->data)):?>
			<tr id="bold">
				<td colspan="4">&nbsp;</td>
			</tr>
			<tr id="bold">
				<td colspan="4"><?=$this->say['discounts overall']->format(array())?></td>
			</tr>
		<?foreach($invoice->getDiscounts()->data as $discount):?>
			<tr>
				<td colspan="3"><?=($discount->discountDescription ? $discount->discountDescription : $this->say('discount'))?></td>
				<td class="value"><?=$this->money(-1 * $discount->effectiveValue)?></td>
			</tr>
		<?endforeach?>
		<?endif?>

			<tr>
				<td colspan="4">&nbsp;</td>
			</tr>
			<tr id="bold">
				<td colspan="3"><?=$this->say('invoice total', array())?></td>
				<td class="value"><?=$this->money($invoice->total)?></td>
			</tr>

		<?if($invoice->taxInvoice):?>
			<tr>
				<td colspan="4">&nbsp;</td>
			</tr>
			<tr id="bold">
				<td colspan="4"><?=$this->say['vat summary']->format(array())?></td>
			</tr>
		<?foreach($invoice->getTaxes()->data as $tax):?>
			<tr>
				<td><?=$this->footnote($tax->tax_id)?></td>
				<td colspan="2"><?="{$tax->tax_name}: " . ((float)$tax->tax_rate ? ($this->percent($tax->tax_rate) . " * "): "") . $this->money($tax->tax_basis)?></td>
				<td class="value"><?=$this->money($tax->tax_value)?></td>
			</tr>
		<?endforeach?>
		<?endif?>
		</tbody></table>
	<h3>* * *</h3>
	<?endforeach?>
	<table><tbody>
			<tr id="bold">
				<td colspan="2"><?=$this->say['payments']->format(array())?>:</td>
			</tr>
	<?foreach($this->current_sale->getPayments()->data as $payment):?>
			<tr>
				<td><?=$payment->paymentGroup?></td>
				<td class="value"><?=$this->money($payment->amount)?></td>
			</tr>
	<?endforeach?>
	</tbody></table>

	<script type="text/javascript">
		window.print();
	</script>
</body>
</html>
