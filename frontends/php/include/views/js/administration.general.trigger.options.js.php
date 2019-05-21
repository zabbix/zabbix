<?php
$schema = DB::getSchema('config');
?>

<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery("input[name=custom_color]").on('change', function() {
			var checked = jQuery(this).is(':checked');
			jQuery(".input-color-picker").each(function() {
				var $field = jQuery(this);
				$field.toggleClass('<?= ZBX_STYLE_DISABLED ?>', !checked);
				jQuery("input", $field).prop('disabled', !checked);
			});
		});
		jQuery("#resetDefaults").click(function() {
			overlayDialogue({
				'title': <?= CJs::encodeJson(_('Reset confirmation')) ?>,
				'content': jQuery('<span>').text(<?= CJs::encodeJson(_('Reset all fields to default values?')) ?>),
				'buttons': [
					{
						'title': <?= CJs::encodeJson(_('Cancel')) ?>,
						'cancel': true,
						'class': '<?= ZBX_STYLE_BTN_ALT ?>',
						'action': function() {}
					},
					{
						'title': <?= CJs::encodeJson(_('Reset defaults')) ?>,
						'focused': true,
						'action': function() {
							var custom_color_enabled = <?=
								($schema['fields']['custom_color']['default'] == EVENT_CUSTOM_COLOR_ENABLED)
									? 'true'
									: 'false'
								?>;

							jQuery('#custom_color')
								.prop('checked', custom_color_enabled)
								.trigger('change');

							// Unacknowledged problem events
							jQuery('#problem_unack_color')
								.val("<?= $schema['fields']['problem_unack_color']['default'] ?>")
								.prop('disabled', !custom_color_enabled)
								.trigger('change');
							jQuery('#problem_unack_style').prop('checked',
								<?= ($schema['fields']['problem_unack_style']['default'] == 0) ? 'false' : 'true' ?>
							);

							// Acknowledged problem events
							jQuery('#problem_ack_color')
								.val("<?= $schema['fields']['problem_ack_color']['default'] ?>")
								.prop('disabled', !custom_color_enabled)
								.trigger('change');
							jQuery('#problem_ack_style').prop('checked',
								<?= ($schema['fields']['problem_ack_style']['default'] == 0) ? 'false' : 'true' ?>
							);

							// Unacknowledged resolved events
							jQuery('#ok_unack_color')
								.val("<?= $schema['fields']['ok_unack_color']['default'] ?>")
								.prop('disabled', !custom_color_enabled)
								.trigger('change');
							jQuery('#ok_unack_style').prop('checked',
								<?= ($schema['fields']['ok_unack_style']['default'] == 0) ? 'false' : 'true' ?>
							);

							// Acknowledged resolved events
							jQuery('#ok_ack_color')
								.val("<?= $schema['fields']['ok_ack_color']['default'] ?>")
								.prop('disabled', !custom_color_enabled)
								.trigger('change');
							jQuery('#ok_ack_style').prop('checked',
								<?= ($schema['fields']['ok_ack_style']['default'] == 0) ? 'false' : 'true' ?>
							);

							jQuery('#ok_period').val("<?= $schema['fields']['ok_period']['default'] ?>");
							jQuery('#blink_period').val("<?= $schema['fields']['blink_period']['default'] ?>");
						}
					}
				]
			}, this);
		});
	});
</script>
