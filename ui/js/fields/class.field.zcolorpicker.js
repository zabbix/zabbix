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


class CFieldZColorPicker extends CField {

	#hidden_input;

	init() {
		this.#hidden_input = this._field.querySelector('input[type="hidden"]');
		super.init();

		this._field.addEventListener('change', () => this.onBlur());
	}

	getName() {
		return this.#hidden_input.getAttribute('name');
	}

	getValue() {
		if (this.isDisabled()) {
			return null;
		}

		return this.#hidden_input.value;
	}

	isDisabled () {
		return this.#hidden_input.disabled;
	}

	focusErrorField() {
		super.focusErrorField();
		this._field.focus();
	}
}
