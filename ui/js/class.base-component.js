/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

	constructor(target) {
		this._target = target;
	}

	/**
	 * CSS Classes.
	 */
	addClass(class_name) {
		this._target.classList.add(class_name);
		return this;
	}

	removeClass(class_name) {
		this._target.classList.remove(class_name);
		return this;
	}

	toggleClass(class_name, force) {
		return this._target.classList.toggle(class_name, force);
	}

	hasClass(class_name) {
		return this._target.classList.contains(class_name);
	}

	/**
	 * Events.
	 */
	on(types, listener, options = false) {
		types.split(' ').forEach((t) => this._target.addEventListener(t, listener, options));
		return this;
	}

	one(types, listener, options = false) {
		return this.on(types, listener, {once: true, ...options});
	}

	off(types, listener, options = false) {
		types.split(' ').forEach((t) => this._target.removeEventListener(t, listener, options));
		return this;
	}

	fire(type, detail = {}, options = {}) {
		return this._target.dispatchEvent(new CustomEvent(type, {...options, detail: {target: this, ...detail}}));
	}
}
