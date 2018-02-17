<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
class Converter {

private $sftpKeyPassword;
protected $mysqli; // object - The MySQLi connection
protected $trader; // trader object
public $id = 0;	//	integer - Identificator for this converter as stored in the DB
public $name; // string - Name of this converter
public $description; // string - Description of what this converter is used for
public $action = "export"; // export, update, replace or insert
public $insertBeforeUpdate = false;
public $format = "csv"; // json, xml or csv
public $escape = ",";
public $csvSeparator = ",";
public $csvWrapper = '"';
public $csvQuoteText = true;
public $csvHeaders = true;
public $characterEncoding = "UTF-8";
public $feed = false; // (bool) True to not save result but feed directly
public $filetransferMethod; // local, ftp or sftp
public $filename;
public $path;
public $host;
public $port;
public $user;
public $password;
public $fields = array(); // Array of fields to be imported or exported and value conversion of these

function __construct($autoloadId = 0) {
	global $mysqliConnection;
	$this->mysqli = $mysqliConnection;
	$tp = $this->mysqli->table_prefix;
	
	if((int)$autoloadId) {
		$this->id = $autoloadId;
		if(!$this->load()->success) {
			$this->id = 0;
		}
	}
}


// Return string from array
/****************************************/
//	$value:	(mixed) value to convert
//	$config: (object) Config parameters:
//		true: (string) string to return if true
//		false: (string) string to return if false
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful	
//		msg (string): (Error) message	
//		result: Converted value	
function con_boolToString($value, $config) {
	$result = new stdClass;
	$result->success = true;
	$result->data = $value;
	if($value and isset($config->true)) {
		$result->data = $config->true;
	}
	else if(isset($config->false)) {
		$result->data = $config->false;
	}
	return $result;
}


// Return string from array
/****************************************/
//	$value:	(mixed) value to convert
//	$config: (object) Config parameters:
//		type: (string) json (defaults to comma separated values)
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful	
//		msg (string): (Error) message	
//		result: Converted value	
function con_arrayToString($value, $config) {
	$result = new stdClass;
	$result->success = true;
	settype($value, "array");
	switch($config->type) {
		case "json":
			$result->data = json_encode($value);
			return $result;
			break;
		default:
			if(!isset($config->delimiter)) {
				$config->delimiter = ",";
			}
			$result->data = implode($config->delimiter, $value);
			return $result;
			break;
	}
}


// Typecast field value
/****************************************/
//	$value:	(mixed) value to convert
//	$config: (object) Config parameters:
//		type: (string) which type the value should be cast
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful	
//		msg (string): (Error) message	
//		result: Converted value	
function con_cast($value, $config) {
	$result = new stdClass;
	switch($config->type) {
		case "int":
		case "integer":
		case "bool":
		case "float":
		case "string":
		case "array":
		case "object":
			settype($value, $config->type);
			$result->success = true;
			$result->data = $value;
			return $result;
			break;
		default:
			settype($value, $config->type);
			$result->success = true;
			$result->data = $value;
			return $result;
			break;
	}
}


// Return object property value
/****************************************/
//	$value:	(object) Object
//	$config: (object) Config parameters:
//		property: (string) object property to return
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful	
//		msg (string): (Error) message	
//		result: Converted value	
function con_getObjectProperty($value, $config) {
	$result = new stdClass;
	$result->success = true;
	if(is_object($value)) {
		$result->data = $value->{$config->property};
	}
	else {
		$result->success = false;
		$result->msg = "Cannot retreive property from non-object";
	}
	return $result;
}


// Return object property value
/****************************************/
//	$value:	(object) Object
//	$config: (object) Config parameters:
//		class: (string) The class to which the object belongs. Defaults to stdClass
//		required: (bool) if false a stdClass object will be issued if given class does not exist
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful	
//		msg (string): (Error) message	
//		result: Object	
function con_objectFromId($id, $config) {
	$result = new stdClass;
	$result->success = true;
	if($config->class and $config->required and !class_exists($config->class)) {
		$result->success = false;
		$result->msg = "err Converter class class does not exist";
	}
	else if(class_exists($config->class)) {
		$result->data = new $config->class($id);
	}
	else {
		$result->data = new stdClass;
	}
	return $result;
}


// Run preg_replace on value
/****************************************/
//	$value:	(string) value to convert
//	$config: (object) Config parameters:
//		pattern: (string) regex pattern
//		replacement: (string) regex replacement
//		limit: limit parameter to pass to preg_replace()
//		count: count parameter to pass to preg_replace()
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful	
//		msg (string): (Error) message	
//		result: Converted value	
function con_regex($value, $config) {
	$result = new stdClass;
	$result->success = true;
	if(!isset($config->limit)) {
		$config->limit = -1;
	}
	$result->data = preg_replace($config->pattern, $config->replacement, $value, $config->limit, $config->count);
	return $result;
}


// Return array from csv
/****************************************/
//	$value:	(mixed) value to convert
//	$config: (object) Config parameters:
//		type: (string) json (defaults to comma separated values)
//		delimiter: (string) symbol that separates values. Defaults to ','
//		wrapper: (string) symbol that encloses strings. Defaults to '"'
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful	
//		msg (string): (Error) message	
//		result: Converted value	
function con_stringToArray($value, $config) {
	$result = new stdClass;
	$result->success = true;
	settype($config, "object");
	switch($config->type) {
		case "json":
			$result->data = json_decode($value);
			return $result;
			break;
		default:
			$result->data = str_getcsv($value, $config->delimiter, $config->wrapper, $config->escape);
			return $result;
			break;
	}
}


// Converts field value to spesified format
/****************************************/
//	$value:	(mixed) value to convert
//	$method: Converter class 'con_' prefixed conversion method
//	$config: (object) Extra parameters to pass to conversion method
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful	
//		msg (string): (Error) message	
//		data: Converted value	
function convert($value, $method= "", $config = array()) {
	$result = new stdClass;
	if(is_string($config)) {
		$config = json_decode($config);
	}
	if (method_exists($this, $method = "con_{$method}")) {
		$result = $this->$method($value, $config);
	}
	else {
		$result->success =  false;
		$result->msg =  "err converter class: Given conversion method does not exist.";
	}
	return $result;
}


// Executes converter and imports or exports products
/****************************************/
//	$objects: array of objects to process
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg (string): (Error) message	
function execute($export_objects = array()) {
	$result = new stdClass;
	$load = $this->load();
		if(!$load->success) {
			return $load;
		}
	switch($this->action) {
		case "insert":
		case "update":
		case "replace":
			$result = $this->readFromFile();
			if(!$result->success) {
				return $result;
			}
			else {
				$result = $this->import($result->data);
			}
			return $result;
			break;
		default:
			$result = $this->export($export_objects);
			if(!$result->success) {
				return $result;
			}
			else {
				$result = $this->writeToFile($result->data);
			}
			return $result;
			break;
	}
}


// Runs converter and outputs result
/****************************************/
//	$objects: array of objects to process
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg (string): (Error) message	
private function export($objects) {
	$result = new stdClass;
	$result->success = true;

	foreach($objects as $object) {
		$line = new stdClass;
		foreach($this->fields as $field) {
			$header[$field->field] = $field->field;
			if(property_exists($object, $field->attribute)) {
				if($field->method) {
					$conversion = $this->convert($object->{$field->attribute}, $field->method, $field->config);
					if($conversion->success) {
						$line->{$field->field} = $conversion->data;
					}
					else {
						return $conversion;
					}
				}
				else {
					$line->{$field->field} = $object->{$field->attribute};
				}
			}
			else {
				$line->{$field->field} = null;
			}
		}
		if($this->format == "csv") {
			$delimiter_esc = preg_quote($this->csvSeparator, '/');
			$enclosure_esc = preg_quote($this->csvWrapper, '/');
			foreach($header as $property => $value) {
				if($this->csvQuoteText) {
					$header[$property] = $this->csvWrapper . str_replace($this->csvWrapper, $this->csvWrapper . $this->csvWrapper, $value) . $this->csvWrapper;
				}
			}
			foreach($line as $property => $value) {
				if($this->csvQuoteText or preg_match( "/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $value)) {
					$line->$property = $this->csvWrapper . str_replace($this->csvWrapper, $this->csvWrapper . $this->csvWrapper, $value) . $this->csvWrapper;
				}
			}
			if($this->csvHeaders) {
				$result->data[0] = implode($this->csvSeparator, $header);			
			}
			$result->data[] = implode($this->csvSeparator, (array)$line);			
		}
		else {
			$result->data[] = $line;
		}
	}
	if($this->format == "csv") {
		$result->data = implode("\n", $result->data);
	}
	if($this->format == "json") {
		$result->data = json_encode($result->data);
	}
	
	return $result;
}

// Product Code is always required. An import fails if this is missing from the feed.
// Insert will fail if product code is already in use by this trader.
// Replace containing new product code will be treated as insert.
// Update containing new product code will be treated according to insertBeforeUpdate.
// If ID is included in feed, this is ignored on insert, but takes priority over product code on update and replace.

// Data file is turned into array
// For each item:
// If Product code is missing, the item fails
// Else if 


// Turns data string into products
/****************************************/
//	$data: $data file to import
//	--------------------------------------
//	return: stdClass object with the following properties:
//		results (array) stdClass object with:
//			id (integer): Product id
//			productCode (string): Product code
//			success (integer): if import was successful
//			msg (string): if import was successful
//			fail (stdClass object) containing attribute name and error message:
//		msgs (array): Error descriptions
//		numFails: (int) Number of fails
//		numInserts: (int) Number of successful inserts
//		numUpdates: (int) Number of successful updates
//		numReplacements: (int) Number of successful replacements
private function import($data) {
	$result = new stdClass;
	$result->results = array();
	$result->numFails = 0;
	$result->numInserts = 0;
	$result->numUpdates = 0;
	$result->numReplacements = 0;
	$result->inserted = array();
	$result->updated = array();
	$result->replaced = array();

	// From JSON to array / object
	if($this->format == "json") {
		$items = json_decode($data);
	}
	// From CSV to array / object
	if($this->format == "csv") {
		$items = preg_split('/[\r\n]{1,2}(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/', $data);
		foreach($items as $index => $csv) {
			$items[$index] = str_getcsv($csv, $this->csvSeparator, $this->csvWrapper, $this->escape);
		}
		$headers = array_shift($items);
		foreach($items as $index => $object) {
			$items[$index] = new stdClass;
			foreach($headers as $headerindex => $header) {
				$items[$index]->$header = $object[$headerindex];
			}
		}
	}

	foreach($items as $item) { // Loop through all the items
		$results = new stdClass;
		$line = new stdClass;
		foreach($this->fields as $field) { // For each field defined in this converter:

			// If the field exists in the file the value is assigned to this field
			// If not then the value is set to null
			if(property_exists($item, $field->field)) {
				if($field->method) {
					$conversion = $this->convert($item->{$field->field}, $field->method, $field->config);
					if($conversion->success) {
						$line->{$field->attribute} = $conversion->data;
					}
					else {
						return $conversion;
					}
				}
				else {
					$line->{$field->attribute} = $item->{$field->field};
				}
			}
			else {
				$line->{$field->attribute} = null;
			}
		}
		$product = new Product($this->trader);

		if(!$line->productCode) {	// Product Code is required for any action
			$result->results[] = (object)array('id' => $line->id, 'productCode' => $line->productCode, 'success' => false, 'msg' => "err Converter class missing Product Code on import");
			$result->numFails += 1;
			continue;
		}
		else {
			// Try to load product to see if it exists
			if($this->action != 'insert') {
				if((int)$line->id) {
					$product = new Product((int)$line->id);
				}
				else {
					$product = new Product($this->trader);
					$product->loadByProductCode($line->productCode);
				}
			}

			if($this->action == 'update' and !$product->id and !$this->insertBeforeUpdate) { // update fails on new products unless insertBeforeUpdate is true
				$result->results[] = (object)array('id' => $line->id, 'productCode' => $line->productCode, 'success' => false, 'msg' => "err Converter class update on non existing product");
				$result->numFails += 1;
				continue;
			}
			if($this->trader->id != $product->getTrader()->data->id) {
				$result->results[] = (object)array('id' => $line->id, 'productCode' => $line->productCode, 'success' => false, 'msg' => "err Converter class product belongs to another trader");
				$result->numFails += 1;
				continue;
			}
			$insert = false;
			if($this->action == 'insert' or !$product->id) {
				$insert = true;
				if(!$product->validateUniqueness('productCode', $line->productCode)) {
					$result->results[] = (object)array('id' => $line->id, 'productCode' => $line->productCode, 'success' => false, 'msg' => "err Converter class product code already in use");
					$result->numFails += 1;
					continue;
				}
			}			
			if($this->action == 'replace') {
				$product->delete();
			}
			$update_success = true;
			$fails = new stdClass;
			foreach($line as $attribute => $value) {
				$update = $product->setAttribute($attribute, $value, true);
				if(!$update->success) {
					$fails->{$attribute} = (object)array('msg' => $update->msg, 'value' => $value);
					$update_success = false;
				}
			}
			if(!$update_success) {
				$result->results[] = (object)array('id' => $line->id, 'productCode' => $line->productCode, 'success' => false, 'msg' => $update->msg, 'fails' => $fails);
				$result->numFails += 1;
			}
			else if($insert) {
				$result->data[] = $result->inserted[] = $product;
				$result->numInserts += 1;
			}
			else if($this->action == 'replace') {
				$result->data[] = $result->replaced[] = $product;
				$result->numReplacements += 1;
			}
			else if($this->action == 'update') {
				$result->data[] = $result->updated[] = $product;
				$result->numUpdates += 1;
			}	
		}
	}
	return $result;
}


// Loads converter details from the DB
/****************************************/
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg (string): (Error) message	
function load() {
	$tp = $this->mysqli->table_prefix;
	$result = new stdClass;
	
	$data = $this->mysqli->arrayData(array(
		'source'		=> "{$tp}converters",
		'where'			=> "{$tp}converters.id = '$this->id'"
	));

	if(!$data->totalRows) {
		return (object)array('success' => false, 'msg' => "Converter not found");
	}
	else {
		// assigning properties from the DB query
		$fieldset = $data->data[0];
		
		if(json_decode($fieldset->fields) === null) {
			return (object)array('success' => false, 'msg' => "err Converter class corrupted fields JSON", "json" => $fieldset->fields);
		}

		$this->trader = new Trader($fieldset->trader);
		$this->name = $fieldset->name;
		$this->description = $fieldset->description;
		$this->action = $fieldset->action;
		$this->insertBeforeUpdate = (bool)$fieldset->insertBeforeUpdate;
		$this->format = $fieldset->format;
		$this->escape = $fieldset->escape;
		$this->csvSeparator = $fieldset->csvSeparator;
		$this->csvWrapper = $fieldset->csvWrapper;
		$this->csvQuoteText = (bool)$fieldset->csvQuoteText;
		$this->csvHeaders = (bool)$fieldset->csvHeaders;
		$this->characterEncoding = $fieldset->characterEncoding;
		$this->feed = (bool)$fieldset->feed;
		$this->filetransferMethod = $fieldset->filetransferMethod;
		$this->filename = $fieldset->filename;
		$this->path = $fieldset->path;
		$this->host = $fieldset->host;
		$this->port = $fieldset->port;
		$this->user = $fieldset->user;
		$this->password = $fieldset->password;
		$this->fields = json_decode($fieldset->fields);
		$this->sftpKey = $fieldset->sftpKey;
		$this->sftpKeyPassword = $fieldset->sftpKeyPassword;

		return (object)array('success' => true);
	}
}


// reads data from file
/****************************************/
//	$data: (string) content to write
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
//		msg (string): (Error) message	
//		data: Imported data	
private function readFromFile() {
	$tp = $this->mysqli->table_prefix;
	$result = new stdClass;
	ini_set('auto_detect_line_endings', TRUE);
	
	if(!$this->filename) {
		$result->success = false;
		$result->msg = "err Converter class file name not spesified";
	}

	if($this->filetransferMethod == "local") {
		$result->success = true;
		if(($data = file_get_contents("{$this->path}{$this->filename}")) === false) {
			$result->success = false;
			$result->msg = "err Converter class could not read the import file '{$this->path}{$this->filename}'";
		}
	}
	else if($this->filetransferMethod == "ftp") {
//
	}
	else if($this->filetransferMethod == "sftp") {
		$key = new Crypt_RSA();
		$key->setPassword($this->sftpKeyPassword);
		$key->loadKey(file_get_contents($this->sftpKey));
		$sftp = new Net_SFTP($this->host, $this->port);
		if (!$sftp->login($this->user, $key)) {
			$result->success = false;
			$result->msg = "err Converter class sFTP login rejected";
			return $result;
		}
		$sftp->chdir($this->path);
		if(!$data = $sftp->get($this->filename)) {
			$result->msg = "err Converter class could not write to remote server using sftp";
			return $result;			
		}
	}
	
	$result->data = mb_convert_encoding($data, "UTF-8", $this->characterEncoding);
	return $result;
}


// Saves converter details to DB
/****************************************/
//	$trader: Trader object
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): operation was successful		
function save($trader = 0) {
	if($this->trader instanceof Trader) {
		$trader = $this->trader->id;
	}
	else if($trader instanceof Trader) {
		$trader = $trader->id;
	}
	settype($trader, 'integer');
	if(!$trader) {
		return (object)array('success' => false, 'msg' => "err Converter class Trader not specified");
	}
	
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->saveToDb(array(
		'id'		=> $this->id,
		'table'		=> "{$tp}converters",
		($this->id ? 'update' : 'insert')	=> true,
		'where'		=>	($this->id ? "id = '{$this->id}'" : ""),
		'fields'	=> array(
			'trader'		=> $trader,
			'name'			=> $this->name,
			'description'	=> $this->description,
			'action'		=> $this->action,
			'insertBeforeUpdate'	=> $this->insertBeforeUpdate,
			'format'		=> $this->format,
			'escape'		=> $this->escape,
			'csvSeparator'	=> $this->csvSeparator,
			'csvWrapper'	=> $this->csvWrapper,
			'csvQuoteText'	=> $this->csvQuoteText,
			'csvHeaders'	=> $this->csvHeaders,
			'characterEncoding'	=> $this->characterEncoding,
			'feed'			=> $this->feed,
			'filetransferMethod' => $this->filetransferMethod,
			'filename'		=> $this->filename,
			'path'			=> $this->path,
			'host'			=> $this->host,
			'port'			=> $this->port,
			'user'			=> $this->user,
			'password'		=> $this->password,
			'sftpKey'		=> $this->sftpKey,
			'sftpKeyPassword'	=> $this->sftpKeyPassword,
			'fields'		=> json_encode($this->fields)
		)
	));
	if($result->success) {
		$this->id = $result->id;
		$this->load();
	}
	return $result;
}


// Writes export data to file
/****************************************/
//	$data: (string) content to write
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (boolean): wether operation was successful		
private function writeToFile($data) {
	$data = mb_convert_encoding($data, $this->characterEncoding, "UTF-8");
	
	$tp = $this->mysqli->table_prefix;
	$result = new stdClass;
	
	if(!$this->filename) {
		$result->success = false;
		$result->msg = "err Converter class file name not spesified";
	}

	if($this->filetransferMethod == "local") {
		$result->success = true;
		if(file_put_contents("{$this->path}{$this->filename}", $data) === false) {
			$result->success = false;
			$result->msg = "err Converter class could not write to file";
		}
		return $result;
	}
	else if($this->filetransferMethod == "ftp") {
//
	}
	else if($this->filetransferMethod == "sftp") {
		$key = new Crypt_RSA();
		$key->setPassword($this->sftpKeyPassword);
		$key->loadKey(file_get_contents($this->sftpKey));
		$sftp = new Net_SFTP($this->host, $this->port);
		if (!$sftp->login($this->user, $key)) {
			$result->success = false;
			$result->msg = "err Converter class sFTP login rejected";
			return $result;
		}
		$sftp->chdir($this->path);
		if(!$result->success = $sftp->put($this->filename, $data)) {
			$result->msg = "err Converter class could not write to remote server using sftp";
			return $result;			
		}
	}
	
	return $result;
}


}

?>