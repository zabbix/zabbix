// JavaScript Document
var _PE_SB = null;

function hidegraphmenu(pe){
	if(is_null(_PE_SB)) return;
	_PE_SB.stop();
	_PE_SB = null;
	
	if((G_MENU.gmenumsover == 0 ) && (SCROLL_BAR.barmsdown == 0) && (SCROLL_BAR.arrowmsdown == 0)){
		graphsubmit();
	}
}

function showgraphmenu(obj_id){
	
	var obj = $(obj_id);
	if(!isset(obj)) return false;
	
	var pos = getPosition(obj);
	pos.top+=obj.offsetHeight+18;
	
	var scrl = $('scroll');
	scrl.style.top = pos.top+"px";
	scrl.style.left = 1+"px";
	
	G_MENU.gm_gmenu.style.top = (pos.top-108)+"px" // 110 = G_MENU height
	G_MENU.gm_gmenu.style.left = 1+"px"
	
	SCROLL_BAR.onchange = function(){
		if(is_null(_PE_SB)){
			_PE_SB = new PeriodicalExecuter(hidegraphmenu,2);
		}
	}
	
	SCROLL_BAR.barmousedown = function(){
		G_MENU.gmenuhide();
		
		if(is_null(_PE_SB)) return;
		_PE_SB.stop();
		_PE_SB = null;
	}
	
	G_MENU.gmenuload = gmenuload;
//	G_MENU.gmenumouseout = function(){G_MENU.gmenuhide(); }
	
	var gmshow = function(){
			if(SCROLL_BAR.changed == 1){
				G_MENU.gmenushow(SCROLL_BAR.period,SCROLL_BAR.getsTimeInUnix());
			}
			else{
				G_MENU.gmenushow();
			}
		}
	if(!IE){
		$('scroll_calendar').addEventListener('click',gmshow,false);
	}
	else{
		$('scroll_calendar').attachEvent('onclick',gmshow);
	}

	var date = datetoarray(G_MENU.bstime);
	
	SCROLL_BAR.tabinfoleft.innerHTML = SCROLL_BAR.FormatStampbyDHM(SCROLL_BAR.period)+" | "+date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4]+':'+date[5];
	
	date = datetoarray(G_MENU.bstime+SCROLL_BAR.period);
	SCROLL_BAR.tabinforight.innerHTML = date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4]+':'+date[5];

	
	scrl.style.visibility = 'visible';
}

function graphsubmit(){
	var scrl = $('scroll');

	scrl.style.display = 'none';
	var uri = new url(location.href);
	
	uri.setArgument('stime', SCROLL_BAR.getsTime());
	uri.setArgument('period', SCROLL_BAR.getPeriod());
	location.href = uri.getUrl();
}

function gmenuload(){
	
	var date = datetoarray(G_MENU.bstime);
	
	var stime = ''+date[2]+date[1]+date[0]+date[3]+date[4];
	var uri = new url(location.href);
	
	uri.setArgument('stime', stime);
	uri.setArgument('period', G_MENU.period);
	
	location.href = uri.getUrl();
}

function datetoarray(unixtime){

	var date = new Date();
	date.setTime(unixtime*1000);
	
	var thedate = new Array();
	thedate[0] = date.getDate();
	thedate[1] = date.getMonth()+1;
	thedate[2] = date.getFullYear();
	thedate[3] = date.getHours();
	thedate[4] = date.getMinutes();
	thedate[5] = date.getSeconds();

	for(i = 0; i < thedate.length; i++){
		if((thedate[i]+'').length < 2) thedate[i] = '0'+thedate[i];
	}
return thedate;
}