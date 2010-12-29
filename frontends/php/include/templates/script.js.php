<script type="text/javascript">

jQuery(document).ready(function(){

	jQuery("#name").focus();

// question field typing
	jQuery("#question").keyup(function(){
		if(this.value != ''){
			jQuery("#testQuestion").removeAttr("disabled");
		}
		else{
			jQuery("#testQuestion").attr("disabled", "disabled");
		}
	}).keyup();

// checkbox changing
	jQuery("#enableQuestion").change(function(){
		if(this.checked){
			jQuery("#question").removeAttr("disabled");
			jQuery("#question").keyup();
		}
		else{
			jQuery("#question").attr("disabled", "disabled");
			jQuery("#testQuestion").attr("disabled", "disabled");
		}
	}).change();


	jQuery("#testQuestion").click(function(){
		if(this.getAttribute('disabled')) return false;

		var question = jQuery('#question').val();

		var buttons = [
			{text: '<?php echo _('Execute');?>', click: function(){} },
			{text: '<?php echo _('Cancel');?>', click: function(){
				jQuery(this).dialog("destroy");
			}}
		];

		var d = showScriptDialog(question, buttons);
		jQuery(d).find('button:first span').attr('disabled', 'disabled').addClass('ui-state-disabled');
	});

});

</script>
