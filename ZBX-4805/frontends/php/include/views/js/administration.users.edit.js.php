<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('#autologout_visible').bind('click', function() {
			if (this.checked) {
				jQuery('#autologout').removeAttr('disabled');
				jQuery('#autologin').attr('checked', false);
			}
			else {
				jQuery('#autologout').attr('disabled', 'disabled');
			}
		});
		jQuery('#autologin').bind('click', function() {
			if (this.checked) {
				jQuery('#autologout').attr('disabled', 'disabled');
				jQuery('#autologout_visible').attr('checked', false);
			}
		});
	});
</script>
