<?php declare(strict_types = 0);
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
?>


window.telemetry_condition_popup = new class {

	#overlay;
	#dialogue;
	#form_element;
	#form;
	#complex_columns;
	#operators;
	#column;
	#operator;

	init({rules, complex_columns, condition_operators}) {
		this.#overlay = overlays_stack.end();
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);

		this.#complex_columns = complex_columns;
		this.#operators = condition_operators;
		this.#column = this.#form_element.querySelector('#column');
		this.#operator = this.#form_element.querySelectorAll('[name="operator"]');

		this.#column.addEventListener('change', () => this.#updateFieldVisibility());
		for (const radio of this.#operator) {
			radio.addEventListener('change', () => this.#updateFieldVisibility());
		}

		this.#updateFieldVisibility();
	}

	#updateFieldVisibility() {
		const display_none = <?= json_encode(ZBX_STYLE_DISPLAY_NONE) ?>;
		const is_complex = this.#complex_columns.includes(this.#column.value);

		const allowed_operators = is_complex ? this.#operators.complex : this.#operators.simple;
		let has_checked = false;

		for (const radio of this.#operator) {
			const hidden = !allowed_operators.includes(parseInt(radio.value, 10));

			radio.closest('li').hidden = hidden;

			if (hidden && radio.checked) {
				radio.checked = false;
			}

			has_checked = has_checked || radio.checked;
		}

		if (!has_checked) {
			[...this.#operator]
				.find((radio) => parseInt(radio.value, 10) === <?= CONDITION_OPERATOR_EQUAL ?>)
				.checked = true;
		}

		const operator = [...this.#operator].find((radio) => radio.checked).value;
		const is_exists = parseInt(operator, 10) === <?= CONDITION_OPERATOR_EXISTS ?>;

		this.#form_element.querySelector('#js-key-field').classList.toggle(display_none, !is_complex);
		this.#form_element.querySelector('#js-key-label').classList.toggle(display_none, !is_complex);

		this.#form_element.querySelector('#js-value-field').classList.toggle(display_none, is_exists);
		this.#form_element.querySelector('#js-value-label').classList.toggle(display_none, is_exists);
	}

	submit() {
		const fields = this.#form.getAllValues();

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#overlay.unsetLoading();

					return;
				}

				const is_complex = this.#complex_columns.includes(fields.column);
				const operator = parseInt(fields.operator, 10);

				overlayDialogueDestroy(this.#overlay.dialogueid);
				this.#dialogue.dispatchEvent(new CustomEvent('telemetry_condition.submit', {
					detail: {
						row_index: fields.row_index,
						column: fields.column,
						attribute_key: is_complex ? fields.attribute_key : '',
						operator,
						value: operator === <?= CONDITION_OPERATOR_EXISTS ?> ? '' : fields.value
					}
				}));
			});
	}
}
