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


/**
 * Supported events:
 *   collapse  - submenu is collapsed
 *   expand    - submenu is expanded
 *   focus     - control is focused
 */
class CMenuItem extends CBaseComponent {

	constructor(node) {
		super(node);

		this._submenu = null;

		this.init();
		this.registerEvents();
	}

	init() {
		if (this._node.classList.contains('has-submenu')) {
			this._submenu = new CMenu(this._node.querySelector('.submenu'));
		}

		this._submenu_toggle = this._node.querySelector('a');

		this._is_expanded = this._node.classList.contains('is-expanded');
		this._is_selected = this._node.classList.contains('is-selected');
	}

	focusControl() {
		this._control.focus();
		// this._submenu_toggle.focus();

		return this;
	}

	blurControl() {
		this._control.blur();
		// this._submenu_toggle.blur();

		return this;
	}

	isSelected() {
		return this._is_selected;
	}

	getSubmenu() {
		return this._submenu;
	}

	hasSubmenu() {
		return this._submenu !== null;
	}

	expandSubmenu() {
		let is_expanded = this._node.classList.toggle('is-expanded', this.hasSubmenu());

		if (is_expanded && !this._is_expanded) {
			this._is_expanded = true;
			this.trigger('expand');
		}

		return this;
	}

	collapseSubmenu() {
		this._node.classList.remove('is-expanded');

		if (this._is_expanded) {
			this._is_expanded = false;
			this.trigger('collapse');
		}

		return this;
	}

	/**
	 * Register all DOM events.
	 */
	registerEvents() {
		this._events = {

			click: (e) => {
				if (!this._is_expanded) {
					this.expandSubmenu();
					e.preventDefault();
				}
			},

			focus: () => {
				if (this.hasSubmenu() && !this._is_expanded) {
					this.expandSubmenu();
				}
				this.trigger('focus');
			}
		};

		// this._control.addEventListener('focus', this._events.focus);
		//
		// if (this.hasSubmenu()) {
		// 	this._control.addEventListener('click', this._events.click);
		// 	this._submenu.on('focus', this._events.focus);
		// }
	}
}
