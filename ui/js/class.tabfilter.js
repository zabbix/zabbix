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

		this.init(options);
		this.registerEvents(options);
	}

	init(options) {
		let item, template, container, containers, data_index = 0;

		this._shared_domnode = this._target.querySelector('.form-buttons');
		containers = this._target.querySelector('.tabfilter-tabs-container');

		for (const template of this._target.querySelectorAll('[type="text/x-jquery-tmpl"][data-template]')) {
			this._templates[template.getAttribute('data-template')] = template;
		};

		for (const title of this._target.querySelectorAll('nav [data-target]')) {
			template = options.data[data_index] ? options.data[data_index].template : null;
			container = containers.querySelector('#'+title.getAttribute('data-target'));

			if (!container) {
				container = document.createElement('form');
				container.setAttribute('id', title.getAttribute('data-target'));
				container.setAttribute('method', 'get');
				// TODO: add action and other filter tab specific input variables except data
				container.classList.add('display-none');
				containers.appendChild(container);
			}

			item = new CTabFilterItem(title, {
				can_toggle: options.can_toggle,
				container: container,
				data: options.data[data_index],
				template: this._templates[template]
			});

			this._items.push(item);
			data_index++;

			if (item._expanded) {
				this._active_item = item;
			}
		}
	}

	registerEvents(options) {
		this._events = {
			expand: (ev) => {
				this._active_item = ev.detail.target;
				this.collapseAllItemsExcept(this._active_item);

				if (!ev.detail.target._expanded && (!this._active_item || !this._active_item._expanded)) {
					this._shared_domnode.classList.remove('display-none');
				}
			},

			collapse: (ev) => {
				if (ev.detail.target === this._active_item) {
					this._shared_domnode.classList.add('display-none');
				}
			},

			afterTabContentRender: (ev) => {
				this.afterTabContentRender(ev.detail.target);
			},

			tabSortChanged: (ev, ui) => {
				// Update order of this._items array.
				var from, to, target = ui.item[0].querySelector('[data-target]');

				this._items.forEach((item, index) => from = (item._target === target) ? index : from);
				this._target.querySelectorAll('nav [data-target]')
					.forEach((elm, index) => to = (elm === target) ? index : to);
				this._items[to] = this._items.splice(from, 1, this._items[to])[0];

				// Tab order changed, update changes via ajax.

			},

			selectPrevTab: (ev) => {
				let index = this._items.indexOf(this._active_item);

				if (index > 0) {
					this._items[index - 1].select();
				}
			},

			selectNextTab: (ev) => {
				let index = this._items.indexOf(this._active_item);

				if (index > -1 && index < this._items.length - 1) {
					this._items[index + 1].select();
				}
			},

			toggleTabsList: (ev) => {
				var dropdown = [{items: []}, {items: []}];

				this._items.forEach((item, index) => {
					dropdown[index ? 1 : 0].items.push({
						label: index ? item._data.label : t('Home'),
						clickCallback: (ev) => this._items[index].select()
					})
				});

				$(this._target).menuPopup(dropdown, $(ev), {
					position: {
						of: ev.target,
						my: 'left bottom',
						at: 'left top'
					}
				});
			}
		}

		for (const item of this._items) {
			item.on(TABFILTERITEM_EVENT_EXPAND_BEFORE, this._events.expand);
			item.on(TABFILTERITEM_EVENT_COLLAPSE, this._events.collapse);
			item.on(TABFILTERITEM_EVENT_AFTER_RENDER, this._events.afterTabContentRender);
		}

		$('.ui-sortable', this._target).sortable({
			update: this._events.tabSortChanged,
			axis: 'x',
			containment: 'parent'
		});

		for (const action of this._target.querySelectorAll('nav [data-action]')) {
			action.addEventListener('click', this._events[action.getAttribute('data-action')]);
		}
	}

	collapseAllItemsExcept(except) {
		for (const item of this._items) {
			if (item !== except && item._expanded) {
				item.fire(TABFILTERITEM_EVENT_COLLAPSE)
			}
		}
	}

	afterTabContentRender(tabitem) {
	}
}
