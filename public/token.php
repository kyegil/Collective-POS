<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
if(!defined('LEGAL')) die('No access!<br />Check your URI.');

class Docu extends CollectivePOS {
	public $template = "HTML";
	public $title = '';
	

public function __construct() {
	parent::__construct();
	$this->title = $this->say('authoriser temporary access token');

	if (isset($_POST['email'])) {
		$email = $_POST['email'];
		$referer = @$_POST['referer'];
 
//		if ($this->authoriser->identifier->login($login, $password) == true) {
//			header("Location: {$referer}");
//		}
	}
}

public function script() {
}

public function design() {
	$referer = @$_GET['referer'];

?>
	<div id="panel">
		<div id="content">
			<div class="token">
				<div class="information">
					<?php if($this->authoriser->cuEnsalutinta()):?>
						<?php echo $this->say('authoriser you are currently logged in as', array($this->authoriser->akiruNomo()));?><br>
						<a href="<?php echo $referer;?>"><?php echo $this->say('return');?></a>
					<?php endif;?>
				</div>
				<div class="information">
					<?php echo $this->say('authoriser token desc');?><br>
				</div>
				<form action="<?php echo $this->authoriser->identifier->escapeUrl($_SERVER['PHP_SELF']); ?>?docu=token" method="post" accept-charset="utf-8">
					<div style="display:none;">
						<input type="hidden" name="_method" value="POST"/>
					</div>
					<fieldset>
						<legend><?php echo $this->say('authoriser enter the email address for the temporary access token');?></legend>
						<div class="input email required">
							<label for="email"><?php echo $this->say('authoriser email');?></label>
							<input name="email" maxlength="500" type="text" id="email" required="required"/>
						</div>
						<input type="hidden" name="referer" value="<?php echo $referer;?>" id="referer"/>
					</fieldset>
					<div class="submit">
						<input type="submit" value="<?php echo $this->say('authoriser enter');?>"/>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php
}

public function task($task = "") {
	$tp = $this->mysqli->table_prefix;
	$dec = new NumberFormatter($this->say('locale'), NumberFormatter::DECIMAL);

	switch ($task) {
	default: {
		break;
	}
	}
}

public function request($data = "") {
	$tp = $this->mysqli->table_prefix;
	$dec = new NumberFormatter($this->say('locale'), NumberFormatter::DECIMAL);

	switch ($data) {

	default: {
		break;
	}
	}
}

public function amend($data = "") {
	$tp = $this->mysqli->table_prefix;
	$dec = new NumberFormatter($this->say('locale'), NumberFormatter::DECIMAL);

	switch ($data) {
	default: {
		break;
	}
	}
}

function receive($form) {
	$tp = $this->mysqli->table_prefix;
	$dec = new NumberFormatter($this->say('locale'), NumberFormatter::DECIMAL);

	switch ($form) {
	default: {
		echo json_encode($result);
	}
	}
}

}
?>