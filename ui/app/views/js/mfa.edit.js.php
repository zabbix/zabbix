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
	#form_element;

	/**
	 * @type {CForm}
	 */
	#form;

	/**
	 * @type {string | null}
	 */
	#mfaid;

	/**
	 * @type {array}
	 */
	#existing_names
	/**
	 * @type {Object}
	 */
	#change_sensitive_data;

	init({rules, mfaid, change_sensitive_data, existing_names}) {
		this.#overlay = overlays_stack.getById('mfa_edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);

		this.#mfaid = mfaid;
		this.#change_sensitive_data = change_sensitive_data;
		this.#existing_names = existing_names;

		this.#addEventListeners();
		this.#updateForm();
	}

	#addEventListeners() {
		this.#form_element.querySelector('[name=type]').addEventListener('change', () => {
			this.#updateForm();
		});

		const client_secret_button = document.getElementById('client-secret-btn');

		if (client_secret_button !== null) {
			client_secret_button.addEventListener('click', this.#showClientSecretField);
		}

		this.#overlay.$dialogue.$footer[0].querySelector('.js-submit')
			.addEventListener('click', () => this.#submit());
	}

	#updateForm() {
		const type = this.#form_element.querySelector('[name="type"]').value;

		for (const element_class of ['js-hash-function', 'js-code-length']) {
			for (const element of this.#form_element.querySelectorAll(`.${element_class}`)) {
				element.style.display = type == MFA_TYPE_TOTP ? '' : 'none';
			}
		}

		for (const element_class of ['js-api-hostname', 'js-clientid', 'js-client-secret']) {
			for (const element of this.#form_element.querySelectorAll(`.${element_class}`)) {
				element.style.display = type == MFA_TYPE_DUO ? '' : 'none';
			}
		}
	}

	#showClientSecretField(e) {
		const form_field = e.target.parentNode;

		const client_secret_field = form_field.querySelector('[name="client_secret"][type="password"]');
		client_secret_field.style.display = '';
		client_secret_field.value = '';
		client_secret_field.disabled = false;

		form_field.removeChild(e.target);
	}

	#submit() {
		this.#removePopupMessages();

		if (this.#mfaid !== null && this.#isSensitiveDataModified() && !this.#confirmSubmit()) {
			this.#overlay.unsetLoading();

			return;
		}

		this.#overlay.setLoading();
		const fields = this.#form.getAllValues();
		fields.existing_names = this.#existing_names;

		this.#form.validateSubmit(fields).then(result => {
			if (!result) {
				this.#overlay.unsetLoading();

				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'mfa.check');

			this.#post(curl.getUrl(), fields);
		});
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

				if ('form_errors' in response) {
					this.#form.setErrors(response.form_errors, true, true);
					this.#form.renderErrors();

					return;
				}

				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
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

		const form_fields = this.#form.getAllValues();

		for (const key in this.#change_sensitive_data) {
			if (form_fields.hasOwnProperty(key)) {
				if (this.#change_sensitive_data[key] !== form_fields[key]) {
					return true;
				}
			}
		}

		return false;
	}

	#removePopupMessages() {
		for (const element of this.#form_element.parentNode.children) {
			if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
				element.parentNode.removeChild(element);
			}
		}
	}

	#ajaxExceptionHandler(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		this.#form_element.parentNode.insertBefore(message_box, this.#form_element);
	}
}
