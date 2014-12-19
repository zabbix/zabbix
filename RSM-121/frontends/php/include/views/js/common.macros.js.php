<script type="text/x-jquery-tmpl" id="macroRow">
	<tr class="form_row">
		<td>
			<input class="input text" type="text" id="macros_#{macroNum}_macro" name="macros[#{macroNum}][macro]" size="30" maxlength="64"
				placeholder="{$MACRO}" style="text-transform:uppercase;">
		</td>
		<td>
			<span style="vertical-align:top;"><?php echo RARR ?></span>
		</td>
		<td>
			<input class="input text" type="text" id="macros_#{macroNum}_value" name="macros[#{macroNum}][value]" size="40" maxlength="65535" placeholder="value">
		</td>
		<td>
			<input class="input link_menu macroRemove" type="button" id="macros_#{macroNum}_remove" name="macros_#{macroNum}_remove" value="<?php echo CHtml::encode(_('Remove')); ?>">
		</td>
	</tr>

</script>

<script type="text/javascript">
	jQuery(function() {
		'use strict';

		var rowTemplate = new Template(jQuery('#macroRow').html());

		function addMacroRow() {
			if (addMacroRow.macro_count === void(0)) {
				addMacroRow.macro_count = <?php echo count($this->data['macros']); ?>;
			}

			jQuery('#row_new_macro').before(rowTemplate.evaluate({macroNum: addMacroRow.macro_count}));
			addMacroRow.macro_count++;
		}

		jQuery('#macro_add').click(addMacroRow);

		jQuery('#tbl_macros').on('click', 'input.macroRemove', function() {
			var e = jQuery(this);

			// check if the macro has an hidden ID element, if it does - increment the deleted macro counter
			var macroNum = e.attr('id').split('_')[1];
			if (jQuery('#macros_' + macroNum + '_id').length) {
				var count = jQuery('#save').data('removedCount') + 1;
				jQuery('#save').data('removedCount', count);
			}
			e.closest('.form_row').remove();
		});

		jQuery('#save').click(function() {
			var removedCount = jQuery(this).data('removedCount');

			if (removedCount) {
				return confirm(<?php echo CJs::encodeJson(_('Are you sure you want to delete')); ?> + ' ' + removedCount + ' ' + <?php echo CJs::encodeJson(_('macro(s)')); ?>+'?');
			}
		});
	});
</script>
