<script type="text/javascript">
	function removeSlide(slideid) {
		removeObjectById('slides_' + slideid);
		removeObjectById('slides_' + slideid + '_slideid');
		removeObjectById('slides_' + slideid + '_screenid');
		removeObjectById('slides_' + slideid + '_delay');

		if (jQuery('#slideTable tr.sortable').length <= 1) {
			jQuery('#slideTable').sortable('disable');
		}
		recalculateSortOrder();
	}

	function recalculateSortOrder() {
		var i = 0;
		jQuery('#slideTable tr.sortable .rowNum').each(function() {
			var slideId = (i == 0) ? '0' : i;

			// rewrite ids to temp
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

			jQuery('#tmp_slides_' + n).attr('id', 'slides_' + newSlideId);
			jQuery('#tmp_slides_' + n + '_slideid').attr('id', 'slides_' + newSlideId + '_slideid');
			jQuery('#tmp_slides_' + n + '_screenid').attr('id', 'slides_' + newSlideId + '_screenid');
			jQuery('#tmp_slides_' + n + '_delay').attr('id', 'slides_' + newSlideId + '_delay');

			jQuery('#slides_' + newSlideId + '_slideid').attr('name', 'slides[' + newSlideId + '][slideid]');
			jQuery('#slides_' + newSlideId + '_screenid').attr('name', 'slides[' + newSlideId + '][screenid]');
			jQuery('#slides_' + newSlideId + '_delay').attr('name', 'slides[' + newSlideId + '][delay]');

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
</script>
