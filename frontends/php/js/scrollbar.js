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

var SCROLL_BAR = null;
var IMG_PATH='images/general/bar';
//var cal = new calendar();

function scrollinit(w,period,stime,timel,bar_stime){
	if(!is_null(SCROLL_BAR)) return;
	if(typeof(w) == 'undefined'){
		throw "Parametrs haven't been sent properly";
		return false;
	}
	
	w=get_bodywidth() - 36;

	period = ('undefined' == typeof(period))?3600:period;	
	stime = ('undefined' == typeof(stime))?0:stime;
	bar_stime = ('undefined' == typeof(bar_stime))?0:bar_stime;

	gmenuinit(bar_stime,period);

	SCROLL_BAR = new scrollbar(stime,period,bar_stime,w);

	SCROLL_BAR.scrl_arrowleft.onmousedown = SCROLL_BAR.arrowmousedown.bind(SCROLL_BAR);
	SCROLL_BAR.scrl_arrowright.onmousedown = SCROLL_BAR.arrowmousedown.bind(SCROLL_BAR);

	SCROLL_BAR.scrl_scroll.onmouseover=	SCROLL_BAR.arrowmouseover.bind(SCROLL_BAR);
	SCROLL_BAR.scrl_scroll.onmouseout =	SCROLL_BAR.arrowmouseout.bind(SCROLL_BAR);
	
	$('scroll_left').onclick  = SCROLL_BAR.scrollmoveleft.bind(SCROLL_BAR);
	$('scroll_right').onclick = SCROLL_BAR.scrollmoveright.bind(SCROLL_BAR);

	SCROLL_BAR.scrl_bar.onmousedown = SCROLL_BAR.mousedown.bind(SCROLL_BAR);
	
	addListener(document,'mouseup',SCROLL_BAR.mouseup.bindAsEventListener(SCROLL_BAR),true);
}


var scrollbar = Class.create();

scrollbar.prototype = {

dt : new Date(),	// Date object
sdt: new Date(),	// Selected Date object

starttime:0	,		// time in seconds, for scrollbar
timeline: 0,		// starttime+timeline = endtime

barX: 0,			// bar location on x-axis relativ to scrollbar
barW: 8+4+4,		// bar width

minbarW: 16,		// min allowed value for barW

scrollbarX: 0,		// scrollbar location
scrollbarW: 0,		// length of scroll where bar can move

period: 3600,		// viewable period (1h,2h etc.)

pxtime: 0,			// 1px = some time

// action vars
mouseStartX: 0,			// Mouse start position
arrowmouseStartX: 0,	// Mouse start position on draging arrows

prevMouseX: 0,			// Mouse prev x to direction

barStartX: 0,			// barX state on begining of action
barStartW: 0,			// narW state on begining of action

// DOM obj
scrl_scroll: '',			// html scroll object
scrl_bar : '',				// html bar object
scrl_arrowleft: '',			// html left arrow
scrl_arrowright:'',			// html right arrow
scrl_tabinfoleft: '',		// html object(div) where view info (left)
scrl_tabinforight: '',		// html object(div) where view info (right)
scrl_barbgl:'',				// size of left side +6 of bar 
scrl_barbgr:'',				// size of right side +6 of bar 

dom_graphs: new Array(),	// list of img objects (graphs), attached to this bar

// status
scrollmsover: 0,		// if mouse over scrollbar then = 1, out = 0
barmsdown: 0,			// if mousedown on bar = 1, else = 0
arrowmsdown: 0,			// if mousedown on arrow = 1, else = 0
arrow: '',				// pressed arrow (l/r)
changed: 0,				// switches to 1, when scrollbar been moved or period changed
disabled: 1,			// activates/disables scrollbar

xp:	0,					// exponential of time length

initialize: function(stime,period,bar_stime,width){ // where to put bar on start(time on graph)
//	try{
		if(empty(this.scrl_scroll)) this.scrollcreate(width);
		
		this.period = period;
		this.barX = 0;
		this.barW = 8+4+4;

/************************************************************************
*	 Do not change till you fully understand what you are doing.		*
************************************************************************/

		if(stime < 10000000){
			this.starttime = parseInt((this.dt.getTime()/1000) - (3*this.period));
		}
		else{
			this.starttime = stime;
		}

		this.timeline =  parseInt((this.dt.getTime()/1000) - this.starttime);

		if(this.timeline < (2*this.period)){
			this.timeline = 3*this.period;
			this.starttime = parseInt(this.dt.getTime()/1000 - this.timeline);
		}

		if(!bar_stime) bar_stime = (this.dt.getTime()/1000) - this.period;

		this.scrollbarMaxW = parseInt($('scroll_middle').style.width);

		this.xp = Math.log(this.timeline) / this.scrollbarMaxW;
		this.pxtime = 0;
		
		this.minbarW = this.time2px(3600);
		this.minbarW = (this.minbarW<16)?16:this.minbarW;

		this.period2bar(this.period);

		this.scrollbarW = parseInt(this.scrollbarMaxW) - this.barW;
		this.calcpx2time();

		this.barX = this.time2px(bar_stime - this.starttime);
		this.barX = this.checkbarX(0);
//SDI(this.xp+' : '+this.period+' : '+bar_stime+' : '+this.starttime+" ---||--- "+this.barX+' : '+this.barW+' : '+this.scrollbarMaxW);
		this.barchangeW();
		this.movescroll();

		this.settabinfo();

		this.changed = 0; // we need to reset this attribute, because generaly we may already performe a movement
	try{
	} 
	catch(e){
		throw "ERROR: ScrollBar initialization failed!";
		return false;
	}
},

onbarchange: function(){		
	this.changed = 1;
//	SDI(this.timeline+' : '+this.scrollbarMaxW+' : '+this.barW+' : '+this.barX);
	this.onchange();
},

barmousedown: function(){
},

scrollmouseout: function(){		//  U may use this func to attach some function on mouseout from scroll method
},

scrollmouseover: function(){		//  U may use this func to attach some function on mouseover from scroll method
},

onchange: function(){			//  executed every time the bar period or bar time is changed(mouse button released)
},


/*--------------------------------------------------------------------------
------------------------------ ARROW CONTROLS ------------------------------
--------------------------------------------------------------------------*/

arrowmouseover: function(){
	this.arrowmovetoX();
	
	this.scrl_arrowleft.setStyle({display: 'inline'});
	this.scrl_arrowright.setStyle({display: 'inline'});
	
	this.scrollmsover = 1;
	
	try{
		this.scrollmouseover();
	}
	catch(e){
	}
},

arrowmouseout: function(){

	this.scrl_arrowleft.setStyle({display: 'none'});
	this.scrl_arrowright.setStyle({display: 'none'});
	this.scrollmsover = 0;
	
	try{
		this.scrollmouseout();
	}
	catch(e){
		
	}
},

arrowmousedown: function(e){
	if(this.disabled) return false;
	e = e || window.event;

	this.deselectall();
	
	this.arrow = e.originalTarget || e.srcElement;
	
	this.arrowmouseStartX = parseInt(this.getmousexy(e).x);
	this.prevmouseX = this.arrowmouseStartX;
	
	this.barStartX = this.barX;
	this.barStartW = this.barW;

	if((this.arrow.id != 'arrow_l') && (this.arrow.id != 'arrow_r')) return false;
	
	this.arrowmsdown = 1;
	this.barmousedown();
	
	document.onmousemove = this.arrowmousemove.bind(this);
},

arrowmousemove: function(e){
	if(this.disabled) return false;
	e = e || window.event;

	if(this.arrowmsdown!=1) return false;
	
	if(this.arrow.id == 'arrow_l'){
		this.arrowmousemove_L(e);
	}
	else{ 
		this.arrowmousemove_R(e);
	}

	this.calcpx2time();
	this.period = this.calcperiod();
	this.settabinfo();
},

arrowmousemove_L: function(e){
	var mousexy = this.getmousexy(e);
	var mouseXdiff = parseInt(mousexy.x) - parseInt(this.arrowmouseStartX);

	if((this.barW < this.minbarW) && ( mouseXdiff > 0))	return false;

	var barXtemp = this.barX;
	var barWtemp = this.barW;
	
	var PrevbarX = this.barX;
	var tmp = (mousexy.x-this.prevmouseX);
	if((mousexy.x-this.prevmouseX) < 0){
		this.barX = parseInt(this.barStartX + mouseXdiff);
		this.barX = this.checkbarX(mousexy.x);

		this.barW = this.barW + (barXtemp - this.barX);
		this.barW = this.checkbarW(this.prevmouseX);
	}
	else{
		this.barW = parseInt(this.barStartW - mouseXdiff);
		this.barW = this.checkbarW(mousexy.x);
		
		this.barX = parseInt(this.barX+(barWtemp-this.barW));
		this.barX = this.checkbarX(this.prevmouseX);
	}
//SDI('LEFT: X:'+this.barX+' Width: '+this.barW+' Diff: '+mouseXdiff+' Mx: '+mousexy.x+'  PMx: '+this.prevmouseX+'  P: '+this.period);

	this.barchangeW();
	this.barmovetoX();
	this.arrowmovelefttoX();
},

arrowmousemove_R: function(e){
	var mousexy = this.getmousexy(e);
	var mouseXdiff = parseInt(mousexy.x) - parseInt(this.arrowmouseStartX);

	this.barW = parseInt(this.barStartW) + parseInt(mouseXdiff);
	this.barW = this.checkbarW(mousexy.x);

//SDI('RIGHT: X:'+this.barX+' Width: '+this.barW+' Diff: '+mouseXdiff);

	this.barchangeW();
	this.arrowmoverighttoX();
},

arrowmouseup: function(e){
	if(this.disabled) return false;
	
	this.period = this.calcperiod();
	document.onmousemove = null;
	this.arrowmsdown = 0;
	this.onbarchange();
},
//-------------------------------


/*------------------------------------------------------------------------
------------------------------ BAR CONTROLS ------------------------------
------------------------------------------------------------------------*/
mousedown: function(e){
	if(this.disabled) return false;
	
	e = e || window.event;
	this.deselectall();

	this.mouseStartX = parseInt(this.getmousexy(e).x);
	this.prevmouseX = this.mouseStartX;
	this.barStartX = this.barX;
	
	this.barmsdown = 1;
	this.barmousedown();
	document.onmousemove = this.mousemove.bind(this);
},

mousebarup: function(e){
	if(this.disabled) return false;
	
	document.onmousemove = null;
	this.barmsdown = 0;
	this.onbarchange();	
},

mouseup: function(e){
	if(this.disabled) return false;
	
	e = e || window.event;
	
	if(	this.barmsdown == 1){
		this.mousebarup(e);
	}
	else if(this.arrowmsdown == 1){
		this.arrowmouseup(e);
	}
},

mousemove: function(e){
	if(this.disabled) return false;
	
	e = e || window.event;
		
	var mousexy = this.getmousexy(e);
	var mouseXdiff = parseInt(mousexy.x) - parseInt(this.mouseStartX);
	
	this.barX = parseInt(this.barStartX + mouseXdiff);
	this.barX = this.checkbarX(mousexy.x);
	
	this.barmovetoX();
	this.arrowmovetoX();
},
//-------------------------------

//--- scrollmoves
scrollmoveleft: function(){
	if(this.disabled) return false;
	
	this.barmousedown();
	
	this.barX--;
	this.movescroll();
	this.onbarchange();	
},
scrollmoveright: function(){
	if(this.disabled) return false;
	
	this.barmousedown();
	
	this.barX++;
	this.movescroll();
	this.onbarchange();	
},
//-------------------------------


/*-----------------------------------------------------------------------
------------------------------ FUNC IN USE ------------------------------
-----------------------------------------------------------------------*/
//---arrow
arrowmovelefttoX: function(){
	this.scrl_arrowleft.setStyle({left: (this.barX+17-12)+'px'});
},

arrowmoverighttoX: function(){
	var x = this.barX;
	x += parseInt(this.barW,10)+17;
	this.scrl_arrowright.setStyle({left: x+'px'});
},
//-------------------------------

//---bar
checkbarW: function(msx){

//SDI(this.barX+' : '+this.arrowMouseStartX+' : '+msx+' : '+this.barW);
//SDI(this.barW+' < '+this.minbarW);
	if(this.barW < this.minbarW){
		return this.minbarW;
	}
	else if((this.barW + this.barX) > this.scrollbarMaxW){
		return (this.scrollbarMaxW - this.barX);
	}
	this.prevmouseX = msx;
return this.barW;
},

checkbarX: function(msx){
	
	if(this.barX < 0){ 
		return 0;
	} 
	else if((this.barX + this.barW) > this.scrollbarMaxW){
		return (this.scrollbarMaxW - this.barW);
	} 
	
	this.prevmouseX = msx;
return this.barX;
},

barchangeW: function(){
	var w = this.barW;
	
	this.scrollbarW = parseInt(this.scrollbarMaxW - w);

	w -=12;
	var wl = Math.round(w/2);
	var wr = w - wl;
	this.scrl_barbgl.setStyle({width: wl +'px'});
	this.scrl_barbgr.setStyle({width: wr +'px'});
	
	if(IE) this.scrl_bar.setStyle({width: this.barW +'px'});
},

barmovetoX: function(){
	this.scrl_bar.setStyle({left: (this.barX+17) +'px'});
	
	this.settabinfo();
	if(IE) this.scrl_bar.setStyle({width: this.barW +'px'});
},
//-------------------------------

calcpx2time: function(){
	if( this.scrollbarW > 0){
		this.pxtime = parseInt((this.timeline - this.period) / this.scrollbarW);
	}
	else{
		this.pxtime = parseInt(this.timeline - this.period);
	}
},

time2px: function(time){
	var px = time/this.pxtime;
	if(px == 'Infinity'){
		var c = (Math.log(time) / this.xp)
		var cor = this.scrollbarMaxW/1.1 - c;
		px = c - cor;
	}

return Math.round(px);
},

px2time: function(px){
	var cor = (this.scrollbarMaxW/1.1 - px)/2;
	cor = (cor > 0)?cor:0;
	
	var c = Math.round(px+cor);
	var time = Math.round(Math.exp(c*this.xp));
//SDI(px+' : '+time+' = '+this.time2px(time));
return parseInt(time);
},

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

/*---------------------------------------------------------------------
------------------------------ FUNC USES ------------------------------
---------------------------------------------------------------------*/
//---arrow
arrowmovetoX: function(){
	this.arrowmovelefttoX(this.barX);
	this.arrowmoverighttoX(this.barX);
},
//-------------------------------

//---bar
period2bar: function(period){

	this.barW = this.time2px(period);	
	this.barW = this.checkbarW(0);

	this.barchangeW(this.barW);	
},
//-------------------------------

movescroll: function(){
	this.barX = this.checkbarX(0);
	this.barmovetoX();
	this.arrowmovetoX();
	this.settabinfo();
	this.onbarchange();
},

settabinfo: function(){
	if((this.barX+this.barW) < this.scrollbarMaxW){
		this.sdt.setTime(Math.round(this.starttime+(this.barX * this.pxtime)) * 1000);
	}
	else{
		this.sdt.setTime(this.dt.getTime()-(this.period * 1000));
	}
	
	var date = datetoarray((this.sdt.getTime() / 1000));
	this.scrl_tabinfoleft.innerHTML = this.FormatStampbyDHM(this.period)+" | "+date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4]+':'+date[5];
	
	var date = datetoarray((this.sdt.getTime() / 1000) + this.period);
	this.scrl_tabinforight.innerHTML = date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4]+':'+date[5];

	if((this.barX + this.barW) == this.scrollbarMaxW) {
		this.scrl_tabinforight_flag.show();
	}
	else {
		this.scrl_tabinforight_flag.hide();
	}

},

calcperiod: function(){
	var period = this.px2time(this.barW)
	period = (period > this.timeline)?(this.timeline):(period);
	period = (period<3600)?3600:period;
return period;
},

getsTimeInUnix: function(){
	return parseInt(this.sdt.getTime()/1000);
},

getsTime: function(){
	var date = datetoarray(this.sdt.getTime() / 1000);
return ''+date[2]+date[1]+date[0]+date[3]+date[4];
},


getPeriod: function(){
return this.period;
},

FormatStampbyDHM: function(timestamp){
	timestamp = timestamp || 0;
	var days = 	parseInt(timestamp/86400);
	var hours =  parseInt((timestamp - days*86400)/3600);
	var minutes = parseInt((timestamp -days*86400 - hours*3600)/60);

	var str = (days==0)?(''):(days+'d ');
	str+=hours+'h '+minutes+'m ';
	
return str;
},

/*-------------------------------------------------------------------------------------------------*\
*										SCROLL CREATION												*
\*-------------------------------------------------------------------------------------------------*/
scrollcreate: function(w){
	var scr_cntnr = $('scroll_cntnr');
	if(is_null(scr_cntnr)) throw('ERROR: SCROLL [scrollcreate]: scroll container node is not found!');
	
	this.scrl_scroll = document.createElement('div');
	scr_cntnr.appendChild(this.scrl_scroll);

	Element.extend(this.scrl_scroll);
	this.scrl_scroll.setAttribute('id','scroll');
	this.scrl_scroll.setStyle({top: '20px', left: '0px',width: (17*2+w)+'px',visibility: 'hidden'});
	

	this.scrl_tabinfoleft = document.createElement('div');
	this.scrl_scroll.appendChild(this.scrl_tabinfoleft);

	Element.extend(this.scrl_tabinfoleft);
	this.scrl_tabinfoleft.setAttribute('id','scrolltableft');
	this.scrl_tabinfoleft.appendChild(document.createTextNode('0'));
	
	var img = document.createElement('img');
	this.scrl_scroll.appendChild(img);
	
	img.setAttribute('src',IMG_PATH+'/cal.gif');
	img.setAttribute('width','16');
	img.setAttribute('height','12');
	img.setAttribute('border','0');
	img.setAttribute('alt','cal');
	img.setAttribute('id','scroll_calendar');
	
	if(IE){
// tnx to Palmertree ;)
		var div = document.createElement('div');
		this.scrl_scroll.appendChild(div);
	}

	this.scrl_tabinforight = document.createElement('div');
	this.scrl_scroll.appendChild(this.scrl_tabinforight);
	
	Element.extend(this.scrl_tabinforight);
	this.scrl_tabinforight.setAttribute('id','scrolltabright');
	this.scrl_tabinforight.appendChild(document.createTextNode('0'));
	
	this.scrl_tabinforight_flag = document.createElement('div');
	this.scrl_scroll.appendChild(this.scrl_tabinforight_flag);
	
	Element.extend(this.scrl_tabinforight_flag);
	this.scrl_tabinforight_flag.setAttribute('id','scrolltabright_flag');
	this.scrl_tabinforight_flag.innerHTML = '&raquo;';

	this.scrl_arrowleft = document.createElement('div');
	this.scrl_scroll.appendChild(this.scrl_arrowleft);
	
	Element.extend(this.scrl_arrowleft);
	this.scrl_arrowleft.setAttribute('id','arrow_l');
	

	this.scrl_arrowright = document.createElement('div');
	this.scrl_scroll.appendChild(this.scrl_arrowright);
	
	Element.extend(this.scrl_arrowright);
	this.scrl_arrowright.setAttribute('id','arrow_r');
	
	
	var div = document.createElement('div');
	this.scrl_scroll.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','scroll_left');
	

	var div_mid = document.createElement('div');
	this.scrl_scroll.appendChild(div_mid);
	
	Element.extend(div_mid);
	div_mid.setAttribute('id','scroll_middle');
	div_mid.setStyle({width: w+'px'});

	this.scrl_bar = document.createElement('div');
	div_mid.appendChild(this.scrl_bar);
	
	Element.extend(this.scrl_bar);
	this.scrl_bar.setAttribute('id','scroll_bar');
	
	
	var div = document.createElement('div');
	this.scrl_bar.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','bar_left');

	
	this.scrl_barbgl = document.createElement('div');
	this.scrl_bar.appendChild(this.scrl_barbgl);
	
	Element.extend(this.scrl_barbgl);
	this.scrl_barbgl.setAttribute('id','bar_bg_l');
	

	var div = document.createElement('div');
	this.scrl_bar.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','bar_middle');
	div.setAttribute('align','middle');

	
	this.scrl_barbgr = document.createElement('div');
	this.scrl_bar.appendChild(this.scrl_barbgr);
	
	Element.extend(this.scrl_barbgr);
	this.scrl_barbgr.setAttribute('id','bar_bg_r');
	
	
	var div = document.createElement('div');
	this.scrl_bar.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','bar_right');
	

	var div = document.createElement('div');
	this.scrl_scroll.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','scroll_right');
	
/*
<div id="scroll">
	<img id="scroll_calendar" src="img/cal.gif" width="16" height="12" border="0" alt="GM" />
	
	<div id="scrolltableft">0</div>
	<div id="scrolltabright">0</div>

	<div id="arrow_l"></div>
	<div id="arrow_r"></div>

	<div id="scroll_left"></div>
	<div id="scroll_middle">
		<div id="scroll_bar">
				<div id="bar_left"></div>
				<div id="bar_bg_l"></div>
				<div id="bar_middle" align="center"></div>
				<div id="bar_bg_r"></div>
				<div id="bar_right"></div>
		</div>
	</div>
	<div id="scroll_right"></div>
</div>
*/
}
}
-->