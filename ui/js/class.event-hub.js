/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CEventHub {

	constructor() {
		this.subscribers = new Map();
		this.latest_data = new Map();
	}

	publish({data, descriptor}) {
		descriptor = Object.keys(descriptor).sort().reduce(
			(descriptor_sorted, key) => {
				descriptor_sorted[key] = descriptor[key];

				return descriptor_sorted;
			},
			{}
		);

		const descriptor_hash = JSON.stringify(descriptor);

		this.latest_data.delete(descriptor_hash);
		this.latest_data.set(descriptor_hash, {descriptor, data});

		for (const {require, callback} of this.subscribers.values()) {
			if (this.constructor.#match(require, descriptor)) {
				callback({descriptor, data});
			}
		}
	}

	subscribe({require = {}, callback}) {
		for (const {descriptor, data} of [...this.latest_data.values()].reverse()) {
			if (this.constructor.#match(require, descriptor)) {
				callback({descriptor, data});

				break;
			}
		}

		const handle = {};

		this.subscribers.set(handle, {require, callback});

		return handle;
	}

	unsubscribe(handle) {
		return this.subscribers.delete(handle);
	}

	static #match(require, descriptor) {
		for (const [key, value] of Object.entries(require)) {
			if (!(key in descriptor) || descriptor[key] !== value) {
				return false;
			}
		}

		return true;
	}
}
