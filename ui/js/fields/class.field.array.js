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


class CFieldArray extends CFieldCollection {

	getInnerValue(trim_value) {
		const result = [];

		for (const [key, field] of Object.entries(this.getFields())) {
			const value = trim_value ? field.getValueTrimmed() : field.getValue();

			if (value !== null) {
				result[key] = value;
			}
		}

		return result;
	}

	_normalizeSubfieldName(subfield_name, index) {
		const subname = super._normalizeSubfieldName(subfield_name, index);

		return subname === '[]' ? `${index}` : subname.substring(1, subname.length - 1);
	}

	_bindDiscoveredFieldChangeEvent(discovered_field, field_type) {
		if (field_type === 'checkbox') {
			discovered_field.addEventListener('field.change', (e) => {
				if (!e.target.hasAttribute('data-prevent-validation-on-change')) {
					this.fieldChanged([this.getName(), ...e.detail.source_fields]);
				}
			});
		}
		else {
			super._bindDiscoveredFieldChangeEvent(discovered_field, field_type);
		}
	}

	_fieldsSetErrors(errors, force_display_errors) {
		for (const [key, field_errors] of Object.entries(errors)) {
			const key_full = key.replaceAll('[', '').replaceAll(']', '');

			if (key_full in this.getFields()) {
				const field = this.getFields()[key_full];
				// These errors need to be added even if field is not changed, but smaller index one was.
				const error_levels = [CFormValidator.ERROR_LEVEL_UNIQ,
					CFormValidator.ERROR_LEVEL_OBJECTS_COUNT
				];

				if (field.hasChanged() || force_display_errors
						|| field_errors.some((error) => error.message === '' || error_levels.includes(error.level))) {
					field_errors.forEach((error) => field.setErrors(error));

					this._global_errors = {...this._global_errors, ...field.getGlobalErrors()};
				}
			}
			else if (errors[key] !== '') {
				// Field is not present in fields, display generic error.
				let extended_name = this.getName() + key;
				extended_name = '/' + extended_name.replaceAll('[', '/').replaceAll(']', '');

				field_errors.forEach(error => {
					if (error.message !== '') {
						console.log('Validation error for missing field "' + extended_name + '": ' + error.message);
					}
				});
			}
		}
	}
}
