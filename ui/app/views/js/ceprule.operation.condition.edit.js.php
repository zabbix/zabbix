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

window.ceprule_operation_condition_edit_popup = new class {

	/** @type {HTMLFormElement} */
	form_element;

	/** @type {CForm} */
	form;

	init({rules, condition, overlay, execute_when, used_property_types}) {
		this.form_element = overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.#setValues(this.#defaultCondition(condition), execute_when);
		this.#setAvailableTypes(execute_when, used_property_types);
		this.#initActions();
		document.getElementById('ceprule-condition-type').dispatchEvent(new Event('change'));

		window.requestAnimationFrame(() => {
			this.form_element.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			Focuser.focus(this.form_element.querySelector('[autofocus]'));
		});
	}

	#initActions() {
		document.getElementById('ceprule-condition-type')
			.addEventListener('change', (e) => this.#handleTypeChanged(e.target.value));
	}

	#setAvailableTypes(execute_when, used_property_types) {
		if (execute_when == <?= CCepRuleHelper::WHEN_EVENT_OCCURRED ?>) {
			used_property_types.push(<?= ZBX_CONDITION_TYPE_EVENT_FIRST ?>);
			used_property_types.push(<?= ZBX_CONDITION_TYPE_EVENT_LAST ?>);
		}

		const zselect = document.getElementById('ceprule-condition-type');

		used_property_types.forEach(value => {
			zselect.getOptionByValue(value).disabled = true;
		});
	}

	#setValues(condition, execute_when) {
		condition.execute_when = execute_when;
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
			[<?=ZBX_CONDITION_TYPE_EVENT_TAG ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>
			],
			[<?= ZBX_CONDITION_TYPE_EVENT_TAG_VALUE ?>]: [
				<?= CONDITION_OPERATOR_EQUAL ?>,
				<?= CONDITION_OPERATOR_NOT_EQUAL ?>,
				<?= CONDITION_OPERATOR_LIKE ?>,
				<?= CONDITION_OPERATOR_NOT_LIKE ?>,
				<?= CONDITION_OPERATOR_MORE_EQUAL ?>,
				<?= CONDITION_OPERATOR_LESS_EQUAL ?>
			],
			[<?= ZBX_CONDITION_TYPE_EVENT_OPEN ?>]: [
				<?= CONDITION_OPERATOR_YES ?>,
				<?= CONDITION_OPERATOR_NO ?>
			],
			[<?= ZBX_CONDITION_TYPE_EVENT_SYMPTOM ?>]: [
				<?= CONDITION_OPERATOR_YES ?>,
				<?= CONDITION_OPERATOR_NO ?>
			],
			[<?= ZBX_CONDITION_TYPE_EVENT_FIRST ?>]: [
				<?= CONDITION_OPERATOR_YES ?>,
				<?= CONDITION_OPERATOR_NO ?>
			],
			[<?= ZBX_CONDITION_TYPE_EVENT_LAST ?>]: [
				<?= CONDITION_OPERATOR_YES ?>,
				<?= CONDITION_OPERATOR_NO ?>
			],
			[<?= ZBX_CONDITION_TYPE_EVENT_SUPPRESSED ?>]: [
				<?= CONDITION_OPERATOR_YES ?>,
				<?= CONDITION_OPERATOR_NO ?>
			],
			[<?= ZBX_CONDITION_TYPE_EVENT_COPIED ?>]: [
				<?= CONDITION_OPERATOR_YES ?>,
				<?= CONDITION_OPERATOR_NO ?>
			]
		};

		const operator_field = document.getElementById('ceprule-condition-operator');

		operator_field.querySelectorAll('input').forEach(node => {
			const is_type_option = condition_type_operators[Number(type)].includes(Number(node.value));

			node.disabled = !is_type_option;
			node.closest('li').style.display = is_type_option ? '' : 'none';
		});

		const radio_inputs = [...operator_field.querySelectorAll('input:not([disabled])')];

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
			.then(result => {
				delete fields['execute_when'];
				return result ? resolve(fields) : reject(fields);
			})
		);
	}

	/**
	 * Full record set with defaults. Type determines the used record properties whom no defaults will be applied.
	 */
	#defaultCondition(condition) {
		const default_condition = {
			type: '<?= ZBX_CONDITION_TYPE_EVENT_TAG_VALUE ?>',
			operator: '<?= CONDITION_OPERATOR_EQUAL ?>',
			tag: '',
			tag_name: '',
			tag_value: ''
		};

		if (condition === null) {
			return default_condition;
		}

		function keep() {
			[...arguments].forEach(field_name => {
				default_condition[field_name] = condition[field_name];
			});
		}

		switch (Number(condition.type)) {
			case <?= CCepRuleHelper::CONDITION_TAG ?>:
				keep('type', 'operator', 'tag');
				break;

			case <?= CCepRuleHelper::CONDITION_TAG_VALUE ?>:
				keep('type', 'operator', 'tag_name', 'tag_value');
				break;

			default:
				keep('type', 'operator');
				break;
		}

		return default_condition;
	}
};
