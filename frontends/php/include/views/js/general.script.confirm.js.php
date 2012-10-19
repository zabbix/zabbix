<div id="scriptDialog" style="display: none; white-space: normal;"></div>

<script type="text/javascript">
	function showScriptDialog(confirmation, buttons) {
		jQuery('#scriptDialog').text(confirmation);

		var width = jQuery('#scriptDialog').outerWidth() + 20;

		jQuery('#scriptDialog').dialog({
			buttons: buttons,
			draggable: false,
			modal: true,
			width: (width > 600 ? 600 : 'inherit'),
			resizable: false,
			minWidth: 200,
			minHeight: 100,
			title: <?php echo CJs::encodeJson(CJs::encodeJson(_('Execution confirmation'))); ?>,
			close: function() {
				jQuery(this).dialog('destroy');
			}
		});

		return jQuery('#scriptDialog').dialog('widget');
	}

	function executeScript(hostid, scriptid, confirmation) {
		var execute = function() {
			openWinCentered('scripts_exec.php?execute=1&hostid=' + hostid + '&scriptid=' + scriptid, 'Tools', 560, 470,
				'titlebar=no, resizable=yes, scrollbars=yes, dialog=no'
			);
		};

		if (confirmation == '') {
			execute();
		}
		else {
			var buttons = [
				{text: <?php echo CJs::encodeJson(_('Execute')); ?>, click: function() {
					jQuery(this).dialog('destroy');
					execute();
				}},
				{text: <?php echo CJs::encodeJson(_('Cancel')); ?>, click: function() {
					jQuery(this).dialog('destroy');
				}}
			];

			var dialog = showScriptDialog(confirmation, buttons);
			jQuery(dialog).find('button:first').addClass('main');
		}
	}
</script>
