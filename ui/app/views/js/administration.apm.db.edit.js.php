<?php
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
 * @var array $data
 */
?>

<script>
const view = new class {
	/** @type {Object<string, *>} */
	#rules = {};

	/** @type {Object<string, *>} */
	#default_values = {};

	/** @type {HTMLFormElement|null} */
	#form_element = null;

	/** @type {CForm|null} */
	#form = null;

	/** @type {boolean} */
	#url_changed = false;

	/** @type {HTMLInputElement|null} */
	#url_input = null;

	/** @type {boolean} */
	#password_changed = false;

	/** @type {HTMLInputElement|null} */
	#password_input = null;

	/** @type {HTMLButtonElement|null} */
	#password_warning = null;

	/** @type {HTMLButtonElement|null} */
	#change_password_btn = null;

	/** @type {boolean} */
	#ssl_key_password_changed = false;

	/** @type {HTMLInputElement|null} */
	#ssl_key_password_input = null;

	/** @type {HTMLButtonElement|null} */
	#change_ssl_key_password_btn = null;

	init({rules, default_values}) {
		this.#rules = rules;
		this.#default_values = default_values;

		this.#form_element = document.getElementById('apm-form');
		this.#form = new CForm(this.#form_element, this.#rules);

		this.#url_input = this.#getFormField('url');

		this.#password_input = this.#getFormField('password');
		this.#password_warning = document.querySelector('.js-password-warning');
		this.#change_password_btn = document.querySelector('.js-change-password');

		this.#ssl_key_password_input = this.#getFormField('ssl_key_password');
		this.#change_ssl_key_password_btn = document.querySelector('.js-change-ssl-key-password');

		const initial_values = this.#getAllValues();
		this.#bindEvents({initial_values});
		this.#updateForm({initial_values});

		this.#form_element.removeAttribute('hidden');
	}

	#bindEvents({initial_values}) {
		this.#form_element.addEventListener('submit', this.#submitForm);

		this.#url_input?.addEventListener('input', () => {
			this.#url_changed = true;

			this.#updateForm({initial_values});
		});

		this.#change_password_btn?.addEventListener('click', e => {
			this.#password_changed = this.#password_input?.value !== '';

			this.#password_input?.removeAttribute('hidden');
			this.#password_input?.focus();

			e.target.hidden = true;
		});

		this.#change_ssl_key_password_btn?.addEventListener('click', e => {
			this.#ssl_key_password_changed = this.#ssl_key_password_input?.value !== '';

			this.#ssl_key_password_input?.removeAttribute('hidden');
			this.#ssl_key_password_input?.focus();

			e.target.hidden = true;
		});

		for (const name of ['status', 'authentication_type', 'ssl_verify_peer']) {
			this.#getFormField(name)?.addEventListener('change', () => this.#updateForm({initial_values}));
		}
	}

	#getAllValues() {
		const values = this.#form.getAllValues();
		const authentication_type = this.#getFormField('authentication_type')?.querySelector('input:checked');

		return {
			...values,
			url: values.url.replace(/^[\x00-\x20]+|[\x00-\x20]+$|[\r\n\t]+/g, ''),
			status: parseInt(values.status),
			authentication_type: parseInt(authentication_type?.value ?? this.#default_values.authentication_type),
			ssl_verify_peer: parseInt(values.ssl_verify_peer),
			ssl_verify_host: parseInt(values.ssl_verify_host)
		};
	}

	#updateForm({initial_values}) {
		const values = this.#getAllValues();

		const show_fields = values.status === APM_GLOBAL_DB_STATUS_CONFIGURED;
		const show_user_fields = show_fields && values.authentication_type === APM_GLOBAL_DB_AUTHTYPE_PASSWORD;
		const show_vault_path = show_fields && values.authentication_type === APM_GLOBAL_DB_AUTHTYPE_VAULT;
		const show_ssl_fields = show_fields && values.url.substring(0, 8) === 'https://';
		const show_ssl_verify_peer_fields = show_ssl_fields
			&& values.ssl_verify_peer === APM_GLOBAL_DB_VERIFY_PEER_ENABLED;

		this.#updateDisplayState([
			...document.querySelectorAll('.js-url'),
			...document.querySelectorAll('.js-auth-type'),
			...document.querySelectorAll('.js-db-type'),
			...document.querySelectorAll('.js-database'),
		], show_fields);

		this.#updateDisplayState([
			...document.querySelectorAll('.js-username'),
			...document.querySelectorAll('.js-password')
		], show_user_fields);

		this.#updateDisplayState([
			...document.querySelectorAll('.js-vault-path')
		], show_vault_path);

		this.#updateDisplayState([
			...document.querySelectorAll('.js-ssl-verify-peer'),
			...document.querySelectorAll('.js-ssl-cert-file'),
			...document.querySelectorAll('.js-ssl-key-file'),
			...document.querySelectorAll('.js-ssl-key-password')
		], show_ssl_fields);

		this.#updateDisplayState([
			...document.querySelectorAll('.js-ssl-ca-location'),
			...document.querySelectorAll('.js-ssl-verify-host')
		], show_ssl_verify_peer_fields);

		const show_change_password_btn = initial_values.status === 1
			&& values.authentication_type === APM_GLOBAL_DB_AUTHTYPE_PASSWORD
			&& !this.#url_changed && !this.#password_changed;

		this.#password_warning?.setAttribute('hidden', '');

		if (this.#password_input !== null) {
			if (this.#url_changed && this.#password_input.value !== '') {
				this.#password_input.value = '';

				this.#password_warning?.removeAttribute('hidden');
			}

			this.#password_input.toggleAttribute('hidden', show_change_password_btn);

			this.#change_password_btn?.toggleAttribute('hidden', !show_change_password_btn);
		}

		const show_change_ssl_key_password_btn = initial_values.status === APM_GLOBAL_DB_STATUS_CONFIGURED
			&& show_ssl_fields
			&& !this.#ssl_key_password_changed;

		if (this.#ssl_key_password_input !== null) {
			this.#ssl_key_password_input.toggleAttribute('hidden', show_change_ssl_key_password_btn);

			this.#change_ssl_key_password_btn?.toggleAttribute('hidden', !show_change_ssl_key_password_btn);
		}

		this.#url_changed = false;
		this.#password_changed = false;
	}

	#getFormField(name) {
		return this.#form.findFieldByName(name)?.getField();
	}

	#updateDisplayState(elements, display) {
		for (const element of elements) {
			element?.toggleAttribute('hidden', !display);
		}
	}

	#setLoadingStatus(loading_btn_class) {
		this.#form_element.classList.add(ZBX_STYLE_LOADING, ZBX_STYLE_LOADING_FADEIN);

		this.#form_element.querySelectorAll('.table-forms .tfoot-buttons button').forEach(button => {
			button.disabled = true;

			if (button.classList.contains(loading_btn_class)) {
				button.classList.add(ZBX_STYLE_LOADING);
			}
		});
	}

	#unsetLoadingStatus() {
		this.#form_element.querySelectorAll('.table-forms .tfoot-buttons button').forEach(button => {
			button.classList.remove(ZBX_STYLE_LOADING);
			button.disabled = false;
		});

		this.#form_element.classList.remove(ZBX_STYLE_LOADING, ZBX_STYLE_LOADING_FADEIN);
	}

	#submitForm = e => {
		e.preventDefault();
		this.#setLoadingStatus('js-submit');
		clearMessages();
		const fields = this.#getAllValues();

		this.#form.validateSubmit(fields)
			.then(result => {
				if (!result) {
					this.#unsetLoadingStatus();
					return;
				}

				const url = new URL('zabbix.php', location.href);
				url.searchParams.set('action', 'apm.db.update');

				fetch(url.toString(), {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify(fields)
				})
					.then(response => response.json())
					.then(response => {
						if ('error' in response) {
							throw {error: response.error};
						}

						if ('form_errors' in response) {
							this.#form.setErrors(response.form_errors, true, true);
							this.#form.renderErrors();
							return;
						}

						if ('success' in response) {
							postMessageOk(response.success.title);

							if ('messages' in response.success) {
								postMessageDetails('success', response.success.messages);
							}

							location.href = location.href;
						}
					})
					.catch(exception => this.#handleFormError(exception))
					.finally(() => this.#unsetLoadingStatus());
			});
	}

	#handleFormError(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		addMessage(makeMessageBox('bad', messages, title)[0]);
	}
}
</script>
