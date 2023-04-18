<?php
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

window.script_edit_popup = new class {

	init({script}) {
		this.overlay = overlays_stack.getById('script-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.script = script;
		this.scriptid = script.scriptid;

		this._loadView(script);
		this._hideFields('all');
		this.form.removeAttribute('style');

		document.querySelector('#scope').dispatchEvent(new Event('change'));
		document.querySelector('#type').dispatchEvent(new Event('change'));
		document.querySelector('#enable-confirmation').dispatchEvent(new Event('change'))
	}

	_loadView(script) {
		const that = this;
		this.scope = parseInt(script.scope);
		this.type = parseInt(script.type);
		this.confirmation = script.enable_confirmation;

		// Load scope fields.
		document.querySelector('#scope').onchange = function (e) {
			that._hideFields('all');
			that._loadScope(e);
			document.querySelector('#type').dispatchEvent(new Event('change'));
		}

		// Load type fields.
		document.querySelector('#type').onchange = function (e) {
			that._loadType(script, e);
		}

		// Update confirmation fields
		document.querySelector('#enable-confirmation').onchange = function (e) {
			that._confirmationFields(e);
		}

		// todo - rewrite jqueries to vanilla js

		// test confirmation button
		$('#test-confirmation').click(function() {
			executeScript(null, $('#confirmation').val(), this);
		});

		// host group selection
		$('#hgstype-select')
			.change(function() {
				if ($('#hgstype-select').val() == 1) {
					$('#host-group-selection').show();
				}
				else {
					$('#host-group-selection').hide();
				}
			})
			.change();
	}

	clone({title, buttons}) {
		this.scriptid = null;

		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
	}

	delete() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'script.delete');
		curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
			<?= json_encode(CCsrfTokenHelper::get('script'), JSON_THROW_ON_ERROR) ?>
		);

		this._post(curl.getUrl(), {scriptids: [this.scriptid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response.success}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		for (let key in fields) {
			if (typeof fields[key] === 'string') {
				fields[key] = fields[key].trim();
			}
		}

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this.scriptid === null ? 'script.create' : 'script.update');

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

	_loadScope(event) {
		if (event.target.value) {
			this.scope = parseInt(event.target.value);
		}

		switch (this.scope) {
			case <?= ZBX_SCRIPT_SCOPE_HOST ?>:
			case <?= ZBX_SCRIPT_SCOPE_EVENT ?>:
				let show_fields = [
					'#menu-path', '#menu-path-label', '#usergroup-label', '#usergroup', '#host-access-label',
					'#host-access-field', '#enable-confirmation-label', '#enable-confirmation-field',
					'#confirmation-label', '#confirmation-field'
				]

				show_fields.forEach((field) => {
					document.querySelector(field).style.display = '';
				})

				document.querySelector('#type input[type="radio"][value="6"]').closest('li').style.display = '';

				break;

			case <?= ZBX_SCRIPT_SCOPE_ACTION ?>:
				let hide_fields = ['#menu-path', '#menu-path-label'];

				hide_fields.forEach((field) => {
					document.querySelector(field).style.display = 'none';
				})

				document.querySelector('#type input[type="radio"][value="6"]').closest('li').style.display = 'none';

				if (document.querySelector('input[name="type"]:checked').value == <?= ZBX_SCRIPT_TYPE_URL ?>) {
					const webhook = document.querySelector(`#type [value="${<?= ZBX_SCRIPT_TYPE_WEBHOOK ?>}"]`);
					webhook.checked = true;

					this.type = parseInt(<?= ZBX_SCRIPT_TYPE_WEBHOOK ?>);
				}
				break;
		}
	}

	_loadType(script, event) {
		if (event.target.value) {
			this.type = parseInt(event.target.value);
		}

		let show_fields = [];
		let hide_fields = [
			'#command-ipmi-label', '#command-ipmi', '#webhook-parameters', '#webhook-parameters-label',
			'#js-item-script-field', '#script-label', '#timeout-label', '#timeout', '#auth-type-label', '#auth-type',
			'#username-label', '#username-field', '#password-label', '#password-field', '#publickey-label',
			'#publickey-field', '#privatekey-label', '#privatekey-field', '#passphrase-label', '#passphrase-field',
			'#port-label', '#port-field', '#url', '#url-label', '#new-window-label', '#new-window', '#execute-on-label',
			'#execute-on', '#commands-label', '#commands'
		];

		hide_fields.forEach((field) => {
			document.querySelector(field).style.display = 'none';
		})

		switch (this.type) {
			case <?= ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT ?>:
				if (document.querySelector('#commandipmi').value !== '') {
					document.querySelector('#command').value = document.querySelector('#commandipmi').value;
					document.querySelector('#commandipmi').value = '';
				}

				show_fields = ['#execute-on-label', '#execute-on', '#commands-label', '#commands'];
				break;

			case <?= ZBX_SCRIPT_TYPE_IPMI ?>:
				if (document.querySelector('#command').value !== '') {
					document.querySelector('#commandipmi').value = document.querySelector('#command').value;
					document.querySelector('#command').value = '';
				}

				show_fields = ['#command-ipmi-label', '#command-ipmi'];
				break;

			case <?= ZBX_SCRIPT_TYPE_SSH ?>:
				if (document.querySelector('#commandipmi').value !== '') {
					document.querySelector('#command').value = document.querySelector('#commandipmi').value;
					document.querySelector('#commandipmi').value = '';
				}

				show_fields = [
					'#auth-type-label', '#auth-type', '#username-label', '#username-field', '#port-label',
					'#port-field', '#commands-label', '#commands'
				];

				// Load authentication fields.
				this.authtype = parseInt(script.authtype);
				const that = this;

				document.querySelector('#authtype').onchange = function (e) {
					that._authFields(e);
				}

				document.querySelector('#authtype').dispatchEvent(new Event('change'));

				break;

			case <?= ZBX_SCRIPT_TYPE_TELNET ?>:
				if (document.querySelector('#commandipmi').value !== '') {
					document.querySelector('#command').value = document.querySelector('#commandipmi').value;
					document.querySelector('#commandipmi').value = '';
				}

				show_fields = [
					'#username-label', '#username-field', '#port-label', '#port-field', '#password-label',
					'#password-field', '#commands-label', '#commands'
				];
				break;

			case <?= ZBX_SCRIPT_TYPE_WEBHOOK ?>:
				show_fields = [
					'#webhook-parameters', '#webhook-parameters-label', '#js-item-script-field', '#script-label',
					'#timeout-label', '#timeout'
				];
				break;

			case <?= ZBX_SCRIPT_TYPE_URL ?>:
				show_fields = ['#url', '#url-label', '#new-window-label', '#new-window'];
				break;
		}

		show_fields.forEach((field) => {
			document.querySelector(field).style.display = '';
		})
	}

	_authFields(e) {
		this._hideFields('auth');

		let show_fields = [];

		if (e.target.value) {
			this.authtype = parseInt(e.target.value);
		}

		switch (this.authtype) {
			case <?= ITEM_AUTHTYPE_PASSWORD ?>:
				show_fields = ['#password-label', '#password-field', '#commands-label', '#commands'];
				break;

			case <?= ITEM_AUTHTYPE_PUBLICKEY ?>:
				show_fields = [
					'#publickey-label', '#publickey-field', '#privatekey-label', '#privatekey-field',
					'#passphrase-label', '#passphrase-field'
				]
				break;
		}

		show_fields.forEach((field) => {
			document.querySelector(field).style.display = '';
		})
	}

	_confirmationFields(e) {
		if (e.target.value) {
			this.confirmation = e.target.checked;
		}

		if (this.confirmation) {
			document.querySelector('#confirmation').removeAttribute('disabled');

			document.querySelector('#confirmation').onkeyup = function () {
				if (document.querySelector('#confirmation').value != '') {
					document.querySelector('#test-confirmation').removeAttribute('disabled');
				}
				else {
					document.querySelector('#test-confirmation').setAttribute('disabled', 'disabled');
				}
			}

			document.querySelector('#confirmation').dispatchEvent(new Event('keyup'));
		}
		else {
			document.querySelector('#confirmation').setAttribute('disabled', 'disabled');
			document.querySelector('#test-confirmation').setAttribute('disabled', 'disabled');
		}
	}

	_hideFields(type) {
		let fields = [];

		if (type === 'auth') {
			fields = [
				'#privatekey-label', '#privatekey-field', '#privatekey-label', '#privatekey-field', '#passphrase-label',
				'#passphrase-field', '#publickey-label', '#publickey-field', '#password-label', '#password-field'
			];
		}

		if (type === 'all') {
			document.querySelector('#type input[type="radio"][value="6"]').closest('li').style.display = 'none';

			fields = [
				'#menu-path', '#menu-path-label',
				'#url', '#url-label',
				'#new-window-label', '#new-window',
				'#webhook-parameters', '#webhook-parameters-label',
				'#js-item-script-field', '#script-label',
				'#timeout-label', '#timeout',
				'#commands-label', '#commands',
				'#command-ipmi-label', '#command-ipmi',
				'#auth-type-label', '#auth-type',
				'#username-label', '#username-field',
				'#password-label', '#password-field',
				'#port-label', '#port-field',
				'#publickey-label', '#publickey-field',
				'#privatekey-label', '#privatekey-field',
				'#passphrase-label', '#passphrase-field',
				'#usergroup-label', '#usergroup',
				'#host-access-label', '#host-access-field',
				'#enable-confirmation-label', '#enable-confirmation-field',
				'#confirmation-label', '#confirmation-field'
			];
		}

		fields.forEach((field) => {
			document.querySelector(field).style.display = 'none';
		})
	}
}

