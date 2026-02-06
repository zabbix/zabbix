<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 */
?>

window.mediatype_message_popup = new class {

	init({rules, message_templates}) {
		this.overlay = overlays_stack.getById('mediatype-message-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.message_templates = Object.fromEntries(
			message_templates.map((obj, index) => [index, { ...obj }])
		);

		this.#initEvents();
	}

	#initEvents() {
		this.form_element.querySelector('#message_type').onchange = (e) => {
			const message_template = this.#getDefaultMessageTemplate(e.target.value);

			if (this.form_element.querySelector('#subject') !== null) {
				this.form_element.querySelector('#subject').value = message_template.subject;
			}

			this.form_element.querySelector('#message').value = message_template.message;
		};

		this.overlay.$dialogue.$footer[0].querySelector('.js-submit')
			.addEventListener('click', () => this.#submit());
	}

	/**
	 * Retrieves the default message template based on the specified message_type.
	 *
	 * @param {string} message_type  Message type value.
	 *
	 * @return {object}
	 */
	#getDefaultMessageTemplate(message_type) {
		const message_templates = this.message_templates;
		const media_type = this.form_element.querySelector('#type').value;
		const message_format = this.form_element.querySelector('#message_format').value;

		if (media_type == <?= MEDIA_TYPE_SMS ?>) {
			return {
				message: message_templates[message_type]['template']['sms']
			};
		}

		if (media_type == <?= MEDIA_TYPE_EMAIL ?> && message_format == <?= ZBX_MEDIA_MESSAGE_FORMAT_HTML ?>) {
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

	#submit() {
		this.overlay.setLoading();
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();
					return;
				}

				const curl = new Curl('zabbix.php');
				curl.setArgument('action', 'mediatype.message.check');

				this.#post(curl.getUrl(), fields);
			});
	}

	/**
	 * Sends a POST request to the specified URL with the provided data and handles the response.
	 *
	 * @param {callback} url   The URL to send the POST request to.
	 * @param {object}   data  Data to send with the POST request.
	 */
	#post(url, data) {
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

				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();
					return;
				}

				overlayDialogueDestroy(this.overlay.dialogueid);
				this.dialogue.dispatchEvent(new CustomEvent('message.submit', {detail: response}));
			})
			.catch((exception) => {
				for (const element of this.form_element.parentNode.children) {
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

				this.form_element.parentNode.insertBefore(message_box, this.form_element);
			})
			.finally(() => this.overlay.unsetLoading());
	}
}
