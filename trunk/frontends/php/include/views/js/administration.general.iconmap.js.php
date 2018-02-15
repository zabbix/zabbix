<script type="text/x-jquery-tmpl" id="iconMapRowTPL">
<?=
	(new CRow([
		(new CCol(
			(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
		))->addClass(ZBX_STYLE_TD_DRAG_ICON),
		(new CSpan('#0:'))->addClass('rowNum'),
		(new CComboBox('iconmap[mappings][#{iconmappingid}][inventory_link]', null, null, $data['inventoryList']))
			->setId('iconmap_mappings_#{iconmappingid}_inventory_link')
			->setAttribute('autocomplete', 'off'),
		(new CTextBox('iconmap[mappings][#{iconmappingid}][expression]', '', false, 64))
			->setId('iconmap_mappings_#{iconmappingid}_expression')
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		(new CComboBox('iconmap[mappings][#{iconmappingid}][iconid]', null, null, $data['iconList']))
			->setId('iconmap_mappings_#{iconmappingid}_iconid')
			->addClass('mappingIcon')
			->setAttribute('autocomplete', 'off'),
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
		))->addClass(ZBX_STYLE_NOWRAP)
	]))
		->setId('iconmapidRow_#{iconmappingid}')
		->addClass('sortable')
?>
</script>
<script type="text/javascript">
	jQuery(function($) {
		var iconMapTable = $('#iconMapTable'),
			addMappingButton = $('#addMapping');

		function recalculateSortOrder() {
			var i = 1;

			iconMapTable.find('tr.sortable .rowNum').each(function() {
				$(this).text(i++ + ':');
			});
		}

		iconMapTable.sortable({
			disabled: (iconMapTable.find('tr.sortable').length < 2),
			items: 'tbody tr.sortable',
			axis: 'y',
			cursor: 'move',
			containment: 'parent',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			update: recalculateSortOrder,
			helper: function(e, ui) {
				ui.children().each(function() {
					var td = $(this);

					td.width(td.width());
				});

				// when dragging element on safari, it jumps out of the table
				if (SF) {
					// move back draggable element to proper position
					ui.css('left', (ui.offset().left - 4) + 'px');
				}

				return ui;
			},
			start: function(e, ui) {
				$(ui.placeholder).height($(ui.helper).height());
			}
		});

		iconMapTable.find('tbody')
			.on('click', '.remove_mapping', function() {
				$(this).parent().parent().remove();

				if (iconMapTable.find('tr.sortable').length < 2) {
					iconMapTable.sortable('disable');
				}
				recalculateSortOrder();
			})
			.on('change', 'select.mappingIcon, select#iconmap_default_iconid', function() {
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

			iconMapTable.sortable('refresh');

			if (iconMapTable.find('tr.sortable').length > 1) {
				iconMapTable.sortable('enable');
			}

			recalculateSortOrder();
		});

		if (iconMapTable.find('tr.sortable').length === 0) {
			addMappingButton.click();
		}
	});
</script>
