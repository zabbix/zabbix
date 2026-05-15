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


class CFieldMultiline extends CField {

	init() {
		super.init();
		jQuery(this._field).on('change', () => this.fieldChanged());
	}

	getValueTrimmed() {
		const value = this.getValue();

		if (value === null || !this._allow_trim) {
			return value;
		}

		return value.trim();
	}

	getValue() {
		if (this.isDisabled()) {
			return null;
		}

		return this._field.querySelector(`input[name="${this.getName()}"]`).value;
	}

	getName() {
		return this._field.dataset.name;
	}

	isDisabled() {
		return this._field.disabled;
	}

	lock() {
		if ($(this._field).prop('disabled')) {
			return false;
		}

		this._field.dataset.formDisabled = '';
		$(this._field).prop('disabled', true);

		return true;
	}

	unlock() {
		if ('formDisabled' in this._field.dataset) {
			$(this._field).prop('disabled', false);
			delete this._field.dataset.formDisabled;

			return true;
		}

		return false;
	}
}
