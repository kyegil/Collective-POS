<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/

class Trader {

protected $hasLoaded = false;
public $mysqli;	//	The DB connection
protected $collectivePos;	//	The current instance of the CollectivePOS class
public $id; //	Identificator for this trader as stored in the DB
public $traderCode; //	Trader prefix or short identifier
public $name; //	Trader name
public $address; //	Trader address
public $legal_identifier; // ie tax or business number
public $phone; // Traders phone number
public $mobile; // Traders mobile number
public $fax; // Traders fax number
public $email; // Traders email address
public $website; // Traders website
public $preferences; // This traders preferences

public function __construct($id = null) {
	global $mysqliConnection, $docu;
	$this->mysqli = $mysqliConnection;
	$this->collectivePos = $docu;
	$this->preferences = new stdClass;
	$this->id = (int)$id;
	
	if($this->id != null) {
		$this->load();
	}
}


/*	Export this traders stock
Exports the stock
******************************************
$config:		(stdClass) Configurations:
	->fields: 		(stdClass) Field names to be exported
					Each field can have the following values:
					NULL: The fields is mapped directly to the DB field with the same name
					string:
						starting with '=': Formula
						default: The name of the DB field this field should map to
	->type:	(stdClass) The type of export and settings related to this
		->format:		(string) The export format. Defaults to 'csv'
		->csvDelimiter:	(string) The delimiter for csv format. Defaults to ','
		->csvEnclosure:	(string) The field enclosure for csv format. Defaults to '"'
		->csvEscape:	(string) The escape character for csv format. Defaults to '\'
		->csvIncludeHeaders: (bool) Include field headers in the csv file
	->dateFormat:		(string) The format of date/time values.
								Defaults to 'c' (2004-02-12T15:19:21+00:00)
	->decimalSeparator:	(string) The decimal separator for numeric values. Defaults to '.'
	->charset: (string) The character set of the file. Defaults to 'UTF-8'
	->file:	(required string) The file name of the export file
	->path:	(string) 
	->target: (stdClass):
		->transfer:	(string) 'direct', 'feed', 'local' or 'ftp',
		->path:	(string) filepath for local or ftp transfers
		->server: (string) remote server name for ftp transfers
		->port: (string) remote server port for ftp transfers
		->user: (string) username for ftp transfers
		->password: (string) user password for ftp transfers
	->filter: (string)	Filter expression for what products to include
------------------------------------------
return:			(void) 
*/
public function exportStock( $config ) {
	$tp = $this->mysqli->table_prefix;
	settype($config,	'object');
	settype($config->fields,	'object');
	settype($config->type,		'object');
	settype($config->target,	'object');
	
	if(!isset( $config->file )) {
		throw new Exception("File name required");
	}
	
	if( !isset($config->charset) ) {
		$config->charset = "UTF-8";
	}
	if( !isset($config->type->format) ) {
		$config->type->format = "csv";
	}

	if( $config->type->format == "csv" ) {
		$config->type->csvDelimiter
			= isset($config->type->csvDelimiter) ? $config->type->csvDelimiter : ",";
		$config->type->csvEnclosure
			= isset($config->type->csvEnclosure) ? $config->type->csvEnclosure : '"';
		$config->type->csvEscape
			= isset($config->type->csvEscape) ? $config->type->csvEscape : "\\";
	}
	if( !isset($config->target->transfer) ) {
		$config->target->transfer = "direct";
	}
	if( $config->target->transfer == "local" ) {
		if(!isset( $config->target->path )) {
			throw new Exception("File path required");
		}
	}

	
	$result = array();
	
	$stock = $this->mysqli->arrayData(array(
		'source' => "{$tp}products",
		'where' => "trader = '{$this->id}'" . (@$config->filter ? " AND ({$config->filter})" : "")
	));
	
	foreach($stock->data as $index => $record) {
		settype( $result[$index], 'object' );
	
		$attributes = $this->mysqli->arrayData(array(
			'source'	=> "{$tp}product_attributes as product_attributes INNER JOIN {$tp}attributes as attributes ON product_attributes.attribute = attributes.id",
			'fields'	=> "attributes.code, product_attributes.value",
			'where'		=> "product_attributes.product = '{$record->id}'"
		));
		foreach($attributes->data as $attribute) {
			$record->{$attribute->code} = $attribute->value;
		}
	
		foreach( $config->fields as $field => $mapping) {
			if( $mapping === null ) {
				$result[$index]->{$field} = $record->{$field};
			}

			else if ( is_string( $mapping) and strstr( $mapping, '=') ) {
				$result[$index]->{$field} = $this->collectivePos->evaluate( $mapping, $record );
			}
			
			else if ( is_string( $mapping ) ) {
				$result[$index]->{$field} = $record->{$mapping};
			}
		}
	}
	
	if( $config->target->transfer == 'feed' ) {
		if( $config->type->format == 'csv' ) {
			header("Content-type: text/csv; charset={$config->charset}");
			header("Content-Disposition: attachment; filename=\"{$config->file}\"");
		}
	}
	
	if( $config->target->transfer == 'direct' or  $config->target->transfer == 'feed' ) {
		// Open a file to write to
		$file = fopen("php://output", 'w');
	}
	
	if( $config->target->transfer == 'local' ) {
		// Open a file to write to
		
		$file = fopen($config->target->path . '/' . $config->file, 'w');
	}
	
	// Write a Byte order mark to the file for browser to recognise the encoding
	fputs($file, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
	
	if( $config->type->format == 'csv' ) {
		// Write csv header row
		if(@$config->type->csvIncludeHeaders) {
			$headerRow = array();
			foreach(reset($result) as $field => $value) {
				$headerRow[] = ($field);
			}
			fputcsv(
				$file,
				$headerRow,
				$config->type->csvDelimiter,
				$config->type->csvEnclosure,
				$config->type->csvEscape
			);
		}

		// Write the data to the file
		foreach($result as $index => $record) {
			fputcsv(
				$file,
				(array) $record,
				$config->type->csvDelimiter,
				$config->type->csvEnclosure,
				$config->type->csvEscape
			);
		}
	}
	
	fclose($file);
	
}


/****************************************/
//	$from:	DateTime object
//	$to:	DateTime object
//	--------------------------------------
//	return:	float - total sales for given period
public function getCostOfSales($from = NULL, $to = NULL) {
	if($from) {
		$from->setTimezone(new DateTimeZone('UTC'));
	}
	if($to) {
		$to->setTimezone(new DateTimeZone('UTC'));
	}
	$tp = $this->mysqli->table_prefix;
	$a['source'] = "{$tp}invoices AS invoices INNER JOIN {$tp}invoice_items AS invoice_items ON invoices.id = invoice_items.invoiceId";
	$a['fields'] = "SUM(invoice_items.cost) AS cost";
	$a['where'] = "invoices.trader = '{$this->id}'";
	$a['where'] .= ($from ? (" AND invoices.date >= '" . $from->format('Y-m-d H:i:s') . "'") : "");
	$a['where'] .= ($to ? (" AND invoices.date <= '" . $to->format('Y-m-d H:i:s') . "'") : "");
	$result = $this->mysqli->arrayData($a);
	return (float)$result->data[0]->cost;
}


//	Get inventory count
/****************************************/
//	$count:			(int) Inventory count ID
//	$excludeSor:	(bool) Wether to exclude Sale Or Return items
//	--------------------------------------
//	return:	array of stdClass objects:
public function getInventoryCount($count, $excludeSor = false) {
	$tp = $this->mysqli->table_prefix;
	settype($count, 'integer');
	
	$stock = $this->mysqli->arrayData(array(
		'source' => "{$tp}inventory_count INNER JOIN {$tp}products ON {$tp}inventory_count.product_id = {$tp}products.id LEFT JOIN {$tp}suppliers ON {$tp}products.supplier = {$tp}suppliers.id",
		'fields' => "{$tp}products.supplier AS supplierid, {$tp}suppliers.supplier, brand, {$tp}inventory_count.product_id, {$tp}products.productCode, {$tp}products.name, {$tp}inventory_count.date, {$tp}inventory_count.calculated_quantity, {$tp}inventory_count.actual_quantity, {$tp}inventory_count.value_per, ({$tp}inventory_count.value_per * {$tp}inventory_count.actual_quantity) AS value",
		'where' => "{$tp}inventory_count.count_no = '{$count}' AND {$tp}inventory_count.trader = '{$this->id}'" . ($excludeSor ? " AND !{$tp}products.sor" : ""),
		'orderfields' => "{$tp}suppliers.supplier, brand, productCode"
	))->data;
	return $stock;
}

//	Get inventory counts list
/****************************************/
//	--------------------------------------
//	return:	array of stdClass objects:
//		count_id: (int) Count ID
//		count_name (string) Count name
public function getInventoryCounts() {
	$tp = $this->mysqli->table_prefix;
	
	$counts = $this->mysqli->arrayData(array(
		'source' => "{$tp}inventory_count",
		'fields' => "DISTINCT {$tp}inventory_count.count_no, {$tp}inventory_count.count_name, {$tp}inventory_count.complete",
		'where' => "{$tp}inventory_count.trader = '{$this->id}'",
		'orderfields' => "{$tp}inventory_count.count_no"
	));
	return $counts;
}

//	Get inventory count name
/****************************************/
//	$count:			(int) Inventory count ID
//	$excludeSor:	(bool) Wether to exclude Sale Or Return items
//	--------------------------------------
//	return:	array of stdClass objects:
public function getInventoryCountName($count) {
	$tp = $this->mysqli->table_prefix;
	settype($count, 'integer');
	
	return $this->mysqli->arrayData(array(
		'source' => "{$tp}inventory_count",
		'fields' => "count_name",
		'where' => "count_no = '{$count}' AND trader = '{$this->id}'"
	))->data[0]->count_name;
}

//	Returns this trader's invoice in a given sale.
/****************************************/
//	$saleid:	integer
//	--------------------------------------
//	return: Invoice object
public function get_invoice_in_sale($saleid) {
	$tp = $this->mysqli->table_prefix;
	$a['source'] = "{$tp}invoices";
	$a['where'] = "trader = '{$this->id}' AND sale = '{$saleid}'";
	$set = $this->mysqli->arrayData($a);
	$result = null;
	if(count($set->data == 1)) {
		$result = new Invoice(array(
			'id' => $set->data[0]->id
		));
	}
	return $result;
}


/****************************************/
//	$from:	DateTime object
//	$to:	DateTime object
//	--------------------------------------
//	return:	array of Invoice objects - all invoices within given period
public function get_invoices($from = NULL, $to = NULL) {
	if($from) {
		$from->setTimezone(new DateTimeZone('UTC'));
	}
	if($to) {
		$to->setTimezone(new DateTimeZone('UTC'));
	}
	$tp = $this->mysqli->table_prefix;
	$a['source'] = "{$tp}invoices";
	$a['where'] = "trader = '{$this->id}'";
	$a['where'] .= ($from ? (" AND date >= '" . $from->format('Y-m-d H:i:s') . "'") : "");
	$a['where'] .= ($to ? (" AND date <= '" . $to->format('Y-m-d H:i:s') . "'") : "");
	$set = $this->mysqli->arrayData($a);
	$result = array();
	foreach($set->data as $invoice) {
		$result[] = new Invoice(array(
			'id' => $invoice->id
		));
	}
	return $result;
}

/****************************************/
//	$from:	DateTime object
//	$to:	DateTime object
//	--------------------------------------
//	return:	float - total sales for given period
public function get_invoiced_total($from = NULL, $to = NULL) {
	if($from) {
		$from->setTimezone(new DateTimeZone('UTC'));
	}
	if($to) {
		$to->setTimezone(new DateTimeZone('UTC'));
	}
	$tp = $this->mysqli->table_prefix;
	$a['source'] = "{$tp}invoices";
	$a['fields'] = "SUM(total) AS total";
	$a['where'] = "trader = '{$this->id}'";
	$a['where'] .= ($from ? (" AND date >= '" . $from->format('Y-m-d H:i:s') . "'") : "");
	$a['where'] .= ($to ? (" AND date <= '" . $to->format('Y-m-d H:i:s') . "'") : "");
	$result = $this->mysqli->arrayData($a);
	return (float)$result->data[0]->total;
}


/****************************************/
//	$from:	DateTime object
//	$to:	DateTime object
//	--------------------------------------
//	return:	array of DateTime objects - all dates with sales activity
public function getSaleDates($from = NULL, $to = NULL) {
	if($from) {
		$from->setTimezone(new DateTimeZone('UTC'));
	}
	if($to) {
		$to->setTimezone(new DateTimeZone('UTC'));
	}
	$tp = $this->mysqli->table_prefix;
	$dates = $this->mysqli->arrayData(array(
		'source' => "{$tp}invoices",
		'where' => "trader = '{$this->id}' AND date >= '" . $from->format('Y-m-d H:i:s') . "' AND date <= '" . $to->format('Y-m-d H:i:s') . "'",
		'fields' => "date",
		'groupfields' => "date",
	));
	$result = array();
	foreach($dates->data as $date) {
		$result[] = new DateTime($date->date, new DateTimeZone('UTC'));
	}
	return $result;
}


// Get this trader's share of a payment
/****************************************/
//	$payment: (Payment object) The payment
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg	(string): message that explains the success parameter
//		amount: (number) The traders share of the payment
//		proportion: (number) The traders proportion of the payment
//		cost: (number) The traders share of the payment charges
public function getShareOfPayment($payment) {
	$result = (object)array(
		'success' => true,
		'data' => new stdClass
	);
	if (!($payment instanceof Payment)) {
		$payment = new Payment($payment);
	}
	$paymentId = $payment->id;

	if(!$paymentId){
		return (object)array(
			'success' => false,
			'msg'=> "err Trader class getShareOfPayment payment missing"
		);
	}
	$sale = $payment->getSale();
	if(!$sale->success) return $sale;
	
	$data = $sale->data->getSharesOfPayment($payment);
	if(!$data->success) return $data;
	
	$cost = $payment->getCost();
	if(!$cost->success) return $cost;

	$paid = $payment->amount;
	$share = $data->shares->{$this->id}->amount;
	$proportion = $this->x_round(bcdiv($share, ($paid != 0 ? $paid : 1), 6), 5);
	$costshare = bcmul($cost->data, $proportion, 6);

	return (object)array(
		'success' => true,
		'amount' => $share,
		'proportion' => $proportion,
		'cost' => $costshare
	);
}


/****************************************/
//	$sale: Sale object
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
public function getShareOfSale($sale) {
	$shares = $sale->getShares();
	if($shares->success) {
		return $shares->data->{$this->id};
	}
	else {
		return $shares;
	}
}


//	Get all SOR sales as an array
/****************************************/
//	$from:			DateTime object
//	$to:			DateTime object
//	$supplierid:	integer
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: (array of stdClass objects:
//						[supplier]['supplier']				integer
//						[supplier]['suppliername']			string
//						[supplier]['total_costs']			float
//						[supplier]['items']['product']		Product object
//						[supplier]['items']['cost']			number
//						[supplier]['items']['quantity']		number
//						[supplier]['items']['total_costs']	number
public function getSor($from = NULL, $to = NULL, $supplierid = 0) {
	$result = new stdClass;
	if($from) {
		$from->setTimezone(new DateTimeZone('UTC'));
	}
	if($to) {
		$to->setTimezone(new DateTimeZone('UTC'));
	}
	$tp = $this->mysqli->table_prefix;
	$result->data = array();
	$a['source'] = "
		{$tp}invoices
		INNER JOIN {$tp}invoice_items ON {$tp}invoices.id = {$tp}invoice_items.invoiceId
		LEFT JOIN {$tp}products ON {$tp}invoice_items.product = {$tp}products.id
		LEFT JOIN {$tp}suppliers ON {$tp}products.supplier = {$tp}suppliers.id
	";
	$a['returnQuery'] = true;
	$a['fields'] = "SUM({$tp}invoice_items.quantity) AS quantity, {$tp}products.id, {$tp}products.cost, SUM({$tp}invoice_items.quantity) * {$tp}products.cost AS total_costs, {$tp}products.supplier, {$tp}suppliers.supplier AS suppliername";
	$a['groupfields'] = "{$tp}products.id";
	$a['orderfields'] = "{$tp}suppliers.supplier, {$tp}products.productCode";
	$a['where'] = "{$tp}invoices.trader = '{$this->id}' AND {$tp}products.sor";
	$a['where'] .= ($supplierid ? " AND {$tp}products.supplier = '{$supplierid}'" : "");
	$a['where'] .= ($from ? (" AND date >= '" . $from->format('Y-m-d H:i:s') . "'") : "");
	$a['where'] .= ($to ? (" AND date <= '" . $to->format('Y-m-d H:i:s') . "'") : "");
	$set = $this->mysqli->arrayData($a);
	if(!$result->success = $set->success) {
		$result->msg = $set->msg;
		return $result;
	}
	foreach($set->data as $item) {
		settype($result->data[$item->supplier]->total_costs, 'string');

		if(!is_object(@$result->data[$item->supplier])) {
			$result->data[$item->supplier] = new stdClass;
		}
		$result->data[$item->supplier]->supplier = (int)$item->supplier;
		$result->data[$item->supplier]->suppliername = $item->suppliername;
		$result->data[$item->supplier]->items[] = (object) array(
			'product' => new Product(array('id'=>$item->id)),
			'quantity' => $item->quantity,
			'cost' => $item->cost,
			'total_costs' => $item->total_costs
		);
		$result->data[$item->supplier]->total_costs = bcadd($result->data[$item->supplier]->total_costs, $item->total_costs, 6);
	}
	return $result;
}


public function getStockItemsCount( $includeSor = true ) {
	$tp = $this->mysqli->table_prefix;
	$a['source'] = "{$tp}products AS products";
	$a['fields'] = "SUM(products.inStock) AS items";
	$a['where'] = "products.trader = '{$this->id}'\n"
				.	( !$includeSor ? "AND !products.sor\n" : "");
	$result = $this->mysqli->arrayData($a);
	return (float)$result->data[0]->items;
}


public function getStockProductsCount( $includeSor = true ) {
	$tp = $this->mysqli->table_prefix;
	$a['source'] = "{$tp}products AS products";
	$a['fields'] = "COUNT(products.id) AS products";
	$a['where'] = "products.trader = '{$this->id}'\n"
				.	( !$includeSor ? "AND !products.sor\n" : "")
				.	"AND products.inStock > 0\n";
	$result = $this->mysqli->arrayData($a);
	return (float)$result->data[0]->products;
}


public function getStockValue( $includeSor = true ) {
	$tp = $this->mysqli->table_prefix;
	$a['source'] = "{$tp}products AS products";
	$a['fields'] = "SUM(products.cost * products.inStock) AS value";
	$a['where'] = "products.trader = '{$this->id}'\n"
				.	( !$includeSor ? "AND !products.sor\n" : "");
	$result = $this->mysqli->arrayData($a);
	return (float)$result->data[0]->value;
}


/*	Get Tax Summary for a given period of time
If $taxid is declared, only this tax will be returned as stdclass object.
Otherwise an array consisting of all the taxes is returned
/****************************************/
//	$from:	DateTime object
//	$to:	DateTime object
//	$taxid:	integer
//	--------------------------------------
//	return: stdClass object, or array of stdClass objects, with the following properties:
//		tax_id		
//		tax_name	
//		tax_rate	
//		basis
//		tax

public function getTaxSummary($from = NULL, $to = NULL, $taxid = 0) {
	if($from) {
		$from->setTimezone(new DateTimeZone('UTC'));
	}
	if($to) {
		$to->setTimezone(new DateTimeZone('UTC'));
	}
	$tp = $this->mysqli->table_prefix;
	$a['source'] = "
		{$tp}invoices
		LEFT JOIN {$tp}invoice_tax ON {$tp}invoices.id = {$tp}invoice_tax.invoiceId
	";
	$a['fields'] = "{$tp}invoice_tax.tax_id, MIN({$tp}invoice_tax.tax_name) AS tax_name, {$tp}invoice_tax.tax_rate, SUM({$tp}invoice_tax.tax_basis) AS basis, SUM({$tp}invoice_tax.tax_value) AS tax";
	$a['groupfields'] = "{$tp}invoice_tax.tax_id, {$tp}invoice_tax.tax_rate";
	$a['where'] = "trader = '{$this->id}'";
	$a['where'] .= ($taxid ? "AND {$tp}invoice_tax.tax_id = '{$taxid}'" : "");
	$a['where'] .= ($from ? (" AND date >= '" . $from->format('Y-m-d H:i:s') . "'") : "");
	$a['where'] .= ($to ? (" AND date <= '" . $to->format('Y-m-d H:i:s') . "'") : "");
	$a['where'] .= " AND {$tp}invoices.taxInvoice";
	
	$result = $this->mysqli->arrayData($a)->data;
	if ( $taxid != 0 ) {
		if($result) {
			return reset($result);
		}
		else {
			return (object)array(
				'tax_id'	=> $taxid,
				'tax_name'	=> null,
				'tax_rate'	=> null,
				'basis'		=> 0,
				'tax'		=> 0
			);
		}
	}
	return $result;
}


/*	Get Tax Summary Breakdown by Payment Methods for a given period of time
The function returns an array of taxes grouped by payment methods
/****************************************/
//	$from:	DateTime object
//	$to:	DateTime object
//	$taxid:	integer Only return this Tax if specified
//	--------------------------------------
//	return: object with properties:
//		incalculableInvoices: array of taxable Invoices with a total of 0 (exchanges)
//		payments: an array of stdClass objects, with the following properties:
//			paymentMethod: PaymentMethod object
//			paymentsTotal: (number) Total payments for all traders
//			share: (number) Amount paid to this invoice by this Payment Method
//			taxes: array of taxes applied to the payments in this Payment Method
//				id		
//				tax_name	
//				tax_rate	
//				tax_basis	
//				tax

public function getTaxSummaryBreakdownByPaymentMethods($from = NULL, $to = NULL, $taxid = 0) {
	if($from) {
		$from->setTimezone(new DateTimeZone('UTC'));
	}
	if($to) {
		$to->setTimezone(new DateTimeZone('UTC'));
	}
	$result = (object)array(
		'payments'				=> array(),
		'incalculableInvoices'	=> array()
	);
	$tp = $this->mysqli->table_prefix;
	
	$invoices = $this->mysqli->arrayData(array(
		'distinct'	=> true,
		'class'		=> 'Invoice',
		'source'	=> "
			{$tp}invoices AS invoices
			LEFT JOIN {$tp}invoice_tax AS invoice_tax ON invoices.id = invoice_tax.invoiceId
		",

		'fields'	=> "invoices.id",

		'orderfields'	=> "invoices.id",

		'where'	=> "invoices.trader = '{$this->id}'\n"
			.	($taxid ? "AND invoice_tax.tax_id = '{$taxid}'\n" : "")
			.	($from ? (" AND invoices.date >= '{$from->format('Y-m-d H:i:s')}'\n") : "")
			.	($to ? (" AND invoices.date <= '{$to->format('Y-m-d H:i:s')}'\n") : "")
			.	"AND invoices.taxInvoice\n"
	));
	
	foreach( $invoices->data as $invoice ) {
		if($invoice->total == 0) {
			$result->incalculableInvoices[] = $invoice;
		}
		else {
			$payments = $invoice->getShareOfPayments()->payments;
			$taxes = $invoice->getTaxes()->data;
			foreach( $payments as $share ) {
				$payment = $share->payment;
				$paymentMethod = $payment->paymentMethod;
				$paymentProportionOfInvoice = $share->amount / $invoice->total;
			
				settype( $result->payments[$paymentMethod->id], 'object');
				settype( $result->payments[$paymentMethod->id]->paymentsTotal, 'string');
				settype( $result->payments[$paymentMethod->id]->share, 'string');
				settype( $result->payments[$paymentMethod->id]->taxes, 'array');

				$result->payments[$paymentMethod->id]->paymentMethod = $paymentMethod;
				$result->payments[$paymentMethod->id]->paymentsTotal = bcadd(
					$result->payments[$paymentMethod->id]->paymentsTotal,
					$payment->amount,
					6
				);
				$result->payments[$paymentMethod->id]->share = bcadd(
					$result->payments[$paymentMethod->id]->share,
					$share->amount,
					6
				);
			
				foreach($taxes as $tax) {
					if( $taxid == 0 or $taxid == $tax->tax_id) {
						$paymentsShareOfBasis = $paymentProportionOfInvoice * $tax->tax_basis;
						$paymentsShareOfTax = $paymentProportionOfInvoice * $tax->tax_value;
				
						settype( $result->payments[$paymentMethod->id]->taxes[$tax->tax_id], 'object');
						settype( $result->payments[$paymentMethod->id]->taxes[$tax->tax_id]->basis, 'string');
						settype( $result->payments[$paymentMethod->id]->taxes[$tax->tax_id]->tax, 'string');

						$result->payments[$paymentMethod->id]->taxes[$tax->tax_id]->id = $tax->tax_id;
						$result->payments[$paymentMethod->id]->taxes[$tax->tax_id]->tax_name = $tax->tax_name;
						$result->payments[$paymentMethod->id]->taxes[$tax->tax_id]->tax_rate = $tax->tax_rate;
				
						$result->payments[$paymentMethod->id]->taxes[$tax->tax_id]->basis = bcadd(
							$result->payments[$paymentMethod->id]->taxes[$tax->tax_id]->basis,
							$paymentsShareOfBasis,
							6
						);
						$result->payments[$paymentMethod->id]->taxes[$tax->tax_id]->tax = bcadd(
							$result->payments[$paymentMethod->id]->taxes[$tax->tax_id]->tax,
							$paymentsShareOfTax,
							6
						);
					}
				}
			
			}
		}
	}
	
	return $result;
}


/****************************************/
//	$till:				integer
//	$from:				DateTime object
//	$to:				DateTime object
//	--------------------------------------
//	return	array:
//		transactions:	array with the following keys:
//			time:		DateTime object
//			note:		string
//			sum:		float
//			recorder:	integer
//		sum:			float
public function getTillDeposits($till, $from = NULL, $to = NULL) {
	if($from) {
		$from->setTimezone(new DateTimeZone('UTC'));
	}
	if($to) {
		$to->setTimezone(new DateTimeZone('UTC'));
	}
	$tp = $this->mysqli->table_prefix;

	$query['source'] = "{$tp}cash_register INNER JOIN {$tp}cash_register_accounts ON {$tp}cash_register.id = {$tp}cash_register_accounts.cash_register_entry";
	$query['where'] = "{$tp}cash_register.till = '{(int)$till}' AND {$tp}cash_register_accounts.trader = '{$this->id}' AND {$tp}cash_register.action = 'deposit'";
	$query['fields'] = "{$tp}cash_register.time, {$tp}cash_register.note, {$tp}cash_register.recorder, {$tp}cash_register_accounts.adjustment AS sum";
	$query['orderfields'] = "{$tp}cash_register.time DESC";
	if($from) {
		$query['where'] .= " AND time >= '{$from->format('Y-m-d H:i:s')}'";
	}
	if($to) {
		$query['where'] .= " AND time <= '{$to->format('Y-m-d H:i:s')}'";
	}

	$transactions = $this->mysqli->arrayData($query);
	$result->transactions = $transactions->data;
	$sum = 0;

	foreach($result->transactions as $index => $entry) {
		$result->transactions[$index]->time = new DateTime($entry->time, new DateTimeZone('UTC'));
		$sum = bcadd($sum, $entry->sum, 6);
	}

	return $result;
}


/****************************************/
//	$till:				integer
//	$from:				DateTime object
//	$to:				DateTime object
//	$summary:			boolean
//	--------------------------------------
//	return	array:
//		transactions:	array with the following keys:
//			action:		string
//			time:		DateTime object
//			sale:		integer
//			note:		string
//			recorder:	integer
//			adjustment:	float
//			balance:	float
//		end_balance:	float
//		deposits:		float
//		withdrawals:	float
//		cash_payments:	float
public function getTillStatement($till, $from = NULL, $to = NULL, $summary = false) {
	$result = new stdClass;
	if($from) {
		$from->setTimezone(new DateTimeZone('UTC'));
	}
	if($to) {
		$to->setTimezone(new DateTimeZone('UTC'));
	}
	$tp = $this->mysqli->table_prefix;
	settype($till, 'integer');

	$query['source'] = "{$tp}cash_register INNER JOIN {$tp}cash_register_accounts ON {$tp}cash_register.id = {$tp}cash_register_accounts.cash_register_entry";
	$query['where'] = "{$tp}cash_register.till = '$till' and {$tp}cash_register_accounts.trader = '{$this->id}' AND {$tp}cash_register_accounts.adjustment";
	$query['fields'] = "{$tp}cash_register.action, {$tp}cash_register.time, {$tp}cash_register.sale, {$tp}cash_register.note, {$tp}cash_register.recorder, {$tp}cash_register_accounts.adjustment, {$tp}cash_register_accounts.balance";
	$query['orderfields'] = "{$tp}cash_register.time DESC";
	if($from) {
		$query['where'] .= " AND time >= '{$from->format('Y-m-d H:i:s')}'";
	}
	if($to) {
		$query['where'] .= " AND time <= '{$to->format('Y-m-d H:i:s')}'";
	}

	$transactions = $this->mysqli->arrayData($query);
	unset($query);
	foreach($transactions->data as $index => $entry) {
		$transactions->data[$index]->time = new DateTime($entry->time, new DateTimeZone('UTC'));
	}
	$result->transactions = $transactions->data;
	$result->start_balance = bcsub( @$transactions->data[intval(@$index)]->balance, @$transactions->data[@$index]->adjustment, 6);
	$result->end_balance = @$transactions->data[0]->balance;
	
	if($summary) {
		$sale = false;
		foreach($result->transactions as $index => $entry) {
			if($entry->action == 'payment') {
				if(!$sale or $entry->time->format('Y-m-d') != $date) { //first in a row of sales
					$first = $index;
				}
				else {
					$result->transactions[$first]->adjustment = bcadd($result->transactions[$first]->adjustment, $entry->adjustment, 6);
					$result->transactions[$first]->sale = 0;
					$result->transactions[$first]->action = 'payments';
					unset($result->transactions[$index]);
				}
				$sale = true;
				$date = $entry->time->format('Y-m-d');
			}
			else {
				$sale = false;
			}
		}
	}
	
	$query['source'] = "{$tp}cash_register INNER JOIN {$tp}cash_register_accounts ON {$tp}cash_register.id = {$tp}cash_register_accounts.cash_register_entry";
	$query['where'] = "{$tp}cash_register.till = '{$till}' AND {$tp}cash_register_accounts.trader = '{$this->id}' AND {$tp}cash_register.action = 'deposit'";
	$query['fields'] = "sum({$tp}cash_register_accounts.adjustment) AS deposits";
	if($from) {
		$query['where'] .= " AND time >= '{$from->format('Y-m-d H:i:s')}'";
	}
	if($to) {
		$query['where'] .= " AND time <= '{$to->format('Y-m-d H:i:s')}'";
	}
	$deposits = $this->mysqli->arrayData($query);
	unset($query);
	$result->deposits = $deposits->data[0]->deposits;

	$query['source'] = "{$tp}cash_register INNER JOIN {$tp}cash_register_accounts ON {$tp}cash_register.id = {$tp}cash_register_accounts.cash_register_entry";
	$query['where'] = "{$tp}cash_register.till = '{$till}' AND {$tp}cash_register_accounts.trader = '{$this->id}' AND {$tp}cash_register.action = 'withdrawal'";
	$query['fields'] = "sum({$tp}cash_register_accounts.adjustment) * (-1) AS withdrawals";
	if($from) {
		$query['where'] .= " AND time >= '{$from->format('Y-m-d H:i:s')}'";
	}
	if($to) {
		$query['where'] .= " AND time <= '{$to->format('Y-m-d H:i:s')}'";
	}
	$withdrawals = $this->mysqli->arrayData($query);
	unset($query);
	$result->withdrawals = $withdrawals->data[0]->withdrawals;

	$query['returnQuery'] = true;
	$query['source'] = "{$tp}cash_register INNER JOIN {$tp}cash_register_accounts ON {$tp}cash_register.id = {$tp}cash_register_accounts.cash_register_entry";
	$query['where'] = "{$tp}cash_register.till = '{$till}' AND {$tp}cash_register_accounts.trader = '{$this->id}' AND {$tp}cash_register.action = 'payment'";
	$query['fields'] = "sum({$tp}cash_register_accounts.adjustment) AS cash_payments";
	if($from) {
		$query['where'] .= " AND time >= '{$from->format('Y-m-d H:i:s')}'";
	}
	if($to) {
		$query['where'] .= " AND time <= '{$to->format('Y-m-d H:i:s')}'";
	}
	$payments = $this->mysqli->arrayData($query);
	unset($query);
	$result->cash_payments = $payments->data[0]->cash_payments;

	return $result;
}


/****************************************/
//	$till:				integer
//	$from:				DateTime object
//	$to:				DateTime object
//	--------------------------------------
//	return	array:
//		transactions:	array with the following keys:
//			time:		DateTime object
//			note:		string
//			sum:		float
//			recorder:	integer
//		sum:			float
public function getTillWithdrawals($till, $from = NULL, $to = NULL) {
	if($from) {
		$from->setTimezone(new DateTimeZone('UTC'));
	}
	if($to) {
		$to->setTimezone(new DateTimeZone('UTC'));
	}
	$tp = $this->mysqli->table_prefix;

	$query['source'] = "{$tp}cash_register INNER JOIN {$tp}cash_register_accounts ON {$tp}cash_register.id = {$tp}cash_register_accounts.cash_register_entry";
	$query['where'] = "{$tp}cash_register.till = '{(int)$till}' AND {$tp}cash_register_accounts.trader = '{$this->id}' AND {$tp}cash_register.action = 'withdrawal'";
	$query['fields'] = "{$tp}cash_register.time, {$tp}cash_register.note, {$tp}cash_register.recorder, {$tp}cash_register_accounts.adjustment * (-1) AS sum";
	$query['orderfields'] = "{$tp}cash_register.time DESC";
	if($from) {
		$query['where'] .= " AND time >= '{$from->format('Y-m-d H:i:s')}'";
	}
	if($to) {
		$query['where'] .= " AND time <= '{$to->format('Y-m-d H:i:s')}'";
	}

	$transactions = $this->mysqli->arrayData($query);
	$result['transactions'] = $transactions['data'];
	$sum = 0;

	foreach($result['transactions'] as $index => $entry) {
		$result['transactions'][$index]['time'] = new DateTime($entry['time'], new DateTimeZone('UTC'));
		$sum = bcadd($sum, $entry['sum'], 6);
	}

	return $result;
}


// Load this trader from the DB
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
public function load() {
	$tp = $this->mysqli->table_prefix;
	
	$data = $this->mysqli->arrayData(array(
		'source' => "{$tp}traders",
		'where' => "id = '$this->id'"
	));
	if(!$data->totalRows) {
		$this->id = 0;
		return (object) array(
			'success' =>false,
			'msg' => "err Trader class trader not found"
		);
	}
	foreach($data->data[0] as $property => $value){
		if(property_exists($this, $property)) {
			$this->$property = $value;
		}
	}
	$this->hasLoaded = true;
	$data = $this->mysqli->arrayData(array(
		'source' => "{$tp}preferences",
		'where' => "trader = '$this->id'"
	));
	foreach ($data->data as $preference) {
		$this->preferences->{$preference->setting} = $preference->value;
	}
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