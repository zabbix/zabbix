/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


var globalAllObjForViewSwitcher = {};
var CViewSwitcher = function(objId, objAction, confData, disableDDItems) {
	this.mainObj = document.getElementById(objId);
	this.objAction = objAction;

	if (is_null(this.mainObj)) {
		throw('ViewSwitcher error: main object not found!');
	}
	this.depObjects = {};

	if (disableDDItems) {
		this.disableDDItems = disableDDItems;
	}

	for (var key in confData) {
		if (empty(confData[key])) {
			continue;
		}
		this.depObjects[key] = {};

		for (var vKey in confData[key]) {
			if (empty(confData[key][vKey])) {
				continue;
			}

			if (is_string(confData[key][vKey])) {
				this.depObjects[key][vKey] = {'id': confData[key][vKey]};
			}
			else if (is_object(confData[key][vKey])) {
				this.depObjects[key][vKey] = confData[key][vKey];
			}
		}
	}

	jQuery(this.mainObj).on(objAction, this.rebuildView.bindAsEventListener(this));
	globalAllObjForViewSwitcher[objId] = this;

	this.hideAllObjs();
	this.rebuildView();
};

CViewSwitcher.prototype = {
	mainObj: null,
	depObjects: {},
	lastValue: null,

	rebuildView: function(e) {
		var myValue = this.objValue(this.mainObj);

		// Enable previously disabled dropdown items.
		if (this.disableDDItems && this.disableDDItems[this.lastValue]) {
			for (var DDi in this.disableDDItems[this.lastValue]) {
				if (jQuery('#' + DDi).length == 0) {
					continue;
				}

				jQuery('#' + DDi).get(0).getOptions().map((opt) => opt.disabled = false);
			}
		}

		// Disable dropdown items.
		if (this.disableDDItems && this.disableDDItems[myValue]) {
			for (var DDi in this.disableDDItems[myValue]) {
				var DD = jQuery('#' + DDi);

				if (DD.length == 0) {
					continue;
				}

				for (var Oi in this.disableDDItems[myValue][DDi]) {
					if (DD.get(0).getOptionByValue(this.disableDDItems[myValue][DDi][Oi]) === null) {
						continue;
					}

					DD.get(0).getOptionByValue(this.disableDDItems[myValue][DDi][Oi]).disabled = true;
				}

				// If selected option unavailable set to first available.
				if (DD.get(0).getOptionByValue(DD.val()).disabled) {
					for (let opt of DD.get(0).getOptions()) {
						if (!opt.disabled) {
							DD.val(opt.value);
							break;
						}
					}

					DD.trigger(this.objAction);
				}
			}
		}

		if (isset(this.lastValue, this.depObjects)) {
			for (var key in this.depObjects[this.lastValue]) {
				if (empty(this.depObjects[this.lastValue][key])) {
					continue;
				}
				this.hideObj(this.depObjects[this.lastValue][key]);

				if (isset(this.depObjects[this.lastValue][key].id, globalAllObjForViewSwitcher)) {
					for (var i in globalAllObjForViewSwitcher[this.depObjects[this.lastValue][key].id].depObjects) {
						for (var j in globalAllObjForViewSwitcher[this.depObjects[this.lastValue][key].id].depObjects[i]) {
							this.hideObj(globalAllObjForViewSwitcher[this.depObjects[this.lastValue][key].id].depObjects[i][j]);
						}
					}
				}
			}
		}

		if (isset(myValue, this.depObjects) && !this.mainObj.disabled) {
			for (var key in this.depObjects[myValue]) {
				if (empty(this.depObjects[myValue][key])) {
					continue;
				}
				this.showObj(this.depObjects[myValue][key]);

				if (isset(this.depObjects[myValue][key].id, globalAllObjForViewSwitcher)) {
					globalAllObjForViewSwitcher[this.depObjects[myValue][key].id].rebuildView();
				}
			}
		}
		this.lastValue = myValue;
	},

	objValue: function(obj) {
		if (is_null(obj)) {
			return null;
		}

		switch (obj.tagName.toLowerCase) {
			case 'select':
				return (obj.selectedIndex > -1) ? obj.options[obj.selectedIndex].value : null;

			case 'input':
				if (obj.getAttribute('type').toLowerCase() === 'checkbox') {
					return obj.checked ? obj.value : null;
				}
				return obj.value;

			default:
				return obj.value;
		}
	},

	setObjValue : function (obj, value) {
		if (is_null(obj) || !isset('tagName', obj)) {
			return null;
		}

		switch (obj.tagName.toLowerCase()) {
			case 'select':
				for (var idx in obj.options) {
					if (obj.options[idx].value == value) {
						obj.selectedIndex = idx;
						break;
					}
				}
				break;

			case 'input':
				const input_type = obj.getAttribute('type');
				if (!is_null(input_type) && input_type.toLowerCase() === 'checkbox') {
					obj.checked = true;
				}
				obj.value = value;
				break;

			case 'textarea':
			default:
				obj.value = value;
				break;
		}
	},

	objDisplay: function(obj) {
		if (is_null(obj) || !isset('tagName', obj)) {
			return null;
		}
		switch (obj.tagName.toLowerCase()) {
			case 'th':
			case 'td':
				obj.style.display = 'table-cell';
				break;
			case 'tr':
				obj.style.display = 'table-row';
				break;
			case 'img':
			case 'div':
			case 'li':
				obj.style.display = '';
				break;
			case 'z-select':
				obj.style.display = 'inline-grid';
				break
			default:
				obj.style.display = 'inline';
		}
	},

	disableObj: function(obj, disable) {
		if (is_null(obj) || !isset('tagName', obj)) {
			return null;
		}
		obj.disabled = disable;
		if (obj == this.mainObj) {
			this.rebuildView();
		}
	},

	hideObj: function(data) {
		var element = document.getElementById(data.id);
		if (element === null) {
			return true;
		}
		this.disableObj(element, true);
		element.style.display = 'none';
	},

	showObj: function(data) {
		var element = document.getElementById(data.id);
		if (element === null) {
			return true;
		}
		this.disableObj(element, false);

		if (!is_null(data)) {
			var objValue = this.objValue(element);
			var defaultValue = false;

			for (var i in this.depObjects) {
				for (var j in this.depObjects[i]) {
					if (this.depObjects[i][j]['id'] == data.id
						&& isset('defaultValue', this.depObjects[i][j])
						&& this.depObjects[i][j]['defaultValue'] != ''
						&& this.depObjects[i][j]['defaultValue'] == objValue) {
						defaultValue = true;
					}
				}
			}
			if ((objValue == '' || defaultValue) && isset('defaultValue', data)) {
				this.setObjValue(element, data.defaultValue);
			}
		}
		this.objDisplay(element);
	},

	hideAllObjs: function() {
		var hidden = {};
		for (var i in this.depObjects) {
			if (empty(this.depObjects[i])) {
				continue;
			}
			for (var a in this.depObjects[i]) {
				if (empty(this.depObjects[i][a])) {
					continue;
				}
				if (isset(this.depObjects[i][a].id, hidden)) {
					continue;
				}

				hidden[this.depObjects[i][a].id] = true;

				var elm = document.getElementById(this.depObjects[i][a].id);
				if (elm === null) {
					continue;
				}
				this.hideObj(this.depObjects[i][a]);
			}
		}
	}
};

function ActionProcessor(actions) {
	this.actions = actions || {};
	this.bindEvents();
}

ActionProcessor.prototype = {
	bindEvents: function() {
		var elementId, elementsList = {};

		for (var i = 0; i < this.actions.length; i++) {
			var action = this.actions[i];
			for (var j = 0; j < action.cond.length; j++) {
				for (elementId in action.cond[j]) {
					elementsList[elementId] = true;
				}
			}
		}

		var handler = jQuery.proxy(this.process, this);

		for (elementId in elementsList) {
			var elem = jQuery('#' + elementId);
			switch (elem.get(0).nodeName.toLowerCase()) {
				case 'z-select':
				case 'select':
					elem.change(handler);
					break;
				case 'input':
					switch (elem.attr('type')) {
						case 'checkbox':
						case 'text':
							elem.change(handler);
							break;
						case 'radio':
							var elemName = elem.attr('name');
							jQuery('input[name=' + elemName + ']').click(handler);
							break;
						default:
							elem.click(handler);
					}
					break;
			}
		}
	},

	getValue: function(elementId) {
		var elem = jQuery('#' + elementId);
		var type = elem.attr('type');
		if (type == 'radio' || type == 'checkbox') {
			return elem.prop('checked') === true ? 'checked' : 'unchecked';
		}
		else {
			return elem.val();
		}
	},

	checkConditions: function(conditions) {
		var elementId, i, ln, failed;

		for (i = 0, ln = conditions.length; i < ln; i++) {
			failed = false;

			for (elementId in conditions[i]) {
				if (this.getValue(elementId) !== conditions[i][elementId]) {
					failed = true;
					break;
				}
			}
			if (!failed) {
				return true;
			}
		}
		return false;
	},

	process: function() {
		var action;
		this.hidden = jQuery();

		for (var i = 0; i < this.actions.length; i++) {
			action = this.actions[i];
			switch (action.action) {
				case 'show':
					this.actionToggle(action.value, this.checkConditions(action.cond));
					break;
				case 'hide':
					this.actionToggle(action.value, !this.checkConditions(action.cond));
					break;
				case 'enable':
					jQuery(action.value)
						.prop('disabled', !this.checkConditions(action.cond))
						.closest('.color-picker')
						.toggleClass('disabled', !this.checkConditions(action.cond));
					break;
				case 'disable':
					jQuery(action.value)
						.prop('disabled', this.checkConditions(action.cond))
						.closest('.color-picker')
						.toggleClass('disabled', this.checkConditions(action.cond));
					break;
			}
		}
	},

	actionToggle: function(value, toggle) {
		jQuery(value).toggle(toggle);
		this.hidden = toggle ? this.hidden.not(jQuery(':input', value)) : this.hidden.add(jQuery(':input', value));
	}
};
