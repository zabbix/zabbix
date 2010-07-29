// JavaScript Document
/*
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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
// Title: CDebug class
// Author: Aly

var CDebug = Class.create({
className:		null,			// debuging class name
debug_status:	0,				// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info:		'',				// debug string

// ---------- DEBUG ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------
initialize: function(className){
	this.className = className;
},

debug: function(str, id){
	if(this.debug_status){
		str = this.className+'. '+str;

		if(typeof(id) != 'undefined') str+= ' :'+id;

		//if(this.debug_prev == str) return true;

		this.debug_info += str+'\n';		
		if(this.debug_status == 2){
			SDI(str);
		}

		this.debug_prev = str;
	}
},

notify: function(){
},

info: function(msg){
	msg = msg || 'Info.'
	alert(msg);
},

error: function(msg){
	msg = msg || 'Error.'
	throw(msg);
}
});