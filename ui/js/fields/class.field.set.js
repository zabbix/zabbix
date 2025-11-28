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


class CFieldSet extends CField {

	/**
	 * Object of input field references that particular FieldSet instance holds.
	 *
	 * @type {Object}
	 */
	#fields = {};

	init() {
		super.init();

		this.#discoverAllFields();

		const observer = new MutationObserver(observations => {
			let node_change = false;
			let attribute_change = false;

			for (const obs of observations) {
				if (obs.type === 'childList') {
					node_change = [...obs.addedNodes, ...obs.removedNodes]
						.some(node => {
							const is_field = '[data-field-type]:not([data-temp-field])';

							return node.nodeType == Node.ELEMENT_NODE
								&& (node.matches(is_field) || node.querySelector(is_field));
						});

					if (node_change) {
						break;
					}
				}
				else if (obs.type === 'attributes') {
					attribute_change = attribute_change || obs.attributeName === 'data-skip-from-submit';
				}
			}

			if (node_change) {
				this.#discoverAllFields();
			}

			if (node_change || attribute_change) {
				this.onBlur();
			}
		});

		observer.observe(this._field, {
			attributeFilter: ['data-skip-from-submit'],
			childList: true,
			subtree: true
		});
	}

	onBlur() {
		const child_fields = Object.values(this.#fields).map((field) => field.getName());

		this._field.dispatchEvent(new CustomEvent('field.change', {detail: {
			source_fields: [this.getName(), ...child_fields]
		}}));
	}

	#discoverAllFields() {
		const fields = {};
		const fields_rediscovered = [];

		for (const discovered_field of CForm.findAllFields(this._field)) {
			const field_type = discovered_field.getAttribute('data-field-type');

			if (field_type in CForm.field_types) {
				let field_instance = null;

				for (const existing_field of Object.values(this.#fields)) {
					if (existing_field.isSameField(discovered_field)) {
						existing_field.updateState();
						fields_rediscovered.push(existing_field.getName());
						field_instance = existing_field;
						break;
					}
				}

				if (field_instance === null) {
					field_instance = new CForm.field_types[field_type](discovered_field);
					field_instance.init();
					field_instance.setTabId(this._tab_id);

					discovered_field.addEventListener('field.change', (e) => {
						if (!e.target.hasAttribute('data-prevent-validation-on-change')) {
							this.fieldChanged(e.detail.source_fields);
						}
					});
				}

				const name = field_instance.getName().replace(new RegExp(`^${this.getName()}`), '');

				fields[name] = field_instance;
			}
		}

		for (const field of Object.values(this.#fields)) {
			if (field.hasErrorHint() && fields_rediscovered.includes(field.getName()) === false) {
				field.removeErrorHint();
			}
		}

		this.#fields = fields;
	}

	getName() {
		return this._field.getAttribute('data-field-name');
	}

	getInnerValue(trim_value) {
		let result = {};

		for (const field of Object.values(this.#fields)) {
			if (field._field.hasAttribute('data-skip-from-submit') || field.isDisabled()) {
				continue;
			}

			/*
			 * This code converts name of the simple field (belonged to the fieldset) to the array of name keys.
			 * The main part that matches fieldset name is skipped.
			 *
			 * For example, field name: interfaces[0][port] must be converted to following array: ['0', 'port'].
			 */
			const name_parts = field.getName().replace(/]$/, '').split(/\]\[|\[/);

			if (name_parts[0] === this.getName()) {
				name_parts.shift();
			}

			result = objectSetDeepValue(result, name_parts, trim_value ? field.getValueTrimmed() : field.getValue());
		}

		return result;
	}

	getValue() {
		return this.getInnerValue(false);
	}

	getValueTrimmed() {
		return this.getInnerValue(true);
	}

	hasErrors() {
		for (const field of Object.values(this.#fields)) {
			if (field.hasErrors()) {
				return true;
			}
		}

		return super.hasErrors();
	}

	setErrors(errors, force_display_errors) {
		if (typeof errors === 'object' && '' in errors) {
			errors[''].forEach((error) => super.setErrors(error));
			delete errors[''];
		}

		this.#fieldsSetErrors(errors, force_display_errors);
	}

	unsetErrors() {
		const errors = {
			'': [{message: '', level: -1}]
		};

		for (const field of Object.values(this.#fields)) {
			errors[field.getName().replace(new RegExp(`^${this.getName()}`), '')] = [{message: '', level: -1}];
		}

		this.setErrors(errors);
	}

	showErrors() {
		super.showErrors();

		for (const field of Object.values(this.#fields)) {
			field.showErrors();
		}
	}

	_appendErrorHint(error_hint) {
		let target_insert_after = this._field.closest('.table-forms-separator')
			? this._field.closest('.table-forms-separator')
			: this._field;

		target_insert_after.after(error_hint);
	}

	#fieldsSetErrors(errors, force_display_errors) {
		let missing_field_errors = {};

		for (const [key, field_errors] of Object.entries(errors)) {
			const key_full = key.charAt(0) === '[' ? key : `[${key}]`;

			if (key_full in this.#fields) {
				if (this.#fields[key_full].hasChanged() || this.#hasObjectChanged(key_full) || force_display_errors
						|| field_errors.some((error) => error.message === '' || error.level == CFormValidator.ERROR_LEVEL_UNIQ)) {
					field_errors.forEach((error) => this.#fields[key_full].setErrors(error));

					this._global_errors = {...this._global_errors, ...this.#fields[key_full].getGlobalErrors()};
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

	focusErrorField() {
		for (const field of Object.values(this.#fields)) {
			if (field.hasErrors()) {
				field.focusErrorField();
				break;
			}
		}
	}

	findFieldByName(name) {
		for (const field of Object.values(this.#fields)) {
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

	getFields() {
		return this.#fields;
	}

	hasChanged() {
		for (const field of Object.values(this.#fields)) {
			if (field.hasChanged()) {
				return true;
			}
		}

		return false;
	}

	#hasObjectChanged(field_key) {
		// Object name must be in form X[Y][Z]. This function returns `Y`. `Y` is equal for all same object fields.
		const getObjKey = (n) => n.replace(new RegExp(`^${this.getName()}`), '').replace(/]$/, '').split(/\]\[|\[/)[1];

		return Object.values(this.#fields).filter((field) => {
			return getObjKey(field.getName()) == getObjKey(field_key);
		}).some(field => field.hasChanged());
	}
}
