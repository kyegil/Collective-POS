<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
class PaymentMethod {

protected $mysqli; // object - The MySQLi connection
protected $hasLoaded = false;
public $id;	//	integer - Identificator for this payment method
public $name; // string - Name of this payment method
public $sortorder; // integer - Sorting order
public $paymentGroup; // string - The payment group which this payment method belongs to
public $enabled = false; // boolean - If this payment method should be available
public $allowPayments = true; // boolean - If this method can be used for paying
public $allowRefunds = true; // boolean - If this method can be used for making refunds
public $transactionChargeFixed = 0; // number - Fixed cost per transaction
public $transactionChargeRate = 0; // number - Transaction rate cost
public $colour; // string - clour representing this payment method
public $config; // stdClass object - Extra parameters:
                /****************************************/
                //	$config parameters are flexible and defines the payment method
                //	The following properties are currently in use
                //
                //		calculatedValue: (bool) Indicates that value is auto-calculated and no payment value input is required
                //		paymentParams (req): (array) of stdClass objects defining payment parameters:
                //			name: (req) (string) Parameter name
                //			type:	(string) Parameter type (text/number/bool/date/int). Defaults to text
                //			value: (mixed) Default parameter value
                //			required: (bool) Wether parameter value is required
                //			extFields: (object) Ext JS field config object
                //				xclass: (req) Ext JS class definition
                //				(others): see Ext JS documentation
                //			validator: (string) ref to value validator


    /**
     * PaymentMethod constructor.
     * @param int|string $autoloadId
     */
    public function __construct($autoloadId = null) {
	global $mysqliConnection;
	$this->mysqli = $mysqliConnection;
	$this->config = new stdClass;
	$this->config->paymentParams = array();
	$tp = $this->mysqli->table_prefix;
	
	if($autoloadId != null) {
		$this->id = $autoloadId;
		if(!$this->load()->success) {
			unset($this->id);
		}
	}
}


/**
 * Loads payment method from the DB
 *
 * @return object|stdClass
 */
public function load() {
	$tp = $this->mysqli->table_prefix;
	$result = new stdClass;
	
	$result = $this->mysqli->arrayData(array(
		'source'		=> "{$tp}payment_methods",
		'where'			=> "{$tp}payment_methods.id = '$this->id'"
	));

	if(!count($result->data)) {
		$result->success = false;
	}
	else {
		// assigning properties from the DB query

		$this->name						= $result->data[0]->name;
		$this->sortorder				= (int)$result->data[0]->sortorder;
		$this->paymentGroup				= $result->data[0]->paymentGroup;
		$this->enabled					= (bool)$result->data[0]->enabled;
		$this->allowPayments			= (bool)$result->data[0]->allowPayments;
		$this->allowRefunds				= (bool)$result->data[0]->allowRefunds;
		$this->transactionChargeFixed	= $result->data[0]->transactionChargeFixed;
		$this->transactionChargeRate	= $result->data[0]->transactionChargeRate;
		$this->colour					= $result->data[0]->colour;
		if(!$this->config = json_decode($result->data[0]->config)) {
			return (object)array(
				'success' => false,
				'msg' => "err PaymentMethod malformatted config settings"
			);
		}

		return $result;
	}
}


/**
 * Saves payment method to DB
 *
 * @return stdClass
 *      success (boolean): wether operation was successful
 * @throws Exception
 */
public function save() {
	$tp = $this->mysqli->table_prefix;
	if(!$this->name) {
		return (object)array(
			'success' => false,
			'msg' => "err PaymentMethod class payment method must have name"
		);
	}

	if(!$this->paymentGroup) {
		$this->paymentGroup = $this->name;
	}
	if(!(int)$this->sortorder) {
		$this->sortorder = (int)$this->mysqli->arrayData(array(
			'source'		=> "{$tp}payment_methods",
			'fields'			=> "MAX({$tp}payment_methods.sortorder)+1 AS sortorder"
		))->data[0]->sortorder;
	}
	else if ($this->mysqli->arrayData(array(
		'source'		=> "{$tp}payment_methods",
		'where'			=> "sortorder = '$this->sortorder' AND id != '{$this->id}'"
	))->totalRows) {
		$this->mysqli->query("
			UPDATE {$tp}payment_methods
			SET sortorder = sortorder+1
			WHERE sortorder >= '$this->sortorder'
		");
	}

	$edit = $this->mysqli->saveToDb(array(
		'id'		=> $this->id,
		'table'		=> "{$tp}payment_methods",
		($this->id ? 'update' : 'insert')	=> true,
		'where'		=>	($this->id ? "id = '{$this->id}'" : ""),
		'fields'	=> array(
			'name'						=> $this->name,
			'sortorder'					=> $this->sortorder,
			'paymentGroup'				=> $this->paymentGroup,
			'enabled'					=> (int)$this->enabled,
			'allowPayments'				=> (int)$this->allowPayments,
			'allowRefunds'				=> (int)$this->allowRefunds,
			'transactionChargeFixed'	=> $this->transactionChargeFixed,
			'transactionChargeRate'		=> $this->transactionChargeRate,
			'config'					=> json_encode($this->config),
			'colour'					=> $this->colour
		)
	));

	if($edit->success) {
		$this->id = $edit->id;
	}
	$this->load();
	return (object)array('success' => true);
}


/**
 * Extra function rounding of numbers
 *
 * @param $number
 * @param int $scale
 * @return string
 */
public function x_round($number, $scale = 0) {
	if($scale < 0) $scale = 0;
	$sign = '';
	if(bccomp('0', $number, 64) == 1) $sign = '-';
	$increment = $sign . '0.' . str_repeat('0', $scale) . '5';
	$number = bcadd($number, $increment, $scale+1);
	return bcadd($number, '0', $scale);
}


}


class Payment {

protected $mysqli; // object - The MySQLi connection
private $sale;	//	integer - Sale ID as stored in DB
public $id = 0;	//	integer - Identificator for this payment as saved in DB
public $timestamp; // DateTime object - Timestamp
public $amount; // number - Paid amount
public $paymentMethod; // Payment Method object
public $note; // string - note following the payment
public $registerer; // string - name of person who registered the payment
public $params; // stdClass object - Extra parameters 
/****************************************/
//	$params parameters are flexible as required by each payment method
//	The following parameters are currently defined
//
//	giftVoucherId: (int) ID of Gift voucher if payment by gift voucer
//	nonExchangeable (bool) Payment can not be exchanded for cash or other payment methods
//	limitedToTraders (array) array of Ids of traders who accept this payment


/**
 * Payment constructor.
 * @param int|string $autoloadId
 */
public function __construct($autoloadId = 0) {
	global $mysqliConnection;
	$this->mysqli = $mysqliConnection;
	$this->params = new stdClass;
	$this->timestamp = new DateTime(null, timezone_open('UTC'));
	$tp = $this->mysqli->table_prefix;
	
	if((int) $autoloadId) {
		$this->id = $autoloadId;
		if(!$this->load()->success) {
			unset($this->id);
		}
	}
}


// Check if this payment is accepted by certain trader
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether check was successful		
//		result: True if trader accepts this payment method
public function checkIfAcceptedBy($trader = null) {
	$result = new stdClass;
	if(!is_array(@$this->params->limitedToTraders)) {
		$result->success = true;
		$result->result = true;
		return $result;
	}
	if(is_a($trader, 'Trader')) {
		$trader = $trader->id;
	}
	settype($trader, 'integer');
	$result->success = true;
	$result->result = false;
	if(in_array($trader, $this->params->limitedToTraders)) {
		$result->result = true;
		return $result;
	}
	return $result;
}


// Gets payment charges for this payment
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		data: The cost occurred on this transaction
public function getCost() {
	$result = new stdClass;
	$result->success = true;
	if(!$this->hasLoaded) {
		$result = $this->load();
	}
	if($result->success) {
		$result->data = $this->paymentMethod->x_round(
			bcadd(
				$this->paymentMethod->transactionChargeFixed, 
				bcmul(
					$this->amount, 
					$this->paymentMethod->transactionChargeRate, 
					6
				), 
				6
			),
			5
		);
	}
	return $result;
}


// Gets sale
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		data: Sale object		
public function getSale() {
	$result = new stdClass;
	$result->success = true;
	if(!$this->hasLoaded) {
		$result = $this->load();
	}
	if($result->success) {
		$result->data = new Sale(array(
			'id' => $this->sale
		));
	}
	return $result;
}


// Get each traders share of this payment
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		distributed (number): Total spent amount from this payment
//		shares: stdClass object with properties corresponding to each traders id
//			$traderId: stdClass object with properties:
//				trader: Trader object
//				amount: The traders share of the payment
public function getShares() {
	$sale = $this->getSale();
	if(!$sale->success) {
		return $sale;
	}
	return $sale->data->getSharesOfPayment($this);
}


// Loads payment from the DB
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
public function load() {
	$tp = $this->mysqli->table_prefix;
	$result = new stdClass;
	
	$result = $this->mysqli->arrayData(array(
		'source'		=> "{$tp}payments",
		'where'			=> "{$tp}payments.id = '$this->id'"
	));

	if(!$result->totalRows) {
		$result->success = false;
	}
	else {
		// assigning properties from the DB query

		$this->amount					= $result->data[0]->amount;
		$this->sale						= $result->data[0]->sale;
		$this->paymentMethod			= new PaymentMethod($result->data[0]->paymentMethod);
		$this->timestamp				= new DateTime($result->data[0]->timestamp, timezone_open('UTC'));
		$this->registerer				= $result->data[0]->registerer;
		
		$this->params = (object) json_decode($result->data[0]->params);
		foreach($this->paymentMethod->config->paymentParams as $param) {
			if(!isset($this->params->{$param->name})) {
				$this->params->{$param->name} = @$param->value;
//
			}
		}

		$this->hasLoaded = true;
		$result = (object)array('success' => true);
		return $result;
	}
}


// Saves payment to DB
/****************************************/
//	$sale: Sale object of $payment
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
public function save($sale) {
	$tp = $this->mysqli->table_prefix;
	settype($this->params, 'object');

	$edit = $this->mysqli->saveToDb(array(
		'returnQuery' => true,
		'id'		=> $this->id,
		'table'		=> "{$tp}payments",
		($this->id ? 'update' : 'insert')	=> true,
		'where'		=>	($this->id ? "id = '{$this->id}'" : ""),
		'fields'	=> array(
			'amount'		=> $this->amount,
			'sale'			=> $sale->id,
			'paymentMethod'	=> $this->paymentMethod->id,
			'params'		=> json_encode($this->params),
			'timestamp'		=> $this->timestamp->format('Y-m-d H:i:s'),
			'registerer'	=> $this->registerer
		)
	));

	if($edit->success) {
		$this->id = $edit->id;
	}
	$this->load();
	return $edit;
}


// Validates this payments parameters according to the Payment Methods configurations
/****************************************/
//	$sale: Sale object of $payment
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg (string): general error msg
//		errors: stdClass object with properties corresponding to failing parameters each set to an error message for this parameter
public function validate($sale) {
	$result = new stdClass;
	$result->success = true;
	$result->errors = new stdClass;
	
	if(!$this->paymentMethod->id) {
		return (object)array(
			'success'	=> false,
			'msg'	=> "err Payment class paymentmethod is not PaymentMethod object"
		);
	}

	foreach($this->paymentMethod->config->paymentParams as $param) {
		unset($validator, $class, $method);
		if(!isset($this->params->{$param->name})) {
			if($param->required) {
				$result->success = false;
				$result->errors->{$param->name} = "err Payment this is required value";
			}
			$this->params->{$param->name} = $param->value;
		}
		if(@$param->validator) {
			$validator = explode("::", $param->validator, 2);
			$class = $validator[0];
			$method = $validator[1];
			if($class == 'PaymentValidator' or (is_subclass_of($class, 'PaymentValidator')) and method_exists($class, $method)) {
				$object = new $class;
				$validation = $object->$method($this->params->{$param->name});
				if(!$validation->success) {
					$result->success = false;
					$result->errors->{$param->name} = $validation->msg;
				}
			}
			else {
				$result->success = false;
				$result->errors->{$param->name} = "err Payment can not find validation method";
			}
		}
	}
	
	if(!($this->timestamp instanceof DateTime) and $this->timestamp) {
		$result->success = false;
		$result->msg = "err Payment class timestamp is not DateTime object";
	}

	if(!($sale instanceof Sale)) {
		$sale = $this->getSale()->data;
	}
	if(!($sale instanceof Sale)) {
		$result->success = false;
		$result->msg = "err Payment class sale is not Sale object";
	}

	return $result;
}


}


class PaymentValidator {



public function __construct() {
}


// Default validator // ALWAYS SUCCEEDS
/****************************************/
//	$value: (string) value to be checked
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg (string): error msg
public function validate($value) {
	$result = (object) array(
		'success' => true
	);
	return $result;
}


// Validates Gift Voucher code
/****************************************/
//	$value: (string) value to be checked
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg (string): error msg
public function validateGiftVoucherCode($value) {
	$voucher = new GiftVoucher;
	$result = $voucher->loadByVoucherCode($value);
	if($result->success) {
		$check = $voucher->checkIfSpent();
		if(!$check->success) {
			return $check;
		}
		if($check->data) {
			$result->success = false;
			$result->msg = "err PaymentValidator class voucher has already been used";
		}
	}
	else if(!$result->msg) {
		$result->msg = "err PaymentValidator class validateGiftVoucherCode() could not find a voucher with this code";
	}
	return $result;
}


}


?>