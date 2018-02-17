<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
This file last edited 2016-01-21
**********************************************/

//	Share functionality for all Classes that are representing Objects saved in the database

abstract class DatabaseObject {

protected	$table;		// The db table storing the primary key for this Object
protected	$idField;	// The table field containing the primary key
protected	$pos;		// Property holding the CollectivePOS object
protected	$mysqli;	// The MySQLi connection
protected	$data;		// Internal cache for the db values
public		$id;		// Unique ID for this object


// Default constructor for all child classes
/****************************************/
//	$id (integer / tekst) id-value as stored in the database	
//	--------------------------------------
public function __construct( $id = null ) {
	global $docu;
	$this->pos = $docu;

	global $mysqliConnection;
	$this->mysqli = $mysqliConnection;
	
	$this->id = $id;
	
}


protected function _get($name) {
	if( isset($this->data->{$name}) ) {
		return $this->data->{$name};
	}
	else {
		throw new Exception("Property '{$name}' has not been declared and can not be retrieved.");
	}	
}


public function __get($name) {
	$resultat = $this->_get( $name );
	if( $resultat ) {
		return $resultat;
	}
	else {
		throw new Exception("Property '{$name}' has not been declared and can not be retrieved.");
	}	
}


// Textual representation of the object will be its id
/****************************************/
//	--------------------------------------
//	return:	(int) id for the object
public function __toString() {
	return (string)$this->id;
}


}
?>