/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
// [!CDATA[
/************************************************************************************/
// GRAPHS TIMELINE CONTROLS (GTLC)
// author: Aly
/************************************************************************************/

/************************************************************************************/
// Title: graph magic initialization
// Author: Aly
/************************************************************************************/

//timeControl.addObject(id, time, objData)

var timeControl = {
objectList: {},				// objects needs to be controlled

// DEBUG
debug_status: 	0,			// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: 	'',			// debug string
debug_prev:		'',			// don't log repeated fnc

addObject: function(domid, time, objData){
	this.debug('addObject', domid);

	this.objectList[domid] = {
		'processed': 0,
		'id': domid,
		'containerid': null,
		'domid': domid,
		'time': {},
		'objDims': {},
		'src': location.href,
		'dynamic': 1,
		'periodFixed': 1,
		'loadSBox': 0,
		'loadImage': 0,
		'loadScroll': 1,
		'scrollWidthByImage': 0,
		'mainObject': 0			// object on changing will reflect on all others
	};

	for(key in this.objectList[domid]){
		if(isset(key, objData)) this.objectList[domid][key] = objData[key];
	}

	var nowDate = new CDate();
	now = parseInt(nowDate.getTime() / 1000);

	if(!isset('period', time))		time.period = 3600;
	if(!isset('endtime', time))		time.endtime = now;

	if(!isset('starttime', time) || is_null(time['starttime']))	time.starttime = time.endtime - 3*((time.period<86400)?86400:time.period);
	else time.starttime = (nowDate.setZBXDate(time.starttime) / 1000);


	if(!isset('usertime', time))	time.usertime = time.endtime;
	else time.usertime = (nowDate.setZBXDate(time.usertime) / 1000);

	this.objectList[domid].time = time;
	this.objectList[domid].timeline = create_timeline(this.objectList[domid].domid, 
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

		if((!isset('width', obj.objDims) || (obj.objDims.width < 0)) && isset('shiftXleft', obj.objDims) && isset('shiftXright', obj.objDims)){
			var g_width = get_bodywidth();	
			if(!is_number(g_width)) g_width = 1000;
			
			if(!isset('width', obj.objDims)) obj.objDims.width = 0;
			obj.objDims.width += g_width - (parseInt(obj.objDims.shiftXleft) + parseInt(obj.objDims.shiftXright) + 27);
		}
		
		if(isset('graphtype', obj.objDims) && (obj.objDims.graphtype < 2)){
			var g_url = new Curl(obj.src);
			g_url.setArgument('width', obj.objDims.width);

			var date = new CDate((obj.time.usertime - obj.time.period) * 1000);
			var url_stime = date.getZBXDate();
//			var date = datetoarray(obj.time.usertime - obj.time.period);
//			var url_stime = ''+date[2]+date[1]+date[0]+date[3]+date[4]+date[5];

			g_url.setArgument('period', obj.time.period);
			g_url.setArgument('stime', url_stime);

			obj.src = g_url.getUrl();
		}

		if(obj.loadImage) this.addImage(obj.domid);
		else if(obj.loadScroll) this.addScroll(null,obj.domid);

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
		obj.scroll_listener = this.addScroll.bindAsEventListener(this, obj.domid);
		addListener(g_img, 'load', obj.scroll_listener);
	}

	if(obj.loadSBox){
		obj.sbox_listener = this.addSBox.bindAsEventListener(this, obj.domid);
		addListener(g_img, 'load', obj.sbox_listener);

		addListener(g_img, 'load', moveSBoxes);
		
		if(IE){
// workaround to IE6 & IE7 DOM redraw problem
			addListener(obj.domid, 'load', function(){ setTimeout( function(){$('scrollbar_cntr').show();}, 500);});
		}
	}


},

addSBox: function(e, objid){
	this.debug('addSBox', objid);

	var obj = this.objectList[objid];

	var g_img = $(obj.domid);
	if(!is_null(g_img)) removeListener(g_img, 'load', obj.sbox_listener);
	
	ZBX_SBOX[obj.domid] = new Object;
	ZBX_SBOX[obj.domid].shiftT = parseInt(obj.objDims.shiftYtop);
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
	if(!is_null(g_img)) removeListener(g_img, 'load', obj.scroll_listener);
	
	var g_width = null;
	if(obj.scrollWidthByImage == 0){
		g_width = get_bodywidth() - 30;	
		if(!is_number(g_width)) g_width = 900;
	}

	var scrl = scrollCreate(obj.domid, g_width, obj.timeline.timelineid, this.objectList[objid].periodFixed);
	scrl.onchange = this.objectUpdate.bind(this);

	if(obj.dynamic && !is_null($(g_img))){
		addListener(obj.domid, 'load', function(){ZBX_SCROLLBARS[scrl.scrollbarid].disabled=0;});
	}

//SDI('scrollCreate');
},

objectUpdate: function(domid, timelineid){
	this.debug('objectUpdate', domid);

	if(!isset(domid, this.objectList)) throw('timeControl: Object is not declared "'+domid+'"');
	
	var obj = this.objectList[domid];
		
	var usertime = ZBX_TIMELINES[timelineid].usertime();
	var period = ZBX_TIMELINES[timelineid].period();
	var now = ZBX_TIMELINES[timelineid].now();
	
	if(now) usertime += 86400*365;
	
//	var date = datetoarray(usertime - period);
//	var url_stime = ''+date[2]+date[1]+date[0]+date[3]+date[4];

	var date = new CDate((usertime - period) * 1000);
	var url_stime = date.getZBXDate();

	if(obj.dynamic){
// AJAX update of starttime and period
		this.updateProfile(obj.id, url_stime, period);

		if(obj.mainObject){
			for(var key in this.objectList){
				if(empty(this.objectList[key])) continue;

				if(this.objectList[key].dynamic){
					this.objectList[key].timeline.period(period);
					this.objectList[key].timeline.usertime(usertime);
					this.loadDynamic(this.objectList[key].domid, url_stime, period);
					
					if(isset(key, ZBX_SCROLLBARS)){
						ZBX_SCROLLBARS[key].setBarPosition();
						ZBX_SCROLLBARS[key].setGhostByBar();
						ZBX_SCROLLBARS[key].setTabInfo();
						if(this.objectList[key].loadImage && !is_null($(obj.domid))) ZBX_SCROLLBARS[key].disabled = 1;
					}
				}
			}			
		}
		else{
			this.loadDynamic(obj.domid, url_stime, period);
			
			if(isset(domid, ZBX_SCROLLBARS)){
				ZBX_SCROLLBARS[domid].setBarPosition();
				ZBX_SCROLLBARS[domid].setGhostByBar();
				ZBX_SCROLLBARS[domid].setTabInfo();
				if(!is_null($(obj.domid))) ZBX_SCROLLBARS[domid].disabled = 1;
			}

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

objectReset: function(id){
// unix timestamp
	var usertime = 1600000000;

	var period = 3600;
	var url_stime = 201911051255;
	
	this.updateProfile(id, url_stime, period);
	
	for(var key in this.objectList){
		if(empty(this.objectList[key])) continue;

		if(this.objectList[key].dynamic){
			this.objectList[key].timeline.period(period);
			this.objectList[key].timeline.usertime(usertime);
			this.loadDynamic(this.objectList[key].domid, url_stime, period);
			
			if(isset(key, ZBX_SCROLLBARS)){
				ZBX_SCROLLBARS[key].setBarPosition();
				ZBX_SCROLLBARS[key].setGhostByBar();
				ZBX_SCROLLBARS[key].setTabInfo();
				if(this.objectList[key].loadImage) ZBX_SCROLLBARS[key].disabled = 1;
			}
		}
		else if(!this.objectList[key].dynamic){
			url = new Curl(location.href);
			url.unsetArgument('stime');
			url.unsetArgument('period');
			url.unsetArgument('output');
	
//	alert(uri.getUrl());
			location.href = url.getUrl();
		}
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
		url.setArgument('refresh', Math.floor(Math.random()*1000));

		dom_object.src = url.getUrl();
	}
},

updateProfile: function(id, stime, period){
	if(typeof(Ajax) == 'undefined'){
		throw("Prototype.js lib is required!");
		return false;
	}

	var params = new Array();
	params['favobj'] = 'timeline';
	params['favid'] = id;
	params['graphid'] = id;
	params['period'] = period;
	params['stime'] = stime;

	send_params(params);
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

	var date = new CDate(unixtime*1000);

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


/************************************************************************************/
// Title: TimeLine COntrol CORE
// Author: Aly
/************************************************************************************/

var ZBX_TIMELINES = {};

function create_timeline(tlid, period, starttime, usertime, endtime){
	if(is_null(tlid)){
		var tlid = ZBX_TIMELINES.length;
	}
	
	var now = new CDate();
	now = parseInt(now.getTime() / 1000);

	if('undefined' == typeof(usertime)) usertime = now;
	if('undefined' == typeof(endtime)) endtime = now;
	
	ZBX_TIMELINES[tlid] = new CTimeLine(tlid, period, starttime, usertime, endtime);
	
return ZBX_TIMELINES[tlid];
}

var CTimeLine = Class.create(CDebug,{
timelineid: null,			// own id in array

_starttime: null,				// timeline start time (left, past)
_endtime: null,					// timeline end time (right, now)

_usertime: null,				// selected end time (bar, user selection)
_period: null,					// selected period
_now: false,					// state if time is set to NOW

minperiod: 3600,				// minimal allowed period

initialize: function($super,id, period, starttime, usertime, endtime){
	this.timelineid = id;
	$super('CTimeLine['+id+']');

	if((endtime - starttime) < (3*this.minperiod)) starttime = endtime - (3*this.minperiod);
	
	this.starttime(starttime);
	this.endtime(endtime);

	this.usertime(usertime);
	this.period(period);
},

timeNow: function(){
	var tmp_date = new CDate();
return parseInt(tmp_date.getTime()/1000);
},

setNow: function(){
	var end = this.timeNow();

	this._endtime = end;
	this._usertime = end;

	this.now();
},

now: function(){
	this._now = ((this._usertime+60) > this._endtime);

return this._now;
},


period: function(period){
	this.debug('period');

	if('undefined' == typeof(period)) return this._period;

	if((this._usertime - period) < this._starttime)  period = this._usertime - this._starttime;

//	if((this._usertime - this.period() + period) > this._endtime) period = this._period + this._endtime - this._usertime;
	if(period < this.minperiod) period = this.minperiod;

	this._period = period;

return this._period;
},

usertime: function(usertime){
	this.debug('usertime');

	if('undefined' == typeof(usertime)) return this._usertime;

	if((usertime - this._period) < this._starttime) usertime = this._starttime + this._period;
	if(usertime > this._endtime) usertime = this._endtime;

	this._usertime = usertime;

	this.now();
	
return this._usertime;
},

starttime: function(starttime){
	this.debug('starttime');

	if('undefined'==typeof(starttime)) return this._starttime;
	
	this._starttime = starttime;

return this._starttime;
},

endtime: function(endtime){
	this.debug('endtime');
	
	if('undefined'==typeof(endtime)) return this._endtime;
	
	if(endtime < (this._starttime+this._period*3)) endtime = this._starttime+this._period*3;

	this._endtime = endtime;

return this._endtime;
},

debug: function(fnc_name, id){
	if(this.debug_status){
		var str = 'CTimeLine['+this.timelineid+'].'+fnc_name;
		if(typeof(id) != 'undefined') str+= ' :'+id;

		if(this.debug_prev == str) return true;

		this.debug_info += str + '\n';
		if(this.debug_status == 2){
			SDI(str);
		}

		this.debug_prev = str;
	}
}
});

/************************************************************************************/
// Title: graph scrolling
// Author: Aly
/************************************************************************************/
var ZBX_SCROLLBARS = {};

function scrollCreate(sbid, w, timelineid, fixedperiod){
	if(is_null(sbid)){
		var sbid = ZBX_SCROLLBARS.length;
	}
	
	if(is_null(timelineid)){
		throw "Parameters haven't been sent properly";
		return false;
	}

	if(is_null(w)){
		var dims = getDimensions(sbid);
		w = dims.width - 2;
	}
	
	if(w < 600) w = 600;

	ZBX_SCROLLBARS[sbid] = new CScrollBar(sbid, timelineid, w, fixedperiod);
	
return ZBX_SCROLLBARS[sbid];
}


var CScrollBar = Class.create(CDebug,{
scrollbarid:	null,		// scroll id in array
timelineid:		null,		// timeline id to which it is connected
timeline:		null,		// time Line object
ghostBox:		null,		// ghost box object

clndrLeft:		null,		// calendar object Left
clndrRight:		null,		// calendar object Right

dom:{
	'scrollbar': null,		// dom object
	
	'info': null,				// dom object
	'gmenu': null,				// dom object
	'zoom': null,				// dom object
	'text': null,				// dom object
	'links': null,				// dom object
	'linklist': new Array(),	// dom object
	
	'timeline': null,			// dom object
	'info_left': null,			// dom object
	'info_right': null,			// dom object
	
	'sublevel': null,			// dom object
	'left': null,				// dom object
	'right': null,				// dom object
	'bg': null,					// dom object
	
	'overlevel': null,			// dom object
	
	'bar': null,				// dom object
	'icon': null,				// dom object
	'center': null,				// dom object
	
	'ghost': null,				// dom object
	'left_arr': null,			// dom object
	'right_arr': null,			// dom object
	
	'subline': null,				// dom object
	'nav_links': null,				// dom object
	'nav_linklist': new Array(),	// dom object
	'period_state': null,			// dom object
	'info_period': null				// dom object period info
},

// params
size:{
	'scrollline': null,		// scroll line width 
	'barminwidth': 21		// bar minimal width
},

position:{
	'bar': null,			// bar dimensions
	'ghost': null,			// ghost dimensions
	'leftArr':null,			// left arrow dimensions
	'rightArr':null			// right arrow dimensions
},

px2sec:			null,		// seconds in pixel

// status
scrollmsover: 0,			// if mouse over scrollbar then = 1, out = 0
barmsdown: 0,				// if mousedown on bar = 1, else = 0
arrowmsdown: 0,				// if mousedown on arrow = 1, else = 0
arrow: '',					// pressed arrow (l/r)
changed: 0,					// switches to 1, when scrollbar been moved or period changed
fixedperiod: 1,				// fixes period on bar changes
disabled: 1,				// activates/disables scrollbars

initialize: function($super,sbid, timelineid, width, fixedperiod){ // where to put bar on start(time on graph)
	this.scrollbarid = sbid;
	$super('CScrollBar['+sbid+']');

try{
		this.fixedperiod = fixedperiod == 1 ? 1 : 0;
// Checks
		if(!isset(timelineid,ZBX_TIMELINES)) throw('Failed to initialize ScrollBar with given TimeLine');
		if(empty(this.dom.scrollbar)) this.scrollcreate(width);
//--

// Variable initialization
		this.timeline = ZBX_TIMELINES[timelineid];
		this.ghostBox = new CGhostBox(this.dom.ghost);
		
		this.size.scrollline = width - (17*2) - 2; // border
		this.px2sec = (this.timeline.endtime() - this.timeline.starttime()) / this.size.scrollline;
//--

// Additional dom objects
		this.appendZoomLinks();
		this.appendNavLinks();
		this.appendCalendars();
//--

// AFTER px2sec is set.	important!
		this.position.bar = getDimensions(this.bar);
		
		this.setBarPosition();
		this.setGhostByBar();
		
		this.setTabInfo();
//-------------------------------

// Animate things
		this.makeBarDragable(this.dom.bar);
		this.make_left_arr_dragable(this.dom.left_arr);
		this.make_right_arr_dragable(this.dom.right_arr);
//---------------
		this.disabled = 0;

//try{
	} 
	catch(e){
		throw "ERROR: ScrollBar initialization failed!";
		return false;
	}
},

onBarChange: function(){	
	this.changed = 1;
//	SDI(this.timeline+' : '+this.scrollbarMaxW+' : '+this.barW+' : '+this.barX);
	this.onchange(this.scrollbarid, this.timeline.timelineid, true);
},

barmousedown: function(){
},

scrollmouseout: function(){		//  U may use this func to attach some function on mouseout from scroll method
},

scrollmouseover: function(){		//  U may use this func to attach some function on mouseover from scroll method
},

onchange: function(){			//  executed every time the bar period or bar time is changed(mouse button released)
},

//----------------------------------------------------------------
//-------   MOVE   -----------------------------------------------
//----------------------------------------------------------------
setFullPeriod: function(e){
	this.debug('setFullPeriod');
	if(this.disabled) return false;
//---
	this.timeline.setNow();
	this.timeline.period(this.timeline.endtime() - this.timeline.starttime());

// bar
	this.setBarPosition();
	this.setGhostByBar();

	this.setTabInfo();

	this.onBarChange();

},

setZoom: function(e, zoom){
	this.debug('setZoom', zoom);
	if(this.disabled) return false;
//---
	
	this.timeline.period(zoom);

// bar
	this.setBarPosition();
	this.setGhostByBar();

	this.setTabInfo();
	
	this.onBarChange();
},

navigateLeft: function(e, left){
	this.debug('navigateLeft', this.fixedperiod);
	if(this.disabled) return false;
//---

	deselectAll();
	
	var period = false;
	if(typeof(left) == 'undefined') period = this.timeline.period();
	
//	var dimensions = this.position.bar;
//	this.setBarPosition(dimensions.left-1, dimensions.width, true);

	if(this.fixedperiod == 1){
// fixed
		var usertime = this.timeline.usertime();
		if(period)
			var new_usertime = usertime - period; // by clicking this.dom.left we move bar by period
		else 
			var new_usertime = usertime - left;

// If we slide to another TimeZone
		var TZOffset = this.getTZdiff(usertime, new_usertime);
		new_usertime -= TZOffset;
//------------
		this.timeline.usertime(new_usertime);
	}
	else{
// dynamic
		if(period)
			var new_period = this.timeline.period() + 86400; // by clicking this.dom.left we expand period by 1day
		else 
			var new_period = this.timeline.period() + left;

		this.timeline.period(new_period);
	}
//--------------------

	this.setBarPosition();
	this.setGhostByBar();

	this.setTabInfo();

	this.onBarChange();
},

navigateRight: function(e, right){
	this.debug('navigateRight', this.fixedperiod);
	if(this.disabled) return false;
//---

	deselectAll();
	
	var period = false;
	if(typeof(right) == 'undefined') period = this.timeline.period();
//	var dimensions = this.position.bar;
//	this.setBarPosition(dimensions.left+1, dimensions.width, true);
	
	var usertime = this.timeline.usertime();
	if(this.fixedperiod == 1){
// fixed
		if(period)
			var new_usertime = usertime + period; // by clicking this.dom.left we move bar by period
		else 
			var new_usertime = usertime + right;
	}
	else{
// dynamic
		if(period){
			var new_period = this.timeline.period() + 86400; // by clicking this.dom.left we expand period by 1day
			var new_usertime = usertime + 86400; // by clicking this.dom.left we move bar by period
		}
		else{
			var new_period = this.timeline.period() + right;
			var new_usertime = usertime + right;
		}

		this.timeline.period(new_period);
	}	
//-----------------

// If we slide to another TimeZone
	var TZOffset = this.getTZdiff(usertime, new_usertime);
	new_usertime -= TZOffset;
//------------

	this.timeline.usertime(new_usertime);

	this.setBarPosition();
	this.setGhostByBar();

	this.setTabInfo();

	this.onBarChange();
},

setBarPosition: function(rightSide, periodWidth, setTimeLine){
	this.debug('setBarPosition');

	if('undefined' == typeof(periodWidth))	var periodWidth =  null;
	if('undefined' == typeof(rightSide))	var rightSide = null;
	if('undefined' == typeof(setTimeLine))	var setTimeLine = false;
	
	if(is_null(periodWidth)){
		var periodTime = this.timeline.period();	
		var width = Math.round(periodTime/this.px2sec);		// Ceil
		var periodWidth = width;
	}
	else{
		var width = periodWidth;
	}

	if(is_null(rightSide)){
		var periodTime = this.timeline.period();
		var userTime = this.timeline.usertime();
		var startTime = this.timeline.starttime();
		var endTime = this.timeline.endtime();
		
		var tmp_right = (userTime - startTime)/this.px2sec;

		var rightSide = Math.round(tmp_right);
		var right = rightSide;
	}
	else{
		var right = rightSide;
	}


// Period
	if(width < this.size.barminwidth){
		width = this.size.barminwidth;
	}
	
// Left min
	if((right - width) < 0){
		if(width < this.size.barminwidth) width = this.size.barminwidth;
		right = width;

// actual bar dimensions shouldnt be over side limits
		rightSide = right;
//		periodWidth = width;
	}

// Right max
	if(right > this.size.scrollline){
		if(width < this.size.barminwidth) width = this.size.barminwidth;
		right = this.size.scrollline;
		
// actual bar dimensions shouldnt be over side limits
		rightSide = right;
//		periodWidth = width;
	}

// set actual bar position
	this.dom.bar.style.width = width+'px';
	this.dom.bar.style.left = (right - width)+'px';
//----

// set timeline to given dimensions
	this.position.bar.left = rightSide - periodWidth;
	this.position.bar.right = rightSide;
	this.position.bar.width = periodWidth;
//----

	if(setTimeLine){
		this.updateTimeLine(this.position.bar);
	}
	
	this.position.bar.left = right - width;
	this.position.bar.width = width;
	this.position.bar.right = right;	
},

setGhostByBar: function(){
	this.debug('setGhostByBar');
	
	var dims = getDimensions(this.dom.bar);
	
// ghost
	this.dom.ghost.style.left = dims.left + 'px';
	this.dom.ghost.style.width = dims.width + 'px';
	
// arrows
	this.dom.left_arr.style.left = (dims.left-4) + 'px';
	this.dom.right_arr.style.left = (dims.left+dims.width-3) + 'px';
	
	this.position.ghost = getDimensions(this.dom.ghost);
},

setBarByGhost: function(){
	this.debug('setBarByGhost');
	var dimensions = getDimensions(this.dom.ghost);
	
// bar
// set time
	this.setBarPosition(dimensions.right, dimensions.width, false);
//	this.setGhostByBar();

//	this.setTabInfo();
	
	this.onBarChange();
},

//----------------------------------------------------------------
//-------   CALENDAR   -------------------------------------------
//----------------------------------------------------------------
calendarShowLeft: function(){
	this.debug('calendarShowLeft');
	if(this.disabled) return false;
//---

	var pos = getPosition(this.dom.info_left); 
	pos.top+=34; 
	pos.left-=145; 
	
	if(CR) pos.top-=20;
	this.clndrLeft.clndr.clndrshow(pos.top,pos.left);
},

calendarShowRight: function(){
	this.debug('calendarShowRight');
	if(this.disabled) return false;
//---

	var pos = getPosition(this.dom.info_right); 

	pos.top+=34; 
	pos.left-=77; 
	
	if(CR) pos.top-=20;
	this.clndrRight.clndr.clndrshow(pos.top,pos.left);
},

setCalendarLeft: function(time){
	this.debug('setCalendarLeft', time);
	if(this.disabled) return false;
//---

	time = parseInt(time / 1000);
	
	if(this.fixedperiod == 1){
// fixed
		var new_usertime = time + this.timeline.period();
		this.timeline.usertime(new_usertime);
	}
	else{
// dynamic
		var new_period = Math.abs(this.timeline.usertime() - time);
		this.timeline.period(new_period);
	}	
//-----------------

// bar
	this.setBarPosition();
	this.setGhostByBar();

	this.setTabInfo();
	
	this.onBarChange();
},

setCalendarRight: function(time){
	this.debug('setCalendarRight', time);
	if(this.disabled) return false;
//---

	time = parseInt(time / 1000);
	
	if(this.fixedperiod == 1){
// fixed
		this.timeline.usertime(time);
	}
	else{
// dynamic
		var startusertime = this.timeline.usertime() - this.timeline.period();
		this.timeline.usertime(time);
		
		var new_period = this.timeline.usertime() - startusertime;
		this.timeline.period(new_period);
	}	
//-----------------

// bar
	this.setBarPosition();
	this.setGhostByBar();

	this.setTabInfo();
	
	this.onBarChange();
},
//----------------------------------------------------------------
//-------   DRAG & DROP   ----------------------------------------
//----------------------------------------------------------------

// <BAR>
getBarDimensions: function(x,y,draggable){
	this.debug('getBarDimensions');
	if(this.disabled){
		var dims = getDimensions(draggable.element);
		return[dims.left,y];
	}
//---

	function constrain(n, lower, upper) {
		if (n > upper) return upper;
		else if (n < lower) return lower;
		else return n;
	}
	
	var element_dimensions = draggable.element.getDimensions();
	var parent_dimensions = this.dom.overlevel.getDimensions();

	return[
		constrain(x, 0, parent_dimensions.width - element_dimensions.width),
		constrain(y, 0, parent_dimensions.hight - element_dimensions.hight),
	];
},

barDragStart: function(dragable,e){
	this.debug('barDragStart');
	if(this.disabled) return false;
//---
},

barDragChange: function(dragable,e){
	this.debug('barDragChange');
	if(this.disabled){
		dragable.endDrag(e);
		return false;
	}
//---

	var element = dragable.element;
	this.position.bar = getDimensions(element);

	this.setGhostByBar();

	this.updateTimeLine(this.position.bar);
	this.setTabInfo();
},

barDragEnd: function(dragable,e){
	this.debug('barDragEnd');
	if(this.disabled) return false;
//---

	var element = dragable.element;
	this.position.bar = getDimensions(element);
	
	this.ghostBox.endResize();

	this.setBarByGhost();
},

makeBarDragable: function(element){
	this.debug('makeBarDragable');
//---

	new Draggable(element,{
				ghosting: false,
				snap: this.getBarDimensions.bind(this),
				constraint: 'horizontal',
				onStart: this.barDragStart.bind(this),
				change: this.barDragChange.bind(this),
				onEnd: this.barDragEnd.bind(this)
				});

},
// </BAR>


// <LEFT ARR>
get_dragable_left_arr_dimensions: function(x,y,draggable){
	this.debug('get_dragable_left_arr_dimensions');
	if(this.disabled){
		var dims = getDimensions(draggable.element);
		return[dims.left,y];
	}
//-----

	function constrain(n, lower, upper) {
		if (n > upper) return upper;
		else if (n < lower) return lower;
		else return n;
	}
	
	var element_dimensions = draggable.element.getDimensions();
	var parent_dimensions = this.dom.overlevel.getDimensions();
	
	return[
		constrain(x, -4, parent_dimensions.width - element_dimensions.width + 3),
		constrain(y, 0, parent_dimensions.hight - element_dimensions.hight),
	];
},

make_left_arr_dragable: function(element){
	this.debug('make_left_arr_dragable');
//---

	new Draggable(element,{
				ghosting: false,
				snap: this.get_dragable_left_arr_dimensions.bind(this),
				constraint: 'horizontal',
				onStart: this.leftArrowDragStart.bind(this),
				change: this.leftArrowDragChange.bind(this),
				onEnd: this.leftArrowDragEnd.bind(this)
				});

},

leftArrowDragStart: function(dragable, e){
	this.debug('leftArrowDragStart');
	if(this.disabled) return false;
//---

	var element = dragable.element;
	this.position.leftArr = getDimensions(element);

	this.ghostBox.userstartime = this.timeline.usertime();
	this.ghostBox.usertime = this.timeline.usertime();
	this.ghostBox.startResize(0);
	
},

leftArrowDragChange: function(dragable, e){
	this.debug('leftArrowDragChange');
	if(this.disabled){
		dragable.endDrag(e);
		return false;
	}
//---

	var element = dragable.element;
	var leftArrPos = getDimensions(element);

	this.ghostBox.resizeBox(leftArrPos.right - this.position.leftArr.right);
	this.position.ghost = getDimensions(this.dom.ghost);

	this.updateTimeLine(this.position.ghost);
	this.setTabInfo();	
},

leftArrowDragEnd: function(dragable, e){
	this.debug('leftArrowDragEnd');
	if(this.disabled) return false;
//---

	var element = dragable.element;
	this.position.leftArr = getDimensions(element);
	
	this.ghostBox.endResize();

	this.setBarByGhost();
},
// </LEFT ARR>

// <RIGHT ARR>
get_dragable_right_arr_dimensions: function(x,y,draggable){
	this.debug('get_dragable_right_arr_dimensions');
	if(this.disabled){
		var dims = getDimensions(draggable.element);
		return[dims.left,y];
	}
//-----

	function constrain(n, lower, upper) {
		if (n > upper) return upper;
		else if (n < lower) return lower;
		else return n;
	}
	
	var element_dimensions = draggable.element.getDimensions();
	var parent_dimensions = this.dom.overlevel.getDimensions();
	
	return[
		constrain(x, -3, parent_dimensions.width - element_dimensions.width+4),
		constrain(y, 0, parent_dimensions.hight - element_dimensions.hight),
	];
},

make_right_arr_dragable: function(element){
	this.debug('make_right_arr_dragable');
//---

	new Draggable(element,{
				ghosting: false,
				snap: this.get_dragable_right_arr_dimensions.bind(this),
				constraint: 'horizontal',
				onStart: this.rightArrowDragStart.bind(this),
				change: this.rightArrowDragChange.bind(this),
				onEnd: this.rightArrowDragEnd.bind(this)
				});

},

rightArrowDragStart: function(dragable, e){
	this.debug('rightArrowDragStart');
	if(this.disabled) return false;
//---

	var element = dragable.element;
	this.position.rightArr = getDimensions(element);

	this.ghostBox.userstartime = this.timeline.usertime() - this.timeline.period();
	this.ghostBox.startResize(1);
},

rightArrowDragChange: function(dragable, e){
	this.debug('rightArrowDragChange');
	if(this.disabled){
		dragable.endDrag(e);
		return false;
	}
//---

	var element = dragable.element;
	var rightArrPos = getDimensions(element);
	
	this.ghostBox.resizeBox(rightArrPos.right - this.position.rightArr.right);
	this.position.ghost = getDimensions(this.dom.ghost);

	this.updateTimeLine(this.position.ghost);
	this.setTabInfo();
},

rightArrowDragEnd: function(dragable, e){
	this.debug('rightArrowDragEnd');
	if(this.disabled) return false;
//---

	var element = dragable.element;
	this.position.rightArr = getDimensions(element);
	
	this.ghostBox.endResize();

	this.setBarByGhost();
},
// </RIGHT ARR>

/*---------------------------------------------------------------------
------------------------------ FUNC USES ------------------------------
---------------------------------------------------------------------*/
switchPeriodState: function(){
	this.debug('switchPeriodState');
	if(this.disabled) return false;
//----
	this.fixedperiod = (this.fixedperiod == 1) ? 0 : 1;
	// sending fixed/dynamic setting to server to save in a profile
	var params = {
		favobj:	'timelinefixedperiod',
		favid: this.fixedperiod
	};
	send_params(params);

	if(this.fixedperiod){
		this.dom.period_state.innerHTML = locale['S_FIXED_SMALL'];
	}
	else{
		this.dom.period_state.innerHTML = locale['S_DYNAMIC_SMALL'];
	}
},

getTZOffset: function(time){
	this.debug('getTZOffset');

	var date = new CDate(time*1000);
	var TimezoneOffset = date.getTimezoneOffset();

return TimezoneOffset*60;
},

getTZdiff: function(time1, time2){
	this.debug('getTZdiff');

	var date = new CDate(time1*1000);
	var TimezoneOffset = date.getTimezoneOffset();
	
	date.setTime(time2*1000);
	var offset = (TimezoneOffset - date.getTimezoneOffset()) * 60;

return offset;
},

roundTime: function(usertime){
	this.debug('roundTime');

	var time = parseInt(usertime);
//---------------
	if(time > 86400){
		var dd = new CDate();
		dd.setTime(time*1000);
		dd.setHours(0);
		dd.setMinutes(0);
		dd.setSeconds(0);
		dd.setMilliseconds(0);
//SDI(dd.getFormattedDate()+' : '+dd.getTime()+' : '+dd.tzDiff);
		time = parseInt(dd.getTime() / 1000);
	}

return time;
},

updateTimeLine: function(dim){
	this.debug('updateTimeLine');

// TimeLine Update
	var starttime = this.timeline.starttime();
	var usertime = this.timeline.usertime();
	var period = this.timeline.period();

	var new_usertime = parseInt(dim.right * this.px2sec,10) + starttime;	
	var new_period = parseInt(dim.width * this.px2sec,10);

	if(new_period > 86400){
		new_period = this.roundTime(new_period);
		new_period -= this.getTZOffset(new_period);
	}

	var right = false;
	var left = false;
	if((this.ghostBox.sideToMove == 1)&& (this.ghostBox.flip >= 0)) right = true;
	else if((this.ghostBox.sideToMove == 0) && (this.ghostBox.flip < 0)) right = true;
	
	if((this.ghostBox.sideToMove == 0)&& (this.ghostBox.flip >= 0)) left = true;
	else if((this.ghostBox.sideToMove == 1)&& (this.ghostBox.flip < 0)) left = true;

// Hack for bars most right position
	if((dim.right) == this.size.scrollline){
		if(dim.width != this.position.bar.width){
			this.timeline.period(new_period);
		}
		
		this.timeline.setNow();
	}
	else{
		if(right){
			new_usertime = this.ghostBox.userstartime + new_period;
		}
		else if(left){
			new_usertime = this.ghostBox.userstartime;
		}

// To properly count TimeZone Diffs
		if(period >= 86400) new_usertime = this.roundTime(new_usertime);

		if(dim.width != this.position.bar.width){
			this.timeline.period(new_period);
		}

		this.timeline.usertime(new_usertime);
		
		var	real_period = this.timeline.period();
		var real_usertime = this.timeline.usertime();	
//SDI(left+' : '+new_usertime+' ('+real_usertime+')  p '+new_period+' ('+real_period+')');
	}
//---------------
},

setTabInfo: function(){
	this.debug('setTabInfo');

	var period = this.timeline.period();
	var usertime = this.timeline.usertime();

// USERTIME

	var userstarttime = usertime-period;

	this.dom.info_period.innerHTML = this.formatStampByDHM(period, true, false);
	
	var date = datetoarray(userstarttime);
	this.dom.info_left.innerHTML = date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4];//+':'+date[5];
	
	var date = datetoarray(usertime);
	var right_info = date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4];//+':'+date[5];

	if(this.timeline.now()){
		right_info += '  ('+locale['S_NOW_SMALL']+'!)  ';
	}
	
	this.dom.info_right.innerHTML = right_info;
	
//	seting ZOOM link styles
	this.setZoomLinksStyle();
},

//----------------------------------------------------------------
//-------   MISC   -----------------------------------------------
//----------------------------------------------------------------
getmousexy: function(e){
	if(e.pageX || e.pageY){
		return {x:e.pageX, y:e.pageY};
	}
	return {
		x:e.clientX + document.body.scrollLeft - document.body.clientLeft,
		y:e.clientY + document.body.scrollTop  - document.body.clientTop
	};
},

deselectall: function(){
	if(IE){
		document.selection.empty();
	}
	else if(!KQ){
		var sel = window.getSelection();
		sel.removeAllRanges();
	}	
},

formatStampByDHM: function(timestamp, tsDouble, extend){
	this.debug('formatStampByDHM');
	
	timestamp = timestamp || 0;
	var years = 0;
	var months = 0;
	var weeks = 0;
	if(extend){
		years = parseInt(timestamp/(365*86400));
		months = parseInt((timestamp - years*365*86400)/(30*86400));
		weeks = parseInt((timestamp - years*365*86400 - months*30*86400)/(7*86400));
	}

	var days = 	parseInt((timestamp - years*365*86400 - months*30*86400 - weeks*7*86400)/86400);
	var hours =  parseInt((timestamp - years*365*86400 - months*30*86400 - weeks*7*86400 - days*86400)/3600);
	var minutes = parseInt((timestamp - years*365*86400 - months*30*86400 - weeks*7*86400 - days*86400 - hours*3600)/60);
	
	if(tsDouble){
		if(months.toString().length == 1) months = '0'+months;
		if(weeks.toString().length == 1) weeks = '0'+weeks;
		if(days.toString().length == 1) days = '0'+days;
		if(hours.toString().length == 1) hours = '0'+hours;
		if(minutes.toString().length == 1) minutes = '0'+minutes;
	}

	var str = "";
	str+=(years == 0)?(''):(years+locale['S_YEAR_SHORT']+' ');
	str+=(months == 0)?(''):(months+locale['S_MONTH_SHORT']+' ');
	str+=(weeks == 0)?(''):(weeks+locale['S_WEEK_SHORT']+' ');
	
	if(extend && tsDouble) str+=days+locale['S_DAY_SHORT']+' ';
	else str+=(days == 0)?(''):(days+locale['S_DAY_SHORT']+' ');
	
	str+=hours+locale['S_HOUR_SHORT']+' '+minutes+locale['S_MINUTE_SHORT']+' ';
	
return str;
},

/*-------------------------------------------------------------------------------------------------*\
*										SCROLL CREATION												*
\*-------------------------------------------------------------------------------------------------*/
appendCalendars: function(){
	this.debug('appendCalendars');
//---
	
	this.clndrLeft = create_calendar((this.timeline.usertime() - this.timeline.period()), this.dom.info_left, null, null, 'scrollbar_cntr');
	this.clndrRight = create_calendar(this.timeline.usertime(), this.dom.info_right, null, null, 'scrollbar_cntr');

	this.clndrLeft.clndr.onselect = this.setCalendarLeft.bind(this);
	addListener(this.dom.info_left, 'click', this.calendarShowLeft.bindAsEventListener(this));
	
	this.clndrRight.clndr.onselect = this.setCalendarRight.bind(this);
	addListener(this.dom.info_right, 'click', this.calendarShowRight.bindAsEventListener(this));
},

appendZoomLinks: function(){
	this.debug('appendZoomLinks');
//---

	var timeline = this.timeline.endtime() - this.timeline.starttime();
	
	var caption = '';
	var zooms = [3600, (2*3600), (3*3600), (6*3600), (12*3600), 86400, (7*86400), (14*86400), (30*86400), (90*86400), (180*86400), (365*86400)];

	var links = 0;
	for(var key in zooms){
		if(empty(zooms[key]) || !is_number(zooms[key])) continue;
		if((timeline / zooms[key]) < 1) break;

		caption = this.formatStampByDHM(zooms[key], false, true);
//		caption = caption.split(' 0',2)[0].split(' ').join('');
		caption = caption.split(' ',2)[0];

		this.dom.linklist[links] = document.createElement('span');
		this.dom.links.appendChild(this.dom.linklist[links]);
		this.dom.linklist[links].className = 'link';
		this.dom.linklist[links].setAttribute('zoom', zooms[key]);
		this.dom.linklist[links].appendChild(document.createTextNode(caption));
		addListener(this.dom.linklist[links],'click',this.setZoom.bindAsEventListener(this, zooms[key]),true);
		
		links++;
	}

	this.dom.linklist[links] = document.createElement('span');
	this.dom.links.appendChild(this.dom.linklist[links]);
	this.dom.linklist[links].className = 'link';
	this.dom.linklist[links].setAttribute('zoom', zooms[key]);
	this.dom.linklist[links].appendChild(document.createTextNode(locale['S_ALL_S']));

	addListener(this.dom.linklist[links],'click',this.setFullPeriod.bindAsEventListener(this),true);
},

appendNavLinks: function(){
	this.debug('appendNavLinks');
//---

	var timeline = this.timeline.endtime() - this.timeline.starttime();
	
	var caption = '';
	var moves = [3600, (12*3600), 86400, (7*86400), (30*86400), (180*86400), (365*86400)];

	var links = 0;
	
	var left = new Array();
	var right = new Array();
	
	var tmp_laquo = document.createElement('span');
	this.dom.nav_links.appendChild(tmp_laquo);
	tmp_laquo.className = 'text';
	tmp_laquo.innerHTML = ' &laquo;&laquo; ';
	
	for(var i=moves.length; i>=0; i--){
		if(!isset(i, moves) || !is_number(moves[i])) continue;
		if((timeline / moves[i]) < 1) continue;

		caption = this.formatStampByDHM(moves[i], false, true);
		caption = caption.split(' ',2)[0];

		this.dom.nav_linklist[links] = document.createElement('span');
		this.dom.nav_links.appendChild(this.dom.nav_linklist[links]);
		
		this.dom.nav_linklist[links].className = 'link';
		this.dom.nav_linklist[links].setAttribute('nav', moves[i]);
		this.dom.nav_linklist[links].appendChild(document.createTextNode(caption));
		addListener(this.dom.nav_linklist[links], 'click', this.navigateLeft.bindAsEventListener(this, moves[i]));

		links++;
	}
	
	var tmp_laquo = document.createElement('span');
	this.dom.nav_links.appendChild(tmp_laquo);
	tmp_laquo.className = 'text';
	tmp_laquo.innerHTML = ' | ';

	
	for(var i=0; i<moves.length; i++){
		if(!isset(i, moves) || !is_number(moves[i])) continue;
		if((timeline / moves[i]) < 1) continue;

		caption = this.formatStampByDHM(moves[i], false, true);
		caption = caption.split(' ',2)[0];
		

		this.dom.nav_linklist[links] = document.createElement('span');
		this.dom.nav_links.appendChild(this.dom.nav_linklist[links]);
		
		this.dom.nav_linklist[links].className = 'link';
		this.dom.nav_linklist[links].setAttribute('nav', moves[i]);
		this.dom.nav_linklist[links].appendChild(document.createTextNode(caption));
		addListener(this.dom.nav_linklist[links], 'click', this.navigateRight.bindAsEventListener(this, moves[i]));

		links++;
	}
	
	var tmp_raquo = document.createElement('span');
	this.dom.nav_links.appendChild(tmp_raquo);
	tmp_raquo.className = 'text';
	tmp_raquo.innerHTML = ' &raquo;&raquo; ';

},

setZoomLinksStyle: function(){
	var period = this.timeline.period();
	for(var i=0; i < this.dom.linklist.length; i++){
		if(!isset(i,this.dom.linklist) || empty(this.dom.linklist[i])) continue;
		
		var linkzoom = this.dom.linklist[i].getAttribute('zoom');

		if(linkzoom == period){
			this.dom.linklist[i].style.textDecoration = 'none';
			this.dom.linklist[i].style.fontWeight = 'bold';
			this.dom.linklist[i].style.fontSize = '11px';
//			this.dom.linklist[i].style.color = '#3377BB';
		}
		else{
			this.dom.linklist[i].style.textDecoration = 'underline';
			this.dom.linklist[i].style.fontWeight = 'normal';
			this.dom.linklist[i].style.fontSize = '10px';
//			this.dom.linklist[i].style.color = '';
		}
		
	}

	i = this.dom.linklist.length - 1;
	if(period == (this.timeline.endtime() - this.timeline.starttime())){

		this.dom.linklist[i].style.textDecoration = 'none';
		this.dom.linklist[i].style.fontWeight = 'bold';
		this.dom.linklist[i].style.fontSize = '11px';
	}
},

scrollcreate: function(w){
	var scr_cntr = $('scrollbar_cntr');
	if(is_null(scr_cntr)) throw('ERROR: SCROLL [scrollcreate]: scroll container node is not found!');
	
	scr_cntr.style.paddingRight = '2px';
	scr_cntr.style.paddingLeft = '2px';
	// scr_cntr.style.backgroundColor = '#E5E5E5';
	scr_cntr.style.margin = '5px 0 0 0 ';

	this.dom.scrollbar = document.createElement('div');
	scr_cntr.appendChild(this.dom.scrollbar);
	this.dom.scrollbar.className = 'scrollbar';

	Element.extend(this.dom.scrollbar);
	this.dom.scrollbar.setStyle({width: w+'px'});//,visibility: 'hidden'});

// <INFO>
	this.dom.info = document.createElement('div');
	this.dom.scrollbar.appendChild(this.dom.info);
	this.dom.info.className = 'info';
	$(this.dom.info).setStyle({width: w+'px'});
	
//	this.dom.gmenu = document.createElement('div');
//	this.dom.info.appendChild(this.dom.gmenu);
//	this.dom.gmenu.className = 'gmenu';

	this.dom.zoom = document.createElement('div');
	this.dom.info.appendChild(this.dom.zoom);
	this.dom.zoom.className = 'zoom';
	
	this.dom.text = document.createElement('span');
	this.dom.zoom.appendChild(this.dom.text);
	this.dom.text.className = 'text';
	
	this.dom.text.appendChild(document.createTextNode(locale['S_ZOOM']+':'));
	
	this.dom.links = document.createElement('span');
	this.dom.zoom.appendChild(this.dom.links);
	this.dom.links.className = 'links';
//	this.appendZoomLinks();

	this.dom.timeline = document.createElement('div');
	this.dom.info.appendChild(this.dom.timeline);
	this.dom.timeline.className = 'timeline';
	
// Left
	this.dom.info_left = document.createElement('span');
	this.dom.timeline.appendChild(this.dom.info_left);
	this.dom.info_left.className = 'info_left link';
	this.dom.info_left.appendChild(document.createTextNode('02.07.2009 12:15:12'));

	var sep = document.createElement('span');
	this.dom.timeline.appendChild(sep);
	sep.className = 'info_sep1';
	sep.appendChild(document.createTextNode(' - '));

// Right
	this.dom.info_right = document.createElement('span');
	this.dom.timeline.appendChild(this.dom.info_right);
	this.dom.info_right.className = 'info_right link';
	this.dom.info_right.appendChild(document.createTextNode('02.07.2009 12:15:12'));
	
// </INFO>

// <SUBLEVEL>
	this.dom.sublevel = document.createElement('div');
	this.dom.scrollbar.appendChild(this.dom.sublevel);
	this.dom.sublevel.className = 'sublevel';
	$(this.dom.sublevel).setStyle({width: w+'px'});

	this.dom.left = document.createElement('div');
	this.dom.sublevel.appendChild(this.dom.left);
	this.dom.left.className = 'left';
	addListener(this.dom.left,'click',this.navigateLeft.bindAsEventListener(this),true);

	this.dom.right = document.createElement('div');
	this.dom.sublevel.appendChild(this.dom.right);
	this.dom.right.className = 'right';
	addListener(this.dom.right,'click',this.navigateRight.bindAsEventListener(this),true);

	this.dom.bg = document.createElement('div');
	this.dom.sublevel.appendChild(this.dom.bg);
	this.dom.bg.className = 'bg';
// </SUBLEVEL>

// <OVERLEVEL>
	this.dom.overlevel = document.createElement('div');
	this.dom.scrollbar.appendChild(this.dom.overlevel);
	this.dom.overlevel.className = 'overlevel';
	$(this.dom.overlevel).setStyle({width: (w-17*2)+'px'});
	
	this.dom.bar = document.createElement('div');
	this.dom.overlevel.appendChild(this.dom.bar);
	this.dom.bar.className = 'bar';

	this.dom.icon = document.createElement('div');
	this.dom.bar.appendChild(this.dom.icon);
	this.dom.icon.className = 'icon';
	
	this.dom.center = document.createElement('div');
	this.dom.icon.appendChild(this.dom.center);
	this.dom.center.className = 'center';

	this.dom.ghost = document.createElement('div');
	this.dom.overlevel.appendChild(this.dom.ghost);
	this.dom.ghost.className = 'ghost';
	
	this.dom.left_arr = document.createElement('div');
	this.dom.overlevel.appendChild(this.dom.left_arr);
	this.dom.left_arr.className = 'left_arr';
	
	this.dom.right_arr = document.createElement('div');
	this.dom.overlevel.appendChild(this.dom.right_arr);
	this.dom.right_arr.className = 'right_arr';
// </OVERLEVEL>

// <SUBLINE>
	this.dom.subline = document.createElement('div');
	this.dom.scrollbar.appendChild(this.dom.subline);
	this.dom.subline.className = 'subline';
	$(this.dom.subline).setStyle({width: w+'px'});

// Additional positioning links
	this.dom.nav_links = document.createElement('div');
	this.dom.subline.appendChild(this.dom.nav_links);
	this.dom.nav_links.className = 'nav_links';
//	this.dom.nav_links.appendChild(document.createTextNode('1y  1m  1w  1d  12h  1h << >> 1h  12h  1d  1w  1m  1y'));

// Period state
	this.dom.period = document.createElement('div');
	this.dom.subline.appendChild(this.dom.period);
	this.dom.period.className = 'period';

// State (
	var tmp  = document.createElement('span');
	this.dom.period.appendChild(tmp);
	tmp.className = 'period_state_begin';
	tmp.appendChild(document.createTextNode('('));

// State
	this.dom.period_state = document.createElement('span');
	this.dom.period.appendChild(this.dom.period_state);
	this.dom.period_state.className = 'period_state link';
	this.dom.period_state.appendChild(document.createTextNode(this.fixedperiod == 1 ? locale['S_FIXED_SMALL'] : locale['S_DYNAMIC_SMALL']));
	addListener(this.dom.period_state, 'click', this.switchPeriodState.bindAsEventListener(this));

// State )
	var tmp  = document.createElement('span');
	this.dom.period.appendChild(tmp);
	tmp.className = 'period_state_end';
	tmp.appendChild(document.createTextNode(')'));

// period Info
	this.dom.info_period = document.createElement('div');
	this.dom.subline.appendChild(this.dom.info_period);
	this.dom.info_period.className = 'info_period';
	this.dom.info_period.appendChild(document.createTextNode('0h 0m'));


// </SUBLINE>
	
/*
<div class="scrollbar">
	<div class="info">
		<div class="zoom">
			<span class="text">Zoom:</span>
			<span class="links">
				<span class="link">1h</span>
				<span class="link">2h</span>
				<span class="link">3h</span>
				<span class="link">6h</span>
				<span class="link">12h</span>
				<span class="link">1d</span>
				<span class="link">5d</span>
				<span class="link">1w</span>
				<span class="link">1m</span>
				<span class="link">3m</span>
				<span class="link">6m</span>
				<span class="link">YTD</span>
				<span class="link">1y</span>
			</span>
		</div>
		<div class="gmenu"></div>
		<div class="timeline">
			<span class="info_right">30.06.2009 16:35:08</span>
			<span class="info_sep1"> - </span>
			<span class="info_left">30.06.2009 16:35:00</span>
		</div>
	</div>
	<div class="sublevel">
		<div class="left"></div>
		<div class="right"></div>
		<div class="bg">
		</div>
	</div>
	<div class="overlevel">
		<div class="bar">
			<div class="icon">
				<div class="center"></div>
			</div>
		</div>
		<div class="ghost">
			<div class="left_arr"></div>
			<div class="right_arr"></div>
		</div>
	</div>
	<div class="subline">
		<div class="nav_links"></div>
		<div class="info_period">0h 0m</div>
		<div class="period">
			(
			<span class="period_state">fixed</span>
			)
		</div>
	</div>
</div>

*/
}
});

var CGhostBox = Class.create(CDebug,{
box: 			null,	// resized dom object

start:{					// resize start position
	width: 		null,
	leftSide:	null,
	rightSide:	null
},

current:{				// resize in progress position
	width: 		null,
	leftSide:	null,
	rightSide:	null
},

sideToMove:		null,	// 0 - left side, 1 - right side
flip:			null,	// if flip < 0, ghost is fliped

initialize: function($super,id){
	$super('CGhostBox['+id+']');
	
	this.box = $(id);
	if(is_null(this.box)) throw('Cannot initialize GhostBox with given object id');
},

startResize: function(side){
	this.debug('startResize');
	
	this.sideToMove = side;
	this.flip = 0;
	
	var dimensions = getDimensions(this.box);
	
	this.start.width = dimensions.width;	// - borders (2 x 1px)
	this.start.leftSide = dimensions.left;
	this.start.rightSide = dimensions.right;
	
	this.box.style.zIndex = 20;
},

endResize: function(){
	this.sideToMove = -1;
	this.flip = 0;
	this.box.style.zIndex = 0;
},

calcResizeByPX: function(px){	
	this.debug('calcResizeByPX');
// -px: moveLeft, +px: moveRight

	px = parseInt(px,10);
	this.flip = 0;
	if(this.sideToMove == 0){
		this.flip =  this.start.rightSide - (this.start.leftSide + px);
		if(this.flip < 0){
			this.current.leftSide = this.start.rightSide;
			this.current.rightSide = this.start.rightSide + Math.abs(this.flip);
		}
		else{
			this.current.leftSide = (this.start.leftSide + px);
			this.current.rightSide = this.start.rightSide;
		}
	}
	else if(this.sideToMove == 1){
		this.flip = (this.start.rightSide + px) - this.start.leftSide;
		if(this.flip < 0){
			this.current.leftSide = this.start.leftSide - Math.abs(this.flip);
			this.current.rightSide = this.start.leftSide;
		}
		else{
			this.current.leftSide = this.start.leftSide;
			this.current.rightSide = this.start.rightSide + px;
		}
	}
	
	this.current.width = this.current.rightSide - this.current.leftSide;
//SDI(this.current.width+' = '+this.current.rightSide+' - '+this.current.leftSide);
},

resizeBox: function(px){
	this.debug('resizeBox');
	
	if('undefined' != typeof(px)){
		this.calcResizeByPX(px);
	}

	this.box.style.left = this.current.leftSide+'px';
	this.box.style.width = this.current.width+'px';
}
});


/************************************************************************************/
// Title: selection box uppon graphs
// Author: Aly
/************************************************************************************/
var ZBX_SBOX = {};		//selection box obj reference

function sbox_init(sbid, timeline, domobjectid){
	if(!isset(domobjectid, ZBX_SBOX)) throw('TimeControl: SBOX is not defined for object "'+domobjectid+'"');
	if(is_null(timeline)) throw("Parametrs haven't been sent properly");
	
	if(is_null(sbid))	var sbid = ZBX_SBOX.length;
	
	var dims = getDimensions(domobjectid);
	var width = dims.width - (ZBX_SBOX[domobjectid].shiftL + ZBX_SBOX[domobjectid].shiftR);
//graph borders
	width -= 2;

	var obj = $(domobjectid);
	var box = new sbox(sbid, timeline, obj, width, ZBX_SBOX[sbid].height);

// Listeners
	addListener(window,'resize',moveSBoxes);
		
	if(KQ) setTimeout('ZBX_SBOX['+sbid+'].sbox.moveSBoxByObj('+sbid+');',500);
	
	ZBX_SBOX[sbid].sbox = box;
return box;
}


var sbox = Class.create(CDebug, {
sbox_id:			'',				// id to create references in array to self

mouse_event:		{},				// json object wheres defined needed event params
start_event:		{},				// copy of mouse_event when box created

stime:				0,				//	new start time
period:				0,				//	new period

cobj:				{},				// objects params
dom_obj:			null,			// selection div html obj
box:				{},				// object params
dom_box:			null,			// selection box html obj
dom_period_span:	null,			// period container html obj

shifts:				{},				// shifts regarding to main objet

px2time:			null,			// seconds in 1px

dynamic:			'',				// how page updates, all page/graph only update

initialize: function($super, sbid, timelineid, obj, width, height){
	this.sbox_id = sbid;
	$super('CBOX['+sbid+']');

// For some reason this parameter is need to be initialized due to cross object reference somewhere..
	this.cobj = {};

// Checks
	if(is_null(obj)) throw('Failed to initialize Selection Box with given Object');
	if(!isset(timelineid,ZBX_TIMELINES)) throw('Failed to initialize Selection Box with given TimeLine');

	if(empty(this.dom_obj)){
		this.grphobj = obj;
		this.dom_obj = create_box_on_obj(obj, height);
		this.moveSBoxByObj();
	}
//--

// Variable initialization
	this.timeline = ZBX_TIMELINES[timelineid];		
	
	this.cobj.width = width;
	this.cobj.height = height;
	
	this.box.width = 0;
//--

// Listeners
	if(IE){
		addListener(obj, 'mousedown', this.mousedown.bindAsEventListener(this));
		obj.onmousemove = this.mousemove.bindAsEventListener(this);
		addListener(obj, 'click', this.ieMouseClick.bindAsEventListener(this));
	}
	else{
		addListener(this.dom_obj, 'mousedown', this.mousedown.bindAsEventListener(this),false);
		addListener(document, 'mousemove', this.mousemove.bindAsEventListener(this),true);
		addListener(this.dom_obj, 'click', function(event){ cancelEvent(event);});
	}
	
	addListener(document, 'mouseup', this.mouseup.bindAsEventListener(this),true);
	
//---------

	ZBX_SBOX[this.sbox_id].mousedown = false;	
},

onselect: function(){
	this.debug('onselect');

	this.px2time = this.timeline.period() / this.cobj.width;
	var userstarttime = (this.timeline.usertime() - this.timeline.period());

// this.shifts.left - mainbox absolute shift left
// this.box.left - selection box shift left relative to mainbox
// this.cobj.left - most absolute left possible possition for selection box
	userstarttime += Math.round((this.box.left-(this.cobj.left - this.shifts.left)) * this.px2time);

	var new_period = this.calcperiod();


	if(this.start_event.left < this.mouse_event.left) userstarttime+= new_period;

	this.timeline.period(new_period);
	this.timeline.usertime(userstarttime);

	this.onchange(this.sbox_id, this.timeline.timelineid, true);
},

onchange: function(){			// bind any func to this
	
},

mousedown: function(e){
	this.debug('mousedown',this.sbox_id);

	e = e || window.event;
	if(e.which && (e.which != 1)) return false;
	else if (e.button && (e.button != 1)) return false;

	Event.stop(e);

	if(ZBX_SBOX[this.sbox_id].mousedown == false){
		this.optimizeEvent(e);

		if(!IE) deselectAll();

		if(IE){
			var posxy = getPosition(this.dom_obj);
			if((this.mouse_event.left < posxy.left) || (this.mouse_event.left > (posxy.left+this.dom_obj.offsetWidth))) return false;
			if((this.mouse_event.top < posxy.top) || (this.mouse_event.top > (this.dom_obj.offsetHeight + posxy.top))) return true;
		}

		this.create_box();

		ZBX_SBOX[this.sbox_id].mousedown = true;
	}
	
return false;
},

mousemove: function(e){
	this.debug('mousemove',this.sbox_id);

	e = e || window.event;
	
	if(IE) cancelEvent(e);

	if(ZBX_SBOX[this.sbox_id].mousedown == true){
		this.optimizeEvent(e);
		this.resizebox();
	}
},

mouseup: function(e){
	this.debug('mouseup',this.sbox_id);

	e = e || window.event;

	if(ZBX_SBOX[this.sbox_id].mousedown == true){
		this.onselect();

		cancelEvent(e);
		this.clear_params();
		ZBX_SBOX[this.sbox_id].mousedown = false;
	}
	return false;
},

create_box: function(){
	this.debug('create_box');
	
	if(is_null(this.dom_box)){
		this.dom_box = document.createElement('div');
		this.dom_obj.appendChild(this.dom_box);
		
		this.dom_period_span = document.createElement('span');
		this.dom_box.appendChild(this.dom_period_span);

		this.dom_period_span.setAttribute('id','period_span');
		this.dom_period_span.innerHTML = this.period;
		
		var dims = getDimensions(this.dom_obj);
		this.shifts.left = dims.left;
		this.shifts.top = dims.top;
//		this.obj = dims;

		var top = this.mouse_event.top;
		var left = this.mouse_event.left - dims.left;

		top = 0; // we use only x axis

		this.dom_box.setAttribute('id','selection_box');
		this.dom_box.style.top = top+'px';
		this.dom_box.style.left = left+'px';
		this.dom_box.style.height = this.cobj.height+'px';
		this.dom_box.style.width = '1px';

		this.start_event.top = this.mouse_event.top;
		this.start_event.left = this.mouse_event.left;

		this.box.top = top;
		this.box.left = left;
		this.box.height = this.cobj.height;

		if(IE) this.dom_box.onmousemove = this.mousemove.bindAsEventListener(this);
	}

	ZBX_SBOX[this.sbox_id].mousedown = false;	
},

resizebox: function(){
	this.debug('resizebox',this.sbox_id);
	
	if(ZBX_SBOX[this.sbox_id].mousedown == true){
// fix wrong selection box

//SDI(this.mouse_event.left+' > '+(this.cobj.width + this.cobj.left));
		if(this.mouse_event.left > (this.cobj.width + this.cobj.left)){
			this.moveright(this.cobj.width - (this.start_event.left - this.cobj.left));
		}
		else if(this.mouse_event.left < this.cobj.left){
			this.moveleft(this.cobj.left, this.start_event.left - this.cobj.left);
		}
		else{
			var width = this.validateW(this.mouse_event.left - this.start_event.left);
			var left = this.mouse_event.left - this.shifts.left;

			if(width>0) this.moveright(width);
			else this.moveleft(left, width);
		}

		this.period = this.calcperiod();
		
		if(!is_null(this.dom_box)) 
			this.dom_period_span.innerHTML = this.FormatStampbyDHM(this.period)+((this.period<3600)?' [min 1h]':'');
	}
},

moveleft: function(left, width){
	this.debug('moveleft');
	
//	this.box.left = left;
	if(!is_null(this.dom_box)) this.dom_box.style.left = left+'px';

	this.box.width = Math.abs(width);
	if(!is_null(this.dom_box)) this.dom_box.style.width = this.box.width+'px';
},

moveright: function(width){
	this.debug('moveright');
	
//	this.box.left = (this.start_event.left - this.cobj.left);
	if(!is_null(this.dom_box)) this.dom_box.style.left = this.box.left+'px';
	
	if(!is_null(this.dom_box)) this.dom_box.style.width = width+'px';
	this.box.width = width;
},

calcperiod: function(){
	this.debug('calcperiod');

	if((this.box.width+1) >= this.cobj.width){
		var new_period = this.timeline.period();
	}
	else{
		this.px2time = this.timeline.period()/this.cobj.width;
		var new_period = Math.round(this.box.width * this.px2time);
//SDI('CALCP: '+this.box.width+' * '+this.px2time);
	}

return	new_period;
},

FormatStampbyDHM: function(timestamp){
	this.debug('FormatStampbyDHM');
	
	timestamp = timestamp || 0;
	var days = 	parseInt(timestamp/86400);
	var hours =  parseInt((timestamp - days*86400)/3600);
	var minutes = parseInt((timestamp -days*86400 - hours*3600)/60);

	var str = (days==0)?(''):(days+'d ');
	str+=hours+'h '+minutes+'m ';
	
return str;
},

validateW: function(w){
	this.debug('validateW');
//SDI(this.start_event.left+' - '+this.cobj.left+' - '+w+' > '+this.cobj.width)
	if(((this.start_event.left-this.cobj.left)+w)>this.cobj.width)
		w = 0;//this.cobj.width - (this.start_event.left - this.cobj.left) ;

	if(this.mouse_event.left < this.cobj.left)
		w = 0;//(this.start_event.left - this.cobj.left);
	
return w;
},

validateH: function(h){
	this.debug('validateH');
	
	if(h<=0) h=1;
	if(((this.start_event.top-this.cobj.top)+h)>this.cobj.height)
		h = this.cobj.height - this.start_event.top;
return h;
},

moveSBoxByObj: function(){
	this.debug('moveSBoxByObj',this.sbox_id);
	
	var posxy = getPosition(this.grphobj);
	var dims = getDimensions(this.grphobj);

	this.dom_obj.style.top = (posxy.top+ZBX_SBOX[this.sbox_id].shiftT)+'px';
//this.dom_obj.style.left = (posxy.left+ZBX_SBOX[this.sbox_id].shiftL)+'px';
	this.dom_obj.style.left = posxy.left+'px';
	this.dom_obj.style.width = dims.width+'px';

	this.cobj.top = posxy.top+ZBX_SBOX[this.sbox_id].shiftT;
	this.cobj.left = posxy.left+ZBX_SBOX[this.sbox_id].shiftL;
},

optimizeEvent: function(e){
	this.debug('optimizeEvent');

	if (e.pageX || e.pageY) {
		this.mouse_event.left = e.pageX;
		this.mouse_event.top = e.pageY;
	}
	else if (e.clientX || e.clientY) {
		this.mouse_event.left = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
		this.mouse_event.top = e.clientY + document.body.scrollTop	+ document.documentElement.scrollTop;
	}
	
	this.mouse_event.left = parseInt(this.mouse_event.left);
	this.mouse_event.top = parseInt(this.mouse_event.top);

	if(this.mouse_event.left < this.cobj.left)
		this.mouse_event.left = this.cobj.left;
	else if(this.mouse_event.left > (this.cobj.width + this.cobj.left))
		this.mouse_event.left = this.cobj.width + this.cobj.left;
},

clear_params: function(){
	this.debug('clear_params',this.sbox_id);

	if(!is_null(this.dom_box)) 
		this.dom_obj.removeChild(this.dom_box);
	
	this.mouse_event = {};
	this.start_event = {};
	
	this.dom_box = null;

	this.shifts = {};

	this.box = {};
	this.box.width = 0;
},

ieMouseClick: function(e){
	e = e || window.event;
	if(e.which && (e.which != 1)) return true;
	else if (e.button && (e.button != 1)) return true;

	this.optimizeEvent(e);
	deselectAll();

	var posxy = getPosition(this.dom_obj);
	if((this.mouse_event.top < posxy.top) || (this.mouse_event.top > (this.dom_obj.offsetHeight + posxy.top))) return true;

	Event.stop(e);
}
});

function create_box_on_obj(obj, height){
	var parent = obj.parentNode;

	var div = document.createElement('div');
	parent.appendChild(div);

	div.className = 'box_on';
	div.style.height = (height+2) + 'px';

return div;
}

function moveSBoxes(){

	for(var key in ZBX_SBOX){
		if(empty(ZBX_SBOX[key]) || !isset('sbox', ZBX_SBOX[key])) continue;

		ZBX_SBOX[key].sbox.moveSBoxByObj();
	}
}
//]]
