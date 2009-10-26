// JavaScript Document
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
// Title: selection box uppon graphs
// Author: Aly

<!--

var ZBX_SBOX = {};		//selection box obj reference

function sbox_init(sbid, timeline, domobjectid){
	if(!isset(domobjectid, ZBX_SBOX)) throw('TimeControl: SBOX is not defined for object "'+domobjectid+'"');
	if(is_null(timeline)) throw("Parametrs haven't been sent properly");
	
	if(is_null(sbid))	var sbid = ZBX_SBOX.length;
	
	var dims = getDimensions(domobjectid);
	var width = dims.width - (ZBX_SBOX[domobjectid].shiftL + ZBX_SBOX[domobjectid].shiftR);

	var obj = $(domobjectid);
	var box = new sbox(sbid, timeline, obj, width, ZBX_SBOX[sbid].height);
	
	
// Listeners
	addListener(window,'resize',moveSBoxes);
		
	if(KQ) setTimeout('ZBX_SBOX['+sbid+'].sbox.moveSBoxByObj('+sbid+');',500);
	
	ZBX_SBOX[sbid].sbox = box;

return box;
}


var sbox = Class.create();

sbox.prototype = {
sbox_id:			'',				// id to create references in array to self

mouse_event:		new Object,		// json object wheres defined needed event params
start_event:		new Object,		// copy of mouse_event when box created

stime:				0,				//	new start time
period:				0,				//	new period

obj:				new Object,		// objects params
dom_obj:			null,			// selection div html obj
box:				new Object,		// object params
dom_box:			null,			// selection box html obj
dom_period_span:	null,			// period container html obj

px2time:			null,			// seconds in 1px

dynamic:			'',				// how page updates, all page/graph only update

debug_status: 		0,				// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: 		'',				// debug string
debug_prev:			'',				// don't log repeated fnc


initialize: function(sbid, timelineid, obj, width, height){
	this.sbox_id = sbid;
	this.debug('initialize');

// Checks
	if(is_null(obj)) throw('Failed to initialize Selection Box with given Object');
	if(!isset(timelineid,ZBX_TIMELINES)) throw('Failed to initialize Selection Box with given TimeLine');

	if(empty(this.dom_obj)){
		this.grphobj = obj;
		this.dom_obj = create_box_on_obj(obj, width, height);
		this.moveSBoxByObj();
	}
//--

// Variable initialization
	this.timeline = ZBX_TIMELINES[timelineid];		
	
	this.obj.width = width;
	this.obj.height = height;
	
	this.box.width = 0;
//--

// Listeners
	if(IE6){
		obj.attachEvent('onmousedown', this.mousedown.bindAsEventListener(this));
		obj.onmousemove = this.mousemove.bindAsEventListener(this);
	}
	else{
		addListener(this.dom_obj, 'mousedown', this.mousedown.bindAsEventListener(this),false);
		addListener(document, 'mousemove', this.mousemove.bindAsEventListener(this),true);
	}
	
	addListener(document, 'mouseup', this.mouseup.bindAsEventListener(this),true);
//---------

	ZBX_SBOX[this.sbox_id].mousedown = false;	
},

onselect: function(){
	this.debug('onselect');

	this.px2time = this.timeline.period() / this.obj.width;
	var userstarttime = (this.timeline.usertime() - this.timeline.period()) + Math.round(this.box.left * this.px2time);
//alert(userstarttime+' : '+Math.round(this.box.left * this.px2time)+' - '+this.box.left);
	var new_period = this.calcperiod();
	
	this.timeline.period(new_period);
	this.timeline.usertime(userstarttime + new_period);

//SDI(this.stime+' : '+this.period);
	this.onchange(this.sbox_id, this.timeline.timelineid, true);
},

onchange: function(){			// bind any func to this
	
},

mousedown: function(e){
	this.debug('mousedown',this.sbox_id);

	e = e || window.event;
	if(e.which && (e.which != 1)) return false;
	else if (e.button && (e.button != 1)) return false;

	cancelEvent(e);
	
	if(ZBX_SBOX[this.sbox_id].mousedown == false){
		this.optimize_event(e);
		deselectAll();
		
		if(IE){
			var posxy = getPosition(this.dom_obj);
			if((this.mouse_event.left < posxy.left) || (this.mouse_event.left > (posxy.left+this.dom_obj.offsetWidth))) return false;
		}
		
		this.create_box();
		ZBX_SBOX[this.sbox_id].mousedown = true;
	}
},

mousemove: function(e){
//	this.debug('mousemove',this.sbox_id);

	e = e || window.event;
//	cancelEvent(e);
	if(ZBX_SBOX[this.sbox_id].mousedown == true){
		this.optimize_event(e);
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
},

create_box: function(){
	this.debug('create_box');
	
	if(!$('selection_box')){
		this.dom_box = document.createElement('div');
		this.dom_obj.appendChild(this.dom_box);
		
		this.dom_period_span = document.createElement('span');
		this.dom_box.appendChild(this.dom_period_span);
		this.dom_period_span.setAttribute('id','period_span');
		
		this.dom_period_span.innerHTML = this.period;
		
		var dims = getDimensions(this.dom_obj);

		this.obj = dims;
		var top = (this.mouse_event.top - this.obj.top);
		var left = (this.mouse_event.left - this.obj.left - 1);
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
		
		var dims = getDimensions(this.dom_obj);
		this.dom_box.style.height = dims.height+'px';
		this.box.height = dims.height;
	
		addListener(this.dom_box, 'mousemove', this.mousemove.bindAsEventListener(this));
	
		this.start_event.top = this.mouse_event.top;
		this.start_event.left = this.mouse_event.left;
	}

	ZBX_SBOX[this.sbox_id].mousedown = false;	
},

resizebox: function(){
	this.debug('resizebox',this.sbox_id);
	
	if(ZBX_SBOX[this.sbox_id].mousedown == true){
		
//		var height = this.validateH(this.mouse_event.top - this.start_event.top);
//		height = this.obj.height;
//		this.dom_box.style.height = height+'px';
//		this.box.height = height;

// 		fix wrong selection box
		if(this.mouse_event.left > (this.obj.width + this.obj.left)) {
			this.moveright(this.obj.width - (this.start_event.left - this.obj.left));
		} 
		else if(this.mouse_event.left < this.obj.left) {
			this.moveleft(0, this.start_event.left - this.obj.left);
		}
		
		var width = this.validateW(this.mouse_event.left - this.start_event.left);
		
		if(width>0){
			this.moveright(width);
		}
		else if(width<0){
			this.moveleft(this.mouse_event.left - this.obj.left, width);
		}

		this.period = this.calcperiod();
		
		if(!is_null(this.dom_box)) 
			this.dom_period_span.innerHTML = this.FormatStampbyDHM(this.period)+((this.period<3600)?' [min 1h]':'');
	}
},

moveleft: function(left, width){
	this.debug('moveleft');
	
	//this.box.left = this.mouse_event.left - this.obj.left;
	this.box.left = left;
	if(!is_null(this.dom_box)) this.dom_box.style.left = this.box.left+'px';

	this.box.width = Math.abs(width);
	if(!is_null(this.dom_box)) this.dom_box.style.width = this.box.width+'px';
},

moveright: function(width){
	this.debug('moveright');
	
	this.box.left = (this.start_event.left - this.obj.left);
	if(!is_null(this.dom_box)) this.dom_box.style.left = this.box.left+'px';
	
	if(!is_null(this.dom_box)) this.dom_box.style.width = width+'px';
	this.box.width = width;
},

calcperiod: function(){
	this.debug('calcperiod');

	if(this.box.width >= this.obj.width){
		var new_period = this.timeline.period();
	}
	else{
		this.px2time = this.timeline.period()/this.obj.width;
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
//SDI(this.start_event.left+' - '+this.obj.left+' - '+w+' > '+this.obj.width)
	if(((this.start_event.left-this.obj.left)+w)>this.obj.width) 
		w = 0;//this.obj.width - (this.start_event.left - this.obj.left) ;

	if(this.mouse_event.left < this.obj.left) 
		w = 0;//(this.start_event.left - this.obj.left);
	
return w;
},

validateH: function(h){
	this.debug('validateH');
	
	if(h<=0) h=1;
	if(((this.start_event.top-this.obj.top)+h)>this.obj.height) 
		h = this.obj.height - this.start_event.top;
return h;
},

moveSBoxByObj: function(){
	this.debug('moveSBoxByObj',this.sbox_id);
	
	var posxy = getPosition(this.grphobj);

	this.dom_obj.style.top = (posxy.top+ZBX_SBOX[this.sbox_id].shiftT)+'px';
	this.dom_obj.style.left = (posxy.left+ZBX_SBOX[this.sbox_id].shiftL-1)+'px';	

	posxy = getPosition(this.dom_obj);

	this.obj.top = parseInt(posxy.top); 
	this.obj.left = parseInt(posxy.left);
},

optimize_event: function(e){
	this.debug('optimize_event');
	
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

clear_params: function(){
	this.debug('clear_params',this.sbox_id);

	if(!is_null(this.dom_box)) 
		this.dom_obj.removeChild(this.dom_box);
	
	this.mouse_event = new Object;
	this.start_event = new Object;
	
	this.dom_box = '';
	
	this.box = new Object;
	this.box.width = 0;
},

debug: function(fnc_name, id){
	if(this.debug_status){
		var str = 'SBox['+this.sbox_id+'].'+fnc_name;
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

function create_box_on_obj(obj, width, height){
	var parent = obj.parentNode;

	var div = document.createElement('div');
	parent.appendChild(div);

	div.className = 'box_on';
	div.style.height = (height+1) + 'px';
	div.style.width = (width+1) + 'px';
	
return div;
}

function moveSBoxes(){
	for(var key in ZBX_SBOX){
		if(!empty(ZBX_SBOX[key]) && isset('sbox', ZBX_SBOX[key]))
			ZBX_SBOX[key].sbox.moveSBoxByObj();
	}
}