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


class CForm {

	static field_types = {
		'array': CFieldArray,
		'checkbox': CFieldCheckBox,
		'hidden': CFieldHidden,
		'multiline': CFieldMultiline,
		'multiselect': CFieldMultiselect,
		'radio-list': CFieldRadioList,
		'set': CFieldSet,
		'text-box': CFieldTextBox,
		'textarea': CFieldTextarea,
		'z-select': CFieldZSelect,
		'file': CFieldFile
	};
	#form = null;
	#rules = null;
	#validators = [];
	#fields = {};
	#tabs;
	#listeners = {};
	#validate_changes_call = null;
	#validate_changes_timeout = null;
	#mousedown_registered = false;
	#general_errors = {};
	#message_box = null;
	#custom_validation = [];
	#form_ready = false;

	constructor(form, rules) {
		this.#form = form;
		this.#rules = rules;
		this.#tabs = this.#form.querySelector('.ui-tabs');

		this.init();

		this.discoverAllFields();
		this.#startGarbageCollector();
		this.#form_ready = true;
	}

	init() {
		this.#listeners.mousedown = () => {
			this.#mousedown_registered = true;
			clearTimeout(this.#validate_changes_timeout);
		};

		this.#listeners.mouseup = () => {
			if (this.#mousedown_registered && this.#validate_changes_call !== null) {
				this.#validate_changes_timeout = setTimeout(this.#validate_changes_call);
			}
			this.#mousedown_registered = false;
		}

		this.#form.addEventListener('form.destroyed', () => this.release());

		// Before executing validation and displaying an error message we need to wait till form is updated
		// based on performed user action.
		document.addEventListener('mousedown', this.#listeners.mousedown);
		document.addEventListener('mouseup', this.#listeners.mouseup);
	}

	reload(rules) {
		this.#reset();

		this.#rules = rules;
		this.discoverAllFields();

		// Remove all errors from fields.
		Object.values(this.#fields).forEach((field) => {
			field.unsetErrors();
			field.showErrors();
		});

		this.#form_ready = true;
	}

	#reset() {
		this.#form_ready = false;

		for (const field of Object.values(this.#fields)) {
			// Everything else garbage collector is capable to do.
			field.cancelDelayedValidation();
		}

		this.#custom_validation = [];
		this.abortValidationInProgress();

		clearTimeout(this.#validate_changes_timeout);
		this.#validate_changes_call = null;
	}

	release() {
		this.#reset();
		document.removeEventListener('mousedown', this.#listeners.mousedown);
		document.removeEventListener('mouseup', this.#listeners.mouseup);
	}

	discoverAllFields() {
		const fields = {};

		for (const discovered_field of CForm.findAllFields(this.#form)) {
			let field_instance = null;

			// If instance is already created.
			for (const existing_field of Object.values(this.#fields)) {
				if (existing_field.isSameField(discovered_field)) {
					existing_field.updateState();
					field_instance = existing_field;
					break;
				}
			}

			// If this is a new field.
			if (field_instance === null) {
				const field_type = discovered_field.getAttribute('data-field-type');

				field_instance = new CForm.field_types[field_type](discovered_field);
				field_instance.init();
				field_instance.setTabId(CForm.findTabId(discovered_field));

				discovered_field.addEventListener('field.change', e => {
					if (!e.target.hasAttribute('data-prevent-validation-on-change')) {
						this.validateChanges(e.detail.source_fields)
					}
				});
			}

			fields[field_instance.getName()] = field_instance;
		}

		this.#fields = fields;
	}

	static findTabId(field) {
		const tab_panel = field.closest('.ui-tabs-panel');

		if (tab_panel === null) {
			return null;
		}

		return tab_panel.id;
	}

	static findAllFields(elem) {
		const all_fields = elem.querySelectorAll("[data-field-type]");
		const sub_fields = elem.querySelectorAll(":scope [data-field-type] [data-field-type]");

		// This is difference between arrays.
		return Object.values(all_fields).filter(x => !Object.values(sub_fields).includes(x));
	}

	getAllValues() {
		const fields = {};

		for (const [key, field] of Object.entries(this.#fields)) {
			field.cancelDelayedValidation();

			const key_parts = [...key.matchAll(/[^\[\]]+|\[\]/g)];

			let key_fields = fields;

			for (let i = 0; i < key_parts.length; i++) {
				const key_part = key_parts[i][0];

				if (i === key_parts.length - 1) {
					if (typeof field.getExtraFields === 'function') {
						if (!field.isDisabled()) {
							for (const [extra_key, values] of Object.entries(field.getExtraFields())) {
								key_fields[extra_key] = values;
							}
						}
					}
					else {
						if (!field.isDisabled()) {
							key_fields[key_part] = field.getValueTrimmed();
						}
					}

					break;
				}

				if (!(key_part in key_fields)) {
					key_fields[key_part] = {};
				}

				key_fields = key_fields[key_part];
			}
		}

		return fields;
	}

	validateChanges(fields, force_display_errors = false) {
		// Let form finish its transformations before doing validation.
		this.#validate_changes_call = () => {
			if (!this.#form_ready) {
				return;
			}

			this.#validate_changes_call = null;

			const validator = new CFormValidator(this.#rules);
			this.#validators.push(validator);
			const values = this.getAllValues();

			// Validate only fields still present in form.
			fields = fields.filter((field) => {
				const parts = field.replace(/]$/, '').split(/\]\[|\[/);
				let data = values;

				for (const part of parts) {
					if (part in data) {
						data = data[part];
					}
					else {
						return false;
					}
				}

				return true;
			});

			validator.validateChanges(values, fields)
				.then(() => this.#custom_validation.forEach((fn) => fn.call(this, values)))
				.then(() => {
					if (validator !== null) {
						this.setErrors(validator.getErrors(), undefined, force_display_errors);
						this.renderErrors();
					}
				})
				.catch((error) => {
					if (error.cause !== 'RulesError' && error.type !== 'abort') {
						console.error(error);
					}
				})
				.finally(() => {
					// Remove validator from array
					const index = this.#validators.indexOf(validator);
					if (index !== -1) {
						this.#validators.splice(index, 1);
					}
				});
		};

		// Mousedown event, if there is such, is registered before blur event on field.
		if (!this.#mousedown_registered) {
			this.#validate_changes_timeout = setTimeout(this.#validate_changes_call);
		}
	}

	extendValidation(callback) {
		throw new Error('Missing implementation.');
		this.#custom_validation.push(callback);
	}

	/**
	 * Client-side validation before form submit. Having already running validation, it will be stopped to perform
	 * a new one.
	 *
	 * @param {Object} values
	 */
	validateSubmit(values) {
		if (!this.#form_ready) {
			return;
		}

		clearTimeout(this.#validate_changes_timeout);
		this.abortValidationInProgress();

		const validator = new CFormValidator(this.#rules);
		this.#validators.push(validator);

		return validator.validateSubmit(values)
			.then((result) => {
				result = result && this.#custom_validation.every((fn) => fn.call(this, values));

				return result;
			})
			.then((result) => {
				Object.values(this.#fields).forEach((field) => {
					field.setChanged();
				});
				this.setErrors(validator.getErrors(), true, true);
				this.renderErrors();

				return result;
			})
			.catch((err) => {
				if (err.cause !== 'RulesError' && err.type !== 'abort') {
					console.error(err);
				}
			})
			.finally(() => {
				// Remove validator from array
				const index = this.#validators.indexOf(validator);
				if (index !== -1) {
					this.#validators.splice(index, 1);
				}
			});
	}

	abortValidationInProgress() {
		for (const validator of this.#validators) {
			validator.abortValidationInProgress();
		}

		this.#validators = [];
	}

	/**
	 * Function to call to validate specific fields before some action. For example, to open 'test' popup, there can be
	 * subset of fields that have to be validated first. Function returns promise to externally control when validation
	 * is completed so that popup can be opened only when validation succeeds.
	 *
	 * @param {Array} fields
	 * @param {?Object} rules
	 *
	 * @returns {Promise}
	 */
	validateFieldsForAction(fields, rules) {
		const validator = new CFormValidator(rules ? rules : this.#rules);

		return validator.validateChanges(this.getAllValues(), fields)
			.then((result) => {
				this.setErrors(validator.getErrors(), true);
				this.renderErrors();

				return result;
			})
			.catch((error) => {
				if (error.cause !== 'RulesError' && error.type !== 'abort') {
					console.error(error);
				}
			});
	}

	/**
	 * Sets errors in field objects and in #general_errors array.
	 */
	setErrors(raw_errors, focus_error_field, force_display_errors = false) {
		const {field_errors, general_errors} = this.convertRawErrors(raw_errors);

		Object.entries(field_errors).forEach(([key, errors]) => {
			if (key in this.#fields) {
				const field = this.#fields[key];

				if (field instanceof CFieldSet) {
					field.setErrors(errors, force_display_errors);
				}
				else if (force_display_errors || field.hasChanged() || errors.some((error) => error.message === '')) {
					errors.forEach((error) => field.setErrors(error));
				}

				this.addGeneralErrors(field.getGlobalErrors());
			}
			else {
				errors.forEach((error) => {
					if (error.message !== '') {
						console.log('Validation error for missing field "' + key + '": ' + error.message);
					}
				});
			}
		});

		Object.entries(general_errors).forEach(([key, errors]) => {
			errors.forEach((error) => {
				if (error.message !== '') {
					console.log('Validation error for missing field "' + key + '": ' + error.message);
				}
			});
		});

		if ('' in raw_errors) {
			this.addGeneralErrors({'': raw_errors['']});
		}

		if (focus_error_field) {
			this.focusErrorField(Object.keys(field_errors));
		}
	}

	/**
	 * Function creates field-error mapping with real field names as keys and errors as values.
	 *
	 * @param {Object} raw_errors  Field paths and errors from validator.
	 *
	 * @returns {Object}
	 */
	convertRawErrors(raw_errors) {
		const field_errors = {};

		Object.values(this.#fields).forEach((field) => {
			const field_name = field.getName();
			const field_path = field.getPath();
			const subfield_path = new RegExp('^' + field_path + '/');

			if (field instanceof CFieldMultiselect) {
				const affixed_path = '/' + field_name + '_new';
				const affixed_subfield = new RegExp('^/' + field_name + '_new/');

				for (const error_path in raw_errors) {
					const affixed = error_path === affixed_path || affixed_subfield.test(error_path);

					if (!affixed) {
						continue;
					}

					if (raw_errors[error_path].length) {
						raw_errors[field_path] = [...raw_errors[field_path], ...raw_errors[error_path]]
							.filter(({message}) => message.length);
						raw_errors[error_path] = [];
					}
				}
			}

			Object.entries(raw_errors).filter(([path]) => {
				return field_path === path || subfield_path.test(path);
			}).forEach(([path, errors]) => {
				if (!(field_name in field_errors)) {
					field_errors[field_name] = {};
				}

				delete raw_errors[path];

				const subfield_name = path.split('/').slice(1).map((part, i) => {
					return i == 0 ? part : '[' + part + ']';
				}).join('').slice(field_name.length);

				if (subfield_name === '') {
					if (!Array.isArray(field_errors[field_name])) {
						if (Object.values(field_errors[field_name]).length == 0) {
							field_errors[field_name] = field instanceof CFieldSet ? {'': []} : [];
						}
						else {
							field_errors[field_name] = {'': Object.values(field_errors[field_name])};
						}
					}

					if (field instanceof CFieldSet) {
						errors.forEach((error) => field_errors[field_name][''].push(error));
					}
					else {
						errors.forEach((error) => field_errors[field_name].push(error));
					}
				}
				else {
					if (Array.isArray(field_errors[field_name])) {
						field_errors[field_name] = field_errors[field_name].length
							? {'': field_errors[field_name]}
							: {};
					}

					field_errors[field_name][subfield_name] = errors;
				}
			});
		});

		return {field_errors, general_errors: raw_errors};
	}

	addGeneralErrors(errors) {
		Object.entries(errors).forEach(([field_name, error]) => {
			if (error !== '') {
				this.#general_errors[field_name] = error;
			}
			else {
				delete this.#general_errors[field_name];
			}
		});
	}

	focusErrorField(field_names) {
		for (const [key, field] of Object.entries(this.#fields)) {
			if (field_names.includes(key) && field.hasErrors()) {
				if (field.getTabId() !== null) {
					$(this.#tabs).tabs({active: $(`a[href="#${field.getTabId()}"]`, $(this.#tabs)).parent().index()});
				}

				field.focusErrorField();
				break;
			}
		}
	}

	/**
	 * Show errors from field objects on HTML.
	 */
	renderErrors() {
		for (const field of Object.values(this.#fields)) {
			field.showErrors();
		}

		this.processGeneralErrors();
	}

	processGeneralErrors() {
		const errors_count = Object.keys(this.#general_errors).length;

		if (errors_count != 0) {
			const errors = Object.entries(this.#general_errors).map(([field_name, error]) => {
				return field_name.length ? field_name + ': ' + error : error;
			});
			const {messages, title} = errors_count > 1
				? {messages: errors, title: t('Page received incorrect data')}
				: {messages: [], title: errors[0]};

			if (this.#message_box === null || this.#message_box[0].parentNode == null) {
				this.#message_box = makeMessageBox('bad', messages, title);
				this.#message_box.insertBefore(this.#form);
			}
			else {
				const new_message_box = makeMessageBox('bad', messages, title);
				this.#message_box.replaceWith(new_message_box);
				this.#message_box = new_message_box;
			}
		}
		else if (this.#message_box) {
			this.#message_box.remove();
			this.#message_box = null;
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

	/**
	 * Internal garbage collector to delete field references once the original DOM element is removed.
	 */
	#startGarbageCollector() {
		const observer = new MutationObserver((observations) => {
			observations.forEach((obs) => {
				[...obs.removedNodes]
					.filter(element => element.nodeType == Node.ELEMENT_NODE && element.hasAttribute('data-field-type'))
					.forEach(element => {
						Object.entries(this.#fields).forEach(([key, field]) => {
							if (field.isSameField(element)) {
								delete this.#fields[key];
							}
						});
					});
			});
		});

		observer.observe(this.#form, {
			childList: true,
			subtree: true
		});
	}
}
