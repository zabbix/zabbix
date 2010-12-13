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

var CViewSwitcher = Class.create({
inAction:			false,
mainObj:			null,
actionsObj:			null,
changedFields:		{},
depObjects:			{},
lastValue:			null,

initialize : function(objId, objAction, confData){
	this.mainObj = $(objId);
	if(is_null(this.mainObj)) throw('ViewSwitcher error: main object not found!');

	this.depObjects = {};

	for(var key in confData){
		if(empty(confData[key])) continue;

		this.depObjects[key] = {};
		for(var vKey in confData[key]){
			if(empty(confData[key][vKey])) continue;

			if(is_string(confData[key][vKey])) this.depObjects[key][vKey] = {'id': confData[key][vKey]};
			else if(is_object(confData[key][vKey])) this.depObjects[key][vKey] = confData[key][vKey];
		}
	}

	if(!is_array(objAction)) objAction = new Array(objAction);
	this.actionsObj = objAction;

	for(var i = 0; i < this.actionsObj.length; i++) {
		addListener(this.mainObj, objAction[i], this.rebuildView.bindAsEventListener(this));
	}

	this.hideAllObjs();
	this.rebuildView();
},

rebuildView: function(e){
	var myValue = this.objValue(this.mainObj);

	if(myValue == this.lastValue){
		this.inAction = false;
		return true;
	}

	if(isset(this.lastValue, this.depObjects)) {
		for(var key in this.depObjects[this.lastValue]) {
			if(empty(this.depObjects[this.lastValue][key])) continue;

			this.hideObj(this.depObjects[this.lastValue][key]);
		}
	}

	if(isset(myValue, this.depObjects)) {
		for(var key in this.depObjects[myValue]){
			if(empty(this.depObjects[myValue][key])) continue;

			this.showObj(this.depObjects[myValue][key]);
		}
	}

	this.lastValue = myValue;
},

objValue: function(obj){
	if(is_null(obj) || obj.disabled) return null;

	var aValue = null;
	switch(obj.tagName.toLowerCase()) {
		case 'select':
			aValue = obj.options[obj.selectedIndex].value;
			break;
		case 'input':
			var inpType = obj.getAttribute('type');
			if(!is_null(inpType) && (inpType.toLowerCase() == 'checkbox')){
				aValue = obj.checked ? obj.value : null;
				break;
			}
		case 'textarea':
		default:
			aValue = obj.value;
	}

return aValue;
},

setObjValue : function (obj, value) {
	if(is_null(obj) || !isset('tagName',obj)) return null;

	switch(obj.tagName.toLowerCase()) {
		case 'select':
			for(var idx in obj.options) {
				if(obj.options[idx].value == value) {
					obj.selectedIndex = idx;
					break;
				}
			}
			break;
		case 'input':
			var inpType = obj.getAttribute('type');
			if(!is_null(inpType) && (inpType.toLowerCase() == 'checkbox')){
				obj.checked = true;
				obj.value == value;
				break;
			}
		case 'textarea':
		default:
			obj.value = value;
	}
},

objDisplay: function(obj){
	if(is_null(obj) || !isset('tagName',obj)) return null;

	switch(obj.tagName.toLowerCase()) {
		case 'th':
		case 'td': obj.style.display = IE?'block':'table-cell'; break;
		case 'tr': obj.style.display = IE?'block':'table-row'; break;
		case 'img':
		case 'div':
			obj.style.display = 'block';
			break;
		default:
			obj.style.display = 'inline';
	}
},

disableObj: function(obj, disable){
	if(is_null(obj) || !isset('tagName',obj)) return null;

	obj.disabled = disable;
	if(obj == this.mainObj)	this.rebuildView();
},

hideObj: function(data) {
	if(is_null($(data.id))) return true;

	this.disableObj($(data.id), true);
	$(data.id).style.display = 'none';

	var objValue = this.objValue($(data.id));
	var elmValue = null;

	if(isset('value', data)){
		elmValue = this.objValue($(data.value));
	}

	if(is_null(elmValue) && isset('defaultValue', data) && (this.changedFields[data.id] === false))
		this.setObjValue($(data.id), data.defaultValue);
	else if(!is_null(elmValue))
		this.setObjValue($(data.id), elmValue);
},

showObj : function(data){
	if(is_null($(data.id))) return true;

	this.disableObj($(data.id), false);

	if(!is_null(data)) {
		var objValue = this.objValue($(data.id));
		var elmValue = null;

		if(isset('value', data)){
			elmValue = this.objValue($(data.value));
		}

		if(is_null(elmValue) && isset('defaultValue', data) && (this.changedFields[data.id] === false))
			this.setObjValue($(data.id), data.defaultValue);
		else if(!is_null(elmValue))
			this.setObjValue($(data.id), elmValue);

		if(!is_null(elmValue)) this.setObjValue($(data.value), objValue);
	}

	this.objDisplay($(data.id));
},

hideAllObjs: function(){
	var hidden = {};
	for(var i in this.depObjects) {
		if(empty(this.depObjects[i])) continue;

		for(var a in this.depObjects[i]) {
			if(empty(this.depObjects[i][a])) continue;
			if(isset(this.depObjects[i][a].id, hidden)) continue;

			hidden[this.depObjects[i][a].id] = true;

			var elm = $(this.depObjects[i][a].id);
			if(is_null(elm)) continue;

			this.hideObj(this.depObjects[i][a]);
			if(isset('defaultValue', this.depObjects[i][a])){
				this.changedFields[this.depObjects[i][a].id] = false;

				for(var i = 0; i < this.actionsObj.length; i++) {
					addListener(elm, this.actionsObj[i], this.chageFieldStatus.bindAsEventListener(this, this.depObjects[i][a].id));
				}
			}
		}
	}
},

chageFieldStatus: function(e, id){
	this.changedFields[id] = true;
}
});