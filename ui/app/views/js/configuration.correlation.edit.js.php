<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
		var show_formula = (jQuery('#evaltype').val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>),
			labels = jQuery('#condition_table .label');

		jQuery('#evaltype').closest('li').toggle(labels.length > 1);
		jQuery('#condition_label').toggle(!show_formula);
		jQuery('#formula')
			.toggle(show_formula)
			.prop('disabled', !show_formula);

		if (labels.length > 1) {
			var conditions = [];

			labels.each(function(index, label) {
				label = jQuery(label);

				conditions.push({
					id: label.data('formulaid'),
					type: label.data('type')
				});
			});

			jQuery('#condition_label').html(getConditionFormula(conditions, +jQuery('#evaltype').val()));
		}
	}

	function createRemoveCell() {
		const cell = document.createElement('td');
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.classList.add('btn-link', 'element-table-remove');
		btn.textContent = <?= json_encode(_('Remove')) ?>;
		btn.addEventListener('click', () => btn.closest('tr').remove());

		cell.appendChild(btn);
		return cell;
	}

	function createRow(input) {
		console.log(input);
		this.row = document.createElement('tr');
		this.row.append(createLabelCell(input));
		this.row.append(createNameCell(input));
		this.row.append(createRemoveCell());

		const table = document.getElementById('condition_table');
		this.row_count = table.rows.length -1;

		$('#condition_table tr:last').before(this.row);
		processTypeOfCalculation();
	}

	function createHiddenInput(name, value) {
		const table = document.getElementById('condition_table');
		this.row_count = table.rows.length -1;
		const input = document.createElement('input');
		input.type = 'hidden';
		input.id = `conditions_${this.row_count}_${name}`;
		input.name = `conditions[${this.row_count}][${name}]`;
		input.value = value;

		return input;
	}

	function createLabelCell(input) {
		// todo E.S. : FIX LABEL WHEN DELETE ROW AND ADD A NEW ONE!!
		const cell = document.createElement('td');

		this.label = num2letter(document.getElementById('condition_table').rows.length -2);
		cell.setAttribute('class', 'label');
		cell.setAttribute('data-formulaid', this.label);
		cell.setAttribute('data-type', input.type);
		cell.append(this.label);
		return cell;
	}

	function createNameCell(input) {
		console.log(input);
		const cell = document.createElement('tr');
		const span = document.createElement('td');
		const value = document.createElement('em');
		const value2 = document.createElement('em');

		cell.appendChild(createHiddenInput('formulaid', this.label));
		cell.appendChild(createHiddenInput('type', input.type));
		cell.appendChild(createHiddenInput('operator' ,input.operator));

		if (input.type == <?= ZBX_CORR_CONDITION_EVENT_TAG_PAIR ?>) {
			span.append('Value of old event tag ');

			value2.textContent = input.oldtag;
			span.append(value2)

			span.append(' ' + input.operator_name[input.operator] + ' value of new event tag ');

			value.textContent = input.newtag;
			span.append(value);

			cell.appendChild(createHiddenInput('oldtag',input.oldtag));
			cell.appendChild(createHiddenInput('newtag',input.newtag));
		}
		else if (input.type == <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG ?>) {
			span.append('Old event tag name ');

			span.append(input.operator_name[input.operator] + ' ');

			value.textContent = input.tag;
			span.append(value);
			cell.appendChild(createHiddenInput('tag', input.tag));
		}
		else if (input.type == <?= ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP ?>) {
			span.append('New event host group ');

			span.append(input.operator_name[input.operator] + ' ');

			value.textContent = input.groupids;
			span.append(value);
		}
		else if (input.type == <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG ?>) {
			span.append('New event tag name ');

			span.append(input.operator_name[input.operator] + ' ');

			value.textContent = input.tag;
			span.append(value);
			cell.appendChild(createHiddenInput('tag', input.tag));
		}
		else if (input.type == <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE?>) {
			span.append('Value of old event tag ');

			value2.textContent = input.tag;
			span.append(value2)

			span.append(' ' + input.operator_name[input.operator] + ' ');

			value.textContent = input.value;
			span.append(value);

			cell.appendChild(createHiddenInput('tag',input.tag));
			cell.appendChild(createHiddenInput('value',input.value));
		}
		else if (input.type == <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE?>) {
			span.append('Value of new event tag ');

			value2.textContent = input.tag;
			span.append(value2)

			span.append(' ' + input.operator_name[input.operator] + ' ');

			value.textContent = input.value;
			span.append(value);

			cell.appendChild(createHiddenInput('tag',input.tag));
			cell.appendChild(createHiddenInput('value',input.value));
		}
		else {
			value.textContent = input.tag;

			span.append(input.type + ' ' + input.operator_name[input.operator] + ' ');
			span.append(value);
		}
		cell.append(span);

		return cell;
	}

	jQuery(document).ready(function() {
		document.addEventListener('condition.dialogue.submit', e => {
			createRow(e.detail.inputs);
		})

		// Clone button.
		jQuery('#clone').click(function() {
			jQuery('#correlationid, #delete, #clone').remove();
			jQuery('#update')
				.text(t('Add'))
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
