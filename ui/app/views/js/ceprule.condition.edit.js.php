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
		this.#setValues(this.#defaultCondition(condition));
		this.#initActions();
		window['ceprule-condition-type'].dispatchEvent(new Event('change'));

		window.requestAnimationFrame(() => {
			this.form_element.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			Focuser.focus(this.form_element.querySelector('[autofocus]'));
		});
	}

	#initActions() {
		window['ceprule-condition-type'].addEventListener('change', (e) => this.#handleTypeChanged(e.target.value));
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
		const condition_type_operators = {
			[<?= CCepRuleHelper::CONDITION_EVENT_NAME ?>]: [
				<?= CONDITION_OPERATOR_NOT_LIKE ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_EQUAL ?>
			],
			[<?= CCepRuleHelper::CONDITION_HOST_VISIBLE_NAME ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>
			],
			[<?= CCepRuleHelper::CONDITION_HOST_GROUP_NAME ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>
			],
			[<?= CCepRuleHelper::CONDITION_TAG ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>
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
		window['ceprule-condition-operator'].querySelectorAll('input').forEach(node => {
			const is_type_option = condition_type_operators[Number(type)].includes(Number(node.value));

			node.disabled = !is_type_option;
			node.closest('li').hidden = !is_type_option;
		});

		const radio_inputs = [...window['ceprule-condition-operator'].querySelectorAll('input:not([disabled])')];

		if (!radio_inputs.filter(node => node.checked).length) {
			radio_inputs[0].checked = true;
		}

		this.form_element.querySelectorAll('[for-type]').forEach(field => {
			const is_visible = Number(type) === Number(field.getAttribute('for-type'));

			field.style.display = is_visible ? '' : 'none';
			field.previousElementSibling.style.display = is_visible ? '' : 'none';
			field.querySelectorAll('input').forEach(node => node.disabled = !is_visible);
		});
	}

	submit() {
		const fields = this.form.getAllValues();

		return new Promise((resolve, reject) => this.form.validateSubmit(fields)
			.then(result => result && resolve(fields) || reject(fields))
		);
	}

	/**
	 * Full record set with defaults. Type determines the used record properties whom no defaults will be applied.
	 */
	#defaultCondition(condition) {
		const default_condition = {
			type: '<?= CCepRuleHelper::CONDITION_EVENT_NAME ?>',
			operator: '<?= CONDITION_OPERATOR_EQUAL ?>',
			host_group_name: '',
			host_name: '',
			severity: '<?= TRIGGER_SEVERITY_NOT_CLASSIFIED ?>',
			tag: '',
			tag_name: '',
			tag_value: '',
			event_name: '',
			time_period: ''
		};

		if (condition === undefined) {
			return default_condition;
		}

		function keep() {
			[...arguments].forEach(field_name => {
				default_condition[field_name] = condition[field_name];
			});
		}

		switch (Number(condition.type)) {
			case <?= CCepRuleHelper::CONDITION_EVENT_NAME ?>:
				keep('type', 'operator', 'event_name', );
			break;
			case <?= CCepRuleHelper::CONDITION_TAG ?>:
				keep('type', 'operator', 'tag');
			break;
			case <?= CCepRuleHelper::CONDITION_TAG_VALUE ?>:
				keep('type', 'operator', 'tag_name','tag_value');
				break;
			case <?= CCepRuleHelper::CONDITION_SEVERITY ?>:
				keep('type', 'operator', 'severity');
			break;
			case <?= CCepRuleHelper::CONDITION_HOST_VISIBLE_NAME ?>:
				keep('type', 'operator', 'host_name');
			break;
			case <?= CCepRuleHelper::CONDITION_HOST_GROUP_NAME ?>:
				keep('type', 'operator', 'host_group_name');
			break;
			case <?= CCepRuleHelper::CONDITION_TIME_PERIOD ?>:
				keep('type', 'operator', 'time_period');
			break;
		}

		return default_condition;
	}
};
