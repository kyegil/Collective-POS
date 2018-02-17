<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
class GiftVoucher {

protected $hasLoaded = false;
protected $redemptionPayment; // Storage for the payment created by using this voucher 
protected $redemptionPaymentHasLoaded = false; //bool - True if the Payment object has been loaded
protected $redemptionPaymentId; // Id of the payment created by using this voucher
public $code; // string - The voucher's redemtion code
public $design = ""; // String - Design used for voucher
public $expires = null; // DateTime object Expiry date / time
public $id = 0;	//	integer - Identificator for this gift voucher as stored in the DB
public $issued; // DateTime object - Time of issue
public $issuingSale; // Sale object
public $mysqli; // object - The MySQLi connection
public $prepaymentholdingTrader; // Trader holding payment
public $redeemableForCash = true; // Boolean - Wether the voucher is redeemable for cash
public $redemptionSale; // Sale object
public $traders = array(); // Traders that accept this gift voucher
public $value; // Gift voucher value


function __construct($autoloadId = 0) {
	global $mysqliConnection;
	$this->mysqli = $mysqliConnection;
	$tp = $this->mysqli->table_prefix;
	
	if((int)$autoloadId) {
		$this->id = $autoloadId;
		if(!$this->load()->success) {
			unset($this->id);
		}
	}
}


// Check if this voucher has been spent
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		data	(boolean): True if the sale has been completed
function checkIfSpent() {
	if($this->id and !$this->hasLoaded) {
		$result = $this->load();
		if(!$result->success) {
			$result->data = true;
			return $result;
		}
	}

	return (object) array(
		'data'		=> (bool) $this->getPayment()->data,
		'success'	=> true
	);
}


// Checks wether a given voucher code is available or if it has been used before.
/****************************************/
//	$code:	(string) Voucher code to check.
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
function isCodeAvailable($code) {
	$tp = $this->mysqli->table_prefix;
	return (object) array(
		'success' => !$this->mysqli->arrayData(array(
			'source'		=> "{$tp}giftvouchers",
			'where'			=> "{$tp}giftvouchers.code = '$code' and redemption_payment_id is null"
		))->totalRows
	);
}


// Generates a suggested gift voucher code.
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful	
//		data (string): code suggestion
function generateCode() {
	$result = new stdClass;
	$result->success = true;
	while(!$this->isCodeAvailable($result->data = (strtoupper(dechex(rand(0,65535)) . "-" . dechex(rand(0,65535)) . "-" . dechex(rand(0,65535)) . "-" . dechex(rand(0,65535))))));
	return $result;
}


// Get the Payment created by the use of this voucher
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Payment object or null if the voucher has not been used
function getPayment() {
	if(!$this->id) {
		return (object) array(
			'success'	=> false,
			'msg'		=> "err GiftVoucher class getPayment() GiftVoucher has no Id"
		);
	}

	if(!$this->hasLoaded) {
		$this->load();
	}
	
	$result = (object) array(
		'success'	=> true,
		'data'		=> null
	);

	if($this->redemptionPaymentHasLoaded) {
		$result->data = $this->redemptionPayment;
		return $result;
	}
	$result->data = $this->redemptionPayment = new Payment($this->redemptionPaymentId);
	if(!$result->data->id) {
		$this->redemptionPaymentId = null;
		$this->redemptionPayment = null;
		$this->redemptionSale = null;
		$result->data = null;
		$this->save();
	}
	$this->redemptionPaymentHasLoaded = true;
	return $result;
}


// Loads voucher details from the DB
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
function load() {
	$tp = $this->mysqli->table_prefix;
	$result = new stdClass;
	
	$data = $this->mysqli->arrayData(array(
		'source'		=> "{$tp}giftvouchers",
		'where'			=> "{$tp}giftvouchers.id = '$this->id'"
	));

	if(!count($data->data)) {
		return (object)array('success' => false);
	}
	else {
		// assigning properties from the DB query
		$data = $data->data[0];
		$this->id = $data->id;
		$this->code = $data->code;
		$this->issued = new DateTime($data->issued, timezone_open('UTC'));
		$this->issuingSale = new Sale(array(
			'id'	=> $data->issuing_sale
		));
		$this->redemptionSale = ($data->redemption_sale ? new Sale(array(
			'id'	=> $data->redemption_sale
		)) : null);
		$this->redemptionPaymentId = $data->redemption_payment_id;
		$this->redeemableForCash = (bool)$data->redeemable_for_cash;
		$this->expires = ($data->expires ? new DateTime($data->expires, timezone_open('UTC')) : null);
		$this->value = $data->value;
		$this->prepaymentholdingTrader = new Trader($data->prepayment_holding_trader);
		$this->design = $data->design;

		$data = $this->mysqli->arrayData(array(
			'source'		=> "{$tp}giftvoucher_traders",
			'where'			=> "{$tp}giftvoucher_traders.giftvoucher = '$this->id'"
		));
		$this->traders = array();
		foreach($data->data as $trader) {
			$this->traders[] = new Trader($trader->trader);
		}

		$this->hasLoaded = true;
		return (object)array('success' => true);
	}
}


// Loads voucher from given voucher code
/****************************************/
//	$code:	(string) Voucher code to check.
//	$used:	(bool) Also load used vouchers
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether voucher with given code exists		
function loadByVoucherCode($code, $used = false) {
	$tp = $this->mysqli->table_prefix;
	
	$data = $this->mysqli->arrayData(array(
		'source'		=> "{$tp}giftvouchers",
		'where'			=> "{$tp}giftvouchers.code = '$code'" . (!$used ? " AND {$tp}giftvouchers.redemption_payment_id IS NULL" : ""),
		'orderfields'	=> "{$tp}giftvouchers.redemption_payment_id ASC",
		'returnQuery'	=> true
	));

	if(!count($data->data)) {
		return (object) array(
			'success' => false,
			'msg' => "err class GiftVoucher loadByVoucherCode() no voucher with this code",
		);
	}
	$this->id = $data->data[0]->id;
	$this->load();
	return (object)array('success' => true);
}


// Saves voucher details to DB
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
function save() {
	$tp = $this->mysqli->table_prefix;
	if(!$this->issuingSale or !$this->code) {
		return (object) array('success' => false);
	}

	if($this->redemptionSale) {
		$redemptionSaleId = $this->redemptionSale->id;
	}
	else {
		$redemptionSaleId = null;
	}
	if($this->expires) {
		$expires = $this->expires->format('Y-m-d H:i:s');
	}

	$edit = $this->mysqli->saveToDb(array(
		'returnQuery' => true,
		'id'		=> $this->id,
		'table'		=> "{$tp}giftvouchers",
		($this->id ? 'update' : 'insert')	=> true,
		'where'		=>	($this->id ? "id = '{$this->id}'" : ""),
		'fields'	=> array(
			'issued'					=> $this->issued->format('Y-m-d H:i:s'),
			'issuing_sale'				=> $this->issuingSale->id,
			'code'						=> $this->code,
			'redemption_payment_id'		=> $this->redemptionPaymentId,
			'redemption_sale'			=> $redemptionSaleId,
			'redeemable_for_cash'		=> $this->redeemableForCash,
			'expires'					=> $expires,
			'value'						=> $this->value,
			'prepayment_holding_trader'	=> $this->prepaymentholdingTrader->id,
			'design'					=> $this->design
		)
	));

	if($edit->success) {
		$this->id = $edit->id;
		$this->mysqli->query("DELETE FROM {$tp}giftvoucher_traders WHERE giftvoucher = '$this->id'");
		foreach($this->traders as $trader) {
			$this->mysqli->saveToDb(array(
				'table'		=> "{$tp}giftvoucher_traders",
				'insert'	=> true,
				'fields'	=> array(
					'trader'				=> $trader->id,
					'giftvoucher'			=> $this->id
				)
			));
		}
	}
	$this->load();
	return (object)array('success' => true);
}


// Spend the voucher
/****************************************/
//	$payment:	Payment object
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
function spend($payment) {
	if(!$this->id) {
		return (object) array(
			'success'	=> false,
			'msg'		=> "err GiftVoucher class getPayment() GiftVoucher has no Id"
		);
	}
	
	$check = $this->checkIfSpent();
	if(!$check->success) {
		return (object) array(
			'success'	=> false,
			'msg'		=> "err GiftVoucher class spend() Could not determine if voucher has been used"
		);
	}
	if($check->data) {
		return (object) array(
			'success'	=> false,
			'msg'		=> "err GiftVoucher class spend() GiftVoucher has already been used"
		);
	}

	if(!($payment instanceof Payment)) {
		if(!(int)$payment) {
			return (object) array(
				'success'	=> false,
				'msg'		=> "err GiftVoucher class spend() no payment given"
			);
		}
		else {
			$payment = new Payment($payment);
		}
	}

	if($payment->paymentMethod->id != -2) {
		return (object) array(
			'success'	=> false,
			'msg'		=> "err GiftVoucher class spend() Payment is not by giftvoucher"
		);
	}

	$this->redemptionPayment = $payment;
	$this->redemptionPaymentId = $payment->id;
	$this->redemptionSale = $payment->getSale()->data;
	$this->redemptionPaymentHasLoaded = true;
	return $this->save();
}


}

?>