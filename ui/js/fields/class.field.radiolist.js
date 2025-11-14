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


class CFieldRadioList extends CField {

	_radiobuttons = [];
	_hidden_element = null;

	init() {
		super.init();

		this.updateState();

		this._field.addEventListener('change', () => this.onBlur());
	}

	getName() {
		return this._field.querySelector('input[type="radio"]').getAttribute('name');
	}

	getValue() {
		if (this.isDisabled()) {
			return null;
		}
		else if (this._hidden_element !== null) {
			return this._hidden_element.value;
		}
		else {
			return [...this._radiobuttons].find(radio => radio.checked)?.value ?? null;
		}
	}

	isDisabled() {
		return this._hidden_element === null && [...this._radiobuttons].every(radio => radio.disabled);
	}

	updateState() {
		this._radiobuttons = this._field.querySelectorAll('input[type="radio"]');
		this._hidden_element = this._field.querySelector('input[type="hidden"]');
	}

	focusErrorField() {
		super.focusErrorField();
		this._field.focus();
	}
}
