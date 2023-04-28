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
 */
?>

window.mediatype_edit_popup = new class {

	init({mediatype}) {
		this.overlay = overlays_stack.getById('media-type-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this._loadView(mediatype);

		document.querySelector('#type').dispatchEvent(new Event('change'));
	}

	/**
	 * Compiles necessary fields for popup based on mediatype data.
	 */
	_loadView(mediatype) {
		this.type = parseInt(mediatype.type);

		// Load type fields.
		document.querySelector('#type').onchange = (e) => {
			this._hideFormFields('all');
			this._loadTypeFields(e);
		};
	}

	/**
	 * Displays or hides fields in the popup based on the value of selected type.
	 */
	_loadTypeFields(event) {
		if (event.target.value) {
			this.type = parseInt(event.target.value);
		}

		let show_fields = []

		switch (this.type) {
			case <?= MEDIA_TYPE_EMAIL ?>:
				show_fields = [
					'#email-provider-label', '#email-provider-field'
				];

				const provider = this.form.querySelector('#provider');

				provider.onchange = () => {
					this._loadProviderFields(parseInt(provider.value));
				};

				provider.dispatchEvent(new Event('change'));
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

		show_fields.forEach((field) => {
			this.form.querySelector(field).style.display = '';
		});
	}

	/**
	 * Displays or hides fields in the popup based on the value of selected email provider.
	 */
	_loadProviderFields(provider) {
		let show_fields = [];
		this._hideFormFields('email');

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

	_hideFormFields(type) {
		let fields = [];

		if (type === 'email') {
			fields = [
				'#smtp-server-label', '#smtp-server-field',
				'#smtp-port-label', '#smtp-port-field',
				'#smtp-email-label', '#smtp-email-field',
				'#smtp-helo-label', '#smtp-helo-field',
				'#smtp-security-label', '#smtp-security-field',
				'#verify-peer-label', '#verify-peer-field',
				'#verify-host-label', '#verify-host-field',
				'#passwd_label', '#passwd_field',
				'#smtp-authentication-label', '#smtp-authentication-field'
			];
		}

		if (type === 'all') {
			fields = [
				'#email-provider-label', '#email-provider-field',
				'#smtp-server-label', '#smtp-server-field',
				'#smtp-port-label', '#smtp-port-field',
				'#smtp-email-label', '#smtp-email-field',
				'#smtp-helo-label', '#smtp-helo-field',
				'#smtp-security-label', '#smtp-security-field',
				'#verify-peer-label', '#verify-peer-field',
				'#verify-host-label', '#verify-host-field',
				'#smtp-authentication-label', '#smtp-authentication-field',
				'#smtp-username-label', '#smtp-username-field',
				'#exec-path-label', '#exec-path-field',
				'#row_exec_params_label', '#row_exec_params_field',
				'#gsm_modem_label', '#gsm_modem_field',
				'#passwd_label', '#passwd_field',
				'#content_type_label', '#content_type_field',
				'#webhook_parameters_label', '#webhook_parameters_field',
				'#webhook_script_label', '#webhook_script_field',
				'#webhook_timeout_label', '#webhook_timeout_field',
				'#webhook_tags_label', '#webhook_tags_field',
				'#webhook_event_menu_label', '#webhook_event_menu_field',
				'#webhook_url_name_label', '#webhook_url_name_field',
				'#webhook_event_menu_url_label', '#webhook_event_menu_url_field'
			];
		}

		fields.forEach((field) => {
			this.form.querySelector(field).style.display = 'none';
		});
	}

}
