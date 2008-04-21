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

	new Ajax.Request("dashboard.php?output=ajax",
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(resp){ },//alert(resp.responseText);
						'onFailure': function(){ document.location = 'dashboard.php?'+Object.toQueryString(params); }
					}
	);

}

function create_menu(e,id){
	if (!e) var e = window.event;
	id='menu_'+id;

	var dbrd_menu = new Array();
	
//to create a copy of array, but not references!!!!
//alert(id+' : '+dashboard_menu[id]);
	for(var i=0; i < dashboard_menu[id].length; i++){
		if((typeof(dashboard_menu[id][i]) != 'undefined') && !empty(dashboard_menu[id][i]))
			dbrd_menu[i] = dashboard_menu[id][i].clone();
	}

	for(var i=0; i < dashboard_submenu[id].length; i++){
		if((typeof(dashboard_submenu[id][i]) != 'undefined') && !empty(dashboard_submenu[id][i])){
			var row = dashboard_submenu[id][i];
			var menu_row = new Array(row.name,"javascript: rm4favorites('"+row.favobj+"','"+row.favid+"','"+i+"');");
			dbrd_menu[dbrd_menu.length-1].push(menu_row);
		}
	}
//alert(dashboard_menu[id]);
	show_popup_menu(e,dbrd_menu,280);// JavaScript Document
}


function getTimeFormated(timestamp){
	var dt = new Date();

	var hours = dt.getHours();
	var minutes = dt.getMinutes();
	var seconds = dt.getSeconds();
	var str = '['+hours+':'+minutes+':'+seconds+']';

return str;
}