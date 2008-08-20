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
**/

function send_params(params){

	var uri = new url(location.href);
	new Ajax.Request(uri.getPath()+"?output=ajax",
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(resp){ },//alert(resp.responseText);
						'onFailure': function(){ document.location = uri.getPath()+'?'+Object.toQueryString(params); }
					}
	);
}


function setRefreshRate(id,interval){
	if(typeof(Ajax) == 'undefined'){
		throw("Prototype.js lib is required!");
		return false;
	}

	var params = {
		'favobj': 	'set_rf_rate',
		'favid': 	id,
		'favcnt':	interval
	}

	send_params(params);
}

function add2favorites(favobj,favid){
	if('undefined' == typeof(Ajax)){
		throw("Prototype.js lib is required!");
		return false;
	}

	if(typeof(favobj) == 'undefined'){
		var fav_form = document.getElementById('fav_form');
		if(!fav_form) throw "Object not found.";
		
		var favobj = fav_form.favobj.value;
		var favid = fav_form.favid.value;
	}
	
	if((typeof(favid) == 'undefined') || empty(favid)) return;
	
	var params = {
		'favobj': 	favobj,
		'favid': 	favid,
		'action':	'add'
	}
	
	send_params(params);
//	json.onetime('dashboard.php?output=json&'+Object.toQueryString(params));
}

function rm4favorites(favobj,favid,menu_rowid){
//	alert(favobj+','+favid+','+menu_rowid);
	if('undefined' == typeof(Ajax)){
		throw("Prototype.js lib is required!");
		return false;
	}

	if((typeof(favobj) == 'undefined') || (typeof(favid) == 'undefined')) 
		throw "No agruments sent to function [rm4favorites()].";

	var params = {
		'favobj': 	favobj,
		'favid': 	favid,
		'favcnt':	menu_rowid,
		'action':	'remove'
	}

	send_params(params);
//	json.onetime('dashboard.php?output=json&'+Object.toQueryString(params));
}

function change_hat_state(icon, divid){
	deselectAll(); 
	var hat_state = ShowHide(divid); 
	switchElementsClass(icon,"arrowup","arrowdown");

	if(false === hat_state) return false;
	
	var params = {
		'favobj': 	'hat',
		'favid': 	divid,
		'state':	hat_state
	}
	
	send_params(params);
}

function change_filter_state(icon, divid){
	deselectAll(); 
	var filter_state = ShowHide(divid); 
	switchElementsClass(icon,"filteropened","filterclosed");
	
	if(false === filter_state) return false;

	var params = {
		'favobj': 	'filter',
		'favid': 	divid,
		'state':	filter_state
	}
	
	send_params(params);	
}


function switch_mute(icon){
	deselectAll(); 
	var sound_state = switchElementsClass(icon,"iconmute","iconsound");

	if(false === sound_state) return false;
	sound_state = (sound_state == "iconmute")?1:0;

	var params = {
		'favobj': 	'sound',
		'state':	sound_state
	}
	
	send_params(params);
}