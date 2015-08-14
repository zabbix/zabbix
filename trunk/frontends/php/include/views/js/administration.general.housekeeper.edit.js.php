<?php
$schema = DB::getSchema('config');
?>
<div id="dialog" style="display:none; white-space: normal;"></div>

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

		jQuery('#hk_trends_global').change(function() {
			jQuery('#hk_trends').prop('disabled', !this.checked);
		});

		// reset button
		jQuery("#resetDefaults").click(function(){

			jQuery('#dialog').text(<?php echo CJs::encodeJson(_('Reset all fields to default values?')); ?>);
			var w = jQuery('#dialog').outerWidth()+20;

			jQuery('#dialog').dialog({
				buttons: [
					{text: <?php echo CJs::encodeJson(_('Reset defaults')); ?>, click: function(){
						// events and alerts
						<?php if ($schema['fields']['hk_events_mode']['default'] == 1): ?>
							jQuery('#hk_events_mode').prop('checked', true);
						<?php else: ?>
							jQuery('#hk_events_mode').prop('checked', false);
						<?php endif; ?>

						jQuery('#hk_events_mode').trigger('change');

						jQuery('#hk_events_trigger').val("<?php echo $schema['fields']['hk_events_trigger']['default']; ?>");
						jQuery('#hk_events_internal').val("<?php echo $schema['fields']['hk_events_internal']['default']; ?>");
						jQuery('#hk_events_discovery').val("<?php echo $schema['fields']['hk_events_discovery']['default']; ?>");
						jQuery('#hk_events_autoreg').val("<?php echo $schema['fields']['hk_events_autoreg']['default']; ?>");

						// IT services
						<?php if ($schema['fields']['hk_services_mode']['default'] == 1): ?>
							jQuery('#hk_services_mode').prop('checked', true);
						<?php else: ?>
							jQuery('#hk_services_mode').prop('checked', false);
						<?php endif; ?>

						jQuery('#hk_services_mode').trigger('change');

						jQuery('#hk_services').val("<?php echo $schema['fields']['hk_services']['default']; ?>");

						// audit
						<?php if ($schema['fields']['hk_audit_mode']['default'] == 1): ?>
							jQuery('#hk_audit_mode').prop('checked', true);
						<?php else: ?>
							jQuery('#hk_audit_mode').prop('checked', false);
						<?php endif; ?>

						jQuery('#hk_audit_mode').trigger('change');

						jQuery('#hk_audit').val("<?php echo $schema['fields']['hk_audit']['default']; ?>");

						// user sessions
						<?php if ($schema['fields']['hk_sessions_mode']['default'] == 1): ?>
							jQuery('#hk_sessions_mode').prop('checked', true);
						<?php else: ?>
							jQuery('#hk_sessions_mode').prop('checked', false);
						<?php endif; ?>

						jQuery('#hk_sessions_mode').trigger('change');

						jQuery('#hk_sessions').val("<?php echo $schema['fields']['hk_sessions']['default']; ?>");

						// history
						<?php if ($schema['fields']['hk_history_mode']['default'] == 1): ?>
							jQuery('#hk_history_mode').prop('checked', true);
						<?php else: ?>
							jQuery('#hk_history_mode').prop('checked', false);
						<?php endif; ?>

						<?php if ($schema['fields']['hk_history_global']['default'] == 1): ?>
							jQuery('#hk_history_global').prop('checked', true);
						<?php else: ?>
							jQuery('#hk_history_global').prop('checked', false);
						<?php endif; ?>

						jQuery('#hk_history_global').trigger('change');

						jQuery('#hk_history').val("<?php echo $schema['fields']['hk_history']['default']; ?>");

						// trends
						<?php if ($schema['fields']['hk_trends_mode']['default'] == 1): ?>
							jQuery('#hk_trends_mode').prop('checked', true);
						<?php else: ?>
							jQuery('#hk_trends_mode').prop('checked', false);
						<?php endif; ?>

						<?php if ($schema['fields']['hk_trends_global']['default'] == 1): ?>
							jQuery('#hk_trends_global').prop('checked', true);
						<?php else: ?>
							jQuery('#hk_trends_global').prop('checked', false);
						<?php endif; ?>

						jQuery('#hk_trends_global').trigger('change');

						jQuery('#hk_trends').val("<?php echo $schema['fields']['hk_trends']['default']; ?>");

						jQuery(this).dialog("destroy");
					}},
					{text: <?php echo CJs::encodeJson(_('Cancel')); ?>, click: function(){
						jQuery(this).dialog("destroy");
					}}
				],
				draggable: true,
				modal: true,
				width: (w > 600 ? 600 : 'inherit'),
				resizable: false,
				minWidth: 200,
				minHeight: 100,
				title: <?php echo CJs::encodeJson(_('Reset confirmation')); ?>,
				close: function(){ jQuery(this).dialog('destroy'); }
			});
		});
	});
</script>
