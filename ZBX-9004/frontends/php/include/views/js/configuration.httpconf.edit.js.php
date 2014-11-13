<script type="text/javascript">
	function removeStep(obj) {
		var step = obj.getAttribute('remove_step'),
			table = jQuery('#httpStepTable');

		jQuery('#steps_' + step).remove();
		jQuery('#steps_' + step + '_httpstepid').remove();
		jQuery('#steps_' + step + '_httptestid').remove();
		jQuery('#steps_' + step + '_name').remove();
		jQuery('#steps_' + step + '_no').remove();
		jQuery('#steps_' + step + '_url').remove();
		jQuery('#steps_' + step + '_timeout').remove();
		jQuery('#steps_' + step + '_posts').remove();
		jQuery('#steps_' + step + '_variables').remove();
		jQuery('#steps_' + step + '_required').remove();
		jQuery('#steps_' + step + '_status_codes').remove();

		if (table.find('tr.sortable').length <= 1) {
			table.sortable('disable');
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
			jQuery('#steps_' + step + '_variables').attr('id', 'tmp_steps_' + step + '_variables');
			jQuery('#steps_' + step + '_required').attr('id', 'tmp_steps_' + step + '_required');
			jQuery('#steps_' + step + '_status_codes').attr('id', 'tmp_steps_' + step + '_status_codes');
			jQuery('#current_step_' + step).attr('id', 'tmp_current_step_' + step);

			// set order number
			jQuery(this)
				.attr('new_step', i)
				.text((i + 1) + ':');
			i++;
		});

		// rewrite ids in new order
		for (var n = 0; n < i; n++) {
			var currStep = jQuery('#tmp_current_step_' + n),
				newStep = currStep.attr('new_step');

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
			jQuery('#tmp_steps_' + n + '_variables').attr('id', 'steps_' + newStep + '_variables');
			jQuery('#tmp_steps_' + n + '_required').attr('id', 'steps_' + newStep + '_required');
			jQuery('#tmp_steps_' + n + '_status_codes').attr('id', 'steps_' + newStep + '_status_codes');

			jQuery('#remove_' + newStep).attr('remove_step', newStep);
			jQuery('#name_' + newStep).attr('name_step', newStep);
			jQuery('#steps_' + newStep + '_httpstepid').attr('name', 'steps[' + newStep + '][httpstepid]');
			jQuery('#steps_' + newStep + '_httptestid').attr('name', 'steps[' + newStep + '][httptestid]');
			jQuery('#steps_' + newStep + '_name').attr('name', 'steps[' + newStep + '][name]');
			jQuery('#steps_' + newStep + '_no')
				.attr('name', 'steps[' + newStep + '][no]')
				.val(parseInt(newStep) + 1);
			jQuery('#steps_' + newStep + '_url').attr('name', 'steps[' + newStep + '][url]');
			jQuery('#steps_' + newStep + '_timeout').attr('name', 'steps[' + newStep + '][timeout]');
			jQuery('#steps_' + newStep + '_posts').attr('name', 'steps[' + newStep + '][posts]');
			jQuery('#steps_' + newStep + '_variables').attr('name', 'steps[' + newStep + '][variables]');
			jQuery('#steps_' + newStep + '_required').attr('name', 'steps[' + newStep + '][required]');
			jQuery('#steps_' + newStep + '_status_codes').attr('name', 'steps[' + newStep + '][status_codes]');

			// set new step order position
			currStep.attr('id', 'current_step_' + newStep);
		}
	}

	jQuery(function($) {
		var stepTable = $('#httpStepTable'),
			stepTableWidth = stepTable.width(),
			stepTableColumns = $('#httpStepTable .header td'),
			stepTableColumnWidths = [];

		stepTableColumns.each(function() {
			stepTableColumnWidths[stepTableColumnWidths.length] = $(this).width();
		});

		stepTable.sortable({
			disabled: (stepTable.find('tr.sortable').length < 2),
			items: 'tbody tr.sortable',
			axis: 'y',
			cursor: 'move',
			handle: 'span.ui-icon-arrowthick-2-n-s',
			tolerance: 'pointer',
			opacity: 0.6,
			update: recalculateSortOrder,
			create: function () {
				// force not to change table width
				stepTable.width(stepTableWidth);
			},
			helper: function(e, ui) {
				ui.children().each(function(i) {
					var td = $(this);

					td.width(stepTableColumnWidths[i]);
				});

				// when dragging element on safari, it jumps out of the table
				if (SF) {
					// move back draggable element to proper position
					ui.css('left', (ui.offset().left - 2) + 'px');
				}

				stepTableColumns.each(function(i) {
					$(this).width(stepTableColumnWidths[i]);
				});

				return ui;
			},
			start: function(e, ui) {
				// fix placeholder not to change height while object is being dragged
				$(ui.placeholder).height($(ui.helper).height());

				if (IE8) {
					$('#stepTab #httpFormList ul.formlist').find('li.formrow');
				}
			}
		});

		// http step add pop up
		<?php if (!$this->data['templated']) : ?>
			$('#add_step').click(function() {
				var form = $(this).parents('form');

				// append existing step names
				var stepNames = '';
				form.find('input[name^=steps]').filter('input[name*=name]').each(function(i, stepName) {
					stepNames += '&steps_names[]=' + $(stepName).val();
				});

				return PopUp('popup_httpstep.php?dstfrm=httpForm' + stepNames, 600, 510);
			});
		<?php endif ?>

		// http step edit pop up
		<?php foreach ($this->data['steps'] as $i => $step): ?>
			$('#name_<?php echo $i; ?>').click(function() {
				// append existing step names
				var stepNames = '';
				var form = $(this).parents('form');
				form.find('input[name^=steps]').filter('input[name*=name]').each(function(i, stepName) {
					stepNames += '&steps_names[]=' + $(stepName).val();
				});

				return PopUp('popup_httpstep.php?dstfrm=httpForm&templated=<?php echo $this->data['templated']; ?>'
					+ '&list_name=steps&stepid=' + jQuery(this).attr('name_step')
					+ '<?php echo url_param($step['name'], false, 'name'); ?>'
					+ '<?php echo url_param($step['url'], false, 'url'); ?>'
					+ '<?php echo url_param($step['posts'], false, 'posts'); ?>'
					+ '<?php echo url_param($step['variables'], false, 'variables'); ?>'
					+ '<?php echo url_param($step['timeout'], false, 'timeout'); ?>'
					+ '<?php echo url_param($step['required'], false, 'required'); ?>'
					+ '<?php echo url_param($step['status_codes'], false, 'status_codes'); ?>'
					+ '<?php echo url_param($step['name'], false, 'old_name'); ?>'
					+ stepNames, 600, 510);
			});
		<?php endforeach ?>
	});

	createPlaceholders();
</script>
