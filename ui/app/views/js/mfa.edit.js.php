<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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

window.mfa_edit = new class {

	/**
	 * @var {Overlay}
	 */
	#overlay;

	/**
	 * @type {HTMLDivElement}
	 */
	#dialogue;

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	/**
	 * @type {string | null}
	 */
	#mfaid;

	/**
	 * @type {Object}
	 */
	#change_sensitive_data;

	init({mfaid, change_sensitive_data}) {
		this.#overlay = overlays_stack.getById('mfa_edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form = this.#overlay.$dialogue.$body[0].querySelector('form');

		this.#mfaid = mfaid;
		this.#change_sensitive_data = change_sensitive_data;

		this.#addEventListeners();
		this.#updateForm();
	}

	#addEventListeners() {
		this.#form.querySelector('[name=type]').addEventListener('change', () => {
			this.#updateForm();
		});

		const client_secret_button = document.getElementById('client-secret-btn');

		if (client_secret_button !== null) {
			client_secret_button.addEventListener('click', this.#showClientSecretField);
		}
	}

	#updateForm() {
		const type = this.#form.querySelector('[name="type"]').value;

		for (const element_class of ['js-hash-function', 'js-code-length']) {
			for (const element of this.#form.querySelectorAll(`.${element_class}`)) {
				element.style.display = type == MFA_TYPE_TOTP ? '' : 'none';
			}
		}

		for (const element_class of ['js-api-hostname', 'js-clientid', 'js-client-secret']) {
			for (const element of this.#form.querySelectorAll(`.${element_class}`)) {
				element.style.display = type == MFA_TYPE_DUO ? '' : 'none';
			}
		}
	}

	#showClientSecretField(e) {
		const form_field = e.target.parentNode;

		const client_secret_field = form_field.querySelector('[name="client_secret"][type="password"]');
		client_secret_field.style.display = '';
		client_secret_field.disabled = false;

		const client_secret_var = form_field.querySelector('[name="client_secret"][type="hidden"]');
		if (client_secret_var !== null) {
			form_field.removeChild(client_secret_var);
		}
		form_field.removeChild(e.target);
	}

	submit() {
		if (this.#mfaid !== null && this.#isSensitiveDataModified() && !this.#confirmSubmit()) {
			this.#overlay.unsetLoading();

			return;
		}

		this.#overlay.setLoading();

		const fields = this.#getFormFields();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'mfa.check');

		this.#post(curl.getUrl(), fields);
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
				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((exception) => {
				for (const element of this.#form.parentNode.children) {
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

				this.#form.parentNode.insertBefore(message_box, this.#form);
			})
			.finally(() => {
				this.#overlay.unsetLoading();
			});
	}

	#confirmSubmit() {
		return window.confirm(<?= json_encode(
			_('After this change, users who have already enrolled in this MFA method will have to complete the enrollment process again because TOTP secrets will be reset.')
		) ?>);
	}

	#isSensitiveDataModified() {
		if (this.#change_sensitive_data.type == MFA_TYPE_DUO) {
			return false;
		}

		const form_fields = this.#getFormFields();

		for (const key in this.#change_sensitive_data) {
			if (form_fields.hasOwnProperty(key)) {
				if (this.#change_sensitive_data[key] !== form_fields[key]) {
					return true;
				}
			}
		}

		return false;
	}

	#getFormFields() {
		const fields = getFormFields(this.#form);

		for (let key in fields) {
			if (typeof fields[key] === 'string' && key !== 'confirmation') {
				fields[key] = fields[key].trim();
			}
		}

		return fields;
	}
}
