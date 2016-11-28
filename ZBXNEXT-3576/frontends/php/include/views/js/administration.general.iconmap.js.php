<script type="text/x-jquery-tmpl" id="iconMapRowTPL">
<tr class="sortable" id="iconmapidRow_#{iconmappingid}">
	<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
		<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
	</td>
	<td>
		<span class="rowNum">#0:</span>
	</td>
	<td>
		<select id="iconmap_mappings_#{iconmappingid}_inventory_link" name="iconmap[mappings][#{iconmappingid}][inventory_link]" autocomplete="off">
			<?php foreach ($this->data['inventoryList'] as $key => $value): ?>
				<option value="<?= $key ?>"><?= $value ?></option>
			<?php endforeach ?>
		</select>
	</td>
	<td>
		<input id="iconmap_mappings_#{iconmappingid}_expression" name="iconmap[mappings][#{iconmappingid}][expression]" value="" style="width: <?= ZBX_TEXTAREA_SMALL_WIDTH ?>px" maxlength="64" type="text" />
	</td>
	<td>
		<select class="mappingIcon" id="iconmap_mappings_#{iconmappingid}_iconid" name="iconmap[mappings][#{iconmappingid}][iconid]" autocomplete="off">
			<?php foreach ($this->data['iconList'] as $key => $value): ?>
				<option value="<?= $key ?>"><?= $value ?></option>
			<?php endforeach ?>
		</select>
	</td>
	<td style="vertical-align: middle;">
		<?php reset($this->data['iconList']) ?>
		<?php $iconid = key($this->data['iconList']) ?>
		<img class="pointer preview" name="Preview" alt="Preview" src="imgstore.php?iconid=<?= $iconid ?>&width=24&height=24" data-image-full="imgstore.php?iconid=<?= $iconid ?>" border="0">
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button class="<?= ZBX_STYLE_BTN_LINK ?> removeMapping" type="button" id="remove" name="remove">Remove</button>
	</td>
</tr>
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
			.delegate('.removeMapping', 'click', function() {
				$(this).parent().parent().remove();

				if (iconMapTable.find('tr.sortable').length < 2) {
					iconMapTable.sortable('disable');
				}
				recalculateSortOrder();
			})
			.delegate('select.mappingIcon, select#iconmap_default_iconid', 'change', function() {
				$(this).closest('tr').find('.preview')
					.attr('src', 'imgstore.php?&width=<?= ZBX_ICON_PREVIEW_WIDTH ?>&height=<?= ZBX_ICON_PREVIEW_HEIGHT ?>&iconid=' + $(this).val())
					.data('imageFull', 'imgstore.php?iconid=' + $(this).val());
			})
			.delegate('img.preview', 'click', function(e) {
				var img = $('<img>', {src: $(this).data('imageFull')});
				hintBox.showStaticHint(e, this, img, '', true);
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
