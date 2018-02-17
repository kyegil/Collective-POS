<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/

class Till {

public $id; //	Identificator for this till as stored in the DB
public $entry; //	Identificator for this till entry as stored in the DB
public $content; //	Current till content
protected $mysqli; // The MySQLi connection


function __construct($id) {
	global $mysqliConnection;
	$this->mysqli = $mysqliConnection;
	$this->id = (int)$id;
	$this->load();
}


// Allocate ownership of till difference
/****************************************/
//	$recorderUserId:	(int) Id of user allocating
//	$trader_id:			(int) Trader to allocate to
//	$sum:				(float) Amount to allocate
//	$note:				(string) Note submitted with the clearing of the till
//	--------------------------------------
function allocateDifference($recorderUserId, $trader_id, $sum = 0, $note = "") {
	$tp = $this->mysqli->table_prefix;
	if(!is_numeric($sum)) {
		settype($sum, 'float');
	}

	$current = $this->mysqli->arrayData(array(
		'source' => "{$tp}cash_register_accounts",
		'where' => "cash_register_entry = '{$this->entry}'"
	));
	foreach($current->data as $values) {
		$new[$values->trader] = $values;
		$new[$values->trader]->adjustment = 0;
	}
	$new[$trader_id]->trader = $trader_id;
	$new[$trader_id]->adjustment = $sum;
	$new[$trader_id]->balance = bcadd($this->getBalance($trader_id), $sum, 6);

	$entry = $this->mysqli->saveToDb(array(
		'table' => "{$tp}cash_register",
		'insert' => true,
		'fields' => array(
			'till' => $this->id,
			'action' => 'allocation',
			'adjustment' => 0,
			'content' => $this->content,
			'recorder' => (int)$recorderUserId,
			'note' => $note
		)
	));

	foreach($new as $values) {
		if($entry->success) {
			$detail = $this->mysqli->saveToDb(array(
				'table' => "{$tp}cash_register_accounts",
				'insert' => true,
				'fields' => array(
					'cash_register_entry' => $entry->id,
					'trader' => $values->trader,
					'adjustment' => $values->adjustment,
					'balance' => $values->balance
				)
			));
		}
		if(!$entry->success = $detail->success) {
			$entry->msg = $detail->msg;
		}
	}
	return $entry;
}


// Clear till content
/****************************************/
//	$recorder:	(int) User that registers the deposit
//	$note:		(string) Note submitted with the clearing of the till
//	$action:	(string) Description of action. Defaults to 'reset'.
//	--------------------------------------
function clear($recorder, $note = "", $action = "reset") {
	$tp = 	$this->mysqli->table_prefix;
	$this->mysqli->saveToDb(array(
		'table' => "{$tp}cash_register",
		'insert' => true,
		'fields' => array(
			'till' => $this->id,
			'action' => $action,
			'adjustment' => -$this->content,
			'content' => 0,
			'recorder' => $recorder,
			'note' => $note
		)
	));
	$this->load();
}


// Deposit money into the till
/****************************************/
//	$recorder:	(int) User that registers the deposit
//	$trader_id:	(int) Owner of the money deposited
//	$sum:		(float) Deposited amount
//	$note:		(string) Note submitted with the deposit
//	$action:	(string) Description of action. Defaults to 'deposit'.
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		id	(int): ID of the new till entry
function deposit($recorder, $trader_id, $sum, $note = "", $action = "deposit") {
	$tp = 	$this->mysqli->table_prefix;
	if(!is_numeric($sum)) {
		settype($sum, 'float');
	}
	$current = $this->mysqli->arrayData(array(
		'source' => "{$tp}cash_register_accounts",
		'where' => "cash_register_entry = '{$this->entry}'"
	));
	foreach($current->data as $values) {
		$new[$values->trader] = $values;
		$new[$values->trader]->adjustment = 0;
	}
	settype($new[$trader_id], 'object');
	$new[$trader_id]->trader = $trader_id;
	$new[$trader_id]->adjustment = $sum;
	$new[$trader_id]->balance = bcadd($this->getBalance($trader_id), $sum, 6);

	$entry = $this->mysqli->saveToDb(array(
		'table' => "{$tp}cash_register",
		'insert' => true,
		'fields' => array(
			'till' => $this->id,
			'sale' => 0,
			'action' => $action,
			'adjustment' => $sum,
			'content' => bcadd($this->content, $sum, 6),
			'recorder' => (int)$recorder,
			'note' => $note
		)
	));
	foreach($new as $values) {
		if($entry->success) {
			$detail = $this->mysqli->saveToDb(array(
				'table' => "{$tp}cash_register_accounts",
				'insert' => true,
				'fields' => array(
					'cash_register_entry' => $entry->id,
					'trader' => $values->trader,
					'adjustment' => $values->adjustment,
					'balance' => $values->balance
				)
			));
		}
		if(!$entry->success = $detail->success) {
			$entry->msg = $detail->msg;
		}
	}
	$this->load();
	return $entry;
}


// Get till balance for one trader
/****************************************/
//	$trader_id:	(int) Till entry
//	--------------------------------------
//	return: (float) Spesified Traders till balance
function getBalance($trader_id) {
	$tp = 	$this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}cash_register_accounts",
		'fields' => 'balance',
		'where' => "cash_register_entry = '{$this->entry}' AND trader = '$trader_id'"
	));
	return $result->data[0]->balance;
}


// Get difference between traders balances and actual till content
/****************************************/
//	--------------------------------------
//	return: (float) Spesified Traders till balance
function getDifference() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}cash_register_accounts",
		'fields' => "{$this->content} - SUM(balance) AS difference",
		'where' => "cash_register_entry = '{$this->entry}'"
	));
	return $result->data[0]->difference;
}


// Which traders have money in the till
/****************************************/
//	$entry:	(int) Till entry
//	--------------------------------------
//	return: array of Trader objects
function getTraders($entry = 0) {
	$tp = 	$this->mysqli->table_prefix;
	if(!$entry) $entry = $this->entry;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}cash_register_accounts",
		'fields' => 'trader',
		'where' => "cash_register_entry = '$entry'",
		'flat' => true
	));
	foreach($result->data as $element => $id) {
		$result->data[$element] = new Trader($id);
	}
	return $result->data;
}


// Load the till
/****************************************/
function load() {
	$tp = 	$this->mysqli->table_prefix;
	$this->content = $this->mysqli->arrayData(array(
		'source' => "{$tp}cash_register",
		'where' => "till = '$this->id'",
		'orderfields' => 'id DESC',
		'limit' => '0, 1'
	));
	$this->entry = (int)$this->content->data[0]->id;
	$this->content = $this->content->data[0]->content;
}


// Get recent till movements
/****************************************/
//	since_cleared: (bool) Only return entries since last time till was cleared
//	limit: (int) Maximum number of entries to return
//	$since: DateTime object Only return entries since this time
//	$until: DateTime object Only return entries until this time
//	--------------------------------------
//	return: array of stdClass objects with the following properties:
//		id: (int) id as stored in database
//		action: (string) Either 'count', 'payment', 'deposit', 'withdrawal' or 'reset'
//		time: (DateTime object) Time of transaction
//		sale: (int) The sale from which payment entry occurs
//		adjustment: (float): The amount added to or taken from the till
//		content: (float) new till content after transaction
//		recorder: (int) id of logged in user
//		note: (string) Explanation following the transaction
//		sum: (float) Sum of all traders balances
//		difference: (float) Difference between actual till content and traders balances
//		accounts: array of trader accounts with the following keys:
//			trader: Trader object
//			adjustment: (float): The amount added to or taken from this traders account
//			balance: (float) traders balance after transaction
function recent($since_cleared = false, $limit = 0, $since = null, $until = null) {
	if($since) {
		$since->setTimezone(new DateTimeZone('UTC'));
	}
	if($until) {
		$until->setTimezone(new DateTimeZone('UTC'));
	}
	$tp = $this->mysqli->table_prefix;
	$query ['source'] = "{$tp}cash_register LEFT JOIN {$tp}cash_register_accounts ON {$tp}cash_register.id = {$tp}cash_register_accounts.cash_register_entry";
	$query ['fields'] = "{$tp}cash_register.id, {$tp}cash_register.time, {$tp}cash_register.action, {$tp}cash_register.sale, {$tp}cash_register.adjustment, {$tp}cash_register.content, {$tp}cash_register.note, {$tp}cash_register.recorder, SUM({$tp}cash_register_accounts.balance) AS sum, {$tp}cash_register.content - SUM({$tp}cash_register_accounts.balance) AS difference";
	$query ['groupfields'] = "{$tp}cash_register.id";
	$query ['orderfields'] = "time DESC";
	
	$query['where'] = "till = " . (int)$this->id . "\n";
	if($since) {
		$query['where'] .= " AND time >= '{$since->format('Y-m-d H:i:s')}'";
	}
	if($until) {
		$query['where'] .= " AND time <= '{$until->format('Y-m-d H:i:s')}'";
	}
	if($limit) {
		$query['limit'] = "$limit";
	}
	$entries = $this->mysqli->arrayData($query);
	$reset = null;
	if($since_cleared) {
		$entries->data = array_slice($entries->data, 0, $reset);
	}
	foreach($entries->data as $index => $entry) {
		$entries->data[$index]->time = new DateTime($entry->time, timezone_open('UTC'));
		$details = $this->mysqli->arrayData(array(
			'source' => "{$tp}cash_register_accounts",
			'fields' => 'trader, adjustment, balance',
			'where' => "cash_register_entry = '{$entry->id}'"
		));
		$entries->data[$index]->accounts = $details->data;
		foreach($details->data as $accindex => $account) {
			$entries->data[$index]->accounts[$accindex]->trader = new Trader($account->trader);
		}
		if($entry->action == "reset" and !$reset) {
			$reset = $index;
		}
	}
	return $entries->data;
}


// Submit the actual content of the till
/****************************************/
//	$recorder:	(int) User that submits the count
//	$sum:		(float) The counted content of the till
//	$action:	(string) Description of action. Defaults to 'count'.
//	$note:		(string) Note submitted with the count
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		id	(int): ID of the new till entry
function recount($recorder, $sum = 0, $note = "", $action = "count") {
	$tp = 	$this->mysqli->table_prefix;
	if(!is_numeric($sum)) {
		settype($sum, 'float');
	}
	$current = $this->mysqli->arrayData(array(
		'source' => "{$tp}cash_register_accounts",
		'where' => "cash_register_entry = '{$this->entry}'"
	));

	$entry = $this->mysqli->saveToDb(array(
		'table' => "{$tp}cash_register",
		'insert' => true,
		'fields' => array(
			'till' => $this->id,
			'action' => $action,
			'adjustment' => bcsub($sum, $this->content, 6),
			'content' => $sum,
			'recorder' => (int)$recorder,
			'note' => $note
		)
	));
	
	if( !$entry->success ) {
		return $entry;
	}
	
	foreach($current->data as $values) {
		if($entry->success) {
			$detail = $this->mysqli->saveToDb(array(
				'table' => "{$tp}cash_register_accounts",
				'insert' => true,
				'fields' => array(
					'cash_register_entry' => $entry->id,
					'trader' => $values->trader,
					'adjustment' => 0,
					'balance' => $values->balance
				)
			));
		}
		if(!$entry->success = $detail->success) {
			$entry->msg = $detail->msg;
		}
	}
	$this->load();
	return $entry;
}


// Put a sale in the till
/****************************************/
//	$recorder:	(int) User that submits the count
//	$sale:		(int) Sale ID
//	$portions:	array of associated arrays/objects with the following possible keys/properties:
//		trader:	(int) Trader ID
//		sum:	(float) Paid amount
//	$note:		(string) Note submitted with the sale
//	$action:	(string) Description of action. Defaults to 'payment'.
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		id	(int): ID of the new till entry
function sale($recorder, $sale = 0, $portions = array(), $note = "", $action = "payment") {
	$tp = 	$this->mysqli->table_prefix;
	settype($portions, 'array');

	$current = $this->mysqli->arrayData(array(
		'source' => "{$tp}cash_register_accounts",
		'where' => "cash_register_entry = '{$this->entry}'"
	));
	foreach($current->data as $values) {
		$new[$values->trader] = $values;
		$new[$values->trader]->adjustment = 0;
	}
	$sum = 0;
	foreach($portions as $portion) {
		settype($portion, 'object');

		settype($new[$portion->trader], 'object');
		$new[$portion->trader]->trader = $portion->trader;
		$new[$portion->trader]->adjustment = $portion->sum;
		$new[$portion->trader]->balance = bcadd($new[$portion->trader]->balance, $portion->sum, 6);
		$sum = bcadd($sum, $portion->sum, 6);
	}

	$entry = $this->mysqli->saveToDb(array(
		'table' => "{$tp}cash_register",
		'insert' => true,
		'fields' => array(
			'till' => $this->id,
			'sale' => (int)$sale,
			'action' => $action,
			'adjustment' => $sum,
			'content' => bcadd($this->content, $sum, 6),
			'recorder' => (int)$recorder,
			'note' => $note
		)
	));
	foreach($new as $values) {
		if($entry->success) {
			$detail = $this->mysqli->saveToDb(array(
				'table' => "{$tp}cash_register_accounts",
				'insert' => true,
				'fields' => array(
					'cash_register_entry' => $entry->id,
					'trader' => $values->trader,
					'adjustment' => $values->adjustment,
					'balance' => $values->balance
				)
			));
		}
		if(!$entry->success = $detail->success) {
			$entry->msg = $detail->msg;
		}
	}
	$this->load();
	return $entry;
}


// Withdraw money from the till
/****************************************/
//	$recorder:	(int) User that registers the deposit
//	$trader_id:	(int) Owner of the money withdrawn
//	$sum:		(float) Withdrawn amount
//	$note:		(string) Note submitted with the withdrawal
//	$action:	(string) Description of action. Defaults to 'withdrawal'.
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		id	(int): ID of the new till entry
function withdraw($recorder, $trader_id, $sum, $note = "", $action = "withdrawal") {
	$tp = 	$this->mysqli->table_prefix;
	if(!is_numeric($sum)) {
		settype($sum, 'float');
	}
	$current = $this->mysqli->arrayData(array(
		'source' => "{$tp}cash_register_accounts",
		'where' => "cash_register_entry = '{$this->entry}'"
	));
	foreach($current->data as $values) {
		$new[$values->trader] = $values;
		$new[$values->trader]->adjustment = 0;
	}
	settype($new[$trader_id], 'object');
	$new[$trader_id]->trader = $trader_id;
	$new[$trader_id]->adjustment = -$sum;
	$new[$trader_id]->balance = bcsub($this->getBalance($trader_id), $sum, 6);

	$entry = $this->mysqli->saveToDb(array(
		'table' => "{$tp}cash_register",
		'insert' => true,
		'fields' => array(
			'till' => $this->id,
			'sale' => 0,
			'action' => $action,
			'adjustment' => -$sum,
			'content' => bcsub($this->content, $sum, 6),
			'recorder' => (int)$recorder,
			'note' => $note
		)
	));
	foreach($new as $values) {
		if($entry->success) {
			$detail = $this->mysqli->saveToDb(array(
				'table' => "{$tp}cash_register_accounts",
				'insert' => true,
				'fields' => array(
					'cash_register_entry' => $entry->id,
					'trader' => $values->trader,
					'adjustment' => $values->adjustment,
					'balance' => $values->balance
				)
			));
		}
		if(!$entry->success = $detail->success) {
			$entry->msg = $detail->msg;
		}
	}
	$this->load();
	return $entry;
}


}

?>