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


class CBaseComponent {

	constructor(node) {
		this._node = node;
	}

	on(types, listener, options = false) {
		types.split(' ').forEach((t) => this._node.addEventListener(t, listener, options));
		return this;
	}

	one(types, listener, options = false) {
		return this.on(types, listener, Object.assign({once: true}, options));
	}

	off(types, listener, options = false) {
		types.split(' ').forEach((t) => this._node.removeEventListener(t, listener, options));
		return this;
	}

	trigger(type, options = {}) {
		this._node.dispatchEvent(new CustomEvent(type, {detail: Object.assign({targetObj: this}, options)}));
		return this;
	}
};
