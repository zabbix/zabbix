<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Refresh field visibility on document load.
		changeRecoveryMode();
		changeCorrelationMode();

		$('input[name=recovery_mode]').change(function() {
			changeRecoveryMode();
		});

		$('input[name=correlation_mode]').change(function() {
			changeCorrelationMode();
		});

		function changeRecoveryMode() {
			var	recovery_mode = $('input[name=recovery_mode]:checked').val();

			$('#expression_row').find('label').text(
				(recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>)
					? <?= CJs::encodeJson(_('Problem expression')) ?>
					: <?= CJs::encodeJson(_('Expression')) ?>
			);
			$('.recovery_expression_constructor_row')
				.toggle(recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>);
			$('#correlation_mode_row')
				.toggle(recovery_mode == <?= ZBX_RECOVERY_MODE_EXPRESSION ?>
					|| recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>
				);

			changeCorrelationMode();
		}

		function changeCorrelationMode() {
			var	recovery_mode = $('input[name=recovery_mode]:checked').val(),
				correlation_mode = $('input[name=correlation_mode]:checked').val();

			$('#correlation_tag_row')
				.toggle((recovery_mode == <?= ZBX_RECOVERY_MODE_EXPRESSION ?>
					|| recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>)
					&& correlation_mode == <?= ZBX_TRIGGER_CORRELATION_TAG ?>
				);
		}
	});

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		if (!isset('object', list)) {
			return false;
		}

		if (list.object == 'deptrigger') {
			for (var i = 0; i < list.values.length; i++) {
				create_var('triggersForm', 'new_dependency[' + i + ']', list.values[i].triggerid, false);
			}

			// return to the same form after it has been submitted
			jQuery('#action').val(<?php echo CJs::encodeJson(getRequest('action')) ?>);

			create_var('triggersForm', 'add_dependency', 1, true);
		}
	}

	function removeDependency(triggerid) {
		jQuery('#dependency_' + triggerid).remove();
		jQuery('#dependencies_' + triggerid).remove();
	}
</script>
