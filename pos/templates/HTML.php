<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('No access!<br />Check your URI.');
$library= $this->http_host . "/" . $this->ext_library;
	$theme = "ext-all-gray";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:ext="http://www.extjs.com" xml:lang="<?=$this->say('locale')?>" lang="<?=$this->say('locale')?>">

<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
	<title><?=$this->title;?></title>

	<link rel="stylesheet" type="text/css" href="<?=$library;?>/resources/css/ext-all.css" media="screen" />
	<link rel="stylesheet" type="text/css" href="<?=$library;?>/resources/css/<?=$theme?>.css" />
	<link rel="stylesheet" type="text/css" href="<?=$this->http_host;?>/stylesheet.css" media="screen" />

	<script language="JavaScript" type="text/javascript" src="<?=$this->http_host?>/kwaock_scripts.js"></script>
	<script language="JavaScript" type="text/javascript" src="<?=$library;?>/ext-all.js"></script>
<?if(file_exists("../{$this->ext_library}/locale/ext-lang-{$this->say('locale')}.js")):?>
	<script language="JavaScript" type="text/javascript" src="<?=$library;?>/locale/ext-lang-<?=$this->say('locale')?>.js"></script>
<?endif?>
	<script language="JavaScript" type="text/javascript">
	<?$this->script();?>
	</script>
</head>

<body style="background-color: black;">
<table style="width:100%;">
	<tr>
		<td style="vertical-align: middle;">
			<a href="../pos/index.php">
				<h1 style="color: #aaaaaa;"><?=$this->preferences->pos_name?></h1>
			</a>
		</td>
		<td style="text-align: right; color: #aaaaaa;">
			<?=$this->say('logged in as', array($this->user['name']))?>
				<a href="../pos/index.php?docu=switch">
					<img src="../images/switch-user-icon.png" style="height: 50px; margin: 4px; vertical-align: middle;">
				</a>
		</td>
	</tr>
</table>

<div id="menu"></div>
<?$this->design();?>
</body>
</html>
