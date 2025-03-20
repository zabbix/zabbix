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

	init() {
		super.init();

		this._field.addEventListener('change', () => this.onBlur());
	}

	getName() {
		return this._field.querySelector('input[type="radio"]').getAttribute('name');
	}

	getValue() {
		if (this._field.querySelector('input[type="radio"]').disabled) {
			/**
			 * Radio-list element's read-only state is simulated by disabling radio element
			 * and adding a hidden input of actual value.
			 */
			const radio = this._field.querySelector('input[type="hidden"]');

			return radio !== null ? radio.value : null;
		}

		return this._field.querySelector('input[type="radio"]:checked').value;
	}

	focusErrorField() {
		super.focusErrorField();
		this._field.focus();
	}
}
