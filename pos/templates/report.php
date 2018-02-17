<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('No access!<br />Check your URI.');
$library= $this->http_host . "/" . $this->ext_library;
$this->setLocale($this->preferences->invoice_language);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:ext="http://www.extjs.com" xml:lang="<?$this->say['locale']?>" lang="<?=$this->say['locale']?>">

<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
	<title><?=$this->title;?></title>
	<link rel="stylesheet" type="text/css" href="<?=$this->http_host;?>/stylesheet.css" />
	<script language="JavaScript" type="text/javascript">
	<?$this->script();?>
	</script>
</head>

<body class="dataload">
<?$this->design();?>
</body>
</html>
