<script type="text/javascript">

jQuery(document).ready(function(){

	jQuery("#name").focus();

// Type change
	jQuery("#type").change(function(){
		var ipmi = jQuery(this).val() == '1';

		jQuery("#execute_on").closest('li').toggle(!ipmi);

		if(ipmi){
			jQuery("#commandipmi")
					.val(jQuery("#command").val())
					.closest('li')
					.toggle(true);
			jQuery("#command")
					.closest('li')
					.toggle(false);
		}
		else{
			jQuery("#command")
					.val(jQuery("#commandipmi").val())
					.closest('li')
					.toggle(true);
			jQuery("#commandipmi")
					.closest('li')
					.toggle(false);
		}
	});

// Clone button
	jQuery("#clone").click(function(){
		jQuery("#scriptid, #delete, #clone").remove();

		jQuery("#cancel").addClass('ui-corner-left');
		jQuery("#name").focus();
	});

// Confirmation text input
	jQuery("#confirmation").keyup(function(){
		if(this.value != ''){
			jQuery("#testConfirmation, #confirmationLabel").prop("disabled", false);
		}
		else{
			jQuery("#testConfirmation, #confirmationLabel").prop("disabled", true);
		}
	}).keyup();

// Enable confirmation checkbox
	jQuery("#enableConfirmation").change(function(){
		if(this.checked){
			jQuery("#confirmation").removeAttr("disabled").keyup();
		}
		else{
			jQuery("#confirmation, #testConfirmation, #confirmationLabel").prop("disabled", true);
		}
	}).change();

// Test confirmation button
	jQuery("#testConfirmation").click(function(){
		var confirmation = jQuery('#confirmation').val();

		var buttons = [
			{text: <?php echo CJs::encodeJson(_('Execute')); ?>, click: function(){} },
			{text: <?php echo CJs::encodeJson(_('Cancel')); ?>, click: function(){
				jQuery(this).dialog("destroy");
				jQuery("#testConfirmation").focus();
			}}
		];

		var d = showScriptDialog(confirmation, buttons);
		jQuery(d).find('button:first').prop('disabled', true).addClass('ui-state-disabled');
		jQuery(d).find('button:last').focus();
	});

});

</script>
