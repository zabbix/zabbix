<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


ob_start(); ?>

/**
 * Returns a message template object with message subject and body.
 *
 * @param {int|string} message_type  Message type.
 *
 * @return {object}
 */
function getMessageTemplate(message_type) {
	var media_type = jQuery('#type').val(),
		message_format = jQuery('input[name="content_type"]:checked').val();

	if (media_type == <?= MEDIA_TYPE_SMS ?>) {
		return {
			message: message_templates[message_type]['template']['sms']
		};
	}

	if (media_type == <?= MEDIA_TYPE_EMAIL ?> && message_format == <?= SMTP_MESSAGE_FORMAT_HTML ?>) {
		return {
			subject: message_templates[message_type]['template']['subject'],
			message: message_templates[message_type]['template']['html']
		};
	}

	return {
		subject: message_templates[message_type]['template']['subject'],
		message: message_templates[message_type]['template']['text']
	};
}

/**
 * Check whether a message template of the same type is already present in the list of message templates.
 *
 * @param {object} template  Message template.
 *
 * @return {boolean}
 */
function isDuplicateMessageTemplate(template) {
	for (var i in message_template_list) {
		if (!message_template_list.hasOwnProperty(i)) {
			continue;
		}

		if (template.index != i && template.eventsource == message_template_list[i].eventsource
				&& template.recovery == message_template_list[i].recovery) {
			return true;
		}
	}

	return false;
}

/**
 * Sends message template form data to the server for validation before adding it to the main form.
 */
function submitMessageTemplate() {
	var $form = jQuery(document.forms['mediatype_message_form']);

	return sendAjaxData('zabbix.php', {
		data: $form.serialize(),
		dataType: 'json',
		method: 'POST'
	}).done(function(response) {
		$form.parent().find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

		if (typeof response.errors !== 'undefined') {
			return jQuery(response.errors).insertBefore($form);
		}
		else {
			var template = response.params;

			if (isDuplicateMessageTemplate(template)) {
				jQuery(makeMessageBox('bad', <?= CJs::encodeJson(_('Message template already exists.')) ?>, null, true,
					false)
				).insertBefore($form);

				return false;
			}

			populateMessageTemplates([template]);

			overlayDialogueDestroy($form.closest('[data-dialogueid]').data('dialogueid'));
		}
	});
}

jQuery('#message_type').on('change', function() {
	var message_template = getMessageTemplate(jQuery(this).val());

	jQuery('#subject').val(message_template.subject);
	jQuery('#message').val(message_template.message);
});

<?php return ob_get_clean(); ?>
