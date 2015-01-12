<script type="text/javascript">
	jQuery(document).ready(function() {
		// proxy mode: active or passive
		jQuery('#status').change(function() {
			var active = (jQuery(this).val() == 5);
			if (active) {
				jQuery('#ip')
					.closest('li')
					.css('display', 'none');
			}
			else {
				jQuery('#ip')
					.closest('li')
					.css('display', '')
					.removeClass('hidden');
			}
		});
		// clone button
		jQuery('#clone').click(function() {
			jQuery('#proxyid, #delete, #clone').remove();
			jQuery('#update span').text(<?php echo CJs::encodeJson(_('Add')); ?>);
			jQuery('#update').val('proxy.create').attr({id: 'add'});
			jQuery('#cancel').addClass('ui-corner-left');
			jQuery('#host').focus();
		});
	});
</script>
