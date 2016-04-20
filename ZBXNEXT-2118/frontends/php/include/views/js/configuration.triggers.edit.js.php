<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Refresh field visibility on document load.
		changeRecoveryMode();

		$('input[name=recovery_mode]').change(function() {
			changeRecoveryMode();
		});

		function changeRecoveryMode() {
			/* Used textarea[id="recovery_expression"] selector, because #recovery_expression doesn't correctly work for
			 * disabled fields.
			 */
			if ($('input[name=recovery_mode]:checked').val() == <?= TRIGGER_REC_MODE_REC_EXPRESSION ?>) {
				$('textarea[id="recovery_expression"]').closest('li').show();
			}
			else {
				$('textarea[id="recovery_expression"]').closest('li').hide();
			}
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
