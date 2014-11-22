<?php
$schema = DB::getSchema('config');
?>
<div id="dialog" style="display:none; white-space: normal;"></div>

<script type="text/javascript">

	jQuery(document).ready(function(){

		jQuery("#resetDefaults").click(function(){

			jQuery('#dialog').text(<?php echo CJs::encodeJson(_('Reset all fields to default values?')); ?>);
			var w = jQuery('#dialog').outerWidth()+20;

			jQuery('#dialog').dialog({
				buttons: [
					{text: <?php echo CJs::encodeJson(_('Reset defaults')); ?>, click: function(){
						// Unacknowledged problem events
						jQuery('#problem_unack_color').val("<?php echo $schema['fields']['problem_unack_color']['default']; ?>");
						jQuery('#problem_unack_color').change();
						jQuery('#problem_unack_style').prop(
								'checked',
								<?php echo $schema['fields']['problem_unack_style']['default'] == 0 ? 'false' : 'true'; ?>
						);

						// Acknowledged problem events
						jQuery('#problem_ack_color').val("<?php echo $schema['fields']['problem_ack_color']['default']; ?>");
						jQuery('#problem_ack_color').change();
						jQuery('#problem_ack_style').prop(
								'checked',
								<?php echo $schema['fields']['problem_ack_style']['default'] == 0 ? 'false' : 'true'; ?>
						);

						// Unacknowledged ok events
						jQuery('#ok_unack_color').val("<?php echo $schema['fields']['ok_unack_color']['default']; ?>");
						jQuery('#ok_unack_color').change();
						jQuery('#ok_unack_style').prop(
								'checked',
								<?php echo $schema['fields']['ok_unack_style']['default'] == 0 ? 'false' : 'true'; ?>
						);

						// Acknowledged ok events
						jQuery('#ok_ack_color').val("<?php echo $schema['fields']['ok_ack_color']['default']; ?>");
						jQuery('#ok_ack_color').change();
						jQuery('#ok_ack_style').prop(
								'checked',
								<?php echo $schema['fields']['ok_ack_style']['default'] == 0 ? 'false' : 'true'; ?>
						);

						jQuery('#ok_period').val("<?php echo $schema['fields']['ok_period']['default']; ?>");
						jQuery('#blink_period').val("<?php echo $schema['fields']['blink_period']['default']; ?>");

						jQuery(this).dialog("destroy");
					} },
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

			jQuery('#dialog').dialog('widget').find('.ui-dialog-buttonset .ui-button:first').addClass('main');
		});
	});

</script>
