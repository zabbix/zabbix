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

class CWidgetFieldCheckboxList {

	/**
	 * @type {HTMLInputElement}
	 */
	#empty_input_element;

	/**
	 * @type {Array<HTMLInputElement>}
	 */
	#checkboxes;

	constructor(field_name) {
		this.#empty_input_element = document.querySelector(`input[type="hidden"][name="${field_name}"]`);
		this.#checkboxes = [...document.querySelectorAll(`input[type="checkbox"][name="${field_name}[]"]`)];

		this.#initField();
		this.#update();
	}

	#initField() {
		const observer = new MutationObserver(() => this.#update());

		for (const checkbox of this.#checkboxes) {
			checkbox.addEventListener('change', () => this.#update());
			observer.observe(checkbox, {attributeFilter: ['disabled']});
		}
	}

	#update() {
		this.#empty_input_element.disabled = this.#checkboxes
			.filter((checkbox) => checkbox.checked && !checkbox.disabled).length > 0;
	}
}
