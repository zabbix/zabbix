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
// Title: graph magic initialization
// Author: Aly

//timeControl.addObject(id, time, objData)

var timeControl = {
objectList: {},				// objects needs to be controled

// DEBUG
debug_status: 	0,			// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: 	'',			// debug string
debug_prev:		'',			// don't log repeated fnc

addObject: function(id, time, objData){
	this.debug('addObject', id);

	this.objectList[id] = {
		'processed': 0,
		'id': id,
		'containerid': null,
		'domid': id,
		'time': {},
		'objDims': {},
		'src': location.href,
		'dynamic': 1,
		'loadSBox': 0,
		'loadImage': 0,
		'loadScroll': 1,
		'scrollWidthByImage': 0,
		'mainObject': 0			// object on changing will reflect on all others
	}

	for(key in this.objectList[id]){
		if(isset(key, objData)) this.objectList[id][key] = objData[key];
	}
	
	var now = new Date();
	now = parseInt(now.getTime() / 1000);
	if(!isset('period', time))		time.period = 3600;
	if(!isset('endtime', time))		time.endtime = now;
	if(!isset('starttime', time))	time.starttime = time.endtime - 3*time.period;
	if(!isset('usertime', time))	time.usertime = time.endtime;
	
	this.objectList[id].time = time;
	this.objectList[id].timeline = create_timeline(this.objectList[id].id, 
									  parseInt(time.period), 
									  parseInt(time.starttime), 
									  parseInt(time.usertime), 
									  parseInt(time.endtime));
	
},

processObjects: function(){
	this.debug('processObjects');
	
	for(var key in this.objectList){
		if(empty(this.objectList[key])) continue;

		if(this.objectList[key].processed == 1) continue;
		else this.objectList[key].processed= 1;
		
		var obj = this.objectList[key];

		if(!isset('width', obj.objDims) && isset('shiftXleft', obj.objDims) && isset('shiftXright', obj.objDims)){
			var g_width = get_bodywidth();	
			if(!is_number(g_width)) g_width = 1000;

			obj.objDims.width = g_width - (parseInt(obj.objDims.shiftXleft) + parseInt(obj.objDims.shiftXright) + 27);
		}
		
		if(isset('graphtype', obj.objDims) && (obj.objDims.graphtype < 2)){
			var g_url = new Curl(obj.src);
			g_url.setArgument('width', obj.objDims.width);
			
			var date = datetoarray(obj.time.usertime - obj.time.period);
			var url_stime = ''+date[2]+date[1]+date[0]+date[3]+date[4];
			
			g_url.setArgument('period', obj.time.period);
			g_url.setArgument('stime', url_stime);

			obj.src = g_url.getUrl();
		}

		if(obj.loadImage) this.addImage(obj.id);
		else if(obj.loadScroll) this.addScroll(null,obj.id);

//		addListener(g_img, 'load', function(){addTimeControl(domobjectid, time, loadSBox); })
//		g_img.onload = function(){ addTimeControl(key); };		
	}	
},

addImage: function(objid){
	this.debug('addImage', objid);
	
	var obj = this.objectList[objid];
	
	var g_img = document.createElement('img');
	$(obj.containerid).appendChild(g_img);

	g_img.className = 'borderless';
	g_img.setAttribute('id', obj.domid);
	g_img.setAttribute('src', obj.src);


	if(obj.loadScroll){
		this.scroll_listener = this.addScroll.bindAsEventListener(this, obj.domid);
		addListener(g_img, 'load', this.scroll_listener);
	}

	if(obj.loadSBox){
		this.sbox_listener = this.addSBox.bindAsEventListener(this, obj.domid);
		addListener(g_img, 'load', this.sbox_listener);		

		addListener(g_img, 'load', moveSBoxes);
	}

	
},

addSBox: function(e, objid){
	this.debug('addSBox', objid);

	var obj = this.objectList[objid];

	var g_img = $(obj.domid);
	if(!is_null(g_img)) removeListener(g_img, 'load', this.sbox_listener);
	
	ZBX_SBOX[obj.domid] = new Object;
	ZBX_SBOX[obj.domid].shiftT = 35;
	ZBX_SBOX[obj.domid].shiftL = parseInt(obj.objDims.shiftXleft);
	ZBX_SBOX[obj.domid].shiftR = parseInt(obj.objDims.shiftXright);
	ZBX_SBOX[obj.domid].height = parseInt(obj.objDims.graphHeight);
	ZBX_SBOX[obj.domid].width = parseInt(obj.objDims.width);
	
	var sbox = sbox_init(obj.domid, obj.timeline.timelineid, obj.domid);
	sbox.onchange = this.objectUpdate.bind(this);
},

addScroll: function(e, objid){
	this.debug('addScroll', objid);
	
	var obj = this.objectList[objid];
//SDJ(this.objectList);
	var g_img = $(obj.domid);
	if(!is_null(g_img)) removeListener(g_img, 'load', this.scroll_listener);
	
	var g_width = null;
	if(obj.scrollWidthByImage == 0){
		g_width = get_bodywidth() - 25;	
		if(!is_number(g_width)) g_width = 900;
	}
	
	var scrl = scrollCreate(obj.domid, g_width, obj.timeline.timelineid);
	scrl.onchange = this.objectUpdate.bind(this);
	
	if(obj.dynamic && !is_null($(obj.domid))){
		addListener(obj.domid, 'load', function(){ZBX_SCROLLBARS[scrl.scrollbarid].disabled=0;});
	}
//SDI('scrollCreate');
},

objectUpdate: function(id, timelineid){
	this.debug('objectUpdate', id);
	
	if(!isset(id, this.objectList)) throw('timeControl: Object is not declared "'+graphid+'"');
	
	var obj = this.objectList[id];
	
	if(isset(id, ZBX_SCROLLBARS)){
		ZBX_SCROLLBARS[id].setBarPosition();
		ZBX_SCROLLBARS[id].setGhostByBar();
		ZBX_SCROLLBARS[id].setTabInfo();
		if(!is_null($(obj.domid))) ZBX_SCROLLBARS[id].disabled = 1;
	}
	
	
	var usertime = ZBX_TIMELINES[timelineid].usertime();
	var period = ZBX_TIMELINES[timelineid].period();
	
	var date = datetoarray(usertime - period);
	var url_stime = ''+date[2]+date[1]+date[0]+date[3]+date[4];
	

	if(obj.dynamic){
		if(obj.mainObject){
			for(var key in this.objectList){
				if(empty(this.objectList[key])) continue;
				if(this.objectList[key].dynamic){
					this.objectList[key].timeline.period(period);
					this.objectList[key].timeline.usertime(usertime);
					this.loadDynamic(this.objectList[key].domid, url_stime, period);
				}
			}
		}
		else{
			this.loadDynamic(obj.domid, url_stime, period);
		}
	}
	
	if(!obj.dynamic){
		url = new Curl(location.href);
		url.setArgument('stime', url_stime);
		url.setArgument('period', period);
		url.unsetArgument('output');

//	alert(uri.getUrl());
		location.href = url.getUrl();
	}
},

loadDynamic: function(id, stime, period){
	this.debug('loadDynamic', id);
	
	var obj = this.objectList[id];
	
	var dom_object = $(obj.domid);
	if(!is_null(dom_object) && (dom_object.nodeName.toLowerCase() == 'img')){
		url = new Curl(obj.src);
		url.setArgument('stime', stime);
		url.setArgument('period', period);

		dom_object.src = url.getUrl();
	}
},

debug: function(fnc_name, id){
	if(this.debug_status){
		var str = 'timeLine.'+fnc_name;
		if(typeof(id) != 'undefined') str+= ' :'+id;

		if(this.debug_prev == str) return true;

		this.debug_info += str + '\n';
		if(this.debug_status == 2){
			SDI(str);
		}
		
		this.debug_prev = str;
	}
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