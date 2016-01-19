<?php
$counter = null;
if (hasRequest('conditions')) {
	$conditions = getRequest('conditions');
	krsort($conditions);
	$counter = key($conditions) + 1;
}

include dirname(__FILE__).'/common.item.edit.js.php';
?>
<script type="text/x-jquery-tmpl" id="condition-row">
	<tr class="form_row">
		<td>
			<span class="formulaid">#{formulaId}</span>
			<input type="hidden" name="conditions[#{rowNum}][formulaid]" value="#{formulaId}">
		</td>
		<td>
			<input class="<?= ZBX_STYLE_UPPERCASE ?> macro" type="text" id="conditions_#{rowNum}_macro" name="conditions[#{rowNum}][macro]" style="width: <?= ZBX_TEXTAREA_MACRO_WIDTH ?>px" maxlength="64" placeholder="{#MACRO}" data-formulaid="#{formulaId}">
		</td>
		<td>
			<span><?= _('matches') ?></span>
		</td>
		<td>
			<input type="text" id="conditions_#{rowNum}_value" name="conditions[#{rowNum}][value]" style="width: <?= ZBX_TEXTAREA_MACRO_VALUE_WIDTH ?>px" maxlength="255" placeholder="<?= _('regular expression') ?>">
		</td>
		<td class="<?= ZBX_STYLE_NOWRAP ?>">
			<button class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove" type="button" id="conditions_#{rowNum}_remove" name="conditions_#{rowNum}_remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>
<script type="text/javascript">
	(function($) {
		$(function() {
			function updateExpression() {
				var conditions = [];
				$('#conditions .macro').each(function(index, macroInput) {
					macroInput = $(macroInput);
					macroInput.val(macroInput.val().toUpperCase());
					conditions.push({
						id: macroInput.data('formulaid'),
						type: macroInput.val()
					});
				});

				$('#expression').html(getConditionFormula(conditions, +$('#evaltype').val()));
			}

			$('#conditions')
				.dynamicRows({
					template: '#condition-row',
					counter: <?= CJs::encodeJson($counter) ?>,
					dataCallback: function(data) {
						data.formulaId = num2letter(data.rowNum);

						return data;
					}
				})
				.bind('tableupdate.dynamicRows', function(event, options) {
					$('#conditionRow').toggle($(options.row, $(this)).length > 1);
				})
				.bind('rowremove.dynamicRows', function() {
					updateExpression();
				})
				.on('change', '.macro', function() {
					updateExpression();
				});

			$('#evaltype').change(function() {
				var show_formula = ($(this).val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>);

				$('#expression').toggle(!show_formula);
				$('#formula').toggle(show_formula);
				if (show_formula) {
					updateExpression();
				}
			});

			$('#evaltype').trigger('change');

			updateExpression();
		});
	})(jQuery);
</script>
