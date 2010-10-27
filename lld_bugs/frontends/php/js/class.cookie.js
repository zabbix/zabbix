//Javascript document
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

/************************************************************************************/
/*								COOKIES CONTROL 									*/
/************************************************************************************/
// Title: cookies class
// Description: to manipulate cookies on client side
// Author: Aly
var cookie ={
cookies: new Array(),

init: function(){
	var path = new Curl();
	var page = path.getPath();

	var allCookies = document.cookie.split('; ');
	for (var i=0;i<allCookies.length;i++) {
		var cookiePair = allCookies[i].split('=');

		if((cookiePair[0].indexOf('cb_') > -1) && (cookiePair[0].indexOf('cb_'+page) == -1)){
			this.erase(cookiePair[0]);
		}
		else{
			this.cookies[cookiePair[0]] = cookiePair[1];
//SDI(cookiePair[0] + ' ' + cookiePair[1]);			
		}
	}
},

create: function(name,value,days){
	if(typeof(days) != "undefined") {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else{ 
		var expires = "";
	}

	document.cookie = name+"="+value+expires+"; path=/";
	
// Apache header size limit
	if(document.cookie.length > 8000){
		document.cookie = name+"=; path=/";
		alert(locale['S_MAX_COOKIE_SIZE_REACHED']);
		return false;
	}
	else{
		this.cookies[name] = value;
	}

return true;
},

createArray: function(name,value,days){
	var list = value.join(',');
	var list_part = "";
	var part = 1;
	
	var part_count = parseInt(this.read(name+'_parts'),10);
	if(is_null(part_count)) part_count = 1;
	
	var tmp_index = 0
	var result = true;
	while(list.length > 0){
		list_part = list.substr(0, 4000);
		list = list.substr(4000);

		if(list.length > 0){
			tmp_index = list_part.lastIndexOf(',');
			if(tmp_index > -1){
				list = list_part.substring(tmp_index+1) + list;
				list_part = list_part.substring(0,tmp_index+1);
			}
		}

		result = this.create(name+'_'+part, list_part, days);
		part++;

		if(!result) break;
	}

	this.create(name+'_parts', part-1);
	
	while(part <= part_count){
		this.erase(name+'_'+part);
		part++;
	}
},

createJSON: function(name,value,days){
	var value_array = new Array();
	for(var key in value){
		if(!empty(value[key])) value_array.push(value[key]);
	}

	this.createArray(name,value_array,days);
},

read: function(name){
	if(typeof(this.cookies[name]) != 'undefined'){
		return this.cookies[name];
	} 
	else if(document.cookie.indexOf(name) != -1){
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for(var i=0;i < ca.length;i++) {
			var c = ca[i];
			while (c.charAt(0)==' ') c = c.substring(1,c.length);
			if(c.indexOf(nameEQ) == 0)	return this.cookies[name] = c.substring(nameEQ.length,c.length);
		}
	}

return null;
},

readArray: function(name){
	var list = "";
	var list_part = "";
	var part = 1;

	var part_count = parseInt(this.read(name+'_parts'),10);
	if(is_null(part_count)) part_count = 1;

//	reading all parts of selected list
	while(part <= (part_count+1)){
		if(!is_null(list_part))	list += list_part;

		list_part = this.read(name+'_'+part);
		part++;
	}

	var range = list.split(',');

return range;
},

readJSON: function(name){
	var value_json = {};
	var value_array = this.readArray(name);
	for(var i=0; i < value_array.length; i++){
		if(isset(i, value_array)) value_json[value_array[i]] = value_array[i];
	}

return value_json;
},

printall: function(){
	var allCookies = document.cookie.split('; ');
	for (var i=0;i<allCookies.length;i++) {
		var cookiePair = allCookies[i].split('=');
		
		SDI("[" + cookiePair[0] + "] is " + cookiePair[1]); // assumes print is already defined
	}
},

erase: function(name){
	this.create(name,'',-1);
	this.cookies[name] = undefined;
},

eraseArray: function(name){
	var part_count = parseInt(this.read('cb_'+name+'_parts'), 10);
	if(!is_null(part_count)){
		for(var i = 0; i < part_count; i++){
			this.erase('cb_'+name+'_'+i);
		}
		this.erase('cb_'+name+'_parts');
	}
},

eraseArrayByPattern: function(pattern){

	for(var name in this.cookies) {
		if(!isset(name, this.cookies) || empty(this.cookies[name])) continue;

		if(name.indexOf('cb_'+pattern) == -1){
			this.erase(name);
		}
	}
}
}