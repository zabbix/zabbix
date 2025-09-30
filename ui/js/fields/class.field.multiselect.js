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


class CFieldMultiselect extends CField {

	init() {
		super.init();

		$('button, input[type="text"]', this._field.parentNode).on('blur', () => {
			this.validate_if_no_focus = setTimeout(() => {
				if (!this._field.isConnected || $(this._field).multiSelect('getOption', 'disabled') === true) {
					return;
				}

				let element = overlays_stack.end()?.element;
				element = element instanceof jQuery ? element[0] : element;

				if (!this._field.parentNode.contains(document.activeElement)
						&& !this._field.parentNode.contains(element)) {
					this.onBlur();
				}
			}, 250);
		});

		$('input[type="text"]', this._field.parentNode).on('focusin', () => {
			clearTimeout(this.validate_if_no_focus);
			this.validate_if_no_focus = null;
		});

		$(this._field).on('change', () => this.onKeypress());
	}

	onBlur() {
		this.cancelDelayedValidation();
		this.setChanged();

		this._field.dispatchEvent(new CustomEvent('field.change', {
			detail: {
				source_fields: this.#getFieldNames()
			}
		}));
	}

	#getFieldNames() {
		const names = [this.getName()];

		if ($(this._field).multiSelect('getOption', 'addNew')) {
			names.push(this.getName() + '_new');
		}

		return names;
	}

	getName() {
		return $(this._field).multiSelect('getOption', 'name').replace(/\[\]$/, '');
	}

	getValue() {
		return this.getExtraFields();
	}

	getExtraFields() {
		const return_id = $(this._field).multiSelect('getOption', 'selectedLimit') == 1;
		const values = $(this._field).multiSelect('getOption', 'addNew')
			? {
				[this.getName()]: return_id ? null : [],
				[this.getName() + '_new']: return_id ? null : []
			}
			: {
				[this.getName()]: return_id ? null : []
			};

		if (this.isDisabled()) {
			return null;
		}

		$(this._field).multiSelect('getData').forEach((value) => {
			const field_name = value.isNew ? this.getName() + '_new' : this.getName();

			if (!return_id) {
				values[field_name].push(value.id);
			}
			else if (values[field_name] === null) {
				values[field_name] = value.id;
			}
		});

		return values;
	}

	isDisabled () {
		return $(this._field).multiSelect('getOption', 'disabled') === true;
	}

	_appendErrorHint(error_hint) {
		this._field.parentNode.parentNode.appendChild(error_hint);
	}

	_toggleError() {
		this._field.parentNode.classList.toggle('has-error', this.hasErrorHint());
	}

	focusErrorField() {
		super.focusErrorField();
		$('input[type="text"]', this._field).focus();
	}
}
