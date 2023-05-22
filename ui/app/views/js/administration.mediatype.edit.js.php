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
 * @var array $data
 */
?>

window.mediatype_edit_popup = new class {

	init({mediatype}) {
		this.overlay = overlays_stack.getById('media-type-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.mediatypeid = mediatype.mediatypeid;
		this.mediatype = mediatype;
		this.row_num = 0;
		this.message_template_list = {};
		this.message_templates = <?= json_encode(CMediatypeHelper::getAllMessageTemplates(), JSON_FORCE_OBJECT) ?>;

		this._loadView(mediatype);
		this._initActions();

		this.form.querySelector('#type').dispatchEvent(new Event('change'));
	}

	_initActions() {
		this._addParameterData();
		this._populateMessageTemplates(<?= json_encode(array_values($data['message_templates'])) ?>);

		this.form.querySelector('#message-templates').addEventListener('click', (event) => {
			this._editMessageTemplate(event);
		});

		this.form.querySelector('.element-table-add').addEventListener('click', () => {
			this._addExecParam();
			this.row_num ++;
		});

		this.form.querySelector('.webhook-param-add').addEventListener('click', () => this._addWebhookParam());

		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-remove')) {
				e.target.closest('tr').remove();
			}
			else if (e.target.classList.contains('js-remove-msg-template')) {
				this._removeMessageTemplate(e);
			}
		});

		if (this.form.querySelector('#chPass_btn') !== null) {
			this.form.querySelector('#chPass_btn').addEventListener('click', () => this._toggleChangePswdButton());
		}

		const event_menu = this.form.querySelector('#show_event_menu');

		event_menu.onchange = () => this._toggleEventMenuFields(event_menu);
		event_menu.dispatchEvent(new Event('change'));
	}

	clone({title, buttons}) {
		this.mediatypeid = null;

		this._toggleChangePswdButton();
		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
		this.overlay.recoverFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'mediatype.delete');
		curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
			<?= json_encode(CCsrfTokenHelper::get('mediatype'), JSON_THROW_ON_ERROR) ?>
		);

		this._post(curl.getUrl(), {mediatypeids: [this.mediatypeid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response.success}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		// Trim all string type fields.
		for (let key in fields) {
			if (typeof fields[key] === 'string') {
				fields[key] = fields[key].trim();
			}
		}

		// Trim all string values within the 'parameters_webhook' object.
		if (typeof fields.parameters_webhook !== 'undefined') {
			fields.parameters_webhook.name = fields.parameters_webhook.name.map(name => name.trim());
			fields.parameters_webhook.value = fields.parameters_webhook.value.map(value => value.trim());
		}

		// Trim all string values within the 'parameters_exec' object.
		if (typeof fields.parameters_exec !== 'undefined') {
			Object.keys(fields.parameters_exec).forEach((key) => {
				Object.values(key).forEach(param => {
					fields.parameters_exec[param].value = fields.parameters_exec[param].value.trim();
				});
			});
		}

		// Set maxsessions value.
		const maxsessions_type = this.form.querySelector(`input[name='maxsessions_type']:checked`).value;
		const maxsessions = this.form.querySelector('#maxsessions').value;

		if (maxsessions_type === 'one') {
			fields.maxsessions = 1;
		}
		else if (maxsessions_type === 'custom' && maxsessions.trim() === '') {
			fields.maxsessions = 0;
		}

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.mediatypeid === null ? 'mediatype.create' : 'mediatype.update');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
		});
	}

	_post(url, data, success_callback) {
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
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	_addParameterData() {
		for (const parameter of this.mediatype.parameters_webhook) {
			this._addWebhookParam(parameter);
		}

		for (const parameter of this.mediatype.parameters_exec) {
			this._addExecParam(parameter);
			this.row_num ++;
		}
	}

	_removeMessageTemplate(event) {
		event.target.closest('tr').remove();
		delete this.message_template_list[event.target.closest('tr').getAttribute('data-message-type')];
		this._toggleAddButton();
	}

	_addWebhookParam(parameter = {}) {
		const template = new Template(this.form.querySelector('#webhook_params_template').innerHTML);

		this.form
			.querySelector('#parameters_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(parameter));
	}

	_addExecParam(parameter = {}) {
		parameter.row_num = this.row_num;
		const template = new Template(this.form.querySelector('#exec_params_template').innerHTML);

		this.form
			.querySelector('#exec_params_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(parameter));
	}

	_editMessageTemplate(event) {
		const target = event.target;
		let row = null;

		if (target.hasAttribute('data-action')) {
			const btn = target;
			const params = {
				type: this.form.querySelector('#type').value,
				content_type: this.form.querySelector(`input[name='content_type']:checked`).value,
				message_types: Array.from(this.form.querySelectorAll('tr[data-message-type]')).map((tr) => {
					return tr.dataset.messageType;
				})
			};

			if (btn.dataset.action === 'edit') {
				row = btn.closest('tr');

				params.message_type = row.dataset.messageType;
				params.old_message_type = params.message_type;

				Array.from(row.querySelectorAll(`input[type='hidden']`)).forEach((input) => {
					const name = input.getAttribute('name').match(/\[([^\]]+)]$/);

					if (name) {
						params[name[1]] = input.value;
					}
				});
			}

			const overlay = PopUp('mediatype.message.edit', params, {
				dialogue_class: 'modal-popup-medium',
				dialogueid: 'mediatype-message-form',
				trigger_element: target,
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('message.submit', (e) => {
				if (row !== null) {
					this._addMessageTemplateRow(e.detail, row);
					row.remove();
				}
				else {
					this._addMessageTemplateRow(e.detail);
				}
			});
		}
	}

	_addMessageTemplateRow(input, row = null) {
		const template = new Template(this.form.querySelector('#message-templates-row-tmpl').innerHTML);

		if (row === null) {
			this.form
				.querySelector('#message-templates tbody')
				.insertAdjacentHTML('beforeend', template.evaluate(input));
		}
		else {
			row.insertAdjacentHTML('afterend', template.evaluate(input));
		}

		this.message_template_list[input.message_type] = input;
		this._toggleAddButton();
	}

	/**
	 * Adds data to message template table upon media type configuration form opening.
	 *
	 * @param {array} list  An array of message templates.
	 */
	_populateMessageTemplates(list) {
		for (const key in list) {
			if (!Object.prototype.hasOwnProperty.call(list, key)) {
				continue;
			}

			const template = list[key];
			const message_template = this._getMessageTemplate(template.eventsource, template.recovery);

			template.message_type = message_template.message_type;
			template.message_type_name = message_template.name;

			this._addMessageTemplateRow(template);
			this.message_template_list[template.message_type] = template;
		}

		this._toggleAddButton();
	}

	/**
	 * Returns message type and name by the specified event source and operation mode.
	 *
	 * @param {number} eventsource  Event source.
	 * @param {number} recovery     Operation mode.
	 *
	 * @return {object}
	 */
	_getMessageTemplate(eventsource, recovery) {
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
	_toggleAddButton() {
		const limit_reached = (
			Object.keys(this.message_template_list).length === Object.keys(this.message_templates).length
		);

		const linkBtn = this.form.querySelector('#message-templates-footer .btn-link');
		linkBtn.disabled = limit_reached;
		linkBtn.textContent = limit_reached
			? <?= json_encode(_('Add (message type limit reached)')) ?>
			: <?= json_encode(_('Add')) ?>;
	}

	_toggleChangePswdButton() {
		if (this.form.querySelector('#chPass_btn') !== null) {
			this.form.querySelector('#chPass_btn').style.display = 'none';
			this.form.querySelector('#passwd').style.display = 'block';
			this.form.querySelector('#passwd').disabled = false;
			this.form.querySelector('#passwd').focus();
		}
	}

	_toggleEventMenuFields(event_menu) {
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

	_loadView(mediatype) {
		this.type = parseInt(mediatype.type);
		this.smtp_security = mediatype.smtp_security;
		this.authentication = mediatype.smtp_authentication;

		// Load type fields.
		this.form.querySelector('#type').onchange = (e) => {
			this._hideFormFields('all');
			this._loadTypeFields(e);

			this.form.querySelector('#smtp_authentication').dispatchEvent(new Event('change'));
			this.form.querySelector('#smtp_security').dispatchEvent(new Event('change'));
		};

		const max_sessions = this.form.querySelector('#maxsessions_type');
		this.max_session_checked = this.form.querySelector(`input[name='maxsessions_type']:checked`).value;

		max_sessions.onchange = (e) => {
			this._toggleMaxSessionField(e);
		};

		max_sessions.dispatchEvent(new Event('change'));
	}

	/**
	 * Set concurrent sessions accessibility based on selected media type.
	 *
	 * @param {number} media_type  Selected media type.
	 */
	_setMaxSessionsType(media_type) {
		const maxsessions_type = this.form.querySelectorAll(`#maxsessions_type input[type='radio']`);

		if (media_type == <?= MEDIA_TYPE_SMS ?>) {
			maxsessions_type.forEach((radio) => {
				if (radio.value === 'one') {
					radio.checked = true;
					radio.disabled = false;
				}
				else {
					radio.disabled = true;
					radio.checked = false;
				}
			});
		}
		else {
			maxsessions_type.forEach((radio) => {
				radio.disabled = false;
			});
		}
	}

	_toggleMaxSessionField(e) {
		const concurrent_sessions = typeof e.target.value === 'undefined' ? this.max_session_checked : e.target.value;
		const maxsessions = this.form.querySelector('#maxsessions');

		if (concurrent_sessions === 'one' || concurrent_sessions === 'unlimited') {
			maxsessions.style.display = 'none';
		}
		else {
			maxsessions.style.display = '';
			maxsessions.focus();
		}
	}

	_loadTypeFields(event) {
		if (event.target.value) {
			this.type = parseInt(event.target.value);
		}

		let show_fields = [];

		switch (this.type) {
			case <?= MEDIA_TYPE_EMAIL ?>:
				show_fields = [
					'#email-provider-label', '#email-provider-field'
				];

				// Load provider fields.
				const provider = this.form.querySelector('#provider');

				provider.onchange = (e) => {
					const change = typeof e.detail === 'undefined' ? true : e.detail.change;
					this._loadProviderFields(change, parseInt(provider.value));
				};

				provider.dispatchEvent(new CustomEvent('change', {detail: {change: false}}));
				break;

			case <?= MEDIA_TYPE_SMS ?>:
				show_fields = [
					'#gsm_modem_label', '#gsm_modem_field'
				];
				break;

			case <?= MEDIA_TYPE_EXEC ?>:
				show_fields = [
					'#exec-path-label', '#exec-path-field', '#row_exec_params_label', '#row_exec_params_field'
				];
				break;

			case <?= MEDIA_TYPE_WEBHOOK ?>:
				show_fields = [
					'#webhook_parameters_label', '#webhook_parameters_field', '#webhook_script_label',
					'#webhook_script_field', '#webhook_timeout_label', '#webhook_timeout_field', '#webhook_tags_label',
					'#webhook_tags_field', '#webhook_event_menu_label', '#webhook_event_menu_field',
					'#webhook_url_name_label', '#webhook_url_name_field', '#webhook_event_menu_url_label',
					'#webhook_event_menu_url_field'
				];
				break;
		}

		show_fields.forEach((field) => {
			this.form.querySelector(field).style.display = '';
		});

		this._setMaxSessionsType(this.type);
		this.form.querySelector('#maxsessions_type').dispatchEvent(new Event('change'));
	}

	/**
	 * Sets values to form fields and checks checkboxes according to the media type provider.
	 *
	 * @param {number} provider  Selected provider value.
	 */
	_adjustDataByProvider(provider) {
		const providers = this.mediatype.providers;

		this.form.querySelector('#smtp_username').value = '';
		this.form.querySelector('#smtp_verify_host').checked = providers[provider]['smtp_verify_host'];
		this.form.querySelector('#smtp_verify_peer').checked = providers[provider]['smtp_verify_peer'];
		this.form.querySelector('#smtp_port').value = providers[provider]['smtp_port'];
		this.form.querySelector('#smtp_email').value = providers[provider]['smtp_email'];
		this.form.querySelector('#smtp_server').value = providers[provider]['smtp_server'];
		this.form.querySelector(`input[name=smtp_security][value='${providers[provider]['smtp_security']}']`)
			.checked = true;
		this.form.querySelector(`input[name=content_type][value='${providers[provider]['content_type']}']`)
			.checked = true;
		this.form.querySelector(
			`input[name=smtp_authentication][value='${providers[provider]['smtp_authentication']}']`
		).checked = true;
	}

	_loadProviderFields(change, provider) {
		this.provider = provider;
		let show_fields = [];
		this._hideFormFields('email');

		if (change) {
			this._adjustDataByProvider(provider);
		}

		const authentication = this.form.querySelector('#smtp_authentication');

		authentication.onchange = () => {
			this._loadAuthenticationFields(provider);
		};

		authentication.dispatchEvent(new Event('change'));

		switch (provider) {
			case <?= CMediatypeHelper::EMAIL_PROVIDER_SMTP ?>:
				show_fields = [
					'#email-provider-label', '#email-provider-field', '#smtp-server-label', '#smtp-server-field',
					'#smtp-port-label', '#smtp-port-field', '#smtp-email-label', '#smtp-email-field',
					'#smtp-helo-label', '#smtp-helo-field', '#smtp-security-label', '#smtp-security-field',
					'#smtp-authentication-label', '#smtp-authentication-field', '#content_type_label',
					'#content_type_field'
				];

				const smtp_security = this.form.querySelector('#smtp_security');

				smtp_security.onchange = () => {
					this._loadSmtpSecurityFields();
				};

				smtp_security.dispatchEvent(new Event('change'));
				break;

			case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL ?>:
			case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365 ?>:
				show_fields = [
					'#smtp-email-label', '#smtp-email-field', '#passwd_label', '#passwd_field', '#content_type_label',
					'#content_type_field'
				];
				break;

			case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY ?>:
			case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY ?>:
				show_fields = [
					'#smtp-email-label', '#smtp-email-field', '#smtp-authentication-label',
					'#smtp-authentication-field', '#content_type_label', '#content_type_field'
				];
				break;
		}

		show_fields.forEach((field) => {
			this.form.querySelector(field).style.display = '';
		});
	}

	_loadSmtpSecurityFields() {
		const smtp_security = this.form.querySelector(`input[name='smtp_security']:checked`).value;

		if (parseInt(this.type) === <?= MEDIA_TYPE_EMAIL ?>) {
			switch (parseInt(smtp_security)) {
				case <?= SMTP_CONNECTION_SECURITY_NONE ?>:
					this.form.querySelector('#smtp_verify_peer').checked = false;
					this.form.querySelector('#smtp_verify_host').checked = false;

					const hide_fields = [
						'#verify-peer-label', '#verify-peer-field',
						'#verify-host-label', '#verify-host-field'
					];

					hide_fields.forEach((field) => {
						this.form.querySelector(field).style.display = 'none';
					});
					break;

				case <?= SMTP_CONNECTION_SECURITY_STARTTLS ?>:
				case <?= SMTP_CONNECTION_SECURITY_SSL_TLS ?>:
					const show_fields = [
						'#verify-peer-label', '#verify-peer-field',
						'#verify-host-label', '#verify-host-field'
					];

					show_fields.forEach((field) => {
						this.form.querySelector(field).style.display = '';
					});
					break;
			}
		}
	}

	_loadAuthenticationFields(provider) {
		const authentication = this.form.querySelector(`input[name='smtp_authentication']:checked`).value;
		const passwd_label = this.form.querySelector('#passwd_label');
		const passwd = this.form.querySelector('#passwd');
		const smtp_auth_1 = this.form.querySelector(`label[for= 'smtp_authentication_1']`);

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

							const hide_fields = [
								'#smtp-username-label', '#smtp-username-field',
								'#passwd_label', '#passwd_field'
							];

							hide_fields.forEach((field) => {
								this.form.querySelector(field).style.display = 'none';
							});
							break;

						case <?= SMTP_AUTHENTICATION_NORMAL ?>:
							const show_fields = [
								'#smtp-username-label', '#smtp-username-field',
								'#passwd_label', '#passwd_field'
							];

							show_fields.forEach((field) => {
								this.form.querySelector(field).style.display = '';
							});
							break;
					}
					break;

				case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY ?>:
				case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY ?>:
					smtp_auth_1.innerHTML = <?= json_encode(_('Email and password')) ?>;

					switch (parseInt(authentication)) {
						case <?= SMTP_AUTHENTICATION_NONE ?>:
							this.form.querySelector('#passwd').value = '';

							const hide_fields = [
								'#smtp-username-label', '#smtp-username-field',
								'#passwd_label', '#passwd_field'
							];

							hide_fields.forEach((field) => {
								this.form.querySelector(field).style.display = 'none';
							});
							break;

						case <?= SMTP_AUTHENTICATION_NORMAL ?>:
							const show_fields = [
								'#passwd_label', '#passwd_field'
							];

							show_fields.forEach((field) => {
								this.form.querySelector(field).style.display = '';
							});
							break;
					}
			}
		}
	}

	_hideFormFields(type) {
		let fields = [];

		if (type === 'email') {
			fields = [
				'#smtp-username-label', '#smtp-username-field', '#smtp-server-label', '#smtp-server-field',
				'#smtp-port-label', '#smtp-port-field', '#smtp-email-label', '#smtp-email-field', '#smtp-helo-label',
				'#smtp-helo-field', '#smtp-security-label', '#smtp-security-field', '#verify-peer-label',
				'#verify-peer-field', '#verify-host-label', '#verify-host-field', '#passwd_label', '#passwd_field',
				'#smtp-authentication-label', '#smtp-authentication-field'
			];
		}

		else if (type === 'all') {
			fields = [
				'#email-provider-label', '#email-provider-field', '#smtp-server-label', '#smtp-server-field',
				'#smtp-port-label', '#smtp-port-field', '#smtp-email-label', '#smtp-email-field', '#smtp-helo-label',
				'#smtp-helo-field', '#smtp-security-label', '#smtp-security-field', '#verify-peer-label',
				'#verify-peer-field', '#verify-host-label', '#verify-host-field', '#smtp-authentication-label',
				'#smtp-authentication-field', '#smtp-username-label', '#smtp-username-field', '#exec-path-label',
				'#exec-path-field', '#row_exec_params_label', '#row_exec_params_field', '#gsm_modem_label',
				'#gsm_modem_field', '#passwd_label', '#passwd_field', '#content_type_label', '#content_type_field',
				'#webhook_parameters_label', '#webhook_parameters_field', '#webhook_script_label',
				'#webhook_script_field', '#webhook_timeout_label', '#webhook_timeout_field', '#webhook_tags_label',
				'#webhook_tags_field', '#webhook_event_menu_label', '#webhook_event_menu_field',
				'#webhook_url_name_label', '#webhook_url_name_field', '#webhook_event_menu_url_label',
				'#webhook_event_menu_url_field'
			];
		}

		fields.forEach((field) => {
			this.form.querySelector(field).style.display = 'none';
		});
	}
}
