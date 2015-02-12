<script type="text/javascript">
	jQuery(document).ready(function() {
		// type of media
		jQuery('#type').change(function() {
			switch (jQuery(this).val()) {
				case '<?php echo MEDIA_TYPE_EMAIL; ?>':
					jQuery('#smtp_server, #smtp_helo, #smtp_email').closest('li').removeClass('hidden');
					jQuery('#exec_path, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit, #passwd')
						.closest('li')
						.addClass('hidden');
					jQuery('#eztext_link').addClass('hidden');
					break;

				case '<?php echo MEDIA_TYPE_EXEC; ?>':
					jQuery('#exec_path').closest('li').removeClass('hidden');
					jQuery('#smtp_server, #smtp_helo, #smtp_email, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit, #passwd')
						.closest('li')
						.addClass('hidden');
					jQuery('#eztext_link').addClass('hidden');
					break;

				case '<?php echo MEDIA_TYPE_SMS; ?>':
					jQuery('#gsm_modem').closest('li').removeClass('hidden');
					jQuery('#smtp_server, #smtp_helo, #smtp_email, #exec_path, #jabber_username, #eztext_username, #eztext_limit, #passwd')
						.closest('li')
						.addClass('hidden');
					jQuery('#eztext_link').addClass('hidden');
					break;

				case '<?php echo MEDIA_TYPE_JABBER; ?>':
					jQuery('#jabber_username, #passwd').closest('li').removeClass('hidden');
					jQuery('#smtp_server, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #eztext_username, #eztext_limit')
						.closest('li')
						.addClass('hidden');
					jQuery('#eztext_link').addClass('hidden');
					break;

				case '<?php echo MEDIA_TYPE_EZ_TEXTING; ?>':
					jQuery('#eztext_username, #eztext_limit, #passwd').closest('li').removeClass('hidden');
					jQuery('#eztext_link').removeClass('hidden');
					jQuery('#smtp_server, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #jabber_username')
						.closest('li')
						.addClass('hidden');
					break;
			}
		});

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#mediatypeid, #delete, #clone').remove();
			jQuery('#update span').text(<?php echo CJs::encodeJson(_('Add')); ?>);
			jQuery('#update').val('mediatype.create').attr({id: 'add'});
			jQuery('#cancel').addClass('ui-corner-left');
			jQuery('#description').focus();
		});

		// trim spaces on sumbit
		jQuery('#mediaTypeForm').submit(function() {
			jQuery('#description').val(jQuery.trim(jQuery('#description').val()));
			jQuery('#smtp_server').val(jQuery.trim(jQuery('#smtp_server').val()));
			jQuery('#smtp_helo').val(jQuery.trim(jQuery('#smtp_helo').val()));
			jQuery('#smtp_email').val(jQuery.trim(jQuery('#smtp_email').val()));
			jQuery('#exec_path').val(jQuery.trim(jQuery('#exec_path').val()));
			jQuery('#gsm_modem').val(jQuery.trim(jQuery('#gsm_modem').val()));
			jQuery('#jabber_username').val(jQuery.trim(jQuery('#jabber_username').val()));
			jQuery('#eztext_username').val(jQuery.trim(jQuery('#eztext_username').val()));
		});

		// refresh field visibility on document load
		jQuery('#type').trigger('change');
	});
</script>
