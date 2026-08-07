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


/**
 * @var CView $this
 */
?>

window.ceprule_condition_edit_popup = new class {

	/** @type {HTMLFormElement} */
	form_element;

	/** @type {CForm} */
	form;

	init({rules, condition, overlay}) {
		this.form_element = overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		this.#setValues(condition);
		this.#initActions();
		window['ceprule-condition-type'].dispatchEvent(new Event('change'));
		window['ceprule-condition-tag-operator'].dispatchEvent(new Event('change'));

		window.requestAnimationFrame(() => {
			this.form_element.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			Focuser.focus(this.form_element.querySelector('[autofocus]'));
		});
	}

	#initActions() {
		window['ceprule-condition-type'].addEventListener('change', (e) => this.#handleTypeChanged(e.target.value));
		window['ceprule-condition-tag-operator'].addEventListener('change', (e) => {
			const value = Number(e.target.value);

			if (value == <?= CONDITION_OPERATOR_EXISTS ?> || value == <?= CONDITION_OPERATOR_NOT_EXISTS ?>) {
				window['ceprule-condition-tag-value'].style.display = 'none';
			}
			else {
				window['ceprule-condition-tag-value'].style.display = '';
			}
		});
	}

	#setValues(condition) {
		this.form_element.querySelectorAll('[name]').forEach(node => {
			if (node.type === 'radio') {
				node.checked = node.value === condition[node.name];
			}
			else {
				node.value = condition[node.name] ?? '';
			}
		});
	}

	#handleTypeChanged(type) {
		const operator_field = window['ceprule-condition-operator'].closest('.form-field');
		const operator_field_label = operator_field.previousElementSibling;
		const tag_operator_field = window['ceprule-condition-tag-operator'].closest('.form-field');
		const tag_operator_field_label = tag_operator_field.previousElementSibling;

		if (Number(type) == <?= CCepRuleHelper::CONDITION_TAG ?>) {
			operator_field.style.display = 'none';
			operator_field_label.style.display = 'none';
			tag_operator_field.style.display = '';
			tag_operator_field_label.style.display = '';
		}
		else {
			operator_field.style.display = '';
			operator_field_label.style.display = '';
			tag_operator_field.style.display = 'none';
			tag_operator_field_label.style.display = 'none';
		}

		const condition_type_operators = {
			[<?= CCepRuleHelper::CONDITION_EVENT_NAME ?>]: [
				<?= CONDITION_OPERATOR_NOT_LIKE ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_EQUAL ?>
			],
			[<?= CCepRuleHelper::CONDITION_HOST ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>
			],
			[<?= CCepRuleHelper::CONDITION_HOST_GROUP ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>
			],
			[<?= CCepRuleHelper::CONDITION_HOST ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>
			],
			[<?= CCepRuleHelper::CONDITION_TAG ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>,
				<?= CONDITION_OPERATOR_EXISTS ?>,
				<?= CONDITION_OPERATOR_NOT_EXISTS ?>,
				<?= CONDITION_OPERATOR_MORE_EQUAL ?>,
				<?= CONDITION_OPERATOR_LESS_EQUAL ?>
			],
			[<?= CCepRuleHelper::CONDITION_SEVERITY ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_MORE_EQUAL ?>,
				<?= CONDITION_OPERATOR_LESS_EQUAL ?>
			],
			[<?= CCepRuleHelper::CONDITION_TIME_PERIOD ?>]: [
				<?= CONDITION_OPERATOR_IN ?>,
				<?= CONDITION_OPERATOR_NOT_IN ?>
			]
		};
		window['ceprule-condition-operator'].querySelectorAll('input').forEach(node => {
			const is_type_option = condition_type_operators[Number(type)].includes(Number(node.value));

			node.disabled = !is_type_option;
			node.closest('li').style.display = is_type_option ? '' : 'none';
		});

		const radio_inputs = [...window['ceprule-condition-operator'].querySelectorAll('input:not([disabled])')];

		if (!radio_inputs.filter(node => node.checked).length) {
			radio_inputs[0].checked = true;
		}

		this.form_element.querySelectorAll('[for-type]').forEach(field => {
			const is_visible = Number(type) === Number(field.getAttribute('for-type'));

			field.style.display = is_visible ? '' : 'none';
			field.previousElementSibling.style.display = is_visible ? '' : 'none';
			field.querySelector('input').disabled = !is_visible;
		});
	}

	submit() {
		const fields = this.form.getAllValues();

		return new Promise((resolve, reject) => this.form.validateSubmit(fields)
			.then(result => result && resolve(fields) || reject(fields))
		);
	}
};
