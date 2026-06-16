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

		window.requestAnimationFrame(() => this.form_element.style.display = '');
	}

	#initActions() {
		window['ceprule-condition-type'].addEventListener('change', (e) => this.#handleTypeChanged(e.target.value));
	}

	#setValues(condition) {
		[...this.form_element.querySelectorAll('[name]')].map((node) => {
			if (node.type === 'radio') {
				node.checked = node.value === condition[node.name];
			}
			else {
				node.value = condition[node.name] ?? '';
			}
		});

		this.form_element.querySelector('[name="type"]').value = condition.type;
		this.form_element.querySelector('[name="formulaid"]').value = condition.formulaid;
	}

	#handleTypeChanged(type) {
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
			[<?= CCepRuleHelper::CONDITION_TAG_NAME ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_EXISTS ?>
			],
			[<?= CCepRuleHelper::CONDITION_TAG_VALUE ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>,
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
		[...window['ceprule-condition-operator'].querySelectorAll('input')].map((node) => {
			const is_type_option = condition_type_operators[Number(type)].includes(Number(node.value));

			node.disabled = !is_type_option;
			node.closest('li').style.display = is_type_option ? '' : 'none';
		});

		const radio_inputs = [...window['ceprule-condition-operator'].querySelectorAll('input:not([disabled])')];

		if (!radio_inputs.filter(node => node.checked).length) {
			radio_inputs[0].checked = true;
		}

		[...this.form_element.querySelectorAll('[for-type]')].map((field) => {
			const is_visible = Number(type) === Number(field.getAttribute('for-type'));

			field.style.display = is_visible ? '' : 'none';
			field.previousElementSibling.style.display = is_visible ? '' : 'none';
			field.querySelector('input').disabled = !is_visible;
		});
	}
};
