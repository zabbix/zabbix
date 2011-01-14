<div id="scriptDialog" style="display:none; white-space: normal;"></div>

<script type="text/javascript">

function showScriptDialog(confirmation, buttons){
	jQuery('#scriptDialog').text(confirmation);
	var w = jQuery('#scriptDialog').outerWidth()+20;

	jQuery('#scriptDialog').dialog({
		buttons: buttons,
		draggable: false,
		modal: true,
		width: (w > 600 ? 600 : 'inherit'),
		resizable: false,
		minWidth: 200,
		minHeight: 100,
		title: '<?php echo _('Execution confirmation');?>',
		close: function(){ jQuery(this).dialog('destroy'); }
	});

	return jQuery('#scriptDialog').dialog('widget');

}

function executeScript(hostid, scriptid, confirmation){
	var execute = function(){
		openWinCentered('scripts_exec.php?execute=1&hostid='+hostid+'&scriptid='+scriptid, 'Tools', 760, 540, "titlebar=no, resizable=yes, scrollbars=yes, dialog=no");
	};

	if(confirmation == ''){
		execute();
	}
	else{
		var buttons = [
			{text: '<?php echo _('Execute');?>', click: function(){
				jQuery(this).dialog("destroy");
				execute();
			}},
			{text: '<?php echo _('Cancel');?>', click: function(){
				jQuery(this).dialog("destroy");
			}}
		];
		var d = showScriptDialog(confirmation, buttons);

		jQuery(d).find('button:first').css('border-color', '#FA3');
	}
}

</script>
