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
**/

var CSwitcher = Class.create({
switcherId : null,
switchers : {},
classOpened : 'filteropened',
classClosed : 'filterclosed',

initialize : function(id){
	this.switcherId = id;

	var mainSwitcher = $(this.switcherId);

	var eventHandler = this.showHide.bind(this);

	if(!is_null(mainSwitcher)){
		mainSwitcher.observe('click', eventHandler);

		var state_all = cookie.read(this.switcherId + '_all');
		if(!is_null(state_all) && (state_all == 1)){
			mainSwitcher.className = this.classOpened;
		}
	}

	var divs = $$('div[data-switcherid]');
	for(var i=0; i<divs.length; i++){
		if(!isset(i, divs)) continue;

		divs[i].observe('click', eventHandler);

		var switcherid = divs[i].getAttribute('data-switcherid');
		this.switchers[switcherid] = {
			'object' : divs[i],
			'state' : false,
			'dependentElems' : []
		};
	}

	var dependentElements = $$('tr[data-parentid]');
	for(var i=0; i<dependentElements.length; i++){
		if(!isset(i, dependentElements)) continue;

		var parentid = dependentElements[i].getAttribute('data-parentid');
		if(isset(parentid, this.switchers)){
			this.switchers[parentid]['dependentElems'].push(dependentElements[i]);
		}
	}

	var to_change;
	if((to_change = cookie.readArray(this.switcherId)) != null){
		for(var i=0; i<to_change.length; i++){
			if(isset(i, to_change) && isset(to_change[i], this.switchers)){
				var switcherid = to_change[i];
				this.switchers[switcherid]['object'].className = this.classOpened;
				this.switchers[switcherid]['state'] = 1;

				for(var j = 0; j < this.switchers[switcherid]['dependentElems'].length; j++){
					if(!isset(j, this.switchers[switcherid]['dependentElems'])) continue;

					this.switchers[switcherid]['dependentElems'][j].style.display = '';
				}
			}
		}
	}
},

showHide : function(e){
	PageRefresh.restart();

	var obj = Event.element(e);
	var switcherid = obj.getAttribute('data-switcherid');
	var id = obj.getAttribute('id');

	if(obj.className == this.classClosed){
		var state = 1;
		var newClassName = this.classOpened;
		var displayStyle = '';
	}
	else{
		var state = 0;
		var newClassName = this.classClosed;
		var displayStyle = 'none';
	}

	if(id == this.switcherId){
		cookie.create(this.switcherId+'_all', state);
		obj.className = newClassName;

		for(var i in this.switchers){
			if(this.switchers[i]['state'] != state){
				this.switchers[i]['object'].className = newClassName;
				this.switchers[i]['state'] = state;

				for(var j = 0; j < this.switchers[i]['dependentElems'].length; j++){
					if(empty(this.switchers[i]['dependentElems'][j])) continue;

					this.switchers[i]['dependentElems'][j].style.display = displayStyle;
				}
			}
		}
	}
	else if(isset(switcherid, this.switchers)){
		this.switchers[switcherid]['object'].className = newClassName;
		this.switchers[switcherid]['state'] = state;

		for(var j = 0; j < this.switchers[switcherid]['dependentElems'].length; j++){
			if(empty(this.switchers[switcherid]['dependentElems'][j])) continue;

			this.switchers[switcherid]['dependentElems'][j].style.display = displayStyle;
		}
	}

	this.storeCookie();
},

storeCookie : function(){
//	cookie.erase(this.switcherName);

	var storeArray = new Array();

	for(var i in this.switchers){
		if(this.switchers[i]['state'] == 1){
			storeArray.push(i);
		}
	}

	cookie.createArray(this.switcherId, storeArray);
}
});
