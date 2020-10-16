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


const MENUITEM_EVENT_COLLAPSE = 'collapse';
const MENUITEM_EVENT_EXPAND   = 'expand';
const MENUITEM_EVENT_FOCUS    = 'focus';

class CMenuItem extends CBaseComponent {

	constructor(target) {
		super(target);

		this.init();
		this.registerEvents();
	}

	init() {
		this._toggle = this._target.querySelector('a');

		if (this.hasClass('has-submenu')) {
			this._submenu = new CMenu(this._target.querySelector('.submenu'));
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

	getSubmenu() {
		return this._submenu;
	}

	hasSubmenu() {
		return typeof this._submenu !== 'undefined';
	}

	expandSubmenu() {
		if (!this._is_expanded && this.toggleClass('is-expanded', this.hasSubmenu())) {
			this._is_expanded = true;
			this.fire(MENUITEM_EVENT_EXPAND);
		}

		return this;
	}

	collapseSubmenu() {
		this.removeClass('is-expanded');
		this.blur();

		if (this._is_expanded) {
			this._is_expanded = false;
			this.fire(MENUITEM_EVENT_COLLAPSE);
		}

		return this;
	}

	/**
	 * Register all DOM events.
	 */
	registerEvents() {
		this._events = {

			click: (e) => {
				this.expandSubmenu();
				e.preventDefault();
			},

			focus: () => {
				if (this.hasSubmenu() && !this._is_expanded) {
					this.expandSubmenu();
				}
				this.fire(MENUITEM_EVENT_FOCUS);
			}
		};

		if (this.hasSubmenu()) {
			this._toggle.addEventListener('click', this._events.click);
			this._toggle.addEventListener('focus', this._events.focus);

			this._submenu.on(MENU_EVENT_FOCUS, this._events.focus);
		}
	}
}
