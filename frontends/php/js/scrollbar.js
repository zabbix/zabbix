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
<!--

var SCROLL_BAR;
var IMG_PATH='images/general/bar';
//var cal = new calendar();

function scrollinit(x,y,w,period,stime,timel,bar_stime){
	if((typeof(x) == 'undefined') && (typeof(y) == 'undefined')){
		alert("Parametrs haven't been sent properly");
		return false;
	}
	w = w | 0;
	if(w == 0){ 
		w=get_bodywidth() - 36;
	}
	scrollcreate(x,y,w);
	
	period = period || 3600;
	
	stime = stime || 0;
	timel = timel || 0;
	
	bar_stime = bar_stime || stime;

	gmenuinit(y,x,period,bar_stime);

	SCROLL_BAR = new scrollbar(stime,timel,period,bar_stime);
	
	var scroll_x = $('scroll');
	var arrow_l = $('arrow_l');
	var arrow_r = $('arrow_r');
	
	arrow_l.onmousedown = SCROLL_BAR.arrowmousedown.bind(SCROLL_BAR);
	arrow_r.onmousedown = SCROLL_BAR.arrowmousedown.bind(SCROLL_BAR);

	scroll_x.onmouseover=	SCROLL_BAR.arrowmouseover.bind(SCROLL_BAR);
	scroll_x.onmouseout =	SCROLL_BAR.arrowmouseout.bind(SCROLL_BAR);
	
	$('scroll_left').onclick  = SCROLL_BAR.scrollmoveleft.bind(SCROLL_BAR);
	$('scroll_right').onclick = SCROLL_BAR.scrollmoveright.bind(SCROLL_BAR);

	$('scroll_bar').onmousedown = SCROLL_BAR.mousedown.bind(SCROLL_BAR);
	if(IE){
		document.attachEvent('onmouseup',SCROLL_BAR.mouseup.bindAsEventListener(SCROLL_BAR));
	}
	else{
		document.addEventListener('mouseup',SCROLL_BAR.mouseup.bindAsEventListener(SCROLL_BAR),true);
	}
	
//	cal.onselect = SCROLL_BAR.movebarbydate.bind(SCROLL_BAR);
}


var scrollbar = Class.create();

scrollbar.prototype = {

dt : new Date(),	// Date object
starttime:0	,		// time in seconds, for scrollbar
timelength: 0,		// starttime+timelength = endtime

bar : '',			// bar object
barX: 0,			// bar location on x-axis relativ to scrollbar
barW: 8+4+4,		// bar width
minbarW: 16,		// min allowed value for barW due to period > 3600
scrollbarX: 0,		// scrollbar location
scrollbarW: 0,		// length of scroll where bar can move
maxX: 8+4+4+12,		// max movement range for bar
mouseX: 0,			// mouse location
barmsdown: 0,		// if mousedown on bar = 1, else = 0

arrow: '',			// pressed arrow (l/r)
arrowleft: '',		// left arrow
arrowright:'',		// right arrow
arrowX: 0,			// arrow position
arrowmsdown: 0,		// if mousedown on arrow = 1, else = 0
arrowmsX: 0,		// mouse location when dragging arrows

barbgl:26,			// size of left side +6 of bar 
barbgr:26,			// size of right side +6 of bar 

period: 3600,		// viewable period (1h,2h etc.)

pxtime: 0,			// 1px = some time

tabinfoleft: '',	// html object(div) where view info (left)
tabinforight: '',	// html object(div) where view info (right)

scrollmsover: 0,	// if mouse over scrollbar then = 1, out = 0

changed: 0,			// switches to 1, when scrollbar been moved or period changed

xp:	0,				// exponential of time length

initialize: function(stime,timel,period,bar_stime){ // where to put bar on start(time on graph)
	try{
		this.tabinfoleft = $('scrolltableft');
		this.tabinforight = $('scrolltabright');
		
		this.arrowleft = $('arrow_l');
		this.arrowright = $('arrow_r');
		this.arrowX = this.barX;
	
		this.bar = $('scroll_bar');
	
		this.barbgl = $('bar_bg_l');
		this.barbgr = $('bar_bg_r');

		this.period = parseInt(period);

/************************************************************************
*	 Do not change till you fully understand what you are doing.		*
************************************************************************/

		if(stime < 10000000){
			this.timelength = (timel < this.period)?(3*this.period):timel;
			this.starttime = parseInt(this.dt.getTime()/1000 - this.timelength);
		}
		else{
			this.starttime = stime;
			this.timelength = (timel < this.period)?((this.dt.getTime()/1000) - stime):timel;

			if(this.timelength < (2*this.period)){
				this.timelength = 3*this.period;
				this.starttime = parseInt(this.dt.getTime()/1000 - this.timelength);
			}
		}

		this.endtime = this.dt.getTime()/1000;
		this.maxX = parseInt($('scroll_middle').style.width);

		this.settabinfo(this.barX);
		
		this.xp = Math.log(this.timelength) / this.maxX;
		
		this.minbarW = this.time2px(3600);
		this.period2bar(this.period);

		this.scrollbarW = parseInt(this.maxX) - this.barW;
		this.calcpx2time();

		if(typeof(bar_stime) != 'undefined') this.movebarbydate(bar_stime);
		
		this.changed = 0; // we need to reset this attribute, becouse generaly we may already performe a movement.
	} catch(e){
		alert("Needed params haven't been initialized properly");
		return false;
	}
},

onbarchange: function(){		
	this.changed = 1;
//	SDI(this.timelength+' : '+this.maxX+' : '+this.barW+' : '+this.barX);
	this.onchange();
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
	this.arrowmovetoX(this.arrowX);
	var arrowflag = false;
	if(this.barX > 12){
		this.arrowleft.setStyle({display: 'inline'});
		arrowflag = true;
	}
	
	if((this.barX+this.barW) < (this.maxX-12)){
		this.arrowright.setStyle({display: 'inline'});
		arrowflag = true;
	}
	if(!arrowflag) this.arrowright.setStyle({display: 'inline'});
	
	this.scrollmsover = 1;
	
	try{
		this.scrollmouseover();
	}
	catch(e){
	}
},

arrowmouseout: function(){

	this.arrowleft.setStyle({display: 'none'});
	this.arrowright.setStyle({display: 'none'});
	this.scrollmsover = 0;
	
	try{
		this.scrollmouseout();
	}
	catch(e){
		
	}
},

arrowmousedown: function(e){
	e = e || window.event;

	this.deselectall();
	
	this.arrow = e.originalTarget || e.srcElement;
	this.arrowmsX = parseInt(this.getmousexy(e).x);

	if((this.arrow.id != 'arrow_l') && (this.arrow.id != 'arrow_r')) return false;
	
	this.arrowmsdown = 1;
	this.barmousedown();
	
	document.onmousemove = this.arrowmousemove.bind(this);
},

arrowmousemove: function(e){
	e = e || window.event;
	
	if(this.arrow.id == 'arrow_l'){
		this.arrowmousemove_L(e);
	}
	else{ 
		this.arrowmousemove_R(e);
	}
	
	this.calcpx2time();
	this.period = this.calcperiod(this.barW);
	this.settabinfo(this.barX);
},

arrowmousemove_L: function(e){
	var mousexy = this.getmousexy(e);

	var mouseXdiff = parseInt(mousexy.x) - parseInt(this.arrowmsX);
	var barXr = this.barX+this.barW;
	
	if((this.barW < this.minbarW) && ( mouseXdiff > 0))	return false;

	var barWtemp = parseInt(this.barW) - parseInt(mouseXdiff);
	var barXtemp = barXr - barWtemp;
	
	if(barXtemp < 0) return barWtemp;
	barWtemp = this.checkbarW(barWtemp, this.checkbarX(barXtemp, mousexy.x), mousexy.x);
	
	if((barWtemp > this.minbarW) && (barWtemp != this.barW) && ((this.barX + mouseXdiff) > -1)){
		this.barW = barWtemp;
		this.barX = this.checkbarX(barXtemp, mousexy.x);
	} 

	this.barchangeW(this.barW);
	this.barmovetoX(this.barX);
	this.arrowmovelefttoX(this.barX);
},

arrowmousemove_R: function(e){
	var mousexy = this.getmousexy(e);
	var mouseXdiff = parseInt(mousexy.x) - parseInt(this.arrowmsX);
	
	var barWtemp = parseInt(this.barW) + parseInt(mouseXdiff);
	
	barWtemp = this.checkbarW(barWtemp, this.barX, mousexy.x);

	if((barWtemp > this.minbarW) && (barWtemp != this.barW)){
		this.barW = barWtemp;
	}
	
	this.barchangeW(this.barW);
	this.arrowmoverighttoX(this.barX);
},

arrowmouseup: function(e){
	this.period = this.calcperiod(this.barW);
	document.onmousemove = null;
	this.arrowmsdown = 0;
	this.onbarchange();
},
//-------------------------------


/*------------------------------------------------------------------------
------------------------------ BAR CONTROLS ------------------------------
------------------------------------------------------------------------*/
mousedown: function(e){
	e = e || window.event;
	this.deselectall();

	this.scrollbarX = Position.cumulativeOffset(this.bar.parentNode)[0];
	this.barX = Position.cumulativeOffset(this.bar)[0] - this.scrollbarX;
	this.mouseX = parseInt(this.getmousexy(e).x);

	this.barmsdown = 1;
	this.barmousedown();
	document.onmousemove = this.mousemove.bind(this);
},

mousebardown: function(e){
	
},

mousebarup: function(e){
	document.onmousemove = null;
	this.barmsdown = 0;
	this.onbarchange();	
},

mouseup: function(e){
	e = e || window.event;
	if(	this.barmsdown == 1){
		this.mousebarup(e);
	}
	else if(this.arrowmsdown == 1){
		this.arrowmouseup(e);
	}
},

mousemove: function(e){
	e = e || window.event;
		
	var mousexy = this.getmousexy(e);
	var mouseXdiff = parseInt(mousexy.x) - parseInt(this.mouseX);
	
	this.barX = parseInt(this.barX) + parseInt(mouseXdiff);
	this.barX = this.checkbarX(this.barX, mousexy.x);
	
	this.barmovetoX(this.barX);
	this.arrowmovetoX(this.barX);
},
//-------------------------------

/*-----------------------------------------------------------------------
------------------------------ FUNC IN USE ------------------------------
-----------------------------------------------------------------------*/
//---arrow
arrowmovelefttoX: function(x){
	this.arrowleft.setStyle({left: (x+17-12)+'px'});
},

arrowmoverighttoX: function(x){
	x += parseInt(this.barW)+17;
	this.arrowright.setStyle({left: x+'px'});
},
//-------------------------------

//---bar
checkbarW: function(barWtemp,barXtemp,msx){
	if(barWtemp < this.minbarW){
		return this.minbarW;
	}
//	SDI(barXtemp+' : '+this.arrowmsX+' : '+msx+'<br />'+barWtemp+' : '+this.barW);
	
	if(barWtemp < 16){ 
		return 16;
	} 
	else if((barWtemp + barXtemp) > this.maxX){
		return this.maxX - barXtemp;
	}
	else{
		this.arrowmsX = parseInt(msx);
		return barWtemp;
	}	
},

checkbarX: function(barXtemp,msx){
	if(barXtemp < 0){ 
		return 0;
	} 
	else if((barXtemp + this.barW) > this.maxX){
		return this.scrollbarW;
	} 
	else{
		this.mouseX = parseInt(msx);
		return barXtemp;
	}
},

barchangeW: function(w){
	this.scrollbarW = parseInt(this.maxX - w);

	w -=12;
	var wl = Math.round(w/2);
	var wr = w - wl;
	this.barbgl.setStyle({width: wl +'px'});
	this.barbgr.setStyle({width: wr +'px'});
},

barmovetoX: function(x){
	this.arrowX = x;
	this.bar.setStyle({left: (x+17) +'px'});
	
	this.settabinfo(x);
},
//-------------------------------

calcpx2time: function(){
	if( this.scrollbarW > 0){
		this.pxtime = parseInt((this.timelength - this.period) / this.scrollbarW);
	}
	else{
		this.pxtime = parseInt(this.timelength - this.period);
	}
},

time2px: function(time){
	var px = time/this.pxtime;
	if(px == 'Infinity'){
		var c = (Math.log(time) / this.xp)
		var cor = this.maxX/1.1 - c;
		px = Math.round(c - cor);
	} 
return Math.round(px);
},

px2time: function(px){
	var cor = (this.maxX/1.1 - px)/2;
	cor = (cor > 0)?cor:0;
	
	var c = Math.round(px+cor);
	var time = Math.round(Math.exp(c*this.xp));
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

formatdate: function(x,adjust){

	adjust = adjust || 0;
	this.dt.setTime(Math.round((this.starttime+(x*this.pxtime) + adjust) * 1000));

	var date = new Array();
	date[0] = this.dt.getDate();
	date[1] = this.dt.getMonth()+1;
	date[2] = this.dt.getFullYear();
	date[3] = this.dt.getHours();
	date[4] = this.dt.getMinutes();
	date[5] = this.dt.getSeconds();
	
	for(i = 0; i < date.length; i++){
		if((date[i]+'').length < 2) date[i] = '0'+date[i];
	}
	
return date;
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
arrowmovetoX: function(x){
	this.arrowmovelefttoX(x);
	this.arrowmoverighttoX(x);
},
//-------------------------------

//---bar
period2bar: function(period){
	var barW = this.time2px(period);
	this.barW = this.checkbarW(barW);
	
	this.barchangeW(this.barW);	
},
//-------------------------------

//--- scrollmoves
scrollmoveleft: function(){
	this.movescroll(this.barX-1);
},
scrollmoveright: function(){
	this.movescroll(this.barX+1);
},
//-------------------------------

movebarbydate: function(timestamp){
	timestamp = parseInt(timestamp - this.starttime);
	this.movescroll(this.time2px(timestamp));
},

movescroll: function(x){
	this.barX = this.checkbarX(x,0);
	this.barmovetoX(this.barX);
	this.arrowmovetoX(this.barX);
	this.settabinfo(this.barX);
	this.onbarchange();
},

settabinfo: function(x){
	
	var date = this.formatdate(x);
	this.tabinfoleft.innerHTML = this.FormatStampbyDHM(this.period)+" | "+date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4]+':'+date[5];
	
	date = this.formatdate(x,this.period);
	this.tabinforight.innerHTML = date[0]+'.'+date[1]+'.'+date[2]+' '+date[3]+':'+date[4]+':'+date[5];
},

calcperiod: function(barW){
	var period = this.px2time(barW)
	period = (period > this.timelength)?(this.timelength):(period);
	this.getPeriod();
return period;
},

getsTimeInUnix: function(){

	return Math.round((this.starttime+(this.barX*this.pxtime)));
},

getsTime: function(){
	var date = this.formatdate(this.barX);
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
}
}


/*-------------------------------------------------------------------------------------------------*\
*										SCROLL CREATION												*
\*-------------------------------------------------------------------------------------------------*/
function scrollcreate(x,y,w){
	var div_scroll = document.createElement('div');
	document.getElementsByTagName('body')[0].appendChild(div_scroll);

	Element.extend(div_scroll);
	div_scroll.setAttribute('id','scroll');
	div_scroll.setStyle({top: y+'px', left: x+'px',width: (17*2+w)+'px',visibility: 'hidden'});
	

	var div = document.createElement('div');
	div_scroll.appendChild(div);

	Element.extend(div);
	div.setAttribute('id','scrolltableft');
	div.appendChild(document.createTextNode('0'));
	
	var img = document.createElement('img');
	div_scroll.appendChild(img);
	
	img.setAttribute('src',IMG_PATH+'/cal.gif');
	img.setAttribute('width','16');
	img.setAttribute('height','12');
	img.setAttribute('border','0');
	img.setAttribute('alt','cal');
	img.setAttribute('id','scroll_calendar');

	var div = document.createElement('div');
	div_scroll.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','scrolltabright');
	div.appendChild(document.createTextNode('0'));
	

	var div = document.createElement('div');
	div_scroll.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','arrow_l');
	

	var div = document.createElement('div');
	div_scroll.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','arrow_r');
	
	
	var div = document.createElement('div');
	div_scroll.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','scroll_left');
	

	var div_mid = document.createElement('div');
	div_scroll.appendChild(div_mid);
	
	Element.extend(div_mid);
	div_mid.setAttribute('id','scroll_middle');
	div_mid.setStyle({width: w+'px'});

	var div_bar = document.createElement('div');
	div_mid.appendChild(div_bar);
	
	Element.extend(div_bar);
	div_bar.setAttribute('id','scroll_bar');
	
	
	var div = document.createElement('div');
	div_bar.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','bar_left');

	
	var div = document.createElement('div');
	div_bar.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','bar_bg_l');
	

	var div = document.createElement('div');
	div_bar.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','bar_middle');
	div.setAttribute('align','middle');

	
	var div = document.createElement('div');
	div_bar.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','bar_bg_r');
	
	
	var div = document.createElement('div');
	div_bar.appendChild(div);
	
	Element.extend(div);
	div.setAttribute('id','bar_right');
	

	var div = document.createElement('div');
	div_scroll.appendChild(div);
	
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
-->