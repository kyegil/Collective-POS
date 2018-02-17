<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
class Supplier {

public $id;
public $trader;
public $name;

function __construct($autoloadId = 0) {
	settype($autoloadId, "integer");
//	if($autoloadId) $this->load($autoloadId);
}


}

?>