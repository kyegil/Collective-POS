<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('No access!<br />Check your URI.');
$this->setLocale($this->preferences->invoice_language);
$voucher = new GiftVoucher((int)$_GET['id']);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:ext="http://www.extjs.com" xml:lang="<?=$this->say['locale']?>" lang="<?=$this->say['locale']?>">

<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
	<title><?=$this->say('giftvoucher')?></title>
	<link rel="stylesheet" type="text/css" href="<?=$this->http_host;?>/stylesheet.css" media="screen" />
</head>

<body class="receipt">
	<h1><?=$this->say('giftvoucher')?>:</h1>
	<h2><?=$this->money($voucher->value)?></h2>

	<p><?=$this->say('giftvoucher is accepted by traders')?>:</p>
	<?foreach($voucher->traders as $trader):?>
		<p><strong><?=$trader->name?></strong><br />
		<?=nl2br($trader->address)?></p>
	<?endforeach;?>
	
	<p><?=$this->say('giftvoucher code') . ": " . $voucher->code?></p>
	<p><?=$this->say('giftvoucher issued date') . ": " . $this->shortdate($voucher->issued)?></p>
	
	<?if($voucher->expires):?>
		<p><?=$this->say('giftvoucher expires') . ": " . $this->shortdate($voucher->expires)?></p>
	<?endif;?>
	
	<?if(!$voucher->redeemableForCash):?>
		<p><?=$this->say('giftvoucher is non exchangeable')?></p>
	<?endif;?>
	
	<script type="text/javascript">
		window.print();
	</script>
</body>
</html>