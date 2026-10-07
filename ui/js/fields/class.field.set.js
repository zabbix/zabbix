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


class CFieldSet extends CFieldCollection {

	#subFieldNameParts(sub_field_name) {
		if (!sub_field_name.startsWith(this.getName())) {
			return false;
		}

		return sub_field_name
			.slice(this.getName().length)
			.replace(/^\[|]$/g, '')
			.split(/\]\[/);
	}

	getInnerValue(trim_value) {
		let result = Object.create(null);
		let simple_fields = Object.create(null);

		for (const field of Object.values(this.getFields())) {
			if (field._field.hasAttribute('data-skip-from-submit') || field.isDisabled()) {
				continue;
			}

			if (typeof field.getExtraFields === 'function') {
				for (const [field_name, field_value] of Object.entries(field.getExtraFields())) {
					simple_fields[field_name] = field_value;
				}
			}
			else {
				simple_fields[field.getName()] = trim_value ? field.getValueTrimmed() : field.getValue();
			}
		}

		for (const [key, value] of Object.entries(simple_fields)) {
			const name_parts = this.#subFieldNameParts(key);

			if (name_parts === false) {
				continue;
			}

			result = objectSetDeepValue(result, name_parts, value);
		}

		return result;
	}

	_appendErrorHint(error_hint) {
		let target_insert_after = this._field.closest('.table-forms-separator')
			? this._field.closest('.table-forms-separator')
			: this._field;

		target_insert_after.after(error_hint);
	}

	_fieldsSetErrors(errors, force_display_errors) {
		for (const [key, field_errors] of Object.entries(errors)) {
			const key_full = key.charAt(0) === '[' ? key : `[${key}]`;

			if (key_full in this.getFields()) {
				const field = this.getFields()[key_full];
				// These errors need to be added even if field is not changed, but smaller index one was.
				const error_levels = [CFormValidator.ERROR_LEVEL_UNIQ,
					CFormValidator.ERROR_LEVEL_OBJECTS_COUNT
				];

				if (field instanceof CFieldCollection) {
					field.setErrors(field_errors, force_display_errors);
					this._global_errors = {...this._global_errors, ...field.getGlobalErrors()};
				}
				else if (field.hasChanged() || this.#hasObjectChanged(key_full) || force_display_errors
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

	findFieldByName(name) {
		for (const field of Object.values(this.getFields())) {
			if (name.startsWith(field.getName())) {
				if (field.getName() === name) {
					return field;
				}
				else if (field instanceof CFieldSet) {
					return field.findFieldByName(name);
				}
			}
		}

		return null;
	}

	#hasObjectChanged(field_key) {
		// Object name must be in form X[Y][Z]. This function returns `Y`. `Y` is equal for all same object fields.
		const getObjKey = (n) => n.replace(new RegExp(`^${this.getName()}`), '').replace(/]$/, '').split(/\]\[|\[/)[1];

		return Object.values(this.getFields()).filter((field) => {
			return getObjKey(field.getName()) == getObjKey(field_key);
		}).some(field => field.hasChanged());
	}
}
