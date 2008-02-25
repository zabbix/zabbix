// JavaScript Document
function setRefreshRate(id,interval){
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
						'onSuccess': function(resp){ },//alert(resp.responseText);
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
//alert(id+' : '+dashboard_menu[id]);
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


// DOM obj update class
// Author: Aly
var updater = {
objlist:		new Array(),			// list of objects
optlist :		new Array(),			// object params, list
interval:		10,						// update interval in sec

setObj4Update: function(id,url,params,frequency){
	var obj = document.getElementById(id);
	if(!isset(obj)) return false; 

	var obj4update = {
		'id': 		id,
		'url': 		url,
		'params': 	params,
		'interval': frequency,
		'lastupdate': 0
	}
	
	if(!isset(this.optlist[id])){
		this.objlist.push(id);
	}
	this.optlist[id] = obj4update;
},

check4Update: function(){
	if(this.objlist.length > 0){
		var dt = new Date();
		var now = parseInt(dt.getTime()/1000);
		
		for(var i=0; i < this.objlist.length; i++){
			if(isset(this.optlist[this.objlist[i]]) && !empty(this.optlist[this.objlist[i]])){
//				alert(Math.abs(now - this.optlist[this.objlist[i]].lastupdate));
				if(this.optlist[this.objlist[i]].interval <= Math.abs(now - this.optlist[this.objlist[i]].lastupdate)){
					this.update(this.optlist[this.objlist[i]],now);
				}
			}
		}
	}
	setTimeout('updater.check4Update();',(this.interval*1000));
},

update: function(obj4update,time){
	new Ajax.Updater(obj4update.id, obj4update.url,
		{
			method: 'post',
			'parameters':	obj4update.params,
			'onSuccess': function(resp){ obj4update.lastupdate = time;},
			'onFailure': function(){ document.location = 'dashboard.php?'+Object.toQueryString(obj4update.params); }
		});	
}
}

function getTimeFormated(timestamp){
	var dt = new Date();

	var hours = dt.getHours();
	var minutes = dt.getMinutes();
	var seconds = dt.getSeconds();
	var str = '['+hours+':'+minutes+':'+seconds+']';

return str;
}