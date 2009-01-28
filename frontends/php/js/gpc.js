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

// Title: cookies class
// Description: to manipulate cookies on client side
// Author: Aly
var cookie ={
cookies: new Array(),

init: function () {
	var allCookies = document.cookie.split('; ');
	for (var i=0;i<allCookies.length;i++) {
		var cookiePair = allCookies[i].split('=');
		this.cookies[cookiePair[0]] = cookiePair[1];
	}
},

create: function (name,value,days) {
	if(days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else{ 
		var expires = "";
	}
	
	document.cookie = name+"="+value+expires+"; path=/";
	this.cookies[name] = value;
},

read : function(name){
	if(typeof(this.cookies[name]) != 'undefined'){
		return this.cookies[name];
	} 
	else {
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

printall: function() {
	var allCookies = document.cookie.split('; ');
	for(var i=0;i<allCookies.length;i++){
		var cookiePair = allCookies[i].split('=');
		
		alert("[" + cookiePair[0] + "] is " + cookiePair[1]); // assumes print is already defined
	}
},

erase: function (name) {
	this.create(name,'',-1);
	this.cookies[name] = undefined;
}
}

cookie.init();



// Title: url manipulation class
// Author: Aly
var url = Class.create();

url.prototype = {
url: 		'',		//	actually, it's depricated/private variable 
port:		 -1,
host: 		'',
protocol: 	'',
username:	'',
password:	'',
filr:		'',
reference:	'',
path:		'',
query:		'',
arguments:  {},

initialize: function(url){
	this.url=unescape(url);
	
	this.query=(this.url.indexOf('?')>=0)?this.url.substring(this.url.indexOf('?')+1):'';
	if(this.query.indexOf('#')>=0) this.query=this.query.substring(0,this.query.indexOf('#'));
	
	var protocolSepIndex=this.url.indexOf('://');
	if(protocolSepIndex>=0){
		this.protocol=this.url.substring(0,protocolSepIndex).toLowerCase();
		this.host=this.url.substring(protocolSepIndex+3);
		if(this.host.indexOf('/')>=0) this.host=this.host.substring(0,this.host.indexOf('/'));
		var atIndex=this.host.indexOf('@');
		if(atIndex>=0){
			var credentials=this.host.substring(0,atIndex);
			var colonIndex=credentials.indexOf(':');
			if(colonIndex>=0){
				this.username=credentials.substring(0,colonIndex);
				this.password=credentials.substring(colonIndex);
			}
			else{
				this.username=credentials;
			}
			this.host=this.host.substring(atIndex+1);
		}
		
		var host_ipv6 = this.host.indexOf(']');
		if(host_ipv6>=0){
			if(host_ipv6 < (this.host.length-1)){
				host_ipv6++;
				var host_less = this.host.substring(host_ipv6);

				var portColonIndex=host_less.indexOf(':');
				if(portColonIndex>=0){
					this.port=host_less.substring(portColonIndex+1);
					this.host=this.host.substring(0,host_ipv6);
				}
			}
		}
		else{
			var portColonIndex=this.host.indexOf(':');
			if(portColonIndex>=0){
				this.port=this.host.substring(portColonIndex+1);
				this.host=this.host.substring(0,portColonIndex);
			}
		}
		this.file=this.url.substring(protocolSepIndex+3);
		this.file=this.file.substring(this.file.indexOf('/'));
	}
	else{
		this.file=this.url;
	}
	
	if(this.file.indexOf('?')>=0) this.file=this.file.substring(0, this.file.indexOf('?'));

	var refSepIndex=url.indexOf('#');
	if(refSepIndex>=0){
		this.file=this.file.substring(0,refSepIndex);
		this.reference=this.url.substring(this.url.indexOf('#'));
	}
	this.path=this.file;
	if(this.query.length>0) this.file+='?'+this.query;
	if(this.reference.length>0) this.file+='#'+this.reference;
	if(this.query.length > 0)	this.formatArguments();
	
	var sid = cookie.read('zbx_sessionid');
	this.setArgument('sid', sid.substring(16));
},


formatQuery: function(){
	if(this.arguments.lenght < 1) return;
	
	var query = '';
	for(var key in this.arguments){
		if(typeof(this.arguments[key]) != 'undefined'){
			query+=key+'='+this.arguments[key]+'&';
		}
	}
	this.query = query.substring(0,query.length-1);
},

formatArguments: function(){
	var args=this.query.split('&');
	var keyval='';

	if(args.length<1) return;
	
	for(i=0; i<args.length; i++){
		keyval = args[i].split('=');
		this.arguments[keyval[0]] = (keyval.length>1)?keyval[1]:'';
	}
},

setArgument: function(key,value){
	this.arguments[key] = value;
	this.formatQuery();
},

getArgument: function(key){
	if(typeof(this.arguments[key]) != 'undefined') return this.arguments[key];
	else return null;
},

getArguments: function(){
	return this.arguments;
},

getUrl: function(){
	var uri = (this.protocol.length > 0)?(this.protocol+'://'):'';
	uri +=  encodeURI((this.username.length > 0)?(this.username):'');
	uri +=  encodeURI((this.password.length > 0)?(':'+this.password):'');
	uri +=  (this.host.length > 0)?(this.host):'';
	uri +=  (this.port.length > 0)?(':'+this.port):'';
	uri +=  encodeURI((this.path.length > 0)?(this.path):'');
	uri +=  encodeURI((this.query.length > 0)?('?'+this.query):'');
	uri +=  encodeURI((this.reference.length > 0)?('#'+this.reference):'');
//	alert(uri.getProtocol()+' : '+uri.getHost()+' : '+uri.getPort()+' : '+uri.getPath()+' : '+uri.getQuery());
return uri;
},

setPort: function(port){
	this.port = port;
},

getPort: function(){ 
	return this.port;
},

setQuery: function(query){ 
	this.query = query;
	if(this.query.indexOf('?')>=0){
		this.query= this.query.substring(this.query.indexOf('?')+1);
	}
	
	this.formatArguments();
	
	var sid = cookie.read('zbx_sessionid');
	this.setArgument('sid', sid.substring(16));
},

getQuery: function(){ 
	return this.query;
},

/* Returns the protocol of this URL, i.e. 'http' in the url 'http://server/' */
getProtocol: function(){
	return this.protocol;
},

setProtocol: function(protocol){
	this.protocol = protocol;
},
/* Returns the host name of this URL, i.e. 'server.com' in the url 'http://server.com/' */
getHost: function(){
	return this.host;
},

setHost: function(host){
	this.host = host;
},

/* Returns the user name part of this URL, i.e. 'joe' in the url 'http://joe@server.com/' */
getUserName: function(){
	return this.username;
},

setUserName: function(username){
	this.username = username;
},

/* Returns the password part of this url, i.e. 'secret' in the url 'http://joe:secret@server.com/' */
getPassword: function(){
	return this.password;
},

setPassword: function(password){
	this.password = password;
},

/* Returns the file part of this url, i.e. everything after the host name. */
getFile: function(){
	return this.file;
},

setFile: function(file){
	this.file = file;
},

/* Returns the reference of this url, i.e. 'bookmark' in the url 'http://server/file.html#bookmark' */
getReference: function(){
	return this.reference;
},

setReference: function(reference){
	this.reference = reference;
},

/* Returns the file path of this url, i.e. '/dir/file.html' in the url 'http://server/dir/file.html' */
getPath: function(){
	return this.path;
},

setPath: function(path){
	this.path = path;
}
}