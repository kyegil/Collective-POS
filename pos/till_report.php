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
	$this->title = $this->say("till content report");
}

function script() {}

function design() {
	$transactions = $this->till->recent(false, 0, $this->from, $this->to->setTime(23, 59, 59));
	$transactions = array_reverse($transactions);
?>
	<h1><?=$this->say("till transactions report")?>: <?=$this->shortdate($this->from)?> - <?=$this->shortdate($this->to)?></h1>
	<h2><?=$this->preferences->pos_name?></h2>
	<table style="width: 100%;">
	<?foreach($transactions as $transaction):?>
		<?$bg = !$bg;?>
		<tr style="background-color: <?=($bg ? "#cccccc" : "none")?>;">
			<td rowspan="<?=1 + count($transaction->accounts) + (bool)$transaction->difference?>" style="width: 120px;"><?=$this->datetime($transaction->time)?></td>
			<td style="font-weight: bold; width:200px;"><?=$this->say("till {$transaction->action}")?></td>
			<td rowspan="<?=1 + count($transaction->accounts) + (bool)$transaction->difference?>"><?=addslashes($transaction->note)?></td>
			<td style="font-weight: bold; " class="value"><?=($transaction->adjustment ? $this->money($transaction->adjustment): "")?></td>
			<td style="font-weight: bold; " class="value"><?=$this->money($transaction->content)?></td>
			<td rowspan="<?=1 + count($transaction->accounts) + (bool)$transaction->difference?>"><?=$this->get_user($transaction->recorder)?></td>
		</tr>
		<?foreach($transaction->accounts as $account):?>
		<tr style="background-color: <?=($bg ? "#cccccc" : "none")?>;">
			<td><?=$account->trader->name?></td>
			<td class="value"><?=($account->adjustment ? $this->money($account->adjustment) : "")?></td>
			<td class="value"><?=$this->money($account->balance)?></td>
		</tr>
		<?endforeach;?>
		<?if($transaction->difference):?>
		<tr style="background-color: <?=($bg ? "#cccccc" : "none")?>;">
			<td><?=$this->say('till discrepancy')?></td>
			<td>&nbsp;</td>
			<td class="value"><?=$this->money($transaction->difference)?></td>
		</tr>
		<?endif;?>
	<?endforeach;?>
	</table>
<?
}

function request($data = "") {
	$tp = $this->mysqli->table_prefix;
	switch ($data) {
		case "till_content":
			echo "<table><tr><td colspan =\"2\" style=\"font-size:24px;\">" . $this->say('till content x', array($this->money($this->till->content))). "</td></tr>";
			foreach($this->till->getTraders() as $trader) {
				echo "<tr><td>{$trader->name}:</td><td style=\"text-align: right; padding-left: 5px;\">" . $this->money($this->till->getBalance($trader->id)) . "</td></tr>";
			}
			echo "</table>";
			break;
		default:
			echo json_encode($this->main_data);
	}
}

function receive($form) {
	//
}

function amend($data = "") {
	//
}

}
?>