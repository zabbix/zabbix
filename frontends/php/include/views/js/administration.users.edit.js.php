<script type="text/javascript">
	function removeMedia(index) {
		// table row
		jQuery('#user_medias_' + index).remove();
		// hidden variables
		jQuery('#user_medias_' + index + '_mediaid').remove();
		jQuery('#user_medias_' + index + '_mediatype').remove();
		jQuery('#user_medias_' + index + '_mediatypeid').remove();
		jQuery('#user_medias_' + index + '_period').remove();
		jQuery('#user_medias_' + index + '_sendto').remove();
		removeVarsBySelector(null, 'input[id^="user_medias_' + index + '_sendto_"]');
		jQuery('#user_medias_' + index + '_severity').remove();
		jQuery('#user_medias_' + index + '_active').remove();
		jQuery('#user_medias_' + index + '_description').remove();
	}

	jQuery(document).ready(function() {
		var autologout_cbx = document.getElementById('autologout_visible'),
			autologin_cbx = document.getElementById('autologin'),
			autologout_txt = document.getElementById('autologout');

		jQuery(autologin_cbx).bind('change', function() {
			if (this.checked) {
				autologout_cbx.checked = false;
			}
			autologout_txt.disabled = (this.checked || !autologin_cbx.checked);
		});

		jQuery(autologout_cbx).bind('change', function() {
			if (this.checked) {
				autologin_cbx.checked = false;
			}
			autologout_txt.disabled = !this.checked;
		});

		<?php if ($this->data['is_profile']): ?>
			jQuery('#messages_enabled').on('change', function() {
				jQuery('#messagingTab input, #messagingTab button, #messagingTab select')
					.not('[name="messages[enabled]"]')
					.prop('disabled', !this.checked);
			});

			jQuery('#messages_enabled').trigger('change');
		<?php endif ?>
	});
</script>
