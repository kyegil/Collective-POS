<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
This version 2014-07-12
**********************************************/

class Invoice extends DatabaseObject {

protected $mysqli; // The MySQLi connection
protected $hasLoaded = false; // True if this Invoice has been loaded from the database
protected $items = array(); // array Items in this invoice
protected $itemsHaveLoaded = false;
protected $itemsTotal; // Total for the sold and returned items before overall discounts
protected $itemsTotalHasBeenDetermined = false; // bool wether items total is available
protected $sale; // Sale object / null - The Sale that has produced this Invoice
protected $saleHasLoaded = false; // bool - True if the Sale object has been set (even if null)
protected $saleId; // int Id of the sale that has produced this invoice
protected $taxes; // null/array - Different taxes occuring in this invoice
protected $traderHasLoaded = false; // bool - True if the Trader object has been set (even if null)
public $traderId; // int - Id of the trader to whom this invoice belongs
public $created; // integer Time when invoice was initially created
public $customerId; // integer Customer ID if known
public $date; //	date object Invoice date
public $id; //	integer Identificator for this invoice as stored in the DB
public $number; //	integer/string Invoice number
public $taxInvoice;	// boolean Wether this invoice includes taxes
public $total = 0; // float Total for this invoice

function __construct($config = null) {
	if($config instanceof Trader) {
		$this->traderId = $config->id;
	}
	else if(is_object($config)) {
		$this->id = (int)$config->id;
		if($config->trader instanceof Trader) {
			$this->traderId = $config->trader->id;
		}
		else {
			$this->traderId = (int)$config->trader;
		}
	}
	else if(is_array($config)) {
		$this->id = (int)@$config['id'];
		if($config['trader'] instanceof Trader) {
			$this->traderId = $config['trader']->id;
		}
		else {
			$this->traderId = (int)@$config['trader'];
		}
	}
	else if($config) {
		$this->id = (int)$config;
	}

	parent::__construct( $this->id );
	$tp = $this->mysqli->table_prefix;

	settype($config, 'object');
	if($this->id) {
		$attempt_to_load = $this->load();
		if(!$attempt_to_load->success) {
			throw new Exception($attempt_to_load->msg);
		}
	}
	else if($config->create) {
		$this->create($config);
	}
}


// Add an item to the invoice
/****************************************/
//	$config:	object with properties:
//		discountDescription: (string) description of the discount
//		discountRate: (number) Discount rate as decimal if applicable
//		effectiveValue: (number) The actual value of the discount
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Sale object
function addDiscount($discount) {
	settype($discount, 'object');
	$tp = $this->mysqli->table_prefix;
	$result = (object)array(
		'success' => true,
		'msg'=> ""
	);

	if(!$this->hasLoaded) {
		$result = $this->load();
		if(!$result->success) {
			return $result;
		}
	}
	
	$result = $this->mysqli->saveToDb(array(
		'id' => (int)$this->id,
		'table' => "{$tp}invoice_discounts",
		'fields' => array(
			'invoiceId' => $this->id,
			'discountDescription' => $discount->discountDescription,
			'discountRate' => $discount->discountRate,
			'effectiveValue' => $discount->effectiveValue
		),
		'insert' => true,
		'returnQuery' => true
	));
	return $result;	
}


// Add an item to the invoice
/****************************************/
//	$config:	object with properties:
//		product:	Product object or Id. 0 or null for general item
//		description: (string) Descripton of item. Defaults to product name
//		quantity: (number)	Quantity invoiced
//		unit: (string) Unit of measuring quantity
//		pricePer: (number) item price per unit
//		discount: (number) discount rate (0-1) to deduct from price per item
//		price: (number) line total after item discount
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Sale object
function addItem($item) {
	settype($item, 'object');
	$tp = $this->mysqli->table_prefix;
	$result = (object)array(
		'success' => true,
		'msg'=> ""
	);

	if(!$this->hasLoaded) {
		$result = $this->load();
		if(!$result->success) {
			return $result;
		}
	}
	
	if($item->product instanceof Product) {
		$product = $item->product;
	}
	else {
		$product = new Product($item->product);
	}

	$result = $this->mysqli->saveToDb(array(
		'id' => (int)$this->id,
		'table' => "{$tp}invoice_items",
		'fields' => array(
			'invoiceId' => $this->id,
			'product' => $product->id,
			'productCode' => $product->productCode,
			'description' => ($item->description ? $item->description : $product->name),
			'quantity' => $item->quantity,
			'unit' => $product->unit,
			'originalPrice' => $product->price,
			'pricePer' => $item->pricePer,
			'discount' => $item->discount,
			'price' => $item->price,
			'tax' => ($this->taxInvoice ? $product->getTax()->data->id : 0)
		),
		'insert' => true,
		'returnQuery' => true
	));
	return $result;	
}


/****************************************/
//	$config:	array with the following possible keys:
//		trader (int or object): required. The trader who issues the invoice.
//		sale (int or object): The sale (if any) that creates the invoice.
//		date (string or DateTime object): Invoice date. Defaults to today UTC time.
//		items (array) of: Items in this invoice:
//			product (object): Required. The Product.
//			description (string): The Products name or other description. Defaults to the product name.
//			quantity (float): Required. The invoiced quantity of this product.
//			pricePer (float): The actual price of the product in this invoice.
//			discount (float): The % discount on this product given as decimal number.
//			price (float): Final price of this product.
//		discounts (array): Overall discounts in this invoice:
//			description (string): Description or reason for the discount.
//			discountRate (float): The % discount rate as decimal number.
//			effective_discount (float): The real value of the discount wether in percentage or not.
//		tax (array): Taxes in this invoice:
//			id (int): Id of this tax.
//			taxName (string): The tax name.
//			taxRate (float): The tax rate given as decimal number.
//			tax_basis (float): The sum that makes up the basis of this tax.
//			tax_value (float): The actual tax value.
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		sql	(string): The last mysql query in the process
function create($config) {

	settype($config, 'object');
	$tp = $this->mysqli->table_prefix;

	// Get the trader
	if($config->trader instanceof Trader) {
		$this->trader = $config->trader;
		if($this->traderId = $config->trader->id) {
			$this->traderHasLoaded = true;
		}
	}
	else if ((int)$config->trader){
		$this->traderId = (int)$config->trader;
	}
	else {
		throw new Exception('Trader not given for invoice');
	}

	
	// Get the sale
	if($config->sale instanceof Sale) {
		$sale = $config->sale;
		$this->saleId = $sale->id;
		$this->saleHasLoaded = true;
	}
	else {
		$this->saleId = (int)$config->sale;
		$this->saleHasLoaded = false;
	}


	// Get the invoice date
	if($config->date instanceof DateTime) {
		$this->date = clone $config->date;
	}
	else {
		$this->date = new DateTime( @$config->date, new DateTimeZone('UTC') );
	}	
	$this->date->setTimezone(new DateTimeZone('UTC'));


	//	Check if this invoice should charge taxes
	$this->taxInvoice = (bool)$this->getTrader()->data->preferences->manage_tax;


	// Save to the invoices table in db and create the Invoice number
	$creation = $this->mysqli->saveToDb(array(
		'id'			=> (int)$this->id,
		'table'			=> "{$tp}invoices",
		'insert'		=> true,
		'fields'		=> array(
			'trader'		=> $this->getTrader()->data->id,
			'sale'			=> $this->getSale()->data->id,
			'date'			=> $this->date->format("Y-m-d"),
			'taxInvoice'	=> $this->taxInvoice
		)
	));
	
	if( !$creation->success ) {
		throw new Exception("Unable to create Invoice. Mysqli error: {$creation->msg}");
	}
	
	$this->id = $creation->id;
	
	if( !$this->id ) {
		throw new Exception("Unable to create Invoice");
	}

	$this->mysqli->saveToDb(array(
		'id' => (int)$this->id,
		'table' => "{$tp}invoices",
		'update' => true,
		'insert' => false,
		'fields' => array(
			'invoiceNo' => (
				@$this->getTrader()->data->preferences->invoicenumber_format
				? sprintf( $this->getTrader()->data->preferences->invoicenumber_format, $this->id )
				: $this->id
			)
		),
		'where' => "id = '$this->id'"
	));

	if(is_array($config->items)) {
		foreach($config->items as $item) {
			settype($item, 'object');
			
			//	Add only products from sale belonging to this trader
			if($item->product->getTrader()->data->id == $this->getTrader()->data->id) {
			
				// Save each item connected to the invoice			
				$this->mysqli->saveToDb(array(
					'table' => "{$tp}invoice_items",
					'insert' => true,
					'fields' => array(
						'invoiceId' => $this->id,
						'product' => $item->product->id,
						'productCode' => $item->product->productCode,
						'description' => ($item->description ? $item->description : $item->product->name),
						'quantity' => $item->quantity,
						'unit' => $item->product->unit,
						'cost' => ($item->product->cost * $item->quantity),
						'originalPrice' => $item->product->price,
						'pricePer' => $item->pricePer,
						'discount' => $item->discount,
						'price' => $item->price,
						'tax' => ($this->taxInvoice ? $item->product->getTax()->data->id : 0)
					)
				));
				
				//	Update $this->taxes[taxId]->tax_basis based on the item
				//	each element should contain a stdClass object with:
				//		id: integer, the tax id,
				// 		taxName string
				// 		taxRate number
				//		tax_basis number, this tax's basis on this invoice
				if( $this->taxInvoice ) {
					if( !isset($this->taxes[$item->product->getTax()->data->id]) ) {
						$this->taxes[$item->product->getTax()->data->id]
						= clone $item->product->getTax()->data;
					}
					settype($this->taxes[$item->product->getTax()->data->id]->tax_basis, "string");
					
					$this->taxes[$item->product->getTax()->data->id]->tax_basis
					+= (
						$item->price /
						(1 + $item->product->getTax()->data->taxRate)
					);
				}
				
			}
		}
		
		
		// This will link the new invoice to any returns
		$this->mysqli->query("
			UPDATE {$tp}returned_items
			INNER JOIN {$tp}sale_items ON {$tp}returned_items.credited_sale_item = {$tp}sale_items.id
			INNER JOIN (SELECT sale, invoiceId, product, {$tp}invoice_items.id FROM {$tp}invoices INNER JOIN {$tp}invoice_items ON {$tp}invoices.id = {$tp}invoice_items.invoiceId) AS invoice_items ON 
			{$tp}sale_items.product = invoice_items.product AND {$tp}sale_items.sale = invoice_items.sale
			SET {$tp}returned_items.credited_invoice_item = invoice_items.id
			WHERE invoice_items.sale = '{$this->saleId}' AND !{$tp}returned_items.credited_invoice_item
		");
	}

	//	Apply any discounts to the invoice
	if(is_array($config->discounts)) {

		foreach($config->discounts as $discount) {
			settype($discount, 'object');

			// Save the discount to the database
			$this->mysqli->saveToDb(array(
				'table' => "{$tp}invoice_discounts",
				'insert' => true,
				'fields' => array(
					'invoiceId' => $this->id,
					'discountDescription' => @$discount->description,
					'discountRate' => @$discount->discountRate,
					'effectiveValue' => @$discount->effectiveDiscount
				)
			));
			$remainingDiscount = @$discount->effectiveDiscount;
			
			// The discount should also be applied to the relevant tax basis
			if( $this->taxInvoice ) {
				foreach($this->taxes as $tax_id => $tax) {
					$netDiscount = $remainingDiscount / (1 + $tax->taxRate );
				
					if($tax->tax_basis >= $netDiscount) {
						$this->taxes[$tax_id]->tax_basis -= $netDiscount;
						$remainingDiscount = 0;
					}
					else {
						$remainingDiscount -= $tax->tax_basis * (1 + $tax->taxRate );
						$this->taxes[$tax_id]->tax_basis = 0;
					}
				}
			}			
		}
	}
	
	// Save the relevant taxes to the database
	if(is_array($this->taxes)) {
		foreach($this->taxes as $tax) {
			settype($tax, 'object');
			$this->mysqli->saveToDb(array(
				'table' => "{$tp}invoice_tax",
				'insert' => true,
				'fields' => array(
					'invoiceId' => $this->id,
					'tax_id' => $tax->id,
					'tax_name' => $tax->taxName,
					'tax_rate' => $tax->taxRate,
					'tax_basis' => $tax->tax_basis,
					'tax_value' => bcmul($tax->taxRate, $tax->tax_basis, 6)
				)
			));
		}
	}
	$this->load();
	$this->saveTotal();
	return $this;
}


// Get discounts in this invoice
/****************************************/
//	--------------------------------------
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: array of stdClass items with the following properties:
//			id: (int) ID of discount as saved in DB
//			invoiceId: (int) same as $this->id
//			discountDescription: (string) description of the discount
//			discountRate: (float) Discount rate as decimal if applicable
//			effectiveValue: (float) The actual value of the discount
function getDiscounts() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}invoice_discounts",
		'where' => "invoiceId = '$this->id'",
		'orderfields' => "id"
	));
	return $result;
}


// Get items in this invoice
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
//			originalPrice: (float) The original price of this item
//			reduction: (float) The reduction in price from the original price
//			pricePer: (float) The price per item
//			discount: (float) The discount rate to multiply item price by
//			price: Total for this item(s) after discount
function getItems($update = false) {
	if($this->itemsHaveLoaded and $this->itemsTotalHasBeenDetermined and !$update) {
		$result = (object)array(
			'success' => true
		);
		$result->data = $this->items;
		return $result;
	}
	else {
		return $this->loadItems();
	}
}


// Get Items in this invoice OUTDATED
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: array of stdClass invoice items with the following properties:
//			product: Product object
//			productCode: (string) the products product code
//			description: (string) description or product name of the item
//			quantity: (float) Quantity
//			unit: (string) The unit of whch the product is sold
//			originalPrice: (float) The original price of this item
//			reduction: (float) The reduction in price from the original price
//			pricePer: (float) The price per item
//			discount: (float) The discount rate to deduct from price per item
//			price: (float) Total for this item(s) after discount
//			tax: 
// function getItems() {
// 	$tp = $this->mysqli->table_prefix;
// 	$result = $this->mysqli->arrayData(array(
// 		'source' => "{$tp}invoice_items",
// 		'where' => "invoiceId = '$this->id'"
// 	));
// 	foreach($result->data as $id => $item) {
// 		$result->data[$id]->product = new Product($item->product);
// 		settype($result->data[$id]->quantity, (($result->data[$id]->product->floating and $result->data[$id]->quantity = (int)$result->data[$id]->quantity) ? "string" : "integer"));
// 		$result->data[$id]->reduction = bcsub($item->originalPrice, $item->pricePer, 6);
// 	}
// 	return $result;
// }


// Get items total
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		data: Sale Items Total
function getItemsTotal($update = false) {
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
			$result->data = $this->itemsTotal;
		}
	}
	return $result;
}


// Get this invoice's share of payment charges
/****************************************/
//	$paymentMethod:	(int) Payment method to return. Defaults to null.
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: (number) This invoice's share of payment charges in this sale. If payment method is given only charges that have occurred using this payment method are returned.
function getPaymentCharges($paymentMethod = NULL) {
	if($paymentMethod instanceof PaymentMethod) {
		$paymentMethod = $paymentMethod->id;
	}
	$payments = $this->getShareOfPayments();
	if($payments->success) {
		return $payments;
	}
	$cost = 0;
	foreach($payments->payments as $payment) {
		if($paymentMethod == null or $payment->payment->paymentMethod->id == $paymentMethod) {
			$cost = $payment->payment->getCost();
			if (!$cost->success) return $cost;
			$total = bcadd($cost, $payment->amount, $cost->data, 6);
		}
	}
	return (object) array(
		'success' => true,
		'data' => $total
	);
}


// Get the sale from which this invoice has been created
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Sale object
function getSale() {
	
	$result = (object)array(
		'success' => true
	);

	if($this->saleHasLoaded) {
		$result->data = $this->sale;
		return $result;
	}
	$result->data = $this->sale = new Sale($this->saleId);
	if(!$result->data->id) {
		$this->sale = null;
		$result->success = false;
		$result->msg = "err Sale with ID '{$this->saleId}' does not exist in the system";
		return $result;
	}
	$this->saleHasLoaded = true;
	return $result;	
}


// Return this invoice's share of the payments in this sale
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful
//		msg	(string): message that explains the success parameter
//		total (number): Total amount distributed to this trader
//		payments: stdClass object with properties corresponding to each payments id
//			$paymentId: stdClass object with properties:
//				payment: Payment object
//				amount: The traders share of the payment
function getShareOfPayments() {
	$sale = $this->getSale();
	$trader = $this->getTrader()->data;
	if(!$sale->success) {
		return $sale;
	}
	return $sale->data->getTradersShareOfPayments($trader);
}


// Return this invoice's share of the sale
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		share: stdClass object with properties:
// 			items: array of stdClass Sale items
// 				product: Product object
// 				productCode: (string) the products product code
// 				description: (string) description or product name of the item
// 				quantity: (float) Quantity
// 				unit: (string) The unit of which the product is sold
// 				pricePer: (float) The price per item
// 				discount: (float) The discount rate to multiply item price by
// 				price: Total for this item(s) after discount
// 			tax: array of tax stdClass objects:
// 				id (integer)
// 				taxName (string)
// 				taxRate (number)
// 			itemsTotal: This traders total for items before overall discounts
// 			itemsProportion: This traders proportion of items total
// 			discounts: array of stdClass objects: (ONLY IF NOT COMPLETED)
// 				effectiveDiscount: This traders share of the effective discount value
// 				description: Description of this discount
// 				discountRate: % Discount rate if applicable
// 			total: This traders total of the sale
// 			proportion: This traders proportion of sales total
function getShareOfSaleNEW() {
	$sale = $this->getSale();
	if(!$sale->success) {
		return $sale;
	}
	$shares = $sale->data->getShares();
	if($shares->success) {
		return $shares->data->{$this->getTrader()->data->id};
	}
	else {
		return $shares;
	}
}


/****************************************/
//	$includePrepayments: (bool)	Should calculation include gift vouchers (prepayments)?
//	--------------------------------------
//	return: (float) Returns this invoice's share of the total sale.
function getShareofSale($includePrepayments = false) {
	$sale = $this->getSale()->data;
	if (!$sale) return 1;
	
	$tradersTotal = $this->total;
	$salesTotal = $sale->getTotal()->data;
	
	if($includePrepayments) {
		$vouchers = $sale->getIssuedVouchers();
		foreach($vouchers as $voucher) {
			if($voucher->prepaymentholdingTrader->id == $this->traderId) {
				$tradersTotal = bcadd($tradersTotal, $voucher->value, 6);
			}
			$salesTotal = bcadd($salesTotal, $voucher->value, 6);
		}
	}
	
	if (!$sale->getTotal()->data) {
		return 0;
	}
	return bcdiv($tradersTotal, $salesTotal, 6);
}


function getSorCostsTotal() {
	$tp = $this->mysqli->table_prefix;
	$data = $this->mysqli->arrayData(array(
		'source' => "{$tp}invoice_items LEFT JOIN {$tp}products ON {$tp}invoice_items.product = {$tp}products.id",
		'where' => "invoiceId = '$this->id' AND {$tp}products.sor",
		'fields' => "SUM({$tp}products.cost * {$tp}invoice_items.quantity) AS cost"
	));
	return $data->data[0]->cost;
}


function getSorItems() {
	$tp = $this->mysqli->table_prefix;
	$data = $this->mysqli->arrayData(array(
		'source' => "{$tp}invoice_items LEFT JOIN {$tp}products ON {$tp}invoice_items.product = {$tp}products.id",
		'where' => "invoiceId = '$this->id' AND {$tp}products.sor",
		'fields' => "{$tp}invoice_items.quantity, {$tp}products.id"
	));
	foreach($data->data as $element => $item) {
		$data->data[$element]->product = new Product(array(
			'id' => $item->id
		));
	}
	return $data->data;
}


// Get the taxes occurring in this invoice
/****************************************/
//  $update (bool): Force taxes to reload
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: array of stdClass objects. Properties:
// 			tax_id: (int) Internal id for this tax
// 			tax_name: (string) Name of this tax
// 			tax_rate: (number) The tax rate
// 			tax_basis: (number) The basis value to which the tax has been added
// 			tax_value: (number) The tax amount
function getTaxes($update = false) {
	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => null
	);
	if(is_array($this->taxes) and !$update) {
		$result->data = $this->taxes;
		return $result;
	}
	else {
		$result = $this->loadTaxes();
		if($result->success) {
			$result->data = $this->taxes;
		}
		return $result;
	}
}


function getTaxTotal() {
	$tp = $this->mysqli->table_prefix;
	if($this->taxInvoice) {
		$data = $this->mysqli->arrayData(array(
			'source' => "{$tp}invoice_tax",
			'where' => "invoiceId = '$this->id'",
			'fields' => "SUM(tax_value) AS tax"
		));
		return (float)$data->data[0]->tax;
	}
	else return 0;
}


// Get the trader to whom this invoice belongs
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Trader object
function getTrader() {
	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => null
	);

	if(!class_exists("Trader")) {
		$result->success = false;
		$result->msg = "err 'Trader' class has not been loaded";
		return $result;
	}
	if($this->traderHasLoaded) {
		$result->data = $this->trader;
		return $result;
	}
	$result->data = $this->trader = new Trader($this->traderId);
	if(!$result->data->id) {
		$this->trader = null;
		$result->success = false;
		$result->msg = "err Trader with ID '{$this->traderId}' does not exist in the system";
		return $result;
	}
	$this->traderHasLoaded = true;
	return $result;	
}


function load() {
	$result = (object)array(
		'success' => true,
		'msg'=> ""
	);

	$tp = $this->mysqli->table_prefix;
	$this->total = 0;

	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}invoices
		LEFT JOIN {$tp}invoice_items ON {$tp}invoices.id = {$tp}invoice_items.invoiceId",
		'fields' => "{$tp}invoices.*, SUM({$tp}invoice_items.price) AS itemsTotal",
		'groupfields' => "{$tp}invoices.id",
		'where' => "{$tp}invoices.id = '$this->id'"
	));
	if(!$result->success) {
		return $result;
	}
	if($result->totalRows) {
		$this->date = new DateTime($result->data[0]->date, new DateTimeZone('UTC'));
		$this->number = $result->data[0]->invoiceNo;
		$this->saleId = $result->data[0]->sale;
		$this->taxInvoice = $result->data[0]->taxInvoice;
		$this->total = $this->itemsTotal = $result->data[0]->itemsTotal;
		$this->traderId = $result->data[0]->trader;
	}
	else {
		$result->success = false;
		$result->msg = "err Invoice class invoice with id '{$this->id}' was not found";
		return $result;
	}
	
	// Load discounts in this invoice
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}invoice_discounts",
		'fields' => "SUM({$tp}invoice_discounts.effectiveValue) AS discountsTotal",
		'groupfields' => "{$tp}invoice_discounts.invoiceId",
		'where' => "{$tp}invoice_discounts.invoiceId = '$this->id'"
	));
	if($result->totalRows) {
		$this->total -= $result->data[0]->discountsTotal;
		$this->hasLoaded = true;
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
//			originalPrice: (float) The original price of this item
//			reduction: (float) The reduction in price from the original price
//			pricePer: (float) The price per item
//			discount: (float) The discount rate to multiply item price by
//			price: Total for this item(s) after discount
function loadItems() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}invoice_items",
		'where' => "invoiceId = '$this->id'"
	));
	if($result->success) {
		$result->total = 0;
		foreach($result->data as $id => $item) {
			settype($result->data[$id]->quantity, 'float');
			settype($result->data[$id]->pricePer, 'float');
			settype($result->data[$id]->discount, 'float');
			settype($result->data[$id]->price, 'float');
			$result->total = bcadd($result->total, $item->price, 6);
			$result->data[$id]->product = new Product($item->product);
			$result->data[$id]->reduction = bcsub($item->originalPrice, $item->pricePer, 6);
		}
		$this->items = $result->data;
		$this->itemsHaveLoaded = true;
		$this->itemsTotal = $result->total;
		$this->itemsTotalHasBeenDetermined = true;
	}
	return $result;
}


// Load the taxes into the taxes property
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Array of stdClass objects with properties:
// 			tax_id: (int) Internal id for this tax
// 			taxName: (string) The name of the tax
// 			taxRate: (number) Render-name for the attribute
// 			tax_basis: (number) The total being taxed by this tax
// 			tax_value: (number) The amount of tax being imposed
protected function loadTaxes() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => $this->mysqli->table_prefix."invoice_tax",
		'where' => "invoiceId = '$this->id'",
		'orderfields' => "id"
	));
	if($result->success) {
		$this->taxes = $result->data;
		$this->taxesHaveLoaded = true;
	}
	return $result;	
}


function saveTotal() {
	$tp = $this->mysqli->table_prefix;
	$edit = $this->mysqli->saveToDb(array(
		'table' => "{$tp}invoices",
		'where' => "id = '{$this->id}'",
		'fields' => array(
			'itemsTotal' => $this->itemsTotal,
			'total' => $this->total
		),
		'insert' => false,
		'update' => true,
		'returnQuery' => true
	));
	return $edit;
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