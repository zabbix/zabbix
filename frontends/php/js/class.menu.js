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


class CMenu extends CBaseComponent {

	constructor(node) {
		super(node);

		this._items = [];

		for (const el of this._node.childNodes) {
			this._items.push(new CMenuItem(el));
		}

		if (this._node.classList.contains('submenu')) {
			this._node.style.maxHeight = this._node.scrollHeight + 'px';
		}

		this.handleEvents();
	}

	collapseAll(excluded_item = null) {
		for (const item of this._items) {
			if (item !== excluded_item) {
				item.collapseSubmenu();
			}
		}

		return this;
	}

	expandSelected() {
		for (const item of this._items) {
			if (item.isSelected()) {
				item.expandSubmenu();
			}
			else {
				item.collapseSubmenu();
			}
		}

		return this;
	}

	focusSelected() {
		for (const item of this._items) {
			if (item.isSelected()) {
				item.focus();
			}
		}

		return this;
	}

	handleEvents() {
		this._events = {
			expand: (e) => {
				this.collapseAll(e.detail.targetObj);
			}
		}

		for (const item of this._items) {
			item.on('expand', this._events.expand);
		}
	}
}
