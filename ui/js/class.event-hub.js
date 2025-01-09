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


class CEventHub {

	static EVENT = '_EVENT';
	static EVENT_NATIVE = 'native';
	static EVENT_SUBSCRIBE = 'subscribe';
	static EVENT_UNSUBSCRIBE = 'unsubscribe';

	#subscribers = new Map();

	#latest_data = new Map();

	publish({data, descriptor}) {
		descriptor = {
			[CEventHub.EVENT]: CEventHub.EVENT_NATIVE,
			...descriptor
		};

		descriptor = Object.keys(descriptor).sort().reduce(
			(descriptor_sorted, key) => {
				descriptor_sorted[key] = descriptor[key];

				return descriptor_sorted;
			},
			{}
		);

		const descriptor_hash = JSON.stringify(descriptor);

		this.#latest_data.delete(descriptor_hash);
		this.#latest_data.set(descriptor_hash, {data, descriptor});

		for (const {require, callback} of this.#subscribers.values()) {
			if (CEventHub.#match(require, descriptor)) {
				callback({data, descriptor});
			}
		}

		return this;
	}

	subscribe({require = {}, callback}) {
		require = {[CEventHub.EVENT]: CEventHub.EVENT_NATIVE, ...require};

		for (const {data, descriptor} of [...this.#latest_data.values()].reverse()) {
			if (CEventHub.#match(require, descriptor)) {
				callback({data, descriptor});

				break;
			}
		}

		const subscription = {};

		this.#subscribers.set(subscription, {require, callback});

		this
			.publish({
				data: require,
				descriptor: {...require, [CEventHub.EVENT]: CEventHub.EVENT_SUBSCRIBE}
			})
			.invalidateData({[CEventHub.EVENT]: CEventHub.EVENT_SUBSCRIBE});

		return subscription;
	}

	unsubscribe(subscription) {
		if (!this.#subscribers.has(subscription)) {
			return false;
		}

		const {require} = this.#subscribers.get(subscription);

		this.#subscribers.delete(subscription);

		this
			.publish({
				data: require,
				descriptor: {...require, [CEventHub.EVENT]: CEventHub.EVENT_UNSUBSCRIBE}
			})
			.invalidateData({[CEventHub.EVENT]: CEventHub.EVENT_UNSUBSCRIBE});

		return true;
	}

	hasSubscribers(require = {}) {
		require = {[CEventHub.EVENT]: CEventHub.EVENT_NATIVE, ...require};

		for (const subscriber of this.#subscribers.values()) {
			if (CEventHub.#match(require, subscriber.require)) {
				return true;
			}
		}

		return false;
	}

	getData(require) {
		for (const {data, descriptor} of [...this.#latest_data.values()].reverse()) {
			if (CEventHub.#match(require, descriptor)) {
				return data;
			}
		}

		return undefined;
	}

	invalidateData(require) {
		for (const [descriptor_hash, {descriptor}] of this.#latest_data.entries()) {
			if (CEventHub.#match(require, descriptor)) {
				this.#latest_data.delete(descriptor_hash);
			}
		}

		return this;
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
