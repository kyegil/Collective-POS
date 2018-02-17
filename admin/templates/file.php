<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('No access!<br />Check your URI.');
$this->setLocale($this->preferences->invoice_language);

	header("Content-type: text/plain; charset=utf-8");
	header("Content-Disposition: attachment; filename=\"{$this->title}\"");
	
	$this->design();
?>
