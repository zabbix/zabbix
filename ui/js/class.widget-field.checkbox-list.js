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


class CWidgetFieldCheckboxList extends CWidgetField {

	/**
	 * @type {HTMLInputElement}
	 */
	#empty_input_element;

	/**
	 * @type {HTMLInputElement[]}
	 */
	#checkboxes;

	constructor({name, form_name}) {
		super({name, form_name});

		this.#empty_input_element = this.getForm().querySelector(`input[type="hidden"][name="${name}"]`);
		this.#checkboxes = [...this.getForm().querySelectorAll(`input[type="checkbox"][name="${name}[]"]`)];

		this.#initField();
		this.#update();
	}

	#initField() {
		const observer = new MutationObserver(() => this.#update());

		for (const checkbox of this.#checkboxes) {
			checkbox.addEventListener('change', () => {
				this.#update();
				this.dispatchUpdateEvent();
			});
			observer.observe(checkbox, {attributeFilter: ['disabled']});
		}
	}

	#update() {
		this.#empty_input_element.disabled = this.#checkboxes
			.filter((checkbox) => checkbox.checked && !checkbox.disabled).length > 0;
	}
}
