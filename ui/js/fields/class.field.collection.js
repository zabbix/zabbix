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


class CFieldCollection extends CField {

	/**
	 * Object of input field references that particular FieldCollection instance holds.
	 *
	 * @type {Object}
	 */
	#fields = Object.create(null);

	init() {
		super.init();

		this.#discoverAllFields();

		const observer = new MutationObserver((observations) => this.#detectFieldChanges(observations));

		observer.observe(this._field.parentNode, {
			attributeFilter: ['data-skip-from-submit'],
			childList: true,
			subtree: true
		});
	}

	#detectFieldChanges(observations) {
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
	}

	onBlur() {
		this.setChanged();
		const child_fields = Object.values(this.#fields).map((field) => field.getName());

		this._field.dispatchEvent(new CustomEvent('field.change',
			{detail: {source_fields: [this.getName(), ...child_fields]}}
		));
	}

	/**
	 * Attaches blur handling to a button. Validation is done when the button loses focus, unless the blur
	 * was caused by opening the related overlay.
	 *
	 * @param {string} button_class	 Class name for the button.
	 * @param {string} dialogue_id   ID for a dialogue opened by the button.
	 */
	setButtonOnBlur(button_class, dialogue_id) {
		const button = this._field.querySelector('.' + button_class);
		let timeout_id = null;

		button.addEventListener('blur', () => {
			clearTimeout(timeout_id);
			timeout_id = setTimeout(() => {
				if (document.activeElement !== button && overlays_stack.getById(dialogue_id) === undefined) {
					this.onBlur();
				}
			}, 250);
		});
	}

	_normalizeSubfieldName(subfield_name, index) {
		return subfield_name.substring(this.getName().length);
	}

	_bindDiscoveredFieldChangeEvent(discovered_field, field_type) {
		discovered_field.addEventListener('field.change', (e) => {
			if (!e.target.hasAttribute('data-prevent-validation-on-change')) {
				this.fieldChanged(e.detail.source_fields);
			}
		});
	}

	#discoverAllFields() {
		const fields = Object.create(null);
		const fields_rediscovered = [];
		let index = 0;

		for (const discovered_field of CForm.findAllFields(this._field)) {
			const field_type = discovered_field.getAttribute('data-field-type');

			if (field_type in CForm.field_types) {
				let field_instance = null;

				for (const [field_name, existing_field] of Object.entries(this.#fields)) {
					if (existing_field.isSameField(discovered_field)) {
						existing_field.updateState();
						fields_rediscovered.push(field_name);
						field_instance = existing_field;
						break;
					}
				}

				if (field_instance === null) {
					field_instance = new CForm.field_types[field_type](discovered_field);
					field_instance.init();
					field_instance.setTabId(this._tab_id);

					this._bindDiscoveredFieldChangeEvent(discovered_field, field_type);
				}

				if (!field_instance.getName().startsWith(this.getName())) {
					fields[field_instance.getName()] = field_instance;
					console.log(`Field collection ${this.getName()} has invalid field ${field_instance.getName()}`);
				}
				else {
					const subname = this._normalizeSubfieldName(field_instance.getName(), index++);
					fields[subname] = field_instance;
				}
			}
		}

		for (const [field_name, field] of Object.entries(this.#fields)) {
			if (field.hasErrorHint() && fields_rediscovered.includes(field_name) === false) {
				field.removeErrorHint();
			}
		}

		this.#fields = fields;
	}

	getName() {
		return this._field.getAttribute('data-field-name');
	}

	getInnerValue(trim_value) {
		// abstract method
	}

	getValue() {
		return this.getInnerValue(false);
	}

	getValueTrimmed() {
		return this.getInnerValue(true);
	}

	updateState() {
		this.#discoverAllFields();
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
			if (force_display_errors || this.hasChanged()) {
				errors[''].forEach((error) => super.setErrors(error));
			}

			delete errors[''];
		}

		this._fieldsSetErrors(errors, force_display_errors);
	}

	_fieldsSetErrors(errors, force_display_errors) {
		// abstract method
	}

	unsetErrors() {
		const errors = Object.create(null);
		errors[''] = [{message: '', level: -1}];

		for (const field_key of Object.keys(this.#fields)) {
			errors[field_key] = [{message: '', level: -1}];
		}

		this.setErrors(errors);
	}

	showErrors() {
		super.showErrors();

		for (const field of Object.values(this.#fields)) {
			field.showErrors();
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

	getFields() {
		return this.#fields;
	}

	hasChanged() {
		for (const field of Object.values(this.#fields)) {
			if (field.hasChanged()) {
				return true;
			}
		}

		return super.hasChanged();
	}

	lock() {
		let res = false;

		for (const field of Object.values(this.#fields)) {
			res = field.lock() || res;
		}

		return res;
	}

	unlock() {
		let res = false;

		for (const field of Object.values(this.#fields)) {
			res = field.unlock() || res;
		}

		return res;
	}
}
