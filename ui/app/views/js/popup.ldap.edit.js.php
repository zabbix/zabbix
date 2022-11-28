<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


?>

window.ldap_edit_popup = new class {

	constructor() {
		this.overlay = null;
		this.dialogue = null;
		this.form = null;
		this.advanced_chbox = null;
	}

	init() {
		this.overlay = overlays_stack.getById('ldap_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.advanced_chbox = document.getElementById('advanced_configuration');

		this.advanced_chbox.addEventListener('change', (e) => {
			this.toggleAdvancedConfiguration(e.target.checked);
		});

		this.toggleAdvancedConfiguration(this.advanced_chbox.checked);

		if (document.getElementById('bind-password-btn') !== null) {
			document.getElementById('bind-password-btn').addEventListener('click', this.showPasswordField);
		}
	}

	toggleAdvancedConfiguration(checked) {
		for (const element of this.form.querySelectorAll('.advanced-configuration')) {
			element.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !checked);
		}
	}

	showPasswordField(e) {
		const form_field = e.target.parentNode;
		const password_field = form_field.querySelector('[name="bind_password"][type="password"]');
		const password_var = form_field.querySelector('[name="bind_password"][type="hidden"]');

		password_field.style.display = '';
		password_field.disabled = false;

		if (password_var !== null) {
			form_field.removeChild(password_var);
		}
		form_field.removeChild(e.target);
	}

	openTestPopup() {
		const fields = this.preprocessFormFields(getFormFields(this.form));

		const popup_params = {
			host: fields.host,
			port: fields.port,
			base_dn: fields.base_dn,
			bind_dn: fields.bind_dn,
			search_attribute: fields.search_attribute
		};

		const optional_fields = ['userdirectoryid', 'bind_password', 'start_tls', 'search_filter'];

		for (const field of optional_fields) {
			if (fields[field] !== undefined) {
				popup_params[field] = fields[field];
			}
		}

		const test_overlay = PopUp('popup.ldap.test.edit', popup_params, {dialogueid: 'ldap_test_edit'});
		test_overlay.xhr.then(() => this.overlay.unsetLoading());
	}

	submit() {
		this.removePopupMessages();
		this.overlay.setLoading();

		const fields = this.preprocessFormFields(getFormFields(this.form));
		const curl = new Curl(this.form.getAttribute('action'), false);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(fields)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
			})
			.catch((exception) => {
				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = <?= json_encode(_('Unexpected server error.')) ?>;
				}

				const message_box = makeMessageBox('bad', messages, title, true, true)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	removePopupMessages() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	preprocessFormFields(fields) {
		this.trimFields(fields);

		if (fields.advanced_configuration != 1) {
			delete fields.start_tls;
			delete fields.search_filter;
		}

		delete fields.advanced_configuration;

		return fields;
	}

	trimFields(fields) {
		const fields_to_trim = ['name', 'host', 'base_dn', 'bind_dn', 'search_attribute', 'search_filter',
			'description'];
		for (const field of fields_to_trim) {
			if (field in fields) {
				fields[field] = fields[field].trim();
			}
		}
	}
}();
