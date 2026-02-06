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

window.mediatype_edit_popup = new class {

	init({rules, clone_rules, mediatype, message_templates, smtp_server_default, smtp_email_default,
			oauth_defaults_by_provider}) {
		this.overlay = overlays_stack.getById('mediatype.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.clone_rules = clone_rules;
		this.mediatypeid = mediatype.mediatypeid;
		this.mediatype = mediatype;
		this.row_nums = {exec: 0, webhook: 0};
		this.message_template_list = {};
		this.message_templates = Object.fromEntries(message_templates.map((obj, index) => [index, { ...obj }]));
		this.smtp_server_default = smtp_server_default;
		this.smtp_email_default = smtp_email_default;
		this.oauth_defaults_by_provider = oauth_defaults_by_provider;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'mediatype.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.#loadView(mediatype);
		this.#initEvents();
		this.form_element.style.display = '';
		this.overlay.recoverFocus();

		this.form_element.querySelector('#type').dispatchEvent(new CustomEvent('change', {detail: {init: true}}));
	}

	#initEvents() {
		for (const parameter of this.mediatype.parameters_webhook) {
			this.#addWebhookParam(parameter);
		}

		for (const parameter of this.mediatype.parameters_exec) {
			this.#addExecParam(parameter);
		}

		this.#populateMessageTemplates(this.mediatype['message_templates']);

		this.form_element.querySelector('#message-templates').addEventListener('click', (event) =>
			this.#editMessageTemplate(event)
		);

		this.form_element.querySelector('.element-table-add').addEventListener('click', () => {
			this.#addExecParam();
		});

		this.form_element.querySelector('.webhook-param-add').addEventListener('click', () => this.#addWebhookParam());

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

		this.overlay.$dialogue.$footer[0].querySelector('.js-submit')
			.addEventListener('click', () => this.#submit());

		this.overlay.$dialogue.$footer[0].querySelector('.js-clone')
			?.addEventListener('click', () => this.#clone());

		this.overlay.$dialogue.$footer[0].querySelector('.js-delete')
			?.addEventListener('click', () => this.#delete());

		if (this.form_element.querySelector('#chPass_btn') !== null) {
			this.form_element.querySelector('#chPass_btn').addEventListener('click', () => this.#toggleChangePasswordButton());
		}

		const event_menu = this.form_element.querySelector('#show_event_menu');

		event_menu.onchange = () => {
			const event_menu_name = this.form_element.querySelector('#event_menu_name');
			const event_menu_url = this.form_element.querySelector('#event_menu_url');

			if (event_menu.checked) {
				event_menu_name.classList.remove('js-inactive');
				event_menu_url.classList.remove('js-inactive');
				event_menu_name.disabled = false;
				event_menu_url.disabled = false;
			}
			else {
				event_menu_name.classList.add('js-inactive');
				event_menu_url.classList.add('js-inactive');
				event_menu_name.disabled = true;
				event_menu_url.disabled = true;
			}
		}

		this.form_element.querySelector('#js-oauth-configure').addEventListener('click', () => {
			const oauth_fields = ['redirection_url', 'client_id', 'client_secret', 'authorization_url', 'token_url'];
			const fields = this.form.getAllValues();
			let oauth = Object.fromEntries(
				Object.entries(fields).filter(([key]) => oauth_fields.includes(key))
			);

			if (!Object.keys(oauth).length) {
				oauth = {...this.oauth_defaults_by_provider[fields.provider], client_secret: ''};
			}

			oauth.tokens_status = fields.tokens_status;

			let data = {
				update: 'client_id' in fields ? 1 : 0,
				advanced_form: fields.provider == <?= CMediatypeHelper::EMAIL_PROVIDER_SMTP ?> ? 1 : 0
			};

			if ('mediatypeid' in fields) {
				data.mediatypeid = fields.mediatypeid;
			}

			const overlay = PopUp('oauth.edit', {...data, ...oauth}, {dialogue_class: 'modal-popup-generic'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => this.#setOAuth(e.detail));
		});
	}

	#clone() {
		this.mediatypeid = null;
		document.getElementById('mediatypeid').remove();
		this.#setOAuth();

		const title = <?= json_encode(_('New media type')) ?>;
		const buttons = [
			{
				title: <?= json_encode(_('Add')) ?>,
				class: 'js-submit',
				keepOpen: true,
				isSubmit: true
			},
			{
				title: <?= json_encode(_('Cancel')) ?>,
				class: ZBX_STYLE_BTN_ALT,
				cancel: true,
				action: ''
			}
		];

		this.#toggleChangePasswordButton();
		this.overlay.setProperties({title, buttons});

		this.overlay.$dialogue.$footer[0].querySelector('.js-submit')
			.addEventListener('click', () => this.#submit());

		this.overlay.unsetLoading();
		this.overlay.recoverFocus();
		this.overlay.containFocus();
		this.form.reload(this.clone_rules);
	}

	#delete() {
		if (window.confirm(<?= json_encode(_('Delete media type?')) ?>)) {
			this.#removePopupMessages();
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'mediatype.delete');
			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('mediatype')) ?>);

			this.#post(curl.getUrl(), {mediatypeids: [this.mediatypeid]}, (response) => {
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			});
		}
		else {
			this.overlay.unsetLoading();
		}
	}

	#submit() {
		this.#removePopupMessages();
		const fields = this.form.getAllValues();

		switch (fields.maxsessions_type) {
			case 'custom':
				break;

			case 'one':
			default:
				fields.maxsessions = 1;
				break;

			case 'unlimited':
				fields.maxsessions = 0;
				break;
		}

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();
					return;
				}

				const curl = new Curl('zabbix.php');
				const action = document.getElementById('mediatypeid') !== null
					? 'mediatype.update'
					: 'mediatype.create';

				curl.setArgument('action', action);

				this.#post(curl.getUrl(), fields, (response) => {
					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				});
			});
	}

	/**
	 * Set form OAuth input fields and UI status message.
	 *
	 * @param {object} oauth  Key value pair of OAuth fields and oauth status message.
	 */
	#setOAuth(oauth = {}) {
		const status_container = this.form_element.querySelector('#js-oauth-status');

		if (!('tokens_status' in oauth)) {
			oauth.tokens_status = 0;
		}

		status_container.innerText = '';
		status_container.style.display = 'none';

		if ('message' in oauth) {
			const italic = document.createElement('em');

			italic.innerText = oauth.message;
			status_container.append(italic);
			status_container.style.display = '';

			delete oauth.message;
		}

		const oauth_fields = ['tokens_status', 'redirection_url', 'client_id', 'client_secret', 'authorization_url',
			'token_url', 'access_token', 'access_token_updated', 'access_expires_in', 'refresh_token'
		];

		oauth_fields.forEach((field) => {
			if (field in oauth) {
				const input = document.createElement('input');

				input.name = field;
				input.type = 'hidden';
				input.value = oauth[field];
				input.setAttribute('data-field-type', 'hidden');
				input.setAttribute('data-error-container', 'oauth-error-container');

				status_container.append(input);
			}
		});

		this.form.discoverAllFields();
		document.getElementById('oauth-error-container').innerHTML = '';
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

				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();

					return;
				}

				return success_callback(response);
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.overlay.unsetLoading());
	}

	/**
	 * Adds a new row to the Parameters table with the given webhook parameter data (name, value).
	 *
	 * @param {object} parameter  An object containing the webhook parameter data.
	 */
	#addWebhookParam(parameter = {}) {
		parameter.row_num = this.row_nums.webhook;
		const template = new Template(this.form_element.querySelector('#webhook_params_template').innerHTML);

		this.form_element
			.querySelector('#parameters_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(parameter));

		this.row_nums.webhook++;
	}

	/**
	 * Adds a new row to the Script parameters table with the given script parameter data (value).
	 *
	 * @param {object} parameter  An object containing the script parameter data.
	 */
	#addExecParam(parameter = {}) {
		parameter.row_num = this.row_nums.exec;

		const template = new Template(this.form_element.querySelector('#exec_params_template').innerHTML);

		this.form_element
			.querySelector('#exec_params_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(parameter));

		this.row_nums.exec++;
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
				type: this.form_element.querySelector('#type').value,
				message_format: this.form_element.querySelector('input[name="message_format"]:checked').value,
				message_types: [...this.form_element.querySelectorAll('tr[data-message-type]')].map((tr) =>
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
		const template = new Template(this.form_element.querySelector('#message-templates-row-tmpl').innerHTML);

		if (row === null) {
			this.form_element
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
		const add_button = this.form_element.querySelector('#message-templates-footer .btn-link');

		add_button.disabled = limit_reached;
		add_button.textContent = limit_reached
			? <?= json_encode(_('Add (message type limit reached)')) ?>
			: <?= json_encode(_('Add')) ?>;
	}

	/**
	 * Toggles the visibility and state of the change password button and password input field.
	 */
	#toggleChangePasswordButton() {
		if (this.form_element.querySelector('#chPass_btn') !== null) {
			this.form_element.querySelector('#chPass_btn').style.display = 'none';
			this.form_element.querySelector('#passwd').style.display = 'block';
			this.form_element.querySelector('#passwd').classList.remove('js-inactive');
			this.form_element.querySelector('#passwd').disabled = false;
			this.form_element.querySelector('#passwd').focus();
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
		this.form_element.querySelector('#type').onchange = (e) => {
			this.#hideFormFields('all');
			this.#loadTypeFields(e);

			this.form_element.querySelector('#smtp_authentication').dispatchEvent(new Event('change'));
			this.form_element.querySelector('#smtp_security').dispatchEvent(new Event('change'));
		};

		const max_sessions = this.form_element.querySelector('#maxsessions_type');

		this.max_session_checked = this.form_element.querySelector('input[name="maxsessions_type"]:checked').value;

		max_sessions.onchange = (e) => this.#toggleMaxSessionField(e);
	}

	/**
	 * Set concurrent sessions accessibility based on selected media type.
	 *
	 * @param {number} media_type  Selected media type.
	 */
	#setMaxSessionsType(media_type) {
		const maxsessions_type = this.form_element.querySelectorAll(`#maxsessions_type input[type='radio']`);

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

		const maxsessions = this.form_element.querySelector('#maxsessions');

		if (concurrent_sessions === 'one' || concurrent_sessions === 'unlimited') {
			maxsessions.style.display = 'none';
			maxsessions.disabled = true;
		}
		else {
			maxsessions.style.display = '';
			maxsessions.disabled = false;
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
				const provider = this.form_element.querySelector('#provider');

				provider.onchange = (e) => {
					const change = typeof e.detail === 'undefined' ? true : e.detail.change;

					this.#loadProviderFields(change, parseInt(provider.value));
				};

				const smtp_server = this.form_element.querySelector('#smtp_server');
				const smtp_email = this.form_element.querySelector('#smtp_email');

				smtp_server.value = this.mediatype.smtp_server === '' ? this.smtp_server_default : smtp_server.value;
				smtp_email.value = this.mediatype.smtp_email === '' ? this.smtp_email_default : smtp_email.value;
				this.mediatype.smtp_server = smtp_server.value;
				this.mediatype.smtp_email = smtp_email.value;

				provider.dispatchEvent(new CustomEvent('change', {detail: {change: false}}));
				break;

			case <?= MEDIA_TYPE_SMS ?>:
				show_fields = ['#gsm_modem_label', '#gsm_modem_field'];

				const gsm_modem = this.form_element.querySelector('#gsm_modem');

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

		this.#showAndEnableFormElements(show_fields);
		this.form_element.querySelector('#maxsessions_type').dispatchEvent(new Event('change'));
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

			this.form_element.querySelector('#smtp_username').value = '';
			this.form_element.querySelector('#smtp_verify_host').checked = providers[provider]['smtp_verify_host'];
			this.form_element.querySelector('#smtp_verify_peer').checked = providers[provider]['smtp_verify_peer'];
			this.form_element.querySelector('#smtp_port').value = providers[provider]['smtp_port'];
			this.form_element.querySelector('#smtp_email').value = providers[provider]['smtp_email'];
			this.form_element.querySelector('#smtp_server').value = providers[provider]['smtp_server'];
			this.form_element.querySelector(`input[name=smtp_security][value='${providers[provider]['smtp_security']}']`)
				.checked = true;
			this.form_element.querySelector(`input[name=message_format][value='${providers[provider]['message_format']}']`)
				.checked = true;
			this.form_element.querySelector(
				`input[name=smtp_authentication][value='${providers[provider]['smtp_authentication']}']`
			).checked = true;
			this.#setOAuth();

			this.form.validateChanges(['smtp_username', 'smtp_port', 'smtp_email', 'smtp_server']);
		}

		const authentication = this.form_element.querySelector('#smtp_authentication');

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

				const smtp_security = this.form_element.querySelector('#smtp_security');

				smtp_security.onchange = () => this.#loadSmtpSecurityFields();
				this.#loadSmtpSecurityFields();
				break;

			case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL ?>:
			case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365 ?>:
				show_fields = ['#smtp-email-label', '#smtp-email-field', '#passwd_label', '#passwd_field',
					'#message_format_label', '#message_format_field', '#smtp-authentication-label',
					'#smtp-authentication-field'
				];
				break;

			case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY ?>:
			case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY ?>:
				show_fields = ['#smtp-email-label', '#smtp-email-field', '#smtp-authentication-label',
					'#smtp-authentication-field', '#message_format_label', '#message_format_field'
				];
				break;
		}

		this.#showAndEnableFormElements(show_fields);
	}

	#hideAndDisableFormElements(hide_fields) {
		hide_fields.forEach((field) => {
			const element = this.form_element.querySelector(field);

			if (element) {
				element.style.display = 'none';

				if (element.classList.contains('form-field')) {
					element.querySelectorAll('.multilineinput-control, input:not(.js-inactive), select, textarea')
						.forEach((input) => {
							input.disabled = true;
					});
				}
			}
		});
	}

	#showAndEnableFormElements(show_fields) {
		show_fields.forEach((field) => {
			const element = this.form_element.querySelector(field);

			if (element) {
				element.style.display = '';

				if (element.classList.contains('form-field')) {
					element.querySelectorAll('.multilineinput-control, input:not(.js-inactive), select, textarea')
						.forEach((input) => {
							input.disabled = false;
					});
				}
			}
		});
	}

	/**
	 * Compiles necessary fields for popup based on smtp_security value.
	 */
	#loadSmtpSecurityFields() {
		const smtp_security = this.form_element.querySelector('input[name="smtp_security"]:checked').value;

		if (this.type == <?= MEDIA_TYPE_EMAIL ?>) {
			switch (parseInt(smtp_security)) {
				case <?= SMTP_SECURITY_NONE ?>:
					this.form_element.querySelector('#smtp_verify_peer').checked = false;
					this.form_element.querySelector('#smtp_verify_host').checked = false;

					this.#hideAndDisableFormElements(['#verify-peer-label', '#verify-peer-field', '#verify-host-label',
						'#verify-host-field'
					]);
					break;

				case <?= SMTP_SECURITY_STARTTLS ?>:
				case <?= SMTP_SECURITY_SSL ?>:
					this.#showAndEnableFormElements(['#verify-peer-label', '#verify-peer-field', '#verify-host-label',
						'#verify-host-field'
					]);
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
		const authentication = this.form_element.querySelector('input[name="smtp_authentication"]:checked').value;
		const passwd_label = this.form_element.querySelector('#passwd_label');
		const passwd = this.form_element.querySelector('#passwd');
		const smtp_auth_1 = this.form_element.querySelector('label[for="smtp_authentication_1"]');
		const smtp_none = this.form_element.querySelector('[name="smtp_authentication"][value="<?= SMTP_AUTHENTICATION_NONE ?>"]');
		const smtp_oauth = this.form_element.querySelector('[name="smtp_authentication"][value="<?= SMTP_AUTHENTICATION_OAUTH ?>"]');

		passwd_label.setAttribute('class', '<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
		passwd.setAttribute('aria-required', 'true');

		if (this.type == <?= MEDIA_TYPE_EMAIL ?>) {
			smtp_oauth.classList.remove('js-inactive');
			smtp_none.classList.remove('js-inactive');

			switch (parseInt(provider)) {
				case <?= CMediatypeHelper::EMAIL_PROVIDER_SMTP ?>:
					smtp_auth_1.innerHTML = <?= json_encode(_('Username and password')) ?>;
					passwd_label.removeAttribute('class', '<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
					passwd.removeAttribute('aria-required', 'true');

					switch (parseInt(authentication)) {
						case <?= SMTP_AUTHENTICATION_NONE ?>:
							this.form_element.querySelector('#passwd').value = '';
							this.form_element.querySelector('#smtp_username').value = '';

							this.#hideAndDisableFormElements(['#smtp-username-label', '#smtp-username-field',
								'#passwd_label', '#passwd_field', '#oauth-token-label', '#oauth-token-field'
							]);
							break;

						case <?= SMTP_AUTHENTICATION_PASSWORD ?>:
							this.#showAndEnableFormElements(['#smtp-username-label', '#smtp-username-field',
								'#passwd_label', '#passwd_field'
							]);
							this.#hideAndDisableFormElements(['#oauth-token-label', '#oauth-token-field']);
							break;

						case <?= SMTP_AUTHENTICATION_OAUTH ?>:
							this.#hideAndDisableFormElements(['#smtp-username-label', '#smtp-username-field',
								'#passwd_label', '#passwd_field'
							]);
							this.#showAndEnableFormElements(['#oauth-token-label', '#oauth-token-field']);

							break;
					}
					break;

				case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL ?>:
				case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365 ?>:
					smtp_none.setAttribute('disabled', 'disabled');
					smtp_none.classList.add('js-inactive');

					switch (parseInt(authentication)) {
						case <?= SMTP_AUTHENTICATION_PASSWORD ?>:
							this.#showAndEnableFormElements(['#passwd_label', '#passwd_field']);
							this.#hideAndDisableFormElements(['#oauth-token-label', '#oauth-token-field']);
							break;

						case <?= SMTP_AUTHENTICATION_OAUTH ?>:
							this.#hideAndDisableFormElements(['#smtp-username-label', '#smtp-username-field',
								'#passwd_label', '#passwd_field'
							]);
							this.#showAndEnableFormElements(['#oauth-token-label', '#oauth-token-field']);
							break;
					}
					break;

				case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY ?>:
				case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY ?>:
					smtp_auth_1.innerHTML = <?= json_encode(_('Email and password')) ?>;

					if (parseInt(provider) == <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY ?>) {
						smtp_oauth.setAttribute('disabled', 'disabled');
						smtp_oauth.classList.add('js-inactive');
					}

					this.#hideAndDisableFormElements(['#oauth-token-label', '#oauth-token-field']);

					switch (parseInt(authentication)) {
						case <?= SMTP_AUTHENTICATION_NONE ?>:
							this.form_element.querySelector('#passwd').value = '';

							this.#hideAndDisableFormElements(['#smtp-username-label', '#smtp-username-field',
								'#passwd_label', '#passwd_field'
							]);
							break;

						case <?= SMTP_AUTHENTICATION_PASSWORD ?>:
							this.#showAndEnableFormElements(['#passwd_label', '#passwd_field']);
							break;

						case <?= SMTP_AUTHENTICATION_OAUTH ?>:
							this.#hideAndDisableFormElements(['#smtp-username-label', '#smtp-username-field',
								'#passwd_label', '#passwd_field'
							]);
							this.#showAndEnableFormElements(['#oauth-token-label', '#oauth-token-field']);
							break;
					}
					break;
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
				'#webhook_event_menu_url_field', '#oauth-token-label', '#oauth-token-field'
			];
		}

		this.#hideAndDisableFormElements(fields);
	}

	#removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
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

		this.form_element.parentNode.insertBefore(message_box, this.form_element);
	}
};
