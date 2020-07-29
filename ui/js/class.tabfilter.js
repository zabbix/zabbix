/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


const TABFILTER_EVENT_URLSET = 'urlset.tabfilter';

class CTabFilter extends CBaseComponent {

	constructor(target, options) {
		super(target);
		this._options = options;
		// Array of CTabFilterItem objects.
		this._items = [];
		this._active_item = null;
		this._shared_domnode = null;
		// NodeList of available templates (<script> DOM elements).
		this._templates = {};
		this._fetchpromise = null;
		this._idx_namespace = 'web.monitoringhosts';

		this.init(options);
		this.registerEvents(options);
	}

	init(options) {
		let item, index = 0;

		if (options.expanded) {
			options.data[options.selected].expanded = true;
		}

		this._shared_domnode = this._target.querySelector('.form-buttons');

		for (const template of this._target.querySelectorAll('[type="text/x-jquery-tmpl"][data-template]')) {
			this._templates[template.getAttribute('data-template')] = template;
		};

		for (const title of this._target.querySelectorAll('nav [data-target]')) {
			item = this.create(title, options.data[index]||{});

			if (item._expanded) {
				this._active_item = item;
			}

			index++;
		}
	}

	/**
	 * Register tab filter events, called once during initialization.
	 *
	 * @param {object} options  Tab filter initialization options.
	 */
	registerEvents(options) {
		this._events = {
			/**
			 * Event handler on tab content expand.
			 */
			expand: (ev) => {
				this._active_item = ev.detail.target;
				this.collapseAllItemsExcept(this._active_item);

				if (!ev.detail.target._expanded && (!this._active_item || !this._active_item._expanded)) {
					this._shared_domnode.classList.remove('display-none');
					this.profileUpdate('selected', {
						value_int: this._active_item._index
					});
				}
			},

			/**
			 * Event handler on tab content collapse.
			 */
			collapse: (ev) => {
				if (ev.detail.target === this._active_item) {
					this._shared_domnode.classList.add('display-none');
					this.profileUpdate('expanded', {
						value_int: 0
					});
				}
			},

			/**
			 * UI sortable update event handler. Updates tab sorting in profile.
			 */
			tabSortChanged: (ev, ui) => {
				// Update order of this._items array.
				var from, to, target = ui.item[0].querySelector('[data-target]');

				this._items.forEach((item, index) => from = (item._target === target) ? index : from);
				this._target.querySelectorAll('nav [data-target]')
					.forEach((elm, index) => to = (elm === target) ? index : to);
				this._items[to] = this._items.splice(from, 1, this._items[to])[0];

				// Tab order changed, update changes via ajax.
				let value_str = this._items.map((item) => item._index).join(',');

				this.profileUpdate('taborder', {
					value_str: value_str
				}).then(() => {
					this._items.forEach((item, index) => {
						item._index = index;
					});
				});
			},

			/**
			 * Delete tab filter item. Removed HTML elements and CTabFilterItem object. Update index of tabfilter items.
			 */
			deleteItem: (ev) => {
				let item = ev.detail.target,
					index = this._items.indexOf(item);

				if (index > -1) {
					this._active_item = this._items[index - 1];
					this._active_item.select();
					item._target.parentNode.remove();
					delete this._items[index];
					this._items.splice(index, 1);
					this._items.forEach((item, index) => {
						item._index = index;
					});
				}
			},

			/**
			 * Action on 'chevron left' button press. Select previous active tab filter.
			 */
			selectPrevTab: (ev) => {
				let index = this._items.indexOf(this._active_item);

				if (index > 0) {
					this._items[index - 1].select();
				}
			},

			/**
			 * Action on 'chevron right' button press. Select next active tab filter.
			 */
			selectNextTab: (ev) => {
				let index = this._items.indexOf(this._active_item);

				if (index > -1 && index < this._items.length - 1) {
					this._items[index + 1].select();
				}
			},

			/**
			 * Action on 'chevron down' button press. Creates dropdown with list of existing tabs.
			 */
			toggleTabsList: (ev) => {
				let items = [],
					dropdown = [{
						items: [{
								label: t('Home'),
								clickCallback: (ev) => this._items[0].select()
							}]
					}];

				if (this._items.length > 2) {
					for (const item of this._items.slice(1, -1)) {
						items.push({
							label: item._data.name,
							clickCallback: (ev) => item.select()
						});
					}
					dropdown.push({items: items});
				}

				$(this._target).menuPopup(dropdown, $(ev), {
					position: {
						of: ev.target,
						my: 'left bottom',
						at: 'left top'
					}
				});
			},

			/**
			 * Action on 'Update' button press.
			 */
			update: () => {
				var params = this.getActiveFilterParams();

				this.profileUpdate('properties', {
					'idx2[]': this._active_item._index,
					'value_str': params.toString()
				}).then(() => {
					this.setBrowserLocation(params);
				});
			},

			/**
			 * Action on 'Save as' button press, refresh whole page for simplisity.
			 */
			create: () => {
				let filter = this.getActiveFilterParams(),
					url = new Curl('', false);

				filter.set('filter_name', t('Untitled'));
				filter.set('action', url.getArgument('action'));
				window.location.search = filter.toString();
			},

			/**
			 * Action on 'Apply' button press.
			 */
			apply: () => {
				this.setBrowserLocation(this.getActiveFilterParams());
			},

			/**
			 * Action on 'Reset' button press.
			 */
			reset: () => {
				this.setBrowserLocation(new URLSearchParams());
				window.location.reload(true);
			}
		}

		for (const item of this._items) {
			item.on(TABFILTERITEM_EVENT_EXPAND_BEFORE, this._events.expand);
			item.on(TABFILTERITEM_EVENT_COLLAPSE, this._events.collapse);
			item.on(TABFILTERITEM_EVENT_DELETE, this._events.deleteItem);
		}

		$('.ui-sortable', this._target).sortable({
			update: this._events.tabSortChanged,
			axis: 'x',
			containment: 'parent'
		});

		for (const action of this._target.querySelectorAll('nav [data-action]')) {
			action.addEventListener('click', this._events[action.getAttribute('data-action')]);
		}

		this._shared_domnode.querySelector('[name="filter_update"]').addEventListener('click', this._events.update);
		this._shared_domnode.querySelector('[name="filter_new"]').addEventListener('click', this._events.create);
		this._shared_domnode.querySelector('[name="filter_apply"]').addEventListener('click', this._events.apply);
		this._shared_domnode.querySelector('[name="filter_reset"]').addEventListener('click', this._events.reset);
	}

	/**
	 * Create new CTabFilterItem object with it container if it does not exists and append to _items array.
	 *
	 * @param {HTMLElement} title  HTML node element of tab label.
	 * @param {object}      data   Filter item dynamic data for template.
	 *
	 * @return {CTabFilterItem}
	 */
	create(title, data) {
		let item,
			containers = this._target.querySelector('.tabfilter-tabs-container'),
			container = containers.querySelector('#' + title.getAttribute('data-target'));

		if (!container) {
			container = document.createElement('div');
			container.setAttribute('id', title.getAttribute('data-target'));
			container.classList.add('display-none');
			containers.appendChild(container);
		}

		item = new CTabFilterItem(title, {
			idx_namespace: this._idx_namespace,
			index: this._items.length,
			expanded: data.expanded||false,
			can_toggle: this._options.can_toggle,
			container: container,
			data: data,
			template: this._templates[data.template]||null
		});

		item._parent = this;
		this._items.push(item);

		return item;
	}

	/**
	 * Fire event TABFILTERITEM_EVENT_COLLAPSE on every expanded tab except passed one.
	 *
	 * @param {CTabFilterItem} except  Tab item object.
	 */
	collapseAllItemsExcept(except) {
		for (const item of this._items) {
			if (item !== except && item._expanded) {
				item.fire(TABFILTERITEM_EVENT_COLLAPSE)
			}
		}
	}

	/**
	 * Updates filter values in user profile. Aborts any previous unfinished updates.
	 *
	 * @param {string} property  Filter property to be updated: 'selected', 'expanded', 'properties'.
	 * @param {object} body      Key value pair of data to be passed to profile.update action.
	 *
	 * @return {Promise}
	 */
	profileUpdate(property, body) {
		if (this._fetch && 'abort' in this._fetch && !this._fetch.aborted) {
			this._fetch.abort();
		}

		body.idx = this._idx_namespace + '.' + property;
		this._fetch = new AbortController();

		return fetch('zabbix.php?action=profile.update', {
			method: 'POST',
			signal: this._fetch.signal,
			body: new URLSearchParams(body)
		}).then(() => {
			this._fetch = null;
		}).catch((err) => {
			// Catch DOMExeception: The user aborted a request.
		});
	}

	/**
	 * Get active filter parameters as URLSearchParams object, defining value of unchecked checkboxes equal to
	 * 'unchecked-value' attribute value.
	 *
	 * @return {URLSearchParams}
	 */
	getActiveFilterParams() {
		let form = this._active_item._content_container.querySelector('form'),
			params = new URLSearchParams(new FormData(form));

		for (const checkbox of form.querySelectorAll('input[type="checkbox"][unchecked-value]')) {
			if (!checkbox.checked) {
				params.set(checkbox.getAttribute('name'), checkbox.getAttribute('unchecked-value'))
			}
		}

		return params;
	}

	/**
	 * Set browser location URL according to passed values. Argument 'action' from already set URL is preserved.
	 * Create TABFILTER_EVENT_URLSET event with detail.target equal instance of CTabFilter.
	 *
	 * @param {URLSearchParams} search_params  Filter field values to be set in URL.
	 */
	setBrowserLocation(search_params) {
		let url = new Curl('', false);

		search_params.set('action', url.getArgument('action'));
		url.query = search_params.toString();
		url.formatArguments();
		history.replaceState(history.state, '', url.getUrl());
		this.fire(TABFILTER_EVENT_URLSET);
	}

	/**
	 * Update all tab filter counters values.
	 *
	 * @param {array} counters  Array of counters to be set.
	 */
	updateCounters(counters) {
		counters.forEach((value, index) => {
			let item = this._items[index];

			if (!item) {
				return;
			}

			if (item._data.show_counter) {
				item.setCounter(value);
			}
			else {
				item.removeCounter();
			}
		});
	}
}
