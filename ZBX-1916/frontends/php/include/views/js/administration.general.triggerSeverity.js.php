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
						'cancel': true,
						'class': '<?= ZBX_STYLE_BTN_ALT ?>',
						'action': function() {}
					},
					{
						'title': '<?= _('Reset defaults') ?>',
						'focused': true,
						'action': function() {
							jQuery('#severity_name_0').val("<?= $schema['fields']['severity_name_0']['default'] ?>");
							jQuery('#severity_name_1').val("<?= $schema['fields']['severity_name_1']['default'] ?>");
							jQuery('#severity_name_2').val("<?= $schema['fields']['severity_name_2']['default'] ?>");
							jQuery('#severity_name_3').val("<?= $schema['fields']['severity_name_3']['default'] ?>");
							jQuery('#severity_name_4').val("<?= $schema['fields']['severity_name_4']['default'] ?>");
							jQuery('#severity_name_5').val("<?= $schema['fields']['severity_name_5']['default'] ?>");
							jQuery('#severity_color_0')
								.val("<?= $schema['fields']['severity_color_0']['default'] ?>")
								.change();
							jQuery('#severity_color_1')
								.val("<?= $schema['fields']['severity_color_1']['default'] ?>")
								.change();
							jQuery('#severity_color_2')
								.val("<?= $schema['fields']['severity_color_2']['default'] ?>")
								.change();
							jQuery('#severity_color_3')
								.val("<?= $schema['fields']['severity_color_3']['default'] ?>")
								.change();
							jQuery('#severity_color_4')
								.val("<?= $schema['fields']['severity_color_4']['default'] ?>")
								.change();
							jQuery('#severity_color_5')
								.val("<?= $schema['fields']['severity_color_5']['default'] ?>")
								.change();
						}
					}
				]
			});
		});
	});
</script>
