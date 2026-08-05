/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CState {
	/**
	 * @type {URL}
	 */
	#url;

	constructor(url) {
		this.#url = new URL(url || location.href);
	}

	/**
	 * @returns {URLSearchParams}
	 */
	getParams() {
		return this.#url.searchParams;
	}

	/**
	 * @param {Object} params
	 */
	setParams(params) {
		for (const [key, value] of Object.entries(params)) {
			this.#url.searchParams.set(key, value.toString());
		}

		this.push();
	}

	push() {
		history.pushState(null, '', this.#url);
	}

	/**
	 * @returns {URL}
	 */
	getUrl() {
		return this.#url;
	}
}
