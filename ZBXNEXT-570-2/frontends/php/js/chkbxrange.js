/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


/*
 * Automatic checkbox range selection
 */
var chkbxRange = {
	startbox:		null,	// start checkbox obj
	startboxName:	null,	// start checkbox name
	chkboxes:		{},		// ckbx list
	prefix:			null,	// prefix for cookie name
	pageGoName:		null,	// which checkboxes should be counted by Go button
	pageGoCount:	0,		// selected checkboxes
	selectedIds:	{},		// ids of selected checkboxes
	goButton:		null,
	cookieName:		null,

	init: function() {
		var path = new Curl();
		var filename = basename(path.getPath(), '.php');
		this.cookieName = 'cb_' + filename + (this.prefix ? '_' + this.prefix : '');
		this.resetOtherPageCookies();
		this.selectedIds = cookie.readJSON(this.cookieName);

		var chkboxes = jQuery('.tableinfo .checkbox:not(:disabled)');
		if (chkboxes.length > 0) {
			for (var i = 0; i < chkboxes.length; i++) {
				this.implement(chkboxes[i]);
			}
		}

		this.selectMainCheckbox();

		this.goButton = $('goButton');
		if (!is_null(this.goButton)) {
			addListener(this.goButton, 'click', this.submitGo.bindAsEventListener(this), false);
		}

		this.setGo();
	},

	/**
	 * Reset all selections on other pages.
	 */
	resetOtherPageCookies: function() {
		for(var key in cookie.cookies) {
			var cookiePair = key.split('=');
			if (cookiePair[0].indexOf('cb_') > -1 && cookiePair[0].indexOf(this.cookieName) == -1) {
				cookie.erase(key);
			}
		}
	},

	implement: function(obj) {
		var objName = obj.name.split('[')[0];

		if (typeof(this.chkboxes[objName]) === 'undefined') {
			this.chkboxes[objName] = [];
		}
		this.chkboxes[objName].push(obj);

		addListener(obj, 'click', this.check.bindAsEventListener(this), false);

		if (objName == this.pageGoName) {
			var objId = jQuery(obj).val();
			if (isset(objId, this.selectedIds)) {
				obj.checked = true;
			}
		}
	},

	// check if all checkboxes are selected and select main checkbox, else disable checkbox, select options and button
	selectMainCheckbox: function() {
		var mainCheckbox = jQuery('.tableinfo .header .checkbox:not(:disabled)');
		if (!mainCheckbox.length) {
			return;
		}

		var countAvailable = jQuery('.tableinfo tr:not(.header) .checkbox:not(:disabled)').length;

		if (countAvailable > 0) {
			var countChecked = jQuery('.tableinfo tr:not(.header) .checkbox:not(:disabled):checked').length;

			mainCheckbox = mainCheckbox[0];
			mainCheckbox.checked = (countChecked == countAvailable);

			if (mainCheckbox.checked) {
				jQuery('.tableinfo .header').addClass('selectedMain');
			}
			else {
				jQuery('.tableinfo .header').removeClass('selectedMain');
			}
		}
		else {
			mainCheckbox.disabled = true;
		}
	},

	check: function(e) {
		e = e || window.event;
		var obj = Event.element(e);

		PageRefresh.restart();

		if (typeof(obj) === 'undefined' || obj.type.toLowerCase() != 'checkbox' || obj.disabled === true) {
			return true;
		}

		this.setGo();

		if (obj.name.indexOf('all_') > -1 || obj.name.indexOf('_single') > -1) {
			return true;
		}
		var objName = obj.name.split('[')[0];

		// check range selection
		if (e.ctrlKey || e.shiftKey) {
			if (!is_null(this.startbox) && this.startboxName == objName && obj.name != this.startbox.name) {
				var chkboxes = this.chkboxes[objName];
				var flag = false;

				for (var i = 0; i < chkboxes.length; i++) {
					if (typeof(chkboxes[i]) !== 'undefined') {
						if (flag) {
							chkboxes[i].checked = this.startbox.checked;
						}
						if (obj.name == chkboxes[i].name) {
							break;
						}
						if (this.startbox.name == chkboxes[i].name) {
							flag = true;
						}
					}
				}

				if (flag) {
					this.setGo();
					this.selectMainCheckbox();
					return true;
				}
				else {
					for (var i = chkboxes.length - 1; i >= 0; i--) {
						if (typeof(chkboxes[i]) !== 'undefined') {
							if (flag) {
								chkboxes[i].checked = this.startbox.checked;
							}

							if (obj.name == chkboxes[i].name) {
								this.setGo();
								this.selectMainCheckbox();
								return true;
							}

							if (this.startbox.name == chkboxes[i].name) {
								flag = true;
							}
						}
					}
				}
			}

			this.setGo();
		}
		else {
			this.selectMainCheckbox();
		}

		this.startbox = obj;
		this.startboxName = objName;
	},

	checkAll: function(name, value) {
		if (typeof(this.chkboxes[name]) === 'undefined') {
			return false;
		}

		var chkboxes = this.chkboxes[name];
		for (var i = 0; i < chkboxes.length; i++) {
			if (typeof(chkboxes[i]) !== 'undefined' && chkboxes[i].disabled !== true) {
				var objName = chkboxes[i].name.split('[')[0];
				if (objName == name) {
					chkboxes[i].checked = value;
				}
			}
		}

		var mainCheckbox = jQuery('.tableinfo .header .checkbox:not(:disabled)')[0];
		if (mainCheckbox.checked) {
			jQuery('.tableinfo .header').addClass('selectedMain');
		}
		else {
			jQuery('.tableinfo .header').removeClass('selectedMain');
		}
	},

	clearSelectedOnFilterChange: function() {
		cookie.eraseArray(this.cookieName);
	},

	/**
	 * Mark selected checkboxes and update the "Go" dropdown.
	 */
	setGo: function() {
		if (this.pageGoName == null) {
			return;
		}

		if (typeof(this.chkboxes[this.pageGoName]) !== 'undefined') {
			var chkboxes = this.chkboxes[this.pageGoName];
			var selectedCheckboxes = {};
			for (var i = 0; i < chkboxes.length; i++) {
				if (typeof(chkboxes[i]) !== 'undefined') {
					var box = chkboxes[i];
					var objName = box.name.split('[')[0];
					var objId = box.name.split('[')[1];
					objId = objId.substring(0, objId.lastIndexOf(']'));

					if (objName != this.pageGoName) {
						continue;
					}

					if (box.checked) {
						this.selectedIds[objId] = objId;
						selectedCheckboxes[objId] = objId;
					}
					// since there can be multiple checkboxes with the same ID,
					// don't unselect an object if another its checkbox has been checked
					else if (typeof selectedCheckboxes[objId] === 'undefined') {
						delete(this.selectedIds[objId]);
					}

					// mark the table rows as selected
					jQuery(box).closest('tr').toggleClass('selected', box.checked);
				}
			}

		}

		var countChecked = 0;
		for (var key in this.selectedIds) {
			if (!empty(this.selectedIds[key])) {
				countChecked++;
			}
		}

		if (!is_null(this.goButton)) {
			var tmp_val = this.goButton.value.split(' ');
			this.goButton.value = tmp_val[0] + ' (' + countChecked + ')';
		}

		cookie.createJSON(this.cookieName, this.selectedIds);

		if (jQuery('#go').length) {
			jQuery('#go')[0].disabled = (countChecked == 0);
		}
		if (jQuery('#goButton').length) {
			jQuery('#goButton')[0].disabled = (countChecked == 0);
		}

		this.pageGoCount = countChecked;
	},

	submitGo: function(e) {
		e = e || window.event;

		var goSelect = $('go');
		var confirmText = goSelect.options[goSelect.selectedIndex].getAttribute('confirm');

		if (!is_null(confirmText) && !confirm(confirmText)) {
			Event.stop(e);
			return false;
		}

		var form = this.goButton.closest('form');
		for (var key in this.selectedIds) {
			if (!empty(this.selectedIds[key])) {
				create_var(form.name, this.pageGoName + '[' + key + ']', key, false);
			}
		}
		return true;
	}
};
