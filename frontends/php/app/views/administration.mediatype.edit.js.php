<script type="text/javascript">
	jQuery(document).ready(function() {
		// type of media
		jQuery('#type').change(function() {
			switch (jQuery(this).val()) {
				case '<?php echo MEDIA_TYPE_EMAIL; ?>':
					jQuery('#smtp_server, #smtp_helo, #smtp_email, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #passwd, input[name=smtp_security], input[name=smtp_authentication]').closest('li').show();
					jQuery('#exec_path, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit')
						.closest('li')
						.hide();
					jQuery('#eztext_link').hide();

					// radio buttons actions
					securityOptions();
					authenticationOptions();
					break;

				case '<?php echo MEDIA_TYPE_EXEC; ?>':
					jQuery('#exec_path').closest('li').show();
					jQuery('#smtp_server, #smtp_helo, #smtp_email, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit, #passwd, #smtp_verify_peer, #smtp_verify_host, #smtp_username, input[name=smtp_security], input[name=smtp_authentication]')
						.closest('li')
						.hide();
					jQuery('#eztext_link').hide();
					break;

				case '<?php echo MEDIA_TYPE_SMS; ?>':
					jQuery('#gsm_modem').closest('li').show();
					jQuery('#smtp_server, #smtp_helo, #smtp_email, #exec_path, #jabber_username, #eztext_username, #eztext_limit, #passwd, #smtp_verify_peer, #smtp_verify_host, #smtp_username, input[name=smtp_security], input[name=smtp_authentication]')
						.closest('li')
						.hide();
					jQuery('#eztext_link').hide();
					break;

				case '<?php echo MEDIA_TYPE_JABBER; ?>':
					jQuery('#jabber_username, #passwd').closest('li').show();
					jQuery('#smtp_server, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #eztext_username, #eztext_limit, #smtp_verify_peer, #smtp_verify_host, #smtp_username, input[name=smtp_security], input[name=smtp_authentication]')
						.closest('li')
						.hide();
					jQuery('#eztext_link').hide();
					break;

				case '<?php echo MEDIA_TYPE_EZ_TEXTING; ?>':
					jQuery('#eztext_username, #eztext_limit, #passwd').closest('li').show();
					jQuery('#eztext_link').show();
					jQuery('#smtp_server, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #jabber_username, #smtp_verify_peer, #smtp_verify_host, #smtp_username, input[name=smtp_security], input[name=smtp_authentication]')
						.closest('li')
						.hide();
					break;
			}
		});

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#mediatypeid, #delete, #clone').remove();
			jQuery('#update span').text(<?php echo CJs::encodeJson(_('Add')); ?>);
			jQuery('#update').val('mediatype.create').attr({id: 'add'});
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
			jQuery('#smtp_port').val(jQuery.trim(jQuery('#smtp_port').val()));
			jQuery('#smtp_username').val(jQuery.trim(jQuery('#smtp_username').val()));
		});

		// refresh field visibility on document load
		jQuery('#type').trigger('change');

		jQuery('input[name=smtp_security]').change(function() {
			securityOptions();
		});

		jQuery('input[name=smtp_authentication]').change(function() {
			authenticationOptions();
		});

		function securityOptions() {
			if (jQuery('input[name=smtp_security]:checked').val() == <?= SMTP_CONNECTION_SECURITY_NONE ?>) {
				jQuery('#smtp_verify_peer, #smtp_verify_host').prop('checked', false).closest('li').hide();
			}
			else {
				jQuery('#smtp_verify_peer, #smtp_verify_host').closest('li').show();
			}
		}

		function authenticationOptions() {
			if (jQuery('input[name=smtp_authentication]:checked').val() == <?= SMTP_AUTHENTICATION_NORMAL ?>) {
				jQuery('#smtp_username, #passwd').closest('li').show();
			}
			else {
				jQuery('#smtp_username, #passwd').val('').closest('li').hide();
			}
		}
	});
</script>
