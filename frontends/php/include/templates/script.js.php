<script type="text/javascript">

jQuery(document).ready(function(){

	jQuery("#name").focus();

// confirmation field typing
	jQuery("#confirmation").keyup(function(){
		if(this.value != ''){
			jQuery("#testConfirmation").removeAttr("disabled");
			jQuery("#confirmationLabel").removeAttr("disabled");
		}
		else{
			jQuery("#testConfirmation").attr("disabled", "disabled");
			jQuery("#confirmationLabel").attr("disabled", "disabled");
		}
	}).keyup();

// checkbox changing
	jQuery("#enableConfirmation").change(function(){
		if(this.checked){
			jQuery("#confirmation").removeAttr("disabled");
			jQuery("#confirmation").keyup();
		}
		else{
			jQuery("#confirmation").attr("disabled", "disabled");
			jQuery("#testConfirmation").attr("disabled", "disabled");
			jQuery("#confirmationLabel").attr("disabled", "disabled");
		}
	}).change();


	jQuery("#testConfirmation").click(function(){
		if(this.getAttribute('disabled')) return false;

		var confirmation = jQuery('#confirmation').val();

		var buttons = [
			{text: '<?php echo _('Execute');?>', click: function(){} },
			{text: '<?php echo _('Cancel');?>', click: function(){
				jQuery(this).dialog("destroy");
			}}
		];

		var d = showScriptDialog(confirmation, buttons);
		jQuery(d).find('button:first').attr('disabled', 'disabled').addClass('ui-state-disabled');
		jQuery(d).find('button:last').focus();
	});

});

</script>
