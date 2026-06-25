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

window.ceprule_window_condition_edit_popup = new class {

	/** @type {HTMLFormElement} */
	form_element;

	/** @type {CForm} */
	form;

	/** @type {Overlay} */
	#overlay;

	/** @type {Object} */
	#templates;

	/** @type {Array} */
	#live_nodes = [];

	/** @type {DocumentFragment} */
	#template;

	init({rules, window_condition, overlay}) {
		this.#overlay = overlay;
		this.form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		this.#setValues(window_condition);
		this.#initActions();
		window['ceprule-window-condition-type'].dispatchEvent(new Event('change'));
	}

	#initActions() {
		window['ceprule-window-condition-type'].addEventListener('change', (e) => this.#handleTypeChanged(e.target.value));
	}

	#setValues(window_condition) {
		[...this.form_element.querySelectorAll('[name]')].map((node) => {
			if (node.type === 'radio') {
				node.checked = node.value === window_condition[node.name];
			}
			else {
				node.value = window_condition[node.name] ?? '';
			}
		});

		this.form_element.querySelector('[name="type"]').value = window_condition.type;
		this.form_element.querySelector('[name="formulaid"]').value = window_condition.formulaid;
	}

	#handleTypeChanged(type) {
		const condition_type_operators = {
			[<?= CCepRuleHelper::WINDOW_CONDITION_TAG_PAIR ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>
			],
			[<?= CCepRuleHelper::WINDOW_CONDITION_OLD_TAG ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>
			],
			[<?= CCepRuleHelper::WINDOW_CONDITION_OLD_TAG_VALUE ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>
			]
		};
		[...window['ceprule-window-condition-operator'].querySelectorAll('input')].map((node) => {
			const is_type_option = condition_type_operators[Number(type)].includes(Number(node.value));

			node.disabled = !is_type_option;
			node.closest('li').style.display = is_type_option ? '' : 'none';
		});

		const radio_inputs = [...window['ceprule-window-condition-operator'].querySelectorAll('input:not([disabled])')];

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
