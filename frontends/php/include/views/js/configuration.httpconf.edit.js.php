<script type="text/javascript">
	jQuery(function($) {
		'use strict';

		function removeStep(obj) {
			var step = obj.getAttribute('remove_step'),
				table = $('#httpStepTable');

			$('#steps_' + step).remove();
			$('#steps_' + step + '_httpstepid').remove();
			$('#steps_' + step + '_httptestid').remove();
			$('#steps_' + step + '_name').remove();
			$('#steps_' + step + '_no').remove();
			$('#steps_' + step + '_url').remove();
			$('#steps_' + step + '_timeout').remove();
			$('#steps_' + step + '_posts').remove();
			$('#steps_' + step + '_required').remove();
			$('#steps_' + step + '_status_codes').remove();

			if (table.find('tr.sortable').length <= 1) {
				table.sortable('disable');
			}
			recalculateSortOrder();
		}

		function recalculateSortOrder() {
			var i = 0;
			$('#httpStepTable').find('tr.sortable .rowNum').each(function() {
				var step = (i == 0) ? '0' : i;

				// rewrite ids to temp
				$('#remove_' + step).attr('id', 'tmp_remove_' + step);
				$('#name_' + step).attr('id', 'tmp_name_' + step);
				$('#steps_' + step).attr('id', 'tmp_steps_' + step);
				$('#steps_' + step + '_httpstepid').attr('id', 'tmp_steps_' + step + '_httpstepid');
				$('#steps_' + step + '_httptestid').attr('id', 'tmp_steps_' + step + '_httptestid');
				$('#steps_' + step + '_name').attr('id', 'tmp_steps_' + step + '_name');
				$('#steps_' + step + '_no').attr('id', 'tmp_steps_' + step + '_no');
				$('#steps_' + step + '_url').attr('id', 'tmp_steps_' + step + '_url');
				$('#steps_' + step + '_timeout').attr('id', 'tmp_steps_' + step + '_timeout');
				$('#steps_' + step + '_posts').attr('id', 'tmp_steps_' + step + '_posts');
				$('#steps_' + step + '_required').attr('id', 'tmp_steps_' + step + '_required');
				$('#steps_' + step + '_status_codes').attr('id', 'tmp_steps_' + step + '_status_codes');
				$('#current_step_' + step).attr('id', 'tmp_current_step_' + step);

				// set order number
				$(this).attr('new_step', i);
				$(this).text((i + 1) + ':');
				i++
			});

			// rewrite ids in new order
			for (var n = 0; n < i; n++) {
				var currStep = $('#tmp_current_step_' + n),
					newStep = currStep.attr('new_step');

				$('#tmp_remove_' + n).attr('id', 'remove_' + newStep);
				$('#tmp_name_' + n).attr('id', 'name_' + newStep);
				$('#tmp_steps_' + n).attr('id', 'steps_' + newStep);
				$('#tmp_steps_' + n + '_httpstepid').attr('id', 'steps_' + newStep + '_httpstepid');
				$('#tmp_steps_' + n + '_httptestid').attr('id', 'steps_' + newStep + '_httptestid');
				$('#tmp_steps_' + n + '_name').attr('id', 'steps_' + newStep + '_name');
				$('#tmp_steps_' + n + '_no').attr('id', 'steps_' + newStep + '_no');
				$('#tmp_steps_' + n + '_url').attr('id', 'steps_' + newStep + '_url');
				$('#tmp_steps_' + n + '_timeout').attr('id', 'steps_' + newStep + '_timeout');
				$('#tmp_steps_' + n + '_posts').attr('id', 'steps_' + newStep + '_posts');
				$('#tmp_steps_' + n + '_required').attr('id', 'steps_' + newStep + '_required');
				$('#tmp_steps_' + n + '_status_codes').attr('id', 'steps_' + newStep + '_status_codes');

				$('#remove_' + newStep).attr('remove_step', newStep);
				$('#name_' + newStep).attr('name_step', newStep);
				$('#steps_' + newStep + '_httpstepid').attr('name', 'steps[' + newStep + '][httpstepid]');
				$('#steps_' + newStep + '_httptestid').attr('name', 'steps[' + newStep + '][httptestid]');
				$('#steps_' + newStep + '_name').attr('name', 'steps[' + newStep + '][name]');
				$('#steps_' + newStep + '_no')
					.attr('name', 'steps[' + newStep + '][no]')
					.val(parseInt(newStep) + 1);
				$('#steps_' + newStep + '_url').attr('name', 'steps[' + newStep + '][url]');
				$('#steps_' + newStep + '_timeout').attr('name', 'steps[' + newStep + '][timeout]');
				$('#steps_' + newStep + '_posts').attr('name', 'steps[' + newStep + '][posts]');
				$('#steps_' + newStep + '_required').attr('name', 'steps[' + newStep + '][required]');
				$('#steps_' + newStep + '_status_codes').attr('name', 'steps[' + newStep + '][status_codes]');

				// set new step order position
				currStep.attr('id', 'current_step_' + newStep);
			}
		}

		$('#httpStepTable').sortable({
			disabled: ($('#httpStepTable').find('tr.sortable').length <= 1),
			items: 'tbody tr.sortable',
			axis: 'y',
			cursor: 'move',
			containment: 'parent',
			handle: 'span.ui-icon-arrowthick-2-n-s',
			tolerance: 'pointer',
			opacity: 0.6,
			update: recalculateSortOrder,
			start: function(e, ui) {
				$(ui.placeholder).height($(ui.helper).height());
			}
		});
	});
</script>
