<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<script type="text/x-jquery-tmpl" id="exec_params_row">
	<tr class="form_row">
		<td>
			<input type="text" id="parameters_exec_#{rowNum}_value" name="parameters_exec[#{rowNum}][value]" maxlength="255" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px;">
		</td>
		<td>
			<button type="button" id="parameters_exec_#{rowNum}_remove" name="parameters_exec[#{rowNum}][remove]" class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>
<script type="text/x-jquery-tmpl" id="message-templates-row-tmpl">
	<?= (new CRow([
			new CCol('#{message_type_name}'),
			(new CCol([
				new CSpan('#{message}'),
				new CInput('hidden', 'message_templates[#{message_type}][eventsource]', '#{eventsource}'),
				new CInput('hidden', 'message_templates[#{message_type}][recovery]', '#{recovery}'),
				new CInput('hidden', 'message_templates[#{message_type}][subject]', '#{subject}'),
				new CInput('hidden', 'message_templates[#{message_type}][message]', '#{message}')
			]))
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
				->addStyle('max-width: '.ZBX_TEXTAREA_MEDIUM_WIDTH.'px;'),
			(new CHorList([
				(new CButtonLink(_('Edit')))->setAttribute('data-action', 'edit'),
				(new CButtonLink(_('Remove')))->onClick("removeMessageTemplate('#{message_type}');")
			]))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setAttribute('data-message-type', '#{message_type}')
			->toString()
	?>
</script>
<script type="text/javascript">
	var message_templates = <?= json_encode(CMediatypeHelper::getAllMessageTemplates(), JSON_FORCE_OBJECT) ?>,
		message_template_list = {};

	/**
	 * Draws message template table.
	 *
	 * @param {array} list  An array of message templates.
	 */
	function populateMessageTemplates(list) {
		var row_template = new Template(jQuery('#message-templates-row-tmpl').html());

		for (var i = 0; i < list.length; i++) {
			var template = list[i],
				message_template = getMessageTemplate(template.eventsource, template.recovery);

			template.message_type = message_template.message_type;
			template.message_type_name = message_template.name;

			if ('old_message_type' in template && template.old_message_type != -1) {
				jQuery('tr[data-message-type=' + template.old_message_type + ']')
					.replaceWith(row_template.evaluate(template));

				if (template.message_type != template.old_message_type) {
					delete message_template_list[template.old_message_type];
				}
			}
			else {
				jQuery('#message-templates-footer').before(row_template.evaluate(template));
			}

			message_template_list[template.message_type] = template;
		}

		toggleAddButton();
	}

	/**
	 * Returns message type and name by the specified event source and operation mode.
	 *
	 * @param {number} eventsource  Event source.
	 * @param {number} recovery     Operation mode.
	 *
	 * @return {object}
	 */
	function getMessageTemplate(eventsource, recovery) {
		for (var message_type in message_templates) {
			if (!message_templates.hasOwnProperty(message_type)) {
				continue;
			}

			var template = message_templates[message_type];

			if (template.eventsource == eventsource && template.recovery == recovery) {
				return {
					message_type: message_type,
					name: template.name
				};
			}
		}
	}

	/**
	 * Toggles the "Add" button state and changes its text depending on message template count.
	 */
	function toggleAddButton() {
		var limit_reached = (Object.keys(message_template_list).length == Object.keys(message_templates).length);

		jQuery('#message-templates-footer .btn-link')
			.prop('disabled', limit_reached)
			.text(limit_reached
				? <?= json_encode(_('Add (message type limit reached)')) ?>
				: <?= json_encode(_('Add')) ?>
			);
	}

	/**
	 * Removes a template from the list of message templates.
	 *
	 * @param {number} message_type  Message type.
	 */
	function removeMessageTemplate(message_type) {
		jQuery('tr[data-message-type=' + message_type + ']').remove();
		delete message_template_list[message_type];
		toggleAddButton();
	}

	jQuery(function($) {
		populateMessageTemplates(<?= json_encode(array_values($data['message_templates'])) ?>);

		const providers = <?= json_encode($data['providers']) ?>;

		$('#message-templates').on('click', '[data-action]', function() {
			var $btn = $(this),
				params = {
					type: $('#type').val(),
					content_type: $('input[name="content_type"]:checked').val(),
					message_types: $('tr[data-message-type]').map(function() {
						return $(this).data('message-type');
					}).get()
				};

			switch ($btn.data('action')) {
				case 'add':
					PopUp('popup.mediatype.message', params, {
						dialogue_class: 'modal-popup-medium',
						trigger_element: this
					});
					break;

				case 'edit':
					var $row = $btn.closest('tr');

					params.message_type = $row.data('message-type');
					params.old_message_type = params.message_type;

					$row.find('input[type="hidden"]').each(function() {
						var $input = $(this),
							name = $input.attr('name').match(/\[([^\]]+)]$/);

						if (name) {
							params[name[1]] = $input.val();
						}
					});

					PopUp('popup.mediatype.message', params, {
						dialogue_class: 'modal-popup-medium',
						trigger_element: this
					});
					break;
			}
		});

		var old_media_type = $('#type').val();

		// type of media
		$('#type').change(function() {
			var media_type = $(this).val();

			$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #gsm_modem, #passwd, #smtp_verify_peer, ' +
					'#smtp_verify_host, #smtp_username, #smtp_security, #smtp_authentication, #exec_path, ' +
					'#exec_params_table, #content_type, #provider')
				.closest('li')
				.hide();

			$('li[id^="row_webhook_"]').hide();

			switch (media_type) {
				case '<?= MEDIA_TYPE_EMAIL ?>':
					const provider = $('#provider').val();

					showFormByProvider(provider);
					setMaxSessionsType(media_type);
					break;

				case '<?= MEDIA_TYPE_EXEC ?>':
					$('#exec_path, #exec_params_table').closest('li').show();
					setMaxSessionsType(media_type);
					break;

				case '<?= MEDIA_TYPE_SMS ?>':
					$('#gsm_modem').closest('li').show();
					setMaxSessionsType(media_type);
					break;

				case '<?= MEDIA_TYPE_WEBHOOK ?>':
					$('li[id^="row_webhook_"]').show();
					setMaxSessionsType(media_type);
					break;
			}
		});

		$('#provider').change(function() {
			const provider = $('#provider').val();

			adjustDataByProvider(provider);
			showFormByProvider(provider);
		});

		// clone button
		$('#clone').click(function() {
			$('#mediatypeid, #delete, #clone').remove();
			$('#chPass_btn').hide();
			$('#passwd').prop('disabled', false).show();
			$('#update').text(<?= json_encode(_('Add')) ?>);
			$('#update').val('mediatype.create').attr({id: 'add'});
			$('#name').focus();
		});

		// Trim spaces on sumbit. Spaces for script parameters should not be trimmed.
		$('#media-type-form').submit(function() {
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
				'#name', '#smtp_server', '#smtp_port', '#smtp_helo', '#smtp_email', '#exec_path', '#gsm_modem',
				'#smtp_username', '#maxsessions', 'input[name^="parameters"]', 'input[name="script"]',
				'#event_menu_name', '#event_menu_url'
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

		$('#show_event_menu').change(function() {
			$('#event_menu_url, #event_menu_name').prop('disabled', !$(this).is(':checked'));
		});

		$('#parameters_table').dynamicRows({template: '#parameters_row', allow_empty: true});

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
				if ($('#provider').val() == <?= CMediatypeHelper::EMAIL_PROVIDER_SMTP ?>) {
					$('input[name=passwd]').attr('aria-required', 'false');
					$('label[for=passwd]').removeClass(<?= json_encode(ZBX_STYLE_FIELD_LABEL_ASTERISK) ?>);
					$('#smtp_username, #passwd').closest('li').show();
				}
				else {
					$('input[name=passwd]').attr('aria-required', 'true');
					$('label[for=passwd]').addClass(<?= json_encode(ZBX_STYLE_FIELD_LABEL_ASTERISK) ?>);
					$('#passwd').closest('li').show();
				}
			}
			else {
				$('#smtp_username, #passwd').val('').closest('li').hide();
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
				maxsessions_type.prop('disabled', true).filter('[value=one]').prop('disabled', false);
			}
			else {
				maxsessions_type.prop('disabled', false);
			}

			if (old_media_type != media_type) {
				old_media_type = media_type;
				maxsessions_type.filter('[value=one]').click();
			}
		}

		function showFormByProvider(provider) {
			$('#smtp_server, #smtp_port, #smtp_helo, #smtp_email, #passwd, #smtp_verify_peer, #smtp_verify_host,' +
					'#smtp_username, #smtp_security, #smtp_authentication, #content_type, #provider')
				.closest('li')
				.hide();

			if (provider ==	'<?= CMediatypeHelper::EMAIL_PROVIDER_SMTP ?>' ) {
				$('input[name=passwd]').attr('aria-required', 'false');
				$('label[for=passwd]').removeClass(<?= json_encode(ZBX_STYLE_FIELD_LABEL_ASTERISK) ?>);
				$('label[for=smtp_authentication_1]').text(<?= json_encode(_('Username and password')) ?>);

				$('#smtp_server, #smtp_port, #smtp_helo, #smtp_security, #smtp_authentication, #provider,' +
					' #smtp_email, #content_type')
					.closest('li')
					.show();

				// radio button actions
				toggleSecurityOptions();
				toggleAuthenticationOptions();
			}
			else if (provider == '<?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY ?>'
						|| provider == '<?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY ?>') {
				$('#provider, #smtp_email, #smtp_authentication, #content_type').closest('li').show();
				$('label[for=smtp_authentication_1]').text(<?= json_encode(_('Email and password')) ?>);

				toggleAuthenticationOptions();
			}
			else {
				$('#provider, #smtp_email, #content_type').closest('li').show();

				toggleAuthenticationOptions();
			}
		}

		function adjustDataByProvider(provider) {
			$('input[name=smtp_port]').val(providers[provider]['smtp_port']);
			$('input[name=smtp_email]').val(providers[provider]['smtp_email']);
			$('input[name=smtp_server]').val(providers[provider]['smtp_server']);
			$('input[name=smtp_security]:checked').prop("checked", false);
			$('input[name=smtp_security][value=' + providers[provider]['smtp_security'] + ']').prop("checked", true);
			$('input[name=smtp_verify_host]').val(providers[provider]['smtp_verify_host']);
			$('input[name=smtp_verify_peer]').val(providers[provider]['smtp_verify_peer']);
			$('input[name=smtp_authentication]:checked').prop("checked", false);
			$('input[name=smtp_authentication][value=' + providers[provider]['smtp_authentication'] + ']')
				.prop("checked", true);
			$('input[name=content_type]:checked').prop("checked", false);
			$('input[name=content_type][value=' + providers[provider]['content_type'] + ']').prop("checked", true);
		}

		$('#exec_params_table').dynamicRows({template: '#exec_params_row', allow_empty: true});

		$('#chPass_btn').on('click', function() {
			$(this).hide();
			$('#passwd')
				.show()
				.prop('disabled', false)
				.focus();
		});
	});
</script>
