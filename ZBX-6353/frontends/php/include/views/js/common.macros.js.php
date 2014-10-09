<script type="text/x-jquery-tmpl" id="macroRow">
	<tr class="form_row">
		<td>
			<input class="input text" type="text" id="macros_#{rowNum}_macro" name="macros[#{rowNum}][macro]" size="30" maxlength="64"
				placeholder="{$MACRO}" style="text-transform:uppercase;">
		</td>
		<td>
			<span style="vertical-align:top;">&rArr;</span>
		</td>
		<td>
			<input class="input text" type="text" id="macros_#{rowNum}_value" name="macros[#{rowNum}][value]" size="40" maxlength="255" placeholder="value">
		</td>
		<td>
			<input class="input link_menu element-table-remove" type="button" id="macros_#{rowNum}_remove"
				name="macros_#{rowNum}_remove" value="<?php echo _('Remove'); ?>">
		</td>
	</tr>
</script>
<script type="text/javascript">
	jQuery(function() {
		jQuery('#tbl_macros').on('click', 'input.element-table-remove', function() {
			var e = jQuery(this);

			// check if the macro has an hidden ID element, if it does - increment the deleted macro counter
			var macroNum = e.attr('id').split('_')[1];
			if (jQuery('#macros_' + macroNum + '_id').length) {
				var count = jQuery('#save').data('removedCount') + 1;
				jQuery('#save').data('removedCount', count);
			}
		});

		jQuery('#save').click(function() {
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
