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


class CPopupManagerEvent extends CEventHubEvent {

	/**
	 * Event is fired when popup is requested to open.
	 *
	 * @type {string}
	 */
	static EVENT_OPEN = 'open';

	/**
	 * Event is fired when popup is closed with success and is about to reload the page or redirect to the return URL.
	 *
	 * @type {string}
	 */
	static EVENT_SUBMIT = 'submit';

	/**
	 * Event is fired when popup is cancelled by user action.
	 *
	 * @type {string}
	 */
	static EVENT_CANCEL = 'cancel';

	/**
	 * Event is fired when popup is closed with success, cancelled by user or reloaded.
	 *
	 * @type {string}
	 */
	static EVENT_END_SCRIPTING = 'end-scripting';

	/**
	 * URL to redirect the page after the popup is submitted or cancelled.
	 *
	 * @type {string|null}
	 */
	#redirect_url = null;

	/**
	 * Set URL to redirect the page after the popup is submitted or cancelled.
	 *
	 * @type {string|null}
	 */
	setRedirectUrl(url) {
		this.#redirect_url = url;
	}

	/**
	 * Get URL to redirect the page after the popup is submitted or cancelled.
	 *
	 * @returns {string|null}
	 */
	getRedirectUrl() {
		return this.#redirect_url;
	}
}
