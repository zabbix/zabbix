<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

window.mfa_edit = new class {

	constructor() {
		this.overlay = null;
		this.dialogue = null;
		this.form = null;
	}

	init() {
		this.overlay = overlays_stack.getById('mfa_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.type = this.form.querySelector('[name="type"]');

		this.#toggleMfaType(this.type.value);
		this.#addEventListeners();
	}

	#addEventListeners() {
		this.form.addEventListener('change', (e) => {
			if (e.target.name === 'type') {
				this.#toggleMfaType(e.target.value);
			}
		})

		if (document.getElementById('client-secret-btn') !== null) {
			document.getElementById('client-secret-btn').addEventListener('click', this.#showClientSecretField);
		}
	}

	#toggleMfaType(type) {
		let totp_fields = ['hash_function', 'code_length'];
		let duo_fields = ['api_hostname', 'clientid', 'client_secret'];

		switch (type) {
			case MFA_TYPE_TOTP:
				this.#toggleFields(duo_fields, totp_fields);
				break;

			case MFA_TYPE_DUO:
				this.#toggleFields(totp_fields, duo_fields);
				break;
		}
	}

	#toggleFields(fields_to_hide, fields_to_show) {
		fields_to_hide.forEach((field_to_hide) => {
			this.form.querySelector('#' + field_to_hide).style.display = 'none';
			this.form.querySelector('label[for="' + field_to_hide + '"]').style.display = 'none';
		});
		fields_to_show.forEach((field_to_show) => {
			this.form.querySelector('#' + field_to_show).style.display = '';
			this.form.querySelector('label[for="' + field_to_show + '"]').style.display = '';
		});
	}

	#showClientSecretField(e) {
		const form_field = e.target.parentNode;
		const client_secret_field = form_field.querySelector('[name="client_secret"][type="password"]');
		const client_secret_var = form_field.querySelector('[name="client_secret"][type="hidden"]');

		client_secret_field.style.display = '';
		client_secret_field.disabled = false;

		if (client_secret_var !== null) {
			form_field.removeChild(client_secret_var);
		}
		form_field.removeChild(e.target);
	}

	submit() {
		this.overlay.setLoading();

		const fields = this.#getFormFields();
		const curl = new Curl(this.form.getAttribute('action'));

		this.#post(curl.getUrl(), fields);
	}

	#getFormFields() {
		const fields = getFormFields(this.form);

		for (let key in fields) {
			if (typeof fields[key] === 'string' && key !== 'confirmation') {
				fields[key] = fields[key].trim();
			}
		}

		return fields;
	}

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
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
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
