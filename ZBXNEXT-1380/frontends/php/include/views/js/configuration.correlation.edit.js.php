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
		jQuery('#formula').toggle(show_formula);

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
				.text(<?= CJs::encodeJson(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});

			// Remove operations IDs.
			var operationid_RegExp = /operations\[\d+\]\[operationid\]/;
			jQuery('input[name^=operations]').each(function() {
				// Intentional usage of JS Prototype.
				if ($(this).getAttribute('name').match(operationid_RegExp)) {
					$(this).remove();
				}
			});

			jQuery('#form').val('clone');
			jQuery('#name').focus();
		});

		processTypeOfCalculation();
	});
</script>
