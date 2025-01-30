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


class CEventHubEvent {

	/**
	 * Event type for native events published by user.
	 *
	 * @type {string}
	 */
	static TYPE_NATIVE = 'native';

	/**
	 * Event type for subscription events published by the event hub.
	 *
	 * @type {string}
	 */
	static TYPE_SUBSCRIBE = 'subscribe';

	/**
	 * Event type for unsubscription events published by the event hub.
	 *
	 * @type {string}
	 */
	static TYPE_UNSUBSCRIBE = 'unsubscribe';

	/**
	 * Event data.
	 *
	 * @type {*|undefined}
	 */
	#data;

	/**
	 * Event descriptor, an object consisting of scalar parameters of the event.
	 *
	 * @type {Object}
	 */
	#descriptor;

	/**
	 * Event type. One of CEventHubEvent.TYPE_* constants.
	 *
	 * @type {string}
	 */
	#type;

	/**
	 * Whether the event is new or cached.
	 *
	 * @type {boolean}
	 */
	#is_cached = false;

	/**
	 * Whether to prevent the default action after the event has fired.
	 *
	 * @type {boolean}
	 */
	#is_default_prevented = false;

	/**
	 * @param {*}      data        Event data to be passed to the subscribers.
	 * @param {Object} descriptor  Event descriptor, an object consisting of scalar parameters of the event.
	 * @param {string} type        Event type. One of CEventHubEvent.TYPE_* constants.
	 */
	constructor({data = undefined, descriptor, type = CEventHubEvent.TYPE_NATIVE}) {
		this.#data = data;
		this.#descriptor = descriptor;
		this.#type = type;
	}

	/**
	 * @returns {*}
	 */
	getData() {
		return this.#data;
	}

	/**
	 * @returns {Object}
	 */
	getDescriptor() {
		return this.#descriptor;
	}

	/**
	 * @returns {string}
	 */
	getType() {
		return this.#type;
	}

	/**
	 * Mark the event as cached. Invoked by the event hub when the event has fired.
	 *
	 * @returns {this}
	 */
	setCached() {
		this.#is_cached = true;

		return this;
	}

	/**
	 * Check whether the event is new or cached.
	 *
	 * The subscriber shall specify {accept_cached: true} to receive cached events.
	 *
	 * @returns {boolean}
	 */
	isCached() {
		return this.#is_cached;
	}

	/**
	 * Prevent default action.
	 *
	 * @returns {this}
	 */
	preventDefault() {
		this.#is_default_prevented = true;

		return this;
	}

	/**
	 * Check whether the default action shall be prevented.
	 *
	 * @returns {boolean}
	 */
	isDefaultPrevented() {
		return this.#is_default_prevented;
	}
}
