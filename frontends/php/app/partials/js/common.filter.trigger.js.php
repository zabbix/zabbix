<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CPartial $this
 */
?>

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
			<input type="text" id="inventory_#{rowNum}_value" name="inventory[#{rowNum}][value]" style="width: <?= ZBX_TEXTAREA_FILTER_SMALL_WIDTH ?>px" maxlength="255">
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
