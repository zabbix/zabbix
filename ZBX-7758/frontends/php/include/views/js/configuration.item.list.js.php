<script type="text/javascript">
	jQuery(function() {
		// disable the status filter when using the state filter
		jQuery('#filter_state').change(function(event, saveValue) {
			var stateObj = jQuery(this),
				statusObj = jQuery('#filter_status'),
				saveValue = (saveValue === undefined) ? true : saveValue;

			if (stateObj.val() == -1) {
				// restore the last remembered status filter value
				if (statusObj.prop('disabled') && statusObj.data('last-value') !== undefined) {
					statusObj.val(statusObj.data('last-value'));
				}
				statusObj.prop('disabled', false);
			}
			else {
				// remember the last status filter value
				if (!statusObj.prop('disabled') && saveValue) {
					statusObj.data('last-value', statusObj.val());
				}
				statusObj.prop('disabled', true).val(<?php echo ITEM_STATUS_ACTIVE ?>);
			}
		})
		.trigger('change', false);
	});
</script>
