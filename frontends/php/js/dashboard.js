// JavaScript Document

function add2favorites(){

	var fav_form = document.getElementById('fav_form');
	if(!fav_form) throw "Object not found.";
	
	var favobj = fav_form.favobj.value;
	var favid = fav_form.favid.value;;

	if(empty(favid)) return;
	
	var params = {
		'favobj': 	favobj,
		'favid': 	favid,
		'action':	'add'
	}

	new Ajax.Request("dashboard.php?output=ajax",
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(resp){ },//alert(resp.responseText);
						'onFailure': function(){ document.location = 'dashboard.php?'+Object.toQueryString(params); }
					}
	);
//	json.onetime('dashboard.php?output=json&'+Object.toQueryString(params));
}

function rm4favorites(favobj,favid,menu_rowid){
//	alert(favobj+','+favid+','+menu_rowid);

	if(!isset(favobj) || !isset(favid)) throw "No agruments sent to function [rm4favorites()].";
/*
	var	id='menu_'+favobj;

	var tmp_menu = new Array();
	for(var i=0; i < dashboard_submenu[id].length; i++){
		if(isset(dashboard_submenu[id][i]) && (i!=menu_rowid)){
			tmp_menu.push(dashboard_submenu[id][i]);

		}
	}
	dashboard_submenu[id] = tmp_menu;
*/
	var params = {
		'favobj': 	favobj,
		'favid': 	favid,
		'favcnt':	menu_rowid,
		'action':	'remove'
	}

	new Ajax.Request("dashboard.php?output=ajax",
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(resp){ },
						'onFailure': function(){ document.location = 'dashboard.php?'+Object.toQueryString(params); }
					}
	);

//	json.onetime('dashboard.php?output=json&'+Object.toQueryString(params));
}

function change_hat_state(icon, divid){
	if(!isset(icon) || !isset(divid)) throw "Function [change_hat_state()] awaits exactly 2 arguments.";

	deselectAll(); 
	var hat_state = ShowHide(divid); 
	switchElementsClass(icon,"arrowup","arrowdown");
	
	if(false === hat_state) return false;
	
	var params = {
		'favobj': 	'hat',
		'favid': 	divid,
		'state':	hat_state
	}
	
	new Ajax.Request("dashboard.php?output=ajax",
					{
						'method': 'post',
						'parameters':	params,
						'onFailure': function(){	document.location = 'dashboard.php?'+Object.toQueryString(params);}
					}
	);
}

function create_menu(e,id){
	if (!e) var e = window.event;
	id='menu_'+id;

	var dbrd_menu = new Array();
	
//to create a copy of array, but not references!!!!
	for(var i=0; i < dashboard_menu[id].length; i++){
		if(isset(dashboard_menu[id][i]) && !empty(dashboard_menu[id][i]))
			dbrd_menu[i] = dashboard_menu[id][i].clone();
	}

	for(var i=0; i < dashboard_submenu[id].length; i++){
		if(isset(dashboard_submenu[id][i]) && !empty(dashboard_submenu[id][i])){
			var row = dashboard_submenu[id][i];
			var menu_row = new Array(row.name,"javascript: rm4favorites('"+row.favobj+"','"+row.favid+"','"+i+"');");
			dbrd_menu[dbrd_menu.length-1].push(menu_row);
		}
	}
//alert(dashboard_menu[id]);
	show_popup_menu(e,dbrd_menu,280);// JavaScript Document
}