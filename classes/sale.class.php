<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/

class Sale {

protected $change = 0; // Excessive payment
protected $completed = false; // Is this sale completed?
protected $created; // Time when sale was initially created
protected $customerid = 0; // Customer ID if known
protected $customername = ''; // Name or description of customer
protected $discounts = array();
protected $discountsHaveLoaded = false;
protected $due = 0; // Total due after payments have been deducted
protected $hasLoaded = false;
protected $invoices = array(); // Invoiced produced by this sale
protected $invoicesHaveLoaded = false; // True if invoices have been loaded
protected $items = array(); // Items in this sale
protected $itemsHaveLoaded = false; // True if items have been loaded
protected $itemsTotal = 0; // Total for the sold and returned items before overall discounts
protected $itemsTotalHasBeenDetermined = false; // True if itemsTotal has been determined
protected $mysqli;
protected $nonExchangeableExcessivePayment = array(); // Payments that have to be refunded via same payment method
protected $paid = 0; // Total payments for this sale
protected $payments = array();
protected $paymentsHaveBeenDistributed = false;
protected $paymentsHaveLoaded = false;
protected $paymentsDistribution = array();
protected $paymentTotalsHaveBeenCalculated = false;
protected $shares;
protected $sharesHaveBeenDetermined = false;
protected $total = 0; // Total for this sale after overall discounts
protected $totalHasBeenDetermined = false; // True if Total has been determined
protected $traders = array();
protected $tradersHaveLoaded = false;

public $id = 0; //	Identificator for this sale as stored in the DB
public $colour = 'white'; // Colour code for this sale


public function __construct($config = null) {
	global $mysqliConnection;
	$this->shares = new stdClass;
	$this->mysqli = $mysqliConnection;
	if(is_array($config)) {
		settype($config, 'object');
	}
	if(is_object($config)) {
		$this->id = (int)$config->id;
	}
	else if($config) {
		$this->id = (int)$config;
	}
	if($this->id) {
		$this->load();
	}
	else if(@$config->customerid or @$config->customername) {
		$this->assign($config);
	}
}


// Config options:
// id int Product to add required
// quantity float amount of this froduct to add required
// pricePer float price of object
// discount float discount rate as decimal factor
public function addItem($item = array()) {
	$result = new stdClass;
	$tp = $this->mysqli->table_prefix;

	settype($item, 'object');
	settype($item->pricePer, 'string');
	settype($item->discount, 'string');

	if($this->checkIfCompleted()->data) {
		$result->msg = 'msg completed sale cannot be edited';
		$result->success = false;
		return $result;
	}
	if(!$item->id = (int)$item->id or !$item->quantity = (float)$item->quantity) {
		$result->msg = 'msg product or quantity not given';
		$result->success = false;
		return $result;
	}
	$this->assign();
	$existing = $this->mysqli->arrayData(array(
		'source' => "{$tp}sale_items",
		'where' => "sale = '$this->id' AND product = '{$item->id}'"
	));
	
	if(count($existing->data)) {
		$edit = $this->mysqli->saveToDb(array(
			'table' => "{$tp}sale_items",
			'fields' => array(
				'quantity' => bcadd($existing->data[0]->quantity, $item->quantity, 6),
				'pricePer' => ($item->pricePer ? $item->pricePer : $existing->data[0]->pricePer),
				'discount' => ($item->discount ? docu::read_proportion($item->discount) : $existing->data[0]->discount),
				'price' => round(($existing->data[0]->quantity + $item->quantity) * ($item->pricePer ? $item->pricePer : $existing->data[0]->pricePer) * (1-($item->discount ? docu::read_proportion($item->discount) : $existing->data[0]->discount)), 2)
			),
			'update' =>  true,
			'where' => "sale = '$this->id' AND product = '{$item->id}'"
		));
	}
	else {
		$product = new Product(array(
			'id' => $item->id
		));
		
		settype($config->quantity, ($product->floating ? 'float' : 'integer'));

		$edit = $this->mysqli->saveToDb(array(
			'table' => "{$tp}sale_items",
			'fields' => array(
				'sale' => $this->id,
				'product' => $product->id,
				'productCode' => $product->productCode,
				'description' => $product->name,
				'quantity' => $item->quantity,
				'unit' => $product->unit,
				'pricePer' => ($item->pricePer ? $item->pricePer : $product->price),
				'discount' => $this->read_proportion($item->discount),
				'price' => round($item->quantity * ($item->pricePer ? $item->pricePer : $product->price) * (1-$this->read_proportion($item->discount)), 2)
			),
			'insert' =>  true
		));
		if($edit->success) {
			$edit->sale = $this->id;
		}
	}
	$this->setDirty();
	return $edit;
}


// Adds a payment to the Sale
/****************************************/
//	$payment:	Sale object or associated array/object with the following possible keys/properties:
//		amount (float) amount
//		params: stdClass object)
//		paymentMethod (int) Payment Method
//		note (string) Note following this payment
//	$user (string) Name of the user who received the payment
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function addPayment($payment = array(), $user) {
	settype($payment, 'object');
	$result = new stdClass;
	$tp = $this->mysqli->table_prefix;

	if($this->checkIfCompleted()->data) {
		return (object) array(
			'success'	=> false,
			'msg'		=> "msg completed sale cannot be edited"
		);
	}
	if(!($payment->paymentMethod instanceof PaymentMethod)) {
		$payment->paymentMethod = new PaymentMethod($payment->paymentMethod);
	}
	if(!is_object($payment->params)) {
		$payment->params = (object)json_decode($payment->params);
	}
	if(!$payment->paymentMethod) {
		return (object) array(
			'success'	=> false,
			'msg'		=> "msg payment method missing"
		);
	}
	
	$this->assign();
	
	if(!($payment instanceof Payment)) {
		$result = new Payment;
		foreach($payment as $property => $value) {
			$result->$property = $value;
		}
		$payment = $result;
	}
	$payment->registerer = $user;
	$result = $payment->validate($this);
	
	if($result->success) {
		switch($payment->paymentMethod->id) {
			case -2: // ------- Gift Voucher:
				$voucher = new GiftVoucher;
				$voucher->loadByVoucherCode($payment->params->giftVoucherCode);
				
				// On issuing new giftvoucher:
				if($voucher->issuingSale->id == $this->id) {
					$payment->amount = -$voucher->value;
					$payment->params->giftVoucherId = $voucher->id;
					$payment->params->nonExchangeable = 0;
				}

				// On spending existing giftvoucher:
				else if($voucher->id) {
					$check = $voucher->checkIfSpent();
					if(!$check->success) {
						return $check;
					}
					if($check->data) {
						return (object) array(
							'success'	=> false,
							'msg'		=> "err Sale class addPayment() voucher has already been spent"
						);
					}
					
					$result = $voucher->save();
					if($result->success) {
						$payment->amount = $voucher->value;
						$payment->params->giftVoucherId = $voucher->id;
						$payment->params->nonExchangeable = !$voucher->redeemableForCash;
						foreach($voucher->traders as $trader) {
							$payment->params->limitedToTraders[] = $trader->id;
						}
						$result = $payment->save($this);
						if($result->success) {
							$result = $voucher->spend($payment);
						}
					}
				}

				// For both:
				else {
					$result->success = false;
					$result->msg = "err Sale class addPayment() voucher used or not valid";
				}
				break;
				// --	End of Gift Voucher handling
		}
	}
	if($result->success) {
		$result = $payment->save($this);
		$this->setDirty();
	}
	return $result;
}


// Assign a property to this sale
/****************************************/
//	$config:	object with the properties to be assigned
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function assign($config = array()) {
	settype($config, 'object');
	$tp = $this->mysqli->table_prefix;
	$result = (object)array(
		'success' => false,
		'msg'=> ""
	);

	if($this->checkIfCompleted()->data) {
		$result->msg = 'completed sale cannot be edited';
		$result->success = false;
		return $result;
	}
	
	$fields = new stdClass;
	if(isset($config->customerid)) {
		$fields->customerid = $config->customerid;
	}
	if(isset($config->customername)) {
		$fields->customername = $config->customername;
	}
	if(!(int)$this->id) {
		$fields->colour = "rgb(" . (128 + 5 * date('H')) . ", " . (128 + 2 * date('i')) . ", " . (128 + 2 * date('s')) . ")";
	}

	settype( $fields, 'array' );

	if( $fields ) {
		$result = $this->mysqli->saveToDb(array(
			'id' => (int)$this->id,
			'table' => "{$tp}sales",
			'fields' => $fields,
			'update' => ((int)$this->id ? true : false),
			'insert' => ((int)$this->id ? false : true),
			'where' => ((int)$this->id ? "id = '$this->id'" : ""),
			'returnQuery' => true
		));

		if($result->success) {
			$this->id = $result->id;
		}
		else {
			throw new Exception("Unable to assign properties to this sale. Mysqli error: {$result->msg}");
		}
	}
	else {
		$result->success = true;
	}

	$this->setDirty();
	$this->load();
	return $result;
}


// Offset payments against totals and determine due, change etc.
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		paid (number): Total of all payments in this Sale
//		due (number): How much is still to be paid
//		change (number): The payment excess
//		nonExchangeableExcessivePayment: Array of stdClass objects:
//			paymentMethod: PaymentMethod object
//			amount (number): Excessive amount
//			payments: array of Payment objects that need to be refunded
public function calculatePaymentTotals($update = false) {
	$this->paid = 0;
	$this->due = $this->getTotal()->data;
	$this->change = 0;
	$this->nonExchangeableExcessivePayment = array();
	
	$payments = $this->getPayments($update);

	foreach($payments->data as $payment) {
		$this->paid += $payment->amount;
		
		$sharesOfPayment = $this->getSharesOfPayment($payment);

		$paid = $this->getSharesOfPayment($payment)->distributed;
		$this->due -= $paid;
		$remainder = $payment->amount - $paid;
		
		// Are there any excessive non-exchangeable payments in this sale?
		if( $remainder !=0 and @$payment->params->nonExchangeable ) {
			settype($this->nonExchangeableExcessivePayment[$payment->paymentMethod->id], 'object');
			$nonEx = $this->nonExchangeableExcessivePayment[$payment->paymentMethod->id];
			$nonEx->paymentMethod = $payment->paymentMethod;
			$nonEx->payments[$payment->id] = $payment;
			if(!isset($nonEx->amount)) {
			    $nonEx->amount = 0;
            }
			$nonEx->amount += $remainder;
		}
		else if($remainder !=0) {
			$this->change += $remainder;
		}
	}
	
	
	if($this->due < 0) {
		$this->change -= $this->due;
		$this->due = 0;
	}
	if($this->change < 0) {
		$this->due -= $this->change;
		$this->change = 0;
	}
	$this->paymentTotalsHaveBeenCalculated = true;

	$this->paid = round($this->paid, 6);
	$this->due = round($this->due, 6);
	$this->change = round($this->change, 6);

	$result = (object) array('success'	=> true);
	$result->paid = $this->paid;
	$result->due = $this->due;
	$result->change = $this->change;
	$result->nonExchangeableExcessivePayment = $this->nonExchangeableExcessivePayment;
	return $result;
}


// Cancel and delete this sale
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		sql	(string): latest query executed
public function cancel() {
	$tp = $this->mysqli->table_prefix;
	$result = new stdClass;
	if($this->checkIfCompleted()->data) {
		$result->msg = 'msg completed sale cannot be edited';
		$result->success = false;
		return $result;
	}
	$result->sql = "DELETE {$tp}sale_items, {$tp}returned_items FROM {$tp}sale_items LEFT JOIN {$tp}returned_items ON {$tp}sale_items.id = {$tp}returned_items.credited_sale_item WHERE {$tp}sale_items.sale = '" . (int)$this->id . "'";
	if(!$result->success = $this->mysqli->query($result->sql)) {
		$result->msg = $this->mysqli->error;
	}
	else if(!$result->success = $this->mysqli->query($result->sql = "DELETE {$tp}giftvouchers, {$tp}giftvoucher_traders FROM {$tp}giftvouchers INNER JOIN {$tp}giftvoucher_traders ON {$tp}giftvouchers.id = {$tp}giftvoucher_traders.giftvoucher WHERE {$tp}giftvouchers.issuing_sale = '" . (int)$this->id . "'")) {
		$result->msg = $this->mysqli->error;
	}
	else if(!$result->success = $this->mysqli->query($result->sql = "DELETE FROM {$tp}payments WHERE {$tp}payments.sale = '" . (int)$this->id . "'")) {
		$result->msg = $this->mysqli->error;
	}
	else if(!$result->success = $this->mysqli->query($result->sql = "UPDATE {$tp}giftvouchers SET redemption_sale = NULL, redemption_payment_id = NULL WHERE redemption_sale = '" . (int)$this->id . "'")) {
		$result->msg = $this->mysqli->error;
	}
	else {
		$result->sql = "DELETE FROM {$tp}sales WHERE id = '" . (int)$this->id . "'";
		if(!$result->success = $this->mysqli->query($result->sql)) {
			$result->msg = $this->mysqli->error;
		}
	}
	$this->id
	= $this->itemsTotal
	= $this->total
	= $this->paid
	= $this->due
	= $this->change
	= $this->customerid
	= (int)$this->completed
	= $this->hasLoaded
	= $this->itemsHaveLoaded
	= $this->discountsHaveLoaded
	= $this->itemsTotalHasBeenDetermined
	= $this->totalHasBeenDetermined = false;
	$this->customername = '';
	$this->items
	= $this->discounts
	= array();
	unset($this->created);

	return $result;
}


// Check if sale has been completed
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		data	(boolean): True if the sale has been completed
public function checkIfCompleted() {
	if($this->id and !$this->hasLoaded) {
		$this->load();
	}

	return (object) array(
		'data'		=> $this->completed,
		'success'	=> true
	);
}


// Complete sale
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function complete() {
	$tp = $this->mysqli->table_prefix;

	$this->putInTill();
	foreach($this->getItems()->data as $item) {
		$item->product->reduceStock($item->quantity);
	}
	$result = $this->mysqli->saveToDb(array(
		'table' => "{$tp}sales",
		'fields' => array(
			'completed' => time(),
			'total' => $this->getTotal(true)->data
		),
		'update' =>  true,
		'where' => "id = '$this->id'"
	));
	$this->completed = $result->success;
	return $result;
}


// Add a discount to the Sale
/****************************************/
//	$config:	associated array/object with the following possible keys/properties:
//		trader (integer or array) Trader ID(s) covered by the discount
//		discount_code (string) Discount code
//		description (string) Discount name or description
//		value (float) Exact value of discount
//		discountRate (float) Discount rate between 0 and 1
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function discount($config) {
	$result = new stdClass;
	$tp = $this->mysqli->table_prefix;

	settype($config, 'object');
	settype($config->discount_code, 'string');

	if($this->checkIfCompleted()->data) {
		$result->msg = 'msg completed sale cannot be edited';
		$result->success = false;
		return $result;
	}
	$this->assign();
	if(!is_array($config->trader)) {
		$config->trader[] = $config->trader;
	}
	if($config->discount_code) {
		$result->msg = 'msg discount code not valid';
		$result->success = false;
		return $result;
	}
	$result = $this->mysqli->saveToDb(array(
		'table' => "{$tp}discounts",
		'fields' => array(
			'sale' => $this->id,
			'description' => $config->description,
			'value' => abs($config->value),
			'discountRate' => max(min($config->discountRate, 1), 0)
		),
		'insert' =>  true
	));
	if($result->success){
		foreach($config->trader as $trader) {
			$this->mysqli->saveToDb(array(
				'table' => "{$tp}discounts_traders",
				'fields' => array(
					'trader' => (int)$trader,
					'discount' => $result->id
				),
				'insert' =>  true
			));
		}
	}
	$this->setDirty();
	return $result;
}



// Distribute payment shares to each trader in the sale.
/****************************************/
//	--------------------------------------
//	return: (array)
//		[traderId]: (array) payment shares
//			[paymentId]: (number) Amount of this payment allocated to this trader
public function distributePayments() {
	$tp = $this->mysqli->table_prefix;
	
	/*	$traders will be filled with one object per trader that either is part of the sale,
	or would accept one or more of the payments in this sale.
	The objects have these properties:
		trader:		Trader object
		claim:		Max claim (invoice total) for each trader
		due:		Remaining unpaid claim at all times
		dividend:	The traders part of the total demand (as amount)
	*/
	$traders = array();

	/*	$pmnts will be filled with objects with these properties:
		payment:				Payment object
		remainder:				The remaining undistributed amount at all times
		demand:					Total wanted by all eligible traders from this payment
		numAcceptingTraders:	Number of traders who accept this payment
	*/
	$pmnts = array();


	/* First see if the distribution already has been calculated and saved in db:
	*/
	$saved = $this->mysqli->arrayData(array(
		'source' => "{$tp}payment_distributions",
		'where' => "sale_id = '{$this->id}'"
	));

	if($saved->totalRows) {
		$this->paymentsDistribution = array();
		
		foreach($saved->data as $distribution) {
			settype( $this->paymentsDistribution[ $distribution->trader_id ], 'array' );
			$this->paymentsDistribution[ $distribution->trader_id ][ $distribution->payment_id ]
			= $distribution->amount;
		}
		
		$this->paymentsHaveBeenDistributed = true;
		return $this->paymentsDistribution;
	}


	/*	Start new calculation:
	*/
	foreach($this->getPayments()->data as $payment) {
		$pmnts[] = (object) array(
			'payment'			=> $payment,
			'remainder'			=> $payment->amount,// The payment amount available for distribution
			'demand' 			=> 0,	// The total due owed to all who accept this payment
			'numAcceptingTraders' => 0,	// Number of traders accepting this payment
            'exchangeable'      => true // Change can be given by other payment methods
		);
		if(is_array(@$payment->params->limitedToTraders)) {
			foreach($payment->params->limitedToTraders as $acceptorId) {
				$traders[$acceptorId] = (object) array(
					'trader'	=> new Trader($acceptorId),
					'claim'		=> 0,
					'due'		=> 0,
					'dividend'	=> 0
				);
			}
		}
	}

	foreach($this->getShares()->data as $traderId => $share) {
		$traders[$traderId] = (object) array(
			'trader'	=> new Trader($traderId),
			'claim'		=> $share->total,
			'due'		=> $share->total,
			'dividend'	=> $share->total
		);
	}
	
	$allocations = array();
	$lap = 1;
	$stop  = false;
	while($stop == false) { // Continues to try until stop becomes true

		/* The following values are true until set as false.
		If either of them is still true by the end of the loop,
		the distribution will stop.
		*/
		$spent = true;
		$covered = true;

		// Establish the accepting traders and demands for each payment
		foreach($pmnts as $payment) {
			$payment->demand = 0;
			$payment->numAcceptingTraders = 0;
			if(isset($payment->payment->params->nonExchangeable)) {
			    $payment->exchangeable = !$payment->payment->params->nonExchangeable;
            }
			
			foreach($traders as $share) {
				$share->dividend = $share->due;
				
				if($payment->payment->checkIfAcceptedBy($share->trader)->result) {
					$payment->demand				+= $share->due;
					$payment->numAcceptingTraders	++;
				}
			}
		}
		// Demand and accepting traders has been determined for each payment


		// Distribute undistributed payments to the different demands
		foreach($pmnts as $payment) {
			$pmid = $payment->payment->id;
			$remainder = $payment->remainder;
			
			foreach($traders as $share) {
				$trid = $share->trader->id;
				settype($allocations[$trid], 'array');
				settype($allocations[$trid][$pmid], 'string');
			
				// Establish each traders proportion of demands for each payment
				if( $payment->demand != 0 ) {
					$proportion[$trid][$pmid] = $share->dividend / $payment->demand;
				}
				else if( $payment->numAcceptingTraders ) {
					$proportion[$trid][$pmid] = 1 / $payment->numAcceptingTraders;
				}
				else {
					$proportion[$trid][$pmid] = 0;
				}

				// If this payment is accepted by the trader,
				// the relevant proportion of the remaining payment
				// will be added to the allocation
				if($payment->payment->checkIfAcceptedBy($share->trader)->result) {

					// Add the value to the allocation
					$allocations[$trid][$pmid] += $remainder * $proportion[$trid][$pmid];
					
					// Subtract the allocated value from the due amount
					$share->due -= $remainder * $proportion[$trid][$pmid];
				}
			}
			
			// The payment remainder has been distributed
			if($payment->numAcceptingTraders) {
				$payment->remainder = 0;
			}
		}
		
		
		// Loop to return excessive payments for redistribution
		foreach($traders as $share) {
			$trid = $share->trader->id;

			if(($share->claim * $share->due) < 0) { // claim has been more than covered

				// Sort payments by flexibility
                $this->sortObjects($pmnts, "exchangeable", "DESC");
                $this->sortObjects($pmnts, "demand", "DESC");
				$this->sortObjects($pmnts, "numAcceptingTraders", "DESC");
				foreach($pmnts as $payment) {
					$pmid = $payment->payment->id;
					$excess = bcsub($payment->remainder, $share->due, 9);
					$allocated = $allocations[$trid][$pmid];
					
					// Find the smallest of allocation and excessive payment
					$returnable = (abs($excess) < abs($allocated) ? $excess : $allocated);

					// Return the returnable amount for redistribution
					$payment->remainder = bcadd($payment->remainder, $returnable, 9);
					$allocations[$trid][$pmid] = bcsub($allocations[$trid][$pmid], $returnable, 9);
					$share->due = bcadd($share->due, $returnable, 9);

					// This due or remainder is covered and accept termination of the while loop
					$spent = $spent && !(float)$payment->remainder;
				}
			}
			$covered = $covered && !(float)$share->due;
		}

		$stop = $stop || $spent || $covered;
		if($allocations == @$previousAllocations) {
			$stop = true;
		}
		$previousAllocations = $allocations;
		$lap += 1;
		if($lap == 10) {
			$stop = true;
			mail("kyegil@gmail.com", "Problems with allocating payments in POS", "While loop exceeding 10 laps in sale {$this->id}");
			throw new Exception("While loop for payments distribution exceeding 10 laps in sale {$this->id}");
		}
	}
	foreach($allocations as $trid => $payments) {
		foreach($payments as $pmid => $allocation) {
			$allocations[$trid][$pmid] = $this->x_round($allocation, 6);
		}
	}

	$this->paymentsDistribution = $allocations;
	$this->paymentsHaveBeenDistributed = true;
	return $this->paymentsDistribution;
}


// Edit item occurence in sale
/****************************************/
//	$config:	associated array/object with the following possible keys/properties:
//		id (integer) Item ID as stored in the DB
//		quantity (float) New quantity of item
//		pricePer (float) New price per unit of the item
//		discount (float) Discount rate between 0 and 1 applied to this item
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function editItem($config = array()) {
	settype($config, 'object');
	$result = new stdClass;
	$tp = $this->mysqli->table_prefix;

	if($this->checkIfCompleted()->data) {
		$result->msg = 'msg completed sale cannot be edited';
		$result->success = false;
		return $result;
	}
	if(!$config->id = (int)$config->id) {
		$result->msg = 'msg item id missing';
		$result->success = false;
		return $result;
	}
	$this->assign();
	$existing = $this->mysqli->arrayData(array(
		'source' => "{$tp}sale_items",
		'where' => "sale = '{$this->id}' AND id = '{$config->id}'"
	));
	if(!$existing->totalRows) {
		$result->msg = 'msg item not in sale';
		$result->success = false;
		return $result;
	}
	$existing = $existing->data[0];
	$product = new Product(array(
		'id' => $existing->product
	));
	if(isset($config->quantity)) {
		settype($config->quantity, ($product->floating ? 'string' : 'integer'));
	}
	if(isset($config->quantity) and !$config->quantity) {
		$this->removeItem($existing->product);
		$result->success = true;
		return $result;
	}

	$new = new stdClass;
	$new->quantity = (isset($config->quantity) ? $config->quantity : $existing->quantity);
	$new->pricePer = (isset($config->pricePer) ? (float)$config->pricePer : $existing->pricePer);
	$new->discount = (isset($config->discount) ? (float)$config->discount : $existing->discount);
	$new->price = bcmul($new->quantity, bcmul($new->pricePer, bcsub(1, $new->discount, 6), 6), 6);

	$edit = $this->mysqli->saveToDb(array(
		'returnQuery' => true,
		'table' => "{$tp}sale_items",
		'where' => "sale = '$this->id' AND id = '{$config->id}'",
		'fields' => $new,
		'update' =>  true
	));
	$this->setDirty();
	return $edit;
}


// Get time of creation of Sale
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		data	DateTime object or null if not created
public function getCreationTime() {
	if(!$this->hasLoaded) {
		$this->load();
	}

	return (object) array(
		'data'		=> $this->created,
		'success'	=> true
	);
}


// Get customer info
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		data	DateTime object or null if not created
public function getCustomer() {
	if(!$this->hasLoaded) {
		$this->load();
	}

	return (object) array(
		'data'		=> (object) array(
			'id'	=> $this->customerid,
			'name'	=> $this->customername
		),
		'success'	=> true
	);
}


// Get discounts in this sale
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: array of stdClass items with the following properties:
//			id: (int) ID of discount as saved in DB
//			description: (string) description of the discount
//			discountRate: (float) Discount rate as decimal (0-1)
//			value: (float) Discount value as decimal if not given as percentage
//			traders: array of Trader objects that the discount applies to
public function getDiscounts($update = false) {
	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => null
	);
	if($this->discountsHaveLoaded and !$update) {
		$result->data = $this->discounts;
		return $result;
	}
	else {
		return $this->loadDiscounts();
	}
}


// Get Invoices in this sale
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: array of Invoice objects
public function getInvoices($update = false) {
	$result = (object)array(
		'success' => true
	);
	if($this->invoicesHaveLoaded and !$update) {
		$result->data = $this->invoices;
		return $result;
	}
	else {
		return $this->loadInvoices();
	}
}


// Get Gift Vouchers issued on this sale
/****************************************/
//	--------------------------------------
//	return: array of GiftVoucher objects
public function getIssuedVouchers() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}giftvouchers",
		'where' => "issuing_sale = '$this->id'"
	))->data;
	foreach($result as $id => $voucher) {
		$result[$id] = new GiftVoucher($voucher->id);
	}
	return $result;
}


// Get items in this sale
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: array of stdClass objects with properties:
//			product: Product object
//			productCode: (string) the products product code
//			description: (string) description or product name of the item
//			quantity: (float) Quantity
//			unit: (string) The unit of which the product is sold
//			pricePer: (float) The price per item
//			discount: (float) The discount rate to multiply item price by
//			price: Total for this item(s) after discount
public function getItems($update = false) {
	$result = (object)array(
		'success' => true
	);
	if($this->itemsHaveLoaded and $this->itemsTotalHasBeenDetermined and !$update) {
		$result->data = $this->items;
		$result->total = $this->itemsTotal;
		return $result;
	}
	else {
		return $this->loadItems();
	}
}


// Get items total
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: Sale Items Total
public function getItemsTotal($update = false) {
	$result = (object)array(
		'success' => true
	);
	if($this->itemsTotalHasBeenDetermined and !$update) {
		$result->data = $this->itemsTotal;
		return $result;
	}
	else {
		$result = $this->getItems($update);
		if($result->success) {
			$result->data = $result->total;
		}
	}
	return $result;
}


// Get payments in this sale
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: array of Payment objects:
public function getPayments($update = false) {
	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => null
	);
	if($this->paymentsHaveLoaded and !$update) {
		$result->data = $this->payments;
		return $result;
	}
	else {
		return $this->loadPayments();
	}
}


// Get Payments total
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: Payments Total
//		paid (number): Total of all payments in this Sale
//		due (number): How much is still to be paid
//		change (number): The payment excess
//		nonExchangeableExcessivePayment: Array of stdClass objects:
//			paymentMethod: PaymentMethod object
//			amount (number): Excessive amount
public function getPaymentsTotal($update = false) {
	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => null
	);
	if($this->paymentTotalsHaveBeenCalculated and !$update) {
		$result->paid = $this->paid;
		$result->due = $this->due;
		$result->change = $this->change;
		$result->nonExchangeableExcessivePayment = $this->nonExchangeableExcessivePayment;
		return $result;
	}
	else {
		return $this->calculatePaymentTotals();
	}
}


// Get the different traders' shares of this sale
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: stdClass object with properties corresponding to each traders id
//			$traderId: stdClass object with properties:
//				items: array of stdClass Sale items
// 					product: Product object
// 					productCode: (string) the products product code
// 					description: (string) description or product name of the item
// 					quantity: (float) Quantity
// 					unit: (string) The unit of which the product is sold
// 					pricePer: (float) The price per item
// 					discount: (float) The discount rate to multiply item price by
// 					price: Total for this item(s) after discount
//				tax: array of tax stdClass objects:
// 					id (integer)
// 					taxName (string)
// 					taxRate (number)
//				itemsTotal: This traders total for items before overall discounts
//				itemsProportion: This traders proportion of items total
//				discounts: array of stdClass objects: (ONLY IF NOT COMPLETED)
//					effectiveDiscount: This traders share of the effective discount value
//					description: Description of this discount
//					discountRate: % Discount rate if applicable
//				total: This traders total of the sale
//				proportion: This traders proportion of sales total
public function getShares($update = false) {
	$result = (object)array(
		'success' => false,
		'msg'=> "",
		'data' => null
	);
	if($this->sharesHaveBeenDetermined and !$update) {
		$result->data = $this->shares;
		$result->success = true;
		return $result;
	}
	else {
		if($this->checkIfCompleted()->data) {
			$invoices = $this->getInvoices();
			if(!$invoices->success) {
				return $invoices;
			}
			foreach($invoices->data as $invoice) {
				$trader = $invoice->getTrader()->data;
				settype($this->shares->{$trader->id}, 'object');
				
				$this->shares->{$trader->id}->items = $invoice->getItems()->data;
				$this->shares->{$trader->id}->tax = $invoice->getTaxes()->data;
				$this->shares->{$trader->id}->itemsTotal = $invoice->getItemsTotal()->data;
				$itemsTotal = $this->getItemsTotal()->data;
				$this->shares->{$trader->id}->itemsProportion = bcdiv($invoice->getItemsTotal()->data, ($itemsTotal != 0 ? $itemsTotal : 1), 6);
				$this->shares->{$trader->id}->total = $invoice->total;
				$total = $this->getTotal()->data;
				$this->shares->{$trader->id}->proportion = bcdiv($invoice->total, ($total != 0 ? $total : 1), 6);
			}
			unset($total);
			$result->success = true;
			$result->data = $this->shares;
		}
		else {
			$result = $this->split(new CollectivePOS);
			if(!$result->success) {
				$this->sharesHaveBeenDetermined = false;
				return $result;
			}
		}
		$this->sharesHaveBeenDetermined = true;
		$result->success = true;
		return $result;		
	}
}


// Get each traders share of a payment
/****************************************/
//	$payment: (Payment object or integer) The payment to return the shares from
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		distributed (number): Total spent amount from this payment
//		shares: stdClass object with properties corresponding to each traders id
//			$traderId: stdClass object with properties:
//				trader: Trader object
//				amount: The traders share of the payment
public function getSharesOfPayment($payment) {
	$result = (object)array(
		'success' => true,
		'distributed' => 0,
		'shares' => new stdClass
	);
	if ($payment instanceof Payment) {
		$paymentId = $payment->id;
	}
	else {
		$paymentId = (int)$payment;
	}

	if(!$paymentId) {
		throw new Exception("err Sale class getSharesOfPayment payment missing");
	}
	if(!$this->paymentsHaveBeenDistributed) {
		$distribution = $this->distributePayments();
	}
	foreach($this->paymentsDistribution as $traderId => $share) {
		$result->shares->{$traderId} = (object) array(
			'trader' => new Trader($traderId),
			'amount' => @$share[$paymentId]
		);
		$result->distributed += @$share[$paymentId];
	}
	
	return $result;
}


// Get Sale total
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: Sale Total
public function getTotal($update = false) {
	$result = (object)array(
		'success' => true
	);
	if($this->totalHasBeenDetermined and !$update) {
		$result->data = $this->total;
		return $result;
	}
	else {
		$this->total = $this->getItemsTotal($update)->data;
		
		$discounts = 0;
		foreach($this->getDiscounts($update)->data as $discount) {
			$discounts +=1;
			foreach($discount->traders as $trader) {
				foreach($this->getItems()->data as $item) {
					if($item->product->getTrader()->data->id == $trader->id) {
						$this->total
						= bcsub($this->total, bcmul($discount->discountRate, $item->price, 6), 6);
					}
				}
			}
			$this->total -= $discount->value;
		}

		$this->totalHasBeenDetermined = true;
		$result->data = round($this->total, 6);
		return $result;
	}
}


// Get traders in this sale
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: array of Trader objects:
public function getTraders($update = false) {
	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => null
	);
	if($this->tradersHaveLoaded and !$update) {
		$result->data = $this->traders;
		return $result;
	}
	else {
		return $this->loadTraders();
	}
}


// Return a traders share of the payments in this sale
/****************************************/
//	$trader: (Trader object or integer id) The trader to get payment shares for
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful
//		msg	(string): message that explains the success parameter
//		total (number): Total amount distributed to this trader
//		payments: stdClass object with properties corresponding to each payments id
//			$paymentId: stdClass object with properties:
//				payment: Payment object
//				amount: The traders share of the payment
public function getTradersShareOfPayments($trader) {
	$result = (object)array(
		'success' => true,
		'total' => 0,
		'payments' => new stdClass
	);
	if ($trader instanceof Trader) {
		$traderId = $trader->id;
	}
	else {
		$traderId = (int)$trader;
	}
	if(!$traderId){
		return (object)array(
			'success' => false,
			'msg'=> "err Sale class getTradersShareOfPayments trader missing"
		);
	}
	
	if(!$this->paymentsHaveBeenDistributed) {
		$distribution = $this->distributePayments();
	}
	settype($this->paymentsDistribution[$traderId], 'array');
	foreach($this->paymentsDistribution[$traderId] as $paymentId => $amount) {
		$result->payments->{$paymentId} = (object) array(
			'payment' => new Payment($paymentId),
			'amount' => $amount
		);
		$result->total = bcadd($result->total, $amount, 6);
	}
	
	return $result;
}


// Create invoices on this sale
//	This function creates a new invoice for each of the traders in the sale.
//	Each invoice is created with the following option parameter:
//		create:	boolean true (true to create a new invoice)
//		trader:	integer, The trader id
//		date: string The current date as Y-m-d
//		sale: The Id of the current sale
//		items: array of assoc/stdClass sale items belonging to this trader:
//			id:	integer sale items identifier
//			sale: integer The Id of the current sale
//			product: The Product object of the item
//			productCode: string, the product code of the item
//			description: string, The product name or description for the invoice
//			quantity: number, the quantity sold
//			unit: string, the unit part of the quantity
//			pricePer:	number, the original price per item
//			discount: number, the price discount applied to this item line
//			price:	number, the line total
//		discounts: array of assoc/stdClass taxes applied to this invoice:
//			
//		tax: array of assoc/stdClass taxes applied to this invoice:
//			id: integer, the tax id
//			taxName: string, the tax name/description
//			taxRate: number, the tax rate as decimal number
//			taxBasis: number: the items total foundation from which the tax has been calculated
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function invoice() {
	$this->setDirty();
	$result = new stdclass;
	$result->success = false;
	$tp = $this->mysqli->table_prefix;

	// Stop if the sale has been completed already
	if($this->checkIfCompleted()->data) {
		$result->msg = 'msg completed sale cannot be edited';
		return $result;
	}

	// Split the sale into each traders shares
	$shares = $this->getShares($update = true);
	if(!$shares->success) {
		return $shares;
	}

	//	Populate $this->paymentsDistribution with distribution of payments
	$this->distributePayments();
	
	// Repeat for each share (=trader)
	foreach($shares->data as $traderId => $share) {
		settype( $this->paymentsDistribution[$traderId], 'array' );

		// Create an invoice based on this traders share of the sale
		$invoice = new Invoice(array(
			'create'	=> true,
			'trader'	=> $traderId,
			'date'		=> date('Y-m-d'),
			'sale'		=> $this->id,
			'items'		=> $share->items,
			'discounts'	=> @$share->discounts,
			'tax'		=> $share->tax
		));
		if(!$invoice->id) {
			throw new Exception("Sale class unable to invoice");
			$result->success = false;
			$result->msg = "err Sale class unable to invoice";
			return $result;
		}

		// Save this traders share of each payment in the Payment Distributions table
		$sql = array();
		foreach($this->paymentsDistribution[$traderId] as $pmid => $value) {
			if($value != 0) {
				$sql[] = "({$this->id}, {$pmid}, {$invoice->id}, {$traderId}, '{$value}')";
			}
		}
		$this->mysqli->query("INSERT INTO {$tp}payment_distributions (sale_id, payment_id, invoice_id, trader_id, amount)\n VALUES\n" . implode(",\n", $sql));

		// Add the newly created invoice to this sale's invoices
		$this->invoices[] = $invoice;
	}
	
	// Save any prepayment transactions to the Payment Distributions table
	$sql = array();
	if(isset($this->paymentsDistribution[0])) {
		foreach($this->paymentsDistribution[0] as $pmid => $value) {
			if($value != 0) {
				$sql[] = "({$this->id}, {$pmid}, 0, '{$value}')";
			}
		}
		$this->mysqli->query("INSERT INTO {$tp}payment_distributions (sale_id, payment_id, trader_id, amount)\n VALUES\n" . implode(",\n", $sql));
	}


 	$this->complete();
 	$this->setDirty();
	$result->success = true;
	return $result;
}


// Issues a gift voucher
/****************************************/
//	$allowSharedDeposit:	(bool) if shared prepayment deposit can be used
//	$config:	associated array/object with the following possible keys/properties:
//		code				req (string) Voucher code to register
//		value				req (string) Voucher value
//		traders				req (Array of Trader objects) who accept the voucher
//		prepaymentholdingTrader	req (Trader object) who's holding prepayment
//		expires				(DateTime object) Expiry of Voucher
//		redeemableForCash	(bool) If voucher can be traded for cash
//	$user:	req (string) Issuing user
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function issueGiftVoucher($allowSharedDeposit, $config, $user) {
	settype($config, 'object');
	$tp = $this->mysqli->table_prefix;
	$result = new stdClass;
	$result->success = true;
	
	if($this->checkIfCompleted()->data) {
		$result->msg = 'msg completed sale cannot be edited';
		$result->success = false;
		return $result;
	}

	$voucher = new GiftVoucher;
	
	if($voucher->isCodeAvailable($config->code)->success) {
		$voucher->code = $config->code;
	}
	else {
		$result->success = false;
		$result->msg = "msg voucher code already issued";
		return $result;
	}

	if($config->value) {
		$voucher->value = $config->value;
	}
	else {
		$result->success = false;
		$result->msg = "msg voucher has no value";
		return $result;
	}

	foreach((array)$config->traders as $trader) {
		if($trader instanceof Trader) {
			if($trader->preferences->vouchers and $trader->id) {
				$voucher->traders[] = $trader;
			}
		}
	}
	if(!count($voucher->traders)) {
		$result->success = false;
		$result->msg = "msg voucher no traders on voucher";
		return $result;
	}
	if(count($voucher->traders) == 1) {
		$voucher->prepaymentholdingTrader = $voucher->traders[0];
	}
	else if($config->prepaymentholdingTrader instanceof Trader) {
		if($config->prepaymentholdingTrader->id or $allowSharedDeposit) {
			$voucher->prepaymentholdingTrader = $config->prepaymentholdingTrader;
		}
		else {
			$result->success = false;
			$result->msg = "msg voucher shared prepayment deposit not allowed";
			return $result;
		}
	}
	else {
		$result->success = false;
		$result->msg = "msg voucher prepayment deposit not indicated";
		return $result;
	}
	
	$this->assign();

	if($config->expires instanceof DateTime) {
		$voucher->expires = $config->expires;
	}
	else {
		$voucher->expires = null;
	}

	if(isset($config->redeemableForCash)) {
		$voucher->redeemableForCash = (bool)$config->redeemableForCash;
	}
	$voucher->issued = new DateTime(null, new DateTimeZone('UTC'));
	$voucher->issuingSale = $this;
	$voucher->design = $config->design;
	
	$result = $voucher->save();
	
	if($result->success) {
	$result = $this->addPayment(array(
			'amount'	=> -$voucher->value,
			'paymentMethod'	=> -2,
			'params'	=> (object) array(
				'giftVoucherId'	=>	$voucher->id,
				'giftVoucherCode'	=>	$voucher->code,
				'limitedToTraders' => array(
					(int)$voucher->prepaymentholdingTrader->id
				)
			)
		), $user);
	}
	
	return $result;
}


// Load this sale from the DB
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
public function load() {
	$tp = $this->mysqli->table_prefix;
	
	if($this->id) {
		$data = $this->mysqli->arrayData(array(
			'source'		=> "{$tp}sales LEFT JOIN {$tp}sale_items ON {$tp}sales.id = {$tp}sale_items.sale",
			'where'			=> "{$tp}sales.id = '$this->id'",
			'fields'		=> "{$tp}sales.*, SUM({$tp}sale_items.price) AS items_total",
			'groupfields'	=> "{$tp}sales.id"
		));
	
		if(!$data->totalRows) {
			// No records found; properties are reset to class default
			
			die("Loading of Sale {$this->id} failed");
			$this->id = $this->itemsTotal = $this->total = $this->paid = $this->due = $this->change = $this->customerid = (int)$this->completed = (bool)$this->customername = '';
			unset($this->created);
			return (object) array(
				'success'	=> false,
				'msg'	=> "err Sale class sale cant be found"
			);
		}
		$this->created = new DateTime($data->data[0]->created, timezone_open('UTC'));
		$this->completed = ($data->data[0]->completed ? new DateTime("@" . $data->data[0]->completed) : false);
		$this->customerid = $data->data[0]->customerid;
		$this->customername = $data->data[0]->customername;

		if($this->completed) {
			$this->itemsTotal = $data->data[0]->items_total;
			$this->itemsTotalHasBeenDetermined = true;
		}
		
	}
	$this->hasLoaded = true;
	
	return (object) array('success'	=> true);
}


// Loads discounts into discounts property
/****************************************/
//	--------------------------------------
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: array of stdClass items with the following properties:
//			id: (int) ID of discount as saved in DB
//			description: (string) description of the discount
//			discountRate: (number) Discount rate as decimal (0-1)
//			value: (number) Discount value as decimal if not given as percentage
//			traders: array of Trader objects that the discount applies to
protected function loadDiscounts() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}discounts",
		'where' => "sale = '$this->id'",
		'fields' => "id, description, discountRate, value",
		'orderfields' => "id"
	));
	if(!$result->success) {
		return $result;
	}
	foreach($result->data as $id => $discount) {
		$result->data[$id]->traders = array();
		$data = $this->mysqli->arrayData(array(
			'source' => "{$tp}discounts_traders",
			'fields' => "trader",
			'where' => "discount = '{$discount->id}'",
			'orderfields' => "trader"
		));
		foreach($data->data as $trader) {
			$result->data[$id]->traders[] = new Trader($trader->trader);
		}
	}
	if($result->success) {
		$this->discounts = $result->data;
		$this->discountsHaveLoaded = true;
	}
	return $result;
}


// Load the invoices into the invoices property
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		total (number): Total of all items
//		data: array of Invoice objects
protected function loadInvoices() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'class'	=> 'Invoice',
		'fields' => 'id',
		'source' => "{$tp}invoices",
		'where' => "sale = '{$this->id}'"
	));
	if($result->success) {
		$this->invoices = $result->data;
		$this->invoicesHaveLoaded = true;
	}
	return $result;
}


// Load the items into the items property
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		total (number): Total of all items
//		data: array of stdClass invoice items with the following properties:
//			product: Product object
//			productCode: (string) the products product code
//			description: (string) description or product name of the item
//			quantity: (float) Quantity
//			unit: (string) The unit of which the product is sold
//			pricePer: (float) The price per item
//			discount: (float) The discount rate to multiply item price by
//			price: Total for this item(s) after discount
protected function loadItems() {
	$tp = $this->mysqli->table_prefix;
	
	if($this->checkIfCompleted()->data) {
		$result = $this->mysqli->arrayData(array(
			'source' => "{$tp}invoices LEFT JOIN {$tp}invoice_items ON {$tp}invoices.id = {$tp}invoice_items.invoiceId",
			'fields' => "{$tp}invoice_items.*",
			'where' => "{$tp}invoices.sale = '$this->id'"
		));
	}
	else {
		$result = $this->mysqli->arrayData(array(
			'source' => "{$tp}sale_items",
			'where' => "sale = '$this->id'"
		));
	}
	if($result->success) {
		$result->total = 0;
		foreach($result->data as $id => $item) {
			$result->total = bcadd($result->total, $item->price, 6);
			$result->data[$id]->product = new Product($item->product);
		}
		$this->items = $result->data;
		$this->itemsHaveLoaded = true;
		$this->itemsTotal = $result->total;
		$this->itemsTotalHasBeenDetermined = true;
	}
	return $result;
}


// Load payments into payments property
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: array of Payment objects:
protected function loadPayments() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'class' => "Payment",
		'source' => "{$tp}payments a LEFT JOIN {$tp}payment_methods b ON a.paymentMethod = b.id",
		'fields' => "a.id, a.timestamp, a.amount, a.paymentMethod, a.note, a.registerer, b.name, b.paymentGroup",
		'where' => "sale = '$this->id'",
		'orderfields' => "a.timestamp"
	));
	if($result->success) {
		$this->payments = $result->data;
		$this->paymentsHaveLoaded = true;
	}
	return $result;
}


// Load traders into traders property
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: array of Trader objects:
protected function loadTraders() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'class' 		=> "Trader",
		'source'		=> "{$tp}sale_items INNER JOIN {$tp}products ON {$tp}sale_items.product = {$tp}products.id",
		'where'			=> "{$tp}sale_items.sale = '$this->id'",
		'fields'		=> "{$tp}products.trader AS id",
		'distinct'		=> true
	));
	if($result->success) {
		$this->traders = $result->data;
		$this->tradersHaveLoaded = true;
	}
	return $result;
}


// Put payment from sale in the till
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function putInTill() {
	$this->setDirty();
	$portions = array();
	$CollectivePOS = new CollectivePOS;
	if($this->checkIfCompleted()->data) {
	    return (object)array(
	        'msg'   => 'msg completed sale cannot be edited',
            'success'   => false
        );
	}

	$cash = false;
	foreach($this->getPayments()->data as $payment) {
		if($payment->paymentMethod->id == $CollectivePOS->preferences->till_payment_method) { // if payment is cash:
			$cash = true;
			$shares = $this->getSharesOfPayment($payment);
			foreach($shares->shares as $traderId => $share) {
				settype($portions[$traderId], 'object');
				settype($portions[$traderId]->sum, 'string');
				$portions[$traderId]->trader = $traderId;
				$portions[$traderId]->sum += $share->amount;
			}
		}
	}

	if($cash) { // There has been cash transactions. Otherwise nothing to do
		$result = $CollectivePOS->till->sale( @$CollectivePOS->user['id'], $this->id, $portions );
	}
}


public function registerChange($paymentMethod, $user, $change = 0) {
	$result = (object) array(
		'success' => true
	);
	$tp = $this->mysqli->table_prefix;

	if(!$change) {
		$change = $this->getPaymentsTotal()->change;
	}
	if($change != 0) {
		$result = $this->mysqli->saveToDb(array(
			'table' => "{$tp}payments",
			'fields' => array(
				'sale' => $this->id,
				'amount' => -$change,
				'paymentMethod' => $paymentMethod,
				'registerer' => $user
			),
			'insert' =>  true
		));
	}
	return $result;
}


public function read_proportion($proportion){
	$proportion = str_replace(array(",", "%", " "), array(".", "/100", ""), $proportion);
	$decimal = (float)eval("return $proportion;");
	if($decimal >1 or $decimal <0) return false;
	else return $decimal;
}


// Remove a discount from the sale
/****************************************/
//	$discount_id:	Discount ID as stored in the database
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function removeDiscount($discount_id) {
	$result = new stdClass;
	$tp = $this->mysqli->table_prefix;

	if($this->checkIfCompleted()->data) {
		$result->msg = 'msg completed sale cannot be edited';
		$result->success = false;
		return $result;
	}
	$result->sql = "DELETE FROM {$tp}discounts WHERE sale = '" . (int)$this->id . "' and id = '" . (int)$discount_id . "'";
	if(!$result->success = $this->mysqli->query($result->sql)) {
		$result->msg = $this->mysqli->error;
	}
	$this->setDirty();
	return $result;
}


// Remove an item from the sale
/****************************************/
//	$product_id (integer) Product ID as stored in the DB
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function removeItem($product_id) {
	settype($product, 'array');
	$result = new stdClass;
	$tp = $this->mysqli->table_prefix;

	if($this->checkIfCompleted()->data) {
		$result->msg = 'msg completed sale cannot be edited';
		$result->success = false;
		return $result;
	}
	$result->sql = "DELETE {$tp}sale_items, {$tp}returned_items FROM {$tp}sale_items LEFT JOIN {$tp}returned_items ON {$tp}sale_items.id = {$tp}returned_items. 	credited_sale_item WHERE sale = '" . (int)$this->id . "' and product = '" . (int)$product_id . "'";
	if(!$result->success = $this->mysqli->query($result->sql)) {
		$result->msg = $this->mysqli->error;
	}
	$this->setDirty();
	return $result;
}


// Adds a return of an item to the Sale
/****************************************/
//	$product_id:	(integer) Product ID of the returned product (either this or $instance is required)
//	$instance:		(integer) Reference to the invoiced item (either this or $product_id is required)
//	$quantity:		(float) Optional number of items returned
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
public function returnItem($product_id = 0, $instance = 0, $quantity = 0) {
	$result = new stdClass;
	settype($product_id, 'integer');
	settype($instance, 'integer');
	settype($quantity, 'float');
	$tp = $this->mysqli->table_prefix;

	if($instance) {
		$item = $this->mysqli->arrayData(array(
			'source' => "{$tp}invoice_items",
			'where' => "id = '{$instance}'"
		))->data;
		if(count($item)) {
			$product_id = $item[0]->product;
			$quantity = ($quantity ? $quantity : $item[0]->quantity);
		}
	}
	if(!$product_id) {
		$result->success = false;
	}
	else {
		$quantity = ($quantity ? $quantity : 1);
		$result = $this->addItem(array(
			'id' 		=> $product_id,
			'quantity' 	=> -$quantity
		));
		if(count($item)) {
			$this->editItem(array(
				'discount'	=> $item[0]->discount,
				'id'  		=> $result->id,
				'pricePer'	=> $item[0]->pricePer
			));
			$this->mysqli->saveToDb(array(
				'table' => "{$tp}returned_items",
				'fields' => array(
					'original_invoice_item' => $instance,
					'credited_sale_item' => $result->id
				),
				'insert' =>  true
			));
		}
	}
	$this->setDirty();
	return $result;
}


public function setDirty() {
	$this->hasLoaded = false;
	$this->invoicesHaveLoaded = false;
	$this->itemsHaveLoaded = false;
	$this->itemsTotalHasBeenDetermined = false;
	$this->discountsHaveLoaded = false;
	$this->totalHasBeenDetermined = false;
	$this->sharesHaveBeenDetermined = false;
	$this->paymentsHaveLoaded = false;
	$this->paymentsHaveBeenDistributed = false;
	$this->paymentTotalsHaveBeenCalculated = false;
	$this->tradersHaveLoaded = false;
}


public function sortObjects(&$objects, $property, $order = 'ASC') {
    $comparer = ($order === 'DESC')
        ? "return -strcmp(\$a->{$property},\$b->{$property});"
        : "return strcmp(\$a->{$property},\$b->{$property});";
    usort($objects, create_function('$a,$b', $comparer));
}


// Split discounts between traders
/****************************************/
//	$CollectivePOS:	this CollectivePOS object:
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful
//		msg	(string): message that explains the success parameter
//		data: stdClass object with integer properties corresponding to each traders id
//			$traderId: stdClass object with properties:
//				items: array of stdClass Sale items
//				itemsTotal: (number)
//				itemsProportion: (number)
//				proportion: (number) equals itemsProportion
//				total: (number) equals total
//				tax: array of stdClass stdClass objects as Tax
// 					id (integer)
// 					taxName (string)
// 					taxRate (number)
//					taxBasis (number) The GROSS foundation for calculating tax
//				discounts: array of stdClass objects
// 					effectiveDiscount
// 					description
// 					discountRate
private function split($CollectivePOS) {

	$result = $this->splitItems();
	if(!$result->success) {
		return $result;
	}
	
	$discounts = $this->getDiscounts();
	if(!$discounts->success) {
		return $result;
	}

	foreach( $discounts->data as $element => $discount ) {

		// Establish $affectedSharesTotal
		//	(The summarised share of all the traders that are affected by this discount)
		// and $affectedSharesTotal
		//	(The number of traders that are affected by this discount)
		$affectedSharesTotal = 0;
		$affectedTradersCount = 0;
		foreach($this->shares as $traderID => $share) {
			foreach( $discount->traders as $discounttrader ) {
				if ( $discounttrader->id == $traderID ) {
					$affectedSharesTotal = bcadd($affectedSharesTotal, $share->itemsTotal, 6);
					$affectedTradersCount += 1;
				}
			}
		}


		foreach( $this->shares as $traderID => $share ) {
			$trader = new Trader($traderID);
			
			// $discountShare has properties
			//	effectiveDiscount
			//	description
			//	discountRate
			
			$discountShare = new stdClass;
			
			foreach( $discount->traders as $discounttrader ) {
				if ($discounttrader->id == $traderID) {
					$discountShare->effectiveDiscount
					= bcmul($discount->discountRate, $share->itemsTotal, 6);
					
					$share->total
					= bcsub($share->total, bcmul($discount->discountRate, $share->itemsTotal, 6), 6);
					
					if(
						$CollectivePOS->preferences->discounts_shared_proportionally
						and $affectedSharesTotal
					) {
						$discountShare->effectiveDiscount
						= bcadd(
							$discountShare->effectiveDiscount,
							bcmul(
							$discount->value, bcdiv(
								$share->itemsTotal,
								$affectedSharesTotal,
								6
							), 6),
							6
						);
						$share->total
						= bcsub(
							$share->total,
							bcmul(
								$discount->value,
								bcdiv(
									$share->itemsTotal,
									$affectedSharesTotal,
									6
								),
								6
							),
							6
						);
					}
					else if( $affectedTradersCount ) {
						$discountShare->effectiveDiscount
						= bcadd(
							$discountShare->effectiveDiscount,
							bcdiv(
								$discount->value,
								$affectedTradersCount,
								6
							),
							6
						);
						$share->total
						= bcsub(
							$share->total,
							bcdiv(
								$discount->value,
								$affectedTradersCount,
								6
							),
							6
						);
					}
					
					$total = $this->getTotal()->data;
					$share->proportion
					= bcdiv($share->total, ($total != 0 ? $total : 1), 6);				

					$discountShare->description = $discount->description;
					$discountShare->discountRate = $discount->discountRate;
					
					arsort($share->tax);

					$remaining_discount = $discountShare->effectiveDiscount;
					
					if($trader->preferences->manage_tax) {
						foreach( $share->tax as $tax_id => $tax ) {
						
							if( $tax->taxBasis > $remaining_discount ) {
								$share->tax[$tax_id]->taxBasis
								= bcsub($share->tax[$tax_id]->taxBasis, $remaining_discount, 6);
								$remaining_discount = 0;
							}
							else {
								$remaining_discount
								= bcsub($remaining_discount, $tax->taxBasis, 6);
								$share->tax[$tax_id]->taxBasis = 0;
							}
						}
					}
				}
			}
			$share->discounts[] = $discountShare;
		}
	}
	
	$result->data = $this->shares;
	return $result;
}


// Split items between traders
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful
//		msg	(string): message that explains the success parameter
//		data: stdClass object with integer properties corresponding to each traders id
//			$traderId: stdClass object with properties:
//				items: array of stdClass Sale items
//				itemsTotal: (number)
//				itemsProportion: (number)
//				proportion: (number) equals itemsProportion
//				total: (number) equals total
//				tax: array of stdClass stdClass objects as Tax
// 					id (integer)
// 					taxName (string)
// 					taxRate (number)
//					taxBasis (number) The GROSS foundation for calculating tax

private function splitItems() {
	$this->shares = new stdClass;

	foreach($this->getItems()->data as $item) {
		// an item has properties:
		// 		product: Product object
		// 		productCode: (string) the products product code
		// 		description: (string) description or product name of the item
		// 		quantity: (float) Quantity
		// 		unit: (string) The unit of which the product is sold
		// 		pricePer: (float) The price per item
		// 		discount: (float) The discount rate to multiply item price by
		// 		price: Total for this item(s) after discount
	
		$traderId = $item->product->getTrader()->data->id;
		settype($this->shares->{$traderId}, 'object');
		settype($this->shares->{$traderId}->items, 'array');
		settype($this->shares->{$traderId}->tax, 'array');
		settype($this->shares->{$traderId}->itemsTotal, 'string');
		settype($this->shares->{$traderId}->total, 'string');
		settype($this->shares->{$traderId}->itemsProportion, 'string');
		settype($this->shares->{$traderId}->proportion, 'string');

		$product = $item->product;
		$this->shares->{$traderId}->items[] = $item;
		
		// Tax management
		if( $product->getTrader()->data->preferences->manage_tax ) {
		
			$tax = $product->getTax()->data;
			
			if(!$tax) {
				throw new Exception("{$item->productCode} {$item->description} has not been assigned to at Tax Group");
			}
			
			$taxId = $product->getTax()->data->id;
			
			// if this tax is not already added to this share,
			//	it will either be added or removed
			//	or removed from this share
			if( !isset( $this->shares->{$traderId}->tax[$taxId] ) ) {
				$this->shares->{$traderId}->tax[$taxId] = clone $tax;
			}

			$this->shares->{$traderId}->tax[$taxId]->taxBasis
			= bcadd(
				@$this->shares->{$traderId}->tax[$taxId]->taxBasis,
				$item->price,
				6
			);
		}
	}

	foreach( $this->shares as $traderId => $share ) {
	
		foreach($share->items as $element => $item) {
			$share->total = bcadd(
				$share->total,
				$item->price,
				6
			);
		}
		$share->itemsTotal = $share->total;

		$itemsTotal = $this->getItemsTotal()->data;
		
		$share->proportion
		= $share->itemsProportion
		= bcdiv(
			$share->itemsTotal,
			( $itemsTotal != 0 ? $itemsTotal : 1 ),
			6
		);
	}
	unset($itemsTotal);
	
	$this->sharesHaveBeenDetermined = true;
	return (object) array(
		'success' => true,
		'data' => $this->shares
	);
}


// Extra function rounding of numbers 
public function x_round($number, $scale = 0) {
	if($scale < 0) $scale = 0;
	$sign = '';
	if(bccomp('0', $number, 64) == 1) $sign = '-';
	$increment = $sign . '0.' . str_repeat('0', $scale) . '5';
	$number = bcadd($number, $increment, $scale+1);
	return bcadd($number, '0', $scale);
}


}

?>