<script type="text/x-jquery-tmpl" id="macroRow">
	<tr class="form_row">
		<td>
			<input class="input text macro" type="text" id="macros_#{rowNum}_macro" name="macros[#{rowNum}][macro]" size="30" maxlength="64" placeholder="{$MACRO}">
		</td>
		<td>&rArr;</td>
		<td>
			<input class="input text" type="text" id="macros_#{rowNum}_value" name="macros[#{rowNum}][value]" size="40" maxlength="255" placeholder="value">
		</td>
		<td>
			<button class="button link_menu element-table-remove" type="button" id="macros_#{rowNum}_remove" name="macros[#{rowNum}][remove]"><?php echo _('Remove');?></button>
		</td>
<?php	if ($data['show_inherited_macros']) {
			echo '<td></td><td></td><td></td><td></td>';
		}?>
	</tr>
</script>
<script type="text/javascript">
	jQuery(function($) {
		$('#tbl_macros').dynamicRows({
			template: '#macroRow'
		});

		$('#tbl_macros').on('click', 'button.element-table-change', function() {
			var macroNum = $(this).attr('id').split('_')[1];

			if ($('#macros_' + macroNum + '_type').val() & 0x02/* HOSTMACRO */) {
				var value;

				if ($('#macros_' + macroNum + '_template_value').length) {
					value = $('#macros_' + macroNum + '_template_value').val();
				}
				else {
					value = $('#macros_' + macroNum + '_global_value').val();
				}

				$('#macros_' + macroNum + '_type')
					.val($('#macros_' + macroNum + '_type').val() & (~0x02/* HOSTMACRO */));
				$('#macros_' + macroNum + '_value')
					.attr('readonly', 'readonly')
					.val(value);
				$('#macros_' + macroNum + '_change')
					.text(<?php echo CJs::encodeJson(_('Change')); ?>);
			}
			else {
				$('#macros_' + macroNum + '_type')
					.val($('#macros_' + macroNum + '_type').val() | 0x02/* HOSTMACRO */);
				$('#macros_' + macroNum + '_value')
					.removeAttr('readonly')
					.focus();
				$('#macros_' + macroNum + '_change')
					.text(<?php echo CJs::encodeJson(_('Remove')); ?>);
			}
		});
	});
</script>
