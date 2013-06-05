<script type="text/javascript">
	jQuery(function() {
		// disable the status filter when using the state filter
		jQuery('#filter_state').change(function() {
			var state = jQuery(this);
			var status = jQuery('#filter_status');

			if (state.val() != -1) {
				status.data('last-value', status.val()).prop('disabled', true).val(<?php echo ITEM_STATUS_ACTIVE ?>);
			}
			else {
				if (status.prop('disabled')) {
					status.val(status.data('last-value'));
				}
				status.prop('disabled', false);
			}
		})
		.trigger('change');
	});
</script>
