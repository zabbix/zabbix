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
tmpDate: null,			// inner usage

// DEBUG
debug_status: 	0,			// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: 	'',			// debug string
debug_prev:		'',			// don't log repeated fnc


initialize: function(){
	this.debug('initialize');
//--
	this.tmpDate = new Date();

	if(arguments.length > 0) this.clientDate = new Date(arguments[0]);
	else this.clientDate = new Date();

	this.calcTZdiff(this.clientDate.getTime());

	this.serverDate = new Date(this.clientDate.getTime() - (this.tzDiff * 1000));
},

// ====== CUSTOM =============================================
calcTZdiff: function(time){
	this.debug('calcTZdiff');
//--

	if(typeof(time) != 'undefined'){
		this.tmpDate.setTime(time);
		var ddTZOffset = this.tmpDate.getTimezoneOffset() * -60;
	}
	else{
		var ddTZOffset = this.serverDate.getTimezoneOffset() * -60;
	}

	this.tzDiff = ddTZOffset - PHP_TZ_OFFSET;
},

getZBXDate: function(){
//	this.debug('getZBXDate');
//--
	var thedate = new Array();
	thedate[0] = this.serverDate.getDate();
	thedate[1] = this.serverDate.getMonth()+1;
	thedate[2] = this.serverDate.getFullYear();
	thedate[3] = this.serverDate.getHours();
	thedate[4] = this.serverDate.getMinutes();
	thedate[5] = this.serverDate.getSeconds();

	for(i = 0; i < thedate.length; i++){
		if((thedate[i]+'').length < 2) thedate[i] = '0'+thedate[i];
	}

return ''+thedate[2]+thedate[1]+thedate[0]+thedate[3]+thedate[4]+thedate[5];
},

setZBXDate: function(strdate){
//	this.debug('getZBXDate');
//--
	this.server = 1;

	var theDate = {
		'Y': strdate.toString().substr(0,4),
		'm': strdate.toString().substr(4,2),
		'd': strdate.toString().substr(6,2),
		'H': strdate.toString().substr(8,2),
		'i': strdate.toString().substr(10,2),
		's': strdate.toString().substr(12,2)
	};

	this.serverDate.setFullYear(theDate.Y);
	this.serverDate.setMonth(theDate.m - 1);
	this.serverDate.setDate(theDate.d);
	this.serverDate.setHours(theDate.H);
	this.serverDate.setMinutes(theDate.i);
	this.serverDate.setSeconds(theDate.s);
	this.serverDate.setMilliseconds(0);
	this.calcTZdiff();

return this.getTime();
},

getFormattedDate: function(){
//	this.debug('getFormattedDate');
//--
	var fDate = this.getFullYear()+'-'+(this.getMonth()+1)+'-'+this.getDate();
	fDate+= ' '+this.getHours()+':'+this.getMinutes()+':'+this.getSeconds();

	fDate+= ' '+tzOffsetHours+':'+(this.tzOffset / 3600);

return fDate;
},

// ==============================================================

toString: function(){
	this.debug('toString');
//--

	return this.serverDate.toString();
},

parse: function(arg){
	this.debug('parse');
//--
	this.server = 1;

	this.serverDate.setTime(Date.parse(arg));
	this.calcTZdiff();

return this.getTime();
},

getTimezoneOffset: function(){
//	this.debug('getTimezoneOffset');
//--
	return parseInt(-PHP_TZ_OFFSET / 60);
},

getMilliseconds: function(){
//	this.debug('getMilliseconds');
//--
	return this.serverDate.getMilliseconds();
},

getSeconds: function(){
//	this.debug('getSeconds');
//--
	return this.serverDate.getSeconds();
},

getMinutes: function(){
//	this.debug('getMinutes');
//--
	return this.serverDate.getMinutes();
},

getHours: function(){
//	this.debug('getHours');
//--
	return this.serverDate.getHours();
},

getDay: function(){
//	this.debug('getDay');
//--
	return this.serverDate.getDay();
},

getMonth: function(){
//	this.debug('getMonth');
//--
	return this.serverDate.getMonth();
},

getYear: function(){
//	this.debug('getYear');
//--
	return this.serverDate.getYear();
},

getFullYear: function(){
//	this.debug('getFullYear');
//--
	return this.serverDate.getFullYear();
},

getDate: function(){
//	this.debug('getDate');
//--
	return this.serverDate.getDate();
},

getTime: function(){
//	this.debug('getTime');
//--

	if(this.server == 1)
		return this.serverDate.getTime() + (this.tzDiff * 1000);
	else
		return this.clientDate.getTime();
},

setMilliseconds: function(arg){
	this.debug('setMilliseconds');
//--

	this.server = 1;

	this.serverDate.setMilliseconds(arg);
	this.calcTZdiff();
},

setSeconds: function(arg){
	this.debug('setSeconds');
//--

	this.server = 1;

	this.serverDate.setSeconds(arg);
	this.calcTZdiff();
},

setMinutes: function(arg){
	this.debug('setMinutes');
//--

	this.server = 1;
	
	this.serverDate.setMinutes(arg);;
	this.calcTZdiff();
},

setHours: function(arg){
	this.debug('setHours');
//--

	this.server = 1;

	this.serverDate.setHours(arg);
	this.calcTZdiff();
},

setDate: function(arg){
	this.debug('setDate');
//--

	this.server = 1;

	this.serverDate.setDate(arg);
	this.calcTZdiff();
},

setMonth: function(arg){
	this.debug('setMonth');
//--

	this.server = 1;

	this.serverDate.setMonth(arg);
	this.calcTZdiff();
},

setFullYear: function(arg){
	this.debug('setFullYear');
//--

	this.server = 1;

	this.serverDate.setFullYear(arg);
	this.calcTZdiff();
},

setTime: function(arg){
	this.debug('setTime');
//--

	this.server = 0;
	arg = parseInt(arg, 10);

	this.calcTZdiff(arg);

	this.serverDate.setTime(arg - (this.tzDiff * 1000));
	this.clientDate.setTime(arg);

//SDI(this.getFormattedDate());
},


// ===== DEBUG ===============
debug: function(fnc_name, id){
	if(this.debug_status){
		var str = 'CDate.'+fnc_name;
		if(typeof(id) != 'undefined') str+= ' :'+id;

		if(this.debug_prev == str) return true;

		this.debug_info += str;
		if(this.debug_status == 2){
			SDI(str);
		}

		this.debug_prev = str;
	}
}
}