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
	/** @type {Object} */
	#rules = {};

	/** @type {Object<string, *>} */
	#default_values = {};

	/** @type {boolean} */
	#has_api_key = false;

	/** @type {boolean} */
	#has_password = false;

	/** @type {HTMLFormElement|null} */
	#form_element = null;

	/** @type {CForm|null} */
	#form = null;

	/** @type {boolean} */
	#host_changed = false;

	/** @type {HTMLButtonElement|null} */
	#change_host_btn = null;

	/** @type {boolean} */
	#password_changed = false;

	/** @type {HTMLButtonElement|null} */
	#change_password_btn = null;

	/** @type {boolean} */
	#api_key_changed = false;

	/** @type {HTMLButtonElement|null} */
	#change_api_key_btn = null;

	init({rules, default_values, has_api_key, has_password}) {
		this.#rules = rules;
		this.#default_values = default_values;
		this.#has_api_key = has_api_key;
		this.#has_password = has_password;

		this.#form_element = document.getElementById('apm-form');
		this.#form = new CForm(this.#form_element, this.#rules);

		this.#change_host_btn = document.getElementById('change_host');
		this.#change_password_btn = document.getElementById('change_password');
		this.#change_api_key_btn = document.getElementById('change_api_key');

		this.#bindEvents();
	}

	#bindEvents() {
		this.#form_element.addEventListener('submit', this.#submitForm);

		const host_input = this.#getFormField('host');
		const password_input = this.#getFormField('password');
		const api_key_input = this.#getFormField('api_key');

		host_input?.addEventListener('change', () => {
			this.#host_changed = true;

			const fields = this.#getAllValues();

			/** @var {HTMLButtonElement|null} */
			const password_warning = document.querySelector('.js-password-warning');
			/** @var {HTMLButtonElement|null} */
			const api_key_warning = document.querySelector('.js-api-key-warning');

			if ((fields.type !== ZBX_DB_ELASTICSEARCH || fields.authentication === ELASTICSEARCH_AUTH_BASIC)
					&& password_input !== null) {
				this.#password_changed = password_input.value !== '';

				password_input.value = '';
				password_input.classList.remove(ZBX_STYLE_DISPLAY_NONE);

				this.#change_password_btn?.classList.add(ZBX_STYLE_DISPLAY_NONE);

				if (this.#password_changed) {
					api_key_warning?.classList.add(ZBX_STYLE_DISPLAY_NONE);
					password_warning?.classList.remove(ZBX_STYLE_DISPLAY_NONE);
				}
			}

			if (fields.type === ZBX_DB_ELASTICSEARCH && fields.authentication === ELASTICSEARCH_AUTH_API_KEY
					&& api_key_input !== null) {
				this.#api_key_changed = api_key_input.value !== '';

				api_key_input.value = '';
				api_key_input.classList.remove(ZBX_STYLE_DISPLAY_NONE);

				this.#change_api_key_btn?.classList.add(ZBX_STYLE_DISPLAY_NONE);

				if (this.#api_key_changed) {
					password_warning?.classList.add(ZBX_STYLE_DISPLAY_NONE);
					api_key_warning?.classList.remove(ZBX_STYLE_DISPLAY_NONE);
				}
			}
		});

		this.#change_host_btn?.addEventListener('click', () => {
			this.#host_changed = true;

			host_input?.removeAttribute('readonly');
			host_input?.focus();

			const value_length = host_input?.value.length ?? 0;
			host_input?.setSelectionRange(value_length, value_length);

			this.#change_host_btn?.classList.add(ZBX_STYLE_DISPLAY_NONE);

			this.#updateDisplayState(document.querySelectorAll('.js-change-host'), false);
		});

		this.#change_password_btn?.addEventListener('click', e => {
			this.#password_changed = true;

			this.#getFormField('change_password')?.setAttribute('value', '1');

			password_input?.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			password_input?.focus();

			e.target.classList.add(ZBX_STYLE_DISPLAY_NONE);
		});

		this.#change_api_key_btn?.addEventListener('click', e => {
			this.#api_key_changed = true;

			this.#getFormField('change_api_key')?.setAttribute('value', '1');

			api_key_input?.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			api_key_input?.focus();

			e.target.classList.add(ZBX_STYLE_DISPLAY_NONE);
		});

		for (const name of ['authentication', 'status', 'type', 'encryption', 'verify_peer']) {
			this.#getFormField(name)?.addEventListener('change', () => this.#updateForm());
		}
	}

	#getAllValues() {
		const fields = this.#form.getAllValues();
		const type = this.#getFormField('type');
		const authentication = this.#getFormField('authentication')?.querySelector('input:checked');

		return {
			...fields,
			status: parseInt(fields.status),
			type: type?.value ?? this.#default_values.type,
			authentication: parseInt(authentication?.value ?? this.#default_values.authentication),
			encryption: parseInt(fields.encryption),
			verify_peer: parseInt(fields.verify_peer),
			verify_host: parseInt(fields.verify_host)
		};
	}

	#updateForm() {
		const fields = this.#getAllValues();

		const is_type_sql = [ZBX_DB_MYSQL, ZBX_DB_POSTGRESQL].includes(fields.type);
		const is_type_postgresql = fields.type === ZBX_DB_POSTGRESQL;
		const is_type_elasticsearch = fields.type === ZBX_DB_ELASTICSEARCH;

		const show_fields = fields.status === 1;
		const show_change_host_btn = !this.#host_changed && String(fields.host).length > 0;
		const show_database_fields = show_fields && !is_type_elasticsearch;
		const show_schema_fields = show_fields && is_type_postgresql;
		const show_authentication_fields = show_fields && is_type_elasticsearch;
		const show_api_key_fields = show_authentication_fields && fields.authentication === ELASTICSEARCH_AUTH_API_KEY;
		const show_encryption_fields = show_fields && fields.encryption === 1;
		const show_key_file_fields = show_encryption_fields && is_type_sql;
		const show_cert_file_fields = show_encryption_fields && is_type_sql;
		const show_user_fields = show_fields
			&& (show_database_fields || (!show_api_key_fields && fields.authentication !== ELASTICSEARCH_AUTH_NONE));
		const show_verify_peer = show_encryption_fields && fields.verify_peer === 1;

		if (!show_fields) {
			this.#resetFormState();
		}

		this.#updateDisplayState([
			...document.querySelectorAll('.js-type'),
			...document.querySelectorAll('.js-host'),
			...document.querySelectorAll('.js-port'),
			...document.querySelectorAll('.js-encryption')
		], show_fields);

		this.#updateDisplayState(document.querySelectorAll('.js-change-host'), show_change_host_btn);

		if (show_change_host_btn) {
			this.#getFormField('host')?.setAttribute('readonly', 'readonly');
		}

		this.#updateDisplayState(document.querySelectorAll('.js-schema'), show_schema_fields);

		this.#updateDisplayState([
			...document.querySelectorAll('.js-username'),
			...document.querySelectorAll('.js-password')
		], show_user_fields);

		this.#updateDisplayState(document.querySelectorAll('.js-authentication'), show_authentication_fields);
		this.#updateDisplayState(document.querySelectorAll('.js-api-key'), show_api_key_fields);

		this.#updateDisplayState([
			...document.querySelectorAll('.js-verify-peer'),
			...document.querySelectorAll('.js-certificate-file'),
			...document.querySelectorAll('.js-verify-host')
		], show_encryption_fields);

		this.#updateDisplayState(document.querySelectorAll('.js-key-file'), show_key_file_fields);
		this.#updateDisplayState(document.querySelectorAll('.js-cert-file'), show_cert_file_fields);
		this.#updateDisplayState(document.querySelectorAll('.js-ca-file'), show_verify_peer);
		this.#updateDisplayState(document.querySelectorAll('.js-database'), show_database_fields);

		this.#updateRequiredState('database', is_type_sql);

		/** @var {HTMLElement|null} */
		const host_help = document.querySelector('.js-host-help');
		host_help?.classList.toggle(ZBX_STYLE_DISPLAY_NONE, !is_type_postgresql);

		const show_change_api_key_btn = !this.#api_key_changed && this.#has_api_key;

		this.#getFormField('api_key')?.classList.toggle(ZBX_STYLE_DISPLAY_NONE, show_change_api_key_btn);

		this.#getFormField('change_api_key')?.setAttribute('value',
			!show_change_api_key_btn && fields.authentication === ELASTICSEARCH_AUTH_API_KEY ? '1' : '0');

		this.#change_api_key_btn?.classList.toggle(ZBX_STYLE_DISPLAY_NONE, !show_change_api_key_btn);

		const show_change_password_btn = !this.#password_changed && this.#has_password;

		if (show_change_password_btn) {
			this.#getFormField('change_password')?.setAttribute('value', '0');
		}

		this.#getFormField('password')?.classList.toggle(ZBX_STYLE_DISPLAY_NONE, show_change_password_btn);

		this.#change_password_btn?.classList.toggle(ZBX_STYLE_DISPLAY_NONE, !show_change_password_btn);
	}

	#getFormField(name) {
		return this.#form.findFieldByName(name)?.getField();
	}

	#updateDisplayState(elements, display) {
		for (const element of elements) {
			element?.classList.toggle(ZBX_STYLE_DISPLAY_NONE, !display);
		}
	}

	#updateRequiredState(field, required) {
		/** @var {HTMLLabelElement|null} */
		const label = document.querySelector(`label[for="${field}"]`);
		label?.classList.toggle(ZBX_STYLE_FIELD_LABEL_ASTERISK, required);
	}

	#resetFormState() {
		const default_values = {
			...this.#default_values,
			password: '',
			api_key: ''
		};

		for (const [name, value] of Object.entries(default_values).filter(([name]) => name !== 'status')) {
			const field = this.#form.findFieldByName(name);

			const input = field?.getField();
			if (input !== null) {
				if (input.type === 'checkbox') {
					input.checked = value === 1;
				}
				else {
					input.value = value;
				}

				field?.setChanged(false);
			}
		}

		this.#host_changed = false;
		this.#password_changed = false;
		this.#api_key_changed = false;

		this.#form.reload(this.#rules);
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
