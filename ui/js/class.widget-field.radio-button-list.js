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


class CWidgetFieldRadioButtonList extends CWidgetField {

	/**
	 * @type {HTMLInputElement[]}
	 */
	#radio_buttons;

	constructor({name, form_name}) {
		super({name, form_name});

		this.#radio_buttons = [...this.getForm().querySelectorAll(`input[type="radio"][name="${name}"]`)];

		this.#initField();
	}

	#initField() {
		for (const radio_button of this.#radio_buttons) {
			radio_button.addEventListener('change', () => this.dispatchUpdateEvent());
		}
	}
}
