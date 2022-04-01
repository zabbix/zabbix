<?php declare(strict_types = 1);
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


/**
 * @var CView $this
 */
?>

window.ldap_edit_popup = {
	overlay: null,
	dialogue: null,
	form: null,
	advanced_chbox: null,

	init() {
		this.overlay = overlays_stack.getById('ldap_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.advanced_chbox = document.getElementById('advanced_configuration');

		this.advanced_chbox.addEventListener('change', (e) => {
			this.onChangeAdvancedConfiguration(e.target.checked);
		});

		this.onChangeAdvancedConfiguration(this.advanced_chbox.checked);

		if (document.getElementById('bind-password-btn') !== null) {
			document.getElementById('bind-password-btn').addEventListener('click', this.bindPasswordBtnOnClick);
		}
	},

	onChangeAdvancedConfiguration(checked) {
		[...this.form.querySelectorAll('.advanced-configuration')].forEach(element => {
			element.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !checked);
		});
	},

	bindPasswordBtnOnClick(e) {
		const form_field = e.target.parentNode;
		form_field.innerHTML = '';

		const password_field = new Template(`<?= (new CPassBox('bind_password', ''))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->toString();
		?>`);
		form_field.insertAdjacentHTML('beforeend', password_field.evaluate({}));
	},

	openTestPopup() {
		const popup_params = {
			host: this.form.host.value,
			port: this.form.port.value,
			base_dn: this.form.base_dn.value,
			search_attribute: this.form.search_attribute.value,
			bind_dn: this.form.bind_dn.value,
			bind_password: this.form.bind_password.value,
			case_sensitive: this.form.case_sensitive.checked ? 1 : 0
		};

		if (this.advanced_chbox.checked) {
			popup_params['start_tls'] = this.form.start_tls.checked ? 1 : 0;
			popup_params['userfilter'] = this.form.userfilter.value;
		}

		const test_overlay = PopUp('popup.ldap.test.edit', popup_params, {dialogueid: 'ldap_test_edit'});

		test_overlay.$dialogue[0].addEventListener('overlay.close', () => {
			this.overlay.unsetLoading();
		}, {once: true});
	},

	submit() {
		this.removePopupMessages();
		this.overlay.setLoading();

		const curl = new Curl(this.form.getAttribute('action'), false);
		const fields = getFormFields(this.form);

		if (fields.advanced_configuration != 1) {
			delete fields.userfilter;
			delete fields.start_tls;
		}

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
				else {
					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				}
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	removePopupMessages() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	},

	ajaxExceptionHandler: (exception) => {
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

		ldap_edit_popup.form.parentNode.insertBefore(message_box, ldap_edit_popup.form);
	}
};
