<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/

if(class_exists('access_user')) {
class SessionsManager extends Access_user {
public $stored_sessions_table = "kwaock_sleeping_sessions";

function __construct() {
	parent::__construct();
	$sql = sprintf("CREATE TABLE IF NOT EXISTS %s (ses_id varchar(32) collate utf8_danish_ci NOT NULL default '', ses_time int(11) NOT NULL default '0', ses_start int(11) NOT NULL default '0', ses_value text collate utf8_danish_ci NOT NULL);", $this->stored_sessions_table);
	mysql_query($sql);
	$this->delete_overstored_sessions();
}

/****************************************/
//	userid:		(int) userid if known
//	username:	(string) username to be used if userid is not known
//	--------------------------------------
//	return:		User object
function get_user($userid, $username = '') {
	if($userid) {
		$sql = sprintf("SELECT real_name AS name, login AS username, extra_info, email, id, pw FROM %s WHERE id = %s", $this->table_name, $this->ins_string($userid));
	}
	else {
		$sql = sprintf("SELECT real_name AS name, login AS username, extra_info, email, id, pw FROM %s WHERE login = %s", $this->table_name, $this->ins_string($username));
	}
	$res = mysql_query($sql);
	if(!mysql_num_rows($res)) return new User;
	$extra_info = json_decode(mysql_result($res, 0, "extra_info"));
	return new User(array(
		'id' => mysql_result($res, 0, "id"),
		'username' => mysql_result($res, 0, "username"),
		'password' => mysql_result($res, 0, "pw"),
		'name' => mysql_result($res, 0, "name"),
		'email' => mysql_result($res, 0, "email"),
		'locale' => $extra_info->locale,
		'colour' => @$extra_info->colour
	));
}



/****************************************/
//	Get all registered users
/****************************************/
//	--------------------------------------
//	return: array of User objects:
function get_users() {
	global $ses_class;
	$sql = "SELECT * FROM {$this->table_name}"; 
	$res = @mysql_query($sql); 

	$data = array();
	while($row = mysql_fetch_assoc($res)) {
		$data[] = $this->get_user($row['id']);
	}
	return $data; 
}
/****************************************/



/****************************************/
/****************************************/
//	--------------------------------------
//	return: array with the active as well as sleeping sessions as stdclass object:
//		ses_id:		(string) Session id
//		ses_time:	(int) Session time
//		ses_start: 	(int) Session start
//		ses_value:	(string) Session Value
//		available	(boolean) Is this session available from current client computer?
function get_sessions() {
	global $ses_class;
	$session_sql = "SELECT *, IF(ses_id='" . session_id() . "', 1, 0) AS available FROM {$ses_class->ses_table} UNION SELECT *, IF(ses_id='" . session_id() . "', 1, 0) AS available FROM {$this->stored_sessions_table}"; 
	$session_res = @mysql_query($session_sql); 
	if (!$session_res) { 
		return false; 
	} 

	$ses_data = array();
	while($session_row = mysql_fetch_assoc($session_res)) {
		$ses_data[] = (object)$session_row;
	}
	return $ses_data; 
}
/****************************************/



/****************************************/
//	ses_value:	(string) session values as stored in DB
//	--------------------------------------
//	return: stdclass object with the following properties:
//		id:			(string) user id
//		user:		(string) login name
//		name:		(string) full name
//		pw:			(string) user password encrypted
//		extra_info:	(string) extra info
//		email:		(string) user email
//		logged_in:	(DateTime object) logged in time
function get_user_from_session($ses_value) {
	$unserialised = $this->unserialize($ses_value);
	$user = $this->get_user(0, $unserialised['user']);
	$user->logged_in = new DateTime("@" . (int)$unserialised['logged_in']);
	return $user;
}
/****************************************/



/****************************************/
//	session_id:	(string)
//	--------------------------------------
function put_aside($session_id = '') {
	global $ses_class;
	if(!@$_SESSION['user']) {
		return;
	}
	if(!$session_id) {
		$session_id = session_id();
	}

	$sql = sprintf("INSERT INTO %s SELECT * FROM %s WHERE ses_id = '%s';", $this->stored_sessions_table, $ses_class->ses_table, $session_id);
	mysql_query($sql);
}

/****************************************/
//	session_value:	(string)
//	session_id:		(string) Defaults to current session
//	--------------------------------------
function delete_stored_session($session_value, $session_id = '') {
	global $ses_class;
	if(!$session_id) {
		$session_id = session_id();
	}

	$sql = sprintf("DELETE FROM %s WHERE ses_id = '%s' AND ses_value = '%s';", $this->stored_sessions_table, $session_id, $session_value);
	return mysql_query($sql);
}

/****************************************/
//	session_id:		(string) Defaults to current session
//	--------------------------------------
function reclaim_stored_session($session_id = '') {
	global $ses_class;
	if(!$session_id) {
		$session_id = session_id();
	}

	$sql = sprintf("SELECT * FROM %s WHERE ses_id = '%s' ORDER BY ses_time DESC;", $this->stored_sessions_table, $session_id);
	$ses = mysql_query($sql);
	if(mysql_num_rows($ses)){
		$sql = sprintf("UPDATE %s SET ses_time = '%s', ses_start = '%s', ses_value = '%s' WHERE ses_id = '%s';", $this->stored_sessions_table, mysql_result($res, 0, "ses_start"), mysql_result($res, 0, "ses_value"), $session_id);
		mysql_query($sql);
		$this->delete_stored_session(mysql_result($res, 0, "ses_value"), $session_id);
	}
}



/****************************************/
//	username:	(string)
//	goto_page:	(int) 
//	--------------------------------------
function change_user($username) {
	global $ses_class;
	
	$result = false;
	$sessions = $this->get_sessions();
	foreach($sessions as $session) {
		$user = $this->get_user_from_session($session->ses_value);
		if($user->username == $username and $session->available) {
			$this->put_aside();

			$_SESSION['user'] = $user->username;
			$_SESSION['pw'] = $user->password;
			$_SESSION['logged_in'] = $user->logged_in->getTimestamp;
			
			$result = $this->delete_stored_session($session->ses_value);
		}
	}
	return $result;
}



// Rewrite of Access_user's login method
/****************************************/
//	username:	(string)
//	goto_page:	(int) 
//	--------------------------------------
function login_user($user, $password) {
	if ($user != "" && $password != "") {
		$this->user = $user;
		$this->user_pw = md5($password);
		if ($this->check_user()) {
			$this->login_saver();
			if ($this->count_visit) {
				$this->reg_visit($user, $this->user_pw);
			}
 // The following line is new from original method
			$this->put_aside();
//
			$this->set_user(true);
		} else {
			$this->the_msg = $this->messages(10);
		}
	} else {
		$this->the_msg = $this->messages(11);
	}
}

function log_out() {
	$sessions = $this->get_sessions();
	foreach($sessions as $session) {
		$user = $this->get_user_from_session($session->ses_value);
			if($user->username == $_SESSION['user']) {
				$this->delete_stored_session($session->ses_value);
			}
	}
	$this->reclaim_stored_session();
	parent::log_out();
}

function delete_overstored_sessions() {
	$ses_life = time() - 7200;

	$session_sql = "DELETE FROM {$this->stored_sessions_table} WHERE ses_time < $ses_life";

	return mysql_query ($session_sql);
}

public static function unserialize($session_data) {
	$method = ini_get("session.serialize_handler");
	switch ($method) {
		case "php":
			return self::unserialize_php($session_data);
			break;
		case "php_binary":
			return self::unserialize_phpbinary($session_data);
			break;
		default:
			throw new Exception("Unsupported session.serialize_handler: " . $method . ". Supported: php, php_binary");
	}
}

private static function unserialize_php($session_data) {
	$return_data = array();
	$offset = 0;
	while ($offset < strlen($session_data)) {
		if (!strstr(substr($session_data, $offset), "|")) {
			throw new Exception("invalid data, remaining: " . substr($session_data, $offset));
		}
		$pos = strpos($session_data, "|", $offset);
		$num = $pos - $offset;
		$varname = substr($session_data, $offset, $num);
		$offset += $num + 1;
		$data = unserialize(substr($session_data, $offset));
		$return_data[$varname] = $data;
		$offset += strlen(serialize($data));
	}
	return $return_data;
}

private static function unserialize_phpbinary($session_data) {
	$return_data = array();
	$offset = 0;
	while ($offset < strlen($session_data)) {
		$num = ord($session_data[$offset]);
		$offset += 1;
		$varname = substr($session_data, $offset, $num);
		$offset += $num;
		$data = unserialize(substr($session_data, $offset));
		$return_data[$varname] = $data;
		$offset += strlen(serialize($data));
	}
	return $return_data;
}
}
}
?>