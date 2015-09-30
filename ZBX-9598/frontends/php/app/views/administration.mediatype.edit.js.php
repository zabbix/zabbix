<script type="text/javascript">
	jQuery(document).ready(function($) {
		// type of media
		$('#type').change(function() {
			switch ($(this).val()) {
				case '<?= MEDIA_TYPE_EMAIL ?>':
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #smtp_security, #smtp_authentication').closest('li').show();
					$('#exec_path, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit')
						.closest('li')
						.hide();
					$('#eztext_link').hide();

					// radio button actions
					toggleSecurityOptions();
					toggleAuthenticationOptions();
					break;

				case '<?= MEDIA_TYPE_EXEC ?>':
					$('#exec_path').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit, #passwd, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					break;

				case '<?= MEDIA_TYPE_SMS ?>':
					$('#gsm_modem').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #jabber_username, #eztext_username, #eztext_limit, #passwd, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					break;

				case '<?= MEDIA_TYPE_JABBER ?>':
					$('#jabber_username, #passwd').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #eztext_username, #eztext_limit, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					break;

				case '<?= MEDIA_TYPE_EZ_TEXTING ?>':
					$('#eztext_username, #eztext_limit, #passwd').closest('li').show();
					$('#eztext_link').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #jabber_username, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication')
						.closest('li')
						.hide();
					break;
			}
		});

		// clone button
		$('#clone').click(function() {
			$('#mediatypeid, #delete, #clone').remove();
			$('#update').text(<?= CJs::encodeJson(_('Add')) ?>);
			$('#update').val('mediatype.create').attr({id: 'add'});
			$('#description').focus();
		});

		$('#mediaTypeForm').submit(function() {
			$(this).trimValues([
				'#description', '#smtp_server', '#smtp_port', '#smtp_helo', '#smtp_email', '#exec_path', '#gsm_modem',
				'#jabber_username', '#eztext_username', '#smtp_username'
			]);
		});

		// Refresh field visibility on document load.
		$('#type').trigger('change');

		$('input[name=smtp_security]').change(function() {
			toggleSecurityOptions();
		});

		$('input[name=smtp_authentication]').change(function() {
			toggleAuthenticationOptions();
		});

		/**
		 * Show or hide "SSL verify peer" and "SSL verify host" fields.
		 */
		function toggleSecurityOptions() {
			if ($('input[name=smtp_security]:checked').val() == <?= SMTP_CONNECTION_SECURITY_NONE ?>) {
				$('#smtp_verify_peer, #smtp_verify_host').prop('checked', false).closest('li').hide();
			}
			else {
				$('#smtp_verify_peer, #smtp_verify_host').closest('li').show();
			}
		}

		/**
		 * Show or hide "Username" and "Password" fields.
		 */
		function toggleAuthenticationOptions() {
			if ($('input[name=smtp_authentication]:checked').val() == <?= SMTP_AUTHENTICATION_NORMAL ?>) {
				$('#smtp_username, #passwd').closest('li').show();
			}
			else {
				$('#smtp_username, #passwd').val('').closest('li').hide();
			}
		}
	});
</script>
