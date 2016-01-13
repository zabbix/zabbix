<script type="text/x-jquery-tmpl" id="macroRow">
<?=
	(new CRow([
		(new CTextBox('macros[#{rowNum}][macro]', '', false, 255))
			->addClass('macro')
			->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
			->setAttribute('placeholder', '{$MACRO}'),
		'&rArr;',
		(new CTextBox('macros[#{rowNum}][value]', '', false, 255))
			->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
			->setAttribute('placeholder', _('value')),
		(new CCol(
			(new CButton('macros[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	]))
		->addClass('form_row')
		->toString()
?>
</script>
<script type="text/javascript">
	jQuery(function($) {
		$('#tbl_macros').on('click', 'button.element-table-remove', function() {
			// check if the macro has an hidden ID element, if it does - increment the deleted macro counter
			var macroNum = $(this).attr('id').split('_')[1];
			if ($('#macros_' + macroNum + '_globalmacroid').length) {
				var count = $('#update').data('removedCount') + 1;
				$('#update').data('removedCount', count);
			}
		});

		// Convert macro names to uppercase.
		$('#tbl_macros').on('blur', 'input.macro', function() {
			macroToUpperCase(this);
		});

		$('#update').click(function() {
			var removedCount = $(this).data('removedCount');

			if (removedCount) {
				return confirm(<?= CJs::encodeJson(_('Are you sure you want to delete')) ?> + ' ' + removedCount + ' ' + <?= CJs::encodeJson(_('macro(s)')) ?> + '?');
			}
		});

		$('#tbl_macros').dynamicRows({
			template: '#macroRow'
		});

		$('form[name="macrosForm"]').submit(function() {
			$('input.macro').each(function() {
				macroToUpperCase(this);
			});
		});

		function macroToUpperCase(element) {
			var macro = $(element).val(),
				end = macro.indexOf(':');

			if (end == -1) {
				$(element).val(macro.toUpperCase());
			}
			else {
				var macro_part = macro.substr(0, end),
					context_part = macro.substr(end, macro.length);

				$(element).val(macro_part.toUpperCase() + context_part);
			}
		}
	});
</script>
