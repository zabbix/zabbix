<?php
$schema = DB::getSchema('config');
?>

<script type="text/javascript">
	jQuery(document).ready(function() {
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

		jQuery("#resetDefaults").click(function() {
			overlayDialogue({
				'title': '<?= _('Reset confirmation') ?>',
				'content': jQuery('<span>').text('<?= _('Reset all fields to default values?') ?>'),
				'buttons': [
					{
						'title': '<?= _('Cancel') ?>',
						'class': 'btn-alt',
						'action': function() {}
					},
					{
						'title': '<?= _('Reset defaults') ?>',
						'focused': true,
						'action': function() {
							// events and alerts
							jQuery('#hk_events_mode')
								.prop('checked',
									<?= ($schema['fields']['hk_events_mode']['default'] == 1) ? 'true' : 'false' ?>
								)
								.change();
							jQuery('#hk_events_trigger')
								.val("<?= $schema['fields']['hk_events_trigger']['default'] ?>");
							jQuery('#hk_events_internal')
								.val("<?= $schema['fields']['hk_events_internal']['default'] ?>");
							jQuery('#hk_events_discovery')
								.val("<?= $schema['fields']['hk_events_discovery']['default'] ?>");
							jQuery('#hk_events_autoreg')
								.val("<?= $schema['fields']['hk_events_autoreg']['default'] ?>");

							// IT services
							jQuery('#hk_services_mode')
								.prop('checked',
									<?= ($schema['fields']['hk_services_mode']['default'] == 1) ? 'true' : 'false' ?>
								)
								.change();
							jQuery('#hk_services').val("<?= $schema['fields']['hk_services']['default'] ?>");

							// audit
							jQuery('#hk_audit_mode')
								.prop('checked',
									<?= ($schema['fields']['hk_audit_mode']['default'] == 1) ? 'true' : 'false' ?>
								)
								.change();
							jQuery('#hk_audit').val("<?= $schema['fields']['hk_audit']['default'] ?>");

							// user sessions
							jQuery('#hk_sessions_mode')
								.prop('checked',
									<?= ($schema['fields']['hk_sessions_mode']['default'] == 1) ? 'true' : 'false' ?>
								)
								.change();
							jQuery('#hk_sessions').val("<?= $schema['fields']['hk_sessions']['default'] ?>");

							// history
							jQuery('#hk_history_mode').prop('checked',
								<?= ($schema['fields']['hk_history_mode']['default'] == 1) ? 'true' : 'false' ?>
							);
							jQuery('#hk_history_global')
								.prop('checked',
									<?= ($schema['fields']['hk_history_global']['default'] == 1) ? 'true' : 'false' ?>
								)
								.change();
							jQuery('#hk_history').val("<?= $schema['fields']['hk_history']['default'] ?>");

							// trends
							jQuery('#hk_trends_mode').prop('checked',
								<?= ($schema['fields']['hk_trends_mode']['default'] == 1) ? 'true' : 'false' ?>
							);
							jQuery('#hk_trends_global')
								.prop('checked',
									<?= ($schema['fields']['hk_trends_global']['default'] == 1) ? 'true' : 'false' ?>
								)
								.change();
							jQuery('#hk_trends').val("<?= $schema['fields']['hk_trends']['default'] ?>");
						}
					}
				]
			});
		});
	});
</script>
