<script type="text/x-jquery-tmpl" id="inventory-filter-row">
	<tr class="form_row">
		<td>
			<select class="input select" id="inventory_#{rowNum}_field" name="inventory[#{rowNum}][field]">
				<?php foreach (getHostInventories() as $field): ?>
					<option value="<?php echo $field['db_field'] ?>"><?php echo $field['title'] ?></option>
				<?php endforeach ?>
			</select>
		</td>
		<td>
			<input class="input text" type="text" id="inventory_#{rowNum}_value" name="inventory[#{rowNum}][value]"
				   size="20" maxlength="255">
		</td>
		<td>
			<input class="input link_menu element-table-remove" type="button" id="inventory_#{rowNum}_remove"
				   name="inventory_#{rowNum}_remove" value="<?php echo _('Remove'); ?>">
		</td>
	</tr>
</script>
<script type="text/javascript">
	(function($) {
		$(function() {
			$('#inventory-filter').elementTable({ template: '#inventory-filter-row' });
		});
	})(jQuery);
</script>
