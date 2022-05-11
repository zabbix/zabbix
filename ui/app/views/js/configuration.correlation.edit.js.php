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

	jQuery(document).ready(function() {
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
