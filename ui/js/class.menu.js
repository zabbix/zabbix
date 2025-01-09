/*
** Copyright (C) 2001-2025 Zabbix SIA
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


const MENU_EXPAND_SELECTED_DELAY = 5000;

const MENU_EVENT_BLUR            = 'blur';
const MENU_EVENT_EXPAND          = 'expand';
const MENU_EVENT_FOCUS           = 'focus';

class CMenu extends CBaseComponent {

	constructor(target, level) {
		super(target);

		this.init(level || 0);
		this.registerEvents();
	}

	init(level) {
		this._expanded_item = null;
		this._selected_item = null;
		this._items = [];
		this._level = level;

		for (const el of this._target.childNodes) {
			const item = new CMenuItem(el, this._level);
			if (item.isExpanded()) {
				this._expanded_item = item;
			}
			if (item.isSelected()) {
				this._selected_item = item;
			}
			this._items.push(item);
		}

		this.hasClass('submenu') && this.updateHeight();
	}

	getItems() {
		return this._items;
	}

	getLevel() {
		return this._level;
	}

	collapseExpanded(from_level) {
		if (this._expanded_item !== null && this._expanded_item.collapseSubmenu(from_level)) {
			this._expanded_item = null;
		}

		return this._level > (from_level || 0);
	}

	expandSelected(till_level) {
		if (this._level < till_level && this._selected_item !== null && this._selected_item !== this._expanded_item) {
			this.collapseExpanded();
			this._selected_item.hasSubmenu() && this._selected_item.expandSubmenu(till_level);
		}

		return this;
	}

	focusSelected(till_level) {
		if (this._selected_item !== null) {
			if (this._selected_item.hasSubmenu() && this._level < till_level) {
				this.expandSelected(till_level);
				this._selected_item.getSubmenu().focusSelected(till_level);
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

	updateHeight() {
		this._target.style.maxHeight = `${this._target.scrollHeight}px`;
	}

	updateRect(relative_item, limit) {
		const r_rect = relative_item.getBoundingClientRect();

		limit = Object.assign({top: 0, bottom: 0}, limit || {});

		this._target.style.top = `${Math.max(limit.top,
			Math.min(r_rect.y, window.innerHeight - this._target.scrollHeight - limit.bottom)
		)}px`;
		this._target.style.left = `${r_rect.x + r_rect.width}px`;
		this._target.style.maxWidth = `${this._target.scrollWidth}px`;
		this._target.style.maxHeight = `${this._target.scrollHeight}px`;

		if (this._expanded_item && this._expanded_item.hasSubmenu()) {
			this._expanded_item.getSubmenu().updateRect(this._expanded_item._target, limit);
		}
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

				this.fire(MENU_EVENT_EXPAND, {menu_item: this._expanded_item});
			},

			collapse: () => {
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
