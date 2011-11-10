<script type="text/javascript">
	function removeHttpStep(stepid) {
		remove('steps_' + stepid);
		remove('steps_' + stepid + '_httpstepid');
		remove('steps_' + stepid + '_httptestid');
		remove('steps_' + stepid + '_name');
		remove('steps_' + stepid + '_no');
		remove('steps_' + stepid + '_url');
		remove('steps_' + stepid + '_timeout');
		remove('steps_' + stepid + '_posts');
		remove('steps_' + stepid + '_required');
		remove('steps_' + stepid + '_status_codes');

		if (jQuery('#httpStepTable tr.sortable').length <= 1) {
			jQuery('#httpStepTable').sortable('disable');
		}
		recalculateSortOrder();
	}

	function remove(id) {
		obj = document.getElementById(id);
		if (!empty(obj)) {
			obj.parentNode.removeChild(obj);
		}
	}

	function recalculateSortOrder() {
		var i = 0;
		jQuery('#httpStepTable tr.sortable .rowNum').each(function() {
			var stepId = i == 0 ? '0' : i;

			// rewrite ids to temp
			jQuery('#steps_' + stepId).attr('id', 'tmp_steps_' + stepId);
			jQuery('#current_step_' + stepId).attr('id', 'tmp_current_step_' + stepId);
			jQuery('#steps_' + stepId + '_httpstepid').attr('id', 'tmp_steps_' + stepId + '_httpstepid');
			jQuery('#steps_' + stepId + '_httptestid').attr('id', 'tmp_steps_' + stepId + '_httptestid');
			jQuery('#steps_' + stepId + '_name').attr('id', 'tmp_steps_' + stepId + '_name');
			jQuery('#steps_' + stepId + '_no').attr('id', 'tmp_steps_' + stepId + '_no');
			jQuery('#steps_' + stepId + '_url').attr('id', 'tmp_steps_' + stepId + '_url');
			jQuery('#steps_' + stepId + '_timeout').attr('id', 'tmp_steps_' + stepId + '_timeout');
			jQuery('#steps_' + stepId + '_posts').attr('id', 'tmp_steps_' + stepId + '_posts');
			jQuery('#steps_' + stepId + '_required').attr('id', 'tmp_steps_' + stepId + '_required');
			jQuery('#steps_' + stepId + '_status_codes').attr('id', 'tmp_steps_' + stepId + '_status_codes');

			// set order number
			jQuery(this).attr('new_step', i);
			jQuery(this).text((i + 1) + ':');
			i++
		});

		// rewrite ids in new order
		for (var n = 0; n < i; n++) {
			var newStep = getNewStepByCurrentStep(n);
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

			jQuery('#steps_' + newStep + '_httpstepid').attr('name', 'steps[' + newStep + '][httpstepid]');
			jQuery('#steps_' + newStep + '_httptestid').attr('name', 'steps[' + newStep + '][httptestid]');
			jQuery('#steps_' + newStep + '_name').attr('name', 'steps[' + newStep + '][name]');
			jQuery('#steps_' + newStep + '_no').attr('name', 'steps[' + newStep + '][no]');
			jQuery('#steps_' + newStep + '_url').attr('name', 'steps[' + newStep + '][url]');
			jQuery('#steps_' + newStep + '_timeout').attr('name', 'steps[' + newStep + '][timeout]');
			jQuery('#steps_' + newStep + '_posts').attr('name', 'steps[' + newStep + '][posts]');
			jQuery('#steps_' + newStep + '_required').attr('name', 'steps[' + newStep + '][required]');
			jQuery('#steps_' + newStep + '_status_codes').attr('name', 'steps[' + newStep + '][status_codes]');

			jQuery('#steps_' + newStep + '_no').val(parseInt(newStep) + 1);

			// set new step order position
			jQuery('#tmp_current_step_' + n).attr('id', 'current_step_' + newStep);
		}
	}

	function getNewStepByCurrentStep(currentStep) {
		return jQuery('#tmp_current_step_' + currentStep).attr('new_step');
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
