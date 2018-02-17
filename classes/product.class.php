<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
This version 2015-06-25
**********************************************/

class Product {

protected $attributes;	// object with properties named after attribute codes, each holding these properties:
				//	id (integer)
				//	code (string)
				//	name (string)
				//	type (string)
				//	config (string)
				//	description (string)
protected $attributesets = array();	// array of stdClass objects with properties:
				//	id (integer)
				//	name (string)
protected $attributesetsHaveLoaded = false;
protected $attributesHaveLoaded = false;
protected $categories = array();	// array of stdClass objects with properties:
				//	id (integer)
				//	category (string)
protected $categoriesHaveLoaded = false;
protected $hasLoaded = false; // True if this Product has been loaded from the database
protected $taxId; // object - Id of this products tax class
protected $trader; // Trader object to whom this product belongs
protected $traderHasLoaded = false; // bool - True if trader object has loaded.
protected $traderId; // int - Id of the trader to whom this product belongs
public $barcode; // Products barcode
public $brand; // Brand or manufacturer of this product
public $cost; // Cost value of this product
public $description; // Description of product
public $enabled; // Enable/disable the product
public $unit; // If floating, sold in this unit
public $floating = false; // Is this product sold in floating entities?
public $id = null; //	integer - Identificator for this product as stored in the DB
public $inStock = 0; // float - Number in stock if Stock Management is enabled. Otherwise boolean
public $lowestPrice = 0; // 
public $mysqli; // The MySQLi connection
public $name; // string - Name of this product
public $price = 0; // 
public $productCode; // string - Abbreviation or code used to identify this product
public $sor; // 
public $supplier; // Main supplier of this product

public function __construct($config = 0) {
	global $mysqliConnection;
	$this->mysqli = $mysqliConnection;
	$tp = $this->mysqli->table_prefix;

	if($config instanceof Trader) {
		$this->traderId = $config->id;
		$this->trader = $config;
		$this->traderHasLoaded = true;
	}
	else if(is_object($config)) {
		$this->id = (int)$config->id;
		$this->traderId = (int)$config->trader;
	}
	else if(is_array($config)) {
		$this->id = (int)$config['id'];
		$this->traderId = (int)@$config['trader'];
	}
	else if($config) {
		$this->id = (int)$config;
	}

	if($this->id) {
		$this->load();
	}
}

// Add an attribute set to this product
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
public function addAttributeset($attributesetId) {
	if(!$this->hasLoaded) {
		$result = $this->save();
		if(!$result->success) {
			return $result;
		}
	}
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->saveToDb(array(
		'table' => "{$tp}products_attributesets",
		'insert' => true,
		'fields' => array(
			'product' => $this->id,
			'attributeset' => $attributesetId
		)
	));
	if($result->success) {
		$result = $this->loadAttributesets();
	}
	return $result;	
}


// Add a category to this product
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
public function addCategory($categoryId) {
	if(!$this->hasLoaded) {
		$result = $this->save();
		if(!$result->success) {
			return $result;
		}
	}
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->saveToDb(array(
		'table' => "{$tp}product_categories",
		'insert' => true,
		'fields' => array(
			'product' => $this->id,
			'category' => $categoryId
		)
	));
	if($result->success) {
		$result = $this->loadCategories();
	}
	return $result;	
}


// Checks if a given attritbute exists with this Product
/****************************************/
//  $attribute (string): The attribute to look for
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		result: (bool) If this attribute exists or not
public function checkIfAttributeExists($attribute) {
	$result = (object)array(
		'success' => false,
		'msg'=> "",
		'result' => false
	);
	if(!$attribute) {
		$result->msg = "err Product Class missing parameter in checkIfAttributeExists";
	}
	$attributes = $this->getAttributes();
	if(!$attributes->success) {
		return $attributes;
	}
	$result->result = isset($attributes->data->$attribute);
	$result->success = true;
	return $result;
}


// Register a product count and update stock
/****************************************/
//	$quantity:	(number) Counted quantity of product
//	$count:	(int) Count ID as registered in DB
//	$occation:	(string) Description/name of the count
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		count (integer): Count ID
//		msg (text): Explanation of success parameter
public function count($quantity, $count = 0, $occation = "") {
	$tp = $this->mysqli->table_prefix;
	settype($count, 'integer');
	$result = new stdClass;
	
	if(!$count) {
		$count = $this->mysqli->arrayData(array(
			'source' => "{$tp}inventory_count",
			'fields' => "IFNULL(MAX(count_no), 0) + 1 AS count_no"
		))->data[0]->count_no;
	}

	if($this->mysqli->arrayData(array(
		'source' => "{$tp}inventory_count",
		'where' => "product_id = '{$this->id}' AND count_no = '{$count}'"
	))->totalRows) {
		return (object) array(
			'success' => false,
			'msg' => "msg product already counted"
		);
	}

	if($this->mysqli->arrayData(array(
		'source' => "{$tp}inventory_count",
		'where' => "trader = '{$this->traderId}' AND count_no = '{$count}' AND complete"
	))->totalRows) {
		return (object) array(
			'success' => false,
			'msg' => "err Product class count already complete"
		);
	}

	$result = $this->mysqli->saveToDb(array(
		'table' => "{$tp}inventory_count",
		'returnQuery' => true,
		'insert' => true,
		'fields' => array(
			'trader' => $this->traderId,
			'product_id' => $this->id,
			'actual_quantity' => $quantity,
			'calculated_quantity' => $this->inStock,
			'date' => date('Y-m-d'),
			'count_no' => $count,
			'count_name' => $this->mysqli->real_escape_string($occation),
			'value_per' => $this->cost
		)
	));

	if($result->success) {
		$result = $this->mysqli->saveToDb(array(
			'table' => "{$tp}products",
			'update' => true,
			'where' => "id = '{$this->id}'",
			'fields' => array(
				'inStock' => $quantity
			)
		));
	}
	
	if($result->success and $occation) {
		$result = $this->mysqli->saveToDb(array(
			'table' => "{$tp}inventory_count",
			'update' => true,
			'where' => "trader = '{$this->traderId}' AND count_no = '{$count}'",
			'fields' => array(
				'count_name' => $this->mysqli->real_escape_string($occation)
			)
		));
	}
	$result->count = $count;
	return $result;
}


// Load the product
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
public function delete() {
	$result = (object)array(
		'success' => true,
		'msg'       => ''
	);
	$tp = $this->mysqli->table_prefix;
	$result->success = $this->mysqli->query("
		DELETE {$tp}products, {$tp}products_attributesets, {$tp}product_attributes, {$tp}product_categories FROM
		{$tp}products
		LEFT JOIN {$tp}products_attributesets ON {$tp}products.id = {$tp}products_attributesets.product 
		LEFT JOIN {$tp}product_attributes ON {$tp}products.id = {$tp}product_attributes.product 
		LEFT JOIN {$tp}product_categories ON {$tp}products.id = {$tp}product_categories.product 
		WHERE {$tp}products.id = '" . (int)$this->id . "'");
	if ($result->success ) {
		$this->attributesHaveLoaded = false;
		$this->attributesetsHaveLoaded = false;
		$this->categoriesHaveLoaded = false;
		$this->traderHasLoaded = false;
		$this->hasLoaded = false;
		$this->load();
		return $result;
	}
	else {
		$result->msg = $this->mysqli->error;
		return $result;
	}
}


// Get the attributes for this product
/****************************************/
//  $update (bool): Force attributes to reload
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: stdClass objects with properties in accordance with the attribute sets:
//			Each attribute property containing properties:
// 			id: (int) Unique id for this attributes
// 			code: (string) The attribute code reference (no spaces)
// 			name: (string) Render-name for the attribute
// 			description: (string) Explanation of the attribute
// 			type: (string) Value type for the attribute:
// 				bool
// 				float
// 				int
// 				array
// 				object
// 				image (uri)
// 				html
// 				uri
// 				no value will be treated as plain text
// 			config: (object) Additional configurations following attribute. E.g:
// 				
public function getAttributes($update = false) {
	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => null
	);
	if($this->attributesHaveLoaded and !$update) {
		$result->data = $this->attributes;
		return $result;
	}
	else {
		$result = $this->loadAttributes();
		if($result->success) {
			$result->data = $this->attributes;
		}
		return $result;
	}
}


// Get the attribute sets assigned to this product
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Array of stdClass objects with properties:
//			id (int) Attribute set Id
//			name: (string) Attribute set name
public function getAttributesets($update = false) {
	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => null
	);
	if($this->attributesetsHaveLoaded and !$update) {
		$result->data = $this->attributesets;
		return $result;
	}
	else {
		$result = $this->loadAttributesets();
		if($result->success) {
			$this->attributesets = $result->data;
		}
		return $result;
	}
}


// Return the value of a given attribute
/****************************************/
//  $attribute (bool): The attribute to look for
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		result: (bool) If this attribute exists or not
public function getAttributeValue($attribute) {
	$result = (object)array(
		'success' => false,
		'msg'=> ""
	);
	$attributes = $this->getAttributes();
	if(!$attributes->success) {
		return $attributes;
	}
	$result->data = @$attributes->data->{$attribute}->value;
	$result->success = true;
	return $result;
}


// Get the categories this product belongs to
/****************************************/
//	$update: (bool) Forces to reload from db
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Array of stdClass objects with properties:
//			id (int) Category id
//			category: (string) Category name
public function getCategories($update = false) {
	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => null
	);
	if($this->categoriesHaveLoaded and !$update) {
		$result->data = $this->categories;
		return $result;
	}
	else {
		$result = $this->loadCategories();
		if($result->success) {
			$result->data = $this->categories;
			$this->categories = $result->data;
		}
		return $result;
	}
}


// Get sold instances of this product
/****************************************/
//	$filter:	(array/object) Filtering conditions
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: array of stdClass object with properties:
//			id:	(int) The invoiced instance of the item as saved in DB
//			invoiceId:	(int) Invoice ID
//			quantity: (float) Quantity
//			pricePer: (float) price per unit sold
//			discount: (float) Discount given on this item
//			price: (float) price sold
//			returned_id:	(int) The return instance of the item
//			returned_invoice_id:	(int) The Invoice where the returned item was credited
//			returned_quantity: (float) Returned quantity
public function getSoldItems($filter = array()) {
	settype($filter, 'array');
	$tp = $this->mysqli->table_prefix;

	return $this->mysqli->arrayData(array(
		'source' => "{$tp}invoice_items LEFT JOIN ({$tp}returned_items INNER JOIN {$tp}invoice_items returned ON {$tp}returned_items.credited_invoice_item = returned.id) ON {$tp}invoice_items.id = {$tp}returned_items.original_invoice_item LEFT JOIN {$tp}invoices ON {$tp}invoice_items.invoiceId = {$tp}invoices.id",
		'fields' => "{$tp}invoices.date, {$tp}invoice_items.id, {$tp}invoice_items.invoiceId, {$tp}invoice_items.quantity, {$tp}invoice_items.pricePer, {$tp}invoice_items.discount, {$tp}invoice_items.price, returned.id AS returned_id, returned.invoiceId AS returned_invoice_id, returned.quantity AS returned_quantity",
		'where' => "{$tp}invoice_items.quantity > 0 AND {$tp}invoice_items.product = '{$this->id}'"
	));
}


// Get this products tax group
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: stdClass object with properties:
// 			id (integer)
// 			taxName (string)
// 			taxRate (number)
public function getTax() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}tax",
		'where' => "id = '$this->taxId'"
	));
	if($result->success) {
		$result->data = reset($result->data);
		if(!$result->data) {
		    $result->success = false;
		}
	}
	return $result;
}


// Get the trader to whom this product belongs
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Trader object
public function getTrader() {
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
	
	if(!$this->hasLoaded) {
		$this->load();
	}

	$result->data = $this->trader = new Trader($this->traderId);
	if(!$result->data->id) {
		$this->trader = null;
		$this->traderHasLoaded = false;
		$result->success = false;
		$result->msg = "err Trader with ID '{$this->traderId}' does not exist in the system";
		return $result;
	}
	$this->traderHasLoaded = true;
	return $result;	
}


public function increaseStock($q) {
	$tp = $this->mysqli->table_prefix;
	if($this->getTrader()->data->preferences->manageStock) {
		$result = $this->mysqli->saveToDb(array(
			'table' => "{$tp}products",
			'update' => true,
			'where' => "id = '{$this->id}'",
			'fields' => array(
				'inStock' => $this->inStock + ($this->floating ? (float)$q : (int)$q)
			)
		));
		if($result->success) {
			$this->inStock += ($this->floating ? (float)$q : (int)$q);
		}
	}
}

// Load the product
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
public function load() {
	$result = (object)array(
		'success' => true,
		'msg'=> ""
	);
	settype($this->id, "integer");

	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}products",
		'where' => "id = '$this->id'"
	));
	if(!$result->totalRows) {
		$this->hasLoaded = true;
		$this->id = null;
		return (object)array(
			'success' => false,
			'msg' => "err Product class product with id '{$this->id}' was not found"
		);
	}
	foreach($result->data[0] as $property => $value){
		if(property_exists($this, $property)) {
			switch($property) {
				case "id":
				case "supplier":
					$this->$property = $value ? (int)$value : null;
						break;
				case "floating":
				case "sor":
				case "enabled":
					$this->$property = (bool)$value;
						break;
				case "tax":
						$this->taxId = (int)$value;
						break;
				case "trader":
						$this->traderId = (int)$value;
						$this->traderHasLoaded = false;
						break;
				default:
					$this->$property = $value;			
			}
		}
	}
	
	$this->taxId = $result->data[0]->tax;

	if(!$this->floating) {
		$this->inStock = (int)$this->inStock;
	}
	$this->hasLoaded = true;
	return $result;
}


// Load the attributes into the attributes property
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: stdClass objects with properties in accordance with the attribute sets:
protected function loadAttributes() {
	$tp = $this->mysqli->table_prefix;
	$att_array = array();
	$result = (object)array(
		'success' => true,
		'msg'=> ""
	);
	
	$ref = "SELECT DISTINCT {$tp}products_attributesets.product, {$tp}attributesets_attributes.attribute
	FROM (
		{$tp}products_attributesets
		INNER JOIN (
			{$tp}attributesets
			INNER JOIN {$tp}attributesets_attributes ON {$tp}attributesets.id = {$tp}attributesets_attributes.attributeset
		)
		ON {$tp}products_attributesets.	attributeset = {$tp}attributesets.id
	)
	WHERE !{$tp}attributesets.trader OR {$tp}attributesets.trader = '{$this->traderId}'";
	
	$sql =	"SELECT {$tp}attributes.*, {$tp}product_attributes.value
			FROM ({$ref}) AS ref 
			LEFT JOIN {$tp}product_attributes ON ref.product = {$tp}product_attributes.product AND ref.attribute = {$tp}product_attributes.attribute
			INNER JOIN {$tp}attributes ON ref.attribute = {$tp}attributes.id
			WHERE ref.product = '$this->id'
			UNION
			SELECT {$tp}attributes.*, {$tp}product_attributes.value
			FROM {$tp}product_attributes
			INNER JOIN {$tp}attributes ON {$tp}product_attributes.attribute = {$tp}attributes.id
			WHERE {$tp}product_attributes.product = '{$this->id}'";
	
	$attributes = $this->mysqli->arrayData(array(
		'sql' => $sql
	));
	if(!$attributes->success) {
	    throw new Exception('Could not load attributes: ' . $ql);
	}
	foreach($attributes->data as $attribute) {
		$att_array[$attribute->code] = $attribute;
		switch($attribute->type) {
			case "int":
			case "integer":
			case "bool":
			case "boolean":
				settype($att_array[$attribute->code]->value, $attribute->type);
				break;
			case "array":
			case "object":
				$att_array[$attribute->code]->value = json_decode($attribute->value);
				break;
		}
	}
	
	$this->attributes = (object)$att_array;
	$this->attributesHaveLoaded = true;
	return $result;
}


// Load the attributesets into the attributesets property
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Array of stdClass objects with properties:
//			id (int) Attribute set Id
//			name: (string) Attribute set name
protected function loadAttributesets() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}products_attributesets LEFT JOIN {$tp}attributesets ON {$tp}products_attributesets.attributeset = {$tp}attributesets.id",
		'fields' => "{$tp}attributesets.id, {$tp}attributesets.name",
		'where' => "{$tp}products_attributesets.product = '{$this->id}' AND (!{$tp}attributesets.trader or {$tp}attributesets.trader = '{$this->traderId}')",
		'distinct' => true
	));
	if($result->success) {
		$this->attributesets = $result->data;
		$this->attributesetsHaveLoaded = true;
	}
	return $result;	
}


// Load the product with the given product code
/****************************************/
//	$productCode: (string) Unique Product Code
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: stdClass objects with properties in accordance with the attribute sets:
public function loadByProductCode($product_code, $trader_id = 0) {
	if(!$trader_id) {
		if(!$trader_id = $this->getTrader()->data->id) {
			return (object)array(
				'success' => false,
				'msg'=> "err Product class trader missing"
			);
		}
	}
	$tp = $this->mysqli->table_prefix;
	$this->hasLoaded = false;
	$this->id = $this->mysqli->arrayData(array(
		'source' => "{$tp}products",
		'where' => "productCode = '{$product_code}' and trader = '{$trader_id}'"
	))->data[0]->id;
	return $this->load();
}


// Load the categories into the categories property
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: Array of stdClass objects with properties:
//			id (int) Category id
//			category: (string) Category name
protected function loadCategories() {
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source' => "{$tp}product_categories INNER JOIN {$tp}categories ON {$tp}product_categories.category = {$tp}categories.id",
		'fields' => "{$tp}categories.*",
		'where' => "{$tp}product_categories.product = '{$this->id}'",
		'distinct' => true
	));
	if($result->success) {
		$this->categories = $result->data;
		$this->categoriesHaveLoaded = true;
	}
	return $result;	
}


// Outputs the attributes as a single stdClass object
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
//		data: stdClass object with all properties and attributes of the product
public function output() {
	if(!$this->hasLoaded) {
		$this->load();
	}
	$tp = $this->mysqli->table_prefix;
	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => new stdClass
	);
	$attributes = $this->getAttributes();
	if(!$attributes->success) {
		return $attributes;
	}
	foreach($attributes->data as $attribute) {
		$result->data->{$attribute->code} = $attribute->value;
	}
	$result->data->attributesets = $this->getAttributesets()->data;
	$result->data->barcode = $this->barcode;
	$result->data->brand = $this->brand;
	$result->data->categories = $this->getCategories()->data;
	$result->data->cost = $this->cost;
	$result->data->description = $this->description;
	$result->data->enabled = $this->enabled;
	$result->data->unit = $this->unit;
	$result->data->floating = $this->floating;
	$result->data->id = $this->id;
	$result->data->inStock = $this->inStock;
	$result->data->lowestPrice = $this->lowestPrice;
	$result->data->name = $this->name;
	$result->data->price = $this->price;
	$result->data->productCode = $this->productCode;
	$result->data->sor = $this->sor;
	$result->data->supplier = $this->supplier;
	$result->data->tax = $this->getTax()->data;
	$result->data->trader = $this->getTrader()->data;

	return $result;	
}


public function reduceStock($q) {
	$tp = $this->mysqli->table_prefix;
	if($this->getTrader()->data->preferences->manageStock) {
		$result = $this->mysqli->saveToDb(array(
			'table' => "{$tp}products",
			'update' => true,
			'where' => "id = '{$this->id}'",
			'returnQuery' => true,
			'fields' => array(
				'inStock' => $this->inStock - $q
			)
		));
		if($result->success) {
			$this->inStock -= ($this->floating ? (float)$q : (int)$q);
		}
	}
}


// Removes a given attribute set from this product
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
public function removeAttributeset($attributesetId) {
	$result = (object)array(
		'success' => false,
		'msg'=> "",
		'data' => null
	);
	if(!$this->hasLoaded) {
		$result = $this->save();
		if(!$result->success) {
			return $result;
		}
	}
	$tp = $this->mysqli->table_prefix;
	$stmt = $this->mysqli->prepare("DELETE FROM {$tp}products_attributesets WHERE product =? AND attributeset =? ");
	$stmt->bind_param("ss", $this->id, $attributesetId);
	$result->success = $stmt->execute();
	if(!$result->success) {
		$result->msg = $stmt->error();
	}
	$stmt->close();
	if($result->success) {
		$result = $this->loadAttributesets();
	}
	return $result;	
}


// Removes a category from this product
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success: (bool) Wether operation was successful
//		msg: (string) Error message
public function removeCategory($categoryId) {
	$result = (object)array(
		'success' => false,
		'msg'=> "",
		'data' => null
	);
	if(!$this->hasLoaded) {
		$result = $this->save();
		if(!$result->success) {
			return $result;
		}
	}
	$tp = $this->mysqli->table_prefix;
	$stmt = $this->mysqli->prepare("DELETE FROM {$tp}product_categories WHERE product =? AND category =? ");
	$stmt->bind_param("ss", $this->id, $categoryId);
	$result->success = $stmt->execute();
	if(!$result->success) {
		$result->msg = $stmt->error();
	}
	$stmt->close();
	if($result->success) {
		$result = $this->loadCategories();
	}
	return $result;	
}


public function save($trader = 0) {
	$tp = $this->mysqli->table_prefix;
	if(!$this->traderId and $trader instanceof Trader) {
		$this->traderId = $trader->id;
	}
	else if(!$this->traderId and is_integer($trader)) {
		$this->traderId = $trader;
	}
	if(!$this->traderId) {
		return (object)array(
			'success' => false,
			'msg' => "err Trader is not declared for the product"
		);
	}

	$result = $this->mysqli->saveToDb(array(
		'table' => "{$tp}products",
		'insert' => ((int)$this->id ? false : true),
		'update' => ((int)$this->id ? true : false),
		'where' => "id = " . $this->id,
		'fields' => array(
			'id' => $this->id,
			'trader' => $this->traderId
		)
	));
	if($result->success) {
		$this->id = $result->id;
		$this->load();
	}
	return $result;
}


public function setAttribute($attribute, $value, $allow_new_value = false) {
	$result = (object)array(
		'success' => false,
		'msg'=> ""
	);
	if(
	    (
	        $attribute == "id"
	        or $attribute == "productCode"
	        or (
	            isset($this->getAttributes()->data->$attribute)
	            and
	            $this->getAttributes()->data->$attribute->config->unique
	        )
	    )
	    and !$this->validateUniqueness($attribute, $value)
	) {
		$result->success = false;
		$result->msg = "err this value must be unique to this product, but is not";
		return $result;
	}
	$tp = $this->mysqli->table_prefix;
	
	if(!$this->hasLoaded) {
		$result = $this->save();
		if(!$result->success) {
			return $result;
		}
	}

	if($attribute == "supplier" and $value) {
		$suppliers = $this->mysqli->arrayData(array(
			'source' => "{$tp}suppliers",
			'where' => "trader = '{$this->traderId}' and (id = '$value' or supplier = '$value')",
			'fields' => "id"
		));
		if($suppliers->data[0]->id) {
			$value = $suppliers->data[0]->id;
		}
		else if($allow_new_value){
			$value = $this->mysqli->saveToDb(array(
				'table' => "{$tp}suppliers",
				'insert' => true,
				'fields' => array(
					'trader' => $this->traderId,
					'supplier' => $value
				)
			))->id;
		}
		else {
			$result->msg = "err supplier cannot be found";
			return $result;
		}
	}
	
	if($attribute == "categories") {
		$stmt = $this->mysqli->prepare("DELETE FROM {$tp}product_categories WHERE product =? ");
		$stmt->bind_param("s", $this->id);
		$result->success = $stmt->execute();
		if(!$result->success) {
			$result->msg = $stmt->error();
		}
		$stmt->close();
		foreach($value as $category) {
			if($result->success) {
				if($category->id) {
					$cat_id = $category->id;
				}
				else if($category['id']) {
					$cat_id = $category['id'];
				}
				else if(is_string($category)) {
					$cat_id = $this->mysqli->arrayData(array(
						'source' => "{$tp}categories",
						'where' => "category = '" . $this->mysqli->real_escape_sring($category) . "'",
					))->data->id;
				}
				else {
					$cat_id = $category;
				}
				$result = $this->mysqli->saveToDb(array(
					'table' => "{$tp}product_categories",
					'returnQuery' => true,
					'insert' => true,
					'fields' => array(
						'product' => $this->id,
						'category' => (int)$cat_id
					)
				));
			}
		}
		$this->categoriesHaveLoaded = false;
		return $result;
	}
	
	if($value != NULL) {
		switch($attribute) {
			case "tax":
				settype($value, 'integer');
				break;
			case "enabled":
			case "floating":
			case "sor":
				$value = (($value == 'false' or $value == 'FALSE' or !$value) ? false : true);
				break;
			case "categories":
				settype($value, 'array');
				break;
		}
	}
	
	// Properties belonging to this object are saved first
	switch($attribute) {
		case "id":
			if(!(int)$value) {
				return (object)array('success' => true);
			}
		case "id":
		case "barcode":
		case "brand":
		case "cost":
		case "description":
		case "enabled":
		case "floating":
		case "inStock":
		case "lowestPrice":
		case "name":
		case "price":
		case "productCode":
		case "sor":
		case "supplier":
		case "tax":
		case "unit":
			$result = $this->mysqli->saveToDb(array(
				'table' => "{$tp}products",
				'returnQuery' => true,
				'update' => true,
				'where' => "id = '{$this->id}'",
				'fields' => array(
					$attribute => $value
				)
			));
			if($result->success) {
				$this->$attribute = $value;
			}
			return $result;
			break;
	}
	
	// Then attributes belonging to the attributes property are saved
	$is_attribute = $this->mysqli->arrayData(array(
		'source' => "{$tp}attributes",
		'where' => "id = '$attribute' or code = '$attribute'",
		'orderfields' => "IF(id = '$attribute', 0, 1)"
	));
	$attribute = $is_attribute->data[0]->id;
	if(!$attribute) {
		$result->msg = "err Attribute does not exist";
		return $result;
	}
	$attribute_exists = $this->mysqli->arrayData(array(
		'source' => "{$tp}product_attributes",
		'where' => "attribute = '$attribute' and product = '{$this->id}'"
	))->totalRows;

	if($value == NULL) {
		$stmt = $this->mysqli->prepare("DELETE FROM {$tp}product_attributes WHERE product =? AND attribute =?");
		$stmt->bind_param("ss", $this->id, $attribute);
		$result->success = $stmt->execute();
		if(!$result->success) {
			$result->msg = $stmt->error();
		}
		$stmt->close();
	}
	else {
		$result = $this->mysqli->saveToDb(array(
			'table' => "{$tp}product_attributes",
			'update' => ($attribute_exists ? true : false),
			'insert' => ($attribute_exists ? false : true),
			'where' => "attribute = '$attribute' and product = '{$this->id}'",
			'fields' => array(
				'product' => $this->id,
				'attribute' => $attribute,
				'value' => $value
			)
		));
	}
	return $result;
}


// Validate if given attribute is unique
/****************************************/
//	$attribute: (string) Property or attribute to check for
//	$value: (string) Unique value
//	$across_traders: (bool) False to check if value is unique within this traders products, true to check for all traders
//	--------------------------------------
//	return: (bool) True if value is unique, otherwise false
public function validateUniqueness($attribute, $value, $across_traders = false, $traderId = 0) {
	
	$tp = $this->mysqli->table_prefix;
	if(!(int)$traderId) {
		if(!$traderId = $this->getTrader()->data->id) {
			$across_traders = true;
		}
	}
	
	$hits = 
	$this->mysqli->arrayData(array(
		'source' => "{$tp}products",
		'where' => "{$tp}products.id != " . (int)$this->id . " AND {$tp}products." . $this->mysqli->real_escape_string($attribute) . " = '{$value}'" . ($across_traders ? "" : " AND {$tp}products.trader = '{$traderId}'")
	))->totalRows
	or
	$this->mysqli->arrayData(array(
		'source' => "{$tp}product_attributes LEFT JOIN {$tp}products ON {$tp}product_attributes.product = {$tp}products.id",
		'where' => "{$tp}product_attributes.product != " . (int)$this->id . " AND {$tp}product_attributes.attribute = '$attribute' AND {$tp}product_attributes.value = '{$value}'" . ($across_traders ? "" : " AND {$tp}products.trader = '{$traderId}'")
	))->totalRows;
	return !$hits;
}


}

?>