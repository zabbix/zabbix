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

window.mediatype_edit_popup = new class {

	init({mediatype, message_templates, smtp_server_default, smtp_email_default}) {
		this.overlay = overlays_stack.getById('mediatype.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.mediatypeid = mediatype.mediatypeid;
		this.mediatype = mediatype;
		this.row_num = 0;
		this.message_template_list = {};
		this.message_templates = Object.fromEntries(message_templates.map((obj, index) => [index, { ...obj }]));
		this.smtp_server_default = smtp_server_default;
		this.smtp_email_default = smtp_email_default;

		const backurl = new Curl('zabbix.php');

		backurl.setArgument('action', 'mediatype.list');
		this.overlay.backurl = backurl.getUrl();

		this.#loadView(mediatype);
		this.#initActions();
		this.form.style.display = '';
		this.overlay.recoverFocus();

		this.form.querySelector('#type').dispatchEvent(new CustomEvent('change', {detail: {init: true}}));
	}

	#initActions() {
		for (const parameter of this.mediatype.parameters_webhook) {
			this.#addWebhookParam(parameter);
		}

		for (const parameter of this.mediatype.parameters_exec) {
			this.#addExecParam(parameter);
			this.row_num++;
		}

		this.#populateMessageTemplates(this.mediatype['message_templates']);

		this.form.querySelector('#message-templates').addEventListener('click', (event) =>
			this.#editMessageTemplate(event)
		);

		this.form.querySelector('.element-table-add').addEventListener('click', () => {
			this.#addExecParam();
			this.row_num++;
		});

		this.form.querySelector('.webhook-param-add').addEventListener('click', () => this.#addWebhookParam());

		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-remove')) {
				e.target.closest('tr').remove();
			}
			else if (e.target.classList.contains('js-remove-msg-template')) {
				e.target.closest('tr').remove();
				delete this.message_template_list[e.target.closest('tr').getAttribute('data-message-type')];
				this.#toggleAddButton();
			}
		});

		if (this.form.querySelector('#chPass_btn') !== null) {
			this.form.querySelector('#chPass_btn').addEventListener('click', () => this.#toggleChangePasswordButton());
		}

		const event_menu = this.form.querySelector('#show_event_menu');

		event_menu.onchange = () => {
			const event_menu_name = this.form.querySelector('#event_menu_name');
			const event_menu_url = this.form.querySelector('#event_menu_url');

			if (event_menu.checked) {
				event_menu_name.disabled = false;
				event_menu_url.disabled = false;
			}
			else {
				event_menu_name.disabled = true;
				event_menu_url.disabled = true;
			}
		}
	}

	clone({title, buttons}) {
		this.mediatypeid = null;

		this.#toggleChangePasswordButton();
		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'mediatype.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('mediatype')) ?>);

		this.#post(curl.getUrl(), {mediatypeids: [this.mediatypeid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		// Trim all string type fields.
		for (let key in fields) {
			if (typeof fields[key] === 'string' && key !== 'passwd') {
				fields[key] = fields[key].trim();
			}
		}

		// Trim all string values within the 'parameters_webhook' object.
		if (typeof fields.parameters_webhook !== 'undefined') {
			fields.parameters_webhook.name = fields.parameters_webhook.name.map((name) => name.trim());
			fields.parameters_webhook.value = fields.parameters_webhook.value.map((value) => value.trim());
		}

		// Trim all string values within the 'parameters_exec' object.
		if (typeof fields.parameters_exec !== 'undefined') {
			Object.keys(fields.parameters_exec).forEach((key) =>
				Object.values(key).forEach((parameter) =>
					fields.parameters_exec[parameter].value = fields.parameters_exec[parameter].value.trim()
				)
			)
		}

		// Set maxsessions value.
		const maxsessions_type = this.form.querySelector('input[name="maxsessions_type"]:checked').value;

		switch (maxsessions_type) {
			case 'one':
			default:
				fields.maxsessions = 1;
				break;

			case 'unlimited':
				fields.maxsessions = 0;
				break;

			case 'custom':
				fields.maxsessions = this.form.querySelector('#maxsessions').value;
				break;
		}

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.mediatypeid === null ? 'mediatype.create' : 'mediatype.update');

		this.#post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	/**
	 * Sends a POST request to the specified URL with the provided data and executes the success_callback function.
	 *
	 * @param {string}   url               The URL to send the POST request to.
	 * @param {object}   data              The data to send with the POST request.
	 * @param {callback} success_callback  The function to execute when a successful response is received.
	 */
	#post(url, data, success_callback) {
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

				return response;
			})
			.then(success_callback)
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
			.finally(() => this.overlay.unsetLoading());
	}

	/**
	 * Adds a new row to the Parameters table with the given webhook parameter data (name, value).
	 *
	 * @param {object} parameter  An object containing the webhook parameter data.
	 */
	#addWebhookParam(parameter = {}) {
		const template = new Template(this.form.querySelector('#webhook_params_template').innerHTML);

		this.form
			.querySelector('#parameters_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(parameter));
	}

	/**
	 * Adds a new row to the Script parameters table with the given script parameter data (value).
	 *
	 * @param {object} parameter  An object containing the script parameter data.
	 */
	#addExecParam(parameter = {}) {
		parameter.row_num = this.row_num;

		const template = new Template(this.form.querySelector('#exec_params_template').innerHTML);

		this.form
			.querySelector('#exec_params_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(parameter));
	}

	/**
	 * Opens message template popup - create or edit form and adds event listener for message.submit event.
	 *
	 * @param {object} event  The event object.
	 */
	#editMessageTemplate(event) {
		let row = null;

		if (event.target.hasAttribute('data-action')) {
			const btn = event.target;
			const parameters = {
				type: this.form.querySelector('#type').value,
				message_format: this.form.querySelector('input[name="message_format"]:checked').value,
				message_types: [...this.form.querySelectorAll('tr[data-message-type]')].map((tr) =>
					tr.dataset.messageType
				)
			};

			if (btn.dataset.action === 'edit') {
				row = btn.closest('tr');

				parameters.message_type = row.dataset.messageType;
				parameters.old_message_type = parameters.message_type;

				[...row.querySelectorAll('input[type="hidden"]')].forEach((input) => {
					const name = input.getAttribute('name').match(/\[([^\]]+)]$/);

					if (name) {
						parameters[name[1]] = input.value;
					}
				});
			}

			const overlay = PopUp('mediatype.message.edit', parameters, {
				dialogue_class: 'modal-popup-medium',
				dialogueid: 'mediatype-message-form',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('message.submit', (e) => {
				if (row === null) {
					this.#addMessageTemplateRow(e.detail);
				}
				else {
					this.#addMessageTemplateRow(e.detail, row);
				}
			});
		}
	}

	/**
	 * Adds a new row to the Message templates table with the given message template data.
	 *
	 * @param {object} input     An object containing the input data.
	 * @param {object|null} row  Optional. The row element to insert the new row after.
	 *                           If not provided, the new row is appended at the end of the table.
	 */
	#addMessageTemplateRow(input, row = null) {
		const template = new Template(this.form.querySelector('#message-templates-row-tmpl').innerHTML);

		if (row === null) {
			this.form
				.querySelector('#message-templates tbody')
				.insertAdjacentHTML('beforeend', template.evaluate(input));
		}
		else {
			row.insertAdjacentHTML('afterend', template.evaluate(input));
			row.remove();
		}

		this.message_template_list[input.message_type] = input;
		this.#toggleAddButton();
	}

	/**
	 * Adds data to message template table upon media type configuration form opening.
	 *
	 * @param {array} list  An array of message templates.
	 */
	#populateMessageTemplates(list) {
		for (const key in list) {
			if (!Object.prototype.hasOwnProperty.call(list, key)) {
				continue;
			}

			const template = list[key];
			const message_template = this.#getMessageTemplate(template.eventsource, template.recovery);

			template.message_type = message_template.message_type;
			template.message_type_name = message_template.name;

			this.#addMessageTemplateRow(template);
			this.message_template_list[template.message_type] = template;
		}

		this.#toggleAddButton();
	}

	/**
	 * Returns message type and name by the specified event source and operation mode.
	 *
	 * @param {number} eventsource  Event source.
	 * @param {number} recovery     Operation mode.
	 *
	 * @return {object}
	 */
	#getMessageTemplate(eventsource, recovery) {
		for (let message_type in this.message_templates) {
			if (!this.message_templates.hasOwnProperty(message_type)) {
				continue;
			}

			const template = this.message_templates[message_type];

			if (template.eventsource == eventsource && template.recovery == recovery) {
				return {
					message_type: message_type,
					name: template.name
				};
			}
		}
	}

	/**
	 * Toggles the "Add" button state and changes its text depending on already added message templates to the table.
	 */
	#toggleAddButton() {
		const limit_reached = (
			Object.keys(this.message_template_list).length == Object.keys(this.message_templates).length
		);
		const add_button = this.form.querySelector('#message-templates-footer .btn-link');

		add_button.disabled = limit_reached;
		add_button.textContent = limit_reached
			? <?= json_encode(_('Add (message type limit reached)')) ?>
			: <?= json_encode(_('Add')) ?>;
	}

	/**
	 * Toggles the visibility and state of the change password button and password input field.
	 */
	#toggleChangePasswordButton() {
		if (this.form.querySelector('#chPass_btn') !== null) {
			this.form.querySelector('#chPass_btn').style.display = 'none';
			this.form.querySelector('#passwd').style.display = 'block';
			this.form.querySelector('#passwd').disabled = false;
			this.form.querySelector('#passwd').focus();
		}
	}

	/**
	 * Compiles necessary fields for popup based on mediatype object data.
	 *
	 * @param {object} mediatype  The media type object.
	 */
	#loadView(mediatype) {
		this.type = parseInt(mediatype.type);
		this.smtp_security = mediatype.smtp_security;
		this.authentication = mediatype.smtp_authentication;

		// Load type fields.
		this.form.querySelector('#type').onchange = (e) => {
			this.#hideFormFields('all');
			this.#loadTypeFields(e);

			this.form.querySelector('#smtp_authentication').dispatchEvent(new Event('change'));
			this.form.querySelector('#smtp_security').dispatchEvent(new Event('change'));
		};

		const max_sessions = this.form.querySelector('#maxsessions_type');

		this.max_session_checked = this.form.querySelector('input[name="maxsessions_type"]:checked').value;

		max_sessions.onchange = (e) => this.#toggleMaxSessionField(e);
	}

	/**
	 * Set concurrent sessions accessibility based on selected media type.
	 *
	 * @param {number} media_type  Selected media type.
	 */
	#setMaxSessionsType(media_type) {
		const maxsessions_type = this.form.querySelectorAll(`#maxsessions_type input[type='radio']`);

		maxsessions_type.forEach((radio) => {
			radio.checked = (radio.value === 'one');
			radio.disabled = (media_type == <?= MEDIA_TYPE_SMS ?> && radio.value !== 'one');
		});
	}

	/**
	 * Toggles the maxsession field based on concurrent sessions value.
	 *
	 * @param {object} event  The event object.
	 */
	#toggleMaxSessionField(event) {
		const concurrent_sessions = typeof event.target.value === 'undefined'
			? this.max_session_checked
			: event.target.value;

		const maxsessions = this.form.querySelector('#maxsessions');

		if (concurrent_sessions === 'one' || concurrent_sessions === 'unlimited') {
			maxsessions.style.display = 'none';
		}
		else {
			maxsessions.style.display = '';
			maxsessions.focus();
		}
	}

	/**
	 * Compiles necessary fields for popup based on type.
	 *
	 * @param {object} event  The event object.
	 */
	#loadTypeFields(event) {
		if (event.target.value) {
			this.type = parseInt(event.target.value);
		}

		let show_fields = [];

		switch (this.type) {
			case <?= MEDIA_TYPE_EMAIL ?>:
				show_fields = ['#email-provider-label', '#email-provider-field'];

				// Load provider fields.
				const provider = this.form.querySelector('#provider');

				provider.onchange = (e) => {
					const change = typeof e.detail === 'undefined' ? true : e.detail.change;

					this.#loadProviderFields(change, parseInt(provider.value));
				};

				const smtp_server = this.form.querySelector('#smtp_server');
				const smtp_email = this.form.querySelector('#smtp_email');

				smtp_server.value = this.mediatype.smtp_server === '' ? this.smtp_server_default : smtp_server.value;
				smtp_email.value = this.mediatype.smtp_email === '' ? this.smtp_email_default : smtp_email.value;
				this.mediatype.smtp_server = smtp_server.value;
				this.mediatype.smtp_email = smtp_email.value;

				provider.dispatchEvent(new CustomEvent('change', {detail: {change: false}}));
				break;

			case <?= MEDIA_TYPE_SMS ?>:
				show_fields = ['#gsm_modem_label', '#gsm_modem_field'];

				const gsm_modem = this.form.querySelector('#gsm_modem');

				gsm_modem.value = this.mediatype.gsm_modem === '' ? '/dev/ttyS0' : gsm_modem.value;
				this.mediatype.gsm_modem = gsm_modem.value;
				break;

			case <?= MEDIA_TYPE_EXEC ?>:
				show_fields = ['#exec-path-label', '#exec-path-field', '#row_exec_params_label',
					'#row_exec_params_field'
				];
				break;

			case <?= MEDIA_TYPE_WEBHOOK ?>:
				show_fields = ['#webhook_parameters_label', '#webhook_parameters_field', '#webhook_script_label',
					'#webhook_script_field', '#webhook_timeout_label', '#webhook_timeout_field', '#webhook_tags_label',
					'#webhook_tags_field', '#webhook_event_menu_label', '#webhook_event_menu_field',
					'#webhook_url_name_label', '#webhook_url_name_field', '#webhook_event_menu_url_label',
					'#webhook_event_menu_url_field'
				];
				break;
		}

		if (typeof event.detail === 'undefined' || this.type == <?= MEDIA_TYPE_SMS ?>) {
			this.max_session_checked = 'one';
			this.#setMaxSessionsType(this.type);
		}

		show_fields.forEach((field) => this.form.querySelector(field).style.display = '');
		this.form.querySelector('#maxsessions_type').dispatchEvent(new Event('change'));
	}

	/**
	 * Compiles necessary fields for popup based on provider value.
	 *
	 * @param {string} change    Indicates whether the provider field value has changed.
	 * @param {number} provider  Media type provider.
	 */
	#loadProviderFields(change, provider) {
		let show_fields = [];

		this.#hideFormFields('email');

		if (change) {
			const providers = this.mediatype.providers;

			this.form.querySelector('#smtp_username').value = '';
			this.form.querySelector('#smtp_verify_host').checked = providers[provider]['smtp_verify_host'];
			this.form.querySelector('#smtp_verify_peer').checked = providers[provider]['smtp_verify_peer'];
			this.form.querySelector('#smtp_port').value = providers[provider]['smtp_port'];
			this.form.querySelector('#smtp_email').value = providers[provider]['smtp_email'];
			this.form.querySelector('#smtp_server').value = providers[provider]['smtp_server'];
			this.form.querySelector(`input[name=smtp_security][value='${providers[provider]['smtp_security']}']`)
				.checked = true;
			this.form.querySelector(`input[name=message_format][value='${providers[provider]['message_format']}']`)
				.checked = true;
			this.form.querySelector(
				`input[name=smtp_authentication][value='${providers[provider]['smtp_authentication']}']`
			).checked = true;
		}

		const authentication = this.form.querySelector('#smtp_authentication');

		authentication.onchange = () => this.#loadAuthenticationFields(provider);

		this.#loadAuthenticationFields(provider);

		switch (provider) {
			case <?= CMediatypeHelper::EMAIL_PROVIDER_SMTP ?>:
				show_fields = ['#email-provider-label', '#email-provider-field', '#smtp-server-label',
					'#smtp-server-field', '#smtp-port-label', '#smtp-port-field', '#smtp-email-label',
					'#smtp-email-field', '#smtp-helo-label', '#smtp-helo-field', '#smtp-security-label',
					'#smtp-security-field', '#smtp-authentication-label', '#smtp-authentication-field',
					'#message_format_label', '#message_format_field'
				];

				const smtp_security = this.form.querySelector('#smtp_security');

				smtp_security.onchange = () => this.#loadSmtpSecurityFields();
				this.#loadSmtpSecurityFields();
				break;

			case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL ?>:
			case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365 ?>:
				show_fields = ['#smtp-email-label', '#smtp-email-field', '#passwd_label', '#passwd_field',
					'#message_format_label', '#message_format_field'
				];
				break;

			case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY ?>:
			case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY ?>:
				show_fields = ['#smtp-email-label', '#smtp-email-field', '#smtp-authentication-label',
					'#smtp-authentication-field', '#message_format_label', '#message_format_field'
				];
				break;
		}

		show_fields.forEach((field) => this.form.querySelector(field).style.display = '');
	}

	/**
	 * Compiles necessary fields for popup based on smtp_security value.
	 */
	#loadSmtpSecurityFields() {
		const smtp_security = this.form.querySelector('input[name="smtp_security"]:checked').value;

		if (this.type == <?= MEDIA_TYPE_EMAIL ?>) {
			switch (parseInt(smtp_security)) {
				case <?= SMTP_SECURITY_NONE ?>:
					this.form.querySelector('#smtp_verify_peer').checked = false;
					this.form.querySelector('#smtp_verify_host').checked = false;

					const hide_fields = ['#verify-peer-label', '#verify-peer-field', '#verify-host-label',
						'#verify-host-field'
					];

					hide_fields.forEach((field) => this.form.querySelector(field).style.display = 'none');
					break;

				case <?= SMTP_SECURITY_STARTTLS ?>:
				case <?= SMTP_SECURITY_SSL ?>:
					const show_fields = ['#verify-peer-label', '#verify-peer-field', '#verify-host-label',
						'#verify-host-field'
					];

					show_fields.forEach((field) => this.form.querySelector(field).style.display = '');
					break;
			}
		}
	}

	/**
	 * Compiles necessary fields for popup based on smtp_authentication value.
	 *
	 * @param {number} provider  Media type provider.
	 */
	#loadAuthenticationFields(provider) {
		const authentication = this.form.querySelector('input[name="smtp_authentication"]:checked').value;
		const passwd_label = this.form.querySelector('#passwd_label');
		const passwd = this.form.querySelector('#passwd');
		const smtp_auth_1 = this.form.querySelector('label[for="smtp_authentication_1"]');

		passwd_label.setAttribute('class', '<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
		passwd.setAttribute('aria-required', 'true');

		if (this.type == <?= MEDIA_TYPE_EMAIL ?>) {
			switch (parseInt(provider)) {
				case <?= CMediatypeHelper::EMAIL_PROVIDER_SMTP ?>:
					smtp_auth_1.innerHTML = <?= json_encode(_('Username and password')) ?>;
					passwd_label.removeAttribute('class', '<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
					passwd.removeAttribute('aria-required', 'true');

					switch (parseInt(authentication)) {
						case <?= SMTP_AUTHENTICATION_NONE ?>:
							this.form.querySelector('#passwd').value = '';
							this.form.querySelector('#smtp_username').value = '';

							const hide_fields = ['#smtp-username-label', '#smtp-username-field', '#passwd_label',
								'#passwd_field'
							];

							hide_fields.forEach((field) => this.form.querySelector(field).style.display = 'none');
							break;

						case <?= SMTP_AUTHENTICATION_NORMAL ?>:
							const show_fields = ['#smtp-username-label', '#smtp-username-field', '#passwd_label',
								'#passwd_field'
							];

							show_fields.forEach((field) => this.form.querySelector(field).style.display = '');
							break;
					}
					break;

				case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY ?>:
				case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY ?>:
					smtp_auth_1.innerHTML = <?= json_encode(_('Email and password')) ?>;

					switch (parseInt(authentication)) {
						case <?= SMTP_AUTHENTICATION_NONE ?>:
							this.form.querySelector('#passwd').value = '';

							const hide_fields = ['#smtp-username-label', '#smtp-username-field', '#passwd_label',
								'#passwd_field'
							];

							hide_fields.forEach((field) => this.form.querySelector(field).style.display = 'none');
							break;

						case <?= SMTP_AUTHENTICATION_NORMAL ?>:
							const show_fields = ['#passwd_label', '#passwd_field'];

							show_fields.forEach((field) => this.form.querySelector(field).style.display = '');
							break;
					}
			}
		}
	}

	/**
	 * Hides the specified fields from the form based on input parameter type.
	 *
	 * @param {string} type  A string indicating the type of fields to hide.
	 */
	#hideFormFields(type) {
		let fields = [];

		if (type === 'email') {
			fields = ['#smtp-username-label', '#smtp-username-field', '#smtp-server-label', '#smtp-server-field',
				'#smtp-port-label', '#smtp-port-field', '#smtp-email-label', '#smtp-email-field', '#smtp-helo-label',
				'#smtp-helo-field', '#smtp-security-label', '#smtp-security-field', '#verify-peer-label',
				'#verify-peer-field', '#verify-host-label', '#verify-host-field', '#passwd_label', '#passwd_field',
				'#smtp-authentication-label', '#smtp-authentication-field'
			];
		}
		else if (type === 'all') {
			fields = ['#email-provider-label', '#email-provider-field', '#smtp-server-label', '#smtp-server-field',
				'#smtp-port-label', '#smtp-port-field', '#smtp-email-label', '#smtp-email-field', '#smtp-helo-label',
				'#smtp-helo-field', '#smtp-security-label', '#smtp-security-field', '#verify-peer-label',
				'#verify-peer-field', '#verify-host-label', '#verify-host-field', '#smtp-authentication-label',
				'#smtp-authentication-field', '#smtp-username-label', '#smtp-username-field', '#exec-path-label',
				'#exec-path-field', '#row_exec_params_label', '#row_exec_params_field', '#gsm_modem_label',
				'#gsm_modem_field', '#passwd_label', '#passwd_field', '#message_format_label', '#message_format_field',
				'#webhook_parameters_label', '#webhook_parameters_field', '#webhook_script_label',
				'#webhook_script_field', '#webhook_timeout_label', '#webhook_timeout_field', '#webhook_tags_label',
				'#webhook_tags_field', '#webhook_event_menu_label', '#webhook_event_menu_field',
				'#webhook_url_name_label', '#webhook_url_name_field', '#webhook_event_menu_url_label',
				'#webhook_event_menu_url_field'
			];
		}

		fields.forEach((field) => this.form.querySelector(field).style.display = 'none');
	}
}
