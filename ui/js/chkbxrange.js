/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/*
 * Automatic checkbox range selection
 */
var chkbxRange = {
	startbox:					null,	// start checkbox obj
	chkboxes:					{},		// ckbx list
	prefix:						null,	// prefix for session storage variable name
	pageGoName:					null,	// which checkboxes should be counted by Go button and saved to session storage
	selected_ids_storage_name:	null,
	search_params_storage_name: null,
	event_handlers: 			null,

	init: function() {
		var path = new Curl();
		var filename = basename(path.getPath(), '.php');

		this.selected_ids_storage_name = 'cb_' + filename + (this.prefix ? '_' + this.prefix : '');
		this.search_params_storage_name = 'sp_' + filename + (this.prefix ? '_' + this.prefix : '');
		// Erase old checkboxes.
		this.chkboxes = {};
		this.startbox = null;

		this.resetOtherPage();

		// initialize checkboxes
		const checkboxes = Array.from(
			document.querySelectorAll('.list-table tbody input[type="checkbox"]:not(:disabled)')
		);

		checkboxes.forEach(checkbox => this.implement(checkbox));

		// load selected checkboxes from session storage or cache
		if (this.pageGoName != null) {
			const object_ids = new Set(Object.keys(this.getSelectedIds()));

			checkboxes.filter(checkbox => checkbox.checked)
				.forEach(checkbox => object_ids.add(this.getObjectIdFromName(checkbox.name)));

			this.checkObjects(this.pageGoName, [...object_ids], true);
			this.update(this.pageGoName);
		}

		if (this.event_handlers === null) {
			this.event_handlers = {
				action_button_click: (e) => this.submitFooterButton(e)
			};
		}

		for (const footer_button of document.querySelectorAll('#action_buttons button:not(.js-no-chkbxrange)')) {
			footer_button.addEventListener('click', this.event_handlers.action_button_click);
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
			if (isset(objId, this.getSelectedIds())) {
				obj.checked = true;
			}
		}
	},

	getSelectedIds() {
		const session_selected_ids = sessionStorage.getItem(this.selected_ids_storage_name);

		return session_selected_ids === null ? {} : JSON.parse(session_selected_ids);
	},

	/**
	 * Handles a click on one of the checkboxes.
	 *
	 * @param e
	 */
	handleClick: function(e) {
		var checkbox = e.target;

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
	 * @param {string} object
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
	 * @param {string}		object
	 * @param {string[]}	object_ids	array of objects IDs.
	 * @param {boolean}		checked
	 */
	checkObjects: function(object, object_ids, checked) {
		const checkboxes = {};
		this.getObjectCheckboxes(object).forEach(checkbox => {
			const object_id = this.getObjectIdFromName(checkbox.name);

			checkboxes[object_id] = checkbox;
		});

		const selected_ids = this.getSelectedIds();

		Object.entries(checkboxes).forEach(([object_id, checkbox]) => {
			if (object_ids.includes(object_id)) {
				checkbox.checked = checked;

				const row = checkbox.closest('tr');

				if (checked) {
					row.classList.add('row-selected');

					const actions = document.getElementById(object + '_' + object_id).getAttribute('data-actions');
					selected_ids[object_id] = (actions === null) ? '' : actions;
				}
				else {
					row.classList.remove('row-selected');

					// Remove class attribute if it's empty.
					if (row.className.trim().length === 0) {
						row.removeAttribute('class');
					}

					delete selected_ids[object_id];
				}
			}
		});

		const search_params = this.getSearchParams();

		this.handleBackForwardNavigation(search_params, checkboxes, object_ids, selected_ids);

		this.saveSearchParams(object, search_params);
		this.saveSelectedIds(object, selected_ids);
	},

	/**
	 * Handle back/forward navigation by resetting the state of selected checkboxes.
	 *
	 * @param {URLSearchParams}						search_params	current search params.
	 * @param {Record<string, HTMLInputElement>}	checkboxes		map of object IDs and checkbox elements.
	 * @param {string[]}   							object_ids		array of objects IDs.
	 * @param {string[]} 							selected_ids	currently selected object IDs.
	 */
	handleBackForwardNavigation: function(search_params, checkboxes, object_ids, selected_ids) {
		if (!PerformanceObserver.supportedEntryTypes.includes('navigation')) {
			return;
		}

		const entries = performance.getEntriesByType('navigation').filter(entry => entry.type === 'back_forward');
		if (entries.length === 0) {
			return;
		}

		if (this.hasSearchParamChanged(this.getPreviousSearchParams(), search_params, 'page')) {
			return;
		}

		for (const object_id of object_ids) {
			if (checkboxes[object_id] === undefined) {
				delete selected_ids[object_id];
			}
		}
	},

	/**
	 * Get the current page's URL search parameters.
	 *
	 * @returns {URLSearchParams}
	 */
	getSearchParams: function() {
		return new URLSearchParams(window.location.search);
	},

	/**
	 * Retrieve the previously saved search parameters from SessionStorage.
	 *
	 * @returns {URLSearchParams}
	 */
	getPreviousSearchParams: function() {
		const search_params = JSON.parse(sessionStorage.getItem(this.search_params_storage_name)) || {};

		return new URLSearchParams(search_params);
	},

	/**
	 * Check if a specific search parameter has changed between two URLSearchParams objects.
	 *
	 * @param {URLSearchParams}	first_params	first search parameters to compare.
	 * @param {URLSearchParams}	second_params	second search parameters to compare.
	 * @param {string}			param			name of the search parameter to check.
	 * @returns {boolean}
	 */
	hasSearchParamChanged: function (first_params, second_params, param) {
		return first_params.get(param) !== second_params.get(param);
	},

	/**
	 * Toggle all objects between the two checkboxes.
	 *
	 * @param {string} object
	 * @param {object} startCheckbox
	 * @param {object} endCheckbox
	 * @param {boolean} checked
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
	 * @param {boolean} checked
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
		this.updateMainCheckbox(object);

		if (this.pageGoName == object) {
			this.updateGoButton();
		}
	},

	/**
	 * Update the state of the "Go" controls.
	 */
	updateGoButton: function() {
		const object = this.pageGoName;
		let selected_count = 0;
		let actions = [];

		Object
			.values(this.getSelectedIds())
			.forEach(value => {
				selected_count++;

				// Count the special attributes for checkboxes.
				if (value !== null) {
					const action_list = value.split(' ');

					for (const action of action_list) {
						if (!actions.hasOwnProperty(action)) {
							actions[action] = 0;
						}
						actions[action]++;
					}
				}
			});

		// Replace the selected count text.
		const selected_count_span = document.getElementById('selected_count');
		selected_count_span.innerHTML = selected_count + ' ' + selected_count_span.innerHTML.split(' ')[1];

		document.querySelectorAll('#action_buttons button').forEach((button) => {
			// In case button is not permanently disabled by view, enable it depending on attributes and count.
			if (!button.dataset.disabled) {
				// First disabled the button and then check if it can be enabled.
				button.disabled = true;

				// Check if a special attribute is required to enable the button.
				if (button.dataset.required) {
					for (const [action, count] of Object.entries(actions)) {
						// Checkbox data-actions attribute must match the button attribute.
						if (button.dataset.required === action) {
							// Check if there is a minimum amount of checkboxes required to be selected.
							if (button.dataset.requiredCount) {
								button.disabled = (count < button.dataset.requiredCount);
							}
							else {
								button.disabled = (count == 0);
							}
						}
					}
				}
				else {
					// No special attributes required, enable the button depending only on selected count.
					button.disabled = (selected_count == 0);
				}
			}
		});
	},

	/**
	 * Select main checkbox if all other checkboxes are selected.
	 *
	 * @param {string} object
	 */
	updateMainCheckbox: function(object) {
		const checkbox_list = this.getObjectCheckboxes(object);
		const $main_checkbox = $(checkbox_list)
			.parents('table')
			.find('thead input[type=checkbox]');

		if ($main_checkbox.length == 0) {
			return;
		}

		const count_available = checkbox_list.length;

		if (count_available > 0) {
			const checked = [];

			jQuery.each(checkbox_list, (i, checkbox) => {
				if (checkbox.checked) {
					checked.push(checkbox);
				}
			});

			$main_checkbox[0].checked = (checked.length == count_available);
		}
	},

	/**
	 * Save the state of the search params belonging to the given object group in SessionStorage.
	 *
	 * @param {string}			object
	 * @param {URLSearchParams}	search_params
	 */
	saveSearchParams: function(object, search_params) {
		if (search_params.size > 0) {
			if (this.pageGoName == object) {
				const entries = Object.fromEntries(search_params.entries());

				sessionStorage.setItem(this.search_params_storage_name, JSON.stringify(entries));
			}
		} else {
			sessionStorage.removeItem(this.search_params_storage_name);
		}
	},

	/**
	 * Save the state of the checkboxes belonging to the given object group in SessionStorage.
	 *
	 * @param {string} object
	 * @param {Object} selected_ids  key/value pairs of selected ids.
	 */
	saveSelectedIds: function(object, selected_ids) {
		if (Object.keys(selected_ids).length > 0) {
			if (this.pageGoName == object) {
				sessionStorage.setItem(this.selected_ids_storage_name, JSON.stringify(selected_ids));
			}
		}
		else {
			sessionStorage.removeItem(this.selected_ids_storage_name);
		}
	},

	clearSelectedOnFilterChange: function() {
		sessionStorage.removeItem(this.search_params_storage_name);
		sessionStorage.removeItem(this.selected_ids_storage_name);
	},

	/**
	 * Reset all selections on other pages.
	 */
	resetOtherPage: function() {
		var key_;

		for (var i = 0; i < sessionStorage.length; i++) {
			key_ = sessionStorage.key(i);

			if (key_.substring(0, 3) === 'cb_' && key_ != this.selected_ids_storage_name) {
				sessionStorage.removeItem(key_);
			}
		}
	},

	submitFooterButton: function(e) {
		const checked_count = Object.keys(this.getSelectedIds()).length;

		var footerButton = jQuery(e.target),
			form = footerButton.closest('form'),
			confirmText = checked_count > 1
				? footerButton.attr('confirm_plural')
				: footerButton.attr('confirm_singular');

		if (confirmText && !confirm(confirmText)) {
			e.preventDefault();
			e.stopPropagation();

			return false;
		}

		const selected_ids = this.getSelectedIds();
		for (let key in selected_ids) {
			if (selected_ids.hasOwnProperty(key) && selected_ids[key] !== null) {
				create_var(form.attr('name'), this.pageGoName + '[' + key + ']', key, false);
			}
		}
		return true;
	}
};
