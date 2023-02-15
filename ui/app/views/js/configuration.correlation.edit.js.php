<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<script type="text/javascript">
	document.addEventListener('click', (e) => {
		if (e.target.classList.contains('js-remove')) {
			e.target.closest('tr').remove();
			this.processTypeOfCalculation();
		}
	});

	function removeCondition(index) {
		var row = jQuery('#conditions_' + index);

		row.find('*').remove();
		row.remove();

		processTypeOfCalculation();
	}

	function removeOperation(index) {
		var row = jQuery('#operations_' + index);

		row.find('*').remove();
		row.remove();
	}

	function processTypeOfCalculation() {
		const show_formula = document.querySelector('#evaltype').value == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;
		const labels = document.querySelectorAll('#condition_table .label');

		jQuery('#evaltype').closest('li').toggle(labels.length > 1);
		document.querySelector('#expression').style.display = show_formula ? 'none' : '';
		document.querySelector('#evaltype').style.display = labels.length > 1 ? '' : 'none';
		jQuery('#condition_label').toggle(!show_formula);
		document.querySelector('#formula').style.display = show_formula ? '' : 'none';
		jQuery('#formula')
			.toggle(show_formula)
			.prop('disabled', !show_formula);

		const conditions = [];

		[...labels].forEach(function (label) {
			conditions.push({
				id: label.dataset.formulaid,
				type: label.dataset.conditiontype
			});
		});

		document.getElementById('expression')
			.innerHTML = getConditionFormula(conditions, + document.querySelector('#evaltype').value);

		document.querySelector('#evaltype').onchange = function() {
			this.show_formula = document.querySelector('#evaltype').value == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;
			document.querySelector('#expression').style.display = this.show_formula ? 'none' : '';
			document.querySelector('#formula').style.display = this.show_formula ? '' : 'none';
			document.querySelector('#formula').removeAttribute('readonly');

			const labels = document.querySelectorAll('#condition_table .label');
			const conditions = [];

			[...labels].forEach(function (label) {
				conditions.push({
					id: label.dataset.formulaid,
					type: label.dataset.conditiontype
				});
			});

			document.getElementById('expression')
				.innerHTML = getConditionFormula(conditions, + document.querySelector('#evaltype').value);
		};
	}

	function createRow(input) {
		const row_ids = [];

		$('#condition_table tr').each(function() {
			row_ids.push(this.id);
		});

		if (input.groupids) {
			Object.keys(input.groupids).map(key => {
				let element = {...input, name: input.groupids[key], value: key};
				element.groupid = key;
				let has_row = this.checkConditionRow(element);

				const result = [has_row.some(element => element === true)];
				if (result[0] === true) {
					return;
				}
				else {
					while (row_ids.some(id => id === `conditions_${input.row_index}`)) {
						element.row_index++;
						input.row_index++;
					}

					element.condition_name = getConditionName(input);
					element.data = element.name;
					element.conditiontype = input.type;
					element.label = num2letter(element.row_index);
					element.groupid = key;
					input.row_index++;
					const template = new Template(document.getElementById('condition-hostgr-row-tmpl').innerHTML);

					document
						.querySelector('#condition_table tbody')
						.insertAdjacentHTML('beforeend', template.evaluate(element));
				}
				this.processTypeOfCalculation();
			});
		}
		else {
			let has_row = this.checkConditionRow(input);
			let template;
			const result = [has_row.some(element => element === true)];

			if (result[0] === true) {
				return;
			}
			else {
				while (row_ids.some(id => id === `conditions_${input.row_index}`)) {
					input.row_index++;
				}

				input.label = num2letter(input.row_index);

				switch (parseInt(input.type)) {
					case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG?>:
					case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG?>:
						template = new Template(document.getElementById('condition-tag-row-tmpl').innerHTML);
						break;

					case <?= ZBX_CORR_CONDITION_EVENT_TAG_PAIR ?> :
						input.condition_name2 = getConditionName(input)[1];
						input.condition_operator = getConditionName(input)[2];
						input.data_old_tag = getConditionName(input)[3];
						input.data_new_tag = getConditionName(input)[4];
						template = new Template(document.getElementById('condition-tag-pair-row-tmpl').innerHTML);
						break;

					case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE ?>:
					case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE?> :
						input.condition_name = getConditionName(input)[0];
						input.condition_operator = getConditionName(input)[1];
						input.tag = getConditionName(input)[2];
						input.value = getConditionName(input)[3];
						template = new Template(document.getElementById('condition-old-new-tag-row-tmpl').innerHTML);
						break;
				}
				input.condition_name = getConditionName(input)[0];
				input.data = getConditionName(input)[1];
				input.conditiontype = input.type;

				document
					.querySelector('#condition_table tbody')
					.insertAdjacentHTML('beforeend', template.evaluate(input));

				input.row_index++;
				processTypeOfCalculation();
			}
		}
	}

	function checkConditionRow(input) {
		const result = [];

		[...document.getElementById('condition_table').getElementsByTagName('tr')].map(element => {
			const table_row = element.getElementsByTagName('td')[2];

			if (table_row !== undefined) {
				const type = table_row.getElementsByTagName('input')[0].value;
				let value;
				let value2;

				switch (parseInt(type)) {
					case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG?>:
					case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG?>:
						value = table_row.getElementsByTagName('input')[2].value;
						result.push(input.type === type && input.tag === value);
						break;

					case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE ?>:
					case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE?> :
						value = table_row.getElementsByTagName('input')[2].value;
						value2 = table_row.getElementsByTagName('input')[3].value;
						result.push(input.type === type && input.tag === value && input.value === value2);
						break;

					case <?= ZBX_CORR_CONDITION_EVENT_TAG_PAIR ?> :
						value = table_row.getElementsByTagName('input')[2].value;
						value2 = table_row.getElementsByTagName('input')[3].value;
						result.push(input.type === type && input.oldtag === value && input.newtag === value2);
						break;

					case <?= ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP ?> :
						value = table_row.getElementsByTagName('input')[2].value;
						result.push(input.type === type && input.groupid === value);
						break;
				}

				if (input.row_index == element.dataset.row_index) {
					input.row_index++;
				}
			}

			result.push(false);
		});

		return result;
	}

	function getConditionName(input) {
		let condition_name;
		let condition_name2;
		let condition_data;
		let operator;
		let value;
		let value2;
		switch (parseInt(input.type)) {
			case <?= ZBX_CORR_CONDITION_EVENT_TAG_PAIR ?>:
				condition_name = <?= json_encode(_('Value of old event tag')) ?>;
				condition_name2 = <?= json_encode(_('value of new event tag')) ?>;
				operator = input.operator_name[input.operator];
				value = input.oldtag;
				value2 = input.newtag;
				return [condition_name, condition_name2, operator, value, value2];

			case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG ?>:
				condition_name = <?= json_encode(_('Old event tag name')) ?> + ' '
					+ input.operator_name[input.operator];
				condition_data = input.tag;
				return [condition_name, condition_data];

			case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG ?>:
				condition_name = <?= json_encode(_('New event tag name')) ?> + ' '
					+ input.operator_name[input.operator];
				condition_data = input.tag;
				return [condition_name, condition_data];

			case <?= ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP ?>:
				condition_name = <?= json_encode(_('New event host group')) ?> + ' '
					+ input.operator_name[input.operator];
				return [condition_name];

			case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE ?>:
				condition_name = <?= json_encode(_('Value of old event tag')) ?>;
				value = input.tag;
				operator = input.operator_name[input.operator];
				value2 = input.value;
				return [condition_name, operator, value, value2];

			case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE?>:
				condition_name = <?= json_encode(_('Value of new event tag')) ?>;
				value = input.tag;
				operator = input.operator_name[input.operator];
				value2 = input.value;
				return [condition_name, operator, value, value2];
		}
	}

	jQuery(document).ready(function() {
		document.addEventListener('condition.dialogue.submit', e => {
			createRow(e.detail.inputs);
		});

		// Clone button.
		jQuery('#clone').click(function() {
			jQuery('#correlationid, #delete, #clone').remove();
			jQuery('#update')
				.text(<?= json_encode(_s('Add')) ?>)
				.val('correlation.create')
				.attr({id: 'add'});

			// Remove operations IDs.
			var operationid_RegExp = /operations\[\d+\]\[operationid\]/;
			jQuery('input[name^=operations]').each(function() {
				if ($(this).attr('name').match(operationid_RegExp)) {
					$(this).remove();
				}
			});

			jQuery('#form').val('clone');
			jQuery('#name').focus();
		});

		$('#evaltype').on('change', () => {
			processTypeOfCalculation();
		});

		processTypeOfCalculation();

		const $form = $(document.forms['correlation.edit']);
		$form.on('submit', () => $form.trimValues(['#name', '#description']));
	});
</script>
