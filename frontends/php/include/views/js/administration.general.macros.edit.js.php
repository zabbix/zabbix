<script type="text/x-jquery-tmpl" id="macroRow">
	<tr class="form_row">
		<td>
			<input class="input text macro" type="text" id="macros_#{rowNum}_macro" name="macros[#{rowNum}][macro]" style="width: <?= ZBX_TEXTAREA_MACRO_WIDTH ?>px" maxlength="64" placeholder="{$MACRO}">
		</td>
		<td>&rArr;</td>
		<td>
			<input class="input text" type="text" id="macros_#{rowNum}_value" name="macros[#{rowNum}][value]" style="width: <?= ZBX_TEXTAREA_MACRO_VALUE_WIDTH ?>px" maxlength="255" placeholder="<?= _('value') ?>">
		</td>
		<td>
			<button class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove" type="button" id="macros_#{rowNum}_remove" name="macros[#{rowNum}][remove]"><?= _('Remove') ?></button>
		</td>
	</tr>
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

		$('#update').click(function() {
			var removedCount = $(this).data('removedCount');

			if (removedCount) {
				return confirm(<?= CJs::encodeJson(_('Are you sure you want to delete')) ?> + ' ' + removedCount + ' ' + <?= CJs::encodeJson(_('macro(s)')) ?> + '?');
			}
		});

		$('#tbl_macros').dynamicRows({
			template: '#macroRow'
		});
	});
</script>
