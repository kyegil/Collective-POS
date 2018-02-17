<?php
/**********************************************
Collective POS
by Kay-Egil Hauan
**********************************************/
If(!defined('LEGAL')) die('Ingen permission - No access!<br />Sjekk at adressen du har oppgitt er riktig.');
	$trader = new Trader($_GET['trader']);
?>

	var sale = Ext.create('Ext.menu.Menu', {
		id: 'sale',
		items: [
			{
				text: "<?=$this->say['menu new sale']->format(array())?>",
				handler: function(){
					window.location = "../pos/index.php";
				}
			}, {
				text: "<?=$this->say['menu list all sales']->format(array())?>",
				handler: function(){
					window.location = "../pos/index.php?docu=sales_list";
				}
			}, {
				text: "<?=$this->say['menu till']->format(array())?>",
				handler: function(){
					window.location = "../pos/index.php?docu=till";
				}
			}, {
				text: "<?=$this->say['menu payments']->format(array())?>",
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
				text: "<?=$this->say['menu log out']->format(array())?>",
				handler: function(){
					window.location = "../index.php?mission=exit";
				}
			}
		]
	});


	var trader = Ext.create('Ext.menu.Menu', {
		id: 'trader',
		items: [
			{
				text: "<?=$this->say['menu admin page']->format(array())?>",
				handler: function(){
					window.location = "../admin/index.php?docu=main&trader=<?=$trader->id?>";
				}
			}, {
				text: "<?=$this->say['menu stock']->format(array())?>",
				handler: function(){
					window.location = "../admin/index.php?docu=stock&trader=<?=$trader->id?>";
				}
			}, {
				text: "<?=$this->say['menu inventory counts list']->format(array())?>",
				handler: function(){
					window.location = "../admin/index.php?docu=inventory_counts_list&trader=<?=$trader->id?>";
				}
			}
		]
	});


	var menu = Ext.create('Ext.Toolbar', {
		items: [
		{
			text: "<b><?=strtoupper($this->say['menu sales']->format(array()))?></b>",
			hideOnClick: false,
			menu: sale
		},
		'-',
		{
			text: "<b><?=strtoupper($this->say['menu user']->format(array()))?></b>",
			hideOnClick: false,
			menu: user
		},
		'-',
		{
			text: "<b><?=strtoupper($trader->name)?></b>",
			hideOnClick: false,
			menu: trader
		}
		]
	});
	
	menu.render('menu');
	menu.doLayout();