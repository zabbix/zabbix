<script type="text/javascript">
	jQuery(function() {
		// disable the status filter when using the state filter
		jQuery('#filter_state').change(function() {
			var stateObj = jQuery(this);
			var statusObj = jQuery('#filter_status');

			if (stateObj.val() == -1) {
				if (statusObj.prop('disabled')) {
					statusObj.val(statusObj.data('last-value'));
				}
				statusObj.prop('disabled', false);
			}
			else {
				if (!statusObj.prop('disabled')) {
					statusObj.data('last-value', statusObj.val());
				}
				statusObj.prop('disabled', true).val(<?php echo ITEM_STATUS_ACTIVE ?>);
			}
		})
		.trigger('change');
	});
</script>
