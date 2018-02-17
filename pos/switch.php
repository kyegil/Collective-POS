<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {
	public $title = 'Point of Sale';
	public $template = "HTML";
	

function __construct() {
	parent::__construct();
	$this->current_sale = new Sale(array(
		'id' => @$_GET['sale'])
	);
}

function script() {
?>
Ext.Loader.setConfig({
	enabled: true
});
Ext.Loader.setPath('Ext.ux', '<?=$this->http_host . "/" . $this->ext_library . "/examples/ux"?>');

Ext.require([
 	'Ext.data.*'
]);

Ext.onReady(function(){
	login = function(username) {
		Ext.Ajax.request({
			waitMsg: '<?=$this->say('hang on', array())?>',
			url: "index.php?docu=switch&mission=task&task=change_user",
			params: {
				data: username
			},
			success :function(response, opts){
				var result = Ext.JSON.decode(response.responseText);
				if(result['success'] == true) {
					window.location = "../pos/index.php";
				}
				else {
					window.location = "../public/index.php?docu=login&referer=http%3A%2F%2Fpos.maya-b.com%2Fpos%2Findex.php";
				}
			}
		});
	}
});
<?php
}

function design() {
	if (version_compare(PHP_VERSION, '7.0.0', '<')) {
		$sessions_manager = new SessionsManager;
		$sessions = $sessions_manager->get_sessions();
		$users = $sessions_manager->get_users();
	}
	
	else {
		$authoriser = $this->authoriser;

		$sessions_manager = $authoriser->session;
		$sessions = $sessions_manager->getAllSessions();
		$users = $authoriser->identifier->getAllUsers();
		
		$loggedIn = array();
		foreach(@$sessions as $session) {
			if($session->data) {
				settype($session->data['logged_in_users'], 'array');
				foreach($session->data['logged_in_users'] as $user) {
					settype($loggedIn[$user->login], 'bool');
					$loggedIn[$user->login] |= (bool)$session->available;
				}
			}
		}
	}
?>
<div style="height: 100px;"></div>
<p class="switch">
	
		<?php if(version_compare(PHP_VERSION, '7.0.0', '<')):?>
			<?php foreach(@$sessions as $session):?>
				<?php if($session->available):?>
					<?php $username = $sessions_manager->get_user_from_session($session->ses_value)->username?>
					<button onclick="login('<?php echo $username?>')"><?php echo $username?></button>
				<?php endif;?>
			<?php endforeach;?>
			<?php foreach(@$sessions as $session):?>
				<?php if(!$session->available):?>
					<button class="disabled" style="background-color: rgba(153,153,153,0.8);"><?php echo $sessions_manager->get_user_from_session($session->ses_value)->username?></button>
				<?php endif;?>
			<?php endforeach;?>

		<?php else:?>
			<?php foreach($loggedIn as $login => $available ):?>
				<?php if($available):?>
					<button onclick="login('<?php echo $login?>')"><?php echo $login;?></button>
				<?php else:?>
					<button class="disabled" style="background-color: rgba(153,153,153,0.8);"><?php echo $login;?></button>
				<?php endif;?>
			<?php endforeach;?>
		<?php endif;?>

</p>

<div style="height: 100px;"></div>
<p class="switch">
	<?php foreach($users as $user):?>
		<?php if(version_compare(PHP_VERSION, '7.0.0', '<')):?>
			<button style="font-size: 1.2em; background-color: #990000; border-color: #aa0000;" onclick="login('<?php echo $user->username?>')"><?php echo $user->username?></button>
		<?php else:?>
			<button style="font-size: 1.2em; background-color: #990000; border-color: #aa0000;" onclick="login('<?php echo $user->login?>')"><?php echo $user->login?></button>
		<?php endif;?>
	<?php endforeach;?>
			<a href="../index.php?mission=exit">
				<img src="../../images/exit.png" style="height: 50px; margin: 4px; vertical-align: middle;">
			</a>
</p>
<?php
}

function request($data = "") {
	$tp = $this->mysqli->table_prefix;
	switch ($data) {
		default:
//			echo json_encode($this->main_data);
			break;
	}
}

function receive($form) {
	switch ($form) {
		default:
//			echo json_encode($this->main_data);
			break;
	}
}

function task($task = "") {
	switch ($task) {
		case "change_user":
			if(version_compare(PHP_VERSION, '7.0.0', '<')) {
				$sessions_manager = new SessionsManager;
				$result['success'] = $sessions_manager->change_user($_POST['data']);
			}
			else {
				$authoriser = $this->authoriser;
				$result['success'] = $authoriser->identifier->setCurrentUser($_POST['data']);
			}
			echo json_encode($result);
			break;
		default:
			echo json_encode($result);
			break;
	}
}

function amend($data = "") {
	switch ($data) {
		default:
//			echo json_encode($this->main_data);
			break;
	}
}

}

?>