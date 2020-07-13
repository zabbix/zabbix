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

	_options = {};

	/**
	 * Array of CTabFilterItem objects.
	 */
	_items = [];
	_active_item;
	_shared_domnode;

	/**
	 * NodeList of available templates (<script> DOM elements).
	 */
	_templates;

	constructor(target, options) {
		super(target);
		this._options = options;

		this.init(options);
		this.registerEvents(options);
	}

	init(options) {
		let container, item;

		for (const title of this._target.childNodes.item(0).querySelectorAll('[data-target]')) {
			container = this._target.querySelector(title.getAttribute('data-target'));
			item = new CTabFilterItem(title, container);
			item._can_toggle = options.can_toggle;

			this._items.push(item);
		}

		this._templates = this._target.querySelectorAll('[type="text/x-jquery-tmpl"]');
		this._shared_domnode = this._target.querySelector('.form-buttons');

		$('.ui-sortable', this._target).sortable({});
	}

	registerEvents(options) {
		this._events = {
			expand: (ev) => {
				this._active_item = ev.detail.target;
				this.collapseAllItemsExcept(this._active_item);

				if (!ev.detail.target._expanded && (!this._active_item || !this._active_item._expanded)) {
					this._shared_domnode.classList.remove('display-none');
					this.render();
				}
			},

			collapse: (ev) => {
				if (ev.detail.target === this._active_item) {
					this._shared_domnode.classList.add('display-none');
				}
			}
		}

		for (const item of this._items) {
			item.on(TABFILTERITEM_EVENT_EXPAND_BEFORE, this._events.expand);
			item.on(TABFILTERITEM_EVENT_COLLAPSE, this._events.collapse);
		}
	}

	collapseAllItemsExcept(except) {
		for (const item of this._items) {
			if (item !== except && item._expanded) {
				item.fire(TABFILTERITEM_EVENT_COLLAPSE)
			}
		}
	}

	render() {

	}
}
