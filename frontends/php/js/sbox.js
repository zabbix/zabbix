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

var A_SBOX = new Array();		//selection box obj reference

function sbox_init(stime, period){
	
	period = period || 3600;
	if(period < 3600) period = 3600;
	
	var dt = new Date;
	stime = stime || (parseInt(dt.getTime()/1000 - period));

	var s_box = new sbox(parseInt(stime),parseInt(period));
return s_box;
}


var sbox = Class.create();

sbox.prototype = {
sbox_id:			'',				// id to create references in array to self

mouse_event:		'',				// json object wheres defined needed event params
start_event:		'',				// copy of mouse_event when box created

stime:				'',				//	new start time
period:				'',				//	new period

obj:				'',				// objects params
dom_obj:			'',				// selection div html obj
box:				'',				// object params
dom_box:			'',				// selection box html obj
dom_period_span:	'',				// period container html obj

px2time:			'',				// seconds in 1px


initialize: function(stime, period){
	
	this.sbox_id = A_SBOX.length || 0;
	
	this.mouse_event = new Object;
	this.start_event = new Object;
	this.obj = new Object;
	this.box = new Object;
	
	this.obj.stime = stime;
	this.obj.period = period;

	this.mouse_event.mousedown = false;	
},

onselect: function(){
	this.px2time = parseInt(this.obj.period/this.obj.width);
	
	this.stime = Math.round(this.box.left * this.px2time + this.obj.stime);
	this.period = this.calcperiod();

	this.sboxload();
},

sboxload: function(){			// bind any func to this
	
},

mousedown: function(e){
	e = e || window.event;
	cancelEvent(e);

	if(this.mouse_event.mousedown == false){
		
		this.optimize_event(e);

		this.deselectall();
		this.create_box();
		this.mouse_event.mousedown = true;
	}
},

mousemove: function(e){
	e = e || window.event;
	cancelEvent(e);
	if(this.mouse_event.mousedown == true){
		this.optimize_event(e);
		this.resizebox();
	}
},

mouseup: function(e){
	e = e || window.event;

	if(this.mouse_event.mousedown == true){
		this.onselect();

		this.clear_params();
		this.mouse_event.mousedown = false;
	}
},

create_box: function(){
	if(!$('selection_box')){
		this.dom_box = document.createElement('div');
		this.dom_obj.appendChild(this.dom_box);
		
		this.dom_period_span = document.createElement('span');
		this.dom_box.appendChild(this.dom_period_span);
		this.dom_period_span.setAttribute('id','period_span');
		
		this.dom_period_span.innerHTML = this.period;
		
		var top = (this.mouse_event.top-this.obj.top);
		var left = (this.mouse_event.left-this.obj.left);
		
		top = 0;
		
		this.dom_box.setAttribute('id','selection_box');
		if(IE){
			this.dom_box.style.top = top+'px'; 
			this.dom_box.style.left= left+'px';
		}
		else{
			this.dom_box.setAttribute('style', 'top: '+top+'px; left: '+left+'px;');
		}
	
		this.box.top = top;
		this.box.left = left;
		
		var height = this.obj.height;
		this.dom_box.style.height = height+'px';
		this.box.height = height;
	
		if(IE){
//			this.dom_box.attachEvent('onmousemove',S_BOX.mousemove.bindAsEventListener(S_BOX));
			this.dom_box.onmousemove = A_SBOX[this.sbox_id].sbox.mousemove.bind(A_SBOX[this.sbox_id].sbox);
		}
		else{
			this.dom_box.addEventListener('mousemove',A_SBOX[this.sbox_id].sbox.mousemove.bindAsEventListener(A_SBOX[this.sbox_id].sbox),true);
		}
	
		this.start_event.top = this.mouse_event.top;
		this.start_event.left = this.mouse_event.left;
	}
},

resizebox: function(){
	if(this.mouse_event.mousedown == true){
		
//		var height = this.validateH(this.mouse_event.top - this.start_event.top);
//		height = this.obj.height;
//		this.dom_box.style.height = height+'px';
//		this.box.height = height;
		
		var width = this.validateW(this.mouse_event.left - this.start_event.left);
		if(width>0){
			this.moveright(width);
		}
		else if(width<0){
			this.moveleft(width);
		}

		this.period = this.calcperiod();
		this.dom_period_span.innerHTML = this.FormatStampbyDHM(this.period)+((this.period<3600)?' [min 1h]':'');
	}
},

moveleft: function(width){
	
	this.box.left = this.mouse_event.left - this.obj.left;
	this.dom_box.style.left = this.box.left+'px';

	this.box.width = Math.abs(width);
	this.dom_box.style.width = this.box.width+'px';
},

moveright: function(width){
	this.box.left = (this.start_event.left - this.obj.left);
	this.dom_box.style.left = this.box.left+'px';
	
	this.dom_box.style.width = width+'px';
	this.box.width = width;
},

calcperiod: function(){
	this.px2time = parseInt(this.obj.period/this.obj.width);
return	Math.round(this.box.width * this.px2time);
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

validateW: function(w){
	if(((this.start_event.left-this.obj.left)+w)>this.obj.width) 
		w = 0;//this.obj.width - (this.start_event.left - this.obj.left) ;

	if(this.mouse_event.left < this.obj.left) 
		w = 0;//(this.start_event.left - this.obj.left);
	
return w;
},

validateH: function(h){
	if(h<=0) h=1;
	if(((this.start_event.top-this.obj.top)+h)>this.obj.height) 
		h = this.obj.height - this.start_event.top;
return h;
},

optimize_event: function(e){
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

clear_params: function(){
	this.dom_obj.removeChild(this.dom_box);
	
	this.mouse_event = new Object;
	this.start_event = new Object;
	
	this.dom_box = '';
	this.box = new Object;
}
}

function create_box_on_obj(obj_ref){
	if(!isset(obj_ref)) throw('Reference Object is not defined');
	
	var div = document.createElement('div');
	obj_ref.appendChild(div);
	
	div = $(div);
	div.addClassName('box_on');
	
return div;
}