<?php
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
 * @var CPartial $this
 */
?>

var LldRuleEditLldFiltersTab = class {
	#container;

	constructor({container, conditions}) {
		this.#container = container;

		if (conditions.length == 0) {
			conditions.push({});
		}

		$(this.#container.querySelector('.js-lld-filters'))
			.dynamicRows({
				template: this.#container.querySelector('.js-lld-filters-template'),
				rows: conditions,
				allow_empty: true,
				counter: null,
				dataCallback: (data) => {
					data.formulaid = num2letter(data.rowNum);

					return data;
				}
			})
			.bind('tableupdate.dynamicRows', () => {
				this.#toggleTypeOfCalculation();
				this.#updateExpression();
			})
			.on('change', '.js-macro', () => {
				this.#updateExpression();
			})
			.on('afteradd.dynamicRows', (event) => {
				[...event.currentTarget.querySelectorAll('.js-operator')]
					.pop()
					.addEventListener('change', (e) => this.#toggleConditionValue(e.currentTarget));
				this.#initMacroFields();
			})
			.ready(() => {
				this.#toggleTypeOfCalculation();

				this.#container.querySelectorAll('.js-lld-filters .js-operator').forEach(el => {
					el.addEventListener('change', (e) => this.#toggleConditionValue(e.currentTarget));
					this.#toggleConditionValue(el);
				});
			});

		this.#container.querySelector('.js-evaltype').addEventListener('change', () =>
			this.#updateExpression()
		);

		this.#updateExpression();
		this.#initMacroFields();
	}

	#toggleTypeOfCalculation() {
		const row_count = this.#container.querySelectorAll('.js-lld-filters .form_row').length;

		this.#container.querySelectorAll('.js-item-condition').forEach(el =>
			el.style.display = row_count > 1 ? '' : 'none'
		);
	}

	#toggleConditionValue(target) {
		const value_input = target.closest('.form_row').querySelector('.js-value');
		const show_value = (target.value == <?= CONDITION_OPERATOR_REGEXP ?>
			|| target.value == <?= CONDITION_OPERATOR_NOT_REGEXP ?>);

		value_input.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show_value);

		if (!show_value) {
			value_input.value = '';
		}
	}

	#updateExpression() {
		if (this.#container.querySelector('.js-evaltype').value == <?=CONDITION_EVAL_TYPE_EXPRESSION ?>) {
			this.#container.querySelector('.js-item-condition .js-expression').style.display = 'none';
			this.#container.querySelector('.js-item-condition .js-formula').style.display = '';
		}
		else {
			this.#container.querySelector('.js-item-condition .js-expression').style.display = '';
			this.#container.querySelector('.js-item-condition .js-formula').style.display = 'none';
		}

		const conditions = [];

		this.#container.querySelectorAll('.js-lld-filters .form_row').forEach(row => {
			conditions.push({
				id: row.querySelector('[name$="[formulaid]"').value,
				type: row.querySelector('.js-macro').value
			});
		});

		this.#container.querySelector('.js-expression').innerText = getConditionFormula(conditions,
			parseInt(this.#container.querySelector('.js-evaltype').value)
		);
	}

	#initMacroFields() {
		this.#container.querySelectorAll('.js-macro:not(.initialized-field)').forEach(textarea => {
			const $textarea = $(textarea);

			$textarea.on('change keydown', (e) => {
				if (e.type === 'change' || e.which === 13) {
					$(textarea).val($(textarea).val().toUpperCase());
				}
			});
		});
	}
};
