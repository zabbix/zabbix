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
 * @var CView $this
 */
?>

<script type="text/x-jquery-tmpl" id="mapping_row">
	<?= (new CRow([
			(new CTextBox('mappings[#{rowNum}][value]', '', false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			'&rArr;',
			(new CTextBox('mappings[#{rowNum}][newvalue]', '', false, 64))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired(),
			(new CButton('mappings[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))
			->addClass('form_row')
			->toString()
	?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		var $form = $('form#valuemap');

		$form.on('submit', function() {
			$form.trimValues(['#name']);
		});

		$('#mappings_table').dynamicRows({
			template: '#mapping_row'
		});

		$form.find('#clone').click(function() {
			var url = new Curl('zabbix.php?action=valuemap.edit');

			$form.serializeArray().forEach(function(field) {
				if (field.name !== 'valuemapid') {
					url.setArgument(field.name, field.value);
				}
			});

			redirect(url.getUrl(), 'post', 'action', undefined, false, true);
		});
	});
</script>
