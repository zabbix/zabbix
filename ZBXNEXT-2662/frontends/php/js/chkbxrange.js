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
	chkboxes:		{},		// ckbx list
	prefix:			null,	// prefix for cookie name
	pageGoName:		null,	// which checkboxes should be counted by Go button and saved to cookies
	selectedIds:	{},		// ids of selected objects
	goButton:		null,
	cookieName:		null,

	init: function() {
		// cookie name
		var path = new Curl();
		var filename = basename(path.getPath(), '.php');
		this.cookieName = 'cb_' + filename + (this.prefix ? '_' + this.prefix : '');

		this.resetOtherPageCookies();

		// initialize checkboxes
		var chkboxes = jQuery('.tableinfo .checkbox:not(:disabled)');
		if (chkboxes.length > 0) {
			for (var i = 0; i < chkboxes.length; i++) {
				this.implement(chkboxes[i]);
			}
		}

		// load selected checkboxes from cookies or cache
		if (this.pageGoName != null) {
			this.selectedIds = cookie.readJSON(this.cookieName);

			// check if checkboxes should be selected from cookies
			if (!jQuery.isEmptyObject(this.selectedIds)) {
				var objectIds = jQuery.map(this.selectedIds, function(id) { return id });
			}
			// no checkboxes selected from cookies, check browser cache if checkboxes are still checked and update state
			else {
				var checkedFromCache = jQuery('.tableinfo tr:not(.header) .checkbox:checked:not(:disabled)');
				var objectIds = jQuery.map(checkedFromCache, jQuery.proxy(function(checkbox) {
					return this.getObjectIdFromName(checkbox.name);
				}, this));
			}

			this.checkObjects(this.pageGoName, objectIds, true);
			this.update(this.pageGoName);
		}

		// bind event to the "Go" button
		this.goButton = $('goButton');
		if (!is_null(this.goButton)) {
			addListener(this.goButton, 'click', this.submitGo.bindAsEventListener(this), false);
		}
	},

	implement: function(obj) {
		// skip the "select all" checkbox
		if (obj.name.indexOf('all_') > -1) {
			return;
		}

		var objName = this.getObjectFromName(obj.name);

		if (typeof(this.chkboxes[objName]) === 'undefined') {
			this.chkboxes[objName] = [];
		}
		this.chkboxes[objName].push(obj);

		addListener(obj, 'click', this.handleClick.bindAsEventListener(this), false);

		if (objName == this.pageGoName) {
			var objId = jQuery(obj).val();
			if (isset(objId, this.selectedIds)) {
				obj.checked = true;
			}
		}
	},

	/**
	 * Handles a click on one of the checkboxes.
	 *
	 * @param e
	 */
	handleClick: function(e) {
		e = e || window.event;
		var checkbox = Event.element(e);

		PageRefresh.restart();

		var object = this.getObjectFromName(checkbox.name);
		var objectId = this.getObjectIdFromName(checkbox.name);

		// range selection
		if ((e.ctrlKey || e.shiftKey) && this.startbox != null) {
			this.checkObjectRange(object, this.startbox, checkbox, this.startbox.checked);
		}
		// an individual checkbox
		else {
			this.checkObjects(object, [objectId], checkbox.checked);
		}

		this.update(object);
		this.saveCookies(object);

		this.startbox = checkbox;
	},

	/**
	 * Extracts the name of an object from the name of a checkbox.
	 *
	 * @param {string} name
	 *
	 * @returns {string}
	 */
	getObjectFromName: function(name) {
		return name.split('[')[0];
	},

	/**
	 * Extracts the ID of an object from the name of a checkbox.
	 *
	 * @param {string} name
	 *
	 * @returns {string}
	 */
	getObjectIdFromName: function(name) {
		var id = name.split('[')[1];
		id = id.substring(0, id.lastIndexOf(']'));

		return id;
	},

	/**
	 * Returns the checkboxes in an object group.
	 *
	 * @param string object
	 *
	 * @returns {Array}
	 */
	getObjectCheckboxes: function(object) {
		return this.chkboxes[object] || [];
	},

	/**
	 * Toggle all checkboxes of the given objects.
	 *
	 * Checks all of the checkboxes that belong to these objects and highlights the table row.
	 *
	 * @param {string}  object
	 * @param {Array}   objectIds     array of objects IDs as integers
	 * @param {bool}    checked
	 */
	checkObjects: function(object, objectIds, checked) {
		jQuery.each(this.getObjectCheckboxes(object), jQuery.proxy(function(i, checkbox) {
			var objectId = this.getObjectIdFromName(checkbox.name);

			if (objectIds.indexOf(objectId) > -1) {
				checkbox.checked = checked;

				jQuery(checkbox).closest('tr').toggleClass('selected', checked);

				if (checked) {
					this.selectedIds[objectId] = objectId;
				}
				else {
					delete this.selectedIds[objectId];
				}
			}
		}, this));
	},

	/**
	 * Toggle all objects between the two checkboxes.
	 *
	 * @param {string} object
	 * @param {object} startCheckbox
	 * @param {object} endCheckbox
	 * @param {bool} checked
	 */
	checkObjectRange: function(object, startCheckbox, endCheckbox, checked) {
		var checkboxes = this.getObjectCheckboxes(object);

		var startCheckboxIndex = checkboxes.indexOf(startCheckbox);
		var endCheckboxIndex = checkboxes.indexOf(endCheckbox);
		var start = Math.min(startCheckboxIndex, endCheckboxIndex);
		var end = Math.max(startCheckboxIndex, endCheckboxIndex);

		var objectIds = [];
		for (var i = start; i <= end; i++) {
			objectIds.push(this.getObjectIdFromName(checkboxes[i].name));
		}
		this.checkObjects(object, objectIds, checked);

	},

	/**
	 * Toggle all of the checkboxes belonging to the given object group.
	 *
	 * @param {string} object
	 *
	 * @param {bool} checked
	 */
	checkObjectAll: function(object, checked) {
		// main checkbox exists and is clickable, but other checkboxes may not exist and object may be empty
		var objectIds = jQuery.map(this.getObjectCheckboxes(object), jQuery.proxy(function(checkbox) {
			return this.getObjectIdFromName(checkbox.name);
		}, this));

		this.checkObjects(object, objectIds, checked);
	},

	/**
	 * Update the general state after toggling a checkbox.
	 *
	 * @param {string} object
	 */
	update: function(object) {
		// update main checkbox state
		this.updateMainCheckbox();

		if (this.pageGoName == object) {
			this.updateGoButton();
		}
	},

	/**
	 * Update the state of the "Go" controls.
	 */
	updateGoButton: function() {
		var count = 0;
		jQuery.each(this.selectedIds, function() {
			count++;
		});

		// update go button
		var goButton = jQuery('#goButton');
		goButton.text(goButton.text().split(' ')[0] + ' (' + count + ')')
			.prop('disabled', count == 0);
		jQuery('#action').prop('disabled', count == 0);
	},

	// check if all checkboxes are selected and select main checkbox, else disable checkbox, select options and button
	updateMainCheckbox: function() {
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

	/**
	 * Save the state of the checkboxes belonging to the given object group in cookies.
	 *
	 * @param {string} object
	 */
	saveCookies: function(object) {
		if (this.pageGoName == object) {
			cookie.createJSON(this.cookieName, this.selectedIds);
		}
	},

	clearSelectedOnFilterChange: function() {
		cookie.eraseArray(this.cookieName);
	},

	/**
	 * Reset all selections on other pages.
	 */
	resetOtherPageCookies: function() {
		for (var key in cookie.cookies) {
			var cookiePair = key.split('=');
			if (cookiePair[0].indexOf('cb_') > -1 && cookiePair[0].indexOf(this.cookieName) == -1) {
				cookie.erase(key);
			}
		}
	},

	submitGo: function(e) {
		e = e || window.event;

		var goSelect = $('action');
		var confirmText = goSelect.options[goSelect.selectedIndex].getAttribute('confirm');

		if (!is_null(confirmText) && !confirm(confirmText)) {
			Event.stop(e);
			return false;
		}

		var form = jQuery('#goButton').closest('form');
		for (var key in this.selectedIds) {
			if (!empty(this.selectedIds[key])) {
				create_var(form.attr('name'), this.pageGoName + '[' + key + ']', key, false);
			}
		}
		return true;
	}
};
