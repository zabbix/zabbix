<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

/**
 * Returns a default message template with message subject and body.
 *
 * @param {number} message_type  Message type.
 *
 * @return {object}
 */
function getDefaultMessageTemplate(message_type) {
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
 * Sends message template form data to the server for validation before adding it to the main form.
 *
 * @param {Overlay} overlay
 */
function submitMessageTemplate(overlay) {
	var $form = overlay.$dialogue.find('form');
	overlay.setLoading();

	overlay.xhr = sendAjaxData('zabbix.php', {
		data: $form.serialize(),
		dataType: 'json',
		method: 'POST',
		success: function(response) {
			overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

			if ('errors' in response) {
				jQuery(response.errors).insertBefore($form);
			}
			else {
				populateMessageTemplates([response.params]);
				overlayDialogueDestroy(overlay.dialogueid);
			}
		}
	});

	overlay.xhr.always(function() {
		overlay.unsetLoading();
	});
}

jQuery('#message_type').on('change', function() {
	var message_template = getDefaultMessageTemplate(jQuery(this).val());

	jQuery('#subject').val(message_template.subject);
	jQuery('#message').val(message_template.message);
});
