<script type="text/x-jquery-tmpl" id="exec_params_row">
	<tr class="form_row">
		<td>
			<input type="text" id="exec_params_#{rowNum}_exec_param" name="exec_params[#{rowNum}][exec_param]" maxlength="255" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px;">
		</td>
		<td>
			<button type="button" id="exec_params_#{rowNum}_remove" name="exec_params[#{rowNum}][remove]" class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		// type of media
		$('#type').change(function() {
			switch ($(this).val()) {
				case '<?= MEDIA_TYPE_EMAIL ?>':
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #smtp_security, #smtp_authentication').closest('li').show();
					$('#exec_path, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit, #exec_params_table, #remedy_url, #remedy_username, #remedy_proxy, #remedy_company, #remedy_mapping')
						.closest('li')
						.hide();
					$('#eztext_link').hide();

					// radio button actions
					toggleSecurityOptions();
					toggleAuthenticationOptions();
					break;

				case '<?= MEDIA_TYPE_EXEC ?>':
					$('#exec_path, #exec_params_table').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit, #passwd, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #remedy_url, #remedy_username, #remedy_proxy, #remedy_company, #remedy_mapping')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					break;

				case '<?= MEDIA_TYPE_SMS ?>':
					$('#gsm_modem').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #jabber_username, #eztext_username, #eztext_limit, #passwd, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_params_table, #remedy_url, #remedy_username, #remedy_proxy, #remedy_company, #remedy_mapping')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					break;

				case '<?= MEDIA_TYPE_JABBER ?>':
					$('#jabber_username, #passwd').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #eztext_username, #eztext_limit, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_params_table, #remedy_url, #remedy_username, #remedy_proxy, #remedy_company, #remedy_mapping')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					break;

				case '<?= MEDIA_TYPE_EZ_TEXTING ?>':
					$('#eztext_username, #eztext_limit, #passwd').closest('li').show();
					$('#eztext_link').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #jabber_username, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_params_table, #remedy_url, #remedy_username, #remedy_proxy, #remedy_company, #remedy_mapping')
						.closest('li')
						.hide();
					break;

				case '<?= MEDIA_TYPE_REMEDY ?>':
					$('#remedy_url, #remedy_username, #passwd, #remedy_proxy, #remedy_company, #remedy_mapping')
						.closest('li')
						.show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #jabber_username, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_params_table, #eztext_username, #eztext_limit')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
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

		// Trim spaces on sumbit. Spaces for script parameters should not be trimmed.
		$('#media_type_form').submit(function() {
			$(this).trimValues([
				'#description', '#smtp_server', '#smtp_port', '#smtp_helo', '#smtp_email', '#exec_path', '#gsm_modem',
				'#jabber_username', '#eztext_username', '#smtp_username',
				'#remedy_url', '#remedy_proxy', '#remedy_company', '#remedy_mapping'
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

		// When adding and removing dynamic rows, store counter in hidden field.
		$('#exec_params_table').dynamicRows({
			template: '#exec_params_row',
			dataCallback: function() {
				$('#exec_params_count').val(parseInt($('#exec_params_count').val()) + 1);
			}
		});

		$('#exec_params_table').on('click', 'button.element-table-remove', function() {
			$('#exec_params_count').val($('#exec_params_table .form_row input[type="text"]').length);
		});
	});
</script>
