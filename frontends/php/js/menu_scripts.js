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
**/

//------------------------------------------------------
//					DASHBOARD JS MENU 
//------------------------------------------------------

function create_page_menu(e,id){
	if(!e) var e = window.event;
	id='menu_'+id;

	var dbrd_menu = new Array();
	
//to create a copy of array, but not references!!!!
//alert(id+' : '+page_menu[id]);
	for(var i=0; i < page_menu[id].length; i++){
		if((typeof(page_menu[id][i]) != 'undefined') && !empty(page_menu[id][i]))
			dbrd_menu[i] = page_menu[id][i].clone();
	}

	for(var i=0; i < page_submenu[id].length; i++){
		if((typeof(page_submenu[id][i]) != 'undefined') && !empty(page_submenu[id][i])){
			var row = page_submenu[id][i];
			var menu_row = new Array(row.name,"javascript: rm4favorites('"+row.favobj+"','"+row.favid+"','"+i+"');");
			dbrd_menu[dbrd_menu.length-1].push(menu_row);
		}
	}
//alert(page_menu[id]);
	show_popup_menu(e,dbrd_menu,280);// JavaScript Document
}

//------------------------------------------------------
//					TRIGGERS JS MENU 
//------------------------------------------------------

function create_mon_trigger_menu(e, args, items){
	var tr_menu = new Array(['Triggers',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
								['Events','events.php?triggerid='+args[0].triggerid+'&nav_time='+args[0].lastchange,null]);

	if((args.length > 1) && !is_null(args[1])) tr_menu.push(args[1]);
	if((args.length > 1) && !is_null(args[2])) tr_menu.push(args[2]);

	tr_menu.push(['Simple graphs',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);

//	for(var i=0; i < items.length; i++){
	for(var itemid in items){
		if(typeof(items[itemid]) != 'undefined'){
			tr_menu.push([items[itemid].description,
									'history.php?action='+items[itemid].action+'&itemid='+items[itemid].itemid,
									null]);
		}
	}

//to create a copy of array, but not references!!!!
//alert(id+' : '+page_menu[id]);

//alert(page_menu[id]);
	show_popup_menu(e,tr_menu,280);// JavaScript Document
}
