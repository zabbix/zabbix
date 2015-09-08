<script type="text/x-jquery-tmpl" id="screenRowTPL">
<tr class="sortable" id="slides_#{rowId}">
	<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
		<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
		<input id="slides_#{rowId}_screenid" name="slides[#{rowId}][screenid]" type="hidden" value="#{screenid}" />
		<input id="slides_#{rowId}_slideid" name="slides[#{rowId}][slideid]" type="hidden" value="" />
	</td>
	<td>
		<span class="rowNum" id="current_slide_#{rowId}">#{rowNum}</span>
	</td>
	<td>#{name}</td>
	<td>
		<input type="text" id="slides_#{rowId}_delay" name="slides[#{rowId}][delay]" placeholder="<?= CHtml::encode(_('default')); ?>" value="" maxlength="5" onchange="validateNumericBox(this, true, false);" style="text-align: right; width: <?= ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH ?>px">
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" id="remove_#{rowId}" remove_slide="#{rowId}" onclick="removeSlide(this);"><?= _('Remove') ?></button>
	</td>
</tr>
</script>
<script type="text/javascript">
	function removeSlide(obj) {
		var step = obj.getAttribute('remove_slide');

		jQuery('#slides_' + step).remove();
		jQuery('#slides_' + step + '_slideid').remove();
		jQuery('#slides_' + step + '_screenid').remove();
		jQuery('#slides_' + step + '_delay').remove();

		if (jQuery('#slideTable tr.sortable').length <= 1) {
			jQuery('#slideTable').sortable('disable');
		}

		recalculateSortOrder();
	}

	function recalculateSortOrder() {
		var i = 0;

		jQuery('#slideTable tr.sortable .rowNum').each(function() {
			var newStep = (i == 0) ? '0' : i,
				currentStep = jQuery(this).closest('tr').attr('id').split('_')[1];

			// rewrite ids to temp
			jQuery('#remove_' + currentStep).attr('id', 'tmp_remove_' + newStep);
			jQuery('#slides_' + currentStep).attr('id', 'tmp_slides_' + newStep);
			jQuery('#slides_' + currentStep + '_slideid').attr('id', 'tmp_slides_' + newStep + '_slideid');
			jQuery('#slides_' + currentStep + '_screenid').attr('id', 'tmp_slides_' + newStep + '_screenid');
			jQuery('#slides_' + currentStep + '_delay').attr('id', 'tmp_slides_' + newStep + '_delay');
			jQuery('#current_slide_' + currentStep).attr('id', 'tmp_current_slide_' + newStep);

			// set order number
			jQuery(this)
				.attr('new_slide', i)
				.text((i + 1) + ':');
			i++;
		});

		// rewrite ids in new order
		for (var n = 0; n < i; n++) {
			var newStep = jQuery('#tmp_current_slide_' + n).attr('new_slide');
			jQuery('#tmp_current_slide_' + n).removeAttr('new_slide');

			jQuery('#tmp_remove_' + n).attr('id', 'remove_' + newStep);
			jQuery('#tmp_slides_' + n).attr('id', 'slides_' + newStep);
			jQuery('#tmp_slides_' + n + '_slideid').attr('id', 'slides_' + newStep + '_slideid');
			jQuery('#tmp_slides_' + n + '_screenid').attr('id', 'slides_' + newStep + '_screenid');
			jQuery('#tmp_slides_' + n + '_delay').attr('id', 'slides_' + newStep + '_delay');

			jQuery('#slides_' + newStep + '_slideid').attr('name', 'slides[' + newStep + '][slideid]');
			jQuery('#slides_' + newStep + '_screenid').attr('name', 'slides[' + newStep + '][screenid]');
			jQuery('#slides_' + newStep + '_delay').attr('name', 'slides[' + newStep + '][delay]');
			jQuery('#remove_' + newStep)
				.attr('remove_slide', newStep)
				.attr('name', 'remove_' + newStep);

			// set new slide order position
			jQuery('#tmp_current_slide_' + n).attr('id', 'current_slide_' + newStep);
		}
	}

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		var initSize = jQuery('#slideTable tr.sortable .rowNum').length,
			defaultDelay = jQuery('#delay').val();

		for (var i = 0; i < list.values.length; i++) {
			if (empty(list.values[i])) {
				continue;
			}

			var value = list.values[i];

			value['rowId'] = jQuery('#slideTable tr.sortable .rowNum').length;
			value['rowNum'] = value['rowId'] + 1;
			value['rowDelay'] = defaultDelay;

			var tpl = new Template(jQuery('#screenRowTPL').html());
			jQuery('#screenListFooter').before(tpl.evaluate(value));
		}

		if (initSize < 2) {
			initSortable();
		}
	}

	function initSortable() {
		var slideTable = jQuery('#slideTable'),
			slideTableWidth = slideTable.width(),
			slideTableColumns = jQuery('#slideTable .header td'),
			slideTableColumnWidths = [];

		slideTableColumns.each(function() {
			slideTableColumnWidths[slideTableColumnWidths.length] = jQuery(this).width();
		});

		slideTable.sortable({
			disabled: (slideTable.find('tr.sortable').length < 2),
			items: 'tbody tr.sortable',
			axis: 'y',
			cursor: 'move',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			update: recalculateSortOrder,
			create: function () {
				// force not to change table width
				slideTable.width(slideTableWidth);
			},
			helper: function(e, ui) {
				ui.children().each(function(i) {
					var td = jQuery(this);

					td.width(slideTableColumnWidths[i]);
				});

				// when dragging element on safari, it jumps out of the table on IE it moves about 4 pixels to right
				if (SF) {
					// move back draggable element to proper position
					ui.css('left', (ui.offset().left - 4) + 'px');
				}

				slideTableColumns.each(function(i) {
					jQuery(this).width(slideTableColumnWidths[i]);
				});

				return ui;
			},
			start: function(e, ui) {
				jQuery(ui.placeholder).height(jQuery(ui.helper).height());
			}
		});
	}

	jQuery(function() {
		initSortable();
	});
</script>
