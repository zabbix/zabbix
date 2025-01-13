/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
