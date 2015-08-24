<?php
$schema = DB::getSchema('config');
?>
<div id="dialog" style="display:none; white-space: normal;"></div>

<script type="text/javascript">

	jQuery(document).ready(function(){

		jQuery("#resetDefaults").click(function(){

			jQuery('#dialog').text(<?php echo CJs::encodeJson(_('Reset all names and colours to default values?')); ?>);
			var w = jQuery('#dialog').outerWidth()+20;

			jQuery('#dialog').dialog({
				buttons: [
					{text: <?php echo CJs::encodeJson(_('Reset defaults')); ?>, click: function(){
						jQuery('#severity_name_0').val("<?php echo $schema['fields']['severity_name_0']['default']; ?>");
						jQuery('#severity_name_1').val("<?php echo $schema['fields']['severity_name_1']['default']; ?>");
						jQuery('#severity_name_2').val("<?php echo $schema['fields']['severity_name_2']['default']; ?>");
						jQuery('#severity_name_3').val("<?php echo $schema['fields']['severity_name_3']['default']; ?>");
						jQuery('#severity_name_4').val("<?php echo $schema['fields']['severity_name_4']['default']; ?>");
						jQuery('#severity_name_5').val("<?php echo $schema['fields']['severity_name_5']['default']; ?>");
						jQuery('#severity_color_0').val("<?php echo $schema['fields']['severity_color_0']['default']; ?>");
						jQuery('#severity_color_0').change();
						jQuery('#severity_color_1').val("<?php echo $schema['fields']['severity_color_1']['default']; ?>");
						jQuery('#severity_color_1').change();
						jQuery('#severity_color_2').val("<?php echo $schema['fields']['severity_color_2']['default']; ?>");
						jQuery('#severity_color_2').change();
						jQuery('#severity_color_3').val("<?php echo $schema['fields']['severity_color_3']['default']; ?>");
						jQuery('#severity_color_3').change();
						jQuery('#severity_color_4').val("<?php echo $schema['fields']['severity_color_4']['default']; ?>");
						jQuery('#severity_color_4').change();
						jQuery('#severity_color_5').val("<?php echo $schema['fields']['severity_color_5']['default']; ?>");
						jQuery('#severity_color_5').change();
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
		});
	});

</script>
