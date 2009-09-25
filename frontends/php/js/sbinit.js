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

function graphload(id, timelineid, dynamic){
	ZBX_SCROLLBARS[id].disabled = 1;
	ZBX_SCROLLBARS[id].setBarPosition();
	ZBX_SCROLLBARS[id].setGhostByBar();
	ZBX_SCROLLBARS[id].setTabInfo();
	
	var usertime = ZBX_TIMELINES[timelineid].usertime();
	var period = ZBX_TIMELINES[timelineid].period();
	
	var date = datetoarray(usertime - period);
	var url_stime = ''+date[2]+date[1]+date[0]+date[3]+date[4];
	
	if(dynamic){
		var graph = $(id);
		if(!is_null(graph) && (graph.nodeName.toLowerCase() == 'img')){
			url = new Curl(graph.src);
			url.setArgument('stime', url_stime);
			url.setArgument('period', period);
			
			graph.src = url.getUrl();
//alert(url_stime);
		}
	}
	else{
		url = new Curl(location.href);
		url.setArgument('stime', url_stime);
		url.setArgument('period', period);
		url.unsetArgument('output');

//	alert(uri.getUrl());
		location.href = url.getUrl();
	}	
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


function onload_update_scroll(id,w,period,stime,timel,bar_stime){
	var obj = $(id);
	if((typeof(obj) == 'undefined') || is_null(obj)){
		setTimeout('onload_update_scroll("'+id+'",'+w+','+period+','+stime+','+timel+','+bar_stime+');',1000);
		return;
	}

//	eval('var fnc = function(){ onload_update_scroll("'+id+'",'+w+','+period+','+stime+','+timel+','+bar_stime+');}');
	scrollinit(w,period,stime,timel,bar_stime);
	if(!is_null($('scroll')) && showgraphmenu){
		showgraphmenu(id);
	}
//	addListener(window,'resize', fnc );
}