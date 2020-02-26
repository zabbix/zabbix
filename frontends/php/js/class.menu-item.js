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


class CMenuItem extends CBaseComponent {

	constructor(node) {
		super(node);

		this._submenu = node.classList.contains('has-submenu') ? new CMenu(node.querySelector('.submenu')) : null;
		this._toggle = node.querySelector('a');

		this._is_expanded = this._node.classList.contains('is-expanded');
		this._is_selected = this._node.classList.contains('is-selected');

		this.handleEvents();
	}

	collapseSubmenu() {
		this._node.classList.remove('is-expanded');

		if (this._is_expanded) {
			this.trigger('collapse');
			this._is_expanded = false;
		}

		return this;
	}

	expandSubmenu() {
		let is_expanded = this._node.classList.toggle('is-expanded', this.hasSubmenu());

		if (is_expanded && !this._is_expanded) {
			this.trigger('expand');
			this._is_expanded = true;
		}

		return this;
	}

	focus() {
		this._toggle.focus();
	}

	hasSubmenu() {
		return this._submenu !== null;
	}

	isSelected() {
		return this._is_selected;
	}

	handleEvents() {
		this._events = {

			expand: (e) => {
				if (this.hasSubmenu()) {
					this.expandSubmenu();
					e.preventDefault();
				}
			}
		};

		this._toggle.addEventListener('click', this._events.expand);
		this._toggle.addEventListener('focus', this._events.expand);
	}
}
