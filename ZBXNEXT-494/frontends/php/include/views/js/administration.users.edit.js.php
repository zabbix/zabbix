<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('#autologout_visible').bind('click', function() {
			if (this.checked) {
				jQuery('#autologout').prop('disabled', false);
				jQuery('#autologin').attr('checked', false);
			}
			else {
				jQuery('#autologout').prop('disabled', true);
			}
		});
		jQuery('#autologin').bind('click', function() {
			if (this.checked) {
				jQuery('#autologout').prop('disabled', true);
				jQuery('#autologout_visible').attr('checked', false);
			}
		});

		<?php if ($this->data['is_profile']): ?>
			jQuery('#messages_enabled').on('change', function() {
				jQuery('#messagingTab input, #messagingTab button, #messagingTab select').prop('disabled', !this.checked);
				jQuery('#messages_enabled').prop('disabled', false);
			});

			jQuery('#messages_enabled').trigger('change');
		<?php endif ?>
	});
</script>
