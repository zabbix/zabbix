<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery(":submit").button();
		jQuery("#agree").click(function(){
			if (this.buttonEnabled) {
				jQuery("#next_1").button("disable");
				delete this.buttonEnabled;
			}
			else {
				jQuery("#next_1").button("enable");
				this.buttonEnabled = true;
			}
		})
	});
</script>
