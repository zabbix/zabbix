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
		this._filters_footer = null;
		// NodeList of available templates (<script> DOM elements).
		this._templates = {};
		this._fetchpromise = null;
		this._idx_namespace = options.idx;
		this._timeselector = null;

		this.init(options);
		this.registerEvents(options);
	}

	init(options) {
		let item, index = 0;

		this._filters_footer = this._target.querySelector('.form-buttons');

		if (options.expanded) {
			options.data[options.selected].expanded = true;
		}

		for (const template of this._target.querySelectorAll('[type="text/x-jquery-tmpl"][data-template]')) {
			this._templates[template.getAttribute('data-template')] = template;
		};

		for (const title of this._target.querySelectorAll('nav [data-target]')) {
			item = this.create(title, options.data[index] || {});

			if (options.selected === index) {
				if (!item._expanded) {
					item.renderContentTemplate();
				}

				let url = window.location.search,
					params = item.getFilterParams();

				this.setSelectedItem(item);

				if (options.page !== null) {
					params.set('page', options.page);
				}

				item.setBrowserLocation(params);

				if (url === window.location.search) {
					item._src_url = options.src_url;
					item.updateUnsavedState();
				}
			}

			if (item._expanded) {
				item.setExpanded();
			}

			index++;
		}

		if (this._active_item) {
			this._active_item._target.parentNode.scrollIntoView();
		}
	}

	/**
	 * Delete item from items collection.
	 */
	delete(item) {
		let index = this._items.indexOf(item);

		if (index > -1) {
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
			expanded: data.expanded||false,
			can_toggle: this._options.can_toggle,
			container: container,
			data: data,
			template: this._templates[data.tab_view]||null,
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
				item.fire(TABFILTERITEM_EVENT_COLLAPSE)
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
		let disabled = disable || (!this._options.support_custom_time || item.hasCustomTime()),
			buttons = this._target.querySelectorAll('button.btn-time-left,button.btn-time-out,button.btn-time-right');

		if (this._timeselector) {
			this._timeselector.setDisabled(disabled);

			for (const button of buttons) {
				if (disabled) {
					button.setAttribute('disabled', 'disabled');
				}
				else {
					button.removeAttribute('disabled');
				}
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

		return fetch('zabbix.php?action=tabfilter.profile.update', {
			method: 'POST',
			signal: this._fetch.signal,
			body: new URLSearchParams(body)
		}).then(() => {
			this._fetch = null;
		}).catch(() => {
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

			if (!item) {
				return;
			}

			if (item._data.filter_show_counter) {
				item.setCounter(value);
			}
			else {
				item.removeCounter();
			}
		});

		if (this._active_item) {
			// Position of selected item is changed, update it to ensure label is visible.
			this._active_item.setSelected();
		}
	}

	/**
	 * Set item object as current selected item, also ensures that only one selected item exists.
	 *
	 * @param {CTabFilterItem} item  Item object to be set as selected item.
	 */
	setSelectedItem(item) {
		this._active_item = item;
		item.setSelected();
		item.setBrowserLocation(item.getFilterParams());

		for (const _item of this._items) {
			if (_item !== this._active_item) {
				_item.removeSelected();
			}
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
			select: (ev) => {
				let item = ev.detail.target;

				if (item != this._active_item) {
					if (this._active_item._expanded) {
						item.setExpanded();
					}

					this.setSelectedItem(item);
					this.collapseAllItemsExcept(item);
					item.initUnsavedState();
					this.profileUpdate('selected', {
						value_int: this._active_item._index
					});
				}
				else if (!item._expanded) {
					item.fire(TABFILTERITEM_EVENT_EXPAND);
				}
				else if (item._can_toggle) {
					item.fire(TABFILTERITEM_EVENT_COLLAPSE);
				}
			},

			expand: (ev) => {
				if (ev.detail.target != this._timeselector) {
					this.setSelectedItem(ev.detail.target);
					this._filters_footer.classList.remove('display-none');
					this.profileUpdate('expanded', {
						value_int: 1
					});
				}
				else {
					this._filters_footer.classList.add('display-none');
				}

				this.collapseAllItemsExcept(ev.detail.target);
				this._target.querySelector('.tabfilter-content-container').classList.remove('display-none');
			},

			/**
			 * Event handler on tab content collapse.
			 */
			collapse: (ev) => {
				let item = ev.detail.target;

				if (item === this._active_item) {
					this._target.querySelector('.tabfilter-content-container').classList.add('display-none');
					this.profileUpdate('expanded', {
						value_int: 0
					});
				}

				item.updateUnsavedState();
			},

			/**
			 * UI sortable update event handler. Updates tab sorting in profile.
			 */
			tabSortChanged: (ev, ui) => {
				// Update order of this._items array.
				var from, to, item_moved, target = ui.item[0].querySelector('[data-target] .tabfilter-item-link');

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
				}).then(() => {
					this._items.forEach((item, index) => {
						item._index = index;
					});
				});
			},

			/**
			 * Event handler for 'Delete' button
			 */
			deleteActiveFilterTab: (ev) => {
				this.delete(this._active_item);
			},

			/**
			 * Event handler for 'Save as' button
			 */
			updateActiveFilterTab: (ev) => {
				var item = (ev.detail.create == '1')
					? this.create(this._active_item._target.parentNode.cloneNode(true), {})
					: this._active_item;

				item.update(ev.detail);
				var params = item.getFilterParams();

				if (ev.detail.create == '1') {
					// Popup were created by 'Save as' button, reload page for simplicity.
					this.profileUpdate('properties', {
						'idx2': ev.detail.idx2,
						'value_str': params.toString()
					}).then(() => {
						item.setBrowserLocation(params);
						window.location.reload(true);
					});
				}
				else {
					item.setSelected();
				}
			},

			/**
			 * Action on 'chevron left' button press. Select previous active tab filter.
			 */
			selectPrevTab: (ev) => {
				let index = this._items.indexOf(this._active_item);

				if (index > 0) {
					let expanded = this._active_item._expanded;

					this._active_item.removeExpanded();
					this.setSelectedItem(this._items[index - 1]);

					if (expanded) {
						this._active_item.fire(TABFILTERITEM_EVENT_EXPAND);
					}
				}
			},

			/**
			 * Action on 'chevron right' button press. Select next active tab filter.
			 */
			selectNextTab: (ev) => {
				let index = this._items.indexOf(this._active_item);

				if (index > -1 && index < this._items.length - 1 && this._items[index + 1] !== this._timeselector) {
					let expanded = this._active_item._expanded;

					this._active_item.removeExpanded();
					this.setSelectedItem(this._items[index + 1]);

					if (expanded) {
						this._active_item.fire(TABFILTERITEM_EVENT_EXPAND);
					}
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
							clickCallback: () => this._items[0].fire(TABFILTERITEM_EVENT_EXPAND)
						}]
					}],
					items = this._timeselector ? this._items.slice(1, -1) : this._items.slice(1);

				if (items.length) {
					for (const item of items) {
						dropdown_items.push({
							label: item._data.filter_name,
							dataAttributes: (item._data.filter_show_counter && !item._unsaved)
								? {'data-counter': item.getCounter()} : [],
							clickCallback: () => item.fire(TABFILTERITEM_EVENT_EXPAND)
						});
					}

					dropdown.push({items: dropdown_items});
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
			buttonUpdateAction: () => {
				var params = this._active_item.getFilterParams();

				this.profileUpdate('properties', {
					idx2: this._active_item._index,
					value_str: params.toString()
				}).then(() => {
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
					idx2: this._items.length
				}, ev.target);
			},

			/**
			 * Action on 'Apply' button press.
			 */
			buttonApplyAction: () => {
				this._active_item.updateUnsavedState();
				this._active_item.setBrowserLocation(this._active_item.getFilterParams());
			},

			/**
			 * Action on 'Reset' button press.
			 */
			buttonResetAction: () => {
				this._active_item.setBrowserLocation(new URLSearchParams());
				window.location.reload(true);
			},

			/**
			 * Keydown handler for keyboard navigation support.
			 */
			keydown: (ev) => {
				if (ev.key !== 'ArrowLeft' && ev.key !== 'ArrowRight') {
					return;
				}

				if (ev.path.indexOf(this._target.querySelector('nav')) > -1) {
					this._events[(ev.key == 'ArrowRight') ? 'selectNextTab' : 'selectPrevTab']();
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
			axis: 'x',
			containment: 'parent'
		});

		const container = this._target.querySelector('.ui-sortable-container').parentNode;

		try {
			addEventListener('test', null, {get passive() {}});
			container.addEventListener('wheel', ev => this._events.mouseWheelHandler(container, ev), {passive:true});
		} catch(e) {
			container.addEventListener('wheel', ev => this._events.mouseWheelHandler(container, ev));
		}

		for (const action of this._target.querySelectorAll('nav [data-action]')) {
			action.addEventListener('click', this._events[action.getAttribute('data-action')]);
		}

		this._filters_footer.querySelector('[name="filter_update"]').addEventListener('click', this._events.buttonUpdateAction);
		this._filters_footer.querySelector('[name="filter_new"]').addEventListener('click', this._events.buttonSaveAsAction);
		this._filters_footer.querySelector('[name="filter_apply"]').addEventListener('click', this._events.buttonApplyAction);
		this._filters_footer.querySelector('[name="filter_reset"]').addEventListener('click', this._events.buttonResetAction);

		this.on('keydown', this._events.keydown);
		this.on(TABFILTERITEM_EVENT_UPDATE, this._events.updateActiveFilterTab);
		this.on(TABFILTERITEM_EVENT_DELETE, this._events.deleteActiveFilterTab);
		this.on('submit', (ev) => {
			ev.preventDefault();
			this._filters_footer.querySelector('[name="filter_apply"]').dispatchEvent(new CustomEvent('click'));
		});
	}
}
