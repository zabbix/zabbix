/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

var cookie ={
init: function () {
	var allCookies = document.cookie.split('; ');
	for (var i=0;i<allCookies.length;i++) {
		var cookiePair = allCookies[i].split('=');
		this[cookiePair[0]] = cookiePair[1];
	}
},

create: function (name,value,days) {
	if(days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}else{ 
		var expires = "";
	}
	
	document.cookie = name+"="+value+expires+"; path=/";
	this[name] = value;
},

read : function(name){
	if(typeof(this[name]) != 'undefined'){
		return this[name];
	} else {
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for(var i=0;i < ca.length;i++) {
			var c = ca[i];
			while (c.charAt(0)==' ') c = c.substring(1,c.length);
			if(c.indexOf(nameEQ) == 0)	return this[name] = c.substring(nameEQ.length,c.length);
		}
	}
	return null;
},

erase: function (name) {
	this.create(name,'',-1);
	this[name] = undefined;
}

}
cookie.init();