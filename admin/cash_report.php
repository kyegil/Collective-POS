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
	$this->title = $this->say('cash');
}

function script() {
	$tp = $this->mysqli->table_prefix;
	$trader = new Trader($_GET['trader']);
}

function design() {
	$tp = $this->mysqli->table_prefix;
	$trader = new Trader($_GET['trader']);
	$statement = $trader->getTillStatement($this->till->id, $this->from, $this->to->setTime(23, 59, 59), true);
	$statement->transactions = array_reverse($statement->transactions);

	echo "<span class=\"dataload\"><h1>" . $this->say('till till transactions', array()) . " ";
	if($this->from){
		echo $this->shortdate($this->from);
	}
	if($this->to and ($this->to !== $this-from)){
		echo " - " . $this->shortdate($this->to);
	}
	echo "</h1>\n";
?>
	<h2><?=$trader->name?></h2>

	<p>
		<?=$this->say('report start balance x', array($this->money($statement->start_balance)))?>
	</p>
	<p>
		<?=$this->say('report end balance x', array($this->money($statement->end_balance)))?>
	</p>
	<p>
		<?=$this->say('report total sum deposits x', array($this->money($statement->deposits)))?>
	</p>
	<p>
		<?=$this->say('report total sum withdrawals x', array($this->money($statement->withdrawals)))?>
	</p>
	<p>
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