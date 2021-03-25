<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	<?=
		(new CRow([
			(new CSelect('inventory[#{rowNum}][field]'))
				->addOptions(CSelect::createOptionsFromArray($data['inventory_fields'])),
			(new CTextBox('inventory[#{rowNum}][value]', ''))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CCol(
				(new CButton('inventory[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
	?>
</script>
<script type="text/javascript">
	(function($) {
		$(function() {
			$('#inventory-filter').dynamicRows({ template: '#inventory-filter-row' });
		});
	})(jQuery);
</script>
