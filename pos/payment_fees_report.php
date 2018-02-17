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
	$this->title = $this->say('report shared payment fees summary');
}

function script() {
	$tp = $this->mysqli->table_prefix;

}

function design() {
	$summary = (bool)$_GET['summary'];
	$daily = @$_GET['daily'] or @$_POST['daily'];
	$a = $result = $this->getPaymentCharges(array(
		'from' => $this->from,
		'to' => $this->to
	));
	$b = array();

	if($daily) {
		foreach($a as $method) {
			foreach($method->traders as $trader) {
				foreach($trader->payments as $payment) {
					settype(
						$b[$method->id][$payment->payment->timestamp->format('Y-m-d')]
						[$trader->trader->id]['amount'],
						'string'
					);
					settype(
						$b[$method->id][$payment->payment->timestamp->format('Y-m-d')]
						[$trader->trader->id]['cost'],
						'string'
					);
					
					$b[$method->id][$payment->payment->timestamp->format('Y-m-d')]
					[$trader->trader->id]['amount']
					= bcadd(
						$b[$method->id][$payment->payment->timestamp->format('Y-m-d')]
						[$trader->trader->id]['amount'],
						$payment->amount,
						6
					);
					$b[$method->id][$payment->payment->timestamp->format('Y-m-d')]
					[$trader->trader->id]['cost']
					= bcadd(
						$b[$method->id][$payment->payment->timestamp->format('Y-m-d')]
						[$trader->trader->id]['cost'],
						$payment->cost,
						6
					);
				}
			}
			ksort($b[$method->id]);
		}
	}
?>
	<span class="dataload">
	<h1><?=$this->say('report shared payment fees summary')?>
		<?if($this->from):?><?=$this->shortdate($this->from)?><?endif?>
		<?if($this->to and ($this->to !== $this->from)):?> - <?=$this->shortdate($this->to)?><?endif?>
	</h1>
	
	<h2><?=$this->preferences->pos_name?></h2>
	<h3><?=$this->say('report as per x', array($this->long_datetime(date_create())))?></h3>

	<table>
		<tr>
			<th><?=$this->say("payment method")?></th>
			<th>&nbsp;</th>
			<th><?=$this->say('total')?></th>
			<th><?=$this->say('report payment charges')?></th>
		</tr>
		<?foreach($result as $method):?>
			<?$fee = array();?>
			<?if($method->transactionChargeFixed != 0) $fee[] = $this->money($method->transactionChargeFixed);?>
			<?if($method->transactionChargeRate != 0) $fee[] = $this->percent($method->transactionChargeRate);?>
			<tr>
				<td colspan="2" class="bold"><?=$method->name?> (<?=(count($fee) ? implode(' + ', $fee) : $this->say('report no charges'))?>)</td>
				<td colspan="2" class="value"></td>
			</tr>
			<?if($daily):?>
				<?foreach($b[$method->id] as $date => $c):?>
					<tr>
						<td colspan="4">&nbsp;</td>
					</tr>
					<tr>
						<td colspan="2" class="bold"><?=$this->shortdate(date_create($date))?></td>
						<td colspan="2" class="value"></td>
					</tr>
					<?$amount = $cost = 0?>
					<?foreach($c as $trader_id => $total):?>
						<?$trader = New Trader($trader_id);?>
						<tr>
							<td><?=$trader->name?></td>
							<td>&nbsp;</td>
							<td class="value"><?=$this->money($total['amount'])?></td>
							<td class="value"><?=$this->money($total['cost'])?></td>
						</tr>
						<?$amount = bcadd($amount, $total['amount'], 6); $cost = bcadd($cost, $total['cost'], 6)?>
					<?endforeach?>
					<tr class="bold">
						<td colspan="2">&nbsp;</td>
						<td class="value"><?=$this->money($amount)?></td>
						<td class="value"><?=$this->money($cost)?></td>
					</tr>
				<?endforeach?>
			<?else:?>
				<?foreach($method->traders as $trader_id => $share):?>
					<tr>
						<td><?=$share->trader->name?></td>
						<td>&nbsp;</td>
					<?if(!$summary):?>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
					<?else:?>
						<td class="value"><??><?=$this->money($share->sum)?></td>
						<td class="value"><?=$this->money($share->costs)?></td>
					<?endif?>
					</tr>
					<?if(!$summary):?>
					<?foreach($share->payments as $payment):?>
						<tr>
							<td style="padding-left: 20px;"><?=$this->say('report sale x', array($payment->sale->id))?></td>
							<td><?=$payment->quantity?></td>
							<td class="value"><?=$this->money($payment->amount)?></td>
							<td class="value"><?=$this->money($payment->cost)?></td>
						</tr>
					<?endforeach?>
					<tr class="bold">
						<td colspan="2">&nbsp;</td>
						<td class="value"><?=$this->money($share->sum)?></td>
						<td class="value"><?=$this->money($share->cost)?></td>
					</tr>
					<?endif?>
				<?endforeach?>
			<?endif?>
			<tr class="bold summary">
				<td>&nbsp;</td>
				<td><?=$this->say('report x transactions', array($method->quantity))?></td>
				<td class="value"><?=$this->money($method->sum)?></td>
				<td class="value"><?=$this->money($method->cost)?></td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
			</tr>
		<?endforeach?>
	</table>
</span>
<?
}

function request($data = "") {
	$tp = $this->mysqli->table_prefix;
	switch ($data) {

	default:
		echo json_encode($this->main_data);
		break;
	}
}

function receive($form) {
	switch ($form) {

	default:
		echo json_encode($result);
		break;
	}
}

}
?>