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
**/
// JavaScript Document
// Screen class beta
// Author: Aly

// [!CDATA[
var ZBX_SCREENS = new Array();			// screens obj reference
Position.includeScrollOffsets = true;

// screenid ALWAYS must be a STRING (js doesn't support uint64) !!!!
function init_screen(screenid, obj_id, id){
	if(typeof(id) == 'undefined'){
		var id = ZBX_SCREENS.length;
	}

	if(is_number(screenid) && (screenid > 100000000000000)){
		throw('Error: Wrong type of arguments passed to function [init_screen]');
	}
	
	ZBX_SCREENS[id] = new Object;
	ZBX_SCREENS[id].screen = new Cscreen(screenid, obj_id, id);
}

var Cscreen = Class.create();

Cscreen.prototype = {
id:	0,								// inner js class id
screenid: 0,

dragged: 0,							// element dragged or just clicked
screen_obj: null,					// DOM ref to screen obj

debug_status: 0,					// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: '',						// debug string


initialize: function(screenid, obj_id, id){
	this.debug('initialize');
	
	this.screenid = screenid;
	this.id = id;
	
	this.screen_obj = $(obj_id);
//	this.add_divs(this.screen_obj, 'td', 'draggable');
	
	var trs = this.screen_obj.getElementsByTagName("tr");

	function wedge(event){ return false }
	
	for (var i = 0; i < trs.length; i++){
		var divs = document.getElementsByClassName("draggable", trs[i]);
		for (var j = 0; j < divs.length; ++j){
			addListener(divs[j], 'mousedown', this.deactivate_drag.bindAsEventListener(this), false);
			new Draggable(divs[j], {//revert: 'failure',
//									handle:'handle'+c,
				revert: true,
//				function(){
//					if(IE){
//						Event.stopObserving(document.body, "drag", wedge, false);
//						Event.stopObserving(document.body, "selectstart", wedge, false);
//					}
//				},
				onStart: function(){
					if(IE){
						Event.observe(document.body, "drag", wedge, false);
						Event.observe(document.body, "selectstart", wedge, false);
					}
				},
				onEnd: this.activate_drag.bind(this)
			});
		}
	}

	var divs = document.getElementsByClassName("draggable", this.screen_obj);
	for (var j = 0; j < divs.length; ++j){
		Droppables.add(divs[j], {accept:'draggable',
									hoverclass:'hoverclass123',
									onDrop: this.on_drop.bind(this)
								});
	}
},

on_drop: function(element, dropon, event){
	var dropon_parent = dropon.parentNode;
	element.parentNode.appendChild(dropon);
	dropon_parent.appendChild(element);

	element.style.top = '0px';
	element.style.left = '0px';
	
	var pos = element.id.split('_');
	var r1 = pos[1];
	var c1 = pos[2];
	
	pos = dropon.id.split('_');
	var r2 = pos[1];
	var c2 = pos[2];
	
	var url = new Curl(location.href);
	
	var args = url.getArguments();
	for(a in args){
		if(a == 'screenid') continue;
		url.unsetArgument(a);
	}
	
	url.setArgument('sw_pos[0]',r1);
	url.setArgument('sw_pos[1]',c1);
	url.setArgument('sw_pos[2]',r2);
	url.setArgument('sw_pos[3]',c2);
	
	
	// url.unsetArgument('add_row');
	// url.unsetArgument('add_col');
	// url.unsetArgument('rmv_row');
	// url.unsetArgument('rmv_col');
		
	location.href = url.getUrl();
},

element_onclick: function(href){
	if(this.dragged == 0){
		location.href = href;
	}
},

activate_drag: function(){
	this.debug('activate_drag');
	this.dragged = 1;
},

deactivate_drag: function(){
	this.debug('deactivate_drag');
	this.dragged = 0;
},

debug: function(str){
	if(this.debug_status){
		this.debug_info += str + '\n';
		
		if(this.debug_status == 2){
			SDI(str);
		}
	}	
}
}