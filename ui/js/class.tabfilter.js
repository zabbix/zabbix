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


const TABFILTER_EVENT_URLSET = 'urlset.tabfilter';
const TABFILTER_EVENT_UPDATE = 'update.tabfilter';
const TABFILTER_EVENT_NEWITEM = 'newitem.tabfilter';

class CTabFilter extends CBaseComponent {

	constructor(target, options) {
		super(target);
		this._options = options;
		// Array of CTabFilterItem objects.
		this._items = [];
		this._active_item = null;
		this._filters_footer = null;
		// NodeList of available templates (<script> DOM elements).
		this._templates = {};
		this._fetch = {};
		this._idx_namespace = options.idx;
		this._timeselector = null;

		this.init(options);
		this.registerEvents();
		this.initItemUnsavedState(this._active_item, this._active_item._data);

		if (this._timeselector instanceof CTabFilterItem) {
			this._timeselector._data = options.timeselector;
			this.updateTimeselector(this._active_item, this._timeselector._data.disabled);
		}
	}

	init(options) {
		let item, index = 0;

		this._filters_footer = this._target.querySelector('.form-buttons');

		if (options.expanded) {
			options.data[options.selected].expanded = true;
		}

		for (const template of this._target.querySelectorAll('[type="text/x-jquery-tmpl"][data-template]')) {
			this._templates[template.getAttribute('data-template')] = template;
		}

		for (const title of this._target.querySelectorAll('nav [data-target]')) {
			item = this.create(title, options.data[index] || {});

			if (index > 0) {
				item.initUnsavedIndicator();
			}

			if (options.selected == index) {
				item.renderContentTemplate();
				this.setSelectedItem(item);

				if (options.expanded) {
					item.setExpanded();
				}
			}

			index++;
		}
	}

	/**
	 * Ensures item label is visible in tab filter navigation.
	 *
	 * @param {CTabfilterItem} item    Filter item object.
	 */
	scrollIntoView(item) {
		let scrollable_parent = item._target.closest('.ui-sortable-container').parentNode;

		scrollable_parent.scrollLeft = item._target.parentNode.offsetLeft - item._target.parentNode.clientWidth;
	}

	/**
	 * Render filter with profiles stored data to hidden container to get source url for unsaved state comparison.
	 *
	 * @param {CTabfilterItem} item    Selected filter object.
	 * @param {object} filter_data     Selected filter object filter data.
	 */
	initItemUnsavedState(item, filter_data) {
		let filter_src = {...{tab_view: filter_data.tab_view}, ...filter_data.filter_src},
			target = item._target.parentNode.cloneNode(true),
			src_item;

		filter_src.uniqid = filter_data.uniqid + '__clone';
		filter_src.filter_configurable = filter_data.filter_configurable;
		target.setAttribute('data-target', target.getAttribute('data-target') + '__clone');
		src_item = this.create(target, filter_src);
		this._items.pop();

		src_item.renderContentTemplate();
		item._src_url = src_item._src_url;
		src_item.delete();
		item.updateUnsavedState();
	}

	/**
	 * Delete item from items collection.
	 */
	delete(item) {
		let index = this._items.indexOf(item);

		if (index != -1) {
			this.setSelectedItem(this._items[index - 1]);

			if (item._expanded) {
				this._active_item.setExpanded();
			}

			item.delete();
			delete this._items[index];
			this._items.splice(index, 1);
			this._items.forEach((item, index) => {
				item._index = index;
			});
			this._active_item.setBrowserLocation(this._active_item.getFilterParams());
		}
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

		item = new CTabFilterItem(title.querySelector('a'), {
			parent: this,
			idx_namespace: this._idx_namespace,
			index: this._items.length,
			expanded: data.expanded || false,
			container: container,
			data: data,
			template: this._templates[data.tab_view] || null,
			support_custom_time: this._options.support_custom_time
		});

		this._items.push(item);

		if (title.getAttribute('data-target') === 'tabfilter_timeselector') {
			this._timeselector = item;
		}

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
				item.fire(TABFILTERITEM_EVENT_COLLAPSE);
			}
		}
	}

	/**
	 * Update timeselector tab and timeselector buttons accessibility according passed item.
	 *
	 * @param {CTabFilterItem} item     Tab item object.
	 * @param {bool}           disable  Additional status to determine should the timeselector to be disabled or not.
	 */
	updateTimeselector(item, disable) {
		if (!this._timeselector) {
			return;
		}

		let disabled = disable || (!this._options.support_custom_time || item.hasCustomTime()),
			buttons = {
				decrement_button: this._target.querySelector('button.btn-time-left'),
				increment_button: this._target.querySelector('button.btn-time-right'),
				zoomout_button: this._target.querySelector('button.btn-time-out')
			};

		this._timeselector.setDisabled(disabled);
		this._timeselector._target.setAttribute('tabindex', disabled ? -1 : 0);

		if (disabled || !this._timeselector._data.can_decrement) {
			buttons.decrement_button.setAttribute('disabled', 'disabled');
		}
		else {
			buttons.decrement_button.removeAttribute('disabled');
		}

		if (disabled || !this._timeselector._data.can_increment) {
			buttons.increment_button.setAttribute('disabled', 'disabled');
		}
		else {
			buttons.increment_button.removeAttribute('disabled');
		}

		if (disabled || !this._timeselector._data.can_zoomout) {
			buttons.zoomout_button.setAttribute('disabled', 'disabled');
		}
		else {
			buttons.zoomout_button.removeAttribute('disabled');
		}
	}

	/**
	 * Updates filter values in user profile. Aborts previous unfinished update of property.
	 *
	 * @param {string} property  Filter property to be updated: 'selected', 'expanded', 'properties', 'taborder'.
	 * @param {object} body      Key value pair of data to be passed to profile.update action.
	 *
	 * @return {Promise}
	 */
	profileUpdate(property, body) {
		let url = new Curl('zabbix.php'),
			signal = null;

		url.setArgument('action', 'tabfilter.profile.update');
		this.fire(TABFILTER_EVENT_UPDATE, {filter_property: property});

		if (this._fetch[property] && ('abort' in this._fetch[property]) && !this._fetch[property].aborted) {
			this._fetch[property].abort();
		}

		body.idx = this._idx_namespace + '.' + property;

		if (property !== 'properties') {
			this._fetch[property] = new AbortController();
			signal = this._fetch[property].signal;
		}

		return fetch(url.getUrl(), {
			method: 'POST',
			signal: signal,
			body: new URLSearchParams(body)
		})
		.then(() => {
			this._fetch[property] = null;
		})
		.catch(() => {
			// User aborted a request.
		});
	}

	/**
	 * Update all tab filter counters values.
	 *
	 * @param {array} counters  Array of counters to be set.
	 */
	updateCounters(counters) {
		counters.forEach((value, index) => {
			let item = this._items[index];

			if (item) {
				item.updateCounter(value);
			}
		});

		if (this._active_item !== this._timeselector) {
			this.scrollIntoView(this._active_item);
		}
	}

	/**
	 * Set item object as current selected item, also ensures that only one selected item with filters form exists.
	 *
	 * @param {CTabFilterItem} item  Item object to be set as selected item.
	 */
	setSelectedItem(item) {
		this._active_item = item;
		this._active_item.unsetExpandedSubfilters();
		item.setSelected();

		if (item !== this._timeselector) {
			item._target.setAttribute('tabindex', 0);
			this.scrollIntoView(item);
			item.setBrowserLocationToApplyUrl();
		}

		for (const _item of this._items) {
			if (_item !== this._active_item && this._timeselector !== this._active_item) {
				_item.removeSelected();

				if (_item !== this._timeselector) {
					_item._target.setAttribute('tabindex', -1);
				}
			}
		}
	}

	/**
	 * Get first selected item. Return null if no item is selected.
	 *
	 * @return {CTabFilterItem}
	 */
	getSelectedItem() {
		for (const item of this._items) {
			if (item.isSelected()) {
				return item;
			}
		}

		return null;
	}

	/**
	 * Enable subfilter option by key and value.
	 */
	setSubfilter(key, value) {
		this._active_item.setSubfilter(key, value);
		this._active_item.updateUnsavedState();
		this._active_item.updateApplyUrl();
		this._active_item.setBrowserLocationToApplyUrl();
	}

	/**
	 * Disable subfilter option by key and value.
	 */
	unsetSubfilter(key, value) {
		this._active_item.unsetSubfilter(key, value);
		this._active_item.updateUnsavedState();
		this._active_item.updateApplyUrl();
		this._active_item.setBrowserLocationToApplyUrl();
	}

	/**
	 * Set expanded subfilter name.
	 */
	setExpandedSubfilters(name) {
		return this._active_item.setExpandedSubfilters(name);
	}

	/**
	 * Retrieve expanded subfilter names.
	 *
	 * @returns {array}
	 */
	getExpandedSubfilters() {
		return this._active_item.getExpandedSubfilters();
	}

	/**
	 * Register tab filter events, called once during initialization.
	 */
	registerEvents() {
		this._events = {
			/**
			 * Event handler on tab content expand.
			 */
			select: (ev) => {
				let item = ev.detail.target,
					expand = (this._active_item._expanded
						|| (item === this._timeselector && this._active_item !== this._timeselector)
						|| (item !== this._timeselector && this._active_item === this._timeselector)
					);

				if (item !== this._timeselector) {
					if (item.isSelected()) {
						this.profileUpdate('expanded', {
							value_int: item._expanded ? 0 : 1
						}).then(() => {
							this._options.expanded = +item._expanded;
						});
					}
					else {
						item.setFocused();
						item.initUnsavedState();
						this.profileUpdate('selected', {
							value_int: item._index
						}).then(() => {
							this._options.selected = item._index;
						});
					}

					this.scrollIntoView(item);
				}

				if (item !== this._active_item) {
					this.setSelectedItem(item);
					this.collapseAllItemsExcept(item);

					if (expand) {
						item.setFocused();
						item.fire(TABFILTERITEM_EVENT_EXPAND);
					}
				}
				else if (!item._expanded) {
					item.fire(TABFILTERITEM_EVENT_EXPAND);
				}
				else {
					item.fire(TABFILTERITEM_EVENT_COLLAPSE);
				}

				if (this._timeselector instanceof CTabFilterItem && item !== this._timeselector
						&& this._timeselector._expanded) {
					this._timeselector.removeExpanded();
				}
			},

			expand: (ev) => {
				let item = ev.detail.target;

				if (item !== this._timeselector) {
					this._filters_footer.classList.remove('display-none');
				}
				else {
					this._filters_footer.classList.add('display-none');
				}

				item.setExpanded();

				const tabfilter = this._target.querySelector('.tabfilter-content-container');
				tabfilter.classList.remove('tabfilter-collapsed', 'display-none');
			},

			/**
			 * Event handler on tab content collapse.
			 */
			collapse: (ev) => {
				let item = ev.detail.target;

				if (item !== this._timeselector) {
					item.updateUnsavedState();
				}

				item.removeExpanded();
				const tabfilter = this._target.querySelector('.tabfilter-content-container');
				if (tabfilter.querySelector('.tabfilter-subfilter')) {
					tabfilter.classList.add('tabfilter-collapsed');
				}
				else {
					tabfilter.classList.add('tabfilter-collapsed', 'display-none');
				}
			},

			/**
			 * UI sortable update event handler. Updates tab sorting in profile.
			 */
			tabSortChanged: (ev, ui) => {
				// Update order of this._items array.
				let from,
					to,
					item_moved;
				const target = ui.item[0].querySelector('[data-target] .tabfilter-item-link');

				this._items.forEach((item, index) => from = (item._target === target) ? index : from);
				this._target.querySelectorAll('nav [data-target] .tabfilter-item-link')
					.forEach((elm, index) => to = (elm === target) ? index : to);

				item_moved = this._items[from];
				this._items.splice(from, 1);
				this._items.splice(to, 0, item_moved);

				// Tab order changed, update changes via ajax.
				let value_str = this._items.map((item) => item._index).join(',');

				this.profileUpdate('taborder', {
					value_str: value_str
				})
				.then(() => {
					this._items.forEach((item, index) => {
						item._index = index;
					});
				});
			},

			/**
			 * Event handler for 'Delete' button.
			 *
			 * @param {object} ev.detail.idx2  Index of deleted tab.
			 */
			deleteFilterTab: (ev) => {
				this.delete(this._items[ev.detail.idx2]);
			},

			/**
			 * Event handler for 'Save as' button and on filter modal close.
			 */
			updateActiveFilterTab: (ev) => {
				var item = this.getSelectedItem(),
					params;

				if (ev.detail.create == '1') {
					item = this.create(item._target.parentNode.cloneNode(true), {});
				}

				item.update(ev.detail);

				if (ev.detail.create == '1') {
					// Allow to tab filter initialization code modify values of new created filter.
					this.fire(TABFILTER_EVENT_NEWITEM, {item: item});
					params = item.getFilterParams();

					// Popup were created by 'Save as' button, reload page for simplicity.
					this.profileUpdate('properties', {
						'idx2': ev.detail.idx2,
						'value_str': params.toString()
					})
					.then(() => {
						item.setBrowserLocation(params);
						window.location.reload(true);
					});
				}
				else {
					params = item.getFilterParams();
					this.setSelectedItem(item);
					this.fire(TABFILTER_EVENT_UPDATE, {filter_property: 'properties'});

					if (this._timeselector instanceof CTabFilterItem && this._timeselector._expanded
							&& params.get('filter_custom_time') == 1) {
						this._timeselector.fire(TABFILTERITEM_EVENT_COLLAPSE);

						if (this._options.expanded) {
							item.fire(TABFILTERITEM_EVENT_EXPAND);
						}
					}
				}
			},

			/**
			 * Action on 'chevron left' button press. Select previous active tab filter.
			 */
			selectPrevTab: () => {
				let index = this._items.indexOf(this._active_item);

				if (index > 0) {
					this._items[index - 1].fire(TABFILTERITEM_EVENT_SELECT);
				}
			},

			/**
			 * Action on 'chevron right' button press. Select next active tab filter.
			 */
			selectNextTab: () => {
				let index = this._items.indexOf(this._active_item);

				if (index > -1 && index < this._items.length - 1 && this._items[index + 1] !== this._timeselector) {
					this._items[index + 1].fire(TABFILTERITEM_EVENT_SELECT);
				}
			},

			/**
			 * Action on 'chevron down' button press. Creates dropdown with list of existing tabs.
			 */
			toggleTabsList: (ev) => {
				let dropdown_items = [],
					dropdown = [{
						items: [{
							label: this._items[0]._target.getAttribute('aria-label'),
							clickCallback: () => {
								if (this._active_item !== this._items[0]) {
									this._items[0].fire(TABFILTERITEM_EVENT_SELECT);
									// Set selected item focus after popup menu will focus it opener element (used for ESC).
									setTimeout(() => this._items[0].setFocused(), 0);
								}
							}
						}]
					}],
					items = this._timeselector ? this._items.slice(1, -1) : this._items.slice(1);

				if (items.length) {
					for (const item of items) {
						dropdown_items.push({
							label: item._data.filter_name,
							dataAttributes: (item._data.filter_show_counter && !item._unsaved)
								? {'data-counter': item.getCounter()} : [],
							clickCallback: () => {
								if (this._active_item !== item) {
									item.fire(TABFILTERITEM_EVENT_SELECT);
									// Set selected item focus after popup menu will focus it opener element (used for ESC).
									setTimeout(() => item.setFocused(), 0);
								}
							}
						});
					}

					dropdown.push({items: dropdown_items});
				}

				$(this._target).menuPopup(dropdown, new jQuery.Event(ev), {
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
			buttonUpdateAction: () => {
				var params = this._active_item.getFilterParams();

				this.profileUpdate('properties', {
					idx2: this._active_item._index,
					value_str: params.toString()
				})
				.then(() => {
					this._active_item.updateApplyUrl();
					this._active_item.setBrowserLocation(params);
					this._active_item.resetUnsavedState();
				});
			},

			/**
			 * Action on 'Save as' button press, open properties popup.
			 */
			buttonSaveAsAction: (ev) => {
				this._active_item.openPropertiesDialog({
					create: 1,
					idx2: this._items.length,
					support_custom_time: this._options.support_custom_time
				}, ev.target);
			},

			/**
			 * Action on 'Apply' button press.
			 */
			buttonApplyAction: () => {
				this._active_item.unsetExpandedSubfilters();
				this._active_item.emptySubfilter();
				this._active_item.updateUnsavedState();
				this._active_item.updateApplyUrl();
				this._active_item.setBrowserLocationToApplyUrl();
			},

			/**
			 * Action on 'Reset' button press.
			 */
			buttonResetAction: () => {
				let current_url = new Curl(),
					url = new Curl('zabbix.php', false);

				url.setArgument('action', current_url.getArgument('action'));
				url.setArgument('filter_reset', 1);
				window.location.href = url.getUrl();
			},

			/**
			 * Trigger TABFILTERITEM_EVENT_ACTION on active item passing clicked button name as action parameter.
			 */
			buttonActionNotify: (ev) => {
				if (ev.target instanceof HTMLButtonElement && this._active_item instanceof CTabFilterItem) {
					this._active_item.fire(TABFILTERITEM_EVENT_ACTION, {action: ev.target.getAttribute('name')});
				}
			},

			/**
			 * Keydown handler for keyboard navigation support.
			 */
			keydown: (ev) => {
				if (ev.key !== 'ArrowLeft' && ev.key !== 'ArrowRight') {
					return;
				}

				let path = ev.path || (ev.composedPath && ev.composedPath()),
					focused_item = this._active_item,
					index;

				if (path && path.indexOf(this._target.querySelector('nav')) > -1) {
					for (const item of this._items) {
						if (item._target.parentNode.classList.contains(TABFILTERITEM_STYLE_FOCUSED)) {
							focused_item = item;
							break;
						}
					}

					index = focused_item._index + ((ev.key === 'ArrowRight') ? 1 : -1);

					if (this._items[index] instanceof CTabFilterItem && this._items[index] !== this._timeselector) {
						this._items[index].setFocused();
					}

					cancelEvent(ev);
				}
			},

			/**
			 * Scroll horizontally with mouse wheel handler for sortable items container.
			 */
			mouseWheelHandler: (container, ev) => {
				if ((ev.deltaY < 0 && container.scrollLeft > 0)
						|| (ev.deltaY > 0 && container.scrollLeft < container.scrollWidth - container.clientWidth)) {
					container.scrollBy({left: ev.deltaY});
				}
			}
		}

		for (const item of this._items) {
			item
				.on(TABFILTERITEM_EVENT_SELECT, this._events.select)
				.on(TABFILTERITEM_EVENT_EXPAND, this._events.expand)
				.on(TABFILTERITEM_EVENT_COLLAPSE, this._events.collapse)
				.on(TABFILTERITEM_EVENT_URLSET, () => this.fire(TABFILTER_EVENT_URLSET));
		}

		$('.ui-sortable-container', this._target).sortable({
			items: '.tabfilter-item-label:not(:first-child)',
			update: this._events.tabSortChanged,
			stop: (_, ui) => {
				const $item = ui.item;

				/**
				 * Remove inline style position, left and top that stay after sortable.
				 * This styles broken tabs layout.
				 */
				if ($item.css('position') === 'relative') {
					$item.css({
						'position': '',
						'left': '',
						'top': ''
					});
				}
			},
			axis: 'x',
			containment: 'parent'
		});

		const container = this._target.querySelector('.ui-sortable-container').parentNode;

		try {
			addEventListener('test', null, {get passive() {}});
			container.addEventListener('wheel', ev => this._events.mouseWheelHandler(container, ev), {passive: true});
		}
		catch(e) {
			container.addEventListener('wheel', ev => this._events.mouseWheelHandler(container, ev));
		}

		for (const action of this._target.querySelectorAll('nav [data-action]')) {
			action.addEventListener('click', this._events[action.getAttribute('data-action')]);
		}

		this._filters_footer.querySelector('[name="filter_update"]')
			.addEventListener('click', this._events.buttonUpdateAction);
		this._filters_footer.querySelector('[name="filter_new"]')
			.addEventListener('click', this._events.buttonSaveAsAction);
		this._filters_footer.querySelector('[name="filter_apply"]')
			.addEventListener('click', () => {
				if (this._active_item._index == 0) {
					this._events.buttonUpdateAction();
				}
				else {
					this._events.buttonApplyAction();
				}
			});
		this._filters_footer.querySelector('[name="filter_reset"]')
			.addEventListener('click', this._events.buttonResetAction);
		this._filters_footer.addEventListener('click', this._events.buttonActionNotify);

		this.on('keydown', this._events.keydown);
		this.on(TABFILTERITEM_EVENT_UPDATE, this._events.updateActiveFilterTab);
		this.on(TABFILTERITEM_EVENT_DELETE, this._events.deleteFilterTab);
		this.on('submit', (ev) => {
			ev.preventDefault();
			this._filters_footer.querySelector('[name="filter_apply"]').dispatchEvent(new CustomEvent('click'));
		});

		// Timeselector uses jQuery object as pub sub.
		$.subscribe('timeselector.rangeupdate', (e, data) => {
			Object.assign(this._timeselector._data, data);
			this.updateTimeselector(this._active_item, false);
		});
	}
}
