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
	<?=
		(new CRow([[
				new CSpan('#{formulaId}'),
				new CVar('conditions[#{rowNum}][formulaid]', '#{formulaId}')
			],
			(new CTextBox('conditions[#{rowNum}][macro]', '', false, 64))
				->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
				->addClass(ZBX_STYLE_UPPERCASE)
				->addClass('macro')
				->setAttribute('placeholder', '{#MACRO}')
				->setAttribute('data-formulaid', '#{formulaId}'),
			(new CComboBox('conditions[#{rowNum}][operator]', CONDITION_OPERATOR_REGEXP, null, [
				CONDITION_OPERATOR_REGEXP => _('matches'),
				CONDITION_OPERATOR_NOT_REGEXP => _('does not match')
			]))->addClass('operator'),
			(new CTextBox('conditions[#{rowNum}][value]', '', false, 255))
				->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
				->setAttribute('placeholder', _('regular expression')),
			(new CCol(
				(new CButton('conditions_#{rowNum}_remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString()
	?>
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

					if (!($('#evaltype').val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>)) {
						updateExpression();
					}
				})
				.on('change', '.macro', function() {
					if (!($('#evaltype').val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>)) {
						updateExpression();
					}
				})
				.ready(function() {
					$('#conditionRow').toggle($('.form_row', $('#conditions')).size() > 1);
				});

			$('#evaltype').change(function() {
				var show_formula = ($(this).val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>);

				$('#expression').toggle(!show_formula);
				$('#formula').toggle(show_formula);
				if (!show_formula) {
					updateExpression();
				}
			});

			$('#evaltype').trigger('change');

			$('#type').change(function() {
				var type = parseInt($('#type').val()),
					asterisk = '<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>';

				if (type == <?= ITEM_TYPE_SSH ?> || type == <?= ITEM_TYPE_TELNET ?>) {
					$('label[for=username]').addClass(asterisk);
					$('input[name=username]').attr('aria-required', 'true');
				}
				else {
					$('label[for=username]').removeClass(asterisk);
					$('input[name=username]').removeAttr('aria-required');
				}
			}).trigger('change');
		});
	})(jQuery);
</script>
