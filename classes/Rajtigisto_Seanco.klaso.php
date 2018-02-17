<?php
/**********************************************
universala rajtigisto interfaco
de Kay-Egil Hauan
**********************************************/

require_once("Rajtigisto.klaso.php");

require_once('Session.class.php');
require_once('Identifier.class.php');

class Rajtigisto_Seanco extends Rajtigisto {


public $session;
public $identifier;
protected $extraInfo;


public function __construct() {
	global $mysqliConnection;

	$this->session = new kyegil\sessions\Session(array(
		'mysqli'					=> $mysqliConnection,
		'dbSessionsTable'			=> 'access_user_sessions',
		'dbSessionsIdField'			=> 'ses_id',
		'dbSessionsTimestampField'	=> 'ses_start',
		'dbSessionsTimestampFormat'	=> 'U',
		'dbSessionsKeyField'		=> 'ses_time',
		'dbSessionsDataField'		=> 'ses_value'
	));

	$this->komencuLaSeanco(array(
		'sessionName'	=> AUTHORISER_COOKIE_NAME,
		'secure'	=>	false
	));

	$this->identifier = new kyegil\sessions\Identifier(array(
		'sessionHandler'			=> $this->session,
		'mysqli'					=> $mysqliConnection,
		'db'						=> 'knitwith_collective',
		'dbUsersTable'				=> 'access_user_users',
		'dbIdField'					=> 'id',
		'dbLoginField'				=> 'login',
		'dbPasswordField'			=> 'pw',
		'dbEmailField'				=> 'email',
		'dbAdditionalUserFields'	=> array(
			'real_name',
			'extra_info',
			'registered'
		)
	));
}


public function akiri($atributo) {

	switch($atributo) {

	case "registered": {
		$currentUser = @$_SESSION['current_user'];
		return @$_SESSION['logged_in_users'][$currentUser]->registered;
		break;
	}

	case "extra_info": {
		if(!$this->extraInfo) {
            $currentUser = @$_SESSION['current_user'];
            $sql = "SELECT extra_info FROM {$this->identifier->dbUsersTable} WHERE {$this->identifier->dbLoginField} = '{$currentUser}'";
            $query = $this->identifier->mysqli->query($sql);
            if ($query) {
                $this->extraInfo = $query->fetch_object()->$atributo;
            }
        }

		return json_decode($this->extraInfo);
		break;
	}

	case "locale": {
		$currentUser = @$_SESSION['current_user'];
		return @$this->akiri('extra_info')->locale;
		break;
	}

	case "till": {
		$currentUser = @$_SESSION['current_user'];
		return @$this->akiri('extra_info')->till;
		break;
	}

	default: {
		return null;
	}
	}
}


public function akiruId() {
	$currentUser = @$_SESSION['current_user'];
	if( is_object(@$_SESSION['logged_in_users'][$currentUser]) ) {
		return @$_SESSION['logged_in_users'][$currentUser]->id;
	}
	else return null;
}


public function akiruNomo() {
	$currentUser = @$_SESSION['current_user'];
	if( is_object(@$_SESSION['logged_in_users'][$currentUser]) ) {
		return @$_SESSION['logged_in_users'][$currentUser]->real_name;
	}
	else return null;
}


public function akiruRetpostadreso() {
	$currentUser = @$_SESSION['current_user'];
	if( is_object(@$_SESSION['logged_in_users'][$currentUser]) ) {
		return @$_SESSION['logged_in_users'][$currentUser]->email;
	}
	else return null;
}


public function akiruUzantoNomo() {
	$currentUser = @$_SESSION['current_user'];
	if( is_object(@$_SESSION['logged_in_users'][$currentUser]) ) {
		return @$_SESSION['logged_in_users'][$currentUser]->login;
	}
	else return null;
}


// Aldonu Uzanto
/****************************************/
//	$agordoj (array):
//		id (int) identiganta entjero por la uzanto
//		uzanto (str) uzantonomo
//		nomo (str) la uzanto plena nomoj
//		retpostadreso (str) la uzanto retpoŝto
//		pasvorto: la uzanto pasvorto
//	--------------------------------------
//	return: (bool) indiko de sukceso
public function aldonuUzanto($agordoj = array()) {
	return false;
}


public function cuEnsalutinta() {
	return $this->identifier->checkIfLoggedIn();
}


public function cuHavasPermeson($agordoj) {
//	return false;
}


public function cuHavasRolon() {
//	return false;
}


public function cuLaPasvortoEstasValida($pasvorto) {
	return $this->identifier->validatePassword($pasvorto);
}


public function cuLaRetpostadresoEstasDisponebla($retpostadreso, $uzanto = "") {
	return $this->identifier->validateEmail($retpostadreso, $uzanto);
}


public function cuLaUzantoEkzistas($uzanto) {
	return $this->identifier->doesUserExist($uzanto);
}


public function cuLaUzantonomoEstasDisponebla($uzantoNomo, $uzanto = "") {
	return !$this->identifier->doesUserExist($uzanto);
}


public function donuPermeson() {
 	return false;
}


public function donuRolon() {
 	return false;
}


public function elsalutu() {
	$currentUser = @$_SESSION['current_user'];
	return $this->identifier->logout($currentUser);
}


public function ensalutu() {
	return $this->identifier->login();
}


public function komencuLaSeanco($agordoj = array()) {
	settype($agordoj, 'object');
	return $this->session->start($agordoj->sessionName, $agordoj->secure);
}


public function postuluIdentigon($agordoj = array()) {
	if( $this->cuEnsalutinta() == true ) {
		return true;
	}
	else {
		$current_page;
		$pageURL
		= ( @$_SERVER['HTTPS'] ? 'https://' : 'http://' )
		. (
			($_SERVER["SERVER_PORT"] != "80")
			? "{$_SERVER['SERVER_NAME']}:{$_SERVER['SERVER_PORT']}{$_SERVER['REQUEST_URI']}"
			: "{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}"
		);
		header("Location: " . INSTALL_URI . 'public/index.php?docu=login&referer=' . urlencode($pageURL));
	}
}


public function revokuPermeson() {
 	return false;
}


public function revokuRolon() {
 	return false;
}


public function sanguNomo($uzanto, $nomo) {
	$login = $this->identifier->getLoginFromId( $uzanto );
	return $this->identifier->editUser( $login, null, null, null, (object)array('real_name' => $nomo) );
}


public function sanguPasvorto($uzanto, $pasvorto) {
 	if(!$this->cuLaPasvortoEstasValida($pasvorto)) {
 		return false;
 	}
	$login = $this->identifier->getLoginFromId( $uzanto );
	return $this->identifier->editUser( $login, null, $pasvorto );
}


public function sanguRetpostadreso($uzanto, $retpoŝtadreso) {
	$login = $this->identifier->getLoginFromId( $uzanto );
	return $this->identifier->editUser( $login, null, null, $retpoŝtadreso );
}


public function sanguUzantoNomo($uzanto, $uzantoNomo) {
	$login = $this->identifier->getLoginFromId( $uzanto );
	return $this->identifier->editUser( $login, $uzantoNomo );
}


public function trovuNomo($uzanto) {
	$login = $this->identifier->getLoginFromId( $uzanto );
	$user = $this->identifier->getUser($login);
	return $user->real_name;
}


public function trovuRetpostadreso($uzanto) {
	$login = $this->identifier->getLoginFromId( $uzanto );
	$user = $this->identifier->getUser($login);
	return $user->email;
}


public function trovuUzantoNomo($uzanto) {
	return $this->identifier->getLoginFromId( $uzanto );
}


}

?>
