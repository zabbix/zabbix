<script type="text/x-jquery-tmpl" id="screenRowTPL">
<tr id="slides_#{rowId}" class="sortable">
	<td>
		<span class="ui-icon ui-icon-arrowthick-2-n-s move"></span>
		<input id="slides_#{rowId}_screenid" name="slides[#{rowId}][screenid]" type="hidden" value="#{screenid}" />
		<input id="slides_#{rowId}_slideid" name="slides[#{rowId}][slideid]" type="hidden" value="" />
	</td>
	<td>
		<span class="rowNum" id="current_slide_#{rowId}">#{rowNum}</span>
	</td>
	<td>#{name}</td>
	<td>
		<input class="input text" type="text" id="slides_#{rowId}_delay" name="slides[#{rowId}][delay]" placeholder="<?php echo CHtml::encode(_('default')); ?>" value="" size="5" maxlength="5" onchange="validateNumericBox(this, true, false);" style="text-align: right;">
	</td>
	<td>
		<input type="button" class="input link_menu" id="remove_#{rowId}" remove_slide="#{rowId}" value="<?php echo CHtml::encode(_('Remove')); ?>" onclick="removeSlide(this);" />
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
			var step = (i == 0) ? '0' : i;

			// rewrite ids to temp
			jQuery('#remove_' + step).attr('id', 'tmp_remove_' + step);
			jQuery('#slides_' + step).attr('id', 'tmp_slides_' + step);
			jQuery('#slides_' + step + '_slideid').attr('id', 'tmp_slides_' + step + '_slideid');
			jQuery('#slides_' + step + '_screenid').attr('id', 'tmp_slides_' + step + '_screenid');
			jQuery('#slides_' + step + '_delay').attr('id', 'tmp_slides_' + step + '_delay');
			jQuery('#current_slide_' + step).attr('id', 'tmp_current_slide_' + step);

			// set order number
			jQuery(this).attr('new_slide', i);
			jQuery(this).text((i + 1) + ':');
			i++
		});

		// rewrite ids in new order
		for (var n = 0; n < i; n++) {
			var newStep = jQuery('#tmp_current_slide_' + n).attr('new_slide');

			jQuery('#tmp_remove_' + n).attr('id', 'remove_' + newStep);
			jQuery('#tmp_slides_' + n).attr('id', 'slides_' + newStep);
			jQuery('#tmp_slides_' + n + '_slideid').attr('id', 'slides_' + newStep + '_slideid');
			jQuery('#tmp_slides_' + n + '_screenid').attr('id', 'slides_' + newStep + '_screenid');
			jQuery('#tmp_slides_' + n + '_delay').attr('id', 'slides_' + newStep + '_delay');

			jQuery('#slides_' + newStep + '_slideid').attr('name', 'slides[' + newStep + '][slideid]');
			jQuery('#slides_' + newStep + '_screenid').attr('name', 'slides[' + newStep + '][screenid]');
			jQuery('#slides_' + newStep + '_delay').attr('name', 'slides[' + newStep + '][delay]');
			jQuery('#remove_' + newStep).attr('remove_slide', newStep);

			// set new slide order position
			jQuery('#tmp_current_slide_' + n).attr('id', 'current_slide_' + newStep);
		}
	}

	function addPopupValues(list) {
		var initSize = jQuery('#slideTable tr.sortable .rowNum').length;
		var defaultDelay = jQuery('#delay').val();
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
		if (initSize <= 1) {
			initSortable();
		}
		createPlaceholders();
	}

	function initSortable() {
		jQuery(document).ready(function() {
			'use strict';

			jQuery('#slideTable').sortable({
				disabled: (jQuery('#slideTable tr.sortable').length <= 1),
				items: 'tbody tr.sortable',
				axis: 'y',
				cursor: 'move',
				containment: 'parent',
				handle: 'span.ui-icon-arrowthick-2-n-s',
				tolerance: 'pointer',
				opacity: 0.6,
				update: recalculateSortOrder,
				start: function(e, ui) {
					jQuery(ui.placeholder).height(jQuery(ui.helper).height());
				}
			});
		});
	}

	initSortable();
	createPlaceholders();
</script>
