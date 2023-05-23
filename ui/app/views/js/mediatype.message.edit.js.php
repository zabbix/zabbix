<?php declare(strict_types = 0);
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

window.mediatype_message_popup = new class {

	init() {
		this.overlay = overlays_stack.getById('mediatype-message-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this._initActions();
	}

	_initActions() {
		this.form.querySelector('#message_type').onchange = (e) => {
			const message_template = this._getDefaultMessageTemplate(e.target.value);

			this.form.querySelector('#subject').value = message_template.subject;
			this.form.querySelector('#message').value = message_template.message;
		};
	}

	_getDefaultMessageTemplate(message_type) {
		const message_templates = <?= json_encode(CMediatypeHelper::getAllMessageTemplates(), JSON_FORCE_OBJECT) ?>;
		const media_type = this.form.querySelector('#type').value;
		const message_format = document.querySelector(`input[name='content_type']:checked`). value;

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

	submit() {
		const curl = new Curl('zabbix.php');
		const fields = getFormFields(this.form);

		curl.setArgument('action', 'mediatype.message.check');
		this._post(curl.getUrl(), fields);
	}

	_post(url, data) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				this.dialogue.dispatchEvent(new CustomEvent('message.submit', {detail: response}));
				overlayDialogueDestroy(this.overlay.dialogueid);
			})
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];
				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
}
