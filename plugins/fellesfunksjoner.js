function initClock ( )
{
  timeDisplay = document.createTextNode ( "" );
  document.getElementById("clock").appendChild ( timeDisplay );
}

function updateClock ( )
{
  var currentTime = new Date ( );

  var currentHours = currentTime.getHours ( );
  var currentMinutes = currentTime.getMinutes ( );
  var currentSeconds = currentTime.getSeconds ( );

  // Pad the minutes and seconds with leading zeros, if required
  currentHours = ( currentHours < 10 ? "0" : "" ) + currentHours;
  currentMinutes = ( currentMinutes < 10 ? "0" : "" ) + currentMinutes;
  currentSeconds = ( currentSeconds < 10 ? "0" : "" ) + currentSeconds;

  // Compose the string for display
  var currentTimeString = currentHours + ":" + currentMinutes + ":" + currentSeconds;

  // Update the time display
  document.getElementById("clock").firstChild.nodeValue = currentTimeString;
}




Ext.util.Format.Currency = function(n) {
	var c=2, d=",", t=" ", s="kr";
	var m = (c = Math.abs(c) + 1 ? c : 2, d = d || ",", t = t || ".",
		/(d+)(?:(.d+)|)/.exec(n + "")), x = m[1].length > 3 ? m[1].length % 3 : 0;
	return ((x ? m[1].substr(0, x) + t : "") + m[1].substr(x).replace(/(d{3})(?=d)/g,
		"$1" + t) + (c ? d + (+m[2] || 0).toFixed(c).substr(2) : ""))+" "+s;
}

	// norsk beløp
Ext.util.Format.noMoney = function(v){
	if (v === '') return null;
	v = (Math.round((v-0)*100))/100;
	v = (v == Math.floor(v)) ? v + ".–" : ((v*10 == Math.floor(v*10)) ? v + "0" : v);
	v = String(v);
	var ps = v.split('.');
	var whole = ps[0];
	var sub = ps[1] ? ','+ ps[1] : ',00';
	var r = /(\d+)(\d{3})/;
	while (r.test(whole)) {
		whole = whole.replace(r, '$1' + ' ' + '$2');
	}
	v = whole + sub;
	if(v.charAt(0) == '-'){
		return 'kr -' + v.substr(1);
	}
	return "kr " + v;
};

    // egendefinert renderfunksjon
Ext.util.Format.hake = function (val){
		if(val == false || val == null){
			return '';
		}
		else{
			return '<img src="/bilder/hake9.png" alt="X"/>';
		}
//		return val;
	}

    // egendefinert renderfunksjon
Ext.util.Format.etasjerenderer = function(val){
		switch(val){
			case '+': return 'loft';
			case '5': return '5. etg.';
			case '4': return '4. etg.';
			case '3': return '3. etg.';
			case '2': return '2. etg.';
			case '1': return '1. etg.';
			case '0': return 'sokkel';
			case '-1': return 'kjeller';
			case '': return '';
		}
	}

    // egendefinert renderfunksjon
Ext.util.Format.kalenderperiode = function(val){
		switch(val){
			case 'P12M': return '1 år';
			case 'P6M': return '6 måneder';
			case 'P4M': return '4 måneder';
			case 'P3M': return '3 måneder';
			case 'P2M': return '2 måneder';
			case 'P1M': return '1 måned';
			case 'P28D': return '4 uker';
			case 'P21D': return '3 uker';
			case 'P14D': return '2 uker';
			case 'P7D': return '1 uke';
			case 'P0M': return 'ingen oppsigelsestid';
			default : return val;
		}
	}

    // egendefinert renderfunksjon
Ext.util.Format.leietakerlinje = function(val){
		var tekst = '';
		for (i=0; i<val.length; i++){
		if(!val[i]['slettet']){
			if (i>0)
				tekst = tekst + ' / ';
			tekst = tekst + val[i]['fornavn'] + ' ' + val[i]['etternavn'];
		}
		}
		return tekst;
	}

    // egendefinert renderfunksjon
Ext.util.Format.leietakerliste = function(val){
		var tekst = '';
		for (i=0;i<val.length;i++){
			if(val[i]['slettet'])
				tekst = tekst + "<span style=\"text-decoration: line-through;\">";
			tekst = tekst + val[i]['fornavn'] + ' ' + val[i]['etternavn'] + '<br />';
			if(val[i]['slettet'])
				tekst = tekst + "</span>";
		}
		return tekst;
	}

