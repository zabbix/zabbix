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

	/**
	 * Subscribers data.
	 *
	 * @type {Map}
	 */
	#subscribers = new Map();

	/**
	 * Event cache for late subscribers.
	 *
	 * @type {Map}
	 */
	#cache = new Map();

	/**
	 * Publish event.
	 *
	 * @param {CEventHubEvent} event
	 *
	 * @returns {this}
	 */
	publish(event) {
		const descriptor = event.getDescriptor();

		const descriptor_sorted = Object.keys(descriptor).sort().reduce(
			(descriptor_sorted, key) => {
				descriptor_sorted[key] = descriptor[key];

				return descriptor_sorted;
			},
			{}
		);

		const event_hash = JSON.stringify([event.getType(), descriptor_sorted]);

		this.#cache.delete(event_hash);
		this.#cache.set(event_hash, event);

		for (const {require, require_type, callback} of this.#subscribers.values()) {
			if (CEventHub.#matchEvent(require, require_type, event)) {
				callback({data: event.getData(), descriptor, event});
			}
		}

		event.setCached();

		return this;
	}

	/**
	 * Subscribe to events.
	 *
	 * @param {Object}   require        Event descriptor requirements.
	 * @param {string}   require_type   Event type requirement.
	 * @param {function} callback       Callback to invoke for matching events.
	 * @param {boolean}  accept_cached  Whether to invoke callback for the last cached event matching the criteria.
	 *
	 * @returns {Object}  Subscription object to use for unsubscription.
	 */
	subscribe({require = {}, require_type = CEventHubEvent.TYPE_NATIVE, callback, accept_cached = false}) {
		if (accept_cached) {
			for (const event of [...this.#cache.values()].reverse()) {
				if (CEventHub.#matchEvent(require, require_type, event)) {
					callback({data: event.getData(), descriptor: event.getDescriptor(), event});

					break;
				}
			}
		}

		const subscription = {};

		this.#subscribers.set(subscription, {require, require_type, callback});

		this
			.publish(new CEventHubEvent({
				data: {require_type},
				descriptor: require,
				type: CEventHubEvent.TYPE_SUBSCRIBE
			}))
			.invalidateData({}, CEventHubEvent.TYPE_SUBSCRIBE);

		return subscription;
	}

	/**
	 * Unsubscribe single subscription from events.
	 *
	 * @param {Object} subscription  Subscription object received when subscribed to events.
	 *
	 * @returns {boolean}  Whether unsubscription was successful.
	 */
	unsubscribe(subscription) {
		if (!this.#subscribers.has(subscription)) {
			return false;
		}

		const {require, require_type} = this.#subscribers.get(subscription);

		this.#subscribers.delete(subscription);

		this
			.publish(new CEventHubEvent({
				data: {require_type},
				descriptor: require,
				type: CEventHubEvent.TYPE_UNSUBSCRIBE
			}))
			.invalidateData({}, CEventHubEvent.TYPE_UNSUBSCRIBE);

		return true;
	}

	/**
	 * Unsubscribe array of subscriptions from events.
	 *
	 * @param {Object[]} subscriptions  Array of subscription objects received when subscribed to events.
	 *
	 * @returns {boolean}  Whether unsubscription of all subscriptions was successful.
	 */
	unsubscribeAll(subscriptions) {
		let result = true;

		for (const subscription of subscriptions) {
			if (!this.unsubscribe(subscription)) {
				result = false;
			}
		}

		return result;
	}

	/**
	 * Check if subscribers exist matching the criteria.
	 *
	 * @param {Object} require       Event descriptor requirements.
	 * @param {string} require_type  Event type requirement.
	 *
	 * @returns {boolean}
	 */
	hasSubscribers(require = {}, require_type = CEventHubEvent.TYPE_NATIVE) {
		for (const subscriber of this.#subscribers.values()) {
			if (subscriber.require_type === require_type && CEventHub.#matchDescriptor(require, subscriber.require)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get cached event data matching the criteria.
	 *
	 * @param {Object} require       Event descriptor requirements.
	 * @param {string} require_type  Event type requirement.
	 *
	 * @returns {*|undefined}  Data of the last cached event matching the criteria, undefined otherwise.
	 */
	getData(require, require_type = CEventHubEvent.TYPE_NATIVE) {
		for (const event of [...this.#cache.values()].reverse()) {
			if (CEventHub.#matchEvent(require, require_type, event)) {
				return event.getData();
			}
		}

		return undefined;
	}

	/**
	 * Invalidate cached event data matching the criteria.
	 *
	 * @param {Object} require       Event descriptor requirements.
	 * @param {string} require_type  Event type requirement.
	 *
	 * @returns {this}
	 */
	invalidateData(require = {}, require_type = CEventHubEvent.TYPE_NATIVE) {
		for (const [descriptor_hash, event] of this.#cache.entries()) {
			if (CEventHub.#matchEvent(require, require_type, event)) {
				this.#cache.delete(descriptor_hash);
			}
		}

		return this;
	}

	static #matchEvent(require, require_type, event) {
		if (event.getType() !== require_type) {
			return false;
		}

		return CEventHub.#matchDescriptor(require, event.getDescriptor());
	}

	static #matchDescriptor(require, descriptor) {
		for (const [key, value] of Object.entries(require)) {
			if (!(key in descriptor) || descriptor[key] !== value) {
				return false;
			}
		}

		return true;
	}
}
