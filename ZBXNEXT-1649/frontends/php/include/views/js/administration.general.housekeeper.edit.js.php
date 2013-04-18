<?php
$schema = DB::getSchema('config');
?>

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
			if (jQuery('#hk_history_mode').prop('checked') == true
					&& jQuery('#hk_history_global').prop('checked') == true) {
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
			if (jQuery('#hk_trends_mode').prop('checked') == true
					&& jQuery('#hk_trends_global').prop('checked') == true) {
				jQuery('#hk_trends').prop('disabled', false);
			} else {
				jQuery('#hk_trends').prop('disabled', true);
			}
		});

		jQuery("#reset").click(function(){
			<?php if ($schema['fields']['hk_events_mode']['default'] == 1): ?>
				jQuery('#hk_events_mode').prop('checked', true);
				jQuery('#hk_events_trigger').prop('disabled', false);
				jQuery('#hk_events_internal').prop('disabled', false);
				jQuery('#hk_events_discovery').prop('disabled', false);
				jQuery('#hk_events_autoreg').prop('disabled', false);
			<?php else: ?>
				jQuery('#hk_events_mode').prop('checked', false);
				jQuery('#hk_events_trigger').prop('disabled', true);
				jQuery('#hk_events_internal').prop('disabled', true);
				jQuery('#hk_events_discovery').prop('disabled', true);
				jQuery('#hk_events_autoreg').prop('disabled', true);
			<?php endif; ?>

			jQuery('#hk_events_trigger').val("<?php echo $schema['fields']['hk_events_trigger']['default']; ?>");
			jQuery('#hk_events_internal').val("<?php echo $schema['fields']['hk_events_internal']['default']; ?>");
			jQuery('#hk_events_discovery').val("<?php echo $schema['fields']['hk_events_discovery']['default']; ?>");
			jQuery('#hk_events_autoreg').val("<?php echo $schema['fields']['hk_events_autoreg']['default']; ?>");

			<?php if ($schema['fields']['hk_services_mode']['default'] == 1): ?>
				jQuery('#hk_services_mode').prop('checked', true);
				jQuery('#hk_services').prop('disabled', false);
			<?php else: ?>
				jQuery('#hk_services_mode').prop('checked', false);
				jQuery('#hk_services').prop('disabled', true);
			<?php endif; ?>

			jQuery('#hk_services').val("<?php echo $schema['fields']['hk_services']['default']; ?>");

			<?php if ($schema['fields']['hk_audit_mode']['default'] == 1): ?>
				jQuery('#hk_audit_mode').prop('checked', true);
				jQuery('#hk_audit').prop('disabled', false);
			<?php else: ?>
				jQuery('#hk_audit_mode').prop('checked', false);
				jQuery('#hk_audit').prop('disabled', true);
			<?php endif; ?>

			jQuery('#hk_audit').val("<?php echo $schema['fields']['hk_audit']['default']; ?>");

			<?php if ($schema['fields']['hk_sessions_mode']['default'] == 1): ?>
				jQuery('#hk_sessions_mode').prop('checked', true);
				jQuery('#hk_sessions').prop('disabled', false);
			<?php else: ?>
				jQuery('#hk_sessions_mode').prop('checked', false);
				jQuery('#hk_sessions').prop('disabled', true);
			<?php endif; ?>

			jQuery('#hk_sessions').val("<?php echo $schema['fields']['hk_sessions']['default']; ?>");

			<?php if ($schema['fields']['hk_history_mode']['default'] == 1): ?>
				jQuery('#hk_history_mode').prop('checked', true);
				<?php if ($schema['fields']['hk_history_global']['default'] == 1): ?>
					jQuery('#hk_history_global').prop('checked', true);
					jQuery('#hk_history').prop('disabled', false);
				<?php else: ?>
					jQuery('#hk_history_global').prop('checked', false);
					jQuery('#hk_history').prop('disabled', true);
				<?php endif; ?>
			<?php else: ?>
				jQuery('#hk_history_mode').prop('checked', false);
				jQuery('#hk_history_global').prop('checked', false);
				jQuery('#hk_history').prop('disabled', true);
			<?php endif; ?>

			jQuery('#hk_history').val("<?php echo $schema['fields']['hk_history']['default']; ?>");

			<?php if ($schema['fields']['hk_trends_mode']['default'] == 1): ?>
				jQuery('#hk_trends_mode').prop('checked', true);
				<?php if ($schema['fields']['hk_trends_global']['default'] == 1): ?>
					jQuery('#hk_trends_global').prop('checked', true);
					jQuery('#hk_trends').prop('disabled', false);
				<?php else: ?>
					jQuery('#hk_trends_global').prop('checked', false);
					jQuery('#hk_trends').prop('disabled', true);
				<?php endif; ?>
			<?php else: ?>
				jQuery('#hk_trends_mode').prop('checked', false);
				jQuery('#hk_trends_global').prop('checked', false);
				jQuery('#hk_trends').prop('disabled', true);
			<?php endif; ?>

			jQuery('#hk_trends').val("<?php echo $schema['fields']['hk_trends']['default']; ?>");
		});
	});
</script>
