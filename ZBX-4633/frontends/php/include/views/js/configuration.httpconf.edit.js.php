<script type="text/javascript">
	function removeStep(obj) {
		var step = obj.getAttribute('remove_step');

		jQuery('#steps_' + step).remove();
		jQuery('#steps_' + step + '_httpstepid').remove();
		jQuery('#steps_' + step + '_httptestid').remove();
		jQuery('#steps_' + step + '_name').remove();
		jQuery('#steps_' + step + '_no').remove();
		jQuery('#steps_' + step + '_url').remove();
		jQuery('#steps_' + step + '_timeout').remove();
		jQuery('#steps_' + step + '_posts').remove();
		jQuery('#steps_' + step + '_required').remove();
		jQuery('#steps_' + step + '_status_codes').remove();

		if (jQuery('#httpStepTable tr.sortable').length <= 1) {
			jQuery('#httpStepTable').sortable('disable');
		}
		recalculateSortOrder();
	}

	function recalculateSortOrder() {
		var i = 0;
		jQuery('#httpStepTable tr.sortable .rowNum').each(function() {
			var step = (i == 0) ? '0' : i;

			// rewrite ids to temp
			jQuery('#remove_' + step).attr('id', 'tmp_remove_' + step);
			jQuery('#name_' + step).attr('id', 'tmp_name_' + step);
			jQuery('#steps_' + step).attr('id', 'tmp_steps_' + step);
			jQuery('#steps_' + step + '_httpstepid').attr('id', 'tmp_steps_' + step + '_httpstepid');
			jQuery('#steps_' + step + '_httptestid').attr('id', 'tmp_steps_' + step + '_httptestid');
			jQuery('#steps_' + step + '_name').attr('id', 'tmp_steps_' + step + '_name');
			jQuery('#steps_' + step + '_no').attr('id', 'tmp_steps_' + step + '_no');
			jQuery('#steps_' + step + '_url').attr('id', 'tmp_steps_' + step + '_url');
			jQuery('#steps_' + step + '_timeout').attr('id', 'tmp_steps_' + step + '_timeout');
			jQuery('#steps_' + step + '_posts').attr('id', 'tmp_steps_' + step + '_posts');
			jQuery('#steps_' + step + '_required').attr('id', 'tmp_steps_' + step + '_required');
			jQuery('#steps_' + step + '_status_codes').attr('id', 'tmp_steps_' + step + '_status_codes');
			jQuery('#current_step_' + step).attr('id', 'tmp_current_step_' + step);

			// set order number
			jQuery(this).attr('new_step', i);
			jQuery(this).text((i + 1) + ':');
			i++
		});

		// rewrite ids in new order
		for (var n = 0; n < i; n++) {
			var newStep = jQuery('#tmp_current_step_' + n).attr('new_step');

			jQuery('#tmp_remove_' + n).attr('id', 'remove_' + newStep);
			jQuery('#tmp_name_' + n).attr('id', 'name_' + newStep);
			jQuery('#tmp_steps_' + n).attr('id', 'steps_' + newStep);
			jQuery('#tmp_steps_' + n + '_httpstepid').attr('id', 'steps_' + newStep + '_httpstepid');
			jQuery('#tmp_steps_' + n + '_httptestid').attr('id', 'steps_' + newStep + '_httptestid');
			jQuery('#tmp_steps_' + n + '_name').attr('id', 'steps_' + newStep + '_name');
			jQuery('#tmp_steps_' + n + '_no').attr('id', 'steps_' + newStep + '_no');
			jQuery('#tmp_steps_' + n + '_url').attr('id', 'steps_' + newStep + '_url');
			jQuery('#tmp_steps_' + n + '_timeout').attr('id', 'steps_' + newStep + '_timeout');
			jQuery('#tmp_steps_' + n + '_posts').attr('id', 'steps_' + newStep + '_posts');
			jQuery('#tmp_steps_' + n + '_required').attr('id', 'steps_' + newStep + '_required');
			jQuery('#tmp_steps_' + n + '_status_codes').attr('id', 'steps_' + newStep + '_status_codes');

			jQuery('#remove_' + newStep).attr('remove_step', newStep);
			jQuery('#name_' + newStep).attr('name_step', newStep);
			jQuery('#steps_' + newStep + '_httpstepid').attr('name', 'steps[' + newStep + '][httpstepid]');
			jQuery('#steps_' + newStep + '_httptestid').attr('name', 'steps[' + newStep + '][httptestid]');
			jQuery('#steps_' + newStep + '_name').attr('name', 'steps[' + newStep + '][name]');
			jQuery('#steps_' + newStep + '_no').attr('name', 'steps[' + newStep + '][no]');
			jQuery('#steps_' + newStep + '_no').val(parseInt(newStep) + 1);
			jQuery('#steps_' + newStep + '_url').attr('name', 'steps[' + newStep + '][url]');
			jQuery('#steps_' + newStep + '_timeout').attr('name', 'steps[' + newStep + '][timeout]');
			jQuery('#steps_' + newStep + '_posts').attr('name', 'steps[' + newStep + '][posts]');
			jQuery('#steps_' + newStep + '_required').attr('name', 'steps[' + newStep + '][required]');
			jQuery('#steps_' + newStep + '_status_codes').attr('name', 'steps[' + newStep + '][status_codes]');

			// set new step order position
			jQuery('#tmp_current_step_' + n).attr('id', 'current_step_' + newStep);
		}
	}

	jQuery(document).ready(function() {
		'use strict';

		jQuery('#httpStepTable').sortable({
			disabled: (jQuery('#httpStepTable tr.sortable').length <= 1),
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
