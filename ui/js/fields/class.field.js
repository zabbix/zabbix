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


class CField {

	_field;
	_tab_id = null;
	_error_hint = null;
	_prev_value = null;
	_delayed_field_change = null;
	_changed = false;
	_error_msg = null;
	_error_level = -1;
	_global_errors = {};
	_error_container = null;
	_error_label;
	_allow_trim;

	constructor(field) {
		this._field = field;

		if (this._field.hasAttribute('data-error-container')) {
			this._error_container = document.getElementById(this._field.getAttribute('data-error-container'));
		}

		this._error_label = this._field.getAttribute('data-error-label');
		this._allow_trim = !this._field.hasAttribute('data-notrim');
	}

	init() {
		this._prev_value = this.getValue();
	}

	onBlur() {
		this.cancelDelayedValidation();
		this.fieldChanged();
	}

	onKeypress() {
		this.cancelDelayedValidation();

		if (!this.hasErrors() || this._prev_value === this.getValue()) {
			return;
		}

		// Make the check after 500 milliseconds of inactivity.
		this._delayed_field_change = setTimeout(() => this.fieldChanged(), 500);
	}

	cancelDelayedValidation() {
		clearTimeout(this._delayed_field_change);
		this._delayed_field_change = null;
	}

	fieldChanged(source_fields) {
		if (source_fields === undefined) {
			source_fields = [this.getName()];
		}

		this.setChanged();
		this._prev_value = this.getValue();
		this._field.dispatchEvent(new CustomEvent('field.change', {detail: {source_fields}}));
	}

	setChanged() {
		this._changed = true;
	}

	hasChanged() {
		return this._changed;
	}

	setGlobalError(message) {
		this._global_errors[this.getName()] = message;
	}

	getGlobalErrors() {
		return this._global_errors;
	}

	getName() {
		// abstract method
	}

	getValue() {
		return null;
	}

	getValueTrimmed() {
		return this.getValue();
	}

	getPath() {
		return '/' + this.getName().replaceAll('[', '/').replaceAll(']', '');
	}

	isDisabled() {
		return false;
	}

	isSameField(field) {
		return field == this._field;
	}

	/**
	 * This method is called when field is rediscovered. Together with this.isSameField().
	 * Its purpose is to update field's state if rediscovery could have been triggered by this change.
	 */
	updateState() {
		// blank
	}

	setTabId(tab_id) {
		this._tab_id = tab_id;
	}

	getTabId() {
		return this._tab_id;
	}

	hasErrors() {
		return this._error_msg !== null || Object.keys(this._global_errors).length > 0;
	}

	setErrors({message, level}) {
		if (message === '') {
			if (this._error_level >= level) {
				this._error_msg = null;
				this._error_level = -1;
				this._global_errors = {};
			}
		}
		else {
			if (!document.body.contains(this._field)) {
				console.log('Validation error for missing field "' + this.getName() + '": ' + message);
			}
			else if (this._error_level === -1 || this._error_level >= level) {
				this._error_msg = message;
				this._error_level = level;
			}
		}
	}

	unsetErrors() {
		this.setErrors({message: '', level: -1});
	}

	showErrors() {
		if (this._error_msg === null) {
			if (this.hasErrorHint()) {
				this.removeErrorHint();
			}
		}
		else {
			const new_hint = this.errorHint();

			if (!this.hasErrorHint() || this._error_hint.textContent !== new_hint.textContent) {
				if (this.hasErrorHint()) {
					this.removeErrorHint();
				}

				this._error_hint = new_hint;

				if (this._error_container !== null) {
					this._appendErrorToContainer(this._error_hint);
				}
				else {
					this._appendErrorHint(this._error_hint);
				}
			}
		}

		this._toggleError();

		return this;
	}

	errorHint() {
		const error_hint = document.createElement(this._error_container !== null ? 'li' : 'span');

		error_hint.classList.add('error');
		error_hint.textContent = this._error_label !== null && this._error_level != CFormValidator.ERROR_LEVEL_UNIQ
			? sprintf(t('%1$s: %2$s'), this._error_label, this._error_msg)
			: this._error_msg;

		return error_hint;
	}

	hasErrorHint() {
		return this._error_hint !== null;
	}

	removeErrorHint() {
		this._error_hint.remove();
		this._error_hint = null;

		if (this._error_container !== null) {
			this._updateErrorContainer();
		}
	}

	focusErrorField() {
		const accordion_item = this._field.closest('.list-accordion-item:not(.list-accordion-item-opened)');

		if (accordion_item) {
			const index = [...accordion_item.parentElement.children].indexOf(accordion_item)

			if (index != -1) {
				jQuery(accordion_item.parentElement).zbx_vertical_accordion('expandNth', index);
			}
		}
	}

	_appendErrorHint(error_hint) {
		this._field.parentNode.appendChild(error_hint);
	}

	_toggleError() {
		this._field.classList.toggle('has-error', this.hasErrorHint());
	}

	_appendErrorToContainer(error_hint) {
		let errors_list = this._error_container.querySelector('ul');

		if (errors_list === null) {
			errors_list = document.createElement('ul');
			errors_list.classList.add('errors-list');

			this._error_container.appendChild(errors_list);
		}
		else {
			errors_list.classList.add('list-dashed');
		}

		errors_list.appendChild(error_hint);
	}

	_updateErrorContainer() {
		if (this._error_container === null) {
			return;
		}

		const errors_list = this._error_container.querySelector('ul');

		if (errors_list !== null) {
			if (errors_list.childElementCount == 0) {
				errors_list.remove();
			}
			else if (errors_list.childElementCount == 1) {
				errors_list.classList.remove('list-dashed');
			}
		}
	}
}
