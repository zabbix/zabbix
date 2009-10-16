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
// Title: graph scrolling
// Author: Aly

<!--

var ZBX_SCROLLBARS = {};

function scrollCreate(sbid, w, timelineid){
	if(is_null(sbid)){
		var sbid = ZBX_SCROLLBARS.length;
	}
	
	if(is_null(timelineid)){
		throw "Parametrs haven't been sent properly";
		return false;
	}
	
	if(is_null(w)){
		var dims = getDimensions(sbid);
		if($(sbid).nodeName.toLowerCase() == 'img')
			w = dims.width + 5;
		else 
			w = dims.width - 5;
	}
	
	if(w < 600) w = 600;

	ZBX_SCROLLBARS[sbid] = new CScrollBar(sbid, timelineid, w);
	
return ZBX_SCROLLBARS[sbid];
}


var CScrollBar = Class.create();

CScrollBar.prototype = {
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
disabled: 1,				// activates/disables scrollbar


// DEBUG
debug_status: 	0,			// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: 	'',			// debug string
debug_prev:		'',			// don't log repeated fnc

initialize: function(sbid, timelineid, width){ // where to put bar on start(time on graph)
	this.scrollbarid = sbid;
	this.debug('initialize', sbid);

//	try{
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

	try{
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
	pos.top-=204; 
	pos.left-=102; 
	this.clndrLeft.clndr.clndrshow(pos.top,pos.left);
},

calendarShowRight: function(){
	this.debug('calendarShowRight');
	if(this.disabled) return false;
//---

	var pos = getPosition(this.dom.info_right); 
	pos.top-=204; 
	pos.left-=58; 
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
		this.timeline.usertime(time);
		
		var startusertime = this.timeline.usertime() - this.timeline.period();
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
	if(this.disabled) return false;
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

	this.ghostBox.usertime = this.timeline.usertime();
	this.ghostBox.startResize(0);
	
},

leftArrowDragChange: function(dragable, e){
	this.debug('leftArrowDragChange');
	if(this.disabled) return false;
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
	if(this.disabled) return false;
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

	this.fixedperiod = (this.fixedperiod == 1)?0:1;
	
	if(this.fixedperiod){
		this.dom.period_state.innerHTML = 'fixed';
	}
	else{
		this.dom.period_state.innerHTML = 'dynamic';
	}
},

syncTZOffset: function(time){
	this.debug('syncTZOffset');

	if(time > 86400){
		var date = new Date();
		date.setTime(time*1000);
		var TimezoneOffset = date.getTimezoneOffset();
		time -= (TimezoneOffset*60);
	}

return time;
},

getTZdiff: function(time1, time2){
	this.debug('getTZdiff');

	var date = new Date();
	date.setTime(time1*1000);
	var TimezoneOffset = date.getTimezoneOffset();
	
	date.setTime(time2*1000);
	var offset = (TimezoneOffset - date.getTimezoneOffset()) * 60;

return offset;
},

roundTime: function(usertime){
	this.debug('roundTime');

	var time = parseInt(usertime);
//---------------
//	if((this._period % 86400) == 0){
	if(time > 86400){
		var dd = new Date();
		dd.setTime(time*1000);
		dd.setHours(0);
		dd.setMinutes(0);
		dd.setSeconds(0);
		dd.setMilliseconds(0);
		
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
	new_period = this.roundTime(new_period);
	new_period = this.syncTZOffset(new_period);

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
			new_usertime = this.ghostBox.usertime;
		}
//SDI(new_usertime+' : '+this.roundTime(new_usertime));
		new_usertime = this.roundTime(new_usertime);

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

//SDI((usertime-period)+' - '+usertime+' : '+period);

// beating Timezone offsets
// USERTIME
	var date = new Date();
	date.setTime(usertime*1000);
	var TimezoneOffset = date.getTimezoneOffset();
	
	var userstarttime = usertime-period;
	date.setTime(userstarttime*1000);
	
	var offset = TimezoneOffset - date.getTimezoneOffset();
	userstarttime -= offset * 60;

//	SDI(usertime+' : '+userstarttime+' | '+offset+' | '+date.getTimezoneOffset()+' | '+TimezoneOffset);	
//--

	this.dom.info_period.innerHTML = this.formatStampByDHM(period, true, false);
	
	var date = datetoarray(userstarttime);
	this.dom.info_left.innerHTML = date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4];//+':'+date[5];
	
	var date = datetoarray(usertime);
	var right_info = date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4];//+':'+date[5];

	if(this.timeline.now()){
		right_info += '  (now!)  ';
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

formatStampByDHM: function(timestamp, double, extend){
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
	
	if(double){
		if(months.toString().length == 1) months = '0'+months;
		if(weeks.toString().length == 1) weeks = '0'+weeks;
		if(days.toString().length == 1) days = '0'+days;
		if(hours.toString().length == 1) hours = '0'+hours;
		if(minutes.toString().length == 1) minutes = '0'+minutes;
	}

	var str = "";
	str+=(years == 0)?(''):(years+'y ');
	str+=(months == 0)?(''):(months+'m ');
	str+=(weeks == 0)?(''):(weeks+'w ');
	
	if(extend && double) str+=days+'d ';
	else str+=(days == 0)?(''):(days+'d ');
	
	str+=hours+'h '+minutes+'m ';
	
return str;
},

debug: function(fnc_name, id){
	if(this.debug_status){
		var str = 'CScrollBar['+this.scrollbarid+'].'+fnc_name;
		if(typeof(id) != 'undefined') str+= ' :'+id;

		if(this.debug_prev == str) return true;

		this.debug_info += str;
		if(this.debug_status == 2){
			SDI(str);
		}
		
		this.debug_prev = str;
	}
},
/*-------------------------------------------------------------------------------------------------*\
*										SCROLL CREATION												*
\*-------------------------------------------------------------------------------------------------*/
appendCalendars: function(){
	this.debug('appendCalendars');
//---
	
	this.clndrLeft = create_calendar((this.timeline.usertime() - this.timeline.period()), this.dom.info_left);
	this.clndrRight = create_calendar(this.timeline.usertime(), this.dom.info_right);

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
		caption = caption.split(' ',2)[0];

		this.dom.linklist[links] = document.createElement('span');
		this.dom.links.appendChild(this.dom.linklist[links]);
		this.dom.linklist[links].className = 'link';
		this.dom.linklist[links].setAttribute('zoom', zooms[key]);
		this.dom.linklist[links].appendChild(document.createTextNode(caption));
		addListener(this.dom.linklist[links],'click',this.setZoom.bindAsEventListener(this, zooms[key]),true);
		
		links++;
	}	
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
},

scrollcreate: function(w){
	var scr_cntr = $('scrollbar_cntr');	
	if(is_null(scr_cntr)) throw('ERROR: SCROLL [scrollcreate]: scroll container node is not found!');
	
	scr_cntr.style.paddingRight = '2px';
	scr_cntr.style.paddingLeft = '2px';
	scr_cntr.style.backgroundColor = '#DDDDDD';

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
	
	this.dom.text.appendChild(document.createTextNode('Zoom:'));
	
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

// Additional postitioning links
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
	this.dom.period_state.appendChild(document.createTextNode('fixed'));
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
}

var CGhostBox = Class.create();
CGhostBox.prototype = {
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

// DEBUG
debug_status: 	1,			// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: 	'',			// debug string
debug_prev:		'',			// don't log repeated fnc

initialize: function(id){
	this.debug('initialize');
	
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
},

debug: function(fnc_name, id){
	if(this.debug_status){
		var str = 'CGhost.'+fnc_name;
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
-->