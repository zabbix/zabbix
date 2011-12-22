<script type="text/x-jquery-tmpl" id="macroRow">
	<tr class="form_row">
		<td>
			<input class="input text" type="text" name="macros[#{macroNum}][macro]" size="30" maxlength="64"
				placeholder="{$MACRO}" style="text-transform:uppercase;">
		</td>
		<td>
			<span style="vertical-align:top;">⇒</span>
		</td>
		<td>
			<input class="input text" type="text" placeholder="value" name="macros[#{macroNum}][value]" size="40" maxlength="255">
		</td>
		<td>
			<input class="input link_menu macroRemove" type="button" value="<?php echo _('Remove'); ?>" data-is-new="1">
		</td>
	</tr>

</script>

<script type="text/javascript">
	jQuery(document).ready(function() {
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

			if (!jQuery(this).data('isNew')) {
				var count = jQuery('#save').data('removedCount') + 1;
				jQuery('#save').data('removedCount', count);
			}
			jQuery(this).closest('.form_row').remove();
		});

		jQuery('#save').click(function() {
			var removedCount = jQuery(this).data('removedCount');

			if (removedCount) {
				return confirm('<?php echo _('Are you sure you want to delete'); ?> '+removedCount+' <?php echo _('macro(s)') ?>?');
			}
		});
	});
</script>
