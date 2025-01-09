<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */
?>

<script type="text/x-jquery-tmpl" id="iconMapRowTPL">
<?=
	(new CRow([
		(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
		(new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM),
		(new CSelect('iconmap[mappings][#{iconmappingid}][inventory_link]'))
			->addOptions(CSelect::createOptionsFromArray($data['inventory_list']))
			->setId('iconmap_mappings_#{iconmappingid}_inventory_link'),
		(new CTextBox('iconmap[mappings][#{iconmappingid}][expression]', '', false, 64))
			->setId('iconmap_mappings_#{iconmappingid}_expression')
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		(new CSelect('iconmap[mappings][#{iconmappingid}][iconid]'))
			->addOptions(CSelect::createOptionsFromArray($data['icon_list']))
			->setId('iconmap_mappings_#{iconmappingid}_iconid')
			->addClass('js-mapping-icon'),
		(new CCol(
			(new CImg('imgstore.php?iconid='.$data['default_imageid'].'&width='.ZBX_ICON_PREVIEW_WIDTH.
				'&height='.ZBX_ICON_PREVIEW_HEIGHT, _('Preview'))
			)
				->setAttribute('data-image-full', 'imgstore.php?iconid='.$data['default_imageid'])
				->addClass(ZBX_STYLE_CURSOR_POINTER)
				->addClass('preview')
		))->addStyle('vertical-align: middle'),
		(new CCol(
			(new CButton('remove', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('remove_mapping')
				->removeId()
		))->addClass(ZBX_STYLE_NOWRAP)
	]))->setId('iconmapidRow_#{iconmappingid}')
?>
</script>
<script type="text/javascript">
	jQuery(function($) {
		var $form = $('form#iconmap');

		$form.on('submit', function() {
			$form.trimValues(['#iconmap_name']);
		});

		$form.find('#clone').click(function() {
			var url = new Curl('zabbix.php?action=iconmap.edit');

			$form.serializeArray().forEach(function(field) {
				if (field.name !== 'iconmapid') {
					url.setArgument(field.name, field.value);
				}
			});

			redirect(url.getUrl(), 'post', 'action', undefined, true);
		});

		var iconMapTable = $('#iconMapTable'),
			addMappingButton = $('#addMapping');

		new CSortable(iconMapTable[0].querySelector('tbody'), {
			selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			freeze_end: 2
		});

		iconMapTable.find('tbody')
			.on('click', '.remove_mapping', function() {
				$(this).parent().parent().remove();
			})
			.on('change', 'z-select.js-mapping-icon, z-select#iconmap_default_iconid', function() {
				$(this).closest('tr').find('.preview')
					.attr('src', 'imgstore.php?&width=<?= ZBX_ICON_PREVIEW_WIDTH ?>&height=<?= ZBX_ICON_PREVIEW_HEIGHT ?>&iconid=' + $(this).val())
					.data('imageFull', 'imgstore.php?iconid=' + $(this).val());
			})
			.on('click', 'img.preview', function(e) {
				var img = $('<img>', {src: $(this).data('imageFull')});
				hintBox.showStaticHint(e, this, '', true, '', img);
			});

		addMappingButton.click(function() {
			var tpl = new Template($('#iconMapRowTPL').html()),
				iconmappingid = getUniqueId(),
				mapping = {};

			// on error, whole page reloads and getUniqueId reset ids sequence which can cause in duplicate ids
			while ($('#iconmapidRow_' + iconmappingid).length != 0) {
				iconmappingid = getUniqueId();
			}

			mapping.iconmappingid = iconmappingid;
			$('#iconMapListFooter').before(tpl.evaluate(mapping));
		});

		if (iconMapTable.find('tr[id^="iconmapidRow_"]').length === 0) {
			addMappingButton.click();
		}
	});
</script>
