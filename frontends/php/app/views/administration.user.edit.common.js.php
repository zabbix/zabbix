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

	function autologoutHandler() {
		var	$autologout_visible = jQuery('#autologout_visible'),
			disabled = !$autologout_visible.prop('checked'),
			$autologout = jQuery('#autologout'),
			$hidden = $autologout.prev('input[type=hidden][name="' + $autologout.prop('name') + '"]');

		$autologout.prop('disabled', disabled);

		if (!disabled) {
			$hidden.remove();
		}
		else if (!$hidden.length) {
			jQuery('<input>', {'type': 'hidden', 'name': $autologout.prop('name')})
				.val('0')
				.insertBefore($autologout);
		}
	}

	jQuery(function($) {
		var $autologin_cbx = $('#autologin'),
			$autologout_cbx = $('#autologout_visible');

		$autologin_cbx.on('click', function() {
			if (this.checked) {
				$autologout_cbx.prop('checked', false);
			}
			autologoutHandler();
		});

		$autologout_cbx.on('click', function() {
			if (this.checked) {
				$autologin_cbx.prop('checked', false).change();
			}
			autologoutHandler();
		});
	});

	jQuery(document).ready(function($) {
		autologoutHandler();
	});
</script>
