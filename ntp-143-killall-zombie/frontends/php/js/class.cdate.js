// JavaScript Document
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
// Title: CDate class
// Author: Aly

// [!CDATA[
// Should be commented!!!
//var PHP_TZ_OFFSET = 0;			// PHP Server TimeZone offset (seconds)

var CDate = Class.create();

CDate.prototype = {
server: 0,				// getTime uses clients :0, or servers time :1
tzDiff: 0,				// server and client TZ diff
clientDate: null,		// clients(JS, Browser) date object
serverDate: null,		// servers(PHP, Unix) date object

initialize: function(){
	if(arguments.length > 0) this.clientDate = new Date(arguments[0]);
	else this.clientDate = new Date();

	var clientTZOffset = this.clientDate.getTimezoneOffset() * -60;
//	if(typeof(PHP_TZ_OFFSET) == 'undefined') PHP_TZ_OFFSET = clientTZOffset;
	
	this.tzDiff = clientTZOffset - PHP_TZ_OFFSET;

	this.serverDate = new Date(this.clientDate.getTime() - (this.tzDiff * 1000));
},

getMilliseconds: function(){
	return this.serverDate.getMilliseconds();
},

getSeconds: function(){
	return this.serverDate.getSeconds();
},

getMinutes: function(){
	return this.serverDate.getMinutes();
},

getHours: function(){
	return this.serverDate.getHours();
},

getDay: function(){
	return this.serverDate.getDay();
},

getMonth: function(){
	return this.serverDate.getMonth();
},

getYear: function(){
	return this.serverDate.getYear();
},

getFullYear: function(){
	return this.serverDate.getFullYear();
},

getDate: function(){
	return this.serverDate.getDate();
},

getTime: function(){
	if(this.server == 1){
		return this.serverDate.getTime() + (this.tzDiff * 1000);
	}
	else{
		return this.clientDate.getTime();
	}
},

getTimezoneOffset: function(){
	return -parseInt(PHP_TZ_OFFSET/60);
},

setMilliseconds: function(arg){
	this.server = 1;

	this.serverDate.setMilliseconds(arg);
	this.clientDate.setMilliseconds(arg);
},

setSeconds: function(arg){
	this.server = 1;

	this.serverDate.setSeconds(arg);
	this.clientDate.setSeconds(arg);
},

setMinutes: function(arg){
	this.server = 1;
	
	this.serverDate.setMinutes(arg);
	this.clientDate.setMinutes(arg);
},

setHours: function(arg){
	this.server = 1;

	this.serverDate.setHours(arg);
	this.clientDate.setHours(arg);
},

setDate: function(arg){
	this.server = 1;

	this.serverDate.setDate(arg);
	this.clientDate.setDate(arg);
},

setMonth: function(arg){
	this.server = 1;

	this.serverDate.setMonth(arg);
	this.clientDate.setMonth(arg);
},

setFullYear: function(arg){
	this.server = 1;

	this.serverDate.setFullYear(arg);
	this.clientDate.setFullYear(arg);
},

setTime: function(arg){
	this.server = 0;
	arg = parseInt(arg, 10);
	
	this.serverDate.setTime(arg - (this.tzDiff * 1000));
	this.clientDate.setTime(arg);
}
}