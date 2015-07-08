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
			jQuery('#messages_enabled').bind('click', function() {
				if (this.checked && !jQuery('#triggers_row input[type=checkbox]').is(':checked')) {
					jQuery('#triggers_row input[type=checkbox]').prop('checked', true);
				}

				// enable/disable child fields
				jQuery('#messagingTab .input, #messagingTab .button').prop('disabled', !this.checked);
				jQuery('#messages_enabled').prop('disabled', false);
			});

			// initial state: enable/disable child fields
			jQuery('#messagingTab .input, #messagingTab .button').prop('disabled',
				!jQuery('#messages_enabled').is(':checked')
			);
			jQuery('#messages_enabled').prop('disabled', false);
		<?php endif ?>
	});
</script>
