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

//------------------------------------------------------
//					USERS JS MENU 
//------------------------------------------------------
//var menu_usrgrp_all 	= new Array();
//var menu_usrgrp_gui 	= new Array();
//var menu_usrgrp_status 	= new Array();

function create_user_menu(e,userid,usr_grp_all_in,usr_grp_gui_in,usr_grp_status_in){
	if(!e) var e = window.event;

// ALL GROUPS
	var grp_add_to = new Array('Add to',null,null,{'outer' : ['pum_o_submenu'],'inner' : ['pum_i_submenu']});
	grp_add_to.push(['Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
	
	var grp_rmv_frm = new Array('Remove from',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
	grp_rmv_frm.push(['Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
	
// add to
	for(var i=0; i < menu_usrgrp_all.length; i++){
		if((typeof(menu_usrgrp_all[i]) != 'undefined') && !empty(menu_usrgrp_all[i])){
			var row = menu_usrgrp_all[i];
			var menu_row = new Array(row.name,'users.php?config=0&form=update&grpaction=1&userid='+userid+'&usrgrpid='+row.usrgrpid);
			grp_add_to.push(menu_row);
		}
	}

// remove from
	for(var i=0; i < usr_grp_all_in.length; i++){
		if((typeof(usr_grp_all_in[i]) != 'undefined') && !empty(usr_grp_all_in[i])){
			var row = usr_grp_all_in[i];
			var menu_row = new Array(row.name,'users.php?config=0&form=update&grpaction=0&userid='+userid+'&usrgrpid='+row.usrgrpid);
			grp_rmv_frm.push(menu_row);
		}
	}
	
// GUI ACCESS GROUPS
	var grp_gui_add_to = new Array('Add to',null,null,{'outer' : ['pum_o_submenu'],'inner' : ['pum_i_submenu']});
	grp_gui_add_to.push(['Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
	
	var grp_gui_rmv_frm = new Array('Remove from',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
	grp_gui_rmv_frm.push(['Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
	
// add to
	for(var i=0; i < menu_usrgrp_gui.length; i++){
		if((typeof(menu_usrgrp_gui[i]) != 'undefined') && !empty(menu_usrgrp_gui[i])){
			var row = menu_usrgrp_gui[i];
			var menu_row = new Array(row.name,'users.php?config=0&form=update&grpaction=1&userid='+userid+'&usrgrpid='+row.usrgrpid);
			grp_gui_add_to.push(menu_row);
		}
	}

// remove from
	for(var i=0; i < usr_grp_gui_in.length; i++){
		if((typeof(usr_grp_all_in[i]) != 'undefined') && !empty(usr_grp_gui_in[i])){
			var row = usr_grp_gui_in[i];
			var menu_row = new Array(row.name,'users.php?config=0&form=update&grpaction=0&userid='+userid+'&usrgrpid='+row.usrgrpid);
			grp_gui_rmv_frm.push(menu_row);
		}
	}

// DISABLED STATUS GROUPS
	var grp_status_add_to = new Array('Add to',null,null,{'outer' : ['pum_o_submenu'],'inner' : ['pum_i_submenu']});
	grp_status_add_to.push(['Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
	
	var grp_status_rmv_frm = new Array('Remove from',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
	grp_status_rmv_frm.push(['Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
	
// add to
	for(var i=0; i < menu_usrgrp_status.length; i++){
		if((typeof(menu_usrgrp_status[i]) != 'undefined') && !empty(menu_usrgrp_status[i])){
			var row = menu_usrgrp_status[i];
			var menu_row = new Array(row.name,'users.php?config=0&form=update&grpaction=1&userid='+userid+'&usrgrpid='+row.usrgrpid);
			grp_status_add_to.push(menu_row);
		}
	}

// remove from
	for(var i=0; i < usr_grp_status_in.length; i++){
		if((typeof(usr_grp_status_in[i]) != 'undefined') && !empty(usr_grp_status_in[i])){
			var row = usr_grp_status_in[i];
			var menu_row = new Array(row.name,'users.php?config=0&form=update&grpaction=0&userid='+userid+'&usrgrpid='+row.usrgrpid);
			grp_status_rmv_frm.push(menu_row);
		}
	}
//['&lt;span class=&quot;red&quot;&gt;Disabled users&lt;/span&gt;','users.php?config=0&form=update&grpaction=1&userid=2&usrgrpid=9']
	var grp_menu = new Array(
							Array('Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}),
								grp_add_to,
								grp_rmv_frm,
							Array('GUI access',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}),
								grp_gui_add_to,
								grp_gui_rmv_frm,
							Array('Status disabled',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}),
								grp_status_add_to,
								grp_status_rmv_frm
					);

//to create a copy of array, but not references!!!!
//alert(id+' : '+dashboard_menu[id]);



//alert(dashboard_menu[id]);
	show_popup_menu(e,grp_menu,280);// JavaScript Document
}
//---------------------------------------------------------------


//------------------------------------------------------
//					HOSTS JS MENU 
//------------------------------------------------------
//var menu_hstgrp_all 	= new Array();

function create_host_menu(e,hostid,hst_grp_all_in){
	if(!e) var e = window.event;

// ALL GROUPS
	var grp_add_to = new Array('Add to group',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
	grp_add_to.push(['Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
	
	var grp_rmv_frm = new Array('Remove from group',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
	grp_rmv_frm.push(['Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
	
// add to
	for(var i=0; i < menu_hstgrp_all.length; i++){
		if((typeof(menu_hstgrp_all[i]) != 'undefined') && !empty(menu_hstgrp_all[i])){
			var row = menu_hstgrp_all[i];
			var menu_row = new Array(row.name,'?add_to_group='+row.groupid+'&hostid='+hostid);
			grp_add_to.push(menu_row);
		}
	}

// remove from
	for(var i=0; i < hst_grp_all_in.length; i++){
		if((typeof(hst_grp_all_in[i]) != 'undefined') && !empty(hst_grp_all_in[i])){
			var row = hst_grp_all_in[i];
			var menu_row = new Array(row.name,'?delete_from_group='+row.groupid+'&hostid='+hostid);
			grp_rmv_frm.push(menu_row);
		}
	}
	

	var grp_menu = new Array(
							['Show',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
								['Items','items.php?hostid='+hostid,{'tw' : ''}],
								['Triggers','triggers.php?hostid='+hostid,{'tw' : ''}],
								['Graphs','graphs.php?hostid='+hostid,{'tw' : ''}],
							['Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
								grp_add_to,
								grp_rmv_frm
							);

//to create a copy of array, but not references!!!!
//alert(id+' : '+dashboard_menu[id]);



//alert(dashboard_menu[id]);
	show_popup_menu(e,grp_menu,280);// JavaScript Document
}
/*
show_popup_menu(event,new Array(['Show',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
							['Items','items.php?hostid=10017',{'tw' : ''}],
							['Triggers','triggers.php?hostid=10017',{'tw' : ''}],
							['Graphs','graphs.php?hostid=10017',{'tw' : ''}],
						['Groups',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
							['Add to group',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']},
								['Linux servers','?&amp;add_to_group=2&amp;hostid=10017'],
								['Templates','?&amp;add_to_group=1&amp;hostid=10017'],
								['Windows servers','?&amp;add_to_group=3&amp;hostid=10017']
							],
							['Delete from group',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']},
								['Test Group','?&amp;delete_from_group=5&amp;hostid=10017'],
								['ZABBIX Servers','?&amp;delete_from_group=4&amp;hostid=10017']
							]
						),null);
*/


//------------------------------------------------------
//					DASHBOARD JS MENU 
//------------------------------------------------------

function create_dashboard_menu(e,id){
	if(!e) var e = window.event;
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