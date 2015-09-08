<script type="text/x-jquery-tmpl" id="inventory-filter-row">
	<tr class="form_row">
		<td>
			<select id="inventory_#{rowNum}_field" name="inventory[#{rowNum}][field]">
				<?php foreach (getHostInventories() as $field): ?>
					<option value="<?= $field['db_field'] ?>"><?= $field['title'] ?></option>
				<?php endforeach ?>
			</select>
		</td>
		<td>
			<input type="text" id="inventory_#{rowNum}_value" name="inventory[#{rowNum}][value]" style="width: <?= ZBX_TEXTAREA_FILTER_SMALL_WIDTH  ?>px" maxlength="255">
		</td>
		<td class="<?= ZBX_STYLE_NOWRAP ?>">
			<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove" id="inventory_#{rowNum}_remove" name="inventory_#{rowNum}_remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>
<script type="text/javascript">
	(function($) {
		$(function() {
			$('#inventory-filter').dynamicRows({ template: '#inventory-filter-row' });
		});
	})(jQuery);
</script>
