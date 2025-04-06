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


/**
 * Abstract class for widget configuration form fields.
 */
class CWidgetField {

	static EVENT_CONTEXT = 'widget-field';

	/**
	 * @type {string}
	 */
	#name;

	/**
	 * @type {string}
	 */
	#form_name;

	constructor({name, form_name}) {
		this.#name = name;
		this.#form_name = form_name;
	}

	/**
	 * Get name of the field.
	 *
	 * @returns {string}
	 */
	getName() {
		return this.#name;
	}

	/**
	 * Get name of the form, the field belongs to.
	 *
	 * @returns {string}
	 */
	getFormName() {
		return this.#form_name;
	}

	/**
	 * Get form element, the field belong to.
	 *
	 * @returns {HTMLFormElement}
	 */
	getForm() {
		return document.forms[this.getFormName()];
	}

	/**
	 * Inform the framework about the update event on the field level.
	 *
	 * The framework will validate the configuration and update the widget on the fly.
	 */
	dispatchUpdateEvent() {
		ZABBIX.EventHub.publish(new CWidgetFieldEvent({
			data: {
				name: this.getName(),
			},
			descriptor: {
				context: CWidgetField.EVENT_CONTEXT,
				event: CWidgetFieldEvent.EVENT_UPDATE,
				form_name: this.getFormName()
			}
		}));
	}
}
