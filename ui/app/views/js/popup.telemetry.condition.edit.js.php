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

	init({rules, complex_columns, condition_operators}) {
		this.#overlay = overlays_stack.end();
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);

		this.#complex_columns = complex_columns;
		this.#operators = condition_operators;

		for (const name of ['column', 'operator']) {
			this.#form.findFieldByName(name).getField()
				.addEventListener('change', () => this.#update());
		}

		this.#update();
	}

	#update() {
		const operator_field = this.#form.findFieldByName('operator');
		const operator_element = operator_field.getField();
		const is_complex = this.#complex_columns.includes(this.#form.findFieldByName('column').getValue());
		const allowed_operators = is_complex ? this.#operators.complex : this.#operators.simple;

		for (const radio of operator_element.querySelectorAll('input[type="radio"]')) {
			radio.closest('li').hidden = true;
		}

		for (const operator of allowed_operators) {
			operator_element.querySelector(`input[value="${operator}"]`).closest('li').hidden = false;
		}

		if (operator_element.querySelector('input[type="radio"]:checked').closest('li').hidden) {
			operator_element.querySelector('input[value="<?= CONDITION_OPERATOR_EQUAL ?>"]').checked = true;
		}

		const is_exists = operator_field.getValue() === '<?= CONDITION_OPERATOR_EXISTS ?>';

		this.#form_element.querySelector('#js-key-field').style.display = is_complex ? '' : 'none';
		this.#form_element.querySelector('#js-key-label').style.display = is_complex ? '' : 'none';

		this.#form_element.querySelector('#js-value-field').style.display = is_exists ? 'none' : '';
		this.#form_element.querySelector('#js-value-label').style.display = is_exists ? 'none' : '';

		const value_required = ['<?= CONDITION_OPERATOR_LIKE ?>', '<?= CONDITION_OPERATOR_NOT_LIKE ?>']
			.includes(operator_field.getValue());

		this.#form_element.querySelector('#js-value-label')
			.classList.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', value_required);
		this.#form_element.querySelector('[name="value"]').toggleAttribute('aria-required', value_required);
	}

	submit() {
		const fields = this.#form.getAllValues();

		if (!this.#complex_columns.includes(fields.column)) {
			fields.attribute_key = '';
		}

		if (fields.operator == <?= CONDITION_OPERATOR_EXISTS ?>) {
			fields.value = '';
		}

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#overlay.unsetLoading();

					return;
				}

				overlayDialogueDestroy(this.#overlay.dialogueid);
				this.#dialogue.dispatchEvent(new CustomEvent('telemetry_condition.submit', {
					detail: {
						row_index: fields.row_index,
						column: fields.column,
						attribute_key: fields.attribute_key,
						operator: fields.operator,
						value: fields.value
					}
				}));
			});
	}
};
