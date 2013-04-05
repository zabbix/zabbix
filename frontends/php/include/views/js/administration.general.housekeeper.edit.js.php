<script type="text/javascript">
	jQuery(function() {
		jQuery('#hk_events_mode').change(function() {
			jQuery('#hk_events_trigger').prop('disabled', !this.checked);
			jQuery('#hk_events_internal').prop('disabled', !this.checked);
			jQuery('#hk_events_discovery').prop('disabled', !this.checked);
			jQuery('#hk_events_autoreg').prop('disabled', !this.checked);
		});

		jQuery('#hk_services_mode').change(function() {
			jQuery('#hk_services').prop('disabled', !this.checked);
		});

		jQuery('#hk_audit_mode').change(function() {
			jQuery('#hk_audit').prop('disabled', !this.checked);
		});

		jQuery('#hk_sessions_mode').change(function() {
			jQuery('#hk_sessions').prop('disabled', !this.checked);
		});

		jQuery('#hk_history_global').change(function() {
			jQuery('#hk_history').prop('disabled', !this.checked);
		});

		jQuery('#hk_history_mode').change(function() {
			jQuery('#hk_history_global').prop('disabled', !this.checked);
			if (jQuery('#hk_history_mode').prop("checked") == true
					&& jQuery('#hk_history_global').prop("checked") == true) {
				jQuery('#hk_history').prop('disabled', false);
			} else {
				jQuery('#hk_history').prop('disabled', true);
			}
		});

		jQuery('#hk_trends_global').change(function() {
			jQuery('#hk_trends').prop('disabled', !this.checked);
		});

		jQuery('#hk_trends_mode').change(function() {
			jQuery('#hk_trends_global').prop('disabled', !this.checked);
			if (jQuery('#hk_trends_mode').prop("checked") == true
					&& jQuery('#hk_trends_global').prop("checked") == true) {
				jQuery('#hk_trends').prop('disabled', false);
			} else {
				jQuery('#hk_trends').prop('disabled', true);
			}
		});
	});
</script>
