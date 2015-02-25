<script type="text/javascript">
	jQuery(function($) {
		var iconMapTable = $('#iconMapTable'),
			addMappindButton = $('#addMapping');

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
			handle: 'span.ui-icon-arrowthick-2-n-s',
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
					.attr('src', 'imgstore.php?&width=<?php echo ZBX_ICON_PREVIEW_WIDTH; ?>&height=<?php echo ZBX_ICON_PREVIEW_HEIGHT; ?>&iconid=' + $(this).val())
					.data('imageFull', 'imgstore.php?iconid=' + $(this).val());
			})
			.delegate('img.preview', 'click', function(e) {
				var img = $('<img />', {src: $(this).data('imageFull')});
				hintBox.showStaticHint(e, this, img, '', '', true);
			});

		addMappindButton.click(function() {
			var tpl = new Template($('#rowTpl').html()),
				iconmappingid = getUniqueId(),
				mapping = {};

			// on error, whole page reloads and getUniqueId reset ids sequence which can cause in duplicate ids
			while ($('#iconmapidRow_' + iconmappingid).length != 0) {
				iconmappingid = getUniqueId();
			}

			mapping.iconmappingid = iconmappingid;
			$('<tr id="iconmapidRow_' + iconmappingid + '" class="sortable">' + tpl.evaluate(mapping) + '</tr>').insertBefore('#rowTpl');

			$('#iconmapidRow_' + iconmappingid + ' :input').prop('disabled', false);
			iconMapTable.sortable('refresh');

			if (iconMapTable.find('tr.sortable').length > 1) {
				iconMapTable.sortable('enable');
			}

			recalculateSortOrder();
		});

		if (iconMapTable.find('tr.sortable').length === 0) {
			addMappindButton.click();
		}
	});
</script>
