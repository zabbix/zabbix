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

		this._loadView(mediatype);
		this._initActions();

		document.querySelector('#type').dispatchEvent(new Event('change'));
	}

	_initActions() {
		// todo implement this functionality after message template popup submit is implemented:
		// var limit_reached = (Object.keys(message_template_list).length == Object.keys(message_templates).length);
		// jQuery('#message-templates-footer .btn-link')
		//	.prop('disabled', limit_reached)
		//	.text(limit_reached
		//		? <?php // = json_encode(_('Add (message type limit reached)')) ?>
		//		: <?php // = json_encode(_('Add')) ?>
		//	);

		for (const parameter of this.mediatype.parameters_webhook) {
			this._addWebhookParam(parameter);
		}

		for (const parameter of this.mediatype.parameters_exec) {
			this._addExecParam(parameter);
			this.row_num ++;
		}

		const event_menu = this.form.querySelector('#show_event_menu');

		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-remove')) {
				e.target.closest('tr').remove();
			}
		});

		this._initMessageTemplates();

		document.querySelector("#message-templates > table").addEventListener('click', (e) => {
			if (e.target.classList.contains('js-remove')) {
				e.target.closest('tr').remove();
			}
		});

		this.form.querySelector('.element-table-add').addEventListener('click', () => {
			this._addExecParamsRow();
			this.row_num ++;
		});

		this.form.querySelector('.webhook-param-add').addEventListener('click', () => {
			this._addWebhookParamsRow();
		});

		event_menu.onchange = () => {
			this._toggleEventMenuFields(event_menu);
		};

		event_menu.dispatchEvent(new Event('change'));

		if (this.form.querySelector('#chPass_btn') !== null) {
			this.form.querySelector('#chPass_btn').addEventListener('click', () => {
				this._toggleChangePswdButton();
			});
		}
	}

	_toggleChangePswdButton() {
		if (this.form.querySelector('#chPass_btn') !== null) {
			this.form.querySelector('#chPass_btn').style.display = "none";
			this.form.querySelector('#passwd').style.display = 'block';
			this.form.querySelector('#passwd').disabled = false;
			this.form.querySelector('#passwd').focus();
		}
	}

	_addWebhookParam(parameter) {
		const template = new Template(this.form.querySelector('#webhook_params_template').innerHTML);

		this.form
			.querySelector('#parameters_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(parameter));
	}

	_addExecParam(parameter) {
		parameter.row_num = this.row_num;
		const template = new Template(this.form.querySelector('#exec_params_template').innerHTML);

		this.form
			.querySelector('#exec_params_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(parameter));
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

		// todo - after all fields added/fixed : check if works as expected
		for (let key in fields) {
			if (typeof fields[key] === 'string') {
				fields[key] = fields[key].trim();
			}
		}

		const maxsessions_type = document.querySelector('input[name="maxsessions_type"]:checked').value;
		const maxsessions = document.querySelector('#maxsessions').value;

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

				let title;
				let messages;

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

	_initMessageTemplates() {
		document.querySelector('#message-templates').addEventListener('click', function(event) {
			var target = event.target;
			if (target.hasAttribute('data-action')) {
				var btn = target;
				var params = {
					type: document.querySelector('#type').value,
					content_type: document.querySelector('input[name="content_type"]:checked').value,
					message_types: Array.from(document.querySelectorAll('tr[data-message-type]')).map(function(tr) {
						return tr.dataset.messageType;
					})
				};

				switch (btn.dataset.action) {
					case 'add':
						PopUp('popup.mediatype.message', params, {
							dialogue_class: 'modal-popup-medium',
							trigger_element: target
						});
						break;

					case 'edit':
						var row = btn.closest('tr');

						params.message_type = row.dataset.messageType;
						params.old_message_type = params.message_type;

						Array.from(row.querySelectorAll('input[type="hidden"]')).forEach(function(input) {
							const name = input.getAttribute('name').match(/\[([^\]]+)]$/);

							if (name) {
								params[name[1]] = input.value;
							}
						});

						PopUp('popup.mediatype.message', params, {
							dialogue_class: 'modal-popup-medium',
							trigger_element: target
						});
						break;
				}
			}
		});
	}

	_addExecParamsRow() {
		const template = new Template(this.form.querySelector('#exec_params_template').innerHTML);

		this.form
			.querySelector('#exec_params_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate({row_num: this.row_num}));
	}

	_addWebhookParamsRow() {
		const template = new Template(this.form.querySelector('#webhook_params_template').innerHTML);

		this.form
			.querySelector('#parameters_table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate({}));
	}

	_toggleEventMenuFields(event_menu) {
		const event_menu_name = this.form.querySelector('#event_menu_name');
		const event_menu_url = this.form.querySelector('#event_menu_url');

		if (event_menu.checked) {
			event_menu.value = <?= ZBX_EVENT_MENU_SHOW ?>;
			event_menu_name.disabled = false;
			event_menu_url.disabled = false;
		}
		else {
			event_menu.value = <?= ZBX_EVENT_MENU_HIDE ?>;
			event_menu_name.disabled = true;
			event_menu_url.disabled = true;
		}
	}

	/**
	 * Compiles necessary fields for popup based on mediatype data.
	 */
	_loadView(mediatype) {
		this.type = parseInt(mediatype.type);
		this.smtp_security = mediatype.smtp_security;
		this.authentication = mediatype.smtp_authentication;

		// Load type fields.
		document.querySelector('#type').onchange = (e) => {
			this._hideFormFields('all');
			this._loadTypeFields(e);

			this.form.querySelector('#smtp_authentication').dispatchEvent(new Event('change'));
			this.form.querySelector('#smtp_security').dispatchEvent(new Event('change'));
		};

		const max_sessions = this.form.querySelector('#maxsessions_type');
		this.max_session_checked = document.querySelector('input[name="maxsessions_type"]:checked').value;

		max_sessions.onchange = (e) => {
			this._loadMaxSessionField(e);
		};

		max_sessions.dispatchEvent(new Event('change'));
	}

	/**
	 * Set concurrent sessions accessibility.
	 *
	 * @param {number} media_type		Selected media type.
	 */
	_setMaxSessionsType(media_type) {
		const maxsessions_type = document.querySelectorAll('#maxsessions_type input[type="radio"]');

		if (media_type == <?= MEDIA_TYPE_SMS ?>) {
			maxsessions_type.forEach(function (radio) {
				radio.disabled = true;
				if (radio.value === "one") {
					radio.disabled = false;
				}
			});
		}
		else {
			maxsessions_type.forEach(function (radio) {
				radio.disabled = false;
			});
		}
	}

	_loadMaxSessionField(e) {
		const concurrent_sessions = typeof e.target.value === 'undefined' ? this.max_session_checked : e.target.value;
		const maxsessions = this.form.querySelector("#maxsessions");

		if (concurrent_sessions === 'one' || concurrent_sessions === 'unlimited') {
			maxsessions.style.display = 'none';
		}
		else {
			maxsessions.style.display = '';
			maxsessions.focus();
		}
	}

	/**
	 * Displays or hides fields in the popup based on the value of selected type.
	 */
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

				provider.dispatchEvent(new CustomEvent('change', {detail: {change:false}}));
				break;

			case <?= MEDIA_TYPE_SMS ?>:
				show_fields = [
					'#gsm_modem_label', '#gsm_modem_field'
				];
				break;

			case <?= MEDIA_TYPE_EXEC ?>:
				show_fields = [
					'#exec-path-label', '#exec-path-field',
					'#row_exec_params_label', '#row_exec_params_field'
				];
				break;

			case <?= MEDIA_TYPE_WEBHOOK ?>:
				show_fields = [
					'#webhook_parameters_label', '#webhook_parameters_field',
					'#webhook_script_label', '#webhook_script_field',
					'#webhook_timeout_label', '#webhook_timeout_field',
					'#webhook_tags_label', '#webhook_tags_field',
					'#webhook_event_menu_label', '#webhook_event_menu_field',
					'#webhook_url_name_label', '#webhook_url_name_field',
					'#webhook_event_menu_url_label', '#webhook_event_menu_url_field'
				];
				break;
		}

		this._setMaxSessionsType(this.type);

		show_fields.forEach((field) => {
			this.form.querySelector(field).style.display = '';
		});
	}

	_adjustDataByProvider(provider) {
		const providers = this.mediatype.providers;

		this.form.querySelector('#smtp_username').value = '';
		this.form.querySelector('#smtp_verify_host').checked = providers[provider]['smtp_verify_host'];
		this.form.querySelector('#smtp_verify_peer').checked = providers[provider]['smtp_verify_peer'];
		this.form.querySelector('#smtp_port').value = providers[provider]['smtp_port'];
		this.form.querySelector('#smtp_email').value = providers[provider]['smtp_email'];
		this.form.querySelector('#smtp_server').value = providers[provider]['smtp_server'];
		this.form.querySelector('input[name=smtp_security][value="' + providers[provider]['smtp_security'] + '"]')
			.checked = true;
		this.form.querySelector('input[name=content_type][value="' + providers[provider]['content_type'] + '"]')
			.checked = true;
		this.form.querySelector(
			'input[name=smtp_authentication][value="' + providers[provider]['smtp_authentication'] + '"]'
		).checked = true;
	}

	/**
	 * Displays or hides fields in the popup based on the value of selected email provider.
	 */
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
					'#email-provider-label', '#email-provider-field',
					'#smtp-server-label', '#smtp-server-field',
					'#smtp-port-label', '#smtp-port-field',
					'#smtp-email-label', '#smtp-email-field',
					'#smtp-helo-label', '#smtp-helo-field',
					'#smtp-security-label', '#smtp-security-field',
					'#smtp-authentication-label', '#smtp-authentication-field',
					'#content_type_label', '#content_type_field'
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
					'#smtp-email-label', '#smtp-email-field',
					'#passwd_label', '#passwd_field',
					'#content_type_label', '#content_type_field'
				];
				break;

			case <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY ?>:
			case <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY ?>:

				show_fields = [
					'#smtp-email-label', '#smtp-email-field',
					'#smtp-authentication-label', '#smtp-authentication-field',
					'#content_type_label', '#content_type_field'
				];
				break;
		}

		show_fields.forEach((field) => {
			this.form.querySelector(field).style.display = '';
		});
	}

	_loadSmtpSecurityFields() {
		let smtp_security = this.form.querySelector('input[name="smtp_security"]:checked').value;

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
		let authentication = this.form.querySelector('input[name="smtp_authentication"]:checked').value;
		const passwd_label = this.form.querySelector('#passwd_label');
		const passwd = this.form.querySelector('#passwd');
		const smtp_auth_1 = this.form.querySelector(`label[for= 'smtp_authentication_1']`);

		passwd_label.setAttribute('class', '<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
		passwd.setAttribute('aria-required', 'true');

		if (parseInt(this.type) === <?= MEDIA_TYPE_EMAIL ?>) {
			if (parseInt(provider) === <?= CMediatypeHelper::EMAIL_PROVIDER_SMTP ?>) {
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
			}

			if (provider === <?= CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY ?>
				|| provider === <?= CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY ?>) {
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

		if (type === 'all') {
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
