// JavaScript Document
/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**
*/
// Title: graph magic initialization script
// Author: Aly

var _PE_SB = null;
var graphmenu_activated = false;

function hidegraphmenu(pe){
	if(is_null(_PE_SB)) return;
	_PE_SB.stop();
	_PE_SB = null;
	
	if((G_MENU.gmenumsover == 0 ) && (SCROLL_BAR.barmsdown == 0) && (SCROLL_BAR.arrowmsdown == 0) && (SCROLL_BAR.changed == 1)){
		graphsubmit();
	}
}

function showgraphmenu(obj_id){
//	if(graphmenu_activated) return;
//	else graphmenu_activated = true;
	
	var obj = $(obj_id);
	if((typeof(obj) == 'undefined')) return false;
	
	var period_exec = 2;
	if(obj.nodeName.toLowerCase() == 'img'){
		SCROLL_BAR.dom_graphs.push(obj);
		addListener(obj,'load',function(){SCROLL_BAR.disabled=0;});
		period_exec = 0.01;
	}
	
	SCROLL_BAR.scrl_scroll.style.top = '20px'; // 110 = G_MENU height
	SCROLL_BAR.scrl_scroll.style.left = '1px';
		
	SCROLL_BAR.onchange = function(){
		if(is_null(_PE_SB)){
			_PE_SB = new PeriodicalExecuter(hidegraphmenu,period_exec);
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
		if(SCROLL_BAR.disabled) return false;
		
		if(SCROLL_BAR.changed == 1){
			G_MENU.gmenushow(SCROLL_BAR.getsTimeInUnix(),SCROLL_BAR.period);
		}
		else{
			G_MENU.gmenushow();
		}
	}
	
	addListener($('scroll_calendar'),'click',gmshow,false);
	
	if(IE){
		SCROLL_BAR.settabinfo();
		try{$('scroll_calendar').setStyle({'border' : '0px white solid;'});}
		catch(e){}
	}
	
	SCROLL_BAR.scrl_scroll.style.visibility = 'visible';
	SCROLL_BAR.disabled=0;
}


function graph_zoom_init(graph_id,stime,period,width,height, dynamic){
	if((typeof(graph_id) == 'undefined') || empty(graph_id)) return;
	dynamic = dynamic || 0;
		
	A_SBOX[graph_id].sbox = sbox_init(stime,period);
	A_SBOX[graph_id].sbox.sbox_id = graph_id;
	A_SBOX[graph_id].sbox.dynamic = dynamic;
	
	var igraph = $(graph_id);
	var boxongraph = create_box_on_obj(igraph.parentNode);
	
	A_SBOX[graph_id].sbox.dom_obj = boxongraph;

	A_SBOX[graph_id].sbox.moveSBoxByObj(igraph);

	width = width || 900;
	height = height || 200;
	
	if(empty(width)) width = 900;
	if(empty(height)) height = 200;
	
	A_SBOX[graph_id].sbox.obj.width = width-1;
	A_SBOX[graph_id].sbox.obj.height = height;

	boxongraph.style.height = A_SBOX[graph_id].sbox.obj.height+'px';
	boxongraph.style.width = A_SBOX[graph_id].sbox.obj.width+'px';

// Listeners
	addListener(window,'resize',moveSBoxes);
	
	if(IE){
		igraph.attachEvent('onmousedown',A_SBOX[graph_id].sbox.mousedown.bindAsEventListener(A_SBOX[graph_id].sbox));
		igraph.onmousemove = A_SBOX[graph_id].sbox.mousemove.bind(A_SBOX[graph_id].sbox);
	}
	else{
		addListener(boxongraph,'mousedown',A_SBOX[graph_id].sbox.mousedown.bindAsEventListener(A_SBOX[graph_id].sbox),false);
		addListener(boxongraph,'mousemove',A_SBOX[graph_id].sbox.mousemove.bindAsEventListener(A_SBOX[graph_id].sbox),false);
	}
	
	addListener(document,'mouseup',A_SBOX[graph_id].sbox.mouseup.bindAsEventListener(A_SBOX[graph_id].sbox),true);
	
	A_SBOX[graph_id].sbox.sboxload = sboxload;
	
	if(KQ){
		setTimeout('A_SBOX['+graph_id+'].sbox.moveSBoxByObj('+graph_id+');',500);
	}
}

function graphload(dom_objects,unix_stime,period,dynamic){

	if(period < 3600) return false;
	
	var date = datetoarray(unix_stime);
	var url_stime = ''+date[2]+date[1]+date[0]+date[3]+date[4];
	

	if((typeof(SCROLL_BAR) != 'undefined') && SCROLL_BAR.changed){
//alert((SCROLL_BAR.dt.getTime()-(SCROLL_BAR.period * 1000))+' == '+SCROLL_BAR.sdt.getTime());
		if((SCROLL_BAR.dt.getTime()-(SCROLL_BAR.period * 1000)) == SCROLL_BAR.sdt.getTime()){
			url_stime=parseInt(url_stime)+100000000;
		}
	}


	if(empty(dom_objects) || !dynamic){
		dynamic = 0;
		dom_objects = new Array(location);
	}
	
	dynamic = dynamic || 0;
	var src = '';
	var url = '';
	
	if(!is_array(dom_objects)) dom_objects = new Array($(dom_objects));

	if(dynamic){
		for(var i=0; i<dom_objects.length; i++){
			if((typeof(dom_objects[i].nodeName) == 'undefined') || (dom_objects[i].nodeName.toLowerCase() != 'img')){
				continue;
			}
// SBOX			
			if('undefined' != typeof(A_SBOX[dom_objects[i].id])){
				A_SBOX[dom_objects[i].id].sbox.obj.stime = unix_stime;
				A_SBOX[dom_objects[i].id].sbox.obj.period = period;
			}
//------
//SCROLL_BAR
			SCROLL_BAR.initialize(SCROLL_BAR.starttime,period,unix_stime,0)

			SCROLL_BAR.scrl_tabinfoleft.innerHTML = SCROLL_BAR.FormatStampbyDHM(period)+" | "+date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4]+':'+date[5];

			date = datetoarray(unix_stime + (parseInt(period/60)*60));
			SCROLL_BAR.scrl_tabinforight.innerHTML = date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4]+':'+date[5];

//----------
//GMENU
			G_MENU.initialize(unix_stime,period);
			
			G_MENU.syncBSDateByBSTime();
		
			G_MENU.calcPeriodAndTypeByUnix(period);
			G_MENU.setBSDate();
			G_MENU.setPeriod();
			G_MENU.setPeriodType();
//---------
//alert(url_stime);
			url = new Curl(dom_objects[i].src);
			url.setArgument('stime', url_stime);
			url.setArgument('period', period);
			
			dom_objects[i].src = url.getUrl();
		}
	}
	else{
		url = new Curl(dom_objects[0].href);
		url.setArgument('stime', url_stime);
		url.setArgument('period', period);
		url.unsetArgument('output');

		var str_url = url.getUrl();
		dom_objects[0].href = str_url;
	}	
}

function graphsubmit(){
	SCROLL_BAR.disabled = 1;
	graphload(SCROLL_BAR.dom_graphs, SCROLL_BAR.getsTimeInUnix(), SCROLL_BAR.getPeriod(), (SCROLL_BAR.dom_graphs.length > 0));
	SCROLL_BAR.changed = 0;
}

function gmenuload(){
	G_MENU.gmenuhide();
	graphload(SCROLL_BAR.dom_graphs, G_MENU.bstime, G_MENU.period, (SCROLL_BAR.dom_graphs.length > 0));		
}


function sboxload(){
	var igraph = $(this.sbox_id);
	graphload(igraph, parseInt(this.stime), this.period, this.dynamic);	
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