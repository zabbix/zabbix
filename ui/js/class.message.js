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


class CMessageHelper {

	static TYPE_INFO = 'info';
	static TYPE_SUCCESS = 'good';
	static TYPE_WARNING = 'warning';
	static TYPE_ERROR = 'bad';

	static EVENT_MESSAGE = 'message';

	/**
	 * @param {HTMLElement}  element
	 * @param {array}        messages
	 * @param {string|null}  title
	 * @param {boolean}      show_close_box
	 * @param {boolean|null} show_details
	 */
	static success(element, messages, title = null, {show_close_box = true, show_details = null} = {}) {
		CMessageHelper.dispatchEvent(element, CMessageHelper.EVENT_MESSAGE,
			{type: CMessageHelper.TYPE_SUCCESS, messages, title, show_close_box, show_details}
		);
	}

	/**
	 * @param {HTMLElement}  element
	 * @param {array}        messages
	 * @param {string|null}  title
	 * @param {boolean}      show_close_box
	 * @param {boolean|null} show_details
	 */
	static error(element, messages, title = null, {show_close_box = true, show_details = null} = {}) {
		CMessageHelper.dispatchEvent(element, CMessageHelper.EVENT_MESSAGE,
			{type: CMessageHelper.TYPE_ERROR, messages, title, show_close_box, show_details}
		);
	}

	/**
	 * @param {HTMLElement}  element
	 * @param {array}        messages
	 * @param {string|null}  title
	 * @param {boolean}      show_close_box
	 * @param {boolean|null} show_details
	 */
	static info(element, messages, title = null, {show_close_box = true, show_details = null} = {}) {
		CMessageHelper.dispatchEvent(element, CMessageHelper.EVENT_MESSAGE,
			{type: CMessageHelper.TYPE_INFO, messages, title, show_close_box, show_details}
		);
	}

	/**
	 * @param {HTMLElement}  element
	 * @param {array}        messages
	 * @param {string|null}  title
	 * @param {boolean}      show_close_box
	 * @param {boolean|null} show_details
	 */
	static warning(element, messages, title = null, {show_close_box = true, show_details = null} = {}) {
		CMessageHelper.dispatchEvent(element, CMessageHelper.EVENT_MESSAGE,
			{type: CMessageHelper.TYPE_WARNING, messages, title, show_close_box, show_details}
		);
	}

	/**
	 * @param {HTMLElement} element
	 * @param {string}      event
	 * @param {Object}      detail
	 * @param {Object}      options
	 */
	static dispatchEvent(element, event, detail = {}, options = {}) {
		element.dispatchEvent(new CustomEvent(event, {...options, detail}));
	}
}
