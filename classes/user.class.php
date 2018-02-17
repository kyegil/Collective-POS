<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
class User {

public $id;
public $username;
public $name;
public $password;
public $email;
public $locale;
public $colour;

function __construct($config = array()) {
	foreach($config as $property => $value){
		if(property_exists($this, $property)) {
			$this->$property = $value;
		}
	}
}


}

?>