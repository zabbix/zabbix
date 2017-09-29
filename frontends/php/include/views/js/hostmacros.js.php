<script type="text/x-jquery-tmpl" id="macroRow">
	<tr class="form_row">
		<td>
			<input class="macro" type="text" id="macros_#{rowNum}_macro" name="macros[#{rowNum}][macro]" style="width: <?= ZBX_TEXTAREA_MACRO_WIDTH ?>px" maxlength="255" placeholder="{$MACRO}">
		<?php if ($data['show_inherited_macros']): ?>
			<input id="macros_#{rowNum}_type" type="hidden" value="2" name="macros[#{rowNum}][type]">
		<?php endif ?>
		</td>
		<td>&rArr;</td>
		<td>
			<input type="text" id="macros_#{rowNum}_value" name="macros[#{rowNum}][value]" style="width: <?= ZBX_TEXTAREA_MACRO_VALUE_WIDTH ?>px" maxlength="255" placeholder="<?= _('value') ?>">
		</td>
		<td class="<?= ZBX_STYLE_NOWRAP ?>">
			<button class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove" type="button" id="macros_#{rowNum}_remove" name="macros[#{rowNum}][remove]"><?= _('Remove') ?></button>
		</td>
		<?php if ($data['show_inherited_macros']): ?>
			<td></td><td><div class="<?= ZBX_STYLE_OVERFLOW_ELLIPSIS ?>" style="width: <?= ZBX_TEXTAREA_MACRO_VALUE_WIDTH ?>px;"></div></td><td></td><td><div class="<?= ZBX_STYLE_OVERFLOW_ELLIPSIS ?>" style="width: <?= ZBX_TEXTAREA_MACRO_VALUE_WIDTH ?>px;"></div></td>
		<?php endif ?>
	</tr>
</script>
<script type="text/javascript">
	jQuery(function($) {
		$('#tbl_macros').dynamicRows({
			template: '#macroRow'
		});

		$('#tbl_macros').on('click', 'button.element-table-change', function() {
			var macroNum = $(this).attr('id').split('_')[1];

			if ($('#macros_' + macroNum + '_type').val() & <?= MACRO_TYPE_HOSTMACRO ?>) {
				$('#macros_' + macroNum + '_type')
					.val($('#macros_' + macroNum + '_type').val() & (~<?= MACRO_TYPE_HOSTMACRO ?>));
				$('#macros_' + macroNum + '_value')
					.attr('readonly', 'readonly')
					.val($('#macros_' + macroNum + '_inherited_value').val());
				$('#macros_' + macroNum + '_change')
					.text(<?= CJs::encodeJson(_x('Change', 'verb')) ?>);
			}
			else {
				$('#macros_' + macroNum + '_type')
					.val($('#macros_' + macroNum + '_type').val() | <?= MACRO_TYPE_HOSTMACRO ?>);
				$('#macros_' + macroNum + '_value')
					.removeAttr('readonly')
					.focus();
				$('#macros_' + macroNum + '_change')
					.text(<?= CJs::encodeJson(_('Remove')) ?>);
			}
		});

		// Convert macro names to uppercase.
		$('#tbl_macros').on('blur', 'input.macro', function() {
			macroToUpperCase(this);
		});

		$('form[name="hostsForm"], form[name="templatesForm"]').submit(function() {
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
