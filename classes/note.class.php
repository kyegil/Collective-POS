<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
class Note {

public $mysqli; // The MySQLi connection
public $id;

function __construct($id) {
	global $mysqliConnection;
	$this->mysqli = $mysqliConnection;
	$this->id = (int)$id;
}


// Load this note
/****************************************/
//	$revert (int):		How many of the latest versions of the note to skip
//	--------------------------------------
//	return: stdClass object with the following properties:
function load($revert = 0) {
	settype($revert, 'integer');
	$tp = $this->mysqli->table_prefix;
	$result = $this->mysqli->arrayData(array(
		'source'		=> "{$tp}notes",
		'where'			=> "note = '{$this->id}'",
		'orderfields'	=> "timestamp DESC",
		'limit'			=> "{$revert}, 1"
	));
	if(count($result->data)) {
		$result->data[0]->timestamp = new DateTime($result->data[0]->timestamp . "+0:00", new DateTimeZone('UTC'));
		$result->data[0]->extra_info = json_decode($result->data[0]->extra_info);
		return $result->data[0];
	}
	else {
		$result = new stdClass;
		$result->extra_info = new stdClass;
		return $result;
	}
}


// Save this note
/****************************************/
//	$text (string):			The text
//	$author (string):		The author saving the text
//	$extra_info (array/object):	Extra info
//	--------------------------------------
//	return: stdClass object with the following properties:
//		success (bool):	wether operation was successful or not	
//		msg	(string):	message that explains the success parameter
function save($title, $text, $author, $extra_info = array()) {
	settype($extra_info, 'array');
	if(!$extra_info) {
		$extra_info = $this->load()->extra_info;
	}
	$tp = $this->mysqli->table_prefix;
	return $this->mysqli->saveToDb(array(
		'table'		=> "{$tp}notes",
		'fields' => array(
			'note'			=> $this->id,
			'author' 		=> $author,
			'title'			=> $title,
			'text'			=> $text,
			'extra_info'	=> json_encode($extra_info)
		),
		'insert'	=> true,
		'update'	=> false
	));
}


}
?>