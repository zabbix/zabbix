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
			<button type="button" class="button link_menu element-table-remove" id="macros_#{rowNum}_remove" name="macros_#{rowNum}_remove">
				<?php echo _('Remove'); ?>
			</button>
		</td>
	</tr>
</script>
<script type="text/javascript">
	jQuery(function() {
		jQuery('#tbl_macros').dynamicRows({
			template: '#macroRow'
		});
	});
</script>
