<?php
/*************************************************************************
*****************************      RETURI     ****************************
by Kay-Egil Hauan
php class to provide uri history for return / back buttons in applications
This version 2015-05-21
**************************************************************************/

//	session_start(); // Start session with this if not started by other parts of the application

// require_once("./config.php");

class returi {

private $cookie			= RETURI_LOG_COOKIE;
private $expiry			= RETURI_LOG_EXPIRY;
private $session		= "";
private $table			= RETURI_LOG_TABLE;
public $default_uri		= RETURI_LOG_DEFAULT_URI;
public $mysqli;

public function __construct() {
	global $mysqliConnection;
	$this->mysqli = $mysqliConnection;

	$this->determine_session();
	$this->clear();
}

function connect() {
	mysql_connect($this->mysql_host, $this->mysql_username, $this->mysql_pw);
	mysql_select_db($this->db);
	if(mysql_query("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'")) return true;
	else return mysql_error();
}

function install() {
	$sql = "CREATE TABLE IF NOT EXISTS `" . $this->mysqli->real_escape_string($this->table) . "` (
  `id` int(11) NOT NULL auto_increment,
  `time` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `session` varchar(256) NOT NULL,
  `uri` varchar(2000) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `time` (`time`),
  KEY `session` (`session`),
  KEY `uri` (`uri`(333))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
";
	if($this->mysqli->query($sql)) return true;
	else return $this->mysqli->error;
}

function uninstall() {
	$sql = "DROP TABLE `" . $this->mysqli->real_escape_string($this->table) . "`;";
	if($this->mysqli->query($sql)) return true;
	else return $this->mysqli->error;
}

function determine_uri() {
	$result = (
		@$_SERVER["HTTPS"] == "on"
		? "https://"
		: "http://"
	)
	. $_SERVER['SERVER_NAME']
	. (
		($_SERVER["SERVER_PORT"] != "80")
		? ":{$_SERVER['SERVER_PORT']}"
		: ""
	)
	. $_SERVER['REQUEST_URI'];
	return $result;
}

function determine_session() {
	if(isset($_COOKIE[$this->cookie])) {
		$this->session = $_COOKIE[$this->cookie];
	}
	else {
		$this->session = $_SERVER['REMOTE_ADDR'] . " " . $_SERVER['HTTP_USER_AGENT'];
	}
}

function set($uri = "", $session = "") {
	if(!$uri) $uri = $this->determine_uri();
	if(!$session) $session = $this->session;
	
	if($uri == $this->default_uri) {
		$this->reset();
	}
	else {
		$sql = "DELETE `{$this->table}`.*
			FROM `{$this->table}`
			INNER JOIN
			(
				SELECT MIN( id ) AS id 
				FROM `{$this->table}`
				WHERE uri = '" . $this->mysqli->real_escape_string($uri) . "'
				AND `session` = '" . $this->mysqli->real_escape_string($session) . "'
			) AS first
			ON `{$this->table}`.id >= first.id";
		$this->mysqli->query($sql);
	}
	
	$sql = "INSERT INTO `{$this->table}` SET `session` = '" . $this->mysqli->real_escape_string($session) . "', `uri` = '" . $this->mysqli->real_escape_string($uri) . "'";
	return $this->mysqli->query($sql);
}

function reset($session = "") {
	if(!$session) $session = $this->session;
	
	$sql = "DELETE FROM `{$this->table}` WHERE `session` = '" . $this->mysqli->real_escape_string($session) . "'";
	return $this->mysqli->query($sql);
}

function clear() {
	$sql = "DELETE FROM `{$this->table}` WHERE `time` < DATE_SUB(NOW(), INTERVAL {$this->expiry})";
	return $this->mysqli->query($sql);
}

function truncate() {
	return $this->mysqli->query("TRUNCATE `{$this->table}`");
}

function get($skip = 0, $uri = "", $session = "") {
	if(!$uri) $uri = $this->determine_uri();
	if(!$session) $session = $this->session;
	
	$sql = "SELECT `uri`
		FROM `{$this->table}`
		WHERE uri != '" . $this->mysqli->real_escape_string($uri) . "'
		AND `session` = '" . $this->mysqli->real_escape_string($session) . "'
		ORDER BY id DESC
		LIMIT " . (int)$skip . ",1
		";
	$row = $this->mysqli->query($sql)->fetch_assoc();
	if($row['uri']) return $row['uri'];
	else return $this->default_uri;
}

}

?>