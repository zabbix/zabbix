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
	</tr>
</script>
<script type="text/javascript">
	jQuery(function() {
		jQuery('#tbl_macros').on('click', 'button.element-table-remove', function() {
			var e = jQuery(this);

			// check if the macro has an hidden ID element, if it does - increment the deleted macro counter
			var macroNum = e.attr('id').split('_')[1];
			if (jQuery('#macros_' + macroNum + '_globalmacroid').length) {
				var count = jQuery('#update').data('removedCount') + 1;
				jQuery('#update').data('removedCount', count);
			}
		});

		jQuery('#update').click(function() {
			var removedCount = jQuery(this).data('removedCount');

			if (removedCount) {
				return confirm(<?php echo CJs::encodeJson(_('Are you sure you want to delete')); ?> + ' ' + removedCount + ' ' + <?php echo CJs::encodeJson(_('macro(s)')); ?>+'?');
			}
		});

		jQuery('#tbl_macros').dynamicRows({
			template: '#macroRow'
		});
	});
</script>
