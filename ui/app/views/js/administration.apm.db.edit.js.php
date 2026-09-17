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

	/** @type {boolean} */
	#password_cleared = false;

	/** @type {HTMLInputElement|null} */
	#password_input = null;

	/** @type {HTMLButtonElement|null} */
	#password_warning = null;

	/** @type {HTMLButtonElement|null} */
	#change_password_btn = null;

	/** @type {boolean} */
	#tls_automatically_checked = false;

	init({rules}) {
		this.#rules = rules;

		this.#form_element = document.getElementById('apm-form');
		this.#form = new CForm(this.#form_element, this.#rules);

		this.#url_input = this.#getFormField('url');

		this.#password_input = this.#getFormField('password');
		this.#password_warning = document.querySelector('.js-password-warning');
		this.#change_password_btn = document.querySelector('.js-change-password');

		const initial_values = this.#getAllValues();
		this.#bindEvents({initial_values});
		this.#updateForm({initial_values});

		this.#form_element.removeAttribute('hidden');
	}

	#bindEvents({initial_values}) {
		this.#form_element.addEventListener('submit', this.#submitForm);

		this.#url_input?.addEventListener('input', () => {
			this.#url_changed = true;

			if (this.#password_input !== null && !this.#password_changed) {
				const configured_authtype_password = initial_values.status === APM_GLOBAL_DB_STATUS_CONFIGURED
					&& initial_values.authentication_type === APM_GLOBAL_DB_AUTHTYPE_PASSWORD;

				this.#password_cleared = this.#password_input.value.length > 0 || configured_authtype_password;

				this.#password_input.value = '';
			}

			this.#updateForm({initial_values});
		});

		this.#change_password_btn?.addEventListener('click', e => {
			this.#password_changed = true;

			this.#password_input?.focus();

			this.#updateDisplayState([this.#password_input], true);
			this.#updateDisabledState([this.#password_input], false);

			e.target.hidden = true;
		});

		this.#password_input?.addEventListener('input', () => {
			this.#password_cleared = false;

			this.#updateForm({initial_values});
		});

		for (const name of ['status', 'authentication_type', 'ssl_verify_peer']) {
			this.#getFormField(name)?.addEventListener('change', () => this.#updateForm({initial_values}));
		}
	}

	#getAllValues(untrimmed_fields = []) {
		/** @type {Object<string, any>} */
		let values = this.#form.getAllValues(untrimmed_fields);

		for (const field of ['status', 'ssl_verify_peer', 'ssl_verify_host']) {
			if (field in values) {
				values[field] = parseInt(values[field]);
			}
		}

		if (values.status === APM_GLOBAL_DB_STATUS_CONFIGURED) {
			const url = values.url?.replace(/^[\x00-\x20]+|[\x00-\x20]+$|[\r\n\t]+/g, '') ?? '';
			const auth_type_input = this.#getFormField('authentication_type')?.querySelector('input:checked');
			const authentication_type = parseInt(auth_type_input?.value ?? APM_GLOBAL_DB_AUTHTYPE_PASSWORD);

			return {...values, url, authentication_type};
		}

		return values;
	}

	#updateForm({initial_values}) {
		const values = this.#getAllValues();

		const show_fields = values.status === APM_GLOBAL_DB_STATUS_CONFIGURED;
		const show_user_fields = show_fields && values.authentication_type === APM_GLOBAL_DB_AUTHTYPE_PASSWORD;
		const show_ssl_fields = show_fields && values.url.substring(0, 8).toLowerCase() === 'https://';
		const show_ssl_verify_peer_fields = show_ssl_fields
			&& values.ssl_verify_peer === APM_GLOBAL_DB_VERIFY_PEER_ENABLED;

		this.#updateDisplayState([
			...document.querySelectorAll('.js-url'),
			...document.querySelectorAll('.js-auth-type'),
			...document.querySelectorAll('.js-db-type'),
			...document.querySelectorAll('.js-database'),
		], show_fields, true);

		this.#updateDisplayState([
			...document.querySelectorAll('.js-username'),
			...document.querySelectorAll('.js-password')
		], show_user_fields, true);

		this.#updateDisplayState([
			...document.querySelectorAll('.js-ssl-verify-peer')
		], show_ssl_fields, true);

		const ssl_verify_host_fields = document.querySelectorAll('.js-ssl-verify-host');

		this.#updateDisplayState([...ssl_verify_host_fields], show_ssl_verify_peer_fields, true);

		if (initial_values.status === APM_GLOBAL_DB_STATUS_NOT_CONFIGURED && this.#url_changed && show_ssl_fields) {
			const ssl_verify_peer = this.#form.findFieldByName('ssl_verify_peer')?.getField();

			if (!this.#tls_automatically_checked && ssl_verify_peer !== null) {
				this.#updateDisplayState([...ssl_verify_host_fields], true, true);

				ssl_verify_peer.checked = true;

				const ssl_verify_host = this.#form.findFieldByName('ssl_verify_host')?.getField();
				if (ssl_verify_host !== null) {
					ssl_verify_host.checked = true;
				}

				this.#tls_automatically_checked = true;
			}
		}

		const configured_authtype_password = initial_values.status === APM_GLOBAL_DB_STATUS_CONFIGURED
			&& initial_values.authentication_type === APM_GLOBAL_DB_AUTHTYPE_PASSWORD;
		const show_change_password_btn = configured_authtype_password && !this.#url_changed && !this.#password_changed;

		this.#updateDisabledState([this.#password_input], show_change_password_btn);

		const show_password_warning = this.#url_changed && this.#password_cleared;

		this.#password_warning?.toggleAttribute('hidden', !show_password_warning);

		this.#updateDisplayState([this.#password_input], !show_change_password_btn);
		this.#updateDisplayState([this.#change_password_btn], show_change_password_btn);
	}

	#getFormField(name) {
		return this.#form.findFieldByName(name)?.getField();
	}

	#updateDisplayState(elements, display, update_disabled_state = false) {
		for (const element of elements) {
			element?.toggleAttribute('hidden', !display);

			if (update_disabled_state) {
				this.#updateDisabledState(element?.querySelectorAll('input'), !display);
			}
		}
	}

	#updateDisabledState(elements, disabled) {
		for (const element of elements) {
			element?.toggleAttribute('disabled', disabled);
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
		const values = this.#getAllValues(['password']);

		this.#form.validateSubmit(values)
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
					body: JSON.stringify(values)
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
