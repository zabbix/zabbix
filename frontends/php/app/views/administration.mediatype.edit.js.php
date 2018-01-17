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
		var old_media_type = $('#type').val();

		// type of media
		$('#type').change(function() {
			var media_type = $(this).val();

			switch (media_type) {
				case '<?= MEDIA_TYPE_EMAIL ?>':
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #smtp_security, #smtp_authentication, #smtp_passwd')
						.closest('li')
						.show();
					$('#exec_path, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit, #exec_params_table, #passwd')
						.closest('li')
						.hide();
					$('#eztext_link').hide();

					// radio button actions
					toggleSecurityOptions();
					toggleAuthenticationOptions();
					setMaxSessionsType(media_type);
					break;

				case '<?= MEDIA_TYPE_EXEC ?>':
					$('#exec_path, #exec_params_table').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #gsm_modem, #jabber_username, #eztext_username, #eztext_limit, #passwd, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #smtp_passwd')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					setMaxSessionsType(media_type);
					break;

				case '<?= MEDIA_TYPE_SMS ?>':
					$('#gsm_modem').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #jabber_username, #eztext_username, #eztext_limit, #passwd, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_params_table, #smtp_passwd')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					setMaxSessionsType(media_type);
					break;

				case '<?= MEDIA_TYPE_JABBER ?>':
					$('#jabber_username, #passwd').closest('li').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #eztext_username, #eztext_limit, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_params_table, #smtp_passwd')
						.closest('li')
						.hide();
					$('#eztext_link').hide();
					setMaxSessionsType(media_type);
					break;

				case '<?= MEDIA_TYPE_EZ_TEXTING ?>':
					$('#eztext_username, #eztext_limit, #passwd').closest('li').show();
					$('#eztext_link').show();
					$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #exec_path, #gsm_modem, #jabber_username, #smtp_verify_peer, #smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_params_table, #smtp_passwd')
						.closest('li')
						.hide();
					setMaxSessionsType(media_type);
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
			var maxattempts = $('#maxattempts'),
				maxsessions_type = $('#maxsessions_type :radio:checked').val(),
				maxsessions = $('#maxsessions');

			if ($.trim(maxattempts.val()) === '') {
				maxattempts.val(0);
			}

			if (maxsessions_type !== 'custom') {
				maxsessions.val(maxsessions_type === 'one' ? 1 : 0);
			}
			else if (maxsessions_type === 'custom' && $.trim(maxsessions.val()) === '') {
				maxsessions.val(0);
			}

			$(this).trimValues([
				'#description', '#smtp_server', '#smtp_port', '#smtp_helo', '#smtp_email', '#exec_path', '#gsm_modem',
				'#jabber_username', '#eztext_username', '#smtp_username', '#maxsessions'
			]);
		});

		$('#maxsessions_type :radio').change(function() {
			toggleMaxSessionsVisibility($(this).val());
		});

		// Refresh field visibility on document load.
		$('#type').trigger('change');
		$('#maxsessions_type :radio:checked').trigger('change');

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
				$('#smtp_username, #smtp_passwd').closest('li').show();
			}
			else {
				$('#smtp_username, #smtp_passwd').val('').closest('li').hide();
			}
		}

		/**
		 * Show or hide concurrent sessions custom input box.
		 *
		 * @param {string} maxsessions_type		Selected concurrent sessions value. One of 'one', 'unlimited', 'custom'.
		 */
		function toggleMaxSessionsVisibility(maxsessions_type) {
			var maxsessions = $('#maxsessions');

			if (maxsessions_type === 'one' || maxsessions_type === 'unlimited') {
				maxsessions.hide();
			}
			else {
				maxsessions.show().select().focus();
			}
		}

		/**
		 * Set concurrent sessions accessibility.
		 *
		 * @param {number} media_type		Selected media type.
		 */
		function setMaxSessionsType(media_type) {
			var maxsessions_type = $('#maxsessions_type :radio');

			if (media_type == <?= MEDIA_TYPE_SMS ?>) {
				maxsessions_type.attr('disabled', true).filter('[value=one]').attr('disabled', false);
			}
			else {
				maxsessions_type.attr('disabled', false);
			}

			if (old_media_type != media_type) {
				old_media_type = media_type;
				maxsessions_type.filter('[value=one]').click();
			}
		}

		$('#exec_params_table').dynamicRows({ template: '#exec_params_row' });
	});
</script>
