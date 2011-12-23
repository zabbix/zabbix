<script type="text/x-jquery-tmpl" id="screenRowTPL">
<tr id="slides_#{rowNum}" class="sortable">
	<td>
		<span class="ui-icon ui-icon-arrowthick-2-n-s move"></span>
		<input name="slides[#{rowId}][screenid]" type="hidden" value="#{screenid}" />
	</td>
	<td>
		<span class="rowNum" id="current_slide_#{rowId}">#{rowNum}</span>
	</td>
	<td>#{name}</td>
	<td>
		<input class="input text" type="text" name="slides[#{rowId}][delay]" placeholder="<#{rowDelay}>" value="" size="5" maxlength="5" onchange="validateNumericBox(this, true, false);" style="text-align: right;">
	</td>
	<td>
		<input type="button" class="input link_menu" id="remove_#{rowId}" remove_slide="#{rowId}" value="<?php echo _('Remove'); ?>" onclick="removeSlide(this);" />
	</td>
</tr>
</script>

<script type="text/javascript">
	function removeSlide(obj) {
		if (obj != null) {
			slideId = obj.getAttribute('remove_slide');

			removeObjectById('slides_' + slideId);
			removeObjectById('slides_' + slideId + '_slideid');
			removeObjectById('slides_' + slideId + '_screenid');
			removeObjectById('slides_' + slideId + '_delay');

			if (jQuery('#slideTable tr.sortable').length <= 1) {
				jQuery('#slideTable').sortable('disable');
			}
			recalculateSortOrder();
		}
	}

	function recalculateSortOrder() {
		var i = 0;
		jQuery('#slideTable tr.sortable .rowNum').each(function() {
			var slideId = (i == 0) ? '0' : i;

			// rewrite ids to temp
			jQuery('#remove_' + slideId).attr('id', 'tmp_remove_' + slideId);
			jQuery('#slides_' + slideId).attr('id', 'tmp_slides_' + slideId);
			jQuery('#slides_' + slideId + '_slideid').attr('id', 'tmp_slides_' + slideId + '_slideid');
			jQuery('#slides_' + slideId + '_screenid').attr('id', 'tmp_slides_' + slideId + '_screenid');
			jQuery('#slides_' + slideId + '_delay').attr('id', 'tmp_slides_' + slideId + '_delay');
			jQuery('#current_slide_' + slideId).attr('id', 'tmp_current_slide_' + slideId);

			// set order number
			jQuery(this).attr('new_slide', i);
			jQuery(this).text((i + 1) + ':');
			i++
		});

		// rewrite ids in new order
		for (var n = 0; n < i; n++) {
			var newSlideId = jQuery('#tmp_current_slide_' + n).attr('new_slide');

			jQuery('#tmp_remove_' + n).attr('id', 'remove_' + newSlideId);
			jQuery('#tmp_slides_' + n).attr('id', 'slides_' + newSlideId);
			jQuery('#tmp_slides_' + n + '_slideid').attr('id', 'slides_' + newSlideId + '_slideid');
			jQuery('#tmp_slides_' + n + '_screenid').attr('id', 'slides_' + newSlideId + '_screenid');
			jQuery('#tmp_slides_' + n + '_delay').attr('id', 'slides_' + newSlideId + '_delay');

			jQuery('#slides_' + newSlideId + '_slideid').attr('name', 'slides[' + newSlideId + '][slideid]');
			jQuery('#slides_' + newSlideId + '_screenid').attr('name', 'slides[' + newSlideId + '][screenid]');
			jQuery('#slides_' + newSlideId + '_delay').attr('name', 'slides[' + newSlideId + '][delay]');
			jQuery('#remove_' + newSlideId).attr('remove_slide', newSlideId);

			// set new slide order position
			jQuery('#tmp_current_slide_' + n).attr('id', 'current_slide_' + newSlideId);
		}
	}

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

	function addPopupValues(list) {
		var defaultDelay = jQuery('#delay').val();
		for (var i = 0; i < list.values.length; i++) {
			if (empty(list.values[i])) {
				continue;
			}
			var value = list.values[i];
			value['rowNum'] = jQuery('#slideTable tr.sortable .rowNum').length + 1;
			value['rowId'] = jQuery('#slideTable tr.sortable .rowNum').length;
			value['rowDelay'] = defaultDelay;

			if (jQuery('#slides' + value.screenid).length) {
				continue;
			}

			var tpl = new Template(jQuery('#screenRowTPL').html());
			jQuery('#screenListFooter').before(tpl.evaluate(value));
		}
	}
</script>
