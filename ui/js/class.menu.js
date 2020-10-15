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


const MENU_EXPAND_SELECTED_DELAY = 5000;

const MENU_EVENT_BLUR            = 'blur';
const MENU_EVENT_EXPAND          = 'expand';
const MENU_EVENT_FOCUS           = 'focus';

class CMenu extends CBaseComponent {

	constructor(target) {
		super(target);

		this.init();
		this.registerEvents();
	}

	init() {
		this._expanded_item = null;
		this._selected_item = null;
		this._items = [];

		for (const el of this._target.childNodes) {
			const item = new CMenuItem(el);
			if (item.isExpanded()) {
				this._expanded_item = item;
			}
			if (item.isSelected()) {
				this._selected_item = item;
			}
			this._items.push(item);
		}

		if (this.hasClass('submenu')) {
			this._target.style.maxHeight = this._target.scrollHeight + 'px';
		}
	}

	getItems() {
		return this._items;
	}

	collapseExpanded() {
		this._expanded_item !== null && this._expanded_item.collapseSubmenu();
		this._expanded_item = null;
	}

	expandSelected() {
		if (this._selected_item !== null && this._selected_item !== this._expanded_item) {
			this.collapseExpanded();
			this._selected_item.hasSubmenu() && this._selected_item.expandSubmenu();
		}

		return this;
	}

	focusSelected() {
		if (this._selected_item !== null) {
			if (this._selected_item.hasSubmenu()) {
				this.expandSelected();
				this._selected_item.getSubmenu().focusSelected();
			}
			else {
				this._selected_item.focus();
			}
		}

		return this;
	}

	getExpanded() {
		return this._expanded_item;
	}

	getSelected() {
		return this._selected_item;
	}

	/**
	 * Register all DOM events.
	 */
	registerEvents() {
		this._events = {

			focus: (e) => {
				if (!this._target.contains(e.relatedTarget)) {
					this.fire((e.type === 'focusin') ? MENU_EVENT_FOCUS : MENU_EVENT_BLUR);
				}
			},

			expand: (e) => {
				this._expanded_item !== e.detail.target && this.collapseExpanded();
				this._expanded_item = e.detail.target;

				this.fire(MENU_EVENT_EXPAND, {menu_item: e.detail.target});
			},

			collapse: (e) => {
				this._expanded_item = null;
			}
		};

		this.on('focusin focusout', this._events.focus);

		for (const item of this._items) {
			if (item.hasSubmenu()) {
				item.on(MENUITEM_EVENT_EXPAND, this._events.expand);
				item.on(MENUITEM_EVENT_COLLAPSE, this._events.collapse);
			}
		}
	}
}
