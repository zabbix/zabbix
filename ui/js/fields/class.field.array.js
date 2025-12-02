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


class CFieldArray extends CField {

	/**
	 * Array of input field references that particular FieldArray instance holds.
	 *
	 * @type {Array<CField>}
	 */
	#fields = [];

	init() {
		super.init();

		this.#discoverAllFields();

		this._prev_value = this.getValue();

		const observer = new MutationObserver(this.#detectFieldChanges);

		observer.observe(this._field, {
			childList: true,
			subtree: true
		});

		this._field.addEventListener('click', e => e.target.matches('[type="checkbox"]') && this.#detectFieldChanges());
	}

	getName() {
		return this._field.getAttribute('data-field-name');
	}

	getInnerValue(trim_value) {
		const result = [];

		for (const field of this.#fields) {
			const value = trim_value ? field.getValueTrimmed() : field.getValue();

			if (value !== null) {
				result.push(value);
			}
		}

		return result;
	}

	getValue() {
		return this.getInnerValue(false);
	}

	getValueTrimmed() {
		return this.getInnerValue(true);
	}

	#detectFieldChanges = () => {
		this.#discoverAllFields();

		if (this.#hasValueChanged()) {
			this.fieldChanged();
		}
	}

	#discoverAllFields() {
		const fields = [];

		for (const field of CForm.findAllFields(this._field)) {
			const field_type = field.getAttribute('data-field-type');

			if (field_type in CForm.field_types) {
				let field_instance = this.#fields.find(discovered_field => discovered_field.isSameField(field));

				if (field_instance === undefined) {
					field_instance = new CForm.field_types[field_type](field);
					field_instance.init();
					field_instance.setTabId(this._tab_id);
				}
				else {
					field_instance.updateState();
				}

				fields.push(field_instance);
			}
		}

		this.#fields = fields;
	}

	#hasValueChanged() {
		const a = this._prev_value.sort();
		const b = this.getValue().sort();

		if (a.length != b.length) {
			return true;
		}

		for (let i = 0; a.length > i; i++) {
			if (a[i] != b[i]) {
				return true;
			}
		}

		return false;
	}
}
