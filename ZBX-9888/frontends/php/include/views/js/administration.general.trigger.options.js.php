<?php
$schema = DB::getSchema('config');
?>

<script type="text/javascript">
	jQuery(document).ready(function() {
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
							// Unacknowledged problem events
							jQuery('#problem_unack_color')
								.val("<?= $schema['fields']['problem_unack_color']['default'] ?>")
								.change();
							jQuery('#problem_unack_style').prop('checked',
								<?= $schema['fields']['problem_unack_style']['default'] == 0 ? 'false' : 'true' ?>
							);

							// Acknowledged problem events
							jQuery('#problem_ack_color')
								.val("<?= $schema['fields']['problem_ack_color']['default'] ?>")
								.change();
							jQuery('#problem_ack_style').prop('checked',
								<?= $schema['fields']['problem_ack_style']['default'] == 0 ? 'false' : 'true' ?>
							);

							// Unacknowledged ok events
							jQuery('#ok_unack_color')
								.val("<?= $schema['fields']['ok_unack_color']['default'] ?>")
								.change();
							jQuery('#ok_unack_style').prop('checked',
								<?= $schema['fields']['ok_unack_style']['default'] == 0 ? 'false' : 'true' ?>
							);

							// Acknowledged ok events
							jQuery('#ok_ack_color')
								.val("<?= $schema['fields']['ok_ack_color']['default'] ?>")
								.change();
							jQuery('#ok_ack_style').prop('checked',
								<?= $schema['fields']['ok_ack_style']['default'] == 0 ? 'false' : 'true' ?>
							);

							jQuery('#ok_period').val("<?= $schema['fields']['ok_period']['default'] ?>");
							jQuery('#blink_period').val("<?= $schema['fields']['blink_period']['default'] ?>");
						}
					}
				]
			});
		});
	});
</script>
