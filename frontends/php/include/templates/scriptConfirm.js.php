<div id="scriptDialog" style="display:none; white-space: normal;"></div>

<script type="text/javascript">
function executeScript(hostid, scriptid, question){
	var execute = function(){
		openWinCentered('scripts_exec.php?execute=1&hostid='+hostid+'&scriptid='+scriptid, 'Tools', 760, 540, "titlebar=no, resizable=yes, scrollbars=yes, dialog=no");
	};

	if(question == ''){
		execute();
	}
	else{
		jQuery('#scriptDialog').html(question);
		var w = jQuery('#scriptDialog').outerWidth()+20;

		jQuery('#scriptDialog').dialog({
			buttons: [
				{text: '<?php echo _('Execute');?>', click: function(){
					jQuery(this).dialog("destroy");
					execute();
				}},
				{text: '<?php echo _('Cancel');?>', click: function(){
					jQuery(this).dialog("destroy");
				}}
			],
			draggable: false,
			modal: true,
			width: (w > 600 ? 600 : w),
			resizable: false,
			minWidth: 200,
			minHeight: 100,
			title: '<?php echo _('Execution confirmation');?>'
		});
	}
}

</script>
