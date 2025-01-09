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


const MENUITEM_EVENT_COLLAPSE = 'collapse';
const MENUITEM_EVENT_EXPAND   = 'expand';
const MENUITEM_EVENT_FOCUS    = 'focus';

class CMenuItem extends CBaseComponent {

	constructor(target, level) {
		super(target);

		this.init(level);
		this.registerEvents();
	}

	init(level) {
		this._toggle = this._target.querySelector('a');

		if (this.hasClass('has-submenu')) {
			this._submenu = new CMenu(this._target.querySelector('.submenu'), ++level);
		}

		this._is_expanded = this.hasClass('is-expanded');
		this._is_selected = this.hasClass('is-selected');
	}

	focus() {
		this._toggle.focus();
	}

	blur() {
		this._toggle.blur();
	}

	isExpanded() {
		return this._is_expanded;
	}

	isSelected() {
		return this._is_selected;
	}

	isFocused() {
		return document.activeElement === this._toggle;
	}

	getSubmenu() {
		return this._submenu;
	}

	hasSubmenu() {
		return typeof this._submenu !== 'undefined';
	}

	expandSubmenu() {
		if (!this._is_expanded && this.hasSubmenu()) {
			this.addClass('is-expanded');
			this._is_expanded = true;
			this._submenu.updateHeight();

			this.fire(MENUITEM_EVENT_EXPAND);
		}

		return this;
	}

	collapseSubmenu(from_level) {
		if (this._is_expanded && this.hasSubmenu() && this._submenu.collapseExpanded(from_level)) {
			this.removeClass('is-expanded');
			this._is_expanded = false;
			this.fire(MENUITEM_EVENT_COLLAPSE);

			return true;
		}

		return false;
	}

	/**
	 * Register all DOM events.
	 */
	registerEvents() {
		this._events = {

			click: (e) => {
				if (this._is_expanded) {
					this.collapseSubmenu();
				}
				else {
					this.expandSubmenu();
				}

				e.preventDefault();
			},

			focus: () => {
				if (!this.isFocused()) {
					if (this.hasSubmenu() && !this._is_expanded) {
						this.expandSubmenu();
					}

					this.fire(MENUITEM_EVENT_FOCUS);
				}
			},

			blur: () => {
				if (this.hasSubmenu() && this._is_expanded) {
					this.collapseSubmenu(1);
				}
			},

			expand: () => {
				if (this.hasSubmenu() && this._is_expanded) {
					this.fire(MENUITEM_EVENT_EXPAND);
				}
			}
		};

		if (this.hasSubmenu()) {
			this._toggle.addEventListener('click', this._events.click);
			this._toggle.addEventListener('focus', this._events.focus);

			this._submenu.on(MENU_EVENT_FOCUS, this._events.focus);
			this._submenu.on(MENU_EVENT_BLUR, this._events.blur);
			this._submenu.on(MENU_EVENT_EXPAND, this._events.expand);
		}
	}
}
