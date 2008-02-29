// JavaScript Document
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

function change_hat_state(icon, divid){
	if((typeof(icon) == 'undefined') || (typeof(divid) == 'undefined')) throw "Function [change_hat_state()] awaits exactly 2 arguments.";

	if(typeof(Ajax) == 'undefined'){
		throw("Prototype.js lib is required!");
		return false;
	}

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


// DOM obj update class
// Author: Aly
var updater = {
objlist:		new Array(),			// list of objects
optlist :		new Array(),			// object params, list
interval:		10,						// update interval in sec

	setObj4Update: function(id,frequency,url,params){
		var obj = document.getElementById(id);
		if((typeof(obj) == 'undefined')) return false; 
	
		var obj4update = {
			'id': 		id,
			'url': 		url,
			'params': 	params,
			'interval': frequency,
			'lastupdate': 0
		}
		
		if(typeof(this.optlist[id]) == 'undefined'){
			this.objlist.push(id);
		}
		this.optlist[id] = obj4update;
	},
	
	check4Update: function(){
		if(this.objlist.length > 0){
			var dt = new Date();
			var now = parseInt(dt.getTime()/1000);
			
			for(var i=0; i < this.objlist.length; i++){
				if((typeof(this.optlist[this.objlist[i]]) != 'undefined') && !empty(this.optlist[this.objlist[i]])){
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
				'evalScripts': true,
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