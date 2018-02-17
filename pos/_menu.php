<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('Ingen permission - No access!<br />Sjekk at adressen du har oppgitt er riktig.');
?>

	var sale = Ext.create('Ext.menu.Menu', {
		id: 'sale',
		items: [
			{
				text: "<?=$this->say('menu new sale')?>",
				handler: function(){
					window.location = "../pos/index.php";
				}
			}, {
				text: "<?=$this->say('menu list all sales')?>",
				handler: function(){
					window.location = "../pos/index.php?docu=sales_list";
				}
			}, {
				text: "<?=$this->say('menu till')?>",
				handler: function(){
					window.location = "../pos/index.php?docu=till";
				}
			}, {
				text: "<?=$this->say('menu payments')?>",
				handler: function(){
					window.location = "../pos/index.php?docu=payments_list";
				}
			}
		]
	});


	var user = Ext.create('Ext.menu.Menu', {
		id: 'user',
		items: [
			{
				text: "<?=$this->say('menu log out')?>",
				handler: function(){
					window.location = "../index.php?mission=exit";
				}
			}
		]
	});


<?
	$areas = $this->getUserAreas();
	foreach($areas as $area) {
		if($area->area == 'admin' and $area->trader) {
?>
	var trader<?=$area->trader?> = Ext.create('Ext.menu.Menu', {
		id: 'trader<?=$area->trader?>',
		items: [
			{
				text: "<?=$this->say('menu admin page')?>",
				handler: function(){
					window.location = "../admin/index.php?docu=main&trader=<?=$area->trader?>";
				}
			}, {
				text: "<?=$this->say('menu stock')?>",
				handler: function(){
					window.location = "../admin/index.php?docu=stock&trader=<?=$area->trader?>";
				}
			}
		]
	});


<?
		}
	}
?>

	var menu = Ext.create('Ext.toolbar.Toolbar', {
		renderTo: 'menu',
		items: [
		{
			text: "<b><?=strtoupper($this->say('menu sales'))?></b>",
			hideOnClick: false,
			menu: sale
		},
		'-',
		{
			text: "<b><?=strtoupper($this->say('menu user'))?></b>",
			menu: user
<?
	foreach($areas as $area) {
		if($area->area == 'admin' and $area->trader) {
			$tr = new Trader($area->trader);
?>
		},
		'-',
		{
			text: "<b><?=strtoupper($tr->name)?></b>",
			hideOnClick: false,
			menu: trader<?=$area->trader?>
<?
		}
	}
?>
		},
		'-',
		{
			text: "<b>SYSTEM 2DO-LIST</b>",
			handler: function(){
				window.location = "../pos/index.php?docu=note_form&note=1";
			}
		}
		]
	});