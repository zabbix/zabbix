// JavaScript Document
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
			var menu_row = new Array(row.name,"users.php?config=0&form=update&grpaction=1&userid="+userid+"&usrgrpid="+row.usrgrpid);
			grp_add_to.push(menu_row);
		}
	}

// remove from
	for(var i=0; i < usr_grp_all_in.length; i++){
		if((typeof(usr_grp_all_in[i]) != 'undefined') && !empty(usr_grp_all_in[i])){
			var row = usr_grp_all_in[i];
			var menu_row = new Array(row.name,"users.php?config=0&form=update&grpaction=0&userid="+userid+"&usrgrpid="+row.usrgrpid);
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
			var menu_row = new Array(row.name,"users.php?config=0&form=update&grpaction=1&userid="+userid+"&usrgrpid="+row.usrgrpid);
			grp_gui_add_to.push(menu_row);
		}
	}

// remove from
	for(var i=0; i < usr_grp_gui_in.length; i++){
		if((typeof(usr_grp_all_in[i]) != 'undefined') && !empty(usr_grp_gui_in[i])){
			var row = usr_grp_gui_in[i];
			var menu_row = new Array(row.name,"users.php?config=0&form=update&grpaction=0&userid="+userid+"&usrgrpid="+row.usrgrpid);
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
			var menu_row = new Array(row.name,"users.php?config=0&form=update&grpaction=1&userid="+userid+"&usrgrpid="+row.usrgrpid);
			grp_status_add_to.push(menu_row);
		}
	}

// remove from
	for(var i=0; i < usr_grp_status_in.length; i++){
		if((typeof(usr_grp_status_in[i]) != 'undefined') && !empty(usr_grp_status_in[i])){
			var row = usr_grp_status_in[i];
			var menu_row = new Array(row.name,"users.php?config=0&form=update&grpaction=0&userid="+userid+"&usrgrpid="+row.usrgrpid);
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