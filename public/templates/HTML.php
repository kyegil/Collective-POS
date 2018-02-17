<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('No access!<br />Check your URI.');

$library = $this->http_host . "/" . $this->ext_library;
$theme = "ext-all-gray";
?>
<!DOCTYPE html>
<html lang="<?php echo $this->say('locale');?>">
<head>
	<meta charset="utf-8" />
	<title><?php echo $this->title;?></title>
	<meta name="robots" content="noindex, follow">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<link rel="stylesheet" type="text/css" href="<?php echo $library;?>/resources/css/ext-all.css" media="screen" />
	<link rel="stylesheet" type="text/css" href="<?php echo $library;?>/resources/css/<?php echo $theme;?>.css" />
	<link rel="stylesheet" type="text/css" href="<?php echo $this->http_host;?>/stylesheet.css" media="screen" />

	<script language="JavaScript" type="text/javascript" src="<?php echo $library;?>/ext-all.js"></script>
	<?php if(
		file_exists("../{$this->ext_library}/locale/ext-lang-{$this->say('locale')}.js")
	):?>

	<script language="JavaScript" type="text/javascript" src="<?php echo $library;?>/locale/ext-lang-<?php echo $this->say('locale');?>.js"></script>
	<?endif?>

	<script language="JavaScript" type="text/javascript">
		<?php $this->script();?>

	</script>
</head>
<body>
<?php $this->design();?>
</body>
</html>