<script type="text/x-jquery-tmpl" id="mapping_row">
	<tr class="form_row">
		<td>
			<input type="text" id="mappings_#{rowNum}_value" name="mappings[#{rowNum}][value]" maxlength="64" style="width: <?= ZBX_TEXTAREA_SMALL_WIDTH ?>px;">
		</td>
		<td>&rArr;</td>
			<td>
			<input type="text" id="mappings_#{rowNum}_newvalue" name="mappings[#{rowNum}][newvalue]" maxlength="64" style="width: <?= ZBX_TEXTAREA_SMALL_WIDTH ?>px;">
		</td>
		<td>
			<button type="button" id="mappings_#{rowNum}_remove" name="mappings[#{rowNum}][remove]" class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#mappings_table').dynamicRows({
			template: '#mapping_row'
		});
	});
</script>
